<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ステータス確認機能
class Case005AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 勤怠データは入れず、ユーザーだけ作成する
        $this->seed(UserSeeder::class);
    }

    // 勤務外の場合、勤怠ステータスが正しく表示されることを確認するテスト
    public function test_status_is_displayed_as_off_duty_when_no_attendance_exists(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 勤怠打刻画面を開く
        $response = $this->actingAs($user)->get(route('attendance.index'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 当日の勤怠がないため、勤務外と表示されることを確認する
        $response->assertSeeText('勤務外');
    }

    // 出勤中の場合、勤怠ステータスが正しく表示されることを確認するテスト
    public function test_status_is_displayed_as_working_when_clocked_in(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 当日の出勤中データを作成する
        $this->createTodayAttendance($user->id);

        // 勤怠打刻画面を開く
        $response = $this->actingAs($user)->get(route('attendance.index'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 出勤中と表示されることを確認する
        $response->assertSeeText('出勤中');
    }

    // 休憩中の場合、勤怠ステータスが正しく表示されることを確認するテスト
    public function test_status_is_displayed_as_on_break_when_open_break_exists(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 当日の出勤中データを作成する
        $attendance = $this->createTodayAttendance($user->id);

        // 未終了の休憩データを作成して、休憩中状態を作る
        AttendanceBreak::query()->create([
            'attendance_id' => $attendance->id,
            'break_start_at' => now()->toDateTimeString(),
            'break_end_at' => null,
        ]);

        // 勤怠打刻画面を開く
        $response = $this->actingAs($user)->get(route('attendance.index'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 休憩中と表示されることを確認する
        $response->assertSeeText('休憩中');
    }

    // 退勤済の場合、勤怠ステータスが正しく表示されることを確認するテスト
    public function test_status_is_displayed_as_clocked_out_when_clock_out_exists(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 当日の退勤済データを作成する
        $this->createTodayAttendance(
            userId: $user->id,
            clockInAt: now()->subHours(8),
            clockOutAt: now()->subHour()
        );

        // 勤怠打刻画面を開く
        $response = $this->actingAs($user)->get(route('attendance.index'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 退勤済と表示されることを確認する
        $response->assertSeeText('退勤済');
    }

    // シーダーで作成した一般ユーザーを取得する
    private function findGeneralUser(): User
    {
        return User::query()
            ->where('role', User::ROLE_USER)
            ->orderBy('id')
            ->firstOrFail();
    }

    // 当日の勤怠データを作成する
    private function createTodayAttendance(
        int $userId,
        ?Carbon $clockInAt = null,
        ?Carbon $clockOutAt = null
    ): Attendance {

        // 現在時刻を取得する
        $current = now();

        // 出勤時刻が未指定なら、3時間前を入れる
        $clockInAt = $clockInAt ?? $current->copy()->subHours(3);

        return Attendance::query()->create([
            'user_id' => $userId,
            'work_date' => $current->toDateString(),
            'clock_in_at' => $clockInAt->toDateTimeString(),
            'clock_out_at' => $clockOutAt?->toDateTimeString(),
        ]);
    }
}
