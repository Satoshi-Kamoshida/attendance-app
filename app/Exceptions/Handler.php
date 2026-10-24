<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($request->is('api/*') && $e instanceof ModelNotFoundException) {
            return response()->json(['error' => '勤怠情報が見つかりませんでした。'], 404);
        }

        if ($request->is('api/*') && $e instanceof AuthorizationException) {
            return response()->json(['error' => 'この操作を実行する権限がありません。'], 403);
        }

        return parent::render($request, $e);
    }
}
