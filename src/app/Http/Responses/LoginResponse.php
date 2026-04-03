<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user && ! $user->hasVerifiedEmail()) {
            // 誘導画面のルート名に合わせる
            return redirect()->route('verification.notice');
            // return redirect()->route('verification.guide');
        }

        return redirect()->intended(route('attendance.index'));
    }
}
