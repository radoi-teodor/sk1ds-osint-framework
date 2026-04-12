<?php

namespace App\Jobs;

use App\Models\Graph;
use App\Models\ReportJob;
use App\Support\EntityTypes;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(public int $reportJobId) {}

    public function handle(): void
    {
        $job = ReportJob::find($this->reportJobId);
        if (! $job) return;

        $graph = Graph::with('project')->find($job->graph_id);
        if (! $graph) {
            $job->update(['status' => ReportJob::STATUS_FAILED, 'error' => 'Graph not found', 'finished_at' => now()]);
            return;
        }

        $job->update(['status' => ReportJob::STATUS_RUNNING, 'started_at' => now()]);

        $nodes = $graph->nodes()
            ->where('flagged_for_report', true)
            ->orderBy('entity_type')
            ->orderBy('value')
            ->get();

        if ($nodes->isEmpty()) {
            $job->update(['status' => ReportJob::STATUS_FAILED, 'error' => 'No flagged nodes', 'finished_at' => now()]);
            return;
        }

        $job->update(['node_count' => $nodes->count()]);

        $grouped = $nodes->groupBy('entity_type');
        $entityTypes = EntityTypes::all();

        // Find the user for the report header
        $user = \App\Models\User::find($job->user_id);

        $html = view('reports.investigation', [
            'graph' => $graph,
            'grouped' => $grouped,
            'entityTypes' => $entityTypes,
            'nodeCount' => $nodes->count(),
            'generatedAt' => now(),
            'appName' => config('app.name'),
            'operatorName' => $user?->name ?? '—',
            'operatorEmail' => $user?->email ?? '',
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'sans-serif');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'reports/' . str_replace(' ', '_', $graph->title) . '_' . now()->format('Ymd_His') . '.pdf';
        Storage::disk('local')->put($filename, $dompdf->output());

        $job->update([
            'status' => ReportJob::STATUS_COMPLETED,
            'file_path' => $filename,
            'finished_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        $job = ReportJob::find($this->reportJobId);
        if ($job) {
            $job->update([
                'status' => ReportJob::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }
}
