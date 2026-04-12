<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportJob;
use App\Models\Graph;
use App\Models\ReportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function flag(Request $request, Graph $graph): JsonResponse
    {
        $data = $request->validate([
            'cy_ids' => ['required', 'array', 'min:1'],
            'cy_ids.*' => ['string'],
            'flagged' => ['required', 'boolean'],
        ]);
        $graph->nodes()
            ->whereIn('cy_id', $data['cy_ids'])
            ->update(['flagged_for_report' => $data['flagged']]);
        return response()->json(['ok' => true]);
    }

    public function flagAll(Request $request, Graph $graph): JsonResponse
    {
        $data = $request->validate(['flagged' => ['required', 'boolean']]);
        $graph->nodes()->update(['flagged_for_report' => $data['flagged']]);
        return response()->json(['ok' => true]);
    }

    public function generate(Request $request, Graph $graph): JsonResponse
    {
        $count = $graph->nodes()->where('flagged_for_report', true)->count();
        if ($count === 0) {
            return response()->json(['ok' => false, 'error' => 'No nodes flagged for report'], 422);
        }

        $job = ReportJob::create([
            'graph_id' => $graph->id,
            'user_id' => $request->user()?->id,
            'status' => ReportJob::STATUS_QUEUED,
            'node_count' => $count,
        ]);

        GenerateReportJob::dispatch($job->id);

        return response()->json(['ok' => true, 'report_job_id' => $job->id]);
    }

    public function poll(ReportJob $report_job): JsonResponse
    {
        return response()->json([
            'id' => $report_job->id,
            'status' => $report_job->status,
            'node_count' => $report_job->node_count,
            'error' => $report_job->error,
            'started_at' => $report_job->started_at,
            'finished_at' => $report_job->finished_at,
        ]);
    }

    public function download(ReportJob $report_job)
    {
        if ($report_job->status !== ReportJob::STATUS_COMPLETED || ! $report_job->file_path) {
            abort(404, 'Report not ready');
        }
        $path = Storage::disk('local')->path($report_job->file_path);
        if (! file_exists($path)) {
            abort(404, 'File not found');
        }
        return response()->download($path, basename($report_job->file_path), [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
