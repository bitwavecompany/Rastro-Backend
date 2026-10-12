<?php

declare(strict_types=1);

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CheckSessionRevocationMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'session.active' => CheckSessionRevocationMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                $status = HttpCode::INTERNAL_ERROR;
                $message = ApiMessage::SERVER_ERROR;
                $meta = config('app.debug') ? ['debug' => $e->getMessage()] : null;

                if ($e instanceof AuthenticationException) {
                    $status = HttpCode::UNAUTHORIZED;
                    $message = ApiMessage::UNAUTHORIZED;
                    $meta = null;
                } elseif ($e instanceof AccessDeniedHttpException) {
                    $status = HttpCode::FORBIDDEN;
                    $message = ApiMessage::FORBIDDEN;
                    $meta = null;
                } elseif ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
                    $status = HttpCode::NOT_FOUND;
                    $message = ApiMessage::NOT_FOUND;
                    $meta = null;
                } elseif ($e instanceof ValidationException) {
                    $status = HttpCode::UNPROCESSABLE_ENTITY;
                    $message = ApiMessage::VALIDATION_ERROR;
                    $meta = ['errors' => $e->errors()];
                }

                $response = [
                    'success' => false,
                    'message' => $message,
                    'data' => null,
                ];

                if ($meta !== null) {
                    $response['meta'] = $meta;
                }

                return response()->json($response, $status);
            }
        });
    })->create();
