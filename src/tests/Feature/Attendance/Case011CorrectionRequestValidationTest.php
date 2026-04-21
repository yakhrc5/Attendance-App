<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 勤怠詳細情報修正機能（一般ユーザー） バリデーション
class Case011CorrectionRequestValidationTest extends TestCase
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
            // バリデーションエラー時の戻り先を勤怠詳細画面にする
            ->from(route('attendance.detail', ['id' => $attendance->id]))
            // リダイレクト先の画面文言まで確認できるようにする
            ->followingRedirects()
            ->post(
                route('attendance.request.store', ['id' => $attendance->id]),
                $this->correctionRequestData([
                    'clock_in_at' => '19:00',
                    'clock_out_at' => '18:00',
                ])
            );

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
            ->post(
                route('attendance.request.store', ['id' => $attendance->id]),
                $this->correctionRequestData([
                    'breaks' => [
                        [
                            'break_start_at' => '18:30',
                            'break_end_at' => '18:45',
                        ],
                    ],
                ])
            );

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
            ->post(
                route('attendance.request.store', ['id' => $attendance->id]),
                $this->correctionRequestData([
                    'breaks' => [
                        [
                            'break_start_at' => '17:30',
                            'break_end_at' => '18:30',
                        ],
                    ],
                ])
            );

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
            ->post(
                route('attendance.request.store', ['id' => $attendance->id]),
                $this->correctionRequestData([
                    'reason' => '',
                ])
            );

        // バリデーションメッセージが表示されることを確認する
        $response->assertSeeText('備考を記入してください');

        // 修正申請が作成されていないことを確認する
        $this->assertDatabaseCount('stamp_correction_requests', 0);
    }

    // シーダーで投入した一般ユーザーを取得する
    private function findGeneralUser(): User
    {
        return User::query()
            ->where('role', User::ROLE_USER)
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
}
