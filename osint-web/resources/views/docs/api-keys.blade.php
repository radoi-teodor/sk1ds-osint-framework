@extends('docs.layout')
@section('title', 'API Keys')
@section('content')
<h1>API Keys</h1>
<p>Many transforms need credentials for external APIs (Snusbase, Shodan, C99, etc.). The platform provides a secure vault where users store API keys, and your transform receives them decrypted at runtime.</p>

<h2>How it works</h2>
<ol>
    <li>User stores a key at <code>/api-keys</code> with a <strong>name</strong> like <code>SNUSBASE_API_KEY</code> and the raw value.</li>
    <li>The value is encrypted with AES-256-CBC (Laravel's <code>Crypt::encryptString</code>) — never visible in the UI again.</li>
    <li>Your transform declares <code>required_api_keys=["SNUSBASE_API_KEY"]</code>.</li>
    <li>When the user runs your transform, the platform decrypts the matching key and passes it in the <code>api_keys</code> dict.</li>
</ol>

<h2>Accessing keys in your transform</h2>
<pre>@transform(
    name="shodan.host",
    required_api_keys=["SHODAN_API_KEY"],
    ...
)
def run(node, api_keys):
    key = api_keys.get("SHODAN_API_KEY")
    if not key:
        return [Node(type="note", value="missing SHODAN_API_KEY", label="missing key")]
    # Use key in your API call...
    response = urllib.request.urlopen(f"https://api.shodan.io/...?key={key}")
    ...</pre>

<h2>Key naming convention</h2>
<p>Names must be <strong>uppercase</strong> with underscores: <code>^[A-Z][A-Z0-9_]*$</code></p>
<ul>
    <li><code>SNUSBASE_API_KEY</code></li>
    <li><code>SHODAN_API_KEY</code></li>
    <li><code>C99_API_KEY</code></li>
    <li><code>VIRUSTOTAL_KEY</code></li>
</ul>

<h2>Multiple keys</h2>
<p>A transform can require multiple keys:</p>
<pre>@transform(
    required_api_keys=["PRIMARY_KEY", "SECONDARY_KEY"],
    ...
)
def run(node, api_keys):
    primary = api_keys.get("PRIMARY_KEY")
    secondary = api_keys.get("SECONDARY_KEY")</pre>

<h2>Missing keys</h2>
<p>If a key is not in the vault, it simply won't be in the <code>api_keys</code> dict. The platform does <strong>not</strong> reject the transform — your code is responsible for checking and returning a helpful error node.</p>

<div class="callout">
    <strong>Pattern:</strong> Always check <code>api_keys.get("KEY_NAME")</code> and return a <code>Node(type="note", value="missing KEY_NAME")</code> if absent. This gives the user clear feedback instead of a cryptic exception.
</div>
@endsection
