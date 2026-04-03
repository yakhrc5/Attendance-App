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
    // 勤怠一覧画面を表示する
    public function show(): View
    {
        // クエリパラメータの月を取得する
        // 指定がない場合は今月を表示する
        $month = request('month', now()->format('Y-m'));

        // 月の基準日を作成する
        $targetMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        // ログインユーザーの対象月の勤怠一覧を取得する
        $attendances = Attendance::with('attendanceBreaks')
            ->where('user_id', Auth::id())
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
        ]);
    }

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
            // データがなければ null
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

                // データがある日付は詳細リンクを表示するための URL を渡す
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
        $breakMinutes = $completedBreaks->sum(function ($attendanceBreak): int {
            return Carbon::parse($attendanceBreak->break_start_at)
                ->diffInMinutes(Carbon::parse($attendanceBreak->break_end_at));
        });

        // 0分なら formatMinutes() の結果として 0:00 になる
        return $this->formatMinutes($breakMinutes);
    }

    // 勤務合計時間を H:i 形式で返す
    private function formatWorkTime(Attendance $attendance): string
    {
        if (empty($attendance->clock_in_at) || empty($attendance->clock_out_at)) {
            return '';
        }

        $totalMinutes = Carbon::parse($attendance->clock_in_at)
            ->diffInMinutes(Carbon::parse($attendance->clock_out_at));

        $breakMinutes = $attendance->attendanceBreaks->sum(function ($attendanceBreak): int {
            if (empty($attendanceBreak->break_start_at) || empty($attendanceBreak->break_end_at)) {
                return 0;
            }

            return Carbon::parse($attendanceBreak->break_start_at)
                ->diffInMinutes(Carbon::parse($attendanceBreak->break_end_at));
        });

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
