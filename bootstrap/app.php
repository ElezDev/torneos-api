<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $e) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'No autenticado. Inicia sesión de nuevo.'], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.'
                        ? $e->getMessage()
                        : 'No tienes permiso para esta acción.',
                ], 403);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Recurso no encontrado.'], 404);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Recurso no encontrado.'], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $fallback = match ($e->getStatusCode()) {
                401 => 'No autenticado. Inicia sesión de nuevo.',
                403 => 'No tienes permiso para esta acción.',
                404 => 'Recurso no encontrado.',
                405 => 'Método no permitido.',
                422 => 'Los datos enviados no son válidos.',
                429 => 'Demasiadas solicitudes. Prueba de nuevo en unos segundos.',
                default => 'Ocurrió un error. Intenta de nuevo.',
            };

            $message = $e->getMessage();
            if ($message === '' || $message === 'This action is unauthorized.' || $message === 'Unauthenticated.') {
                $message = $fallback;
            }

            return response()->json(['message' => $message], $e->getStatusCode());
        });
    })->create();
