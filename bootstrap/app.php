<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
        
        $middleware->redirectGuestsTo(fn (Request $request) => 
            $request->is('api/*') ? null : route('login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                if ($e instanceof AuthenticationException) {
                    return response()->json(['message' => 'Acceso denegado. Token inválido o ausente.'], 401);
                }
                
                if ($e instanceof AccessDeniedHttpException) {
                    return response()->json(['message' => 'No tienes permisos para realizar esta acción.'], 403);
                }

                if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
                    return response()->json(['message' => 'El recurso que buscas no existe o fue eliminado.'], 404);
                }

                if ($e instanceof ValidationException) {
                    return response()->json([
                        'message' => 'Los datos enviados son inválidos.',
                        'errors' => $e->errors()
                    ], 422);
                }

                return response()->json([
                    'message' => 'Ocurrió un error inesperado en el servidor.',
                    'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
                ], 500);
            }
        });
    })->create();