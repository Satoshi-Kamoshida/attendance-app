
<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionRequestController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::view('/admin/login', 'admin.admin-login');
Route::view('/admin/login', 'admin.admin-login');

Route::post(
    '/admin/login',
    [AuthenticatedSessionController::class, 'store']
);

Route::post('/admin/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/admin/login');
});

Route::post('/register', [RegisteredUserController::class, 'store']);

Route::middleware(['auth', 'user'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'create']);
    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::get('/attendance/list', [AttendanceController::class, 'index']);

    Route::get(
        '/application/{id}',
        [AttendanceCorrectionRequestController::class, 'userShowApplication']
    );
});

Route::middleware('admin')->group(function () {
    Route::get('/admin/attendance/list', [AttendanceController::class, 'adminIndex']);
    Route::get('/admin/staff/list', [UserController::class, 'index']);
    Route::get('/admin/attendance/staff/{id}', [AttendanceController::class, 'staffAttendance']);
    Route::get('/stamp_correction_request/approve/{id}', [AttendanceCorrectionRequestController::class, 'show']);
    Route::post('/stamp_correction_request/approve/{id}', [AttendanceCorrectionRequestController::class, 'update']);
    Route::get('/admin/attendance/{id}', [AttendanceController::class, 'show'])->whereNumber('id');
});

Route::middleware('auth')->group(function () {
    Route::get('/stamp_correction_request/list', [AttendanceCorrectionRequestController::class, 'index']);
    Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->whereNumber('id');
    Route::post('/attendance/{id}', [AttendanceCorrectionRequestController::class, 'store'])->whereNumber('id');
});
