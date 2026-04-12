<?php

namespace App\Http\Controllers;

use App\Models\SlaveSetupScript;
use Illuminate\Http\Request;

class SlaveSetupScriptController extends Controller
{
    public function index()
    {
        $scripts = SlaveSetupScript::orderByDesc('is_default')->orderBy('name')->get();
        return view('slaves.scripts', ['scripts' => $scripts]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:slave_setup_scripts,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'script' => ['required', 'string'],
        ]);
        SlaveSetupScript::create([...$data, 'created_by' => $request->user()?->id]);
        return redirect('/slaves/scripts')->with('status', 'Script created.');
    }

    public function edit(SlaveSetupScript $script)
    {
        return view('slaves.script-edit', ['script' => $script]);
    }

    public function update(Request $request, SlaveSetupScript $script)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', "unique:slave_setup_scripts,name,{$script->id}"],
            'description' => ['nullable', 'string', 'max:500'],
            'script' => ['required', 'string'],
        ]);
        $script->update($data);
        return redirect('/slaves/scripts')->with('status', 'Script updated.');
    }

    public function destroy(SlaveSetupScript $script)
    {
        $script->delete();
        return redirect('/slaves/scripts')->with('status', 'Script deleted.');
    }

    public function setDefault(SlaveSetupScript $script)
    {
        SlaveSetupScript::where('is_default', true)->update(['is_default' => false]);
        $script->update(['is_default' => true]);
        return redirect('/slaves/scripts')->with('status', "'{$script->name}' set as default.");
    }
}
