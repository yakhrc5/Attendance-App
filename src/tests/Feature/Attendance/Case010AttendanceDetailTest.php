<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 勤怠詳細情報取得機能（一般ユーザー）
class Case010AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 勤怠データは入れず、ユーザーだけ作成する
        $this->seed(UserSeeder::class);
    }

    // 勤怠詳細画面の「名前」がログインユーザーの氏名になっていることを確認するテスト
    public function test_name_on_attendance_detail_page_is_logged_in_user_name(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 当日の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth(),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 勤怠詳細画面を開く
        $response = $this->actingAs($user)->get(route('attendance.detail', ['id' => $attendance->id]));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 名前がログインユーザーの氏名になっていることを確認する
        $response->assertSeeText($user->name);
    }

    // 勤怠詳細画面の「日付」が選択した日付になっていることを確認するテスト
    public function test_date_on_attendance_detail_page_matches_selected_date(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 対象日を作成する
        $workDate = now()->copy()->startOfMonth()->addDays(2);

        // 当日の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: $workDate,
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 勤怠詳細画面を開く
        $response = $this->actingAs($user)->get(route('attendance.detail', ['id' => $attendance->id]));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 日付が選択した日付になっていることを確認する
        $response->assertSeeText($workDate->format('Y年'));
        $response->assertSeeText($workDate->format('n月j日'));
    }

    // 「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致していることを確認するテスト
    public function test_clock_in_and_clock_out_times_match_logged_in_user_attendance(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 当日の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(3),
            clockInAt: '08:30',
            clockOutAt: '17:45',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 勤怠詳細画面を開く
        $response = $this->actingAs($user)->get(route('attendance.detail', ['id' => $attendance->id]));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 出勤・退勤欄の value が打刻と一致していることを確認する
        $response->assertSee('value="08:30"', false);
        $response->assertSee('value="17:45"', false);
    }

    // 「休憩」にて記されている時間がログインユーザーの打刻と一致していることを確認するテスト
    public function test_break_times_match_logged_in_user_attendance(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 当日の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(4),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:15',
            breakEndAt: '13:15'
        );

        // 勤怠詳細画面を開く
        $response = $this->actingAs($user)->get(route('attendance.detail', ['id' => $attendance->id]));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 休憩欄の value が打刻と一致していることを確認する
        $response->assertSee('value="12:15"', false);
        $response->assertSee('value="13:15"', false);
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
