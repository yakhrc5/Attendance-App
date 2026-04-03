<?php

namespace App\Http\Controllers;

use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StampCorrectionRequestListController extends Controller
{
    // 一般ユーザーの申請一覧画面を表示する
    public function index(Request $request): View
    {
        // タブは pending / approved の2種類だけ受け付ける
        $currentStatus = $request->query('status', 'pending');

        if (!in_array($currentStatus, ['pending', 'approved'], true)) {
            $currentStatus = 'pending';
        }

        // ログインユーザー本人の申請だけを取得する
        $requestsQuery = StampCorrectionRequest::query()
            ->with('attendance')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at');

        // タブに応じて未承認 / 承認済みを切り替える
        if ($currentStatus === 'pending') {
            $requestsQuery->whereNull('approved_at');
        } else {
            $requestsQuery->whereNotNull('approved_at');
        }

        $userName = Auth::user()->name;

        // 画面で使いやすい表示用データに整える
        $requests = $requestsQuery->get()->map(function (StampCorrectionRequest $stampCorrectionRequest) use ($userName): array {
            $workDate = '';

            if (!empty($stampCorrectionRequest->attendance?->work_date)) {
                $workDate = Carbon::parse($stampCorrectionRequest->attendance->work_date)->format('Y/m/d');
            }

            return [
                'statusLabel' => is_null($stampCorrectionRequest->approved_at) ? '承認待ち' : '承認済み',
                'userName' => $userName,
                'workDate' => $workDate,
                'reason' => $stampCorrectionRequest->reason,
                'appliedAt' => optional($stampCorrectionRequest->created_at)->format('Y/m/d'),
                'detailUrl' => !empty($stampCorrectionRequest->attendance_id)
                    ? route('attendance.detail', ['id' => $stampCorrectionRequest->attendance_id])
                    : '',
            ];
        });

        return view('stamp_correction_requests.index', [
            'currentStatus' => $currentStatus,
            'requests' => $requests,
        ]);
    }
}
