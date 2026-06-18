<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register()
    {
        $this->reportable(function (Throwable $e) {
            Log::error($e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'user_id'   => optional(request()->user())->id,
                'url'       => request()->fullUrl(),
                'request_id' => request()->header('X-Request-Id'),
            ]);
        });

        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->wantsJson()) {
                abort(response()->json(array_merge(
                    responseFormatter(DEFAULT_404),
                    [
                        'type'   => '/errors/not-found',
                        'title'  => 'Resource not found',
                        'status' => 404,
                        'detail' => $e->getMessage() ?: 'The requested resource does not exist.',
                    ]
                ), 404));
            }
        });

        $this->renderable(function (HttpException $e, $request) {
            if ($request->wantsJson()) {
                $status = $e->getStatusCode();
                abort(response()->json(array_merge(
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
                ), $status));
            }
        });
    }
}
