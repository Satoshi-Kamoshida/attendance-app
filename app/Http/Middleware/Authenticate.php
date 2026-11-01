<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * リクエストの種類に応じてリダイレクト先を決定する。
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        if ($request->routeIs('logout')) {
            return route('login');
        }

        if ($request->is('admin/*') || $request->is('export')) {
            return '/admin/login';
        }

        return route('login');
    }
}
