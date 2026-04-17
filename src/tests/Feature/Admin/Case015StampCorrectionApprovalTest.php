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

// 勤怠情報修正機能（管理者）
class Case015AdminStampCorrectionApproveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ユーザーデータを作成する
        $this->seed(UserSeeder::class);
    }

    // 承認待ちの修正申請が全て表示されていることを確認するテスト
    public function test_all_pending_correction_requests_are_displayed(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザーを取得する
        $firstUser = $this->findFirstGeneralUser();
        $secondUser = $this->findSecondGeneralUser();

        // 基準日を作成する
        $baseDate = now()->copy()->startOfMonth();

        // 1件目の勤怠を作成する
        $firstAttendance = $this->createAttendanceWithBreak(
            userId: $firstUser->id,
            workDate: $baseDate->copy(),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 2件目の勤怠を作成する
        $secondAttendance = $this->createAttendanceWithBreak(
            userId: $secondUser->id,
            workDate: $baseDate->copy()->addDay(),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 承認待ちの修正申請を2件作成する
        $firstRequest = $this->createStampCorrectionRequest(
            attendance: $firstAttendance,
            reason: '1件目の未承認申請',
            approvedAt: null
        );

        $secondRequest = $this->createStampCorrectionRequest(
            attendance: $secondAttendance,
            reason: '2件目の未承認申請',
            approvedAt: null
        );

        // 承認済みの修正申請を1件作成する
        $approvedAttendance = $this->createAttendanceWithBreak(
            userId: $firstUser->id,
            workDate: $baseDate->copy()->addDays(2),
            clockInAt: '08:30',
            clockOutAt: '17:30',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        $approvedRequest = $this->createStampCorrectionRequest(
            attendance: $approvedAttendance,
            reason: '承認済み申請',
            approvedAt: now()
        );

        // 管理者で修正申請一覧画面の承認待ちタブを開く
        $response = $this->actingAs($admin)->get(
            route('stamp_correction_request.list')
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 全ユーザーの未承認の修正申請が表示されていることを確認する
        $response->assertSeeText($firstRequest->reason);
        $response->assertSeeText($secondRequest->reason);
        $response->assertSeeText($firstUser->name);
        $response->assertSeeText($secondUser->name);

        // 承認済みの修正申請は表示されていないことを確認する
        $response->assertDontSeeText($approvedRequest->reason);
    }

    // 承認済みの修正申請が全て表示されていることを確認するテスト
    public function test_all_approved_correction_requests_are_displayed(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザーを取得する
        $firstUser = $this->findFirstGeneralUser();
        $secondUser = $this->findSecondGeneralUser();

        // 基準日を作成する
        $baseDate = now()->copy()->startOfMonth();

        // 1件目の勤怠を作成する
        $firstAttendance = $this->createAttendanceWithBreak(
            userId: $firstUser->id,
            workDate: $baseDate->copy(),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 2件目の勤怠を作成する
        $secondAttendance = $this->createAttendanceWithBreak(
            userId: $secondUser->id,
            workDate: $baseDate->copy()->addDay(),
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

        // 承認待ちの修正申請を1件作成する
        $pendingAttendance = $this->createAttendanceWithBreak(
            userId: $firstUser->id,
            workDate: $baseDate->copy()->addDays(2),
            clockInAt: '08:30',
            clockOutAt: '17:30',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        $pendingRequest = $this->createStampCorrectionRequest(
            attendance: $pendingAttendance,
            reason: '未承認申請',
            approvedAt: null
        );

        // 管理者で修正申請一覧画面の承認済みタブを開く
        $response = $this->actingAs($admin)->get(
            route('stamp_correction_request.list', ['status' => 'approved'])
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 全ユーザーの承認済みの修正申請が表示されていることを確認する
        $response->assertSeeText($firstRequest->reason);
        $response->assertSeeText($secondRequest->reason);
        $response->assertSeeText($firstUser->name);
        $response->assertSeeText($secondUser->name);

        // 承認待ちの修正申請は表示されていないことを確認する
        $response->assertDontSeeText($pendingRequest->reason);
    }

    // 修正申請の詳細内容が正しく表示されていることを確認するテスト
    public function test_stamp_correction_request_detail_is_displayed_correctly(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザーを取得する
        $user = $this->findFirstGeneralUser();

        // 対象日を作成する
        $workDate = now()->copy()->startOfMonth();

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: $workDate,
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 修正申請を作成する
        $stampCorrectionRequest = $this->createStampCorrectionRequest(
            attendance: $attendance,
            reason: '電車遅延のため修正申請',
            approvedAt: null,
            requestedClockInAt: '09:30',
            requestedClockOutAt: '18:30',
            requestedBreaks: [
                [
                    'break_start_at' => '12:30',
                    'break_end_at' => '13:15',
                ],
            ]
        );

        // 管理者で修正申請詳細画面を開く
        $response = $this->actingAs($admin)->get(
            route('admin.stamp_correction_request.approve', ['id' => $stampCorrectionRequest->id])
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 申請内容が正しく表示されていることを確認する
        $response->assertSeeText($user->name);
        $response->assertSeeText($workDate->format('Y年'));
        $response->assertSeeText($workDate->format('n月j日'));
        $response->assertSeeText('09:30');
        $response->assertSeeText('18:30');
        $response->assertSeeText('12:30');
        $response->assertSeeText('13:15');
        $response->assertSeeText('電車遅延のため修正申請');
        $response->assertSeeText('承認');
    }

    // 修正申請の承認処理が正しく行われることを確認するテスト
    public function test_stamp_correction_request_is_approved_and_attendance_is_updated(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザーを取得する
        $user = $this->findFirstGeneralUser();

        // 対象日を作成する
        $workDate = now()->copy()->startOfMonth()->addDay();

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: $workDate,
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 承認待ちの修正申請を作成する
        $stampCorrectionRequest = $this->createStampCorrectionRequest(
            attendance: $attendance,
            reason: '管理者承認確認用の申請',
            approvedAt: null,
            requestedClockInAt: '09:30',
            requestedClockOutAt: '18:30',
            requestedBreaks: [
                [
                    'break_start_at' => '12:30',
                    'break_end_at' => '13:15',
                ],
            ]
        );

        // 管理者で承認処理を実行する
        $response = $this->actingAs($admin)->patch(
            route('admin.stamp_correction_request.approve.update', ['id' => $stampCorrectionRequest->id])
        );

        // 同じ承認画面へリダイレクトされることを確認する
        $response->assertRedirect(
            route('admin.stamp_correction_request.approve', ['id' => $stampCorrectionRequest->id])
        );

        // 成功メッセージがセッションに入っていることを確認する
        $response->assertSessionHas('success', '修正申請を承認しました。');

        // 勤怠本体が申請内容で更新されたことを確認する
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_at' => $this->formatWorkDate($attendance) . ' 09:30:00',
            'clock_out_at' => $this->formatWorkDate($attendance) . ' 18:30:00',
        ]);

        // 元の休憩が削除されていることを確認する
        $this->assertDatabaseMissing('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_start_at' => $this->formatWorkDate($attendance) . ' 12:00:00',
            'break_end_at' => $this->formatWorkDate($attendance) . ' 13:00:00',
        ]);

        // 申請された休憩内容が本体へ反映されていることを確認する
        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_start_at' => $this->formatWorkDate($attendance) . ' 12:30:00',
            'break_end_at' => $this->formatWorkDate($attendance) . ' 13:15:00',
        ]);

        // 修正申請が承認済みになっていることを確認する
        $approvedRequest = StampCorrectionRequest::query()->findOrFail($stampCorrectionRequest->id);
        $this->assertNotNull($approvedRequest->approved_at);
    }

    // シーダーで投入した管理者ユーザーを取得する
    private function findAdminUser(): User
    {
        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->orderBy('id')
            ->firstOrFail();
    }

    // シーダーで投入した1人目の一般ユーザーを取得する
    private function findFirstGeneralUser(): User
    {
        return User::query()
            ->where('role', User::ROLE_USER)
            ->orderBy('id')
            ->firstOrFail();
    }

    // シーダーで投入した2人目の一般ユーザーを取得する
    private function findSecondGeneralUser(): User
    {
        return User::query()
            ->where('role', User::ROLE_USER)
            ->orderBy('id')
            ->skip(1)
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
        $approvedAt,
        string $requestedClockInAt = '09:30',
        string $requestedClockOutAt = '18:30',
        array $requestedBreaks = [
            [
                'break_start_at' => '12:30',
                'break_end_at' => '13:15',
            ],
        ]
    ): StampCorrectionRequest {
        $stampCorrectionRequest = StampCorrectionRequest::query()->create([
            'attendance_id' => $attendance->id,
            'requested_clock_in_at' => $this->formatWorkDate($attendance) . ' ' . $requestedClockInAt . ':00',
            'requested_clock_out_at' => $this->formatWorkDate($attendance) . ' ' . $requestedClockOutAt . ':00',
            'reason' => $reason,
            'approved_at' => $approvedAt,
        ]);

        $stampCorrectionBreakRows = [];

        foreach ($requestedBreaks as $requestedBreak) {
            $breakStartAt = $requestedBreak['break_start_at'] ?? null;
            $breakEndAt = $requestedBreak['break_end_at'] ?? null;

            if (empty($breakStartAt) && empty($breakEndAt)) {
                continue;
            }

            $stampCorrectionBreakRows[] = [
                'requested_break_start_at' => $this->formatWorkDate($attendance) . ' ' . $breakStartAt . ':00',
                'requested_break_end_at' => $this->formatWorkDate($attendance) . ' ' . $breakEndAt . ':00',
            ];
        }

        if ($stampCorrectionBreakRows !== []) {
            $stampCorrectionRequest->stampCorrectionBreaks()->createMany($stampCorrectionBreakRows);
        }

        return $stampCorrectionRequest;
    }

    // 勤怠日を Y-m-d 形式で返す
    private function formatWorkDate(Attendance $attendance): string
    {
        return Carbon::parse($attendance->work_date)->toDateString();
    }
}
