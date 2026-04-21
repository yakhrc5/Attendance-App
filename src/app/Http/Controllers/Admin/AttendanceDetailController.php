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
        $attendance = $this->findAttendance($id);

        // この勤怠に紐づく未承認の修正申請を取得する
        $pendingCorrectionRequest = $this->findPendingCorrectionRequest($attendance);

        // 承認待ち申請があるかどうかを真偽値で持たせる
        $isPending = !is_null($pendingCorrectionRequest);

        return view('admin.attendance.detail', [
            'attendance' => $attendance,
            'workDate' => Carbon::parse($attendance->work_date)->locale('ja'),
            'isPending' => $isPending,
            'clockInValue' => $this->formatTime($attendance->clock_in_at),
            'clockOutValue' => $this->formatTime($attendance->clock_out_at),
            'editableBreakRows' => $this->buildEditableBreakRows($attendance, $isPending),
            'pendingCorrectionRequest' => $pendingCorrectionRequest,
            'pendingClockInValue' => $this->formatTime($pendingCorrectionRequest?->requested_clock_in_at),
            'pendingClockOutValue' => $this->formatTime($pendingCorrectionRequest?->requested_clock_out_at),
            'pendingBreakRows' => $this->buildPendingBreakRows($pendingCorrectionRequest),
        ]);
    }

    // 管理者が閲覧する勤怠を取得する
    private function findAttendance(int $id): Attendance
    {
        return Attendance::query()
            ->with([
                'user',
                'attendanceBreaks' => function ($query) {
                    // 休憩は表示順が崩れないように ID 昇順で取得する
                    $query->orderBy('id', 'asc');
                },
            ])
            ->findOrFail($id);
    }

    // 未承認の修正申請を取得する
    private function findPendingCorrectionRequest(Attendance $attendance): ?StampCorrectionRequest
    {
        return StampCorrectionRequest::query()
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
    }

    // 編集可能画面で使う休憩行データを作る
    private function buildEditableBreakRows(Attendance $attendance, bool $isPending): array
    {
        // 承認待ち画面では使わない
        if ($isPending) {
            return [];
        }

        // バリデーションエラー後は old() の入力値を優先する
        $editableBreakRows = collect(old('breaks', []))->map(function (array $breakRow): array {
            return [
                'break_start_at' => $breakRow['break_start_at'] ?? '',
                'break_end_at' => $breakRow['break_end_at'] ?? '',
            ];
        });

        // 初回表示時は現在の勤怠休憩データを使う
        if ($editableBreakRows->isEmpty()) {
            $editableBreakRows = $attendance->attendanceBreaks->map(function ($attendanceBreak): array {
                return [
                    'break_start_at' => $this->formatTime($attendanceBreak->break_start_at),
                    'break_end_at' => $this->formatTime($attendanceBreak->break_end_at),
                ];
            });
        }

        // 最後の行に入力があるときだけ空行を 1 つ追加する
        $lastBreakRow = $editableBreakRows->last();
        $hasNoRows = $editableBreakRows->isEmpty();

        $lastRowHasInput = ! $hasNoRows && (
            ! empty($lastBreakRow['break_start_at']) ||
            ! empty($lastBreakRow['break_end_at'])
        );

        if ($hasNoRows || $lastRowHasInput) {
            $editableBreakRows->push([
                'break_start_at' => '',
                'break_end_at' => '',
            ]);
        }

        return $editableBreakRows->values()->all();
    }

    // 承認待ち画面で使う休憩行データを作る
    private function buildPendingBreakRows(?StampCorrectionRequest $pendingCorrectionRequest): array
    {
        if (is_null($pendingCorrectionRequest)) {
            return [];
        }

        return $pendingCorrectionRequest->stampCorrectionBreaks
            ->map(function ($stampCorrectionBreak): array {
                return [
                    'break_start_at' => $this->formatTime($stampCorrectionBreak->requested_break_start_at),
                    'break_end_at' => $this->formatTime($stampCorrectionBreak->requested_break_end_at),
                ];
            })
            ->values()
            ->all();
    }

    // 時刻を H:i 形式に整える
    private function formatTime($dateTime): string
    {
        if (empty($dateTime)) {
            return '';
        }

        return Carbon::parse($dateTime)->format('H:i');
    }

    // 管理者用 勤怠直接修正処理
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
                ->route('admin.attendance.detail', ['id' => $attendance->id]);
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

    // 日付と時刻文字列を結合して datetime 文字列を作る
    private function buildDateTime(string $workDate, string $time): string
    {
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
        $rows = [];

        foreach ($breaks as $break) {
            $breakStartAt = $break['break_start_at'] ?? null;
            $breakEndAt = $break['break_end_at'] ?? null;

            // 両方空の行は未入力行として無視する
            if (empty($breakStartAt) && empty($breakEndAt)) {
                continue;
            }

            $rows[] = [
                'break_start_at' => $this->buildDateTime($workDate, $breakStartAt),
                'break_end_at' => $this->buildDateTime($workDate, $breakEndAt),
            ];
        }

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
        $rows = [];

        foreach ($breaks as $break) {
            $breakStartAt = $break['break_start_at'] ?? null;
            $breakEndAt = $break['break_end_at'] ?? null;

            // 両方空の行は未入力行として無視する
            if (empty($breakStartAt) && empty($breakEndAt)) {
                continue;
            }

            $rows[] = [
                'requested_break_start_at' => $this->buildDateTime($workDate, $breakStartAt),
                'requested_break_end_at' => $this->buildDateTime($workDate, $breakEndAt),
            ];
        }

        return $rows;
    }
}
