<?php

namespace App\Http\Controllers;

use App\Models\Graph;
use App\Services\EngineClient;
use Illuminate\Http\Request;

class GraphController extends Controller
{
    public function show(Graph $graph, EngineClient $engine)
    {
        $graph->load(['nodes', 'edges', 'project']);

        // Try to fetch live transform list from engine. If engine is down we
        // still render the graph, just with an empty transforms panel.
        $transforms = [];
        $engineError = null;
        $resp = $engine->listTransforms();
        if ($resp['ok']) {
            $transforms = $resp['data']['transforms'] ?? [];
        } else {
            $engineError = $resp['error'] ?? 'unknown';
        }

        $templates = Graph::where('type', Graph::TYPE_TEMPLATE)->orderBy('title')->get(['id', 'title']);

        return view('graphs.show', [
            'graph' => $graph,
            'transforms' => $transforms,
            'engineError' => $engineError,
            'templates' => $templates,
        ]);
    }

    public function destroy(Graph $graph)
    {
        $projectId = $graph->project_id;
        $graph->delete();
        return $graph->isTemplate()
            ? redirect('/templates')
            : redirect("/projects/{$projectId}");
    }
}
