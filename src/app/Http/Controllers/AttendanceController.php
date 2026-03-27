<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * 勤怠登録画面を表示する
     *
     * @return View
     */
    public function index(): View
    {
        return view('attendance.attendance', [
            'statusLabel' => '勤務外',
            'currentDate' => now()->isoFormat('YYYY年M月D日(dd)'),
            'currentTime' => now()->format('H:i'),
            'actionLabel' => '出勤',
        ]);
    }

    /**
     * 打刻を登録する
     *
     * @return RedirectResponse
     */
    public function store(): RedirectResponse
    {
        // 今は画面確認用のため、DB保存処理はまだ入れない
        return back();
    }
}
