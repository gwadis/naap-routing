<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust upstream reverse proxies (e.g. Coolify, Traefik, Caddy, Cloudflare)
        $middleware->trustProxies(at: '*');

        // 1. Registers your Ngrok bypass header
        $middleware->append(\App\Http\Middleware\SkipNgrokWarning::class);

        // 2. Disables CSRF for API routes so the Android app can send data
        $middleware->validateCsrfTokens(except: [
            'api/*', 
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();