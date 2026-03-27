<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceListController;
use App\Http\Controllers\RequestController;
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
| Auth only (verified じゃなくてOK)
|--------------------------------------------------------------------------
| ※ verification.notice は未認証ユーザーも見る必要があるので verified は付けない
*/
Route::middleware('auth')->group(function () {
    // 認証誘導画面を差し替え
    Route::get('/email/verify', function () {
        // 認証完了後にプロフィールへ遷移させるため、意図的に intended を上書き
        session(['url.intended' => route('attendance.index')]);

        return view('auth.verify-email');
    })->name('verification.notice');
});

/*
|--------------------------------------------------------------------------
| 一般ユーザー：認証必須
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    // 勤怠登録画面
    Route::get('/attendance', [AttendanceController::class, 'index'])
        ->name('attendance.index');

    // 打刻処理
    Route::post('/attendance', [AttendanceController::class, 'store'])
        ->name('attendance.store');

    // 勤怠一覧
    Route::get('/attendance/list', [AttendanceListController::class, 'index'])
        ->name('attendance.list');

    // 申請一覧
    Route::get('/requests', [RequestController::class, 'index'])
        ->name('requests.index');
});
