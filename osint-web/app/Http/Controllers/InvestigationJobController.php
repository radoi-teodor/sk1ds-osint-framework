<?php

namespace App\Http\Controllers;

use App\Models\InvestigationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvestigationJobController extends Controller
{
    /**
     * Lightweight polling endpoint.
     *
     * Client may pass ?since_nodes=N&since_edges=M to only get nodes/edges
     * appended AFTER those offsets, keeping the payload small once many
     * items have accumulated.
     */
    public function show(Request $request, InvestigationJob $job): JsonResponse
    {
        $sinceNodes = (int) $request->query('since_nodes', 0);
        $sinceEdges = (int) $request->query('since_edges', 0);

        $allNodes = $job->created_nodes ?? [];
        $allEdges = $job->created_edges ?? [];

        return response()->json([
            'id' => $job->id,
            'graph_id' => $job->graph_id,
            'kind' => $job->kind,
            'transform_name' => $job->transform_name,
            'template_id' => $job->template_id,
            'status' => $job->status,
            'progress_done' => $job->progress_done,
            'progress_total' => $job->progress_total,
            'total_nodes' => count($allNodes),
            'total_edges' => count($allEdges),
            'new_nodes' => array_slice($allNodes, $sinceNodes),
            'new_edges' => array_slice($allEdges, $sinceEdges),
            'error' => $job->error,
            'started_at' => $job->started_at,
            'finished_at' => $job->finished_at,
        ]);
    }

    /**
     * Recent jobs for a graph (sidebar "activity" panel).
     */
    public function indexForGraph(int $graphId): JsonResponse
    {
        $jobs = InvestigationJob::where('graph_id', $graphId)
            ->latest()
            ->limit(20)
            ->get(['id', 'kind', 'transform_name', 'template_id', 'status', 'progress_done', 'progress_total', 'error', 'created_at', 'finished_at']);
        return response()->json(['jobs' => $jobs]);
    }
}
