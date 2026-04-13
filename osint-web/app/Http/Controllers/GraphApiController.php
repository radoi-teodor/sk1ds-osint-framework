<?php

namespace App\Http\Controllers;

use App\Jobs\RunTransformJob;
use App\Models\Graph;
use App\Models\GraphEdge;
use App\Models\GraphNode;
use App\Models\InvestigationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GraphApiController extends Controller
{
    public function index(Graph $graph): JsonResponse
    {
        return response()->json([
            'graph' => [
                'id' => $graph->id,
                'title' => $graph->title,
                'type' => $graph->type,
            ],
            'nodes' => $graph->nodes()->get()->map(fn (GraphNode $n) => [
                'cy_id' => $n->cy_id,
                'entity_type' => $n->entity_type,
                'value' => $n->value,
                'label' => $n->label ?? $n->value,
                'data' => $n->data ?? new \stdClass(),
                'position_x' => $n->position_x,
                'position_y' => $n->position_y,
                'flagged' => (bool) $n->flagged_for_report,
            ]),
            'edges' => $graph->edges()->get()->map(fn (GraphEdge $e) => [
                'cy_id' => $e->cy_id,
                'source' => $e->source_cy_id,
                'target' => $e->target_cy_id,
                'label' => $e->label,
                'data' => $e->data ?? new \stdClass(),
            ]),
        ]);
    }

    public function storeNode(Request $request, Graph $graph): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', 'string', 'max:64'],
            'value' => ['required', 'string'],
            'label' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
            'position_x' => ['nullable', 'numeric'],
            'position_y' => ['nullable', 'numeric'],
        ]);
        $node = $graph->nodes()->create([
            'cy_id' => 'n_' . Str::random(12),
            'entity_type' => $data['entity_type'],
            'value' => $data['value'],
            'label' => $data['label'] ?? $data['value'],
            'data' => $data['data'] ?? [],
            'position_x' => $data['position_x'] ?? 0,
            'position_y' => $data['position_y'] ?? 0,
            'created_by' => $request->user()?->id,
        ]);
        return response()->json(['node' => $this->nodeDto($node)]);
    }

    public function updateNode(Request $request, Graph $graph, string $cyId): JsonResponse
    {
        $node = $graph->nodes()->where('cy_id', $cyId)->firstOrFail();
        $data = $request->validate([
            'position_x' => ['nullable', 'numeric'],
            'position_y' => ['nullable', 'numeric'],
            'label' => ['nullable', 'string'],
            'value' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
        ]);
        $node->fill(array_filter($data, fn ($v) => $v !== null));
        $node->save();
        return response()->json(['node' => $this->nodeDto($node)]);
    }

    public function destroyNode(Graph $graph, string $cyId): JsonResponse
    {
        DB::transaction(function () use ($graph, $cyId) {
            $graph->edges()
                ->where(fn ($q) => $q->where('source_cy_id', $cyId)->orWhere('target_cy_id', $cyId))
                ->delete();
            $graph->nodes()->where('cy_id', $cyId)->delete();
        });
        return response()->json(['ok' => true]);
    }

    public function storeEdge(Request $request, Graph $graph): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string'],
            'target' => ['required', 'string'],
            'label' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
        ]);
        $edge = $graph->edges()->create([
            'cy_id' => 'e_' . Str::random(12),
            'source_cy_id' => $data['source'],
            'target_cy_id' => $data['target'],
            'label' => $data['label'] ?? null,
            'data' => $data['data'] ?? [],
        ]);
        return response()->json(['edge' => [
            'cy_id' => $edge->cy_id,
            'source' => $edge->source_cy_id,
            'target' => $edge->target_cy_id,
            'label' => $edge->label,
            'data' => $edge->data ?? new \stdClass(),
        ]]);
    }

    public function destroyEdge(Graph $graph, string $cyId): JsonResponse
    {
        $graph->edges()->where('cy_id', $cyId)->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Enqueue a transform run. Returns a job id that the client polls via
     * GET /api/jobs/{id}.
     */
    public function runTransform(Request $request, Graph $graph): JsonResponse
    {
        $payload = $request->validate([
            'source_cy_id' => ['required_without:source_cy_ids', 'string'],
            'source_cy_ids' => ['required_without:source_cy_id', 'array', 'min:1'],
            'source_cy_ids.*' => ['string'],
            'transform' => ['required', 'string'],
            'slave_id' => ['nullable', 'integer', 'exists:slaves,id'],
            'generator_name' => ['nullable', 'string'],
            'generator_file_id' => ['nullable', 'integer', 'exists:uploaded_files,id'],
            'generator_text_input' => ['nullable', 'string'],
        ]);

        $cyIds = $payload['source_cy_ids'] ?? [$payload['source_cy_id']];
        $exists = $graph->nodes()->whereIn('cy_id', $cyIds)->pluck('cy_id')->all();
        if (empty($exists)) {
            return response()->json(['ok' => false, 'error' => 'No matching source nodes'], 422);
        }

        $job = InvestigationJob::create([
            'graph_id' => $graph->id,
            'user_id' => $request->user()?->id,
            'kind' => InvestigationJob::KIND_TRANSFORM,
            'transform_name' => $payload['transform'],
            'slave_id' => $payload['slave_id'] ?? null,
            'generator_name' => $payload['generator_name'] ?? null,
            'generator_file_id' => $payload['generator_file_id'] ?? null,
            'generator_text_input' => $payload['generator_text_input'] ?? null,
            'source_cy_ids' => array_values($exists),
            'status' => InvestigationJob::STATUS_QUEUED,
            'progress_total' => count($exists),
        ]);

        RunTransformJob::dispatch($job->id);

        return response()->json([
            'ok' => true,
            'job_id' => $job->id,
            'status' => $job->status,
        ]);
    }

    protected function nodeDto(GraphNode $n): array
    {
        return [
            'cy_id' => $n->cy_id,
            'entity_type' => $n->entity_type,
            'value' => $n->value,
            'label' => $n->label ?? $n->value,
            'data' => $n->data ?? new \stdClass(),
            'position_x' => $n->position_x,
            'position_y' => $n->position_y,
        ];
    }
}
