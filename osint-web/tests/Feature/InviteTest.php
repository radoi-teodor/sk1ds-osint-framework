<?php

use App\Models\Invite;
use App\Models\User;
use App\Support\InviteTokens;

it('generates cryptographically strong tokens', function () {
    $tokens = collect(range(1, 10))->map(fn () => InviteTokens::generate());
    expect($tokens->unique()->count())->toBe(10);
    $tokens->each(function ($t) {
        expect(strlen($t))->toBeGreaterThanOrEqual(40);
        expect($t)->toMatch('/^[A-Za-z0-9_\-]+$/');
    });
});

it('accepts a valid invite and creates a user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $token = InviteTokens::generate();
    Invite::create([
        'token' => $token,
        'created_by' => $admin->id,
        'expires_at' => now()->addHours(24),
    ]);

    $this->get("/invite/$token")->assertOk()->assertSee('ACCEPT INVITE', false);

    $this->post("/invite/$token", [
        'name' => 'Trinity',
        'email' => 'trinity@zion.io',
        'password' => 'matrixhasyou',
        'password_confirmation' => 'matrixhasyou',
    ])->assertRedirect('/projects');

    expect(User::where('email', 'trinity@zion.io')->exists())->toBeTrue();
    expect(Invite::first()->used_at)->not->toBeNull();
});

it('rejects an expired invite', function () {
    User::factory()->create();
    $token = InviteTokens::generate();
    Invite::create([
        'token' => $token,
        'expires_at' => now()->subMinute(),
    ]);
    $this->get("/invite/$token")->assertOk()->assertSee('invalid', false);
});

it('rejects a used invite', function () {
    User::factory()->create();
    $token = InviteTokens::generate();
    Invite::create([
        'token' => $token,
        'expires_at' => now()->addDay(),
        'used_at' => now(),
    ]);
    $this->get("/invite/$token")->assertOk()->assertSee('invalid', false);
});

it('rejects an unknown token', function () {
    User::factory()->create();
    $this->get('/invite/totally-fake-token-string')->assertOk()->assertSee('invalid', false);
});
