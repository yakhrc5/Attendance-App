<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceDetailController extends Controller
{
    // 勤怠詳細画面を表示する
    public function show(int $id): View
    {
        // ログインユーザー本人の勤怠だけ取得する
        $attendance = Attendance::with([
            'user',
            'attendanceBreaks' => function ($query) {
                // 休憩は表示順が崩れないように ID 昇順で取得する
                $query->orderBy('id', 'asc');
            },
        ])
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // この勤怠に紐づく未承認の修正申請を取得する
        // approved_at が null のものを承認待ちとして扱う
        $pendingCorrectionRequest = StampCorrectionRequest::with([
            'stampCorrectionBreaks' => function ($query) {
                // 申請側の休憩も ID 昇順で取得する
                $query->orderBy('id', 'asc');
            },
        ])
            ->where('attendance_id', $attendance->id)
            ->whereNull('approved_at')
            ->latest('id')
            ->first();

        // 承認待ち申請があるかどうかを真偽値で持たせる
        $isPending = !is_null($pendingCorrectionRequest);

        return view('attendance.detail', [
            'attendance' => $attendance,
            'isPending' => $isPending,
            'pendingCorrectionRequest' => $pendingCorrectionRequest,
        ]);
    }
}
