<?php

namespace App\Http\Controllers;

use App\Services\EngineClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransformationController extends Controller
{
    public function index(EngineClient $engine)
    {
        $resp = $engine->listTransforms();
        $transforms = $resp['ok'] ? ($resp['data']['transforms'] ?? []) : [];
        $loadErrors = $resp['ok'] ? ($resp['data']['load_errors'] ?? []) : [];
        $engineError = $resp['ok'] ? null : $resp['error'];

        return view('transformations.index', [
            'transforms' => $transforms,
            'engineError' => $engineError,
            'loadErrors' => $loadErrors,
        ]);
    }

    public function edit(string $name, EngineClient $engine)
    {
        $resp = $engine->getSource($name);
        if (! $resp['ok']) {
            return redirect('/transformations')->withErrors(['engine' => $resp['error']]);
        }
        return view('transformations.edit', [
            'name' => $name,
            'filename' => $resp['data']['filename'] ?? '',
            'source' => $resp['data']['source'] ?? '',
        ]);
    }

    public function update(Request $request, string $name, EngineClient $engine): JsonResponse
    {
        $data = $request->validate(['source' => ['required', 'string']]);
        $resp = $engine->updateSource($name, $data['source']);
        return response()->json($resp);
    }

    public function create()
    {
        return view('transformations.create');
    }

    public function store(Request $request, EngineClient $engine)
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'regex:/^[a-zA-Z0-9_][a-zA-Z0-9_\-]*\.py$/'],
            'source' => ['required', 'string'],
        ]);
        $resp = $engine->createTransform($data['filename'], $data['source']);
        if (! $resp['ok']) {
            return back()->withErrors(['engine' => $resp['error']])->withInput();
        }
        return redirect('/transformations')->with('status', 'Transform created.');
    }

    public function destroy(string $name, EngineClient $engine)
    {
        $resp = $engine->deleteTransform($name);
        if (! $resp['ok']) {
            return back()->withErrors(['engine' => $resp['error']]);
        }
        return redirect('/transformations')->with('status', 'Transform deleted.');
    }

    public function validateSource(Request $request, EngineClient $engine): JsonResponse
    {
        $data = $request->validate(['source' => ['required', 'string']]);
        $resp = $engine->validate($data['source']);
        return response()->json($resp);
    }

    public function reload(EngineClient $engine): JsonResponse
    {
        $resp = $engine->reload();
        return response()->json($resp);
    }
}
