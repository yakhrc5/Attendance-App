<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceDetailController extends Controller
{
    // ログインユーザーIDを取得する
    private function currentUserId(): int
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user->id;
    }

    // 勤怠詳細画面を表示する
    public function show(int $id): View
    {
        // ログインユーザー本人の勤怠だけ取得する
        $attendance = $this->findAttendance($id);

        // この勤怠に紐づく未承認の修正申請を取得する
        $pendingCorrectionRequest = $this->findPendingCorrectionRequest($attendance);

        // 承認待ち申請があるかどうかを真偽値で持たせる
        $isPending = !is_null($pendingCorrectionRequest);

        return view('attendance.detail', [
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

    // ログインユーザー本人の勤怠を取得する
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
            ->where('id', $id)
            ->where('user_id', $this->currentUserId())
            ->firstOrFail();
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

        // 最後の行に入力があるときだけ空行を1つ追加する
        $lastBreakRow = $editableBreakRows->last();
        $hasNoRows = $editableBreakRows->isEmpty();

        $lastRowHasInput = !$hasNoRows && (
            !empty($lastBreakRow['break_start_at']) ||
            !empty($lastBreakRow['break_end_at'])
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
}
