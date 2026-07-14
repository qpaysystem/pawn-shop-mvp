<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Только если реальный URL приложения — https (Timeweb). Для LAN (http://lombard.home) не форсировать https.
        $appUrl = (string) config('app.url', '');
        if (config('app.env') === 'production' && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }
    }
}
