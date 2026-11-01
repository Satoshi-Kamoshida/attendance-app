
<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionRequestController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::view('/admin/login', 'admin.admin-login');

Route::post('/admin/login', [AuthenticatedSessionController::class, 'store']);

Route::post('/admin/logout', function (Request $request) {
    Auth::guard('admin')->logout();
    $request->session()->regenerate();
    $request->session()->regenerateToken();

    return redirect('/admin/login');
})->middleware('auth:admin');

Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->regenerate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth:web')->name('logout');

Route::middleware(['auth', 'verified', 'user'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'create']);
    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::get('/attendance/list', [AttendanceController::class, 'index']);

    Route::get(
        '/application/{id}',
        [AttendanceCorrectionRequestController::class, 'userShowApplication']
    );
    Route::get('/attendance/report', [AttendanceController::class, 'report']);
});

Route::middleware(['auth:admin', 'admin'])->group(function () {
    Route::get('/admin/attendance/list', [AttendanceController::class, 'adminIndex']);
    Route::get('/admin/staff/list', [UserController::class, 'index']);
    Route::get('/admin/attendance/staff/{id}', [AttendanceController::class, 'staffAttendance']);
    Route::get('/stamp_correction_request/approve/{id}', [AttendanceCorrectionRequestController::class, 'show']);
    Route::post('/stamp_correction_request/approve/{id}', [AttendanceCorrectionRequestController::class, 'update']);
    Route::get('/admin/attendance/{id}', [AttendanceController::class, 'show'])->whereNumber('id');
    Route::post('/export', [AttendanceController::class, 'export']);
});

Route::middleware('auth:admin,web')->group(function () {
    Route::get('/stamp_correction_request/list', [AttendanceCorrectionRequestController::class, 'index']);
    Route::post('/attendance/{id}', [AttendanceCorrectionRequestController::class, 'store'])->whereNumber('id');
    Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->whereNumber('id');
});
