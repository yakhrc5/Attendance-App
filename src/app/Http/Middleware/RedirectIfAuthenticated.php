<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class RedirectIfAuthenticated
{
    /**
     * ログイン済みユーザーが guest 向け画面に来た時の制御
     *
     * - 通常のログイン済みユーザーは HOME にリダイレクトする
     * - ただし「メール未認証ユーザー」だけは、
     *   login / register 画面への再アクセスを許可する
     */
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string|null  ...$guards
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                // メール未認証ユーザーは、再ログイン・再登録のための入口だけ通す
                if (
                    $user instanceof MustVerifyEmail &&
                    ! $user->hasVerifiedEmail() &&
                    (
                        $request->is('login') ||
                        $request->is('register') ||
                        $request->is('admin/login')
                    )
                ) {
                    return $next($request);
                }

                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}