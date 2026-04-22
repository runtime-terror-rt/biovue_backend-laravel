<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckDataAccess;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        
        $middleware->statefulApi(); 

        $middleware->trustProxies(at: '*');

        // Shob webhook ebong API route ekbare exclude kora holo
        $middleware->validateCsrfTokens(except: [
            'api/v1/*', 
            'api/v1/payment/webhook',
            'stripe/webhook',
        ]);

        $middleware->alias([
            'data.access' => CheckDataAccess::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();