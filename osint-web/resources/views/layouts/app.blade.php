<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) · {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=VT323&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
    @stack('head')
</head>
<body>
<div class="app">
    <header class="navbar">
        <a href="/projects" class="brand">
            @include('partials.eye')
            <span class="brand-name">{{ config('app.name') }}</span>
        </a>
        @auth
        <nav class="nav-links">
            <a href="/projects" class="{{ request()->is('projects*') || request()->is('graphs*') ? 'active' : '' }}">Projects</a>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-btn {{ request()->is('transformations*') || request()->is('templates*') ? 'active' : '' }}">
                    Develop ▾
                </button>
                <div class="nav-dropdown-menu">
                    <a href="/transformations" class="{{ request()->is('transformations*') ? 'active' : '' }}">Transforms</a>
                    <a href="/templates" class="{{ request()->is('templates*') ? 'active' : '' }}">Templates</a>
                    <a href="/docs" target="_blank">SDK Docs ↗</a>
                </div>
            </div>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-btn {{ request()->is('api-keys*') || request()->is('slaves*') ? 'active' : '' }}">
                    Infra ▾
                </button>
                <div class="nav-dropdown-menu">
                    <a href="/api-keys" class="{{ request()->is('api-keys*') ? 'active' : '' }}">🔑 API Keys</a>
                    <a href="/slaves" class="{{ request()->is('slaves*') ? 'active' : '' }}">🖥 Slaves</a>
                </div>
            </div>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-btn {{ request()->is('users*') ? 'active' : '' }}">
                    Admin ▾
                </button>
                <div class="nav-dropdown-menu">
                    <a href="/users" class="{{ request()->is('users*') ? 'active' : '' }}">👤 Operators</a>
                </div>
            </div>
        </nav>
        <div class="nav-user">
            <span class="user-name">{{ auth()->user()->name }}</span>
            <button type="button" class="theme-toggle" title="Toggle theme">☀ LIGHT</button>
            <form method="POST" action="/logout" style="margin:0">
                @csrf
                <button type="submit" class="btn-link">LOGOUT</button>
            </form>
        </div>
        @endauth
    </header>

    <main class="@yield('main_class', 'container')">
        @if(session('status'))
            <div class="alert success">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert danger">
                <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
