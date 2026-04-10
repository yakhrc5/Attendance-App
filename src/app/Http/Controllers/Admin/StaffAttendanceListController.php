<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffAttendanceListController extends Controller
{
    /**
     * 管理者用 スタッフ別勤怠一覧画面を表示する
     */
    public function show(Request $request, int $id): View
    {
        // 対象スタッフを取得する
        $staffUser = $this->findStaffUser($id);

        // クエリパラメータの月を取得する
        // 指定がない場合は今月を表示する
        $targetMonth = $this->resolveTargetMonth($request->input('month'));

        // 対象スタッフ・対象月の勤怠を取得する
        $attendances = $this->getMonthlyAttendances($staffUser->id, $targetMonth);

        // 一覧表示用データを作成する
        $attendanceRows = $this->buildAttendanceRows($targetMonth, $attendances);

        // 画面へ表示する
        return view('admin.attendance.staff', [
            'staffUser' => $staffUser,
            'currentMonthLabel' => $targetMonth->format('Y年n月'),
            'currentMonth' => $targetMonth->format('Y/m'),
            'previousMonth' => $targetMonth->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $targetMonth->copy()->addMonthNoOverflow()->format('Y-m'),
            'attendanceRows' => $attendanceRows,
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
        $targetMonth = $this->resolveTargetMonth($request->input('month'));

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
            ->where('role', 'user')
            ->findOrFail($id);
    }

    /**
     * 表示対象月を決定する
     */
    private function resolveTargetMonth(?string $month): Carbon
    {
        // 指定がなければ今月を返す
        if (empty($month)) {
            return today()->startOfMonth();
        }

        // 月形式が不正でも画面が落ちないように今月へフォールバックする
        try {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            return today()->startOfMonth();
        }
    }

    /**
     * 対象スタッフ・対象月の勤怠を取得する
     *
     * @return \Illuminate\Support\Collection<string, \App\Models\Attendance>
     */
    private function getMonthlyAttendances(int $userId, Carbon $targetMonth): Collection
    {
        // 表示対象月の開始日と終了日を取得する
        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();

        // 対象月の勤怠を取得し、日付文字列をキーにして取り出しやすくする
        return Attendance::query()
            ->with('attendanceBreaks')
            ->where('user_id', $userId)
            ->whereBetween('work_date', [
                $startOfMonth->toDateString(),
                $endOfMonth->toDateString(),
            ])
            ->get()
            ->keyBy(function (Attendance $attendance): string {
                return $this->formatWorkDateKey($attendance->work_date);
            });
    }

    /**
     * 月内の全日付を一覧表示用データへ変換する
     *
     * @param \Illuminate\Support\Collection<string, \App\Models\Attendance> $attendances
     * @return array<int, array<string, mixed>>
     */
    private function buildAttendanceRows(Carbon $targetMonth, Collection $attendances): array
    {
        $attendanceRows = [];

        // 表示対象月の開始日と終了日を取得する
        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();

        // 月初から月末まで1日ずつ処理する
        $currentDate = $startOfMonth->copy();

        while ($currentDate->lte($endOfMonth)) {
            // 当日分の勤怠を取得する
            /** @var \App\Models\Attendance|null $attendance */
            $attendance = $attendances->get($currentDate->format('Y-m-d'));

            // 休憩合計秒数を計算する
            $breakSeconds = $this->calculateBreakSeconds($attendance);

            // 勤務合計秒数を計算する
            $workSeconds = $this->calculateWorkSeconds($attendance, $breakSeconds);

            // 画面表示用の1行データを作る
            $attendanceRows[] = [
                // Blade 側で isoFormat('MM/DD(dd)') を使うため Carbon のまま渡す
                'workDate' => $currentDate->copy()->locale('ja'),

                // 出勤時刻
                'clockIn' => $this->formatTime($attendance?->clock_in_at),

                // 退勤時刻
                'clockOut' => $this->formatTime($attendance?->clock_out_at),

                // 休憩時間
                // 出勤中かつ、まだ一度も休憩していない場合は空欄にする
                'breakTime' => $attendance
                    ? $this->formatBreakTime($attendance)
                    : '',

                // 合計勤務時間
                // 出勤・退勤の両方がそろっている場合のみ表示する
                'workTime' => $this->shouldShowWorkTime($attendance)
                    ? $this->formatSeconds($workSeconds)
                    : '',

                // 勤怠データがある日のみ詳細リンクを付ける
                'detailUrl' => $attendance
                    ? route('admin.attendance.detail', ['id' => $attendance->id])
                    : null,
            ];

            // 次の日へ進める
            $currentDate->addDay();
        }

        return $attendanceRows;
    }

    /**
     * 勤怠日のキー文字列を Y-m-d 形式にそろえる
     *
     * @param \Carbon\Carbon|string $workDate
     */
    private function formatWorkDateKey($workDate): string
    {
        // Carbon の場合はそのまま整形する
        if ($workDate instanceof Carbon) {
            return $workDate->format('Y-m-d');
        }

        // 文字列の場合は Carbon に変換して整形する
        return Carbon::parse($workDate)->format('Y-m-d');
    }

    /**
     * 時刻表示用に H:i 形式へ整形する
     *
     * @param \Carbon\Carbon|string|null $dateTime
     */
    private function formatTime($dateTime): string
    {
        // 値がなければ空文字を返す
        if (empty($dateTime)) {
            return '';
        }

        // Carbon でも文字列でも H:i 表示にそろえる
        return Carbon::parse($dateTime)->format('H:i');
    }

    /**
     * 休憩時間を H:MM 形式で返す
     */
    private function formatBreakTime(Attendance $attendance): string
    {
        // 開始・終了がそろっている休憩だけを完了済み休憩として取得する
        $completedBreaks = $attendance->attendanceBreaks->filter(function ($attendanceBreak): bool {
            return !empty($attendanceBreak->break_start_at) && !empty($attendanceBreak->break_end_at);
        });

        // 開始だけ入っていて終了が未入力の休憩を未完了休憩として取得する
        $openBreaks = $attendance->attendanceBreaks->filter(function ($attendanceBreak): bool {
            return !empty($attendanceBreak->break_start_at) && empty($attendanceBreak->break_end_at);
        });

        // 出勤中で、まだ一度も休憩していない場合だけ空欄にする
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

        return $this->formatMinutes($breakMinutes);
    }

    /**
     * 休憩合計秒数を計算する
     */
    private function calculateBreakSeconds(?Attendance $attendance): int
    {
        // 勤怠がなければ 0 秒
        if (!$attendance) {
            return 0;
        }

        // 開始・終了がそろっている休憩だけ合計する
        return $attendance->attendanceBreaks->sum(function ($attendanceBreak): int {
            if (
                empty($attendanceBreak->break_start_at) ||
                empty($attendanceBreak->break_end_at)
            ) {
                return 0;
            }

            return Carbon::parse($attendanceBreak->break_start_at)
                ->diffInSeconds(Carbon::parse($attendanceBreak->break_end_at));
        });
    }

    /**
     * 勤務合計秒数を計算する
     */
    private function calculateWorkSeconds(?Attendance $attendance, int $breakSeconds): int
    {
        // 出勤・退勤がそろっていなければ勤務時間は出せない
        if (
            !$attendance ||
            empty($attendance->clock_in_at) ||
            empty($attendance->clock_out_at)
        ) {
            return 0;
        }

        // 出勤から退勤までの総秒数を計算する
        $totalSeconds = Carbon::parse($attendance->clock_in_at)
            ->diffInSeconds(Carbon::parse($attendance->clock_out_at));

        // 総勤務時間 - 休憩時間 を返す
        return max($totalSeconds - $breakSeconds, 0);
    }

    /**
     * 合計勤務時間を表示できる状態か判定する
     */
    private function shouldShowWorkTime(?Attendance $attendance): bool
    {
        // 出勤・退勤がそろっている時だけ表示する
        return $attendance !== null
            && !empty($attendance->clock_in_at)
            && !empty($attendance->clock_out_at);
    }

    /**
     * 秒数を H:MM 形式へ変換する
     */
    private function formatSeconds(int $seconds): string
    {
        // 時を計算する
        $hours = intdiv($seconds, 3600);

        // 分を計算する
        $minutes = intdiv($seconds % 3600, 60);

        // 例: 8:00 の形で返す
        return sprintf('%d:%02d', $hours, $minutes);
    }

    /**
     * 分数を H:MM 形式へ変換する
     */
    private function formatMinutes(int $minutes): string
    {
        // 時を計算する
        $hours = intdiv($minutes, 60);

        // 分を計算する
        $restMinutes = $minutes % 60;

        // 例: 8:00 の形で返す
        return sprintf('%d:%02d', $hours, $restMinutes);
    }
}
