<?php

use App\Services\EngineClient;
use Illuminate\Support\Facades\Http;

it('sends the shared secret header', function () {
    Http::fake(['*/transforms' => Http::response(['transforms' => []])]);
    $client = new EngineClient('http://engine.test', 'topsecret', 5);
    $res = $client->listTransforms();
    expect($res['ok'])->toBeTrue();
    Http::assertSent(fn ($req) => $req->hasHeader('X-Engine-Secret', 'topsecret'));
});

it('returns an error when the engine is down', function () {
    Http::fake(['*' => fn () => Http::response('boom', 500)]);
    $client = new EngineClient('http://engine.test', 's', 5);
    $res = $client->runTransform('x', ['type' => 'domain', 'value' => 'a']);
    expect($res['ok'])->toBeFalse()->and($res['error'])->toContain('500');
});
