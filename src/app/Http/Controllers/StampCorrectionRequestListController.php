<?php

namespace App\Http\Controllers;

use App\Models\StampCorrectionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StampCorrectionRequestListController extends Controller
{
    // 申請一覧画面を表示する
    public function index(): View
    {
        // ログイン中ユーザーを取得する
        /** @var \App\Models\User $loginUser */
        $loginUser = Auth::user();

        // 現在表示するステータスタブを取得する
        // 指定がなければ「承認待ち」を初期表示にする
        $currentStatus = request('status', 'pending');

        // 想定外の値が来た場合は承認待ちに戻す
        if (!in_array($currentStatus, ['pending', 'approved'], true)) {
            $currentStatus = 'pending';
        }

        // 修正申請一覧の取得クエリを作る
        // 一覧表示で使う勤怠情報とユーザー情報を一緒に読み込む
        $query = StampCorrectionRequest::query()
            ->with('attendance.user');

        // 一般ユーザーは自分に紐づく申請だけ表示する
        if ($loginUser->isUser()) {
            $query->whereHas('attendance', function ($query) use ($loginUser) {
                $query->where('user_id', $loginUser->id);
            });
        } else {
            // 管理者は一般ユーザーに紐づく申請だけ表示する
            $query->whereHas('attendance.user', function ($query) {
                $query->where('role', User::ROLE_USER);
            });
        }

        // タブの状態に応じて承認待ち / 承認済み を切り替える
        if ($currentStatus === 'approved') {
            $query->whereNotNull('approved_at');
        } else {
            $query->whereNull('approved_at');
        }

        // 新しい申請順で取得する
        $stampCorrectionRequests = $query
            ->latest('created_at')
            ->get();

        // Blade で扱いやすい表示用データに整形する
        $requests = $stampCorrectionRequests->map(function (
            StampCorrectionRequest $stampCorrectionRequest
        ) use ($loginUser): array {
            $attendance = $stampCorrectionRequest->attendance;
            $attendanceUser = $attendance?->user;

            // 対象勤怠日を表示用に整形する
            $workDate = $attendance
                ? $this->formatWorkDate($attendance->work_date)
                : '';

            // 申請日時を表示用に整形する
            $appliedAt = $stampCorrectionRequest->created_at
                ? $stampCorrectionRequest->created_at->format('Y/m/d')
                : '';

            // 一般ユーザーは勤怠詳細画面へ遷移する
            // 管理者は修正申請承認画面へ遷移する
            $detailUrl = $loginUser->isUser()
                ? route('attendance.detail', ['id' => $stampCorrectionRequest->attendance_id])
                : route('admin.stamp_correction_request.approve', ['id' => $stampCorrectionRequest->id]);

            return [
                // 承認状態ラベル
                'statusLabel' => is_null($stampCorrectionRequest->approved_at)
                    ? '承認待ち'
                    : '承認済み',

                // 申請者名
                'userName' => $attendanceUser?->name ?? '',

                // 対象日時
                'workDate' => $workDate,

                // 申請理由
                'reason' => $stampCorrectionRequest->reason,

                // 申請日時
                'appliedAt' => $appliedAt,

                // 詳細リンク
                'detailUrl' => $detailUrl,
            ];
        });

        return view('stamp_correction_requests.index', [
            'currentStatus' => $currentStatus,
            'requests' => $requests,
        ]);
    }

    /**
     * 対象勤怠日を Y/m/d 形式に整形する
     *
     * @param \Carbon\Carbon|string $workDate
     */
    private function formatWorkDate($workDate): string
    {
        // Carbon インスタンスならそのまま整形する
        if ($workDate instanceof Carbon) {
            return $workDate->format('Y/m/d');
        }

        // 文字列なら Carbon に変換して整形する
        return Carbon::parse($workDate)->format('Y/m/d');
    }
}
