<?php

namespace App\Http\Controllers;

class DocsController extends Controller
{
    public const PAGES = [
        'getting-started' => ['title' => 'Getting Started', 'icon' => '▸'],
        'decorator' => ['title' => '@transform Decorator', 'icon' => '@'],
        'node-edge' => ['title' => 'Node & Edge', 'icon' => '◉'],
        'slave' => ['title' => 'SlaveClient (SSH)', 'icon' => '🖥'],
        'api-keys' => ['title' => 'API Keys', 'icon' => '🔑'],
        'entity-types' => ['title' => 'Entity Types', 'icon' => '◆'],
        'examples' => ['title' => 'Examples', 'icon' => '⟫'],
    ];

    public function index()
    {
        return view('docs.index', ['pages' => self::PAGES]);
    }

    public function show(string $page)
    {
        if (! array_key_exists($page, self::PAGES)) {
            abort(404);
        }
        return view("docs.{$page}", [
            'pages' => self::PAGES,
            'currentPage' => $page,
            'pageTitle' => self::PAGES[$page]['title'],
        ]);
    }

    public function searchIndex()
    {
        $index = [];
        foreach (self::PAGES as $slug => $meta) {
            $viewPath = resource_path("views/docs/{$slug}.blade.php");
            $content = file_exists($viewPath) ? file_get_contents($viewPath) : '';
            $text = strip_tags($content);
            $text = preg_replace('/\s+/', ' ', $text);
            $index[] = [
                'slug' => $slug,
                'title' => $meta['title'],
                'body' => mb_substr($text, 0, 3000),
            ];
        }
        return response()->json($index);
    }
}
