<?php

namespace App\Http\Controllers;

use App\Services\EngineClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeneratorController extends Controller
{
    public function index(EngineClient $engine)
    {
        $resp = $engine->listGenerators();
        $generators = $resp['ok'] ? ($resp['data']['generators'] ?? []) : [];
        $loadErrors = $resp['ok'] ? ($resp['data']['load_errors'] ?? []) : [];
        $engineError = $resp['ok'] ? null : $resp['error'];
        return view('generators.index', compact('generators', 'engineError', 'loadErrors'));
    }

    public function create()
    {
        return view('generators.create');
    }

    public function store(Request $request, EngineClient $engine)
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'regex:/^[a-zA-Z0-9_][a-zA-Z0-9_\-]*\.py$/'],
            'source' => ['required', 'string'],
        ]);
        $resp = $engine->createGenerator($data['filename'], $data['source']);
        if (! $resp['ok']) {
            return back()->withErrors(['engine' => $resp['error']])->withInput();
        }
        return redirect('/generators')->with('status', 'Generator created.');
    }

    public function edit(string $name, EngineClient $engine)
    {
        $resp = $engine->getGeneratorSource($name);
        if (! $resp['ok']) {
            return redirect('/generators')->withErrors(['engine' => $resp['error']]);
        }
        return view('generators.edit', [
            'name' => $name,
            'filename' => $resp['data']['filename'] ?? '',
            'source' => $resp['data']['source'] ?? '',
        ]);
    }

    public function update(Request $request, string $name, EngineClient $engine): JsonResponse
    {
        $data = $request->validate(['source' => ['required', 'string']]);
        $resp = $engine->updateGeneratorSource($name, $data['source']);
        return response()->json($resp);
    }

    public function destroy(string $name, EngineClient $engine)
    {
        $engine->deleteGenerator($name);
        return redirect('/generators')->with('status', 'Generator deleted.');
    }
}
