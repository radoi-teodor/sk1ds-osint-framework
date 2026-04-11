<?php

use App\Models\User;

it('allows login with valid credentials', function () {
    $user = User::factory()->create(['email' => 'agent@smith.io']);
    $this->post('/login', [
        'email' => 'agent@smith.io',
        'password' => 'password',
    ])->assertRedirect('/projects');
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'agent@smith.io']);
    $this->post('/login', ['email' => 'agent@smith.io', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('blocks authenticated routes for guests', function () {
    User::factory()->create();
    $this->get('/projects')->assertRedirect('/login');
    $this->get('/templates')->assertRedirect('/login');
    $this->get('/transformations')->assertRedirect('/login');
});

it('logs out', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
