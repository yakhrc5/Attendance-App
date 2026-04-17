<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\StampCorrectionBreak;
use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AttendanceRequestController extends Controller
{
    // 勤怠修正申請を保存する
    public function store(AttendanceCorrectionRequest $request, int $id): RedirectResponse
    {
        // ログインユーザー本人の勤怠だけ取得する
        $attendance = Attendance::with('attendanceBreaks')
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        // すでに未承認の修正申請があるか確認する
        $hasPendingRequest = StampCorrectionRequest::query()
            ->where('attendance_id', $attendance->id)
            ->whereNull('approved_at')
            ->exists();

        // 未承認の申請がある場合は二重申請を防ぐ
        if ($hasPendingRequest) {
            return redirect()
                ->route('attendance.detail', ['id' => $attendance->id]);
        }

        // FormRequest のバリデーション通過後データを取得する
        $validated = $request->validated();

        // 休憩行を保存しやすい形に整える
        $breaks = $this->normalizeBreaks($validated['breaks'] ?? []);

        // 打刻修正申請を作成する
        $stampCorrectionRequest = StampCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => Auth::id(),

            // 申請された出勤・退勤時刻を datetime に変換して保存する
            'requested_clock_in_at' => $this->toDateTimeOrNull(
                $attendance->work_date,
                $validated['clock_in_at'] ?? null
            ),
            'requested_clock_out_at' => $this->toDateTimeOrNull(
                $attendance->work_date,
                $validated['clock_out_at'] ?? null
            ),

            // 備考は reason カラムに保存する
            'reason' => $validated['reason'],

            // まだ承認前なので null
            'approved_at' => null,
        ]);

        // 休憩申請を子テーブルへ保存する
        foreach ($breaks as $break) {
            StampCorrectionBreak::create([
                'stamp_correction_request_id' => $stampCorrectionRequest->id,
                'requested_break_start_at' => $this->toDateTimeOrNull(
                    $attendance->work_date,
                    $break['break_start_at']
                ),
                'requested_break_end_at' => $this->toDateTimeOrNull(
                    $attendance->work_date,
                    $break['break_end_at']
                ),
            ]);
        }

        // 詳細画面へ戻す
        return redirect()
            ->route('attendance.detail', ['id' => $attendance->id]);
    }

    // 休憩配列を保存しやすい形に整える
    private function normalizeBreaks(array $breaks): array
    {
        return collect($breaks)
            // 両方空の行は保存しない
            ->filter(function (array $break): bool {
                return !empty($break['break_start_at'] ?? null)
                    || !empty($break['break_end_at'] ?? null);
            })
            // キーを詰め直す
            ->values()
            // 保存用の配列に整える
            ->map(function (array $break): array {
                return [
                    'break_start_at' => $break['break_start_at'] ?? null,
                    'break_end_at' => $break['break_end_at'] ?? null,
                ];
            })
            ->all();
    }

    // 勤務日と時刻文字列を結合して datetime に変換する
    private function toDateTimeOrNull($workDate, ?string $time): ?string
    {
        // 未入力なら null
        if (empty($time)) {
            return null;
        }

        // 勤務日を必ず Y-m-d 形式に揃える
        $date = Carbon::parse($workDate)->format('Y-m-d');

        // 日付と時刻を結合して datetime 文字列にする
        return Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time)
            ->format('Y-m-d H:i:s');
    }
}
