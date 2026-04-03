<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceListController;
use App\Http\Controllers\AttendanceDetailController;
use App\Http\Controllers\AttendanceRequestController;
use App\Http\Controllers\StampCorrectionRequestListController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| 一般ユーザー側のルーティング
| 認証（login / register / logout）は Fortify 側に任せる
|
*/

/*
|--------------------------------------------------------------------------
| トップページ
|--------------------------------------------------------------------------
|
| 未ログイン時はログイン画面へ
| ログイン済みなら勤怠登録画面へ
|
*/

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('attendance.index');
    }

    return redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| 認証済み前でも表示が必要なルート
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', function () {
        session(['url.intended' => route('attendance.index')]);

        return view('auth.verify-email');
    })->name('verification.notice');
});

/*
|--------------------------------------------------------------------------
| 一般ユーザー：認証 + メール認証必須
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    // 勤怠登録画面
    Route::get('/attendance', [AttendanceController::class, 'index'])
        ->name('attendance.index');

    // 打刻処理
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])
        ->name('attendance.clock-in');

    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])
        ->name('attendance.clock-out');

    Route::post('/attendance/break-start', [AttendanceController::class, 'breakStart'])
        ->name('attendance.break-start');

    Route::post('/attendance/break-end', [AttendanceController::class, 'breakEnd'])
        ->name('attendance.break-end');

    // 勤怠一覧
    Route::get('/attendance/list', [AttendanceListController::class, 'show'])
        ->name('attendance.list');

    // 勤怠詳細画面
    Route::get('/attendance/detail/{id}', [AttendanceDetailController::class, 'show'])
        ->name('attendance.detail');

    // 修正申請
    Route::post('/attendance/request/{id}', [AttendanceRequestController::class, 'store'])
        ->name('attendance.request.store');

    // 申請一覧
    Route::get('/stamp_correction_request/list', [StampCorrectionRequestListController::class, 'index'])
        ->name('stamp_correction_request.list');
});