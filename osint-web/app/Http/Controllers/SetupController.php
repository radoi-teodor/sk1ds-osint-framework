<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class SetupController extends Controller
{
    public function show()
    {
        if (User::query()->exists()) {
            return redirect('/login');
        }
        return view('setup');
    }

    public function store(Request $request)
    {
        if (User::query()->exists()) {
            return redirect('/login');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:200', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/projects')->with('status', 'Setup complete. Welcome, operator.');
    }
}
