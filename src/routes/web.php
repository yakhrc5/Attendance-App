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
    // 未ログインの場合は一般ユーザーのログイン画面へ遷移する
    if (!auth()->check()) {
        return redirect()->route('login');
    }

    // ログイン済みなら role に応じて遷移先を分ける
    /** @var \App\Models\User $user */
    $user = auth()->user();

    // 管理者は管理者用の勤怠一覧へ遷移する
    if ($user->isAdmin()) {
        return redirect()->route('admin.attendance.list');
    }

    // 一般ユーザーは勤怠打刻画面へ遷移する
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

    // 出勤打刻
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])
        ->name('attendance.clock-in');

    // 退勤打刻
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])
        ->name('attendance.clock-out');

    // 休憩開始打刻
    Route::post('/attendance/break-start', [AttendanceController::class, 'breakStart'])
        ->name('attendance.break-start');

    // 休憩終了打刻
    Route::post('/attendance/break-end', [AttendanceController::class, 'breakEnd'])
        ->name('attendance.break-end');

    // 勤怠一覧
    Route::get('/attendance/list', [AttendanceListController::class, 'index'])
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
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // メール未認証ユーザーだけは管理者ログイン画面を開けるようにする
        if (
            $user instanceof MustVerifyEmail &&
            !$user->hasVerifiedEmail()
        ) {
            return view('admin.auth.login');
        }

        return $user->isAdmin()
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
    // 当日の勤怠一覧画面
    Route::get('/attendance/list', [AdminAttendanceListController::class, 'index'])
        ->name('attendance.list');

    // 勤怠詳細画面
    Route::get('/attendance/{id}', [AdminAttendanceDetailController::class, 'show'])
        ->name('attendance.detail');

    // 勤怠修正
    Route::patch('/attendance/{id}', [AdminAttendanceDetailController::class, 'update'])
        ->name('attendance.update');

    // スタッフ一覧画面
    Route::get('/staff/list', [AdminStaffListController::class, 'index'])
        ->name('staff.list');

    // スタッフ別勤怠一覧画面
    Route::get('/attendance/staff/{id}', [AdminStaffAttendanceListController::class, 'index'])
        ->name('attendance.staff');

    // スタッフ別勤怠一覧CSV出力
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
    // 修正申請承認画面
    Route::get('/stamp_correction_request/approve/{id}', [StampCorrectionRequestApproveController::class, 'show'])
        ->name('admin.stamp_correction_request.approve');

    // 修正申請承認処理
    Route::patch('/stamp_correction_request/approve/{id}', [StampCorrectionRequestApproveController::class, 'update'])
        ->name('admin.stamp_correction_request.approve.update');
});