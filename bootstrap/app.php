<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The Host header is the visitor's to choose, and Laravel builds every
        // absolute URL it writes from it — a redirect, an asset, a password
        // reset link — so a request naming somebody else's host is answered
        // with links to that host. Only APP_URL's host and its subdomains are
        // served. Laravel applies it outside `local` and tests, and an APP_URL
        // with no host trusts every host rather than refusing them all.
        $middleware->trustHosts();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
