<?php

namespace App\Console\Commands;

use App\Models\Graph;
use App\Models\GraphEdge;
use App\Models\GraphNode;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class OsintDemoCommand extends Command
{
    protected $signature = 'osint:demo {--fresh : wipe existing demo data first}';
    protected $description = 'Seed sample project, investigation graph and templates.';

    public function handle(): int
    {
        $user = User::orderBy('id')->first();
        if (! $user) {
            $this->error('No users exist yet. Run /setup first.');
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            Project::where('name', 'DEMO-RECON')->get()->each->delete();
            Graph::where('type', Graph::TYPE_TEMPLATE)
                ->whereIn('title', ['Domain recon', 'Email recon', 'Offline demo'])
                ->get()->each->delete();
            $this->info('Wiped existing demo data.');
        }

        // ---- demo project + investigation graph ----
        $project = Project::firstOrCreate(
            ['name' => 'DEMO-RECON'],
            ['description' => 'Sample project seeded by osint:demo', 'created_by' => $user->id]
        );

        $investigation = Graph::firstOrCreate(
            ['project_id' => $project->id, 'title' => 'example.com investigation'],
            ['type' => Graph::TYPE_INVESTIGATION, 'created_by' => $user->id]
        );

        if ($investigation->nodes()->count() === 0) {
            $root = $this->makeNode($investigation, 'domain', 'example.com', 0, 0);
            $email = $this->makeNode($investigation, 'email', 'hostmaster@example.com', -260, 180);
            $note = $this->makeNode($investigation, 'note', 'starting point', 260, -180);
            $this->makeEdge($investigation, $root, $email);
            $this->makeEdge($investigation, $root, $note);
        }

        // ---- Domain recon template ----
        $domainTpl = Graph::firstOrCreate(
            ['type' => Graph::TYPE_TEMPLATE, 'title' => 'Domain recon'],
            ['description' => 'Resolve a domain, fetch title, WHOIS the domain.', 'created_by' => $user->id]
        );
        if ($domainTpl->nodes()->count() === 0) {
            $in = $this->makeTemplateInput($domainTpl, 'domain input', 0, 0);
            $dns = $this->makeTemplateTransform($domainTpl, 'dns.resolve', 220, -120);
            $http = $this->makeTemplateTransform($domainTpl, 'http.title', 220, 0);
            $whois = $this->makeTemplateTransform($domainTpl, 'whois.domain', 220, 140);
            $this->makeEdge($domainTpl, $in, $dns);
            $this->makeEdge($domainTpl, $in, $http);
            $this->makeEdge($domainTpl, $in, $whois);
        }

        // ---- Email recon template ----
        $emailTpl = Graph::firstOrCreate(
            ['type' => Graph::TYPE_TEMPLATE, 'title' => 'Email recon'],
            ['description' => 'Extract domain from email, compute gravatar URL.', 'created_by' => $user->id]
        );
        if ($emailTpl->nodes()->count() === 0) {
            $in = $this->makeTemplateInput($emailTpl, 'email input', 0, 0);
            $toDom = $this->makeTemplateTransform($emailTpl, 'email.to_domain', 220, -80);
            $grav = $this->makeTemplateTransform($emailTpl, 'email.gravatar', 220, 80);
            $dns = $this->makeTemplateTransform($emailTpl, 'dns.resolve', 440, -80);
            $this->makeEdge($emailTpl, $in, $toDom);
            $this->makeEdge($emailTpl, $in, $grav);
            $this->makeEdge($emailTpl, $toDom, $dns);
        }

        // ---- Offline demo template ----
        $offlineTpl = Graph::firstOrCreate(
            ['type' => Graph::TYPE_TEMPLATE, 'title' => 'Offline demo'],
            ['description' => 'Purely offline: echo then split. Useful to smoke-test the engine.', 'created_by' => $user->id]
        );
        if ($offlineTpl->nodes()->count() === 0) {
            $in = $this->makeTemplateInput($offlineTpl, 'any input', 0, 0);
            $echo = $this->makeTemplateTransform($offlineTpl, 'demo.echo', 220, 0);
            $this->makeEdge($offlineTpl, $in, $echo);
        }

        $this->info("Demo ready:");
        $this->line("  project: {$project->name} (id={$project->id})");
        $this->line("  investigation graph: {$investigation->title} (id={$investigation->id})");
        $this->line("  templates: Domain recon, Email recon, Offline demo");
        return self::SUCCESS;
    }

    protected function makeNode(Graph $g, string $type, string $value, float $x, float $y): GraphNode
    {
        return $g->nodes()->create([
            'cy_id' => 'n_' . Str::random(12),
            'entity_type' => $type,
            'value' => $value,
            'label' => $value,
            'position_x' => $x,
            'position_y' => $y,
        ]);
    }

    protected function makeTemplateInput(Graph $g, string $label, float $x, float $y): GraphNode
    {
        return $g->nodes()->create([
            'cy_id' => 'n_' . Str::random(12),
            'entity_type' => 'template:input',
            'value' => 'input',
            'label' => $label,
            'position_x' => $x,
            'position_y' => $y,
        ]);
    }

    protected function makeTemplateTransform(Graph $g, string $transformName, float $x, float $y): GraphNode
    {
        return $g->nodes()->create([
            'cy_id' => 'n_' . Str::random(12),
            'entity_type' => 'template:transform',
            'value' => $transformName,
            'label' => $transformName,
            'data' => ['transform_name' => $transformName],
            'position_x' => $x,
            'position_y' => $y,
        ]);
    }

    protected function makeEdge(Graph $g, GraphNode $from, GraphNode $to): GraphEdge
    {
        return $g->edges()->create([
            'cy_id' => 'e_' . Str::random(12),
            'source_cy_id' => $from->cy_id,
            'target_cy_id' => $to->cy_id,
        ]);
    }
}
