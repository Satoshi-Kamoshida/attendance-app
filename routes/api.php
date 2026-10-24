<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1/attendance-records')->group(function () {
    Route::get('/', [AttendanceRecordController::class, 'index']);
    Route::get('/{attendance}', [AttendanceRecordController::class, 'show'])->whereNumber('attendance');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [AttendanceRecordController::class, 'store']);
        Route::put('/{attendance}', [AttendanceRecordController::class, 'update'])->whereNumber('attendance');
        Route::patch('/{attendance}', [AttendanceRecordController::class, 'update'])->whereNumber('attendance');
        Route::delete('/{attendance}', [AttendanceRecordController::class, 'destroy'])->whereNumber('attendance');
    });
});
