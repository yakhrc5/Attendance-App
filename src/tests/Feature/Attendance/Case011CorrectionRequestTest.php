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

// 勤怠詳細情報修正機能（一般ユーザー）
class Case011CorrectionRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 勤怠データは入れず、ユーザーだけ作成する
        $this->seed(UserSeeder::class);
    }

    // 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示されることを確認するテスト
    public function test_validation_message_is_shown_when_clock_in_is_after_clock_out(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth(),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 出勤時間を退勤時間より後にして保存する
        $response = $this->actingAs($user)
            ->from(route('attendance.detail', ['id' => $attendance->id]))
            ->followingRedirects()
            ->post(route('attendance.request.store', ['id' => $attendance->id]), $this->correctionRequestData([
                'clock_in_at' => '19:00',
                'clock_out_at' => '18:00',
            ]));

        // バリデーションメッセージが表示されることを確認する
        $response->assertSeeText('出勤時間もしくは退勤時間が不適切な値です');

        // 修正申請が作成されていないことを確認する
        $this->assertDatabaseCount('stamp_correction_requests', 0);
    }

    // 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示されることを確認するテスト
    public function test_validation_message_is_shown_when_break_start_is_after_clock_out(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDay(),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 休憩開始時間を退勤時間より後にして保存する
        $response = $this->actingAs($user)
            ->from(route('attendance.detail', ['id' => $attendance->id]))
            ->followingRedirects()
            ->post(route('attendance.request.store', ['id' => $attendance->id]), $this->correctionRequestData([
                'breaks' => [
                    [
                        'break_start_at' => '18:30',
                        'break_end_at' => '18:45',
                    ],
                ],
            ]));

        // バリデーションメッセージが表示されることを確認する
        $response->assertSeeText('休憩時間が不適切な値です');

        // 修正申請が作成されていないことを確認する
        $this->assertDatabaseCount('stamp_correction_requests', 0);
    }

    // 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示されることを確認するテスト
    public function test_validation_message_is_shown_when_break_end_is_after_clock_out(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(2),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 休憩終了時間を退勤時間より後にして保存する
        $response = $this->actingAs($user)
            ->from(route('attendance.detail', ['id' => $attendance->id]))
            ->followingRedirects()
            ->post(route('attendance.request.store', ['id' => $attendance->id]), $this->correctionRequestData([
                'breaks' => [
                    [
                        'break_start_at' => '17:30',
                        'break_end_at' => '18:30',
                    ],
                ],
            ]));

        // バリデーションメッセージが表示されることを確認する
        $response->assertSeeText('休憩時間もしくは退勤時間が不適切な値です');

        // 修正申請が作成されていないことを確認する
        $this->assertDatabaseCount('stamp_correction_requests', 0);
    }

    // 備考欄が未入力の場合、エラーメッセージが表示されることを確認するテスト
    public function test_validation_message_is_shown_when_reason_is_empty(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(3),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 備考を空のまま保存する
        $response = $this->actingAs($user)
            ->from(route('attendance.detail', ['id' => $attendance->id]))
            ->followingRedirects()
            ->post(route('attendance.request.store', ['id' => $attendance->id]), $this->correctionRequestData([
                'reason' => '',
            ]));

        // バリデーションメッセージが表示されることを確認する
        $response->assertSeeText('備考を記入してください');

        // 修正申請が作成されていないことを確認する
        $this->assertDatabaseCount('stamp_correction_requests', 0);
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

        // 正常な内容で修正申請を送信する
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

        // 勤怠詳細画面へリダイレクトされることを確認する
        $response->assertRedirect(route('attendance.detail', ['id' => $attendance->id]));

        // 修正申請が作成されたことを確認する
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

        // 休憩修正申請も作成されたことを確認する
        $this->assertDatabaseHas('stamp_correction_breaks', [
            'stamp_correction_request_id' => $stampCorrectionRequest->id,
            'requested_break_start_at' => $this->formatWorkDate($attendance) . ' 12:30:00',
            'requested_break_end_at' => $this->formatWorkDate($attendance) . ' 13:15:00',
        ]);

        // 管理者で申請一覧画面を開く
        $requestListResponse = $this->actingAs($admin)
            ->get(route('stamp_correction_request.list'));

        // 申請一覧画面が正常に表示されることを確認する
        $requestListResponse->assertOk();

        // 申請一覧画面に申請内容が表示されることを確認する
        $requestListResponse->assertSeeText('承認待ち');
        $requestListResponse->assertSeeText($user->name);
        $requestListResponse->assertSeeText(Carbon::parse($attendance->work_date)->format('Y/m/d'));
        $requestListResponse->assertSeeText('電車遅延のため修正申請');

        // 申請一覧画面に承認画面へのリンクが表示されることを確認する
        $requestListResponse->assertSee(
            route('admin.stamp_correction_request.approve', [
                'id' => $stampCorrectionRequest->id,
            ]),
            false
        );

        // 管理者で承認画面を開く
        $approvalPageResponse = $this->actingAs($admin)
            ->get(route('admin.stamp_correction_request.approve', [
                'id' => $stampCorrectionRequest->id,
            ]));

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

        // 1件目の勤怠を作成する
        $firstAttendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(5),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 2件目の勤怠を作成する
        $secondAttendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(6),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 承認待ちの修正申請を2件作成する
        $firstRequest = $this->createStampCorrectionRequest(
            attendance: $firstAttendance,
            reason: '1件目の修正申請',
            approvedAt: null
        );

        $secondRequest = $this->createStampCorrectionRequest(
            attendance: $secondAttendance,
            reason: '2件目の修正申請',
            approvedAt: null
        );

        // 申請一覧画面の承認待ちタブを開く
        $response = $this->actingAs($user)->get(route('stamp_correction_request.list'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 自分が行った申請が全て表示されていることを確認する
        $response->assertSeeText($firstRequest->reason);
        $response->assertSeeText($secondRequest->reason);
    }

    // 「承認済み」に管理者が承認した修正申請が全て表示されていることを確認するテスト
    public function test_approved_requests_list_shows_all_requests_approved_by_admin(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 1件目の勤怠を作成する
        $firstAttendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(7),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 2件目の勤怠を作成する
        $secondAttendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(8),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 承認済みの修正申請を2件作成する
        $firstRequest = $this->createStampCorrectionRequest(
            attendance: $firstAttendance,
            reason: '1件目の承認済み申請',
            approvedAt: now()->copy()->subDay()
        );

        $secondRequest = $this->createStampCorrectionRequest(
            attendance: $secondAttendance,
            reason: '2件目の承認済み申請',
            approvedAt: now()
        );

        // 申請一覧画面の承認済みタブを開く
        $response = $this->actingAs($user)->get(
            route('stamp_correction_request.list', ['status' => 'approved'])
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 管理者が承認した申請が全て表示されていることを確認する
        $response->assertSeeText($firstRequest->reason);
        $response->assertSeeText($secondRequest->reason);
    }

    // 各申請の「詳細」を押下すると勤怠詳細画面に遷移することを確認するテスト
    public function test_detail_button_on_request_list_redirects_to_attendance_detail_page(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(9),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 承認待ちの修正申請を作成する
        $this->createStampCorrectionRequest(
            attendance: $attendance,
            reason: '詳細画面遷移確認用の申請',
            approvedAt: null
        );

        // 申請一覧画面の承認待ちタブを開く
        $listResponse = $this->actingAs($user)->get(route('stamp_correction_request.list'));

        // 一覧画面に勤怠詳細画面へのリンクが表示されていることを確認する
        $listResponse->assertSee(route('attendance.detail', ['id' => $attendance->id]), false);

        // 勤怠詳細画面を開く
        $detailResponse = $this->actingAs($user)->get(route('attendance.detail', ['id' => $attendance->id]));

        // 勤怠詳細画面が正常に表示されることを確認する
        $detailResponse->assertOk();
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

    // 勤怠と休憩を1件まとめて作成する
    private function createAttendanceWithBreak(
        int $userId,
        Carbon $workDate,
        string $clockInAt,
        string $clockOutAt,
        string $breakStartAt,
        string $breakEndAt
    ): Attendance {
        $attendance = Attendance::query()->create([
            'user_id' => $userId,
            'work_date' => $workDate->toDateString(),
            'clock_in_at' => $workDate->copy()->format('Y-m-d') . ' ' . $clockInAt . ':00',
            'clock_out_at' => $workDate->copy()->format('Y-m-d') . ' ' . $clockOutAt . ':00',
        ]);

        AttendanceBreak::query()->create([
            'attendance_id' => $attendance->id,
            'break_start_at' => $workDate->copy()->format('Y-m-d') . ' ' . $breakStartAt . ':00',
            'break_end_at' => $workDate->copy()->format('Y-m-d') . ' ' . $breakEndAt . ':00',
        ]);

        return $attendance;
    }

    // 修正申請を1件作成する
    private function createStampCorrectionRequest(
        Attendance $attendance,
        string $reason,
        $approvedAt
    ): StampCorrectionRequest {
        return StampCorrectionRequest::query()->create([
            'attendance_id' => $attendance->id,
            'requested_clock_in_at' => $this->formatWorkDate($attendance) . ' 09:30:00',
            'requested_clock_out_at' => $this->formatWorkDate($attendance) . ' 18:30:00',
            'reason' => $reason,
            'approved_at' => $approvedAt,
        ]);
    }

    // 修正申請のリクエストデータを返す
    private function correctionRequestData(array $overrides = []): array
    {
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
}
