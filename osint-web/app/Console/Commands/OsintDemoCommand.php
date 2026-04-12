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

    protected const TEMPLATE_NAMES = [
        'Domain recon', 'Email recon', 'Offline demo',
        'Passive recon', 'Active recon (slave)', 'Person leak investigation',
        'Domain leak investigation', 'Web application recon',
    ];

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
                ->whereIn('title', self::TEMPLATE_NAMES)
                ->get()->each->delete();
            $this->info('Wiped existing demo data.');
        }

        $uid = $user->id;

        // ---- demo project + investigation graph ----
        $project = Project::firstOrCreate(
            ['name' => 'DEMO-RECON'],
            ['description' => 'Sample project seeded by osint:demo', 'created_by' => $uid]
        );
        $investigation = Graph::firstOrCreate(
            ['project_id' => $project->id, 'title' => 'example.com investigation'],
            ['type' => Graph::TYPE_INVESTIGATION, 'created_by' => $uid]
        );
        if ($investigation->nodes()->count() === 0) {
            $root = $this->n($investigation, 'domain', 'example.com', 0, 0);
            $email = $this->n($investigation, 'email', 'hostmaster@example.com', -260, 180);
            $note = $this->n($investigation, 'note', 'starting point', 260, -180);
            $this->e($investigation, $root, $email);
            $this->e($investigation, $root, $note);
        }

        // ===========================================================
        // TEMPLATES
        // ===========================================================

        // 1. Passive recon — domain → DNS, WHOIS, crt.sh, HTTP headers, etc.
        $this->tpl($uid, 'Passive recon',
            'Comprehensive passive reconnaissance on a domain. No slave needed, no API keys. Runs DNS, WHOIS, crt.sh subdomains, HTTP title/headers/security, TLD extraction, IP geolocation.',
            function (Graph $g) {
                $in  = $this->ti($g, 'domain', 0, 0);
                $dns = $this->tt($g, 'dns.resolve', 240, -200);
                $geo = $this->tt($g, 'ip.geo', 480, -240);
                $cls = $this->tt($g, 'ip.classify', 480, -160);
                $rdns = $this->tt($g, 'dns.reverse', 480, -320);
                $tit = $this->tt($g, 'http.title', 240, -60);
                $hdr = $this->tt($g, 'http.headers', 240, 60);
                $sec = $this->tt($g, 'http.security_headers', 240, 180);
                $who = $this->tt($g, 'whois.domain', 240, 300);
                $crt = $this->tt($g, 'crtsh.subdomains', 240, 420);
                $tld = $this->tt($g, 'domain.tld', 240, -340);

                $this->e($g, $in, $dns);
                $this->e($g, $dns, $geo);
                $this->e($g, $dns, $cls);
                $this->e($g, $dns, $rdns);
                $this->e($g, $in, $tit);
                $this->e($g, $in, $hdr);
                $this->e($g, $in, $sec);
                $this->e($g, $in, $who);
                $this->e($g, $in, $crt);
                $this->e($g, $in, $tld);
            }
        );

        // 2. Active recon — domain/IP → nmap scans → service/SSL/HTTP enum
        $this->tpl($uid, 'Active recon (slave)',
            'Active network scanning via a slave. Runs nmap top 100, then service detection, SSL cert analysis, and HTTP enumeration on discovered ports. Requires a slave with nmap installed.',
            function (Graph $g) {
                $in   = $this->ti($g, 'target (domain/IP)', 0, 0);
                $ping = $this->tt($g, 'nmap.ping', 240, -200);
                $scan = $this->tt($g, 'nmap.top100', 240, 0);
                $svc  = $this->tt($g, 'nmap.service', 480, -120);
                $ssl  = $this->tt($g, 'nmap.ssl', 480, 0);
                $http = $this->tt($g, 'nmap.http', 480, 120);
                $fav  = $this->tt($g, 'http.favicon_hash', 240, 200);

                $this->e($g, $in, $ping);
                $this->e($g, $in, $scan);
                $this->e($g, $scan, $svc);
                $this->e($g, $scan, $ssl);
                $this->e($g, $scan, $http);
                $this->e($g, $in, $fav);
            }
        );

        // 3. Person leak investigation — email → Snusbase → extract fields → geo
        $this->tpl($uid, 'Person leak investigation',
            'Searches Snusbase for breach records tied to an email, extracts passwords, usernames, and IPs, then geolocates discovered IPs. Requires SNUSBASE_API_KEY.',
            function (Graph $g) {
                $in   = $this->ti($g, 'email', 0, 0);
                $snus = $this->tt($g, 'snusbase.email', 240, 0);
                $pw   = $this->tt($g, 'snusbase.extract_password', 480, -160);
                $user = $this->tt($g, 'snusbase.extract_username', 480, -60);
                $ip   = $this->tt($g, 'snusbase.extract_lastip', 480, 60);
                $hash = $this->tt($g, 'snusbase.extract_hash', 480, 160);
                $geo  = $this->tt($g, 'ip.geo', 720, 60);
                $rev  = $this->tt($g, 'snusbase.hash_reverse', 720, 160);

                $this->e($g, $in, $snus);
                $this->e($g, $snus, $pw);
                $this->e($g, $snus, $user);
                $this->e($g, $snus, $ip);
                $this->e($g, $snus, $hash);
                $this->e($g, $ip, $geo);
                $this->e($g, $hash, $rev);
            }
        );

        // 4. Domain leak investigation — domain → Snusbase → extract everything
        $this->tpl($uid, 'Domain leak investigation',
            'Finds all leaked emails under a domain via Snusbase, then extracts passwords, hashes, usernames, and IPs from each record. Requires SNUSBASE_API_KEY.',
            function (Graph $g) {
                $in   = $this->ti($g, 'domain', 0, 0);
                $snus = $this->tt($g, 'snusbase.domain', 240, 0);
                $pw   = $this->tt($g, 'snusbase.extract_password', 480, -120);
                $user = $this->tt($g, 'snusbase.extract_username', 480, 0);
                $ip   = $this->tt($g, 'snusbase.extract_lastip', 480, 120);
                $hash = $this->tt($g, 'snusbase.extract_hash', 480, 240);

                $this->e($g, $in, $snus);
                $this->e($g, $snus, $pw);
                $this->e($g, $snus, $user);
                $this->e($g, $snus, $ip);
                $this->e($g, $snus, $hash);
            }
        );

        // 5. Web application recon — domain → HTTP probes + secrets scan + subdomains
        $this->tpl($uid, 'Web application recon',
            'Web-focused passive recon: HTTP title, response headers, security headers audit, robots.txt, favicon hash, secrets scan on the target, plus crt.sh subdomain enumeration.',
            function (Graph $g) {
                $in   = $this->ti($g, 'domain/url', 0, 0);
                $tit  = $this->tt($g, 'http.title', 240, -240);
                $hdr  = $this->tt($g, 'http.headers', 240, -120);
                $sec  = $this->tt($g, 'http.security_headers', 240, 0);
                $rob  = $this->tt($g, 'http.robots', 240, 120);
                $fav  = $this->tt($g, 'http.favicon_hash', 240, 240);
                $scrt = $this->tt($g, 'web.secrets_scan', 240, 360);
                $crt  = $this->tt($g, 'crtsh.subdomains', 240, 480);
                $dns  = $this->tt($g, 'dns.resolve', 480, 480);

                $this->e($g, $in, $tit);
                $this->e($g, $in, $hdr);
                $this->e($g, $in, $sec);
                $this->e($g, $in, $rob);
                $this->e($g, $in, $fav);
                $this->e($g, $in, $scrt);
                $this->e($g, $in, $crt);
                $this->e($g, $crt, $dns);
            }
        );

        // Keep old simple ones for backward compat
        $this->tpl($uid, 'Domain recon',
            'Simple domain recon: DNS resolve, HTTP title, WHOIS.',
            function (Graph $g) {
                $in = $this->ti($g, 'domain input', 0, 0);
                $dns = $this->tt($g, 'dns.resolve', 220, -120);
                $http = $this->tt($g, 'http.title', 220, 0);
                $whois = $this->tt($g, 'whois.domain', 220, 140);
                $this->e($g, $in, $dns);
                $this->e($g, $in, $http);
                $this->e($g, $in, $whois);
            }
        );

        $this->tpl($uid, 'Email recon',
            'Extract domain from email, compute gravatar, resolve DNS.',
            function (Graph $g) {
                $in = $this->ti($g, 'email input', 0, 0);
                $toDom = $this->tt($g, 'email.to_domain', 220, -80);
                $grav = $this->tt($g, 'email.gravatar', 220, 80);
                $dns = $this->tt($g, 'dns.resolve', 440, -80);
                $this->e($g, $in, $toDom);
                $this->e($g, $in, $grav);
                $this->e($g, $toDom, $dns);
            }
        );

        $this->tpl($uid, 'Offline demo',
            'Purely offline: echo then split. Useful to smoke-test the engine.',
            function (Graph $g) {
                $in = $this->ti($g, 'any input', 0, 0);
                $echo = $this->tt($g, 'demo.echo', 220, 0);
                $this->e($g, $in, $echo);
            }
        );

        $this->info("Demo ready:");
        $this->line("  project: {$project->name} (id={$project->id})");
        $this->line("  investigation: {$investigation->title} (id={$investigation->id})");
        $this->line("  templates: " . implode(', ', self::TEMPLATE_NAMES));
        return self::SUCCESS;
    }

    protected function tpl(int $uid, string $title, string $desc, callable $builder): void
    {
        $g = Graph::firstOrCreate(
            ['type' => Graph::TYPE_TEMPLATE, 'title' => $title],
            ['description' => $desc, 'created_by' => $uid]
        );
        if ($g->nodes()->count() === 0) {
            $builder($g);
            $this->line("  + template: {$title}");
        }
    }

    protected function n(Graph $g, string $type, string $value, float $x, float $y): GraphNode
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

    protected function ti(Graph $g, string $label, float $x, float $y): GraphNode
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

    protected function tt(Graph $g, string $transformName, float $x, float $y): GraphNode
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

    protected function e(Graph $g, GraphNode $from, GraphNode $to): GraphEdge
    {
        return $g->edges()->create([
            'cy_id' => 'e_' . Str::random(12),
            'source_cy_id' => $from->cy_id,
            'target_cy_id' => $to->cy_id,
        ]);
    }
}
