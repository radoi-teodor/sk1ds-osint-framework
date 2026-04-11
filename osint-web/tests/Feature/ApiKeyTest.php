<?php

use App\Models\ApiKey;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('stores an api key encrypted and retrieves it intact', function () {
    $this->post('/api-keys', [
        'name' => 'C99_API_KEY',
        'label' => 'my c99',
        'value' => 'super-secret-value-12345',
    ])->assertRedirect();

    $k = ApiKey::where('name', 'C99_API_KEY')->first();
    expect($k)->not->toBeNull();
    // the raw column should NOT contain the plaintext
    expect($k->getRawOriginal('ciphertext'))->not->toContain('super-secret-value');
    expect($k->getValue())->toBe('super-secret-value-12345');
    expect($k->maskedPreview())->toEndWith('2345');
});

it('rejects invalid key names', function () {
    $this->post('/api-keys', [
        'name' => 'not-upper',
        'value' => 'x',
    ])->assertSessionHasErrors('name');
});

it('enforces unique key names', function () {
    ApiKey::create([
        'name' => 'DUP',
        'ciphertext' => \Illuminate\Support\Facades\Crypt::encryptString('one'),
    ]);
    $this->post('/api-keys', ['name' => 'DUP', 'value' => 'two'])
        ->assertSessionHasErrors('name');
});
