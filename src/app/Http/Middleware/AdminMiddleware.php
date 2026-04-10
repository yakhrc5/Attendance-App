<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // 未ログインなら管理者ログイン画面へ
        if (!auth()->check()) {
            return redirect()->route('admin.login');
        }

        // 一般ユーザーなら一般ユーザー側トップへ
        if (auth()->user()->role !== 'admin') {
            return redirect()->route('attendance.index');
        }

        return $next($request);
    }
}
