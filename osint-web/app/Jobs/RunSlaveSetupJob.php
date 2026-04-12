<?php

namespace App\Jobs;

use App\Models\Slave;
use App\Models\SlaveSetupRun;
use App\Models\SlaveSetupScript;
use App\Services\EngineClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunSlaveSetupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(public int $runId) {}

    public function handle(EngineClient $engine): void
    {
        $run = SlaveSetupRun::find($this->runId);
        if (! $run) return;

        $slave = Slave::find($run->slave_id);
        $script = SlaveSetupScript::find($run->script_id);
        if (! $slave || ! $script) {
            $run->update([
                'status' => SlaveSetupRun::STATUS_FAILED,
                'error' => 'Slave or script not found',
                'finished_at' => now(),
            ]);
            return;
        }

        $run->update(['status' => SlaveSetupRun::STATUS_RUNNING, 'started_at' => now()]);

        $resp = $engine->runScript($slave->toEnginePayload(), $script->script);

        if (! $resp['ok']) {
            $run->update([
                'status' => SlaveSetupRun::STATUS_FAILED,
                'error' => $resp['error'] ?? 'Engine error',
                'finished_at' => now(),
            ]);
            return;
        }

        $output = $resp['data']['output'] ?? [];
        $ok = ! empty($resp['data']['ok']);
        $run->update([
            'status' => $ok ? SlaveSetupRun::STATUS_COMPLETED : SlaveSetupRun::STATUS_FAILED,
            'stdout' => $output['stdout'] ?? null,
            'stderr' => $output['stderr'] ?? null,
            'exit_code' => $output['exit_code'] ?? null,
            'error' => $ok ? null : ($resp['data']['error'] ?? ($output['stderr'] ?? null)),
            'finished_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        $run = SlaveSetupRun::find($this->runId);
        if ($run) {
            $run->update([
                'status' => SlaveSetupRun::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }
}
