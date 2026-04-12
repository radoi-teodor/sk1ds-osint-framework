<?php

namespace Database\Seeders;

use App\Models\Graph;
use App\Models\GraphEdge;
use App\Models\GraphNode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Passive recon
        $this->tpl('Passive recon',
            'Comprehensive passive recon on a domain: DNS, WHOIS, crt.sh subdomains, HTTP title/headers/security, TLD, IP geo.',
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
                $this->e($g, $in, $dns); $this->e($g, $dns, $geo); $this->e($g, $dns, $cls);
                $this->e($g, $dns, $rdns); $this->e($g, $in, $tit); $this->e($g, $in, $hdr);
                $this->e($g, $in, $sec); $this->e($g, $in, $who); $this->e($g, $in, $crt);
                $this->e($g, $in, $tld);
            }
        );

        // 2. Active recon
        $this->tpl('Active recon (slave)',
            'Active network scanning via slave: nmap top 100 → service/SSL/HTTP enum. Requires slave with nmap.',
            function (Graph $g) {
                $in   = $this->ti($g, 'target (domain/IP)', 0, 0);
                $ping = $this->tt($g, 'nmap.ping', 240, -200);
                $scan = $this->tt($g, 'nmap.top100', 240, 0);
                $svc  = $this->tt($g, 'nmap.service', 480, -120);
                $ssl  = $this->tt($g, 'nmap.ssl', 480, 0);
                $http = $this->tt($g, 'nmap.http', 480, 120);
                $fav  = $this->tt($g, 'http.favicon_hash', 240, 200);
                $this->e($g, $in, $ping); $this->e($g, $in, $scan);
                $this->e($g, $scan, $svc); $this->e($g, $scan, $ssl);
                $this->e($g, $scan, $http); $this->e($g, $in, $fav);
            }
        );

        // 3. Person leak investigation
        $this->tpl('Person leak investigation',
            'Snusbase email search → extract passwords, usernames, IPs, hashes → geo + hash reverse. Requires SNUSBASE_API_KEY.',
            function (Graph $g) {
                $in   = $this->ti($g, 'email', 0, 0);
                $snus = $this->tt($g, 'snusbase.email', 240, 0);
                $pw   = $this->tt($g, 'snusbase.extract_password', 480, -160);
                $user = $this->tt($g, 'snusbase.extract_username', 480, -60);
                $ip   = $this->tt($g, 'snusbase.extract_lastip', 480, 60);
                $hash = $this->tt($g, 'snusbase.extract_hash', 480, 160);
                $geo  = $this->tt($g, 'ip.geo', 720, 60);
                $rev  = $this->tt($g, 'snusbase.hash_reverse', 720, 160);
                $this->e($g, $in, $snus); $this->e($g, $snus, $pw); $this->e($g, $snus, $user);
                $this->e($g, $snus, $ip); $this->e($g, $snus, $hash);
                $this->e($g, $ip, $geo); $this->e($g, $hash, $rev);
            }
        );

        // 4. Domain leak investigation
        $this->tpl('Domain leak investigation',
            'Snusbase domain search → extract passwords, hashes, usernames, IPs. Requires SNUSBASE_API_KEY.',
            function (Graph $g) {
                $in   = $this->ti($g, 'domain', 0, 0);
                $snus = $this->tt($g, 'snusbase.domain', 240, 0);
                $pw   = $this->tt($g, 'snusbase.extract_password', 480, -120);
                $user = $this->tt($g, 'snusbase.extract_username', 480, 0);
                $ip   = $this->tt($g, 'snusbase.extract_lastip', 480, 120);
                $hash = $this->tt($g, 'snusbase.extract_hash', 480, 240);
                $this->e($g, $in, $snus); $this->e($g, $snus, $pw); $this->e($g, $snus, $user);
                $this->e($g, $snus, $ip); $this->e($g, $snus, $hash);
            }
        );

        // 5. Web application recon
        $this->tpl('Web application recon',
            'Web-focused passive recon: HTTP title/headers/security/robots, favicon hash, secrets scan, crt.sh → DNS.',
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
                $this->e($g, $in, $tit); $this->e($g, $in, $hdr); $this->e($g, $in, $sec);
                $this->e($g, $in, $rob); $this->e($g, $in, $fav); $this->e($g, $in, $scrt);
                $this->e($g, $in, $crt); $this->e($g, $crt, $dns);
            }
        );

        // 6. Simple domain recon
        $this->tpl('Domain recon',
            'Simple: DNS resolve, HTTP title, WHOIS.',
            function (Graph $g) {
                $in = $this->ti($g, 'domain input', 0, 0);
                $dns = $this->tt($g, 'dns.resolve', 220, -120);
                $http = $this->tt($g, 'http.title', 220, 0);
                $whois = $this->tt($g, 'whois.domain', 220, 140);
                $this->e($g, $in, $dns); $this->e($g, $in, $http); $this->e($g, $in, $whois);
            }
        );

        // 7. Email recon
        $this->tpl('Email recon',
            'Email → domain + gravatar + DNS resolve.',
            function (Graph $g) {
                $in = $this->ti($g, 'email input', 0, 0);
                $toDom = $this->tt($g, 'email.to_domain', 220, -80);
                $grav = $this->tt($g, 'email.gravatar', 220, 80);
                $dns = $this->tt($g, 'dns.resolve', 440, -80);
                $this->e($g, $in, $toDom); $this->e($g, $in, $grav); $this->e($g, $toDom, $dns);
            }
        );

        // 8. Offline demo
        $this->tpl('Offline demo',
            'Echo then split — offline smoke test.',
            function (Graph $g) {
                $in = $this->ti($g, 'any input', 0, 0);
                $echo = $this->tt($g, 'demo.echo', 220, 0);
                $this->e($g, $in, $echo);
            }
        );
    }

    protected function tpl(string $title, string $desc, callable $builder): void
    {
        $g = Graph::firstOrCreate(
            ['type' => Graph::TYPE_TEMPLATE, 'title' => $title],
            ['description' => $desc]
        );
        if ($g->nodes()->count() === 0) {
            $builder($g);
        }
    }

    protected function ti(Graph $g, string $label, float $x, float $y): GraphNode
    {
        return $g->nodes()->create([
            'cy_id' => 'n_' . Str::random(12), 'entity_type' => 'template:input',
            'value' => 'input', 'label' => $label, 'position_x' => $x, 'position_y' => $y,
        ]);
    }

    protected function tt(Graph $g, string $name, float $x, float $y): GraphNode
    {
        return $g->nodes()->create([
            'cy_id' => 'n_' . Str::random(12), 'entity_type' => 'template:transform',
            'value' => $name, 'label' => $name, 'data' => ['transform_name' => $name],
            'position_x' => $x, 'position_y' => $y,
        ]);
    }

    protected function e(Graph $g, GraphNode $from, GraphNode $to): GraphEdge
    {
        return $g->edges()->create([
            'cy_id' => 'e_' . Str::random(12),
            'source_cy_id' => $from->cy_id, 'target_cy_id' => $to->cy_id,
        ]);
    }
}
