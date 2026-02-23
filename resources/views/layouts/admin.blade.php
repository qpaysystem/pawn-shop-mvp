<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Админ') — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    @stack('styles')
</head>
<body class="d-flex">
    <nav class="navbar navbar-dark bg-dark flex-column align-items-stretch p-3" style="width: 220px; min-height: 100vh;">
        <a class="navbar-brand mb-4" href="{{ route('admin.dashboard') }}">CRM</a>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link text-white" href="{{ route('admin.dashboard') }}"><i class="bi bi-grid"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="{{ route('admin.clients.index') }}"><i class="bi bi-people"></i> Клиенты</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="{{ route('admin.transactions.index') }}"><i class="bi bi-journal-text"></i> Транзакции</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="{{ route('admin.tasks.index') }}"><i class="bi bi-check2-square"></i> Задачи</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="{{ route('admin.products.index') }}"><i class="bi bi-box-seam"></i> ТМЦ</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="{{ route('admin.projects.index') }}"><i class="bi bi-folder2-open"></i> Проекты</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="{{ route('admin.custom-fields.index') }}"><i class="bi bi-list-ul"></i> Поля клиентов</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="{{ route('admin.users.index') }}"><i class="bi bi-person-gear"></i> Пользователи</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="{{ route('admin.activity') }}"><i class="bi bi-activity"></i> Активность</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="{{ route('admin.settings.index') }}"><i class="bi bi-gear"></i> Настройки</a></li>
        </ul>
        <hr class="text-secondary">
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-light btn-sm w-100"><i class="bi bi-box-arrow-right"></i> Выход</button>
        </form>
    </nav>
    <main class="flex-grow-1 p-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
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
