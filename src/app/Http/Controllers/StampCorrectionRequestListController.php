<?php

namespace App\Http\Controllers;

use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StampCorrectionRequestListController extends Controller
{
    /**
     * 申請一覧画面
     * - 一般ユーザー: 自分が行った修正申請のみ表示する
     * - 管理者: 全一般ユーザーの修正申請のみ表示する
     */
    public function index(): View
    {
        // ログイン中ユーザーを取得する
        $loginUser = Auth::user();

        // 現在表示するステータスタブを取得する
        // 指定がなければ「承認待ち」を初期表示にする
        $currentStatus = request('status', 'pending');

        // 修正申請一覧の取得クエリを作る
        // 一覧表示で使うユーザー情報と勤怠情報を一緒に読み込む
        // 申請一覧には「一般ユーザーが出した申請」だけを表示対象にする
        $query = StampCorrectionRequest::query()
            ->with([
                'user',
                'attendance',
            ])
            ->where('request_source', StampCorrectionRequest::SOURCE_USER_REQUEST);

        // 一般ユーザーは自分の申請だけ表示する
        if ($loginUser->role === 'user') {
            $query->where('user_id', $loginUser->id);
        }

        // タブの状態に応じて承認待ち / 承認済み を切り替える
        if ($currentStatus === 'approved') {
            $query->whereNotNull('approved_at');
        } else {
            $query->whereNull('approved_at');
        }

        // 新しい申請順で取得する
        $stampCorrectionRequests = $query
            ->latest()
            ->get();

        // Blade で扱いやすい表示用データに整形する
        $requests = $stampCorrectionRequests->map(function (
            StampCorrectionRequest $stampCorrectionRequest
        ) use ($loginUser): array {
            // 対象勤怠日を表示用に整形する
            $workDate = $stampCorrectionRequest->attendance
                ? $this->formatWorkDate($stampCorrectionRequest->attendance->work_date)
                : '';

            // 申請日時を表示用に整形する
            $appliedAt = $stampCorrectionRequest->created_at
                ? $stampCorrectionRequest->created_at->format('Y/m/d')
                : '';

            // 一般ユーザーは既存の勤怠詳細画面へ遷移する
            if ($loginUser->role === 'user') {
                $detailUrl = route('attendance.detail', [
                    'id' => $stampCorrectionRequest->attendance_id,
                ]);
            } else {
                // 管理者は修正申請承認画面へ遷移する
                $detailUrl = route('admin.stamp_correction_request.approve', [
                    'id' => $stampCorrectionRequest->id,
                ]);
            }

            return [
                // 承認状態ラベル
                'statusLabel' => is_null($stampCorrectionRequest->approved_at)
                    ? '承認待ち'
                    : '承認済み',

                // 申請者名
                'userName' => $stampCorrectionRequest->user->name ?? '',

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

        // 共通 Blade を表示する
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
