<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell" data-page="@yield('page')">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark">F</span>
            <span>Force<span class="brand-light">Ops</span><small>FIELD SERVICE</small></span>
        </a>

        <div class="sidebar-label">WORKSPACE</div>
        <nav class="nav flex-column app-nav">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                <span class="nav-icon">⌂</span> Overview
            </a>
            <a id="customers-nav-link" class="nav-link {{ request()->routeIs('customers') ? 'active' : '' }}" href="{{ route('customers') }}">
                <span class="nav-icon">♙</span> Customers
            </a>
            <a id="properties-nav-link" class="nav-link {{ request()->routeIs('properties') ? 'active' : '' }}" href="{{ route('properties') }}">
                <span class="nav-icon">⌂</span> Properties
            </a>
            <a class="nav-link {{ request()->routeIs('visits') ? 'active' : '' }}" href="{{ route('visits') }}">
                <span class="nav-icon">◷</span> Visits
            </a>
            <a id="billing-nav-link" class="nav-link {{ request()->routeIs('billing') ? 'active' : '' }}" href="{{ route('billing') }}">
                <span class="nav-icon">$</span> Billing
            </a>
            <a id="team-nav-link" class="nav-link {{ request()->routeIs('team') ? 'active' : '' }}" href="{{ route('team') }}">
                <span class="nav-icon">♧</span> Team
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="connection-indicator"><span></span> Workspace connected</div>
            <button class="btn btn-quiet w-100 text-start" type="button" data-action="logout">↪ <span class="ms-2">Sign out</span></button>
        </div>
    </aside>

    <main class="main-panel">
        <header class="topbar">
            <div>
                <div class="eyebrow">@yield('eyebrow', 'WORKSPACE')</div>
                <h1>@yield('heading', 'Overview')</h1>
            </div>
            <div class="topbar-meta">
                <span class="status-dot"></span>
                <span id="user-name">Loading workspace</span>
                <div class="avatar" id="user-avatar">A</div>
            </div>
        </header>

        <div class="page-content">
            @yield('content')
        </div>
    </main>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="app-toast" class="toast app-toast" role="status" aria-live="polite">
            <div class="toast-body" id="app-toast-message"></div>
        </div>
    </div>
</body>
</html>
