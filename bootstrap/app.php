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
        $middleware->web(append: [
            \App\Http\Middleware\MaintenanceModeMiddleware::class,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'prevent.staff' => \App\Http\Middleware\PreventStaffAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Database\QueryException $e, \Illuminate\Http\Request $request) {
            $isConnectionError = in_array($e->getCode(), [2002, 1045, '2002', 'HY000'])
                || str_contains($e->getMessage(), '2002')
                || str_contains($e->getMessage(), 'Operation not permitted')
                || str_contains($e->getMessage(), 'Connection refused')
                || str_contains($e->getMessage(), 'No such file or directory');

            if ($isConnectionError) {
                \Illuminate\Support\Facades\Log::critical('Database connection failure: ' . $e->getMessage());

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The database service is temporarily unavailable. Please try again shortly.',
                    ], 503);
                }

                return response()->view('errors.db', [], 503);
            }

            return null;
        });
    })->create();
