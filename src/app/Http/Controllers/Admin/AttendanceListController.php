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
     * 管理者用 勤怠一覧画面を表示する
     */
    public function index(Request $request): View
    {
        // クエリパラメータから対象日を取得する
        // 指定がない場合や不正な形式の場合は今日を採用する
        $targetDate = $this->resolveTargetDate($request->input('date'));

        // 一般ユーザー一覧を取得する
        // 管理者は含めず、名前順で並べる
        $users = User::query()
            ->where('role', 'user')
            ->orderBy('name')
            ->get(['id', 'name']);

        // 対象日の勤怠データをまとめて取得する
        // user_id をキーにして、あとで各ユーザーに対応する勤怠を取り出しやすくする
        $attendances = Attendance::query()
            ->with('attendanceBreaks')
            ->whereDate('work_date', $targetDate->toDateString())
            ->get()
            ->keyBy('user_id');

        // Blade に渡す一覧表示用データを整形する
        $attendanceRows = $users->map(function (User $user) use ($attendances) {
            /** @var \App\Models\Attendance|null $attendance */
            $attendance = $attendances->get($user->id);

            // 休憩秒数を計算する
            $breakSeconds = $this->calculateBreakSeconds($attendance);

            // 勤務秒数を計算する
            $workSeconds = $this->calculateWorkSeconds($attendance, $breakSeconds);

            return [
                // スタッフ名
                'staffName' => $user->name,

                // 出勤時刻
                'clockIn' => $this->formatTime($attendance?->clock_in_at),

                // 退勤時刻
                'clockOut' => $this->formatTime($attendance?->clock_out_at),

                // 休憩時間
                // 一般ユーザー一覧と同じルールで表示する
                // 出勤中かつ、まだ一度も休憩していない場合は空欄にする
                'breakTime' => $attendance
                    ? $this->formatBreakTime($attendance)
                    : '',

                // 合計勤務時間
                // 出勤・退勤の両方がそろっているときだけ表示する
                'workTime' => $this->shouldShowWorkTime($attendance)
                    ? $this->formatSeconds($workSeconds)
                    : '',

                // 勤怠データがある場合だけ詳細リンクを付ける
                'detailUrl' => $attendance
                    ? route('admin.attendance.detail', ['id' => $attendance->id])
                    : null,
            ];
        });

        // 一覧画面に必要な表示データを渡す
        return view('admin.attendance.list', [
            'currentDateLabel' => $targetDate->format('Y年n月j日'),
            'currentDate' => $targetDate->format('Y/m/d'),
            'previousDate' => $targetDate->copy()->subDay()->format('Y-m-d'),
            'nextDate' => $targetDate->copy()->addDay()->format('Y-m-d'),
            'attendanceRows' => $attendanceRows,
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

        // 完了済み休憩だけを合計して秒数に変換する
        $breakSeconds = $completedBreaks->sum(function ($break): int {
            return Carbon::parse($break->break_start_at)
                ->diffInSeconds(Carbon::parse($break->break_end_at));
        });

        return $this->formatSeconds($breakSeconds);
    }

    /**
     * 休憩合計秒数を計算する
     */
    private function calculateBreakSeconds(?Attendance $attendance): int
    {
        // 勤怠データがない場合は 0 秒
        if (!$attendance) {
            return 0;
        }

        // 開始・終了の両方が入っている休憩のみ合計する
        return $attendance->attendanceBreaks->sum(function ($break): int {
            if (empty($break->break_start_at) || empty($break->break_end_at)) {
                return 0;
            }

            return Carbon::parse($break->break_start_at)
                ->diffInSeconds(Carbon::parse($break->break_end_at));
        });
    }

    /**
     * 勤務合計秒数を計算する
     */
    private function calculateWorkSeconds(?Attendance $attendance, int $breakSeconds): int
    {
        // 出勤または退勤が欠けている場合は勤務時間を計算しない
        if (!$attendance || empty($attendance->clock_in_at) || empty($attendance->clock_out_at)) {
            return 0;
        }

        // 出勤から退勤までの総秒数を求める
        $totalSeconds = Carbon::parse($attendance->clock_in_at)
            ->diffInSeconds(Carbon::parse($attendance->clock_out_at));

        // 総勤務秒数から休憩秒数を引く
        // 万が一マイナスになるのを防ぐため 0 未満にはしない
        return max($totalSeconds - $breakSeconds, 0);
    }

    /**
     * 合計勤務時間を表示できる状態か判定する
     */
    private function shouldShowWorkTime(?Attendance $attendance): bool
    {
        // 勤怠データ自体がない場合は表示しない
        if (!$attendance) {
            return false;
        }

        // 出勤・退勤がそろっているときだけ表示する
        return !empty($attendance->clock_in_at) && !empty($attendance->clock_out_at);
    }

    /**
     * 秒数を H:MM 形式に変換する
     */
    private function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%d:%02d', $hours, $minutes);
    }
}
