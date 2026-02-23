<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход для сотрудников — <?php echo e(config('services.lombard.name', 'Ломбард')); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --portal-primary: #224d66;
            --portal-primary-dark: #1a3d52;
            --portal-accent: #f9ba22;
            --portal-accent-hover: #fdd880;
        }
        *, *::after, *::before { box-sizing: border-box; }
        html { font-family: Montserrat, sans-serif; scroll-behavior: smooth; }
        body { margin: 0; font-family: Montserrat, sans-serif; font-size: 1rem; line-height: 1.5; min-height: 100vh; }

        .portal-wrap {
            display: flex;
            min-height: 100vh;
        }
        /* Левая колонка — бренд */
        .portal-brand {
            width: 42%;
            min-height: 100vh;
            background: var(--portal-primary);
            color: #fff;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .portal-brand-logo {
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 3rem;
        }
        .portal-brand-logo a { color: #fff; text-decoration: none; }
        .portal-brand-logo a:hover { color: var(--portal-accent-hover); }
        .portal-brand h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .portal-brand p {
            opacity: 0.9;
            font-size: 1rem;
            margin-bottom: 2rem;
        }
        .portal-brand-features {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .portal-brand-features li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.9375rem;
            opacity: 0.95;
        }
        .portal-brand-features li i {
            color: var(--portal-accent);
            font-size: 1.125rem;
        }

        /* Правая колонка — форма */
        .portal-form-col {
            width: 58%;
            min-height: 100vh;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .portal-form-card {
            width: 100%;
            max-width: 400px;
        }
        .portal-form-card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 0.25rem;
        }
        .portal-form-card .subtitle {
            color: #6c757d;
            font-size: 0.9375rem;
            margin-bottom: 2rem;
        }
        .portal-form-card .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .portal-form-card .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
        }
        .portal-form-card .form-control:focus {
            border-color: var(--portal-primary);
            box-shadow: 0 0 0 3px rgba(34, 77, 102, 0.15);
        }
        .portal-form-card .btn-portal {
            background: linear-gradient(0deg, var(--portal-accent), var(--portal-accent));
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 700;
            padding: 0.75rem 1.5rem;
            width: 100%;
            transition: all 0.2s ease;
        }
        .portal-form-card .btn-portal:hover {
            background: linear-gradient(160deg, var(--portal-accent-hover) 14%, #fbb815 86%);
            color: #fff;
        }
        .portal-back {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            color: #6c757d;
            font-size: 0.875rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .portal-back:hover { color: var(--portal-primary); }
        .portal-form-col { position: relative; }

        @media (max-width: 991.98px) {
            .portal-wrap { flex-direction: column; }
            .portal-brand {
                width: 100%;
                min-height: auto;
                padding: 2rem 1.5rem;
                text-align: center;
            }
            .portal-brand-logo { margin-bottom: 1.5rem; }
            .portal-brand h1 { font-size: 1.5rem; }
            .portal-brand-features { display: none; }
            .portal-form-col { width: 100%; min-height: auto; padding: 2rem 1rem; }
        }
    </style>
</head>
<body>
    <div class="portal-wrap">
        <aside class="portal-brand">
            <div class="portal-brand-logo">
                <a href="<?php echo e(route('home')); ?>"><?php echo e(config('services.lombard.name', 'Ломбард')); ?></a>
            </div>
            <h1>Корпоративный портал</h1>
            <p>Вход в систему для сотрудников. Работа с клиентами, приём товара, договоры и отчёты.</p>
            <ul class="portal-brand-features">
                <li><i class="bi bi-shield-lock-fill"></i> Защищённый доступ</li>
                <li><i class="bi bi-people-fill"></i> Клиенты и договоры</li>
                <li><i class="bi bi-box-seam-fill"></i> Учёт товаров и залогов</li>
            </ul>
        </aside>
        <div class="portal-form-col">
            <a href="<?php echo e(route('home')); ?>" class="portal-back">
                <i class="bi bi-arrow-left"></i> На главную
            </a>
            <div class="portal-form-card">
                <h2>Вход в систему</h2>
                <p class="subtitle">Введите данные учётной записи</p>
                <div class="card">
                    <div class="card-body p-4">
                        <form method="post" action="<?php echo e(url('login')); ?>">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('email')); ?>" required autofocus placeholder="name@example.com">
                                <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Пароль</label>
                                <input type="password" name="password" class="form-control" required placeholder="••••••••">
                            </div>
                            <div class="mb-4 form-check">
                                <input type="checkbox" name="remember" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Запомнить меня</label>
                            </div>
                            <button type="submit" class="btn btn-portal">Войти</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/auth/portal.blade.php ENDPATH**/ ?>