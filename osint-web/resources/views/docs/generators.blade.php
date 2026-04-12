@extends('docs.layout')
@section('title', 'Generators')
@section('content')
<h1>Generators</h1>
<p>Generators are Python scripts that produce a <strong>string output</strong> consumed by transforms. They bridge uploaded files and text inputs with transform execution.</p>

<div class="callout">
    <strong>Workflow:</strong> User uploads a file (e.g., wordlist) → runs a transform that needs a generator → modal asks which generator + file → generator reads the file → string output is passed to the transform as <code>generator_output</code>.
</div>

<h2>Generator SDK</h2>
<pre>from osint_engine.generator_sdk import generator, GeneratorInputs

@generator(
    name="seclists",
    display_name="SecLists file reader",
    description="Reads uploaded wordlist files",
    category="wordlists",
    input_types=["file"],
    timeout=15,
)
def run(inputs: GeneratorInputs) -> str:
    return inputs.read_files()</pre>

<h2>&#64;generator Decorator</h2>
<table>
    <thead><tr><th>Parameter</th><th>Type</th><th>Default</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td><code>name</code></td><td>str</td><td>required</td><td>Unique identifier (e.g., <code>seclists</code>)</td></tr>
        <tr><td><code>display_name</code></td><td>str</td><td>required</td><td>Human-readable name</td></tr>
        <tr><td><code>description</code></td><td>str</td><td><code>""</code></td><td>Tooltip description</td></tr>
        <tr><td><code>category</code></td><td>str</td><td><code>"general"</code></td><td>Grouping in the UI</td></tr>
        <tr><td><code>input_types</code></td><td>list[str]</td><td><code>["text"]</code></td><td>What the generator accepts: <code>["file"]</code>, <code>["text"]</code>, or <code>["file", "text"]</code></td></tr>
        <tr><td><code>timeout</code></td><td>int</td><td><code>30</code></td><td>Max seconds</td></tr>
        <tr><td><code>author</code></td><td>str</td><td><code>""</code></td><td>Your name</td></tr>
    </tbody>
</table>

<h2>GeneratorInputs</h2>
<p>The object your function receives:</p>
<pre>@dataclass
class GeneratorInputs:
    text: str | None = None     # text input from the user
    files: list[str] = []       # absolute paths to uploaded files

    def read_files(encoding="utf-8") -> str:
        """Read and concatenate all input files."""

    def read_file(index=0, encoding="utf-8") -> str:
        """Read a single file by index."""</pre>

<h2>Return value</h2>
<p>Always return a <strong>string</strong>. If your function returns <code>None</code>, it becomes an empty string. Non-string returns are coerced via <code>str()</code>.</p>

<h2>input_types</h2>
<table>
    <thead><tr><th>Value</th><th>UI behavior</th></tr></thead>
    <tbody>
        <tr><td><code>["file"]</code></td><td>Shows file picker (from File Manager uploads)</td></tr>
        <tr><td><code>["text"]</code></td><td>Shows text area</td></tr>
        <tr><td><code>["file", "text"]</code></td><td>Shows both — file picker + text area</td></tr>
    </tbody>
</table>

<h2>Connecting to transforms</h2>
<p>Transforms declare they accept generators via:</p>
<pre>@transform(
    name="web.feroxbuster",
    accepts_generator=True,
    required_generators=["seclists", "custom_wordlist"],
    requires_slave=True,
    ...
)
def run(node, api_keys, slave, generator_output=None):
    if not generator_output:
        return [Node(type="note", value="no wordlist")]
    # generator_output is the string returned by the selected generator
    ...</pre>

<table>
    <thead><tr><th>Transform decorator param</th><th>Effect</th></tr></thead>
    <tbody>
        <tr><td><code>accepts_generator=True</code></td><td>UI shows generator modal before running. <code>generator_output</code> kwarg is passed to the function.</td></tr>
        <tr><td><code>required_generators=["seclists"]</code></td><td>Modal only shows these generators in the dropdown (not all).</td></tr>
    </tbody>
</table>

<h2>File management</h2>
<p>Files are uploaded via <code>/files</code> (File Manager). They're stored in <code>storage/app/uploads/</code> and accessible to the engine via shared filesystem path.</p>

<h2>Built-in generators</h2>
<table>
    <thead><tr><th>Name</th><th>Input</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td><code>seclists</code></td><td>file</td><td>Reads wordlist files and returns content as string</td></tr>
        <tr><td><code>custom_wordlist</code></td><td>text</td><td>Pass-through: returns text input as-is</td></tr>
        <tr><td><code>ip_ranges</code></td><td>file + text</td><td>Expands CIDR ranges to individual IPs (max 10k)</td></tr>
        <tr><td><code>subdomain_list</code></td><td>file + text</td><td>Combines prefix file with base domain from text</td></tr>
    </tbody>
</table>

<h2>Creating from UI</h2>
<p>Go to <code>/generators</code> → <strong>+ NEW</strong>. Same pattern as transforms — CodeMirror editor, auto-reload on save.</p>
@endsection
