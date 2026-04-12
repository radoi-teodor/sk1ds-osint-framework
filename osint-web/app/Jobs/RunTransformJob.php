<?php

namespace App\Jobs;

use App\Models\Graph;
use App\Models\GraphNode;
use App\Models\InvestigationJob;
use App\Models\TransformationRun;
use App\Services\EngineClient;
use App\Support\ApiKeyResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RunTransformJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 1;

    public function __construct(public int $jobId) {}

    public function handle(EngineClient $engine): void
    {
        /** @var InvestigationJob $job */
        $job = InvestigationJob::findOrFail($this->jobId);
        $graph = Graph::find($job->graph_id);
        if (! $graph) {
            $job->update(['status' => InvestigationJob::STATUS_FAILED, 'error' => 'Graph not found', 'finished_at' => now()]);
            return;
        }

        $sourceCyIds = $job->source_cy_ids ?? [];
        $sources = $graph->nodes()->whereIn('cy_id', $sourceCyIds)->get();
        if ($sources->isEmpty()) {
            $job->update(['status' => InvestigationJob::STATUS_FAILED, 'error' => 'No source nodes found', 'finished_at' => now()]);
            return;
        }

        $job->update([
            'status' => InvestigationJob::STATUS_RUNNING,
            'started_at' => now(),
            'progress_total' => $sources->count(),
            'progress_done' => 0,
            'created_nodes' => [],
            'created_edges' => [],
        ]);

        // Resolve required API keys once
        $requiredKeys = [];
        $listResp = $engine->listTransforms();
        if ($listResp['ok']) {
            foreach ($listResp['data']['transforms'] ?? [] as $t) {
                if ($t['name'] === $job->transform_name) {
                    $requiredKeys = $t['required_api_keys'] ?? [];
                    break;
                }
            }
        }
        $apiKeys = ApiKeyResolver::resolveMany($requiredKeys);

        // Resolve slave if needed
        $slave = null;
        if ($job->slave_id) {
            $slaveModel = \App\Models\Slave::find($job->slave_id);
            if ($slaveModel) {
                $slave = $slaveModel->toEnginePayload();
            }
        }

        $errors = [];
        foreach ($sources as $source) {
            try {
                $this->runOne($engine, $graph, $job, $source, $apiKeys, $slave);
            } catch (Throwable $e) {
                $errors[] = "[{$source->cy_id}] " . $e->getMessage();
            }
            $job->refresh();
            $job->progress_done = ($job->progress_done ?? 0) + 1;
            $job->save();
        }

        $job->refresh();
        $job->status = InvestigationJob::STATUS_COMPLETED;
        $job->finished_at = now();
        if ($errors) {
            $job->error = implode("\n", $errors);
        }
        $job->save();
    }

    public function failed(Throwable $e): void
    {
        $job = InvestigationJob::find($this->jobId);
        if ($job) {
            $job->update([
                'status' => InvestigationJob::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }

    protected function runOne(EngineClient $engine, Graph $graph, InvestigationJob $job, GraphNode $source, array $apiKeys, ?array $slave = null): void
    {
        $start = microtime(true);
        $result = $engine->runTransform($job->transform_name, [
            'type' => $source->entity_type,
            'value' => $source->value,
            'label' => $source->label,
            'data' => (object) ($source->data ?: []),
        ], $apiKeys, $slave);
        $duration = (int) ((microtime(true) - $start) * 1000);

        $runRow = TransformationRun::create([
            'job_id' => $job->id,
            'user_id' => $job->user_id,
            'graph_id' => $graph->id,
            'source_cy_id' => $source->cy_id,
            'transform_name' => $job->transform_name,
            'input_type' => $source->entity_type,
            'input_value' => $source->value,
            'duration_ms' => $duration,
        ]);

        if (! $result['ok']) {
            $runRow->update(['error' => $result['error']]);
            throw new \RuntimeException($result['error']);
        }
        $data = $result['data'];
        if (! empty($data['error'])) {
            $runRow->update(['error' => $data['error']]);
            throw new \RuntimeException($data['error']);
        }
        $runRow->update(['output' => $data]);

        $createdNodes = [];
        $createdEdges = [];
        DB::transaction(function () use ($graph, $source, $data, $job, &$createdNodes, &$createdEdges) {
            $i = 0;
            foreach ($data['nodes'] ?? [] as $n) {
                $i++;
                $node = $graph->nodes()->create([
                    'cy_id' => 'n_' . Str::random(12),
                    'entity_type' => $n['type'] ?? 'unknown',
                    'value' => (string) ($n['value'] ?? ''),
                    'label' => $n['label'] ?? ($n['value'] ?? ''),
                    'data' => $n['data'] ?? [],
                    'position_x' => $source->position_x + 80 + ($i * 6),
                    'position_y' => $source->position_y + ($i * 35) - 60,
                    'created_by' => $job->user_id,
                ]);
                $edge = $graph->edges()->create([
                    'cy_id' => 'e_' . Str::random(12),
                    'source_cy_id' => $source->cy_id,
                    'target_cy_id' => $node->cy_id,
                ]);
                $createdNodes[] = $this->nodeDto($node);
                $createdEdges[] = $this->edgeDto($edge);
            }
        });
        $job->appendOutput($createdNodes, $createdEdges);
    }

    protected function nodeDto(GraphNode $n): array
    {
        return [
            'cy_id' => $n->cy_id,
            'entity_type' => $n->entity_type,
            'value' => $n->value,
            'label' => $n->label ?? $n->value,
            'data' => $n->data ?? [],
            'position_x' => $n->position_x,
            'position_y' => $n->position_y,
        ];
    }

    protected function edgeDto($e): array
    {
        return [
            'cy_id' => $e->cy_id,
            'source' => $e->source_cy_id,
            'target' => $e->target_cy_id,
            'label' => $e->label,
            'data' => $e->data ?? [],
        ];
    }
}
