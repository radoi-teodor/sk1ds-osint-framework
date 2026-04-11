<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index()
    {
        $keys = ApiKey::latest()->get()->map(fn (ApiKey $k) => [
            'id' => $k->id,
            'name' => $k->name,
            'label' => $k->label,
            'preview' => $k->maskedPreview(),
            'created_at' => $k->created_at,
        ]);
        return view('api_keys.index', ['keys' => $keys]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:api_keys,name'],
            'label' => ['nullable', 'string', 'max:200'],
            'value' => ['required', 'string'],
        ]);

        $key = new ApiKey([
            'name' => $data['name'],
            'label' => $data['label'] ?? null,
            'created_by' => $request->user()?->id,
        ]);
        $key->setValue($data['value']);
        $key->save();

        return redirect('/api-keys')->with('status', "Stored {$data['name']}");
    }

    public function destroy(ApiKey $api_key)
    {
        $api_key->delete();
        return redirect('/api-keys')->with('status', 'Key removed.');
    }
}
