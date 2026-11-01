<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * 管理者認証と管理者権限を確認する。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('admin')->user();

        if (! $user) {
            return redirect('/admin/login');
        }

        if (! $user->admin_status) {
            abort(403);
        }

        return $next($request);
    }
}
