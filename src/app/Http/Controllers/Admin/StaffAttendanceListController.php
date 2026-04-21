<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffAttendanceListController extends Controller
{
    /**
     * 管理者用 スタッフ別勤怠一覧画面を表示する
     */
    public function index(Request $request, int $id): View
    {
        // 対象スタッフを取得する
        $staffUser = $this->findStaffUser($id);

        // クエリパラメータの月を取得する
        // 指定がない場合は今月を表示する
        $month = $request->input('month', now()->format('Y-m'));

        // 想定外の値が来た場合は今月に戻す
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        // 月の基準日を作成する
        $targetMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        // 対象スタッフ・対象月の勤怠を取得する
        $attendances = $this->getMonthlyAttendances($staffUser->id, $targetMonth);

        // 一覧表示用データを作成する
        $attendanceRows = $this->buildAttendanceRows($targetMonth, $attendances);

        // 画面へ表示する
        return view('admin.attendance.staff', [
            'staffUser' => $staffUser,
            'attendanceRows' => $attendanceRows,
            'currentMonthLabel' => $targetMonth->format('Y/m'),
            'previousMonth' => $targetMonth->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $targetMonth->copy()->addMonth()->format('Y-m'),
            'currentMonthInput' => $targetMonth->format('Y-m'),
        ]);
    }

    /**
     * 管理者用 スタッフ別勤怠一覧CSVを出力する
     */
    public function exportCsv(Request $request, int $id): StreamedResponse
    {
        // 対象スタッフを取得する
        $staffUser = $this->findStaffUser($id);

        // クエリパラメータの月を取得する
        // 指定がない場合は今月を表示する
        $month = $request->input('month', now()->format('Y-m'));

        // 想定外の値が来た場合は今月に戻す
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        // 月の基準日を作成する
        $targetMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        // 対象スタッフ・対象月の勤怠を取得する
        $attendances = $this->getMonthlyAttendances($staffUser->id, $targetMonth);

        // 一覧表示用データを作成する
        // 画面表示とCSV出力で同じデータを使う
        $attendanceRows = $this->buildAttendanceRows($targetMonth, $attendances);

        // ダウンロードするCSVファイル名を作成する
        $fileName = sprintf(
            '%s_attendance_%s.csv',
            $staffUser->name,
            $targetMonth->format('Y_m')
        );

        return response()->streamDownload(function () use ($attendanceRows): void {
            $handle = fopen('php://output', 'w');

            // ExcelでUTF-8が文字化けしにくいようにBOMを付ける
            fwrite($handle, "\xEF\xBB\xBF");

            // ヘッダー行を書き込む
            fputcsv($handle, ['日付', '出勤', '退勤', '休憩', '合計']);

            // 勤怠一覧を1行ずつCSVへ書き込む
            foreach ($attendanceRows as $row) {
                /** @var \Carbon\Carbon $workDate */
                $workDate = $row['workDate'];

                fputcsv($handle, [
                    $workDate->isoFormat('MM/DD(dd)'),
                    $row['clockIn'],
                    $row['clockOut'],
                    $row['breakTime'],
                    $row['workTime'],
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * 対象スタッフを取得する
     */
    private function findStaffUser(int $id): User
    {
        // 管理者は除外し、一般ユーザーのみを対象にする
        return User::query()
            ->where('role', User::ROLE_USER)
            ->findOrFail($id);
    }

    /**
     * 対象スタッフ・対象月の勤怠を取得する
     *
     * @return \Illuminate\Support\Collection<string, \App\Models\Attendance>
     */
    private function getMonthlyAttendances(int $userId, Carbon $targetMonth): Collection
    {
        return Attendance::query()
            ->with('attendanceBreaks')
            ->where('user_id', $userId)
            ->whereBetween('work_date', [
                $targetMonth->copy()->startOfMonth()->toDateString(),
                $targetMonth->copy()->endOfMonth()->toDateString(),
            ])
            ->orderBy('work_date', 'asc')
            ->get()
            ->keyBy(function (Attendance $attendance): string {
                return Carbon::parse($attendance->work_date)->toDateString();
            });
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
                    ? route('admin.attendance.detail', ['id' => $attendance->id])
                    : null,
            ];
        }

        return $rows;
    }

    /**
     * 休憩時間を H:i 形式で返す
     */
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

    /**
     * 勤務合計時間を H:i 形式で返す
     */
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

    /**
     * 分数を H:i 形式に整形する
     */
    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $restMinutes = $minutes % 60;

        return sprintf('%d:%02d', $hours, $restMinutes);
    }
}
