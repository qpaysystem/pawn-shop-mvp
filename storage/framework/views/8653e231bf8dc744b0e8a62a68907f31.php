<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $__env->yieldContent('title', 'Главная'); ?> — <?php echo e(config('services.lombard.name', 'Капитал')); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Точная копия стилей 5.128.186.3 (Капитал) */
        *, *::after, *::before { box-sizing: border-box; }
        html {
            -webkit-text-size-adjust: 100%;
            font-family: Montserrat, sans-serif;
            line-height: 1.15;
            scroll-behavior: smooth;
        }
        body {
            margin: 0;
            background-color: #fff;
            color: #212529;
            font-family: Montserrat, sans-serif;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            text-align: left;
        }
        a:hover { text-decoration: none; }

        /* Контейнер как на оригинале: max-width 1280px */
        .container-capital {
            margin: 0 auto;
            max-width: 1280px;
            padding-left: 15px;
            padding-right: 15px;
            width: 100%;
        }

        /* Основной синий фон (#224d66) */
        .purple_bg {
            background-color: #224d66;
            min-height: calc(100vh - 252px);
            color: #fff;
        }

        /* Кнопка золотая — как .btn на 5.128.186.3 */
        .btn {
            background: linear-gradient(0deg, #f9ba22, #f9ba22);
            border: none;
            border-radius: 8px;
            color: #fff;
            display: inline-block;
            font-size: 16px;
            font-weight: 700;
            outline: none;
            padding: 12px 34px;
            text-align: center;
            transition: all 0.3s ease-in-out;
            text-decoration: none;
        }
        .btn:hover {
            background: linear-gradient(160deg, #fdd880 14%, #fbb815 86%);
            color: #fff;
        }
        .btn:focus { outline: none; }
        @media screen and (max-width: 1024px) { .btn { font-size: 14px; } }

        /* Синяя кнопка */
        .btn_blue {
            background: #224d66;
            color: #fff;
        }
        .btn_blue:hover {
            background: #2a5e7d;
            color: #fff;
        }

        /* Шапка — синяя */
        .header-capital {
            background-color: #224d66;
            padding: 16px 0;
        }
        .header-capital .navbar-brand {
            color: #fff;
            font-weight: 700;
            font-size: 1.25rem;
            text-decoration: none;
        }
        .header-capital .nav-link {
            color: #fff;
            font-size: 16px;
            font-weight: 500;
            padding: 0.5rem 1rem;
        }
        .header-capital .nav-link:hover { color: rgba(255,255,255,0.9); }
        .navbar-toggler { border-color: rgba(255,255,255,.5); }
        .navbar-toggler:focus { box-shadow: 0 0 0 2px #f9ba22; }

        /* Футер — синий */
        .footer-capital {
            background-color: #224d66;
            color: #fff;
            padding: 40px 0;
            font-size: 14px;
        }
        .footer-capital a { color: #fff; }
        .footer-capital a:hover { color: #fdd880; }

        /* Ссылки-подчёркивание как .link-base */
        .link-base { cursor: pointer; }
        .link-base span { border-bottom: 1px solid transparent; transition: all 0.3s ease-in-out; }
        .link-base span, .link-base:hover span { color: #224d66; }
        .link-base:hover span { border-bottom: 1px solid #224d66; }

        /* Списки как на оригинале */
        ul.list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            list-style: none;
            padding: 0;
        }
        ul.list li {
            font-size: 16px;
            font-weight: 500;
            line-height: 18px;
            padding-left: 25px;
            position: relative;
        }
        ul.list li::after {
            background: #fff;
            border-radius: 50%;
            content: "";
            height: 3px;
            left: 12px;
            position: absolute;
            top: 9px;
            width: 3px;
        }
        @media (max-width: 1024px) { ul.list li { font-size: 14px; line-height: 16px; } }

        /* Хлебные крошки как на 5.128.186.3 */
        .breadcrumb-capital { margin-bottom: 29px; padding-left: 0; list-style: none; display: flex; align-items: center; flex-wrap: wrap; gap: 6px; font-size: 11px; font-weight: 400; color: #fff; }
        .breadcrumb-capital a { color: #fff; text-decoration: none; display: inline-flex; align-items: center; }
        .breadcrumb-capital a:hover { color: #fdd880; }
        .breadcrumb-capital .sep { margin: 0 2px; opacity: 0.8; }
    </style>
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body>
    <header class="header-capital">
        <nav class="navbar navbar-expand-lg navbar-dark py-0">
            <div class="container-capital d-flex flex-wrap align-items-center justify-content-between w-100">
                <a class="navbar-brand" href="<?php echo e(route('home')); ?>"><?php echo e(config('services.lombard.name', 'Капитал')); ?></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCapital" aria-controls="navbarCapital" aria-expanded="false" aria-label="Меню">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCapital">
                    <ul class="navbar-nav ms-auto align-items-lg-center">
                        <li class="nav-item"><a class="nav-link" href="<?php echo e(url('/')); ?>">Главная</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo e(route('landing.lombard')); ?>">Ломбард</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo e(route('landing.buy')); ?>">Покупка</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo e(route('landing.about')); ?>">О компании</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo e(route('landing.contacts')); ?>">Контакты</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo e(route('landing.catalog')); ?>">Каталог</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo e(route('kb.index')); ?>">База знаний</a></li>
                        <li class="nav-item ms-lg-3">
                            <a class="btn" href="<?php echo e(route('login')); ?>">Вход для сотрудников</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main class="purple_bg">
        <?php echo $__env->yieldContent('content'); ?>
    </main>
    <footer class="footer-capital">
        <div class="container-capital">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <strong><?php echo e(config('services.lombard.name', 'Капитал')); ?></strong>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', config('services.lombard.phone'))); ?>"><?php echo e(config('services.lombard.phone')); ?></a>
                    <span class="mx-2">|</span>
                    <a class="btn btn_blue" href="<?php echo e(route('login')); ?>" style="padding: 8px 20px; font-size: 14px;">Вход для сотрудников</a>
                </div>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/landing/lombard/layout.blade.php ENDPATH**/ ?>