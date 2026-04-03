<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    // ログインユーザーの今日の勤怠を取得する
    private function findTodayAttendance(int $userId): ?Attendance
    {
        return Attendance::where('user_id', $userId)
            ->whereDate('work_date', today())
            ->first();
    }

    // 未終了の休憩が存在するか判定する
    private function hasOpenBreak(Attendance $attendance): bool
    {
        return $attendance->attendanceBreaks()
            ->whereNull('break_end_at')
            ->exists();
    }

    // 勤怠登録画面を表示する
    public function index(): View
    {
        // ログインユーザーの今日の勤怠情報を取得する
        $attendance = $this->findTodayAttendance(Auth::id());

        // 画面表示用の初期値を設定する
        $statusLabel = '勤務外';
        $showClockIn = false;
        $showBreakStart = false;
        $showBreakEnd = false;
        $showClockOut = false;
        $headerMode = 'default';

        // 今日の勤怠が未登録なら勤務外とし、出勤ボタンを表示する
        if (!$attendance) {
            $showClockIn = true;
        //　attendanceテーブルのレコードが存在し、clock_out_atがnullでない場合は退勤済ステータスのみ表示する
        }elseif ($attendance->clock_out_at) {
            $statusLabel = '退勤済';
            $headerMode = 'after_clock_out';
        // 未終了の休憩レコードが存在する場合は休憩中とし、休憩終了ボタンを表示する
        } elseif ($this->hasOpenBreak($attendance)) {
            $statusLabel = '休憩中';
            $showBreakEnd = true;
        // 上記以外であれば出勤中とし、休憩入ボタンと退勤ボタンを表示する
        } else {
            $statusLabel = '出勤中';
            $showBreakStart = true;
            $showClockOut = true;
        }

        return view('attendance.attendance', [
            'statusLabel' => $statusLabel,
            'currentDate' => now()->isoFormat('YYYY年M月D日(dd)'),
            'currentTime' => now()->format('H:i'),
            'showClockIn' => $showClockIn,
            'showBreakStart' => $showBreakStart,
            'showBreakEnd' => $showBreakEnd,
            'showClockOut' => $showClockOut,
            'headerMode' => $headerMode,
        ]);
    }

    // 出勤打刻を登録する
    public function clockIn(): RedirectResponse
    {
        // 今日の勤怠がすでにある場合は二重打刻を防ぐ
        $attendance = $this->findTodayAttendance(Auth::id());

        if (!is_null($attendance)) {
            return back()->with('error', '本日はすでに出勤済みです。');
        }

        Attendance::create([
            'user_id' => Auth::id(),
            'work_date' => today(),
            'clock_in_at' => now(),
        ]);

        return redirect()->route('attendance.index');
    }

    // 退勤打刻を登録する
    public function clockOut(): RedirectResponse
    {
        // 今日の勤怠を取得する
        $attendance = $this->findTodayAttendance(Auth::id());

        // 今日の勤怠が存在しない場合は退勤できない
        if (is_null($attendance)) {
            return back()->with('error', '本日の勤怠が存在しません。');
        }

        // すでに退勤済みの場合は再打刻させない
        if (!is_null($attendance->clock_out_at)) {
            return back()->with('error', 'すでに退勤済みです。');
        }

        // 休憩中は退勤させない
        if ($this->hasOpenBreak($attendance)) {
            return back()->with('error', '休憩戻を行ってから退勤してください。');
        }

        $attendance->update([
            'clock_out_at' => now(),
        ]);

        return redirect()
            ->route('attendance.index')
            ->with('message', 'お疲れ様でした。');
    }

    // 休憩入打刻を登録する
    public function breakStart(): RedirectResponse
    {
        // 今日の勤怠を取得する
        $attendance = $this->findTodayAttendance(Auth::id());

        // 出勤前は休憩入できない
        if (is_null($attendance)) {
            return back()->with('error', '出勤後に休憩入を行ってください。');
        }

        // 退勤後は休憩入できない
        if (!is_null($attendance->clock_out_at)) {
            return back()->with('error', '退勤後は休憩入できません。');
        }

        // すでに休憩中なら再度休憩入できない
        if ($this->hasOpenBreak($attendance)) {
            return back()->with('error', 'すでに休憩中です。');
        }

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_start_at' => now(),
        ]);

        return redirect()->route('attendance.index');
    }

    // 休憩戻打刻を登録する
    public function breakEnd(): RedirectResponse
    {
        // 今日の勤怠を取得する
        $attendance = $this->findTodayAttendance(Auth::id());

        // 出勤前は休憩戻できない
        if (is_null($attendance)) {
            return back()->with('error', '出勤後に休憩戻を行ってください。');
        }

        // 今日の未終了休憩を取得する
        $attendanceBreak = AttendanceBreak::where('attendance_id', $attendance->id)
            ->whereNull('break_end_at')
            ->latest('break_start_at')
            ->first();

        // 未終了休憩がなければ休憩戻できない
        if (is_null($attendanceBreak)) {
            return back()->with('error', '休憩中ではありません。');
        }

        $attendanceBreak->update([
            'break_end_at' => now(),
        ]);

        return redirect()->route('attendance.index');
    }
}

