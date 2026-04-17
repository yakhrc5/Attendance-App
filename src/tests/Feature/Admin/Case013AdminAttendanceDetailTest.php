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

// 勤怠詳細情報取得・修正機能（管理者）
class Case013AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 勤怠データは入れず、ユーザーだけ作成する
        $this->seed(UserSeeder::class);
    }

    // 勤怠詳細画面に表示されるデータが選択したものになっていることを確認するテスト
    public function test_selected_attendance_information_is_displayed_on_admin_attendance_detail_page(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザーを2人作成する
        $targetUser = $this->createGeneralUser('対象 太郎');
        $otherUser = $this->createGeneralUser('別人 花子');

        // 表示対象日を作成する
        $workDate = now()->copy()->startOfMonth();

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $targetUser->id,
            workDate: $workDate,
            clockInAt: '08:30',
            clockOutAt: '17:45',
            breakStartAt: '12:15',
            breakEndAt: '13:15'
        );

        // 比較用の別勤怠を作成する
        $this->createAttendanceWithBreak(
            userId: $otherUser->id,
            workDate: $workDate->copy()->addDay(),
            clockInAt: '10:00',
            clockOutAt: '19:00',
            breakStartAt: '14:00',
            breakEndAt: '15:00'
        );

        // 管理者で勤怠詳細画面を開く
        $response = $this->actingAs($admin)->get(
            route('admin.attendance.detail', ['id' => $attendance->id])
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 選択した勤怠情報が表示されていることを確認する
        $response->assertSeeText($targetUser->name);
        $response->assertSeeText($workDate->format('Y年'));
        $response->assertSeeText($workDate->format('n月j日'));
        $response->assertSee('value="08:30"', false);
        $response->assertSee('value="17:45"', false);
        $response->assertSee('value="12:15"', false);
        $response->assertSee('value="13:15"', false);

        // 別の勤怠情報は表示されていないことを確認する
        $response->assertDontSeeText($otherUser->name);
    }

    // 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示されることを確認するテスト
    public function test_validation_message_is_shown_when_clock_in_is_after_clock_out(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザーを作成する
        $user = $this->createGeneralUser('修正 太郎');

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDay(),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 出勤時間を退勤時間より後にして保存する
        $response = $this->actingAs($admin)
            ->from(route('admin.attendance.detail', ['id' => $attendance->id]))
            ->followingRedirects()
            ->patch(route('admin.attendance.update', ['id' => $attendance->id]), $this->attendanceCorrectionData([
                'clock_in_at' => '19:00',
                'clock_out_at' => '18:00',
            ]));

        // バリデーションメッセージが表示されることを確認する
        $response->assertSeeText('出勤時間もしくは退勤時間が不適切な値です');

        // 修正履歴が作成されていないことを確認する
        $this->assertDatabaseCount('stamp_correction_requests', 0);
    }

    // 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示されることを確認するテスト
    public function test_validation_message_is_shown_when_break_start_is_after_clock_out(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザーを作成する
        $user = $this->createGeneralUser('修正 花子');

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(2),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 休憩開始時間を退勤時間より後にして保存する
        $response = $this->actingAs($admin)
            ->from(route('admin.attendance.detail', ['id' => $attendance->id]))
            ->followingRedirects()
            ->patch(route('admin.attendance.update', ['id' => $attendance->id]), $this->attendanceCorrectionData([
                'breaks' => [
                    [
                        'break_start_at' => '18:30',
                        'break_end_at' => '18:45',
                    ],
                ],
            ]));

        // バリデーションメッセージが表示されることを確認する
        $response->assertSeeText('休憩時間が不適切な値です');

        // 修正履歴が作成されていないことを確認する
        $this->assertDatabaseCount('stamp_correction_requests', 0);
    }

    // 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示されることを確認するテスト
    public function test_validation_message_is_shown_when_break_end_is_after_clock_out(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザーを作成する
        $user = $this->createGeneralUser('修正 次郎');

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(3),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 休憩終了時間を退勤時間より後にして保存する
        $response = $this->actingAs($admin)
            ->from(route('admin.attendance.detail', ['id' => $attendance->id]))
            ->followingRedirects()
            ->patch(route('admin.attendance.update', ['id' => $attendance->id]), $this->attendanceCorrectionData([
                'breaks' => [
                    [
                        'break_start_at' => '17:30',
                        'break_end_at' => '18:30',
                    ],
                ],
            ]));

        // バリデーションメッセージが表示されることを確認する
        $response->assertSeeText('休憩時間もしくは退勤時間が不適切な値です');

        // 修正履歴が作成されていないことを確認する
        $this->assertDatabaseCount('stamp_correction_requests', 0);
    }

    // 備考欄が未入力の場合、エラーメッセージが表示されることを確認するテスト
    public function test_validation_message_is_shown_when_reason_is_empty(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザーを作成する
        $user = $this->createGeneralUser('備考 太郎');

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(4),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 備考を空のまま保存する
        $response = $this->actingAs($admin)
            ->from(route('admin.attendance.detail', ['id' => $attendance->id]))
            ->followingRedirects()
            ->patch(route('admin.attendance.update', ['id' => $attendance->id]), $this->attendanceCorrectionData([
                'reason' => '',
            ]));

        // バリデーションメッセージが表示されることを確認する
        $response->assertSeeText('備考を記入してください');

        // 修正履歴が作成されていないことを確認する
        $this->assertDatabaseCount('stamp_correction_requests', 0);
    }

    // シーダーで投入した管理者ユーザーを取得する
    private function findAdminUser(): User
    {
        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->orderBy('id')
            ->firstOrFail();
    }

    // 一般ユーザーを1人作成する
    private function createGeneralUser(string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => User::ROLE_USER,
            'email_verified_at' => now(),
        ]);
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

    // 勤怠修正のリクエストデータを返す
    private function attendanceCorrectionData(array $overrides = []): array
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
            'reason' => '管理者による勤怠修正',
        ];

        return array_replace_recursive($defaultData, $overrides);
    }
}
