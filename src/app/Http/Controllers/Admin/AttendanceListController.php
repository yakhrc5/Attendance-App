<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceListController extends Controller
{
    /**
     * 管理者用 勤怠一覧画面
     */
    public function index(Request $request): View
    {
        // クエリパラメータから対象日を取得する
        // 指定がない場合や不正な形式の場合は今日を採用する
        $targetDate = $this->resolveTargetDate($request->input('date'));

        // 対象日の勤怠データを取得する
        // 一般ユーザーの勤怠だけに絞り、名前順で並べる
        $attendances = Attendance::query()
            ->with([
                'user:id,name,role',
                'attendanceBreaks',
            ])
            ->where('work_date', $targetDate->toDateString())
            ->whereHas('user', function ($query) {
                $query->where('role', User::ROLE_USER);
            })
            ->join('users', 'attendances.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->select('attendances.*')
            ->get();

        // Blade に渡す一覧表示用データを整形する
        $attendanceRows = $attendances->map(function (Attendance $attendance): array {
            // 休憩分数を計算する
            $breakMinutes = $this->calculateBreakMinutes($attendance);

            // 勤務分数を計算する
            $workMinutes = $this->calculateWorkMinutes($attendance, $breakMinutes);

            return [
                // スタッフ名
                'staffName' => $attendance->user->name,

                // 出勤時刻
                'clockIn' => $this->formatTime($attendance->clock_in_at),

                // 退勤時刻
                'clockOut' => $this->formatTime($attendance->clock_out_at),

                // 休憩時間
                // 出勤中かつ、まだ一度も休憩していない場合は空欄にする
                'breakTime' => $this->formatBreakTime($attendance),

                // 合計勤務時間
                // 出勤・退勤の両方がそろっているときだけ表示する
                'workTime' => $this->shouldShowWorkTime($attendance)
                    ? $this->formatMinutes($workMinutes)
                    : '',

                // 勤怠詳細リンク
                'detailUrl' => route('admin.attendance.detail', ['id' => $attendance->id]),
            ];
        });

        // 一覧画面に必要な表示データを渡す
        return view('admin.attendance.list', [
            'currentDateLabel' => $targetDate->format('Y年n月j日'),
            'currentDate' => $targetDate->format('Y/m/d'),
            'previousDate' => $targetDate->copy()->subDay()->format('Y-m-d'),
            'nextDate' => $targetDate->copy()->addDay()->format('Y-m-d'),
            'attendanceRows' => $attendanceRows,
            'currentDateInput' => $targetDate->format('Y-m-d'),
        ]);
    }

    /**
     * 表示対象日を決定する
     */
    private function resolveTargetDate(?string $date): Carbon
    {
        // 日付指定がない場合は今日を返す
        if (empty($date)) {
            return today();
        }

        try {
            // Y-m-d 形式で受け取り、その日の開始時刻にそろえる
            return Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            // 不正な値が来た場合も今日を返す
            return today();
        }
    }

    /**
     * 時刻を H:i 形式に整形する
     */
    private function formatTime($dateTime): string
    {
        // 値がない場合は空欄を返す
        if (empty($dateTime)) {
            return '';
        }

        return Carbon::parse($dateTime)->format('H:i');
    }

    /**
     * 休憩時間を表示用に整形する
     */
    private function formatBreakTime(Attendance $attendance): string
    {
        // 開始・終了がそろっている休憩だけを完了済み休憩として取得する
        $completedBreaks = $attendance->attendanceBreaks->filter(function ($break): bool {
            return !empty($break->break_start_at) && !empty($break->break_end_at);
        });

        // 開始だけ入っていて終了が未入力の休憩を未完了休憩として取得する
        $openBreaks = $attendance->attendanceBreaks->filter(function ($break): bool {
            return !empty($break->break_start_at) && empty($break->break_end_at);
        });

        // 出勤中で、まだ一度も休憩していない場合だけ空欄にする
        // 一般ユーザー一覧画面と同じ表示ルールにそろえる
        if (
            empty($attendance->clock_out_at) &&
            $completedBreaks->isEmpty() &&
            $openBreaks->isEmpty()
        ) {
            return '';
        }

        // 完了済み休憩だけを合計して分数に変換する
        // 一覧表示と同じ粒度にそろえるため、秒は切り捨ててから計算する
        $breakMinutes = $completedBreaks->sum(function ($break): int {
            $breakStart = Carbon::parse($break->break_start_at)->startOfMinute();
            $breakEnd = Carbon::parse($break->break_end_at)->startOfMinute();

            return $breakStart->diffInMinutes($breakEnd);
        });

        return $this->formatMinutes($breakMinutes);
    }

    /**
     * 休憩合計分数を計算する
     */
    private function calculateBreakMinutes(Attendance $attendance): int
    {
        // 開始・終了の両方が入っている休憩のみ合計する
        return $attendance->attendanceBreaks->sum(function ($break): int {
            if (empty($break->break_start_at) || empty($break->break_end_at)) {
                return 0;
            }

            $breakStart = Carbon::parse($break->break_start_at)->startOfMinute();
            $breakEnd = Carbon::parse($break->break_end_at)->startOfMinute();

            return $breakStart->diffInMinutes($breakEnd);
        });
    }

    /**
     * 勤務合計分数を計算する
     */
    private function calculateWorkMinutes(Attendance $attendance, int $breakMinutes): int
    {
        // 出勤または退勤が欠けている場合は勤務時間を計算しない
        if (empty($attendance->clock_in_at) || empty($attendance->clock_out_at)) {
            return 0;
        }

        // 一覧表示と同じ粒度にそろえるため、秒は切り捨ててから計算する
        $clockIn = Carbon::parse($attendance->clock_in_at)->startOfMinute();
        $clockOut = Carbon::parse($attendance->clock_out_at)->startOfMinute();

        // 出勤から退勤までの総分数を求める
        $totalMinutes = $clockIn->diffInMinutes($clockOut);

        // 総勤務分数から休憩分数を引く
        // 万が一マイナスになるのを防ぐため 0 未満にはしない
        return max($totalMinutes - $breakMinutes, 0);
    }

    /**
     * 合計勤務時間を表示できる状態か判定する
     */
    private function shouldShowWorkTime(Attendance $attendance): bool
    {
        // 出勤・退勤がそろっているときだけ表示する
        return !empty($attendance->clock_in_at) && !empty($attendance->clock_out_at);
    }

    /**
     * 分数を H:MM 形式に変換する
     */
    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $restMinutes = $minutes % 60;

        return sprintf('%d:%02d', $hours, $restMinutes);
    }
}
