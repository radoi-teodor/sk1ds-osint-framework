<?php

use App\Models\User;

it('redirects to /setup when no users exist', function () {
    $this->get('/login')->assertRedirect('/setup');
    $this->get('/projects')->assertRedirect('/setup');
});

it('allows rendering the setup form when empty', function () {
    $this->get('/setup')->assertOk()->assertSee('INITIALIZE', false);
});

it('creates the first admin via /setup', function () {
    $response = $this->post('/setup', [
        'name' => 'Neo',
        'email' => 'neo@zion.io',
        'password' => 'redpill123',
        'password_confirmation' => 'redpill123',
    ]);
    $response->assertRedirect('/projects');
    $user = User::first();
    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('neo@zion.io')
        ->and($user->is_admin)->toBeTrue();
});

it('redirects /setup to /login once a user exists', function () {
    User::factory()->create();
    $this->get('/setup')->assertRedirect('/login');
});

it('blocks re-initialization via POST /setup', function () {
    User::factory()->create();
    $this->post('/setup', [
        'name' => 'Intruder',
        'email' => 'bad@x.io',
        'password' => 'verysecret',
        'password_confirmation' => 'verysecret',
    ])->assertRedirect('/login');
    expect(User::count())->toBe(1);
});
