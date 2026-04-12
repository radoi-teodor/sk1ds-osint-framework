<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the Python engine. Everything that talks to the engine
 * goes through here so tests can fake() the Http facade.
 */
class EngineClient
{
    public function __construct(
        protected ?string $baseUrl = null,
        protected ?string $secret = null,
        protected ?int $timeout = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('osint.engine.url'), '/');
        $this->secret = $secret ?? (string) config('osint.engine.secret');
        $this->timeout = $timeout ?? (int) config('osint.engine.timeout', 60);
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-Engine-Secret' => $this->secret])
            ->timeout($this->timeout)
            ->acceptJson();
    }

    /** @return array{ok:bool, data?:array, error?:string} */
    protected function wrap(callable $fn): array
    {
        try {
            $response = $fn();
        } catch (ConnectionException $e) {
            return ['ok' => false, 'error' => 'Engine unreachable: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        if (! $response->successful()) {
            return [
                'ok' => false,
                'error' => 'HTTP ' . $response->status() . ': ' . $response->body(),
            ];
        }
        return ['ok' => true, 'data' => $response->json() ?? []];
    }

    public function health(): array
    {
        return $this->wrap(fn () => $this->http()->get('/health'));
    }

    public function listTransforms(): array
    {
        return $this->wrap(fn () => $this->http()->get('/transforms'));
    }

    public function runTransform(string $name, array $node, array $apiKeys = [], ?array $slave = null, ?string $generatorOutput = null, ?int $requestTimeout = null): array
    {
        $body = [
            'node' => $node,
            'api_keys' => (object) $apiKeys,
        ];
        if ($slave !== null) {
            $body['slave'] = $slave;
        }
        if ($generatorOutput !== null) {
            $body['generator_output'] = $generatorOutput;
        }
        $http = $this->http();
        if ($requestTimeout) {
            $http = $http->timeout($requestTimeout);
        }
        return $this->wrap(fn () => $http->post("/transforms/{$name}/run", $body));
    }

    public function listGenerators(): array
    {
        return $this->wrap(fn () => $this->http()->get('/generators'));
    }

    public function runGenerator(string $name, array $inputs): array
    {
        return $this->wrap(fn () => $this->http()->post("/generators/{$name}/run", $inputs));
    }

    public function getGeneratorSource(string $name): array
    {
        return $this->wrap(fn () => $this->http()->get("/generators/{$name}/source"));
    }

    public function updateGeneratorSource(string $name, string $source): array
    {
        return $this->wrap(fn () => $this->http()->put("/generators/{$name}/source", ['source' => $source]));
    }

    public function createGenerator(string $filename, string $source): array
    {
        return $this->wrap(fn () => $this->http()->post('/generators', ['filename' => $filename, 'source' => $source]));
    }

    public function deleteGenerator(string $name): array
    {
        return $this->wrap(fn () => $this->http()->delete("/generators/{$name}"));
    }

    public function testSlave(array $slavePayload): array
    {
        return $this->wrap(fn () => $this->http()->timeout(35)->post('/slaves/test', [
            'slave' => $slavePayload,
        ]));
    }

    public function getSource(string $name): array
    {
        return $this->wrap(fn () => $this->http()->get("/transforms/{$name}/source"));
    }

    public function updateSource(string $name, string $source): array
    {
        return $this->wrap(fn () => $this->http()->put("/transforms/{$name}/source", [
            'source' => $source,
        ]));
    }

    public function createTransform(string $filename, string $source): array
    {
        return $this->wrap(fn () => $this->http()->post('/transforms', [
            'filename' => $filename,
            'source' => $source,
        ]));
    }

    public function deleteTransform(string $name): array
    {
        return $this->wrap(fn () => $this->http()->delete("/transforms/{$name}"));
    }

    public function validate(string $source): array
    {
        return $this->wrap(fn () => $this->http()->post('/validate', ['source' => $source]));
    }

    public function reload(): array
    {
        return $this->wrap(fn () => $this->http()->post('/reload'));
    }
}
