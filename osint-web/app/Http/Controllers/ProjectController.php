<?php

namespace App\Http\Controllers;

use App\Models\Graph;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::withCount('graphs')->latest()->get();
        return view('projects.index', ['projects' => $projects]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['created_by'] = $request->user()->id;
        $project = Project::create($data);
        return redirect("/projects/{$project->id}");
    }

    public function show(Project $project)
    {
        $project->load(['graphs' => fn ($q) => $q->latest()]);
        return view('projects.show', ['project' => $project]);
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return redirect('/projects');
    }

    public function storeGraph(Request $request, Project $project)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:investigation,template'],
        ]);
        $graph = $project->graphs()->create([
            'title' => $data['title'],
            'type' => $data['type'],
            'created_by' => $request->user()->id,
        ]);
        return redirect("/graphs/{$graph->id}");
    }
}
