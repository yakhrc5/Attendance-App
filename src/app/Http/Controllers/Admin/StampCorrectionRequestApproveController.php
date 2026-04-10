<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StampCorrectionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StampCorrectionRequestApproveController extends Controller
{
    /**
     * 管理者用 修正申請承認画面
     */
    public function show(int $id): View
    {
        // 対象の修正申請を取得する
        // 申請者情報、元の勤怠情報、申請休憩情報をまとめて読み込む
        $stampCorrectionRequest = StampCorrectionRequest::query()
            ->with([
                'user',
                'attendance',
                'stampCorrectionBreaks' => fn($query) => $query->orderBy('requested_break_start_at'),
            ])
            ->findOrFail($id);

        // この申請が承認可能かどうかを判定する
        $canApprove = $this->canApprove($stampCorrectionRequest);

        // 承認画面を表示する
        return view('admin.stamp_correction_requests.approve', [
            'stampCorrectionRequest' => $stampCorrectionRequest,
            'canApprove' => $canApprove,
        ]);
    }

    /**
     * 修正申請を承認する
     */
    public function update(int $id): RedirectResponse
    {
        // 対象の修正申請を取得する
        // 承認時に勤怠本体へ反映するので、勤怠本体と申請休憩も一緒に読み込む
        $stampCorrectionRequest = StampCorrectionRequest::query()
            ->with([
                'attendance.attendanceBreaks',
                'stampCorrectionBreaks' => fn($query) => $query->orderBy('requested_break_start_at'),
            ])
            ->findOrFail($id);

        // 承認対象外なら画面へ戻す
        // - すでに承認済み
        // - 管理者直接修正の履歴
        if (!$this->canApprove($stampCorrectionRequest)) {
            return redirect()
                ->route('admin.stamp_correction_request.approve', [
                    'id' => $stampCorrectionRequest->id,
                ]);
        }

        // 勤怠本体更新と承認済み更新は必ずセットで扱いたいのでトランザクションにする
        DB::transaction(function () use ($stampCorrectionRequest): void {
            // 紐づく勤怠本体を取得する
            $attendance = $stampCorrectionRequest->attendance;

            // 勤怠本体を申請内容で更新する
            $attendance->update([
                'clock_in_at' => $stampCorrectionRequest->requested_clock_in_at,
                'clock_out_at' => $stampCorrectionRequest->requested_clock_out_at,
            ]);

            // 本体の既存休憩を一旦すべて削除する
            $attendance->attendanceBreaks()->delete();

            // 申請休憩を本体休憩登録用データに変換する
            $attendanceBreakRows = $this->buildAttendanceBreakRows($stampCorrectionRequest);

            // 申請休憩がある場合だけ本体へ再登録する
            if ($attendanceBreakRows !== []) {
                $attendance->attendanceBreaks()->createMany($attendanceBreakRows);
            }

            // 申請を承認済みに更新する
            $stampCorrectionRequest->update([
                'approved_by_admin_id' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        // 承認後は同じ承認画面へ戻す
        return redirect()
            ->route('admin.stamp_correction_request.approve', [
                'id' => $stampCorrectionRequest->id,
            ])
            ->with('success', '修正申請を承認しました。');
    }

    /**
     * この申請が承認可能かどうかを判定する
     */
    private function canApprove(StampCorrectionRequest $stampCorrectionRequest): bool
    {
        // 一般ユーザー申請で、かつ未承認のものだけ承認対象にする
        return $stampCorrectionRequest->request_source === StampCorrectionRequest::SOURCE_USER_REQUEST
            && is_null($stampCorrectionRequest->approved_at);
    }

    /**
     * 申請休憩を attendance_breaks 登録用データへ変換する
     *
     * @return array<int, array{
     *     break_start_at: \Carbon\Carbon|string,
     *     break_end_at: \Carbon\Carbon|string
     * }>
     */
    private function buildAttendanceBreakRows(StampCorrectionRequest $stampCorrectionRequest): array
    {
        // 登録用配列の初期値を用意する
        $rows = [];

        // 申請休憩を順番に確認する
        foreach ($stampCorrectionRequest->stampCorrectionBreaks as $stampCorrectionBreak) {
            // 申請された休憩開始時刻を取得する
            $requestedBreakStartAt = $stampCorrectionBreak->requested_break_start_at;

            // 申請された休憩終了時刻を取得する
            $requestedBreakEndAt = $stampCorrectionBreak->requested_break_end_at;

            // 両方空なら無視する
            if (empty($requestedBreakStartAt) && empty($requestedBreakEndAt)) {
                continue;
            }

            // attendance_breaks テーブルに登録できる形へ整形して追加する
            $rows[] = [
                'break_start_at' => $requestedBreakStartAt,
                'break_end_at' => $requestedBreakEndAt,
            ];
        }

        // 生成した配列を返す
        return $rows;
    }
}
