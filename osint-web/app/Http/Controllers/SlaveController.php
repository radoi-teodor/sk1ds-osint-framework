<?php

namespace App\Http\Controllers;

use App\Models\Slave;
use App\Services\EngineClient;
use Illuminate\Http\Request;

class SlaveController extends Controller
{
    public function index()
    {
        $slaves = Slave::orderBy('name')->get();
        return view('slaves.index', ['slaves' => $slaves]);
    }

    public function create()
    {
        return view('slaves.create');
    }

    public function store(Request $request, EngineClient $engine)
    {
        $data = $this->validated($request);

        $slave = new Slave($data);
        $slave->created_by = $request->user()?->id;

        if ($data['type'] === 'ssh' && ! empty($data['credential'])) {
            $slave->setCredential($data['credential']);
        }

        // Enforce singleton embedded
        if ($data['type'] === 'embedded' && Slave::where('type', 'embedded')->exists()) {
            return back()->withErrors(['type' => 'An embedded slave already exists.'])->withInput();
        }

        $slave->save();

        // Auto-probe on create
        $this->probe($slave, $engine);

        return redirect('/slaves')->with('status', "Slave '{$slave->name}' created.");
    }

    public function edit(Slave $slave)
    {
        return view('slaves.edit', ['slave' => $slave]);
    }

    public function update(Request $request, Slave $slave, EngineClient $engine)
    {
        $data = $this->validated($request, $slave);

        $slave->fill($data);
        if ($data['type'] === 'ssh' && ! empty($data['credential'])) {
            $slave->setCredential($data['credential']);
        }
        $slave->save();

        return redirect('/slaves')->with('status', "Slave '{$slave->name}' updated.");
    }

    public function destroy(Slave $slave)
    {
        $slave->delete();
        return redirect('/slaves')->with('status', 'Slave removed.');
    }

    public function test(Slave $slave, EngineClient $engine)
    {
        $result = $this->probe($slave, $engine);
        if ($result) {
            return redirect('/slaves')->with('status', "Connection to '{$slave->name}' OK.");
        }
        return redirect('/slaves')->withErrors(['slave' => "Connection to '{$slave->name}' failed. Check credentials."]);
    }

    protected function probe(Slave $slave, EngineClient $engine): bool
    {
        $resp = $engine->testSlave($slave->toEnginePayload());
        if ($resp['ok'] && ! empty($resp['data']['ok'])) {
            $slave->fingerprint = $resp['data']['fingerprint'];
            $slave->last_tested_at = now();
            $slave->save();
            return true;
        }
        return false;
    }

    protected function validated(Request $request, ?Slave $existing = null): array
    {
        $nameRule = $existing
            ? "unique:slaves,name,{$existing->id}"
            : 'unique:slaves,name';

        return $request->validate([
            'name' => ['required', 'string', 'max:100', $nameRule],
            'type' => ['required', 'in:ssh,embedded'],
            'host' => ['nullable', 'required_if:type,ssh', 'string', 'max:200'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'required_if:type,ssh', 'string', 'max:100'],
            'auth_method' => ['nullable', 'required_if:type,ssh', 'in:password,key'],
            'credential' => ['nullable', 'string'],
        ]);
    }
}
