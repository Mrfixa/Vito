<?php

use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\{Authenticate,
    EncryptCookies,
    GlobalMiddleware,
    IdempotencyKey,
    Localization,
    LocalizationMiddleware,
    MaintenanceModeMiddleware,
    RedirectIfAuthenticated,
    RequestId,
    SecurityHeaders,
    VerifyCsrfToken};
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Passport\Http\Middleware\CheckScopes;
use Laravel\Passport\Http\Middleware\CheckForAnyScope;
use Modules\AdminModule\Http\Middleware\AdminMiddleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
//            TrustHosts::class,
            TrustProxies::class,
            HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
            GlobalMiddleware::class,
            SecurityHeaders::class,
        ]);
        $middleware->group('web', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
//            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            Localization::class
        ]);
        $middleware->group('api', [
//           EnsureFrontendRequestsAreStateful::class,
            'throttle:1000,1',
            SubstituteBindings::class,
            LocalizationMiddleware::class,
            RequestId::class,
        ]);
        /*
        |--------------------------------------------------------------------------
        | Route Middleware (Aliases)
        |--------------------------------------------------------------------------
        */
        $middleware->alias([
            'auth' => Authenticate::class,
            'auth.basic' => AuthenticateWithBasicAuth::class,
            'cache.headers' => SetCacheHeaders::class,
            'can' => Authorize::class,
            'guest' => RedirectIfAuthenticated::class,
            'password.confirm' => RequirePassword::class,
            'signed' => ValidateSignature::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,

            // Custom middlewares
            'admin'       => AdminMiddleware::class,
            'maintenance_mode' => MaintenanceModeMiddleware::class,
            'scope'       => CheckScopes::class,
            'scopes'      => CheckForAnyScope::class,
            'idempotent'  => IdempotencyKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Structured error logging with request context on every uncaught exception.
        $exceptions->report(function (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), [
                'exception'  => get_class($e),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'user_id'    => optional(request()->user())->id,
                'url'        => request()->fullUrl(),
                'request_id' => request()->header('X-Request-Id'),
            ]);
            if (app()->bound('sentry') && config('sentry.dsn')) {
                \Sentry\captureException($e);
            }
        });

        // RFC 7807 additive fields on 404s — existing keys remain for Flutter client compat.
        $exceptions->renderable(function (
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->wantsJson()) {
                return response()->json(array_merge(
                    responseFormatter(DEFAULT_404),
                    [
                        'type'   => '/errors/not-found',
                        'title'  => 'Resource not found',
                        'status' => 404,
                        'detail' => $e->getMessage() ?: 'The requested resource does not exist.',
                    ]
                ), 404);
            }
        });

        // RFC 7807 on other HTTP exceptions.
        $exceptions->renderable(function (
            \Symfony\Component\HttpKernel\Exception\HttpException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->wantsJson()) {
                $status = $e->getStatusCode();
                return response()->json(array_merge(
                    [
                        'response_code' => $status,
                        'message'       => $e->getMessage(),
                        'content'       => null,
                        'errors'        => [],
                    ],
                    [
                        'type'   => '/errors/http-' . $status,
                        'title'  => $e->getMessage() ?: 'HTTP Error',
                        'status' => $status,
                        'detail' => $e->getMessage(),
                    ]
                ), $status);
            }
        });
    })
    ->create();

return $app;
