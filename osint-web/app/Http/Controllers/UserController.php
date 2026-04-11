<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\User;
use App\Support\InviteTokens;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get();
        $invites = Invite::with(['creator', 'user'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
        return view('users.index', [
            'users' => $users,
            'invites' => $invites,
            'invite_base_url' => url('/invite'),
        ]);
    }

    public function invite(Request $request)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $token = InviteTokens::generate();
        Invite::create([
            'token' => $token,
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()->id,
            'expires_at' => now()->addHours((int) config('osint.invite_ttl_hours', 72)),
        ]);
        return redirect('/users')->with('status', 'Invite link generated.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Refusing to delete yourself.']);
        }
        $user->delete();
        return redirect('/users')->with('status', 'User removed.');
    }
}
