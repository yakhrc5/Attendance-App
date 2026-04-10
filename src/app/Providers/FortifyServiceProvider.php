<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fortify標準の LoginRequest を自作 LoginRequest に差し替える
        $this->app->singleton(
            \Laravel\Fortify\Http\Requests\LoginRequest::class,
            \App\Http\Requests\LoginRequest::class
        );

        // 会員登録後はメール認証誘導画面へ遷移させる
        $this->app->singleton(RegisterResponseContract::class, function () {
            return new class implements RegisterResponseContract {
                public function toResponse($request)
                {
                    return redirect()->route('verification.notice');
                }
            };
        });

        // ログイン後の遷移先を自作 LoginResponse で制御する
        // 一般ユーザーは勤怠打刻画面へ、管理者は管理画面へ分岐させる想定
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    public function boot(): void
    {
        // 新規ユーザー登録処理に自作 CreateNewUser を使う
        Fortify::createUsersUsing(CreateNewUser::class);

        // 一般ユーザー用の会員登録画面を表示する
        Fortify::registerView(fn() => view('auth.register'));

        // 一般ユーザー用のログイン画面を表示する
        // 管理者ログイン画面は /admin/login の独自ルート・独自Bladeで用意する
        Fortify::loginView(fn() => view('auth.login'));

        // ログイン試行回数を制限する
        // 同じメールアドレス + 同じIPごとに1分間10回までにする
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');
            $ip = (string) $request->ip();

            return Limit::perMinute(10)->by(Str::lower($email) . '|' . $ip);
        });

        // ログイン認証処理をカスタマイズする
        Fortify::authenticateUsing(function (Request $request) {
            // 入力されたメールアドレスに一致するユーザーを取得する
            $user = User::where('email', $request->email)->first();

            // ユーザーが存在しない場合は認証失敗
            if (!$user) {
                return null;
            }

            // パスワードが一致しない場合は認証失敗
            if (!Hash::check($request->password, $user->password)) {
                return null;
            }

            // ログイン画面から送られてきた role を取得する
            // 一般ユーザーログイン画面では user、管理者ログイン画面では admin を送る
            $loginRole = $request->input('role', 'user');

            // ログインしようとしている画面の role と、
            // 実際のユーザーの role が一致しなければ認証失敗にする
            if ($user->role !== $loginRole) {
                return null;
            }

            // すべて一致した場合のみ認証成功とする
            return $user;
        });
    }
}
