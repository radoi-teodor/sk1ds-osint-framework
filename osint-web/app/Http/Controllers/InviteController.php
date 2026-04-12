<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\User;
use App\Support\InviteTokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class InviteController extends Controller
{
    public function show(string $token)
    {
        $invite = $this->resolveValid($token);
        if (! $invite) {
            return view('invite.invalid');
        }
        return view('invite.accept', ['invite' => $invite]);
    }

    public function accept(Request $request, string $token)
    {
        $invite = $this->resolveValid($token);
        if (! $invite) {
            return view('invite.invalid');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:200', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = DB::transaction(function () use ($data, $invite) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_admin' => false,
            ]);
            $invite->used_at = now();
            $invite->used_by = $user->id;
            $invite->save();
            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        return redirect('/projects')->with('status', 'Welcome aboard, operator.');
    }

    /**
     * Look up an invite timing-safely and validate freshness.
     */
    protected function resolveValid(string $token): ?Invite
    {
        if (strlen($token) < 10 || strlen($token) > 100) {
            return null;
        }
        // We still do a direct lookup (tokens are random high-entropy strings, not
        // user-controlled predictable ids), then verify with hash_equals.
        $invite = Invite::where('token', $token)->first();
        if (! $invite) {
            return null;
        }
        if (! InviteTokens::equals($invite->token, $token)) {
            return null;
        }
        if (! $invite->isValid()) {
            return null;
        }
        return $invite;
    }
}
