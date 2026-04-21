<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceListController extends Controller
{
    // ログインユーザーIDを取得する
    private function currentUserId(): int
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user->id;
    }

    // 勤怠一覧画面を表示する
    public function index(): View
    {
        // クエリパラメータの月を取得する
        // 指定がない場合は今月を表示する
        $month = request('month', now()->format('Y-m'));

        // 想定外の値が来た場合は今月に戻す
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        // 月の基準日を作成する
        $targetMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        // ログインユーザーの対象月の勤怠一覧を取得する
        $attendances = Attendance::query()
            ->with('attendanceBreaks')
            ->where('user_id', $this->currentUserId())
            ->whereBetween('work_date', [
                $targetMonth->copy()->startOfMonth()->toDateString(),
                $targetMonth->copy()->endOfMonth()->toDateString(),
            ])
            ->orderBy('work_date', 'asc')
            ->get()
            ->keyBy(function (Attendance $attendance): string {
                return Carbon::parse($attendance->work_date)->toDateString();
            });

        // 対象月の全日付分の表示データを作成する
        $attendanceRows = $this->buildAttendanceRows($targetMonth, $attendances);

        return view('attendance.list', [
            'attendanceRows' => $attendanceRows,
            'currentMonthLabel' => $targetMonth->format('Y/m'),
            'previousMonth' => $targetMonth->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $targetMonth->copy()->addMonth()->format('Y-m'),
            'currentMonthInput' => $targetMonth->format('Y-m'),
        ]);
    }

    /**
     * @param \Carbon\Carbon $targetMonth
     * @param \Illuminate\Support\Collection<string, \App\Models\Attendance> $attendances
     * @return array<int, array{
     *     workDate: \Carbon\Carbon,
     *     clockIn: string,
     *     clockOut: string,
     *     breakTime: string,
     *     workTime: string,
     *     detailUrl: string|null
     * }>
     */
    private function buildAttendanceRows(Carbon $targetMonth, Collection $attendances): array
    {
        $rows = [];

        // その月の1日〜月末日までを1日ずつ生成する
        $period = CarbonPeriod::create(
            $targetMonth->copy()->startOfMonth(),
            $targetMonth->copy()->endOfMonth()
        );

        foreach ($period as $workDate) {
            // その日のキーを作る
            $dateKey = $workDate->toDateString();

            // その日の勤怠データを取得する
            /** @var \App\Models\Attendance|null $attendance */
            $attendance = $attendances->get($dateKey);

            $rows[] = [
                // Blade 側で isoFormat('MM/DD(dd)') を使うため Carbon のまま渡す
                'workDate' => $workDate->copy()->locale('ja'),

                // 出勤時刻
                'clockIn' => $attendance?->clock_in_at
                    ? Carbon::parse($attendance->clock_in_at)->format('H:i')
                    : '',

                // 退勤時刻
                'clockOut' => $attendance?->clock_out_at
                    ? Carbon::parse($attendance->clock_out_at)->format('H:i')
                    : '',

                // 休憩時間
                'breakTime' => $attendance
                    ? $this->formatBreakTime($attendance)
                    : '',

                // 勤務合計時間
                'workTime' => $attendance
                    ? $this->formatWorkTime($attendance)
                    : '',

                // データがある日付は詳細リンクを表示するためのURLを渡す
                'detailUrl' => $attendance !== null
                    ? route('attendance.detail', ['id' => $attendance->id])
                    : null,
            ];
        }

        return $rows;
    }

    // 休憩時間を H:i 形式で返す
    private function formatBreakTime(Attendance $attendance): string
    {
        // 完了済み休憩を取得する
        $completedBreaks = $attendance->attendanceBreaks->filter(function ($attendanceBreak): bool {
            return !empty($attendanceBreak->break_start_at) && !empty($attendanceBreak->break_end_at);
        });

        // 未完了休憩を取得する
        $openBreaks = $attendance->attendanceBreaks->filter(function ($attendanceBreak): bool {
            return !empty($attendanceBreak->break_start_at) && empty($attendanceBreak->break_end_at);
        });

        // 出勤中かつ、まだ一度も休憩していない場合だけ空欄にする
        if (
            empty($attendance->clock_out_at) &&
            $completedBreaks->isEmpty() &&
            $openBreaks->isEmpty()
        ) {
            return '';
        }

        // 完了済み休憩のみ合計する
        // 一覧表示と同じ粒度にそろえるため、秒は切り捨ててから計算する
        $breakMinutes = $completedBreaks->sum(function ($attendanceBreak): int {
            $breakStart = Carbon::parse($attendanceBreak->break_start_at)->startOfMinute();
            $breakEnd = Carbon::parse($attendanceBreak->break_end_at)->startOfMinute();

            return $breakStart->diffInMinutes($breakEnd);
        });

        return $this->formatMinutes($breakMinutes);
    }

    // 勤務合計時間を H:i 形式で返す
    private function formatWorkTime(Attendance $attendance): string
    {
        // 出勤時刻または退勤時刻がない場合は空欄にする
        if (empty($attendance->clock_in_at) || empty($attendance->clock_out_at)) {
            return '';
        }

        // 一覧表示と同じ粒度にそろえるため、秒は切り捨ててから計算する
        $clockIn = Carbon::parse($attendance->clock_in_at)->startOfMinute();
        $clockOut = Carbon::parse($attendance->clock_out_at)->startOfMinute();

        // 出勤から退勤までの総勤務時間を分で求める
        $totalMinutes = $clockIn->diffInMinutes($clockOut);

        // 完了済み休憩のみ合計する
        $breakMinutes = $attendance->attendanceBreaks->sum(function ($attendanceBreak): int {
            if (empty($attendanceBreak->break_start_at) || empty($attendanceBreak->break_end_at)) {
                return 0;
            }

            $breakStart = Carbon::parse($attendanceBreak->break_start_at)->startOfMinute();
            $breakEnd = Carbon::parse($attendanceBreak->break_end_at)->startOfMinute();

            return $breakStart->diffInMinutes($breakEnd);
        });

        // 勤務時間がマイナスにならないように調整する
        $workMinutes = max($totalMinutes - $breakMinutes, 0);

        return $this->formatMinutes($workMinutes);
    }

    // 分数を H:i 形式に整形する
    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $restMinutes = $minutes % 60;

        return sprintf('%d:%02d', $hours, $restMinutes);
    }
}
