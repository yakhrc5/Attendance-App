<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    // ログインユーザーIDを取得する
    private function currentUserId(): int
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user->id;
    }

    // ログインユーザーの今日の勤怠を取得する
    private function findTodayAttendance(): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $this->currentUserId())
            ->where('work_date', today()->toDateString())
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
        $attendance = $this->findTodayAttendance();

        // 画面表示用の初期値を設定する
        $statusLabel = '勤務外';
        $showClockIn = false;
        $showBreakStart = false;
        $showBreakEnd = false;
        $showClockOut = false;
        $headerMode = 'default';

        // 今日の勤怠が未登録なら勤務外とし、出勤ボタンを表示する
        if (is_null($attendance)) {
            $showClockIn = true;
        // 勤怠レコードがあり、退勤済みなら退勤済ステータスのみ表示する
        } elseif (!is_null($attendance->clock_out_at)) {
            $statusLabel = '退勤済';
            $headerMode = 'after_clock_out';
        // 未終了の休憩レコードがある場合は休憩中とし、休憩戻ボタンを表示する
        } elseif ($this->hasOpenBreak($attendance)) {
            $statusLabel = '休憩中';
            $showBreakEnd = true;
        // 上記以外は出勤中とし、休憩入ボタンと退勤ボタンを表示する
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
        $attendance = $this->findTodayAttendance();

        if (!is_null($attendance)) {
            return back();
        }

        Attendance::create([
            'user_id' => $this->currentUserId(),
            'work_date' => today()->toDateString(),
            'clock_in_at' => now(),
        ]);

        return redirect()->route('attendance.index');
    }

    // 退勤打刻を登録する
    public function clockOut(): RedirectResponse
    {
        // 今日の勤怠を取得する
        $attendance = $this->findTodayAttendance();

        // 今日の勤怠が存在しない場合は退勤できない
        if (is_null($attendance)) {
            return back();
        }

        // すでに退勤済みの場合は再打刻させない
        if (!is_null($attendance->clock_out_at)) {
            return back();
        }

        // 休憩中は退勤させない
        if ($this->hasOpenBreak($attendance)) {
            return back();
        }

        $attendance->update([
            'clock_out_at' => now(),
        ]);

        return redirect()
            ->route('attendance.index');
    }


    // 休憩入打刻を登録する
    public function breakStart(): RedirectResponse
    {
        // 今日の勤怠を取得する
        $attendance = $this->findTodayAttendance();

        // 出勤前は休憩入できない
        if (is_null($attendance)) {
            return back();
        }

        // 退勤後は休憩入できない
        if (!is_null($attendance->clock_out_at)) {
            return back();
        }

        // すでに休憩中なら再度休憩入できない
        if ($this->hasOpenBreak($attendance)) {
            return back();
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
        $attendance = $this->findTodayAttendance();

        // 出勤前は休憩戻できない
        if (is_null($attendance)) {
            return back();
        }

        // 退勤後は休憩戻できない
        if (!is_null($attendance->clock_out_at)) {
            return back();
        }

        // 今日の未終了休憩を取得する
        $attendanceBreak = AttendanceBreak::query()
            ->where('attendance_id', $attendance->id)
            ->whereNull('break_end_at')
            ->latest('break_start_at')
            ->first();

        // 未終了休憩がなければ休憩戻できない
        if (is_null($attendanceBreak)) {
            return back();
        }

        $attendanceBreak->update([
            'break_end_at' => now(),
        ]);

        return redirect()->route('attendance.index');
    }
}

