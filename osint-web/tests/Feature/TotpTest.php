<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

it('login without TOTP works as before', function () {
    $user = User::factory()->create(['email' => 'no2fa@test.io']);
    $this->post('/login', ['email' => 'no2fa@test.io', 'password' => 'password'])
        ->assertRedirect('/projects');
    $this->assertAuthenticatedAs($user);
});

it('login with TOTP redirects to challenge page', function () {
    $g2fa = new Google2FA();
    $secret = $g2fa->generateSecretKey();
    $user = User::factory()->create([
        'email' => 'mfa@test.io',
        'totp_secret' => $secret,
        'totp_confirmed_at' => now(),
        'totp_recovery_codes' => [],
    ]);

    $resp = $this->post('/login', ['email' => 'mfa@test.io', 'password' => 'password']);
    $resp->assertRedirect('/auth/totp-challenge');
    $this->assertGuest();
});

it('TOTP challenge verifies a valid code and logs in', function () {
    $g2fa = new Google2FA();
    $secret = $g2fa->generateSecretKey();
    $user = User::factory()->create([
        'totp_secret' => $secret,
        'totp_confirmed_at' => now(),
        'totp_recovery_codes' => [],
    ]);

    // Simulate the post-password session state
    $this->withSession(['mfa_user_id' => $user->id, 'mfa_remember' => false]);

    $code = $g2fa->getCurrentOtp($secret);
    $this->post('/auth/totp-challenge', ['code' => $code])
        ->assertRedirect('/projects');
    $this->assertAuthenticatedAs($user);
});

it('TOTP challenge rejects an invalid code', function () {
    $user = User::factory()->create([
        'totp_secret' => (new Google2FA())->generateSecretKey(),
        'totp_confirmed_at' => now(),
        'totp_recovery_codes' => [],
    ]);

    $this->withSession(['mfa_user_id' => $user->id]);
    $this->post('/auth/totp-challenge', ['code' => '000000'])
        ->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('recovery code works and is consumed', function () {
    $g2fa = new Google2FA();
    $secret = $g2fa->generateSecretKey();
    $recoveryCode = 'abcdefghij';
    $user = User::factory()->create([
        'totp_secret' => $secret,
        'totp_confirmed_at' => now(),
        'totp_recovery_codes' => [Hash::make($recoveryCode), Hash::make('othercode01')],
    ]);

    $this->withSession(['mfa_user_id' => $user->id]);
    $this->post('/auth/totp-challenge', ['code' => $recoveryCode])
        ->assertRedirect('/projects');
    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect(count($user->totp_recovery_codes))->toBe(1);
});

it('authenticated routes require TOTP verification when enabled', function () {
    $user = User::factory()->create([
        'totp_secret' => (new Google2FA())->generateSecretKey(),
        'totp_confirmed_at' => now(),
        'totp_recovery_codes' => [],
    ]);

    $this->actingAs($user);
    // totp_verified not in session → redirected
    $this->get('/projects')->assertRedirect('/auth/totp-challenge');
});

it('profile security page shows enable button when no TOTP', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->withSession(['totp_verified' => true])
        ->get('/profile/security')
        ->assertOk()
        ->assertSee('ENABLE 2FA', false);
});

it('profile security page shows disable when TOTP active', function () {
    $user = User::factory()->create([
        'totp_secret' => (new Google2FA())->generateSecretKey(),
        'totp_confirmed_at' => now(),
        'totp_recovery_codes' => [],
    ]);
    $this->actingAs($user)
        ->withSession(['totp_verified' => true])
        ->get('/profile/security')
        ->assertOk()
        ->assertSee('DISABLE 2FA', false);
});
