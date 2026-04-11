<?php

use App\Models\Graph;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates a project', function () {
    $this->post('/projects', ['name' => 'RECON-1'])->assertRedirect();
    expect(Project::where('name', 'RECON-1')->exists())->toBeTrue();
});

it('creates an investigation graph inside a project', function () {
    $p = Project::create(['name' => 'p', 'created_by' => $this->user->id]);
    $this->post("/projects/{$p->id}/graphs", [
        'title' => 'G1', 'type' => 'investigation',
    ])->assertRedirect();
    expect(Graph::where('project_id', $p->id)->count())->toBe(1);
});

it('adds a node via the JSON api', function () {
    $p = Project::create(['name' => 'p', 'created_by' => $this->user->id]);
    $g = Graph::create(['project_id' => $p->id, 'title' => 'G', 'type' => 'investigation', 'created_by' => $this->user->id]);
    $resp = $this->postJson("/api/graphs/{$g->id}/nodes", [
        'entity_type' => 'domain',
        'value' => 'example.com',
    ]);
    $resp->assertOk()->assertJsonPath('node.entity_type', 'domain');
    expect($g->nodes()->count())->toBe(1);
});

it('deletes a node and cascades its edges', function () {
    $p = Project::create(['name' => 'p', 'created_by' => $this->user->id]);
    $g = Graph::create(['project_id' => $p->id, 'title' => 'G', 'type' => 'investigation', 'created_by' => $this->user->id]);
    $a = $g->nodes()->create(['cy_id' => 'n_a', 'entity_type' => 'domain', 'value' => 'a']);
    $b = $g->nodes()->create(['cy_id' => 'n_b', 'entity_type' => 'ipv4', 'value' => '1.1.1.1']);
    $g->edges()->create(['cy_id' => 'e_1', 'source_cy_id' => 'n_a', 'target_cy_id' => 'n_b']);

    $this->deleteJson("/api/graphs/{$g->id}/nodes/n_a")->assertOk();
    expect($g->nodes()->count())->toBe(1);
    expect($g->edges()->count())->toBe(0);
});
