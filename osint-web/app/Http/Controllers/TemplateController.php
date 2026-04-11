<?php

namespace App\Http\Controllers;

use App\Jobs\RunTemplateJob;
use App\Models\Graph;
use App\Models\InvestigationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index()
    {
        $templates = Graph::where('type', Graph::TYPE_TEMPLATE)
            ->withCount('nodes')
            ->latest()
            ->get();
        return view('templates.index', ['templates' => $templates]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $graph = Graph::create([
            'title' => $data['title'],
            'type' => Graph::TYPE_TEMPLATE,
            'description' => $data['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        return redirect("/graphs/{$graph->id}");
    }

    public function run(Request $request, Graph $graph): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'starting_cy_ids' => ['required', 'array', 'min:1'],
            'starting_cy_ids.*' => ['string'],
        ]);
        $template = Graph::where('type', Graph::TYPE_TEMPLATE)->findOrFail($data['template_id']);

        $exists = $graph->nodes()->whereIn('cy_id', $data['starting_cy_ids'])->pluck('cy_id')->all();
        if (empty($exists)) {
            return response()->json(['ok' => false, 'error' => 'No matching starting nodes'], 422);
        }

        $job = InvestigationJob::create([
            'graph_id' => $graph->id,
            'user_id' => $request->user()?->id,
            'kind' => InvestigationJob::KIND_TEMPLATE,
            'template_id' => $template->id,
            'source_cy_ids' => array_values($exists),
            'status' => InvestigationJob::STATUS_QUEUED,
        ]);

        RunTemplateJob::dispatch($job->id);

        return response()->json([
            'ok' => true,
            'job_id' => $job->id,
            'status' => $job->status,
        ]);
    }
}
