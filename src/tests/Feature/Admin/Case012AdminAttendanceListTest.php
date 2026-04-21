<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 勤怠一覧情報取得機能（管理者）
class Case012AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 勤怠データは入れず、ユーザーだけ作成する
        $this->seed(UserSeeder::class);
    }

    protected function tearDown(): void
    {
        // 他のテストに現在日時固定が影響しないように解除する
        Carbon::setTestNow();

        parent::tearDown();
    }

    // その日になされた全ユーザーの勤怠情報が正確に確認できることを確認するテスト
    public function test_all_attendance_information_for_the_day_is_displayed_correctly(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザーを3人作成する
        $firstUser = $this->createGeneralUser('勤怠 太郎');
        $secondUser = $this->createGeneralUser('勤怠 花子');
        $userWithoutAttendance = $this->createGeneralUser('未打刻 次郎');

        // 表示対象日を作成する
        $targetDate = now()->copy()->startOfMonth();

        // 1人目の当日勤怠を作成する
        $this->createAttendanceWithBreak(
            userId: $firstUser->id,
            workDate: $targetDate,
            clockInAt: '08:30',
            clockOutAt: '17:15',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 2人目の当日勤怠を作成する
        $this->createAttendanceWithBreak(
            userId: $secondUser->id,
            workDate: $targetDate,
            clockInAt: '09:15',
            clockOutAt: '18:45',
            breakStartAt: '13:00',
            breakEndAt: '14:00'
        );

        // 管理者で勤怠一覧画面を開く
        $response = $this->actingAs($admin)->get(
            route('admin.attendance.list', ['date' => $targetDate->format('Y-m-d')])
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 1人目の勤怠情報が正確に表示されていることを確認する
        $response->assertSeeText($firstUser->name);
        $response->assertSeeText('08:30');
        $response->assertSeeText('17:15');
        $response->assertSeeText('1:00');
        $response->assertSeeText('7:45');

        // 2人目の勤怠情報が正確に表示されていることを確認する
        $response->assertSeeText($secondUser->name);
        $response->assertSeeText('09:15');
        $response->assertSeeText('18:45');
        $response->assertSeeText('1:00');
        $response->assertSeeText('8:30');

        // 当日勤怠がないユーザーは表示されていないことを確認する
        $response->assertDontSeeText($userWithoutAttendance->name);
    }

    // 遷移した際に現在の日付が表示されることを確認するテスト
    public function test_current_date_is_displayed_when_admin_opens_attendance_list_page(): void
    {
        // 現在日時を固定する
        $fixedNow = Carbon::create(2026, 4, 21, 9, 0, 0);
        Carbon::setTestNow($fixedNow);

        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 日付パラメータを付けずに勤怠一覧画面を開く
        $response = $this->actingAs($admin)->get(route('admin.attendance.list'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 見出しに現在の日付が表示されていることを確認する
        $response->assertSeeText($fixedNow->format('Y年n月j日') . 'の勤怠');

        // 中央の日付表示に現在の日付が表示されていることを確認する
        $response->assertSeeText($fixedNow->format('Y/m/d'));
    }

    // 「前日」を押下した時に前の日の勤怠情報が表示されることを確認するテスト
    public function test_previous_day_attendance_information_is_displayed_when_previous_day_is_selected(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザーを2人作成する
        $previousDayUser = $this->createGeneralUser('前日 太郎');
        $currentDayUser = $this->createGeneralUser('当日 花子');

        // 基準日を作成する
        $baseDate = now()->copy()->startOfMonth();

        // 前日を作成する
        $previousDate = $baseDate->copy()->subDay();

        // 当日を作成する
        $currentDate = $baseDate->copy();

        // 前日の勤怠を作成する
        $this->createAttendanceWithBreak(
            userId: $previousDayUser->id,
            workDate: $previousDate,
            clockInAt: '08:45',
            clockOutAt: '17:30',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 当日の勤怠を作成する
        $this->createAttendanceWithBreak(
            userId: $currentDayUser->id,
            workDate: $currentDate,
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:30',
            breakEndAt: '13:30'
        );

        // 管理者で前日の勤怠一覧画面を開く
        $response = $this->actingAs($admin)->get(
            route('admin.attendance.list', ['date' => $previousDate->format('Y-m-d')])
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 前日の日付が表示されていることを確認する
        $response->assertSeeText($previousDate->format('Y年n月j日') . 'の勤怠');
        $response->assertSeeText($previousDate->format('Y/m/d'));

        // 前日の勤怠情報が表示されていることを確認する
        $response->assertSeeText($previousDayUser->name);
        $response->assertSeeText('08:45');
        $response->assertSeeText('17:30');

        // 当日の勤怠情報が表示されていないことを確認する
        $response->assertDontSeeText($currentDayUser->name);
    }

    // 「翌日」を押下した時に次の日の勤怠情報が表示されることを確認するテスト
    public function test_next_day_attendance_information_is_displayed_when_next_day_is_selected(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザーを2人作成する
        $currentDayUser = $this->createGeneralUser('当日 太郎');
        $nextDayUser = $this->createGeneralUser('翌日 花子');

        // 基準日を作成する
        $baseDate = now()->copy()->startOfMonth();

        // 当日を作成する
        $currentDate = $baseDate->copy();

        // 翌日を作成する
        $nextDate = $baseDate->copy()->addDay();

        // 当日の勤怠を作成する
        $this->createAttendanceWithBreak(
            userId: $currentDayUser->id,
            workDate: $currentDate,
            clockInAt: '08:30',
            clockOutAt: '17:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 翌日の勤怠を作成する
        $this->createAttendanceWithBreak(
            userId: $nextDayUser->id,
            workDate: $nextDate,
            clockInAt: '10:00',
            clockOutAt: '19:00',
            breakStartAt: '14:00',
            breakEndAt: '15:00'
        );

        // 管理者で翌日の勤怠一覧画面を開く
        $response = $this->actingAs($admin)->get(
            route('admin.attendance.list', ['date' => $nextDate->format('Y-m-d')])
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 翌日の日付が表示されていることを確認する
        $response->assertSeeText($nextDate->format('Y年n月j日') . 'の勤怠');
        $response->assertSeeText($nextDate->format('Y/m/d'));

        // 翌日の勤怠情報が表示されていることを確認する
        $response->assertSeeText($nextDayUser->name);
        $response->assertSeeText('10:00');
        $response->assertSeeText('19:00');

        // 当日の勤怠情報が表示されていないことを確認する
        $response->assertDontSeeText($currentDayUser->name);
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
}
