<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Оценщик">
    <title>@yield('title', 'Дашборд') — {{ config('services.lombard.name', config('app.name')) }}</title>
    <link rel="manifest" href="{{ asset('manifest-appraiser.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/pwa-icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Единая палитра лендинга: синий #224d66, акцент золотой #f9ba22 */
        :root {
            --lombard-primary: #224d66;
            --lombard-primary-hover: #2a5e7d;
            --lombard-accent: #f9ba22;
            --lombard-accent-hover: #fdd880;
        }
        body { font-family: Montserrat, sans-serif; }
        /* Сайдбар в стиле лендинга: шире, чтобы названия разделов в один ряд + иконки */
        .app-sidebar {
            width: 280px;
            min-width: 280px;
            min-height: 100vh;
            background-color: var(--lombard-primary);
        }
        .app-sidebar .navbar-brand {
            color: #fff;
            font-weight: 700;
            font-size: 1.15rem;
        }
        .app-sidebar .navbar-brand:hover { color: var(--lombard-accent-hover); }
        .app-sidebar .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            transition: color 0.2s, background 0.2s;
            white-space: nowrap;
        }
        .app-sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .app-sidebar .nav-link i { opacity: 0.9; margin-right: 0.5rem; flex-shrink: 0; }
        .app-sidebar .nav-group-toggle {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(255,255,255,0.6);
            padding: 0.6rem 0.75rem;
            margin-top: 0.5rem;
            width: 100%;
            text-align: left;
            border: none;
            background: transparent;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: color 0.2s, background 0.2s;
            white-space: nowrap;
        }
        .app-sidebar .nav-group-toggle:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .app-sidebar .nav-group-toggle:first-of-type { margin-top: 0; }
        .app-sidebar .nav-group-toggle .nav-group-toggle-icon { margin-right: 0.5rem; opacity: 0.85; flex-shrink: 0; }
        .app-sidebar .nav-group-toggle .bi-chevron-down { transition: transform 0.2s; flex-shrink: 0; }
        .app-sidebar .nav-group-toggle[aria-expanded="false"] .bi-chevron-down { transform: rotate(-90deg); }
        .app-sidebar .nav-group-items { padding-left: 0; list-style: none; }
        .app-sidebar .nav-group-items .nav-item { margin-bottom: 0; }
        .app-sidebar hr { border-color: rgba(255,255,255,0.2); }
        .app-sidebar .btn-outline-light {
            border-color: rgba(255,255,255,0.5);
            color: #fff;
        }
        .app-sidebar .btn-outline-light:hover {
            background: var(--lombard-accent);
            border-color: var(--lombard-accent);
            color: #fff;
        }
        /* Контент: светлый фон как на портале входа */
        .app-main { background: #f8f9fa; }
        /* Кнопка primary = золотой акцент (как на лендинге) */
        .app-main .btn-primary {
            background: linear-gradient(0deg, var(--lombard-accent), var(--lombard-accent));
            border: none;
            color: #fff;
            font-weight: 600;
        }
        .app-main .btn-primary:hover {
            background: linear-gradient(160deg, var(--lombard-accent-hover) 14%, #fbb815 86%);
            color: #fff;
            border: none;
        }
        .app-main .btn-outline-primary {
            border-color: var(--lombard-primary);
            color: var(--lombard-primary);
        }
        .app-main .btn-outline-primary:hover {
            background: rgba(34, 77, 102, 0.08);
            border-color: var(--lombard-primary-hover);
            color: var(--lombard-primary-hover);
        }
        .app-main a:not(.nav-link):not(.btn) { color: var(--lombard-primary); }
        .app-main a:not(.nav-link):not(.btn):hover { color: var(--lombard-primary-hover); }
        .app-main .nav-tabs .nav-link { color: #6c757d; border-color: transparent; }
        .app-main .nav-tabs .nav-link.active {
            color: var(--lombard-primary);
            font-weight: 600;
            border-color: #dee2e6 #dee2e6 #f8f9fa;
            border-bottom: 2px solid var(--lombard-primary);
        }
        .app-main .card { border-radius: 8px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .app-main .table-hover tbody tr:hover { background: rgba(34, 77, 102, 0.04); }
        .app-main .form-control:focus, .app-main .form-select:focus {
            border-color: var(--lombard-primary);
            box-shadow: 0 0 0 0.2rem rgba(34, 77, 102, 0.15);
        }
        .app-main .pagination .page-link { color: var(--lombard-primary); }
        .app-main .pagination .page-item.active .page-link {
            background-color: var(--lombard-primary);
            border-color: var(--lombard-primary);
        }
        .app-main .alert-success { border-left: 4px solid #198754; }
        .app-main .alert-danger { border-left: 4px solid #dc3545; }

        /* ——— Мобильная верстка ——— */
        .app-mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: var(--lombard-primary);
            color: #fff;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            z-index: 1030;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .app-mobile-header .navbar-brand { color: #fff; font-size: 1.1rem; margin: 0; }
        .app-mobile-header .btn-menu {
            width: 44px;
            height: 44px;
            padding: 0;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.9);
            border-radius: 8px;
            font-size: 1.5rem;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-tap-highlight-color: transparent;
        }
        .app-mobile-header .btn-menu:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .app-sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1025;
            opacity: 0;
            transition: opacity 0.2s;
        }
        body.sidebar-open .app-sidebar-overlay { opacity: 1; }

        @media (max-width: 991.98px) {
            .app-mobile-header { display: flex; padding-left: max(1rem, env(safe-area-inset-left)); padding-right: max(1rem, env(safe-area-inset-right)); padding-top: env(safe-area-inset-top); }
            .app-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 280px;
                max-width: 85vw;
                height: 100vh;
                height: 100dvh;
                z-index: 1030;
                transform: translateX(-100%);
                transition: transform 0.25s ease-out;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                box-shadow: 4px 0 20px rgba(0,0,0,0.15);
                padding-top: env(safe-area-inset-top);
            }
            body.sidebar-open .app-sidebar { transform: translateX(0); }
            .app-sidebar-overlay { display: block; pointer-events: none; }
            body.sidebar-open .app-sidebar-overlay { pointer-events: auto; }
            .app-main {
                padding-top: calc(56px + env(safe-area-inset-top) + 1rem);
                padding-left: max(1rem, env(safe-area-inset-left));
                padding-right: max(1rem, env(safe-area-inset-right));
                padding-bottom: max(1.5rem, env(safe-area-inset-bottom));
                min-height: 100vh;
                min-height: 100dvh;
                width: 100%;
            }
            .app-main .btn { min-height: 44px; padding: 0.5rem 1rem; }
            .app-main .form-control, .app-main .form-select {
                min-height: 44px;
                font-size: 16px; /* уменьшает зум iOS при фокусе */
            }
            .app-main .nav-tabs { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 2px; }
            .app-main .nav-tabs .nav-link { white-space: nowrap; padding: 0.6rem 0.75rem; }
            .app-main .table-responsive { -webkit-overflow-scrolling: touch; }
            .app-main .table { font-size: 0.9rem; }
        }

        @media (max-width: 575.98px) {
            .app-main .row.g-3 > [class*="col-"] { margin-bottom: 0.5rem; }
            .app-main .d-flex.justify-content-between, .app-main .d-flex.justify-content-end { flex-wrap: wrap; gap: 0.5rem; }
            .app-main .btn-group .btn { min-height: 44px; }
        }

        /* Режим оценщика: без сайдбара, только шапка + контент */
        .appraiser-only-header {
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
        .appraiser-only-header .brand { color: #fff; font-weight: 700; font-size: 1.15rem; text-decoration: none; }
        .appraiser-only-header .brand:hover { color: var(--lombard-accent-hover); }
        .appraiser-only-header .btn-outline-light {
            border-color: rgba(255,255,255,0.5);
            color: #fff;
            min-height: 44px;
            padding: 0.4rem 1rem;
        }
        .appraiser-only-header .btn-outline-light:hover {
            background: var(--lombard-accent);
            border-color: var(--lombard-accent);
            color: #fff;
        }
        .appraiser-only-main {
            padding-top: calc(56px + env(safe-area-inset-top) + 1rem);
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
            padding-bottom: max(1.5rem, env(safe-area-inset-bottom));
            min-height: 100vh;
            width: 100%;
            background: #f8f9fa;
        }
        .appraiser-only-main .btn { min-height: 44px; padding: 0.5rem 1rem; }
        .appraiser-only-main .form-control, .appraiser-only-main .form-select {
            min-height: 44px;
            font-size: 16px;
        }
    </style>
    @stack('styles')
</head>
<body class="d-flex">
    @php
        $isAppraiser = $is_appraiser ?? (auth()->check() && auth()->user()->role === 'appraiser');
    @endphp
    @if($isAppraiser)
    <header class="appraiser-only-header">
        <a href="{{ route('appraiser.home') }}" class="brand">Оценщик</a>
        <form method="post" action="{{ route('logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Выход</button>
        </form>
    </header>
    <main class="appraiser-only-main">
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
    @else
    <header class="app-mobile-header">
        <button type="button" class="btn-menu" id="sidebar-toggle" aria-label="Меню"><i class="bi bi-list"></i></button>
        <a class="navbar-brand" href="{{ route('dashboard') }}">{{ config('services.lombard.name', 'Ломбард') }}</a>
        <a class="btn-menu" href="{{ route('home') }}" target="_blank" aria-label="На сайт"><i class="bi bi-box-arrow-up-right"></i></a>
    </header>
    <div class="app-sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
    <nav class="navbar navbar-dark flex-column align-items-stretch p-3 app-sidebar" id="app-sidebar">
        <a class="navbar-brand mb-3" href="{{ route('dashboard') }}">{{ config('services.lombard.name', 'Ломбард') }}</a>
        <a class="nav-link text-white-50 small mb-2" href="{{ route('home') }}" target="_blank"><i class="bi bi-box-arrow-up-right"></i> На сайт</a>
        <a class="nav-link mb-2 py-2 rounded bg-white bg-opacity-10 text-white" href="{{ route('appraiser.home') }}"><i class="bi bi-phone"></i> Оценщик</a>
        <ul class="nav flex-column">
            <li>
                <button class="nav-group-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-group-clients" aria-expanded="true" aria-controls="sidebar-group-clients"><i class="bi bi-people-fill nav-group-toggle-icon"></i><span>Работа с клиентами</span><i class="bi bi-chevron-down"></i></button>
                <ul class="nav flex-column collapse show nav-group-items" id="sidebar-group-clients">
                    <li class="nav-item"><a class="nav-link" href="{{ route('dashboard') }}"><i class="bi bi-grid"></i> Дашборд</a></li>
                    @auth
                    @if(auth()->user()->canCreateContracts())
                    <li class="nav-item"><a class="nav-link" href="{{ route('accept.create') }}"><i class="bi bi-plus-circle"></i> Приём товара</a></li>
                    @endif
                    @endauth
                    <li class="nav-item"><a class="nav-link" href="{{ route('clients.index') }}"><i class="bi bi-people"></i> Клиенты</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('items.index') }}"><i class="bi bi-box-seam"></i> Товары</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('pawn-contracts.index') }}"><i class="bi bi-file-text"></i> Договоры залога</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('commission-contracts.index') }}"><i class="bi bi-file-earmark-text"></i> Договоры комиссии</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('purchase-contracts.index') }}"><i class="bi bi-cash-coin"></i> Договоры скупки</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('call-center.index') }}"><i class="bi bi-telephone-inbound"></i> Колл-центр</a></li>
                </ul>
            </li>

            <li>
                <button class="nav-group-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-group-finance" aria-expanded="true" aria-controls="sidebar-group-finance"><i class="bi bi-wallet2 nav-group-toggle-icon"></i><span>Финансы</span><i class="bi bi-chevron-down"></i></button>
                <ul class="nav flex-column collapse show nav-group-items" id="sidebar-group-finance">
                    @if(auth()->user() && auth()->user()->canProcessSales())
                    <li class="nav-item"><a class="nav-link" href="{{ route('cash.index') }}"><i class="bi bi-cash-stack"></i> Касса</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('cash.report') }}"><i class="bi bi-bar-chart"></i> Отчёт по кассам</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('expenses.index') }}"><i class="bi bi-cash-expense"></i> Расходы</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('employees.index') }}"><i class="bi bi-currency-dollar"></i> ФОТ</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('bank-accounts.index') }}"><i class="bi bi-bank"></i> Банк</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('documents.index') }}"><i class="bi bi-files"></i> Все документы</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('chart-of-accounts.index') }}"><i class="bi bi-journal-ruled"></i> План счетов</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('document-ledger-templates.index') }}"><i class="bi bi-journal-check"></i> Шаблоны проводок</a></li>
                    @endif
                </ul>
            </li>

            <li>
                <button class="nav-group-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-group-settings" aria-expanded="true" aria-controls="sidebar-group-settings"><i class="bi bi-gear nav-group-toggle-icon"></i><span>Настройки</span><i class="bi bi-chevron-down"></i></button>
                <ul class="nav flex-column collapse show nav-group-items" id="sidebar-group-settings">
                    <li class="nav-item"><a class="nav-link" href="{{ route('item-categories.index') }}"><i class="bi bi-tags"></i> Категории</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('brands.index') }}"><i class="bi bi-award"></i> Бренды</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('item-statuses.index') }}"><i class="bi bi-flag"></i> Статусы товара</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('storage-locations.index') }}"><i class="bi bi-geo-alt"></i> Места хранения</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('kb.index') }}"><i class="bi bi-journal-bookmark"></i> База знаний</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('profile.show') }}"><i class="bi bi-person"></i> Профиль</a></li>
                    @auth
                    @if(auth()->user()->isSuperAdmin())
                    <li class="nav-item"><a class="nav-link" href="{{ route('stores.index') }}"><i class="bi bi-shop"></i> Магазины</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('users.index') }}"><i class="bi bi-person-gear"></i> Пользователи</a></li>
                    @endif
                    @endauth
                </ul>
            </li>
        </ul>
        <hr class="my-3">
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-light btn-sm w-100"><i class="bi bi-box-arrow-right"></i> Выход</button>
        </form>
    </nav>
    <main class="flex-grow-1 p-4 app-main">
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
    @endif
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      (function () {
        var STORAGE_KEY = 'sidebar-groups';
        var ids = ['sidebar-group-clients', 'sidebar-group-finance', 'sidebar-group-settings'];
        try {
          var saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
          ids.forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            if (saved[id] === false) {
              el.classList.remove('show');
              var btn = document.querySelector('[data-bs-target="#' + id + '"]');
              if (btn) btn.setAttribute('aria-expanded', 'false');
            }
          });
        } catch (e) {}
        ids.forEach(function (id) {
          var el = document.getElementById(id);
          if (!el) return;
          el.addEventListener('show.bs.collapse', function () { save(id, true); });
          el.addEventListener('hide.bs.collapse', function () { save(id, false); });
        });
        function save(id, open) {
          try {
            var o = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            o[id] = open;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(o));
          } catch (e) {}
        }
      })();
      (function () {
        var btn = document.getElementById('sidebar-toggle');
        var overlay = document.getElementById('sidebar-overlay');
        function open() {
          document.body.classList.add('sidebar-open');
          if (overlay) overlay.setAttribute('aria-hidden', 'false');
        }
        function close() {
          document.body.classList.remove('sidebar-open');
          if (overlay) overlay.setAttribute('aria-hidden', 'true');
        }
        function toggle() {
          document.body.classList.toggle('sidebar-open');
          if (overlay) overlay.setAttribute('aria-hidden', document.body.classList.contains('sidebar-open') ? 'false' : 'true');
        }
        if (btn) btn.addEventListener('click', toggle);
        if (overlay) overlay.addEventListener('click', close);
        var sidebar = document.getElementById('app-sidebar');
        if (sidebar) sidebar.querySelectorAll('.nav-link').forEach(function (a) {
          a.addEventListener('click', close);
        });
      })();
    </script>
    @stack('scripts')
</body>
</html>
