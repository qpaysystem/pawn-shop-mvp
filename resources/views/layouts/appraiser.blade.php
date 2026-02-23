<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Оценщик">
    <title>@yield('title', 'Оценщик') — {{ config('services.lombard.name', config('app.name')) }}</title>
    <link rel="manifest" href="{{ asset('manifest-appraiser.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/pwa-icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --lombard-primary: #224d66;
            --lombard-primary-hover: #2a5e7d;
            --lombard-accent: #f9ba22;
            --lombard-accent-hover: #fdd880;
        }
        body { font-family: Montserrat, sans-serif; margin: 0; background: #f8f9fa; }
        .appraiser-layout-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
            padding-top: env(safe-area-inset-top);
            background: var(--lombard-primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1030;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .appraiser-layout-header .brand {
            color: #fff;
            font-weight: 700;
            font-size: 1.15rem;
            text-decoration: none;
        }
        .appraiser-layout-header .brand:hover { color: var(--lombard-accent-hover); }
        .appraiser-layout-header .btn-outline-light {
            border-color: rgba(255,255,255,0.5);
            color: #fff;
            min-height: 44px;
            padding: 0.4rem 1rem;
        }
        .appraiser-layout-header .btn-outline-light:hover {
            background: var(--lombard-accent);
            border-color: var(--lombard-accent);
            color: #fff;
        }
        .appraiser-layout-main {
            padding-top: calc(56px + env(safe-area-inset-top) + 1rem);
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
            padding-bottom: max(1.5rem, env(safe-area-inset-bottom));
            min-height: 100vh;
        }
        .appraiser-layout-main .btn-primary {
            background: var(--lombard-accent);
            border: none;
            color: #fff;
            font-weight: 600;
        }
        .appraiser-layout-main .btn-primary:hover {
            background: var(--lombard-accent-hover);
            color: #fff;
            border: none;
        }
        .appraiser-layout-main a:not(.btn) { color: var(--lombard-primary); }
        .appraiser-layout-main a:not(.btn):hover { color: var(--lombard-primary-hover); }
    </style>
    @stack('styles')
</head>
<body>
    <header class="appraiser-layout-header">
        <a href="{{ route('appraiser.home') }}" class="brand">Оценщик</a>
        <form method="post" action="{{ route('logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Выход</button>
        </form>
    </header>
    <main class="appraiser-layout-main">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @yield('content')
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
