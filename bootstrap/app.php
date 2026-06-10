<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'role'  => \App\Http\Middleware\RoleMiddleware::class,
            'owner'          => \App\Http\Middleware\OwnerMiddleware::class,
            'owner.approved' => \App\Http\Middleware\OwnerApprovedMiddleware::class,
            'force.password.reset' => \App\Http\Middleware\ForcePasswordReset::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return 403 (not 404) when a non-admin authenticated user probes admin resource IDs.
        // This prevents ID enumeration via the 404 vs 403 distinction that arises because
        // SubstituteBindings resolves the model before AdminMiddleware runs.
        $exceptions->render(function (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->is('admin/*') && auth()->check() && auth()->user()?->role !== 'admin') {
                abort(403, 'Admins only');
            }
        });
    })->create();
