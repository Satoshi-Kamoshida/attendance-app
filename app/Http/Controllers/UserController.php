<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

class UserController extends Controller
{
    /**
     * 管理者向けのスタッフ一覧を表示する。
     */
    public function index(): View
    {
        abort_unless(auth()->user()->admin_status, 403);

        $users = User::where('admin_status', false)
            ->orderBy('id')
            ->get();

        return view('admin.staff-list', compact('users'));
    }
}
