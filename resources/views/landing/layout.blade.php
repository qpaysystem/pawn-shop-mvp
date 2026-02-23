<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Главная') — Периметр Элитного Капитала</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --pec-primary: #1a365d; --pec-accent: #c9a227; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #333; -webkit-text-size-adjust: 100%; }
        .navbar-landing { background: linear-gradient(135deg, var(--pec-primary) 0%, #2c5282 100%); }
        .btn-cabinet { background: var(--pec-accent); border-color: var(--pec-accent); color: #1a365d; font-weight: 600; }
        .btn-cabinet:hover { background: #b8921f; border-color: #b8921f; color: #1a365d; }
        .section-title { color: var(--pec-primary); font-weight: 700; border-bottom: 3px solid var(--pec-accent); display: inline-block; padding-bottom: 0.25rem; }
        .card-object { border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.08); transition: transform .2s, box-shadow .2s; }
        .card-object:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,.12); }
        .card-object .img-wrap { height: 220px; overflow: hidden; background: #e9ecef; }
        .card-object .img-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .object-map { height: 200px; background: #e9ecef; border-radius: 8px; overflow: hidden; position: relative; }
        .object-map iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; display: block; }
        .project-map-wrap { border-top: 1px solid rgba(0,0,0,.08); background: #f8f9fa; }
        .project-map-iframe-wrap { position: relative; width: 100%; height: 280px; min-height: 280px; }
        .project-map-iframe-wrap iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; display: block; }
        .phone-link { font-size: 1.25rem; font-weight: 600; color: var(--pec-primary); }
        /* Мобильная версия: бургер-меню и удобные зоны нажатия */
        .navbar .navbar-toggler { border-color: rgba(255,255,255,.5); padding: 0.5rem 0.6rem; }
        .navbar .navbar-toggler:focus { box-shadow: 0 0 0 2px var(--pec-accent); }
        .navbar .navbar-collapse { margin-top: 0.5rem; }
        .navbar .nav-link { padding: 0.6rem 0.75rem; min-height: 44px; display: flex; align-items: center; }
        .navbar .btn-cabinet-wrap { padding: 0.5rem 0; }
        .navbar .btn-cabinet-wrap .btn { min-height: 44px; padding: 0.5rem 1rem; width: 100%; justify-content: center; }
        @media (min-width: 992px) {
            .navbar .btn-cabinet-wrap { padding: 0; }
            .navbar .btn-cabinet-wrap .btn { width: auto; min-height: auto; }
        }
        /* Крупнее телефон и кнопки на мобильных */
        .min-touch { min-height: 44px; padding: 0.5rem 1rem; }
        @media (max-width: 991px) {
            .phone-link { font-size: 1.35rem; }
            footer .phone-link { font-size: 1.4rem; }
            .card-object .img-wrap { height: 200px; }
            .object-map { height: 180px; }
        }
        /* Баннер между шапкой и описанием */
        .hero-banner-wrap { overflow: hidden; line-height: 0; background: #1a365d; }
        .hero-banner-img { width: 100%; height: auto; max-height: 70vh; object-fit: cover; object-position: center; display: block; }
    </style>
    @stack('styles')
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-landing py-3">
        <div class="container">
            <a class="navbar-brand fw-bold fs-5 text-white text-decoration-none" href="{{ url('/') }}">Периметр Элитного Капитала</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarLanding" aria-controls="navbarLanding" aria-expanded="false" aria-label="Открыть меню">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarLanding">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link active" href="{{ url('/') }}">Главная</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ url('/') }}#about">О компании</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ url('/') }}#objects">Объекты</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ url('/') }}#contact">Контакты</a></li>
                    <li class="nav-item btn-cabinet-wrap">
                        <a class="ms-lg-2" href="{{ route('cabinet.login') }}"><span class="btn btn-light btn-cabinet btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i> Вход в личный кабинет</span></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main>
        @yield('content')
    </main>
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p class="mb-1 fw-bold">Периметр Элитного Капитала</p>
            <p class="mb-2"><a href="tel:+73832910051" class="phone-link text-warning text-decoration-none d-inline-block py-2">+7 (383) 291-00-51</a></p>
            <p class="mb-0"><a href="{{ route('cabinet.login') }}" class="btn btn-outline-warning btn-sm min-touch">Вход в личный кабинет</a></p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
