<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 勤怠詳細情報修正機能（一般ユーザー） 申請フロー
class Case011CorrectionRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 勤怠データは入れず、ユーザーだけ作成する
        $this->seed(UserSeeder::class);
    }

    // 修正申請処理が実行されることを確認するテスト
    public function test_correction_request_is_created_and_is_shown_on_admin_pages(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(4),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 一般ユーザーで修正申請を送信する
        $response = $this->actingAs($user)->post(
            route('attendance.request.store', ['id' => $attendance->id]),
            $this->correctionRequestData([
                'clock_in_at' => '09:30',
                'clock_out_at' => '18:30',
                'breaks' => [
                    [
                        'break_start_at' => '12:30',
                        'break_end_at' => '13:15',
                    ],
                ],
                'reason' => '電車遅延のため修正申請',
            ])
        );

        // 申請後は勤怠詳細画面へ戻ることを確認する
        $response->assertRedirect(route('attendance.detail', ['id' => $attendance->id]));

        // 修正申請本体が作成されたことを確認する
        $this->assertDatabaseHas('stamp_correction_requests', [
            'attendance_id' => $attendance->id,
            'requested_clock_in_at' => $this->formatWorkDate($attendance) . ' 09:30:00',
            'requested_clock_out_at' => $this->formatWorkDate($attendance) . ' 18:30:00',
            'reason' => '電車遅延のため修正申請',
            'approved_at' => null,
        ]);

        // 作成された修正申請を取得する
        $stampCorrectionRequest = StampCorrectionRequest::query()
            ->where('attendance_id', $attendance->id)
            ->firstOrFail();

        // 修正申請に紐づく休憩修正データが作成されたことを確認する
        $this->assertDatabaseHas('stamp_correction_breaks', [
            'stamp_correction_request_id' => $stampCorrectionRequest->id,
            'requested_break_start_at' => $this->formatWorkDate($attendance) . ' 12:30:00',
            'requested_break_end_at' => $this->formatWorkDate($attendance) . ' 13:15:00',
        ]);

        // 管理者で申請一覧画面を開く
        $requestListResponse = $this->actingAs($admin)
            ->get(route('stamp_correction_request.list'));

        // 管理者の申請一覧画面が正常に表示されることを確認する
        $requestListResponse->assertOk();

        // 管理者の申請一覧に申請内容が表示されることを確認する
        $requestListResponse->assertSeeText('承認待ち');
        $requestListResponse->assertSeeText($user->name);
        $requestListResponse->assertSeeText(Carbon::parse($attendance->work_date)->format('Y/m/d'));
        $requestListResponse->assertSeeText('電車遅延のため修正申請');

        // 承認画面へのリンクが表示されていることを確認する
        $requestListResponse->assertSee(
            route('admin.stamp_correction_request.approve', ['id' => $stampCorrectionRequest->id]),
            false
        );

        // 管理者で承認画面を開く
        $approvalPageResponse = $this->actingAs($admin)
            ->get(route('admin.stamp_correction_request.approve', ['id' => $stampCorrectionRequest->id]));

        // 承認画面が正常に表示されることを確認する
        $approvalPageResponse->assertOk();

        // 承認画面に申請内容が表示されることを確認する
        $approvalPageResponse->assertSeeText($user->name);
        $approvalPageResponse->assertSeeText(Carbon::parse($attendance->work_date)->format('Y年'));
        $approvalPageResponse->assertSeeText(Carbon::parse($attendance->work_date)->format('n月j日'));
        $approvalPageResponse->assertSeeText('09:30');
        $approvalPageResponse->assertSeeText('18:30');
        $approvalPageResponse->assertSeeText('12:30');
        $approvalPageResponse->assertSeeText('13:15');
        $approvalPageResponse->assertSeeText('電車遅延のため修正申請');
        $approvalPageResponse->assertSeeText('承認');
    }

    // 「承認待ち」にログインユーザーが行った申請が全て表示されていることを確認するテスト
    public function test_pending_requests_list_shows_all_requests_made_by_logged_in_user(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 比較用の別一般ユーザーを取得する
        $anotherUser = $this->findAnotherGeneralUser($user->id);

        // 自分の勤怠を2件作成する
        $firstAttendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(5),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        $secondAttendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(6),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 他ユーザーの勤怠も1件作成する
        $otherAttendance = $this->createAttendanceWithBreak(
            userId: $anotherUser->id,
            workDate: now()->copy()->startOfMonth()->addDays(7),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 自分の修正申請を2件、実際の保存処理で作成する
        $firstRequest = $this->submitCorrectionRequest($user, $firstAttendance, [
            'reason' => '1件目の修正申請',
        ]);

        $secondRequest = $this->submitCorrectionRequest($user, $secondAttendance, [
            'reason' => '2件目の修正申請',
        ]);

        // 他ユーザーの申請も1件作成する
        $otherRequest = $this->submitCorrectionRequest($anotherUser, $otherAttendance, [
            'reason' => '他ユーザーの修正申請',
        ]);

        // 自分の承認待ち一覧を開く
        $response = $this->actingAs($user)->get(route('stamp_correction_request.list'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 自分が行った承認待ち申請が表示されることを確認する
        $response->assertSeeText($firstRequest->reason);
        $response->assertSeeText($secondRequest->reason);

        // 他ユーザーの申請は表示されないことを確認する
        $response->assertDontSeeText($otherRequest->reason);
    }

    // 「承認済み」に管理者が承認した修正申請が全て表示されていることを確認するテスト
    public function test_approved_requests_list_shows_all_requests_approved_by_admin(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 比較用の別一般ユーザーを取得する
        $anotherUser = $this->findAnotherGeneralUser($user->id);

        // 自分の勤怠を3件作成する
        $firstAttendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(7),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        $secondAttendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(8),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        $pendingAttendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(9),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 他ユーザーの勤怠も1件作成する
        $otherAttendance = $this->createAttendanceWithBreak(
            userId: $anotherUser->id,
            workDate: now()->copy()->startOfMonth()->addDays(10),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 自分の申請を3件作成する
        $firstRequest = $this->submitCorrectionRequest($user, $firstAttendance, [
            'reason' => '1件目の承認済み申請',
        ]);

        $secondRequest = $this->submitCorrectionRequest($user, $secondAttendance, [
            'reason' => '2件目の承認済み申請',
        ]);

        $pendingRequest = $this->submitCorrectionRequest($user, $pendingAttendance, [
            'reason' => '未承認の申請',
        ]);

        // 他ユーザーの申請も1件作成する
        $otherApprovedRequest = $this->submitCorrectionRequest($anotherUser, $otherAttendance, [
            'reason' => '他ユーザーの承認済み申請',
        ]);

        // 管理者で自分の2件と他ユーザー1件を承認する
        $this->approveCorrectionRequest($admin, $firstRequest);
        $this->approveCorrectionRequest($admin, $secondRequest);
        $this->approveCorrectionRequest($admin, $otherApprovedRequest);

        // 一般ユーザーで承認済み一覧を開く
        $response = $this->actingAs($user)->get(
            route('stamp_correction_request.list', ['status' => 'approved'])
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 自分の承認済み申請が表示されることを確認する
        $response->assertSeeText($firstRequest->reason);
        $response->assertSeeText($secondRequest->reason);

        // 未承認の申請は表示されないことを確認する
        $response->assertDontSeeText($pendingRequest->reason);

        // 他ユーザーの承認済み申請は表示されないことを確認する
        $response->assertDontSeeText($otherApprovedRequest->reason);
    }

    // 各申請の「詳細」を押下すると勤怠詳細画面に遷移することを確認するテスト
    public function test_detail_button_on_request_list_redirects_to_attendance_detail_page(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(11),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 実際に修正し保存して申請を作成する
        $request = $this->submitCorrectionRequest($user, $attendance, [
            'clock_in_at' => '09:30',
            'clock_out_at' => '18:30',
            'breaks' => [
                [
                    'break_start_at' => '12:30',
                    'break_end_at' => '13:15',
                ],
            ],
            'reason' => '詳細画面遷移確認用の申請',
        ]);

        // 申請一覧画面を開く
        $listResponse = $this->actingAs($user)->get(route('stamp_correction_request.list'));

        // 一覧画面が正常に表示されることを確認する
        $listResponse->assertOk();

        // 一覧に申請内容と詳細リンクが表示されていることを確認する
        $listResponse->assertSeeText($request->reason);
        $listResponse->assertSeeText('詳細');
        $listResponse->assertSee(route('attendance.detail', ['id' => $attendance->id]), false);

        // 「詳細」押下先の勤怠詳細画面を開く
        $detailResponse = $this->actingAs($user)->get(
            route('attendance.detail', ['id' => $attendance->id])
        );

        // 勤怠詳細画面が正常に表示されることを確認する
        $detailResponse->assertOk();

        // 遷移先が対象勤怠の詳細画面であることを確認する
        $detailResponse->assertSeeText($user->name);
        $detailResponse->assertSeeText(Carbon::parse($attendance->work_date)->format('Y年'));
        $detailResponse->assertSeeText(Carbon::parse($attendance->work_date)->format('n月j日'));
    }

    // シーダーで投入した一般ユーザーを取得する
    private function findGeneralUser(): User
    {
        return User::query()
            ->where('role', User::ROLE_USER)
            ->orderBy('id')
            ->firstOrFail();
    }

    // シーダーで投入した管理者ユーザーを取得する
    private function findAdminUser(): User
    {
        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->orderBy('id')
            ->firstOrFail();
    }

    // 指定した一般ユーザー以外の一般ユーザーを取得する
    private function findAnotherGeneralUser(int $exceptUserId): User
    {
        return User::query()
            ->where('role', User::ROLE_USER)
            ->where('id', '!=', $exceptUserId)
            ->orderBy('id')
            ->firstOrFail();
    }

    // 勤怠と休憩を1件まとめて作成する
    private function createAttendanceWithBreak(
        int $userId,
        Carbon $workDate,
        string $clockInAt,
        string $clockOutAt,
        string $breakStartAt,
        string $breakEndAt
    ): Attendance {
        // 勤怠本体を作成する
        $attendance = Attendance::query()->create([
            'user_id' => $userId,
            'work_date' => $workDate->toDateString(),
            'clock_in_at' => $workDate->format('Y-m-d') . ' ' . $clockInAt . ':00',
            'clock_out_at' => $workDate->format('Y-m-d') . ' ' . $clockOutAt . ':00',
        ]);

        // 休憩データを1件作成する
        AttendanceBreak::query()->create([
            'attendance_id' => $attendance->id,
            'break_start_at' => $workDate->format('Y-m-d') . ' ' . $breakStartAt . ':00',
            'break_end_at' => $workDate->format('Y-m-d') . ' ' . $breakEndAt . ':00',
        ]);

        return $attendance;
    }

    // 修正申請のリクエストデータを返す
    private function correctionRequestData(array $overrides = []): array
    {
        // 通常の正常系データをベースにして、必要な項目だけ上書きする
        $defaultData = [
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'breaks' => [
                [
                    'break_start_at' => '12:00',
                    'break_end_at' => '13:00',
                ],
            ],
            'reason' => '電車遅延のため修正申請',
        ];

        return array_replace_recursive($defaultData, $overrides);
    }

    // 勤怠日を Y-m-d 形式で返す
    private function formatWorkDate(Attendance $attendance): string
    {
        return Carbon::parse($attendance->work_date)->toDateString();
    }

    // 実際の保存処理で修正申請を作成して返す
    private function submitCorrectionRequest(
        User $user,
        Attendance $attendance,
        array $overrides = []
    ): StampCorrectionRequest {
        // 一般ユーザーで修正申請を送信する
        $response = $this->actingAs($user)->post(
            route('attendance.request.store', ['id' => $attendance->id]),
            $this->correctionRequestData($overrides)
        );

        // 送信後は勤怠詳細画面へ戻ることを確認する
        $response->assertRedirect(route('attendance.detail', ['id' => $attendance->id]));

        // 作成された最新の修正申請を返す
        return StampCorrectionRequest::query()
            ->where('attendance_id', $attendance->id)
            ->latest('id')
            ->firstOrFail();
    }

    // 管理者で修正申請を承認する
    private function approveCorrectionRequest(User $admin, StampCorrectionRequest $stampCorrectionRequest): void
    {
        // 管理者で承認処理を実行する
        $response = $this->actingAs($admin)->patch(
            route('admin.stamp_correction_request.approve.update', [
                'id' => $stampCorrectionRequest->id,
            ])
        );

        // 承認後は承認画面へ戻ることを確認する
        $response->assertRedirect(
            route('admin.stamp_correction_request.approve', [
                'id' => $stampCorrectionRequest->id,
            ])
        );

        // 承認済み日時が入ったことを確認する
        $this->assertNotNull($stampCorrectionRequest->fresh()?->approved_at);
    }
}
