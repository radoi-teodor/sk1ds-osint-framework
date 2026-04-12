<?php

use App\Models\Slave;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates an SSH slave', function () {
    Http::fake(['*/slaves/test' => Http::response(['ok' => true, 'fingerprint' => [
        'whoami' => 'root', 'hostname' => 'vps1', 'public_ip' => '1.2.3.4',
        'os' => 'Ubuntu 22.04', 'country' => 'Germany', 'country_code' => 'DE',
        'flag' => "\u{1F1E9}\u{1F1EA}", 'isp' => 'Hetzner',
    ]])]);

    $this->post('/slaves', [
        'name' => 'vps-eu-1',
        'type' => 'ssh',
        'host' => '10.0.0.1',
        'port' => 22,
        'username' => 'root',
        'auth_method' => 'password',
        'credential' => 'secret123',
    ])->assertRedirect('/slaves');

    $slave = Slave::where('name', 'vps-eu-1')->first();
    expect($slave)->not->toBeNull()
        ->and($slave->type)->toBe('ssh')
        ->and($slave->host)->toBe('10.0.0.1')
        ->and($slave->getCredential())->toBe('secret123')
        ->and($slave->fingerprint['whoami'])->toBe('root')
        ->and($slave->fingerprint['flag'])->toContain("\u{1F1E9}");
});

it('creates an embedded slave', function () {
    Http::fake(['*/slaves/test' => Http::response(['ok' => true, 'fingerprint' => [
        'whoami' => 'engine', 'hostname' => 'localhost', 'public_ip' => '5.5.5.5',
    ]])]);

    $this->post('/slaves', [
        'name' => 'local',
        'type' => 'embedded',
    ])->assertRedirect('/slaves');

    $slave = Slave::where('name', 'local')->first();
    expect($slave)->not->toBeNull()
        ->and($slave->isEmbedded())->toBeTrue()
        ->and($slave->getCredential())->toBeNull();
});

it('prevents duplicate embedded slaves', function () {
    Http::fake(['*/slaves/test' => Http::response(['ok' => true, 'fingerprint' => []])]);
    Slave::create(['name' => 'existing-embedded', 'type' => 'embedded']);

    $this->post('/slaves', ['name' => 'local2', 'type' => 'embedded'])
        ->assertSessionHasErrors('type');
});

it('encrypts credential at rest', function () {
    Http::fake(['*/slaves/test' => Http::response(['ok' => true, 'fingerprint' => []])]);
    $this->post('/slaves', [
        'name' => 'sec-test', 'type' => 'ssh', 'host' => '1.1.1.1',
        'username' => 'root', 'auth_method' => 'password', 'credential' => 'p@ssw0rd!',
    ]);
    $slave = Slave::where('name', 'sec-test')->first();
    expect($slave->getRawOriginal('encrypted_credential'))->not->toContain('p@ssw0rd');
    expect($slave->getCredential())->toBe('p@ssw0rd!');
});

it('tests connection and updates fingerprint', function () {
    $slave = Slave::create(['name' => 'probe-me', 'type' => 'ssh', 'host' => '1.1.1.1', 'username' => 'root', 'auth_method' => 'password']);
    $slave->setCredential('x');
    $slave->save();

    Http::fake(['*/slaves/test' => Http::response(['ok' => true, 'fingerprint' => [
        'whoami' => 'root', 'hostname' => 'box', 'public_ip' => '9.8.7.6',
        'os' => 'Debian 12', 'country' => 'Romania', 'country_code' => 'RO',
        'flag' => "\u{1F1F7}\u{1F1F4}", 'isp' => 'RDS',
    ]])]);

    $this->post("/slaves/{$slave->id}/test")->assertRedirect('/slaves');

    $slave->refresh();
    expect($slave->fingerprint['country'])->toBe('Romania')
        ->and($slave->fingerprint['flag'])->toContain("\u{1F1F7}")
        ->and($slave->last_tested_at)->not->toBeNull();
});

it('slave_id flows through to RunTransformJob', function () {
    Http::fake(['*/slaves/test' => Http::response(['ok' => true, 'fingerprint' => []])]);
    $slave = Slave::create(['name' => 'job-test', 'type' => 'embedded']);

    $project = \App\Models\Project::create(['name' => 'p', 'created_by' => $this->user->id]);
    $graph = \App\Models\Graph::create(['project_id' => $project->id, 'title' => 'g', 'type' => 'investigation', 'created_by' => $this->user->id]);
    $graph->nodes()->create(['cy_id' => 'n1', 'entity_type' => 'domain', 'value' => 'example.com']);

    \Illuminate\Support\Facades\Bus::fake();

    $this->postJson("/api/graphs/{$graph->id}/run-transform", [
        'source_cy_id' => 'n1',
        'transform' => 'nmap.top100',
        'slave_id' => $slave->id,
    ])->assertOk()->assertJsonPath('ok', true);

    $job = \App\Models\InvestigationJob::first();
    expect($job->slave_id)->toBe($slave->id);
});
