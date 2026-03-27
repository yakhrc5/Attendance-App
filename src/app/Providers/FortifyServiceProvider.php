<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Responses\LoginResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        // Fortify標準LoginRequestを LoginRequest に差し替え
        $this->app->singleton(
            \Laravel\Fortify\Http\Requests\LoginRequest::class,
            \App\Http\Requests\LoginRequest::class
        );

        // 登録後はメール認証誘導画面へ
        $this->app->singleton(RegisterResponseContract::class, function () {
            return new class implements RegisterResponseContract {
                public function toResponse($request)
                {
                    return redirect()->route('verification.notice');
                }
            };
        });

        // 未認証でログインしたら必ず誘導画面へ
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::registerView(fn() => view('auth.register'));
        Fortify::loginView(fn() => view('auth.login'));

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');
            $ip = (string) $request->ip();

            return Limit::perMinute(10)->by(Str::lower($email) . '|' . $ip);
        });
    }
}
