<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Courier New', Courier, monospace;
        font-size: 10px;
        color: #0b1a0b;
        background: #f1f3ee;
        line-height: 1.5;
        padding: 30px 40px;
    }
    .header {
        border-bottom: 2px solid #006b32;
        padding-bottom: 12px;
        margin-bottom: 20px;
    }
    .header h1 {
        font-size: 22px;
        color: #006b32;
        letter-spacing: 3px;
        text-transform: uppercase;
        font-weight: normal;
    }
    .header .subtitle {
        color: #4d6149;
        font-size: 11px;
        margin-top: 4px;
        letter-spacing: 1px;
    }
    .meta-box {
        background: #ffffff;
        border: 1px solid #c3ccb8;
        padding: 12px 16px;
        margin-bottom: 20px;
        font-size: 10px;
    }
    .meta-box .row { margin: 3px 0; }
    .meta-box .label { color: #4d6149; display: inline-block; width: 120px; text-transform: uppercase; font-size: 9px; letter-spacing: 1px; }
    .section {
        margin-top: 24px;
        page-break-inside: avoid;
    }
    .section h2 {
        font-size: 13px;
        color: #006b32;
        text-transform: uppercase;
        letter-spacing: 2px;
        border-bottom: 1px dashed #c3ccb8;
        padding-bottom: 4px;
        margin-bottom: 10px;
        font-weight: normal;
    }
    .section h2 .badge {
        background: #006b32;
        color: #ffffff;
        padding: 1px 8px;
        font-size: 9px;
        margin-left: 8px;
        letter-spacing: 0;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9px;
        margin-bottom: 8px;
    }
    th {
        background: #e8ece3;
        color: #4d6149;
        text-transform: uppercase;
        font-size: 8px;
        letter-spacing: 1px;
        padding: 6px 8px;
        text-align: left;
        border-bottom: 1px solid #c3ccb8;
    }
    td {
        padding: 5px 8px;
        border-bottom: 1px dashed #dde3d7;
        word-break: break-all;
        vertical-align: top;
    }
    tr:nth-child(even) td { background: #f8faf5; }
    .color-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 2px;
        margin-right: 4px;
        vertical-align: middle;
    }
    .data-cell { font-size: 8px; color: #4d6149; max-width: 250px; overflow: hidden; }
    .data-key { color: #006b32; }
    .footer {
        margin-top: 30px;
        padding-top: 10px;
        border-top: 1px solid #c3ccb8;
        font-size: 8px;
        color: #8fa486;
        text-align: center;
    }
    .confidential {
        text-align: center;
        color: #a00020;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 3px;
        margin: 16px 0;
    }
</style>
</head>
<body>

<div class="header">
    <h1>&#9673; {{ $appName }}</h1>
    <div class="subtitle">investigation report</div>
</div>

<div class="confidential">&#9888; CONFIDENTIAL — authorized personnel only</div>

<div class="meta-box">
    <div class="row"><span class="label">Investigation</span> {{ $graph->title }}</div>
    <div class="row"><span class="label">Project</span> {{ $graph->project?->name ?? '—' }}</div>
    <div class="row"><span class="label">Nodes in report</span> {{ $nodeCount }}</div>
    <div class="row"><span class="label">Generated</span> {{ $generatedAt->format('Y-m-d H:i:s T') }}</div>
    <div class="row"><span class="label">Operator</span> {{ $operatorName ?? auth()->user()?->name ?? '—' }} ({{ $operatorEmail ?? auth()->user()?->email ?? '' }})</div>
</div>

@foreach($grouped as $type => $nodes)
@php $style = $entityTypes[$type] ?? $entityTypes['unknown'] ?? ['color' => '#888', 'icon' => '?', 'label' => $type]; @endphp
<div class="section">
    <h2>
        <span class="color-dot" style="background:{{ $style['color'] }}"></span>
        {{ $style['icon'] ?? '' }} {{ $style['label'] ?? $type }}
        <span class="badge">{{ count($nodes) }}</span>
    </h2>
    <table>
        <thead>
            <tr>
                <th style="width:35%">Value</th>
                <th style="width:25%">Label</th>
                <th style="width:40%">Data</th>
            </tr>
        </thead>
        <tbody>
        @foreach($nodes as $node)
            <tr>
                <td style="font-weight:bold;">{{ Str::limit($node->value, 80) }}</td>
                <td>{{ Str::limit($node->label ?? $node->value, 60) }}</td>
                <td class="data-cell">
                    @if($node->data && is_array($node->data))
                        @foreach(array_slice($node->data, 0, 8) as $k => $v)
                            @if($k !== 'record')
                                <span class="data-key">{{ $k }}:</span>
                                {{ is_array($v) || is_object($v) ? Str::limit(json_encode($v), 60) : Str::limit((string)$v, 60) }}<br>
                            @endif
                        @endforeach
                        @if(isset($node->data['record']) && is_array($node->data['record']))
                            <span class="data-key">record:</span>
                            @foreach(array_slice($node->data['record'], 0, 6) as $rk => $rv)
                                {{ $rk }}={{ Str::limit((string)$rv, 40) }};
                            @endforeach
                        @endif
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endforeach

<div class="footer">
    {{ $appName }} &mdash; Investigation Report &mdash; {{ $generatedAt->format('Y-m-d H:i') }}
    <script type="text/php">if(isset($pdf)){$pdf->page_text(490,773,"Page ".$PAGE_NUM." of ".$PAGE_COUNT,null,8,[0.56,0.64,0.53]);}</script>
</div>

</body>
</html>
