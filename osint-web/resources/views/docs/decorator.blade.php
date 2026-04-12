@extends('docs.layout')
@section('title', '@transform Decorator')
@section('content')
<h1>&#64;transform Decorator</h1>
<p>The <code>@transform</code> decorator registers a function as a transformation. It's the only thing you need to make your code discoverable by the engine.</p>

<pre>from osint_engine.sdk import transform

@transform(
    name="category.action",
    display_name="Human Readable Name",
    description="Longer description shown in tooltips",
    category="mycategory",
    input_types=["domain", "ipv4"],
    output_types=["note", "port"],
    required_api_keys=["MY_API_KEY"],
    requires_slave=True,
    timeout=30,
    author="your-name",
)
def run(node, api_keys, slave):
    ...</pre>

<h2>Parameters</h2>
<table>
    <thead><tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Description</th></tr></thead>
    <tbody>
        <tr>
            <td><code>name</code></td><td>str</td><td>yes</td><td>—</td>
            <td>Unique identifier. Convention: <code>category.action</code> (e.g., <code>dns.resolve</code>, <code>snusbase.email</code>). Must be unique across all loaded transforms.</td>
        </tr>
        <tr>
            <td><code>display_name</code></td><td>str</td><td>yes</td><td>—</td>
            <td>Human-readable name shown in the UI context menu and transforms table.</td>
        </tr>
        <tr>
            <td><code>description</code></td><td>str</td><td>no</td><td><code>""</code></td>
            <td>Longer description shown in tooltips and the sidebar.</td>
        </tr>
        <tr>
            <td><code>category</code></td><td>str</td><td>no</td><td><code>"general"</code></td>
            <td>Groups transforms in the UI sidebar and context menu. Built-in categories: <code>network</code>, <code>web</code>, <code>parsing</code>, <code>crypto</code>, <code>recon</code>, <code>snusbase</code>, <code>demo</code>.</td>
        </tr>
        <tr>
            <td><code>input_types</code></td><td>list[str]</td><td>no</td><td><code>["*"]</code></td>
            <td>Which node types this transform accepts. Use <code>["*"]</code> for any. The UI only shows the transform in context menus of matching nodes. See <a href="/docs/entity-types">Entity Types</a>.</td>
        </tr>
        <tr>
            <td><code>output_types</code></td><td>list[str]</td><td>no</td><td><code>[]</code></td>
            <td>What node types this transform produces. Informational — used by template editor to filter compatible next steps.</td>
        </tr>
        <tr>
            <td><code>required_api_keys</code></td><td>list[str]</td><td>no</td><td><code>[]</code></td>
            <td>Names of API keys this transform needs (e.g., <code>["SNUSBASE_API_KEY"]</code>). The platform decrypts matching keys from the vault and passes them in the <code>api_keys</code> dict. See <a href="/docs/api-keys">API Keys</a>.</td>
        </tr>
        <tr>
            <td><code>requires_slave</code></td><td>bool</td><td>no</td><td><code>False</code></td>
            <td>If <code>True</code>, the function receives a third argument: a <code>SlaveClient</code> for executing commands on a remote or local server. The UI shows a <code>🖥 slave</code> badge and requires the user to select a slave before running. See <a href="/docs/slave">SlaveClient</a>.</td>
        </tr>
        <tr>
            <td><code>timeout</code></td><td>int</td><td>no</td><td><code>30</code></td>
            <td>Maximum seconds the function may run before being killed. Applies per invocation (if run on multiple nodes, each gets its own timeout). The platform also uses this value to set the HTTP request timeout between Laravel and the engine (<code>timeout + 30s</code>), so set it generously for slow transforms like nmap or feroxbuster.</td>
        </tr>
        <tr>
            <td><code>author</code></td><td>str</td><td>no</td><td><code>""</code></td>
            <td>Your name/tag. Informational only.</td>
        </tr>
    </tbody>
</table>

<h2>Name conventions</h2>
<p>Use <code>provider.action</code> format:</p>
<ul>
    <li><code>dns.resolve</code>, <code>dns.reverse</code> — network tools</li>
    <li><code>snusbase.email</code>, <code>snusbase.extract_hash</code> — provider-specific</li>
    <li><code>nmap.top100</code>, <code>nmap.ssl</code> — slave-based scans</li>
    <li><code>string.hashes</code>, <code>string.entropy</code> — offline utilities</li>
</ul>

<h2>Multiple transforms per file</h2>
<p>Perfectly valid — one <code>.py</code> file can contain any number of <code>@transform</code> decorated functions. They all register when the file is loaded.</p>
<pre># transforms/my_suite.py

@transform(name="my.alpha", ...)
def run_alpha(node, api_keys): ...

@transform(name="my.beta", ...)
def run_beta(node, api_keys): ...</pre>
@endsection
