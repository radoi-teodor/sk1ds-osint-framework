@extends('docs.layout')
@section('title', 'Entity Types')
@section('content')
<h1>Entity Types</h1>
<p>Every node in the graph has a <code>type</code> string that drives its visual appearance (color, shape, icon) and determines which transforms are compatible with it.</p>

<h2>Built-in types</h2>
<table>
    <thead><tr><th>Type</th><th>Icon</th><th>Color</th><th>Shape</th><th>Typical use</th></tr></thead>
    <tbody>
    @foreach(config('osint.entity_types') as $type => $meta)
        <tr>
            <td><code>{{ $type }}</code></td>
            <td style="font-size:16px;">{{ $meta['icon'] }}</td>
            <td><span style="display:inline-block;width:14px;height:14px;background:{{ $meta['color'] }};border:1px solid var(--border);vertical-align:middle;margin-right:6px;"></span> <code style="font-size:11px;">{{ $meta['color'] }}</code></td>
            <td><code>{{ $meta['shape'] }}</code></td>
            <td class="text-dim">
                @switch($type)
                    @case('domain') Domain names (example.com) @break
                    @case('ipv4') IPv4 addresses @break
                    @case('ipv6') IPv6 addresses @break
                    @case('url') Full URLs @break
                    @case('email') Email addresses @break
                    @case('username') Usernames / handles @break
                    @case('password') Plaintext passwords (from breach data) @break
                    @case('breach') Breach database source names @break
                    @case('phone') Phone numbers @break
                    @case('person') Real names @break
                    @case('organization') Companies, ISPs, orgs @break
                    @case('location') Geographic locations @break
                    @case('hash') Hashes (MD5, SHA, etc.) @break
                    @case('file') File references @break
                    @case('title') Page titles @break
                    @case('note') Freeform notes / misc info @break
                    @case('whois_record') Raw WHOIS data @break
                    @case('asn') Autonomous system numbers @break
                    @case('port') Open ports (value = host:port) @break
                    @case('certificate') TLS/SSL certificates @break
                    @case('unknown') Fallback for unrecognized types @break
                    @default — @break
                @endswitch
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<h2>Using types in transforms</h2>
<pre>@transform(
    input_types=["domain", "ipv4"],   # only appears on domain and IP nodes
    output_types=["port", "note"],    # informs template editor
    ...
)</pre>

<h2>Wildcard</h2>
<p>Use <code>input_types=["*"]</code> to accept <strong>any</strong> node type. Useful for utility transforms like hashing, entropy, or extractors.</p>

<h2>Custom types</h2>
<p>You can emit any <code>type</code> string — the graph will render it with the <code>unknown</code> style (gray, round-rectangle). To add a new type with a custom color/shape/icon, edit <code>osint-web/config/osint.php</code> → <code>entity_types</code> array.</p>

<div class="callout">
    <strong>Tip:</strong> In the template editor, the "compatible next steps" menu filters transforms by checking if the current step's <code>output_types</code> intersect with the next transform's <code>input_types</code>. Declaring accurate <code>output_types</code> makes template building smoother.
</div>
@endsection
