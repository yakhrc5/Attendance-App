<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        // 管理者は管理画面へ遷移させる
        if ($user->role === 'admin') {
            return redirect()->route('admin.attendance.list');
        }

        // 一般ユーザーは勤怠打刻画面へ遷移させる
        return redirect()->route('attendance.index');
    }
}
