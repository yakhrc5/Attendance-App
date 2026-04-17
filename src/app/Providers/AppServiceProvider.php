<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('*', function ($view): void {
            // 認証系画面かどうか
            $isAuthHeader = request()->routeIs(
                'login',
                'register',
                'verification.notice',
                'verification.verify',
                'admin.login'
            );

            // ログインユーザーを取得する
            $user = Auth::user();

            // User モデルのときだけ管理者判定を行う
            $isAdminUser = $user instanceof User && $user->isAdmin();

            // ヘッダーモードの初期値
            $headerMode = $view->getData()['headerMode'] ?? 'default';

            // 一般ユーザーで今日退勤済みなら退勤後ヘッダーにする
            if (
                !array_key_exists('headerMode', $view->getData())
                && $user instanceof User
                && !$isAdminUser
            ) {
                $hasClockedOutToday = Attendance::query()
                    ->where('user_id', $user->id)
                    ->whereDate('work_date', today())
                    ->whereNotNull('clock_out_at')
                    ->exists();

                if ($hasClockedOutToday) {
                    $headerMode = 'after_clock_out';
                }
            }

            $view->with([
                'isAuthHeader' => $isAuthHeader,
                'isAdminUser' => $isAdminUser,
                'headerMode' => $headerMode,
            ]);
        });
    }
}
