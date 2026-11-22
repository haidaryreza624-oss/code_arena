<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureContestant;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'contestant' => EnsureContestant::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request): string => route('admin.login'));
        $middleware->redirectUsersTo(fn (Request $request): string => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
