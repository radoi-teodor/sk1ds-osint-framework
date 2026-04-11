<?php

use App\Jobs\RunTransformJob;
use App\Models\Graph;
use App\Models\InvestigationJob;
use App\Models\Project;
use App\Models\TransformationRun;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->project = Project::create(['name' => 'p', 'created_by' => $this->user->id]);
    $this->graph = Graph::create([
        'project_id' => $this->project->id,
        'title' => 'g',
        'type' => 'investigation',
        'created_by' => $this->user->id,
    ]);
    $this->node = $this->graph->nodes()->create([
        'cy_id' => 'n_src',
        'entity_type' => 'domain',
        'value' => 'example.com',
    ]);
});

it('POST /run-transform enqueues a job and returns its id', function () {
    Bus::fake();

    $resp = $this->postJson("/api/graphs/{$this->graph->id}/run-transform", [
        'source_cy_id' => 'n_src',
        'transform' => 'dns.resolve',
    ]);
    $resp->assertOk()->assertJsonPath('ok', true)->assertJsonStructure(['job_id', 'status']);

    Bus::assertDispatched(RunTransformJob::class);

    $job = InvestigationJob::first();
    expect($job)->not->toBeNull()
        ->and($job->kind)->toBe('transform')
        ->and($job->transform_name)->toBe('dns.resolve')
        ->and($job->source_cy_ids)->toBe(['n_src'])
        ->and($job->status)->toBe('queued');
});

it('RunTransformJob processes a node and persists children', function () {
    Http::fake([
        '*/transforms' => Http::response(['transforms' => [
            ['name' => 'dns.resolve', 'display_name' => 'DNS', 'input_types' => ['domain'], 'output_types' => ['ipv4'], 'required_api_keys' => []],
        ]]),
        '*/transforms/dns.resolve/run' => Http::response([
            'nodes' => [
                ['type' => 'ipv4', 'value' => '93.184.216.34', 'label' => '93.184.216.34', 'data' => []],
                ['type' => 'ipv4', 'value' => '1.1.1.1', 'label' => '1.1.1.1', 'data' => []],
            ],
            'edges' => [],
            'error' => null,
        ]),
    ]);

    $job = InvestigationJob::create([
        'graph_id' => $this->graph->id,
        'user_id' => $this->user->id,
        'kind' => 'transform',
        'transform_name' => 'dns.resolve',
        'source_cy_ids' => ['n_src'],
        'status' => 'queued',
        'progress_total' => 1,
    ]);

    (new RunTransformJob($job->id))->handle(app(\App\Services\EngineClient::class));

    $job->refresh();
    expect($job->status)->toBe('completed')
        ->and($job->progress_done)->toBe(1)
        ->and(count($job->created_nodes))->toBe(2)
        ->and(count($job->created_edges))->toBe(2);

    expect($this->graph->nodes()->count())->toBe(3);
    expect($this->graph->edges()->count())->toBe(2);
    expect(TransformationRun::where('job_id', $job->id)->count())->toBe(1);
});

it('RunTransformJob records an error when the engine returns one', function () {
    Http::fake([
        '*/transforms' => Http::response(['transforms' => [
            ['name' => 'broken', 'input_types' => ['domain'], 'output_types' => [], 'required_api_keys' => []],
        ]]),
        '*/transforms/broken/run' => Http::response(['error' => 'oops', 'nodes' => [], 'edges' => []]),
    ]);

    $job = InvestigationJob::create([
        'graph_id' => $this->graph->id,
        'kind' => 'transform',
        'transform_name' => 'broken',
        'source_cy_ids' => ['n_src'],
        'status' => 'queued',
        'progress_total' => 1,
    ]);
    (new RunTransformJob($job->id))->handle(app(\App\Services\EngineClient::class));

    $job->refresh();
    expect($job->status)->toBe('completed')
        ->and($job->error)->toContain('oops');
    expect(TransformationRun::first()->error)->toBe('oops');
});

it('GET /api/jobs/{id} returns incremental slices via since offsets', function () {
    $job = InvestigationJob::create([
        'graph_id' => $this->graph->id,
        'kind' => 'transform',
        'transform_name' => 'demo.echo',
        'source_cy_ids' => ['n_src'],
        'status' => 'running',
        'progress_total' => 3,
        'progress_done' => 1,
        'created_nodes' => [
            ['cy_id' => 'n_a', 'entity_type' => 'note', 'value' => 'a', 'label' => 'a', 'data' => [], 'position_x' => 0, 'position_y' => 0],
            ['cy_id' => 'n_b', 'entity_type' => 'note', 'value' => 'b', 'label' => 'b', 'data' => [], 'position_x' => 0, 'position_y' => 0],
        ],
        'created_edges' => [
            ['cy_id' => 'e_a', 'source' => 'n_src', 'target' => 'n_a', 'label' => null, 'data' => []],
        ],
    ]);

    $first = $this->getJson("/api/jobs/{$job->id}");
    $first->assertOk()
        ->assertJsonPath('total_nodes', 2)
        ->assertJsonCount(2, 'new_nodes');

    $second = $this->getJson("/api/jobs/{$job->id}?since_nodes=2&since_edges=1");
    $second->assertOk()
        ->assertJsonPath('total_nodes', 2)
        ->assertJsonCount(0, 'new_nodes')
        ->assertJsonCount(0, 'new_edges');
});
