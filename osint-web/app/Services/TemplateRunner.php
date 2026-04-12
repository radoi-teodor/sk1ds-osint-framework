<?php

namespace App\Services;

use App\Models\Graph;
use App\Models\GraphEdge;
use App\Models\GraphNode;
use App\Models\InvestigationJob;
use App\Models\TransformationRun;
use App\Support\ApiKeyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Executes a template graph against one or more starting investigation nodes.
 *
 * Template conventions:
 *  - template:input nodes  — root slots, bound to the selected investigation node(s)
 *  - template:transform    — carries data.transform_name
 *  - edges                 — data flow between slots/steps
 *
 * When an InvestigationJob is passed in, the runner:
 *  - sets progress_total up front (upper bound),
 *  - increments progress_done after every engine call,
 *  - appends created nodes/edges to the job row so the UI can stream them in.
 */
class TemplateRunner
{
    public function __construct(protected EngineClient $engine) {}

    /**
     * @param  GraphNode[]  $startingNodes
     * @return array{created_nodes: array, created_edges: array, runs: int, errors: array}
     */
    public function run(
        Graph $template,
        Graph $investigation,
        array $startingNodes,
        ?int $userId = null,
        ?InvestigationJob $job = null,
    ): array {
        $templateNodes = $template->nodes()->get()->keyBy('cy_id');
        $templateEdges = $template->edges()->get();

        $children = [];
        $parents = [];
        foreach ($templateNodes as $cyId => $_) {
            $children[$cyId] = [];
            $parents[$cyId] = [];
        }
        foreach ($templateEdges as $e) {
            if (isset($templateNodes[$e->source_cy_id], $templateNodes[$e->target_cy_id])) {
                $children[$e->source_cy_id][] = $e->target_cy_id;
                $parents[$e->target_cy_id][] = $e->source_cy_id;
            }
        }

        // Kahn topo sort
        $indeg = array_map('count', $parents);
        $queue = [];
        foreach ($indeg as $cyId => $d) {
            if ($d === 0) $queue[] = $cyId;
        }
        $order = [];
        while ($queue) {
            $cyId = array_shift($queue);
            $order[] = $cyId;
            foreach ($children[$cyId] as $child) {
                if (--$indeg[$child] === 0) $queue[] = $child;
            }
        }
        if (count($order) !== count($templateNodes)) {
            $this->fail($job, 'Template has cycles');
            return ['created_nodes' => [], 'created_edges' => [], 'runs' => 0, 'errors' => ['Template has cycles']];
        }

        // Resolve all API keys up front
        $transformsList = $this->engine->listTransforms();
        $transformMeta = [];
        if ($transformsList['ok']) {
            foreach ($transformsList['data']['transforms'] ?? [] as $t) {
                $transformMeta[$t['name']] = $t;
            }
        }
        $neededKeys = [];
        $transformStepCount = 0;
        foreach ($templateNodes as $tn) {
            if ($tn->entity_type === 'template:transform') {
                $transformStepCount++;
                $name = $tn->data['transform_name'] ?? null;
                if ($name && isset($transformMeta[$name])) {
                    foreach ($transformMeta[$name]['required_api_keys'] ?? [] as $k) {
                        $neededKeys[$k] = true;
                    }
                }
            }
        }
        $apiKeys = ApiKeyResolver::resolveMany(array_keys($neededKeys));

        if ($job) {
            $job->update([
                'progress_total' => max(1, $transformStepCount * max(1, count($startingNodes))),
                'progress_done' => 0,
                'created_nodes' => [],
                'created_edges' => [],
            ]);
        }

        // state[$tplCyId] = array of payloads
        // parentMap[$tplCyId] = array of matching GraphNode instances from the investigation
        $state = [];
        $parentMap = [];
        foreach ($templateNodes as $tn) {
            if ($tn->entity_type === 'template:input') {
                $state[$tn->cy_id] = array_map(fn ($n) => $this->nodeToPayload($n), $startingNodes);
                $parentMap[$tn->cy_id] = $startingNodes;
            } else {
                $state[$tn->cy_id] = [];
                $parentMap[$tn->cy_id] = [];
            }
        }

        $createdNodes = [];
        $createdEdges = [];
        $errors = [];
        $runs = 0;

        foreach ($order as $cyId) {
            $tn = $templateNodes[$cyId];
            if ($tn->entity_type === 'template:input') continue;
            $name = $tn->data['transform_name'] ?? null;
            if (! $name) {
                $errors[] = "Template step {$cyId} has no transform_name";
                continue;
            }

            $inputPayloads = [];
            $inputParents = [];
            foreach ($parents[$cyId] as $p) {
                foreach ($state[$p] as $idx => $payload) {
                    $inputPayloads[] = $payload;
                    $inputParents[] = $parentMap[$p][$idx] ?? null;
                }
            }

            $outputPayloads = [];
            $outputParents = [];

            foreach ($inputPayloads as $i => $payload) {
                $meta = $transformMeta[$name] ?? null;
                if ($meta && ! in_array('*', $meta['input_types'] ?? [], true)) {
                    if (! in_array($payload['type'], $meta['input_types'] ?? [], true)) {
                        continue;
                    }
                }
                $runs++;
                $start = microtime(true);
                $result = $this->engine->runTransform($name, $payload, $apiKeys);
                $duration = (int) ((microtime(true) - $start) * 1000);

                $runRow = TransformationRun::create([
                    'job_id' => $job?->id,
                    'user_id' => $userId,
                    'graph_id' => $investigation->id,
                    'source_cy_id' => $inputParents[$i]?->cy_id,
                    'transform_name' => $name,
                    'input_type' => $payload['type'] ?? null,
                    'input_value' => $payload['value'] ?? null,
                    'duration_ms' => $duration,
                ]);

                if (! $result['ok']) {
                    $runRow->update(['error' => $result['error']]);
                    $errors[] = "[$name] " . $result['error'];
                    $this->bumpProgress($job);
                    continue;
                }
                $data = $result['data'];
                if (! empty($data['error'])) {
                    $runRow->update(['error' => $data['error']]);
                    $errors[] = "[$name] " . $data['error'];
                    $this->bumpProgress($job);
                    continue;
                }
                $runRow->update(['output' => $data]);

                $parentNode = $inputParents[$i];
                $batchNodes = [];
                $batchEdges = [];
                DB::transaction(function () use ($investigation, $parentNode, $data, $userId, &$batchNodes, &$batchEdges, &$outputPayloads, &$outputParents) {
                    $j = 0;
                    foreach ($data['nodes'] ?? [] as $n) {
                        $j++;
                        $newNode = $investigation->nodes()->create([
                            'cy_id' => 'n_' . Str::random(12),
                            'entity_type' => $n['type'] ?? 'unknown',
                            'value' => (string) ($n['value'] ?? ''),
                            'label' => $n['label'] ?? ($n['value'] ?? ''),
                            'data' => $n['data'] ?? [],
                            'position_x' => ($parentNode->position_x ?? 0) + 80 + ($j * 6),
                            'position_y' => ($parentNode->position_y ?? 0) + ($j * 35) - 60,
                            'created_by' => $userId,
                        ]);
                        $edge = $investigation->edges()->create([
                            'cy_id' => 'e_' . Str::random(12),
                            'source_cy_id' => $parentNode?->cy_id ?? '',
                            'target_cy_id' => $newNode->cy_id,
                        ]);
                        $batchNodes[] = $this->nodeDto($newNode);
                        $batchEdges[] = $this->edgeDto($edge);
                        $outputPayloads[] = [
                            'type' => $newNode->entity_type,
                            'value' => $newNode->value,
                            'label' => $newNode->label,
                            'data' => (object) ($newNode->data ?: []),
                        ];
                        $outputParents[] = $newNode;
                    }
                });

                $createdNodes = array_merge($createdNodes, $batchNodes);
                $createdEdges = array_merge($createdEdges, $batchEdges);
                if ($job && ($batchNodes || $batchEdges)) {
                    $job->appendOutput($batchNodes, $batchEdges);
                }
                $this->bumpProgress($job);
            }

            $state[$cyId] = $outputPayloads;
            $parentMap[$cyId] = $outputParents;
        }

        return [
            'created_nodes' => $createdNodes,
            'created_edges' => $createdEdges,
            'runs' => $runs,
            'errors' => $errors,
        ];
    }

    protected function bumpProgress(?InvestigationJob $job): void
    {
        if (! $job) return;
        $job->refresh();
        $job->progress_done = ($job->progress_done ?? 0) + 1;
        if ($job->progress_done > $job->progress_total) {
            $job->progress_total = $job->progress_done;
        }
        $job->save();
    }

    protected function fail(?InvestigationJob $job, string $msg): void
    {
        if (! $job) return;
        $job->update([
            'status' => InvestigationJob::STATUS_FAILED,
            'error' => $msg,
            'finished_at' => now(),
        ]);
    }

    protected function nodeToPayload(GraphNode $n): array
    {
        return [
            'type' => $n->entity_type,
            'value' => $n->value,
            'label' => $n->label,
            'data' => (object) ($n->data ?: []),
        ];
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

    protected function edgeDto(GraphEdge $e): array
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
