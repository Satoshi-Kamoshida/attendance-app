<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->admin_status, 403);

        $users = User::where('admin_status', false)
            ->orderBy('id')
            ->get();

        return view('admin.staff-list', compact('users'));
    }
}
