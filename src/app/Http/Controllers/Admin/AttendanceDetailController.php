<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceDetailController extends Controller
{
    /**
     * 管理者用 勤怠詳細画面
     */
    public function show(int $id): View
    {
        // 対象の勤怠を取得する
        // ユーザー情報と休憩情報も一緒に読み込む
        $attendance = Attendance::query()
            ->with([
                'user',
                'attendanceBreaks' => function ($query) {
                    // 休憩は表示順が崩れないように ID 昇順で取得する
                    $query->orderBy('id', 'asc');
                },
            ])
            ->findOrFail($id);

        // この勤怠に紐づく未承認の修正申請を取得する
        // approved_at が null のものを承認待ちとして扱う
        $pendingCorrectionRequest = StampCorrectionRequest::query()
            ->with([
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

        // 管理者用詳細画面を表示する
        return view('admin.attendance.detail', [
            'attendance' => $attendance,
            'isPending' => $isPending,
            'pendingCorrectionRequest' => $pendingCorrectionRequest,
        ]);
    }

    /**
     * 管理者用 勤怠直接修正処理
     */
    public function update(AttendanceCorrectionRequest $request, int $id): RedirectResponse
    {
        // 更新対象の勤怠を取得する
        // 休憩も更新対象なので一緒に読み込む
        $attendance = Attendance::query()
            ->with('attendanceBreaks')
            ->findOrFail($id);

        // 未承認の修正申請が残っている場合は直接修正させない
        $pendingRequestExists = StampCorrectionRequest::query()
            ->where('attendance_id', $attendance->id)
            ->whereNull('approved_at')
            ->exists();

        if ($pendingRequestExists) {
            return redirect()
                ->route('admin.attendance.detail', ['id' => $attendance->id])
                ->with('error', '承認待ちのため修正はできません。');
        }

        // バリデーション済みデータを取得する
        /** @var array{
         *     clock_in_at: string,
         *     clock_out_at: string,
         *     breaks?: array<int, array{
         *         break_start_at?: string|null,
         *         break_end_at?: string|null
         *     }>,
         *     reason: string
         * } $validated
         */
        $validated = $request->validated();

        // 本体更新と履歴保存は必ずセットで扱いたいのでトランザクションにする
        DB::transaction(function () use ($attendance, $validated): void {
            // 勤怠日の形式を Y-m-d にそろえる
            $workDate = $this->formatWorkDate($attendance->work_date);

            // 修正後の出勤日時を作る
            $updatedClockInAt = $this->buildDateTime(
                $workDate,
                $validated['clock_in_at']
            );

            // 修正後の退勤日時を作る
            $updatedClockOutAt = $this->buildDateTime(
                $workDate,
                $validated['clock_out_at']
            );

            // 勤怠本体を直接更新する
            $attendance->update([
                'clock_in_at' => $updatedClockInAt,
                'clock_out_at' => $updatedClockOutAt,
            ]);

            // 本体の既存休憩を一旦すべて削除する
            $attendance->attendanceBreaks()->delete();

            // 入力内容から本体休憩登録用データを作る
            $attendanceBreakRows = $this->buildAttendanceBreakRows(
                $workDate,
                $validated['breaks'] ?? []
            );

            // 休憩行がある場合だけ再登録する
            if ($attendanceBreakRows !== []) {
                $attendance->attendanceBreaks()->createMany($attendanceBreakRows);
            }

            // 管理者が直接修正した履歴を作成する
            $stampCorrectionRequest = StampCorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'requested_clock_in_at' => $updatedClockInAt,
                'requested_clock_out_at' => $updatedClockOutAt,
                'reason' => $validated['reason'],
                'approved_at' => now(),
            ]);

            // 入力内容から修正履歴の休憩データを作る
            $stampCorrectionBreakRows = $this->buildStampCorrectionBreakRows(
                $workDate,
                $validated['breaks'] ?? []
            );

            // 履歴側の休憩行がある場合だけ登録する
            if ($stampCorrectionBreakRows !== []) {
                $stampCorrectionRequest->stampCorrectionBreaks()->createMany($stampCorrectionBreakRows);
            }
        });

        // 更新後は対象日の勤怠一覧画面へ戻す
        $targetDate = $attendance->work_date instanceof Carbon
            ? $attendance->work_date->toDateString()
            : Carbon::parse($attendance->work_date)->toDateString();

        return redirect()->route('admin.attendance.list', [
            'date' => $targetDate,
        ]);
    }

    /**
     * 勤怠日の文字列を Y-m-d 形式にそろえる
     *
     * @param \Carbon\Carbon|string $workDate
     */
    private function formatWorkDate($workDate): string
    {
        // すでに Carbon ならそのまま format する
        if ($workDate instanceof Carbon) {
            return $workDate->format('Y-m-d');
        }

        // 文字列なら Carbon に変換してから format する
        return Carbon::parse($workDate)->format('Y-m-d');
    }

    /**
     * 日付と時刻文字列を結合して datetime 文字列を作る
     */
    private function buildDateTime(string $workDate, string $time): string
    {
        // 例: 2026-04-05 + 09:00 → 2026-04-05 09:00:00
        return $workDate . ' ' . $time . ':00';
    }

    /**
     * attendance_breaks 登録用データを作る
     *
     * @param array<int, array{
     *     break_start_at?: string|null,
     *     break_end_at?: string|null
     * }> $breaks
     * @return array<int, array{
     *     break_start_at: string,
     *     break_end_at: string
     * }>
     */
    private function buildAttendanceBreakRows(string $workDate, array $breaks): array
    {
        // 登録用配列の初期値を用意する
        $rows = [];

        // 入力された休憩行を順番に確認する
        foreach ($breaks as $break) {
            // 休憩開始時刻を取り出す
            $breakStartAt = $break['break_start_at'] ?? null;

            // 休憩終了時刻を取り出す
            $breakEndAt = $break['break_end_at'] ?? null;

            // 両方空の行は未入力行として無視する
            if (empty($breakStartAt) && empty($breakEndAt)) {
                continue;
            }

            // 本体休憩テーブルに登録できる形へ整形して追加する
            $rows[] = [
                'break_start_at' => $this->buildDateTime($workDate, $breakStartAt),
                'break_end_at' => $this->buildDateTime($workDate, $breakEndAt),
            ];
        }

        // 生成した配列を返す
        return $rows;
    }

    /**
     * stamp_correction_breaks 登録用データを作る
     *
     * @param array<int, array{
     *     break_start_at?: string|null,
     *     break_end_at?: string|null
     * }> $breaks
     * @return array<int, array{
     *     requested_break_start_at: string,
     *     requested_break_end_at: string
     * }>
     */
    private function buildStampCorrectionBreakRows(string $workDate, array $breaks): array
    {
        // 履歴登録用配列の初期値を用意する
        $rows = [];

        // 入力された休憩行を順番に確認する
        foreach ($breaks as $break) {
            // 休憩開始時刻を取り出す
            $breakStartAt = $break['break_start_at'] ?? null;

            // 休憩終了時刻を取り出す
            $breakEndAt = $break['break_end_at'] ?? null;

            // 両方空の行は未入力行として無視する
            if (empty($breakStartAt) && empty($breakEndAt)) {
                continue;
            }

            // 修正履歴テーブルに登録できる形へ整形して追加する
            $rows[] = [
                'requested_break_start_at' => $this->buildDateTime($workDate, $breakStartAt),
                'requested_break_end_at' => $this->buildDateTime($workDate, $breakEndAt),
            ];
        }

        // 生成した配列を返す
        return $rows;
    }
}
