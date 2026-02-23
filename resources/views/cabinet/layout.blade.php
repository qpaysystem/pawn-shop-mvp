<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Личный кабинет') — {{ config('app.name') }}</title>
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    @if(config('services.vapid.public'))
    <meta name="vapid-public-key" content="{{ config('services.vapid.public') }}">
    @endif
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/pwa-icon-192.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="{{ session('client_id') ? route('cabinet.dashboard') : route('home') }}">Личный кабинет</a>
            @if(session('client_id'))
            <div class="navbar-nav ms-auto">
                <form method="post" action="{{ route('cabinet.logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Выход</button>
                </form>
            </div>
            @endif
        </div>
    </nav>
    @if(session('client_id'))
    <div class="container">
        <ul class="nav nav-tabs nav-fill border-bottom bg-white mb-0">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cabinet.dashboard') ? 'active' : '' }}" href="{{ route('cabinet.dashboard') }}"><i class="bi bi-house-door me-1"></i> Главная</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cabinet.transactions') ? 'active' : '' }}" href="{{ route('cabinet.transactions') }}"><i class="bi bi-wallet2 me-1"></i> Транзакции</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cabinet.board') ? 'active' : '' }}" href="{{ route('cabinet.board') }}"><i class="bi bi-kanban me-1"></i> Канбан-доска</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cabinet.projects.*') ? 'active' : '' }}" href="{{ route('cabinet.projects.index') }}"><i class="bi bi-folder2-open me-1"></i> Проекты</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cabinet.calendar') ? 'active' : '' }}" href="{{ route('cabinet.calendar') }}"><i class="bi bi-calendar3 me-1"></i> Календарь</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cabinet.video') ? 'active' : '' }}" href="{{ route('cabinet.video') }}"><i class="bi bi-camera-video me-1"></i> Видеоконференция</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cabinet.profile') ? 'active' : '' }}" href="{{ route('cabinet.profile') }}"><i class="bi bi-person me-1"></i> Профиль</a>
            </li>
        </ul>
    </div>
    @endif
    <main class="container py-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @yield('content')
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
    @if(request()->is('cabinet*'))
    <script>
      if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
        navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '/' }).catch(function() {});
      }
    </script>
    @endif
</body>
</html>
