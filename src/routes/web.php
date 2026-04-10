<?php

use App\Http\Controllers\Admin\StampCorrectionRequestApproveController;
use App\Http\Controllers\Admin\StaffListController as AdminStaffListController;
use App\Http\Controllers\Admin\StaffAttendanceListController as AdminStaffAttendanceListController;
use App\Http\Controllers\Admin\AttendanceListController as AdminAttendanceListController;
use App\Http\Controllers\Admin\AttendanceDetailController as AdminAttendanceDetailController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceListController;
use App\Http\Controllers\AttendanceDetailController;
use App\Http\Controllers\AttendanceRequestController;
use App\Http\Controllers\StampCorrectionRequestListController;
use Illuminate\Support\Facades\Route;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/*
|--------------------------------------------------------------------------
| トップページ
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }

    // ログイン済みなら role に応じて遷移先を分ける
    if (auth()->user()->role === 'admin') {
        return redirect()->route('admin.attendance.list');
    }

    return redirect()->route('attendance.index');
})->name('home');

/*
|--------------------------------------------------------------------------
| 認証済み前でも表示が必要なルート
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', function () {
        // 一般ユーザーのメール認証後は勤怠打刻画面へ戻す
        session(['url.intended' => route('attendance.index')]);

        return view('auth.verify-email');
    })->name('verification.notice');
});

/*
|--------------------------------------------------------------------------
| 一般ユーザー：認証 + メール認証 + user
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'user'])->group(function () {
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
});

/*
|--------------------------------------------------------------------------
| 管理者ログイン画面
|--------------------------------------------------------------------------
*/
Route::get('/admin/login', function () {
    // ログイン済みユーザーがいる場合
    if (auth()->check()) {
        $user = auth()->user();

        // メール未認証ユーザーだけは管理者ログイン画面を開けるようにする
        if (
            $user instanceof MustVerifyEmail &&
            !$user->hasVerifiedEmail()
        ) {
            return view('admin.auth.login');
        }

        return $user->role === 'admin'
            ? redirect()->route('admin.attendance.list')
            : redirect()->route('attendance.index');
    }

    return view('admin.auth.login');
})->name('admin.login');

/*
|--------------------------------------------------------------------------
| 管理者：認証 + admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/attendance/list', [AdminAttendanceListController::class, 'index'])
        ->name('attendance.list');

    Route::get('/attendance/{id}', [AdminAttendanceDetailController::class, 'show'])
        ->name('attendance.detail');

    Route::patch('/attendance/{id}', [AdminAttendanceDetailController::class, 'update'])
        ->name('attendance.update');

    Route::get('/staff/list', [AdminStaffListController::class, 'index'])
        ->name('staff.list');

    Route::get('/attendance/staff/{id}', [AdminStaffAttendanceListController::class, 'show'])
        ->name('attendance.staff');

    // CSV出力
    Route::get('/attendance/staff/{id}/csv', [AdminStaffAttendanceListController::class, 'exportCsv'])
        ->name('attendance.staff.csv');
});

/*
|--------------------------------------------------------------------------
| 一般ユーザー、管理者両方の画面で必要なルート
|--------------------------------------------------------------------------
*/

// 申請一覧画面
Route::middleware(['auth'])->group(function () {
    Route::get('/stamp_correction_request/list', [StampCorrectionRequestListController::class, 'index'])
        ->name('stamp_correction_request.list');
});

/*
|--------------------------------------------------------------------------
| 管理者：修正申請承認画面
| - パスは要件通り /stamp_correction_request/approve/{id}
| - ただし管理者専用なので auth + admin で制御する
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/stamp_correction_request/approve/{id}', [StampCorrectionRequestApproveController::class, 'show'])
        ->name('admin.stamp_correction_request.approve');

    Route::patch('/stamp_correction_request/approve/{id}', [StampCorrectionRequestApproveController::class, 'update'])
        ->name('admin.stamp_correction_request.approve.update');
});