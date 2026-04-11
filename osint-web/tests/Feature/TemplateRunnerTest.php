<?php

use App\Jobs\RunTemplateJob;
use App\Models\Graph;
use App\Models\InvestigationJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->project = Project::create(['name' => 'p', 'created_by' => $this->user->id]);
    $this->inv = Graph::create([
        'project_id' => $this->project->id,
        'title' => 'inv', 'type' => 'investigation', 'created_by' => $this->user->id,
    ]);
    $this->src = $this->inv->nodes()->create([
        'cy_id' => 'n_src', 'entity_type' => 'domain', 'value' => 'example.com',
    ]);
});

it('POST /run-template enqueues a template job', function () {
    Bus::fake();
    $tpl = Graph::create(['title' => 't', 'type' => 'template', 'created_by' => $this->user->id]);

    $resp = $this->postJson("/api/graphs/{$this->inv->id}/run-template", [
        'template_id' => $tpl->id,
        'starting_cy_ids' => ['n_src'],
    ]);
    $resp->assertOk()->assertJsonPath('ok', true)->assertJsonStructure(['job_id']);

    Bus::assertDispatched(RunTemplateJob::class);
    expect(InvestigationJob::first()->kind)->toBe('template');
});

it('RunTemplateJob executes a simple template and streams outputs into the job row', function () {
    $tpl = Graph::create(['title' => 't', 'type' => 'template', 'created_by' => $this->user->id]);
    $tpl->nodes()->create(['cy_id' => 'n_in', 'entity_type' => 'template:input', 'value' => 'input']);
    $tpl->nodes()->create(['cy_id' => 'n_s1', 'entity_type' => 'template:transform', 'value' => 'dns.resolve', 'data' => ['transform_name' => 'dns.resolve']]);
    $tpl->edges()->create(['cy_id' => 'e1', 'source_cy_id' => 'n_in', 'target_cy_id' => 'n_s1']);

    Http::fake([
        '*/transforms' => Http::response(['transforms' => [
            ['name' => 'dns.resolve', 'input_types' => ['domain'], 'output_types' => ['ipv4'], 'required_api_keys' => []],
        ]]),
        '*/transforms/dns.resolve/run' => Http::response([
            'nodes' => [['type' => 'ipv4', 'value' => '1.2.3.4', 'label' => '1.2.3.4', 'data' => []]],
            'edges' => [], 'error' => null,
        ]),
    ]);

    $job = InvestigationJob::create([
        'graph_id' => $this->inv->id,
        'template_id' => $tpl->id,
        'user_id' => $this->user->id,
        'kind' => 'template',
        'source_cy_ids' => ['n_src'],
        'status' => 'queued',
    ]);

    (new RunTemplateJob($job->id))->handle(app(\App\Services\EngineClient::class));

    $job->refresh();
    expect($job->status)->toBe('completed')
        ->and(count($job->created_nodes))->toBe(1)
        ->and($job->created_nodes[0]['value'])->toBe('1.2.3.4');

    expect($this->inv->nodes()->count())->toBe(2);
    expect($this->inv->edges()->count())->toBe(1);
});
