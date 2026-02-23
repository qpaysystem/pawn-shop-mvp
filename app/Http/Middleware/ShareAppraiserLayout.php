<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareAppraiserLayout
{
    /**
     * Передаёт во все view флаг is_appraiser (роль = appraiser).
     * Layout app использует его для отображения упрощённого интерфейса без меню.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        View::share('is_appraiser', $user && $user->role === 'appraiser');

        return $next($request);
    }
}
