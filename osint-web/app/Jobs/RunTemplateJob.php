<?php

namespace App\Jobs;

use App\Models\Graph;
use App\Models\InvestigationJob;
use App\Services\EngineClient;
use App\Services\TemplateRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(public int $jobId) {}

    public function handle(EngineClient $engine): void
    {
        $job = InvestigationJob::findOrFail($this->jobId);

        $investigation = Graph::find($job->graph_id);
        $template = Graph::find($job->template_id);
        if (! $investigation || ! $template) {
            $job->update([
                'status' => InvestigationJob::STATUS_FAILED,
                'error' => 'Missing graph or template',
                'finished_at' => now(),
            ]);
            return;
        }

        $starting = $investigation->nodes()
            ->whereIn('cy_id', $job->source_cy_ids ?? [])
            ->get()
            ->all();
        if (empty($starting)) {
            $job->update([
                'status' => InvestigationJob::STATUS_FAILED,
                'error' => 'No matching starting nodes',
                'finished_at' => now(),
            ]);
            return;
        }

        $job->update(['status' => InvestigationJob::STATUS_RUNNING, 'started_at' => now()]);

        try {
            $runner = new TemplateRunner($engine);
            $result = $runner->run($template, $investigation, $starting, $job->user_id, $job);
        } catch (Throwable $e) {
            $job->update([
                'status' => InvestigationJob::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            return;
        }

        $job->refresh();
        $job->status = InvestigationJob::STATUS_COMPLETED;
        $job->finished_at = now();
        if (! empty($result['errors'])) {
            $job->error = implode("\n", $result['errors']);
        }
        $job->save();
    }

    public function failed(Throwable $e): void
    {
        $job = InvestigationJob::find($this->jobId);
        if ($job) {
            $job->update([
                'status' => InvestigationJob::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }
}
