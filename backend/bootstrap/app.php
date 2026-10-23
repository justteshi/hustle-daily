<?php

use App\Http\Middleware\RequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->appendToGroup('web', AuthenticateSession::class);
        $middleware->append(RequestId::class);
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : route('filament.admin.auth.login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $status = match (true) {
                $e instanceof AuthenticationException => 401,
                $e instanceof AuthorizationException => 403,
                $e instanceof ValidationException => 422,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };
            $message = $status === 500 ? __('api.server_error') : ($e->getMessage() ?: __('api.request_failed'));

            return response()->json(['error' => [
                'code' => match ($status) {
                    401 => 'unauthenticated', 403 => 'forbidden', 404 => 'not_found', 422 => 'validation_failed', default => 'request_failed'
                },
                'message' => $message,
                'details' => $e instanceof ValidationException ? $e->errors() : null,
                'request_id' => $request->attributes->get('request_id'),
            ]], $status);
        });
    })->create();
