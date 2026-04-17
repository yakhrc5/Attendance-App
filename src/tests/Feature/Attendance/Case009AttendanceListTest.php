<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 勤怠一覧情報取得機能（一般ユーザー）
class Case009AttendanceListTest extends TestCase
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

    // 自分が行った勤怠情報が全て表示されていることを確認するテスト
    public function test_all_of_my_attendance_information_is_displayed(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 今日の日付を基準にする
        $baseDate = now()->copy()->startOfMonth();

        // 自分の当月勤怠を2日分作成する
        $firstAttendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: $baseDate->copy(),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        $secondAttendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: $baseDate->copy()->addDays(1),
            clockInAt: '10:00',
            clockOutAt: '19:00',
            breakStartAt: '15:00',
            breakEndAt: '15:30'
        );

        // 他ユーザーの勤怠を作成する
        $anotherUser = User::query()
            ->where('role', User::ROLE_USER)
            ->where('id', '!=', $user->id)
            ->firstOrFail();

        $this->createAttendanceWithBreak(
            userId: $anotherUser->id,
            workDate: $baseDate->copy()->addDays(3),
            clockInAt: '07:11',
            clockOutAt: '16:22',
            breakStartAt: '11:00',
            breakEndAt: '11:45'
        );

        // 勤怠一覧画面を開く
        $response = $this->actingAs($user)->get(route('attendance.list'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 自分の勤怠情報が表示されていることを確認する
        $response->assertSeeText($firstAttendance['workDate']->format('m/d'));
        $response->assertSeeText('09:00');
        $response->assertSeeText('18:00');
        $response->assertSeeText('1:00');

        $response->assertSeeText($secondAttendance['workDate']->format('m/d'));
        $response->assertSeeText('10:00');
        $response->assertSeeText('19:00');
        $response->assertSeeText('0:30');

        // 他ユーザー固有の勤怠情報が表示されないことを確認する
        $response->assertDontSeeText('07:11');
        $response->assertDontSeeText('16:22');
    }

    // 勤怠一覧画面に遷移した際に現在の月が表示されることを確認するテスト
    public function test_current_month_is_displayed_when_attendance_list_page_is_opened(): void
    {
        // 今日の日付を基準にする
        $baseDate = now()->copy()->startOfMonth();

        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 勤怠一覧画面を開く
        $response = $this->actingAs($user)->get(route('attendance.list'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 現在の月が表示されることを確認する
        $response->assertSeeText($baseDate->format('Y/m'));
    }

    // 前月を押下した時に表示月の前月の情報が表示されることを確認するテスト
    public function test_previous_month_information_is_displayed_when_previous_month_is_opened(): void
    {
        // 今日の日付を基準にする
        $baseDate = now()->copy()->startOfMonth();

        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 前月の勤怠を作成する
        $previousMonthDate = $baseDate->copy()->subMonth()->startOfMonth()->addDays(4);
        $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: $previousMonthDate,
            clockInAt: '08:30',
            clockOutAt: '17:30',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 前月の一覧画面を開く
        $response = $this->actingAs($user)->get(route('attendance.list', [
            'month' => $baseDate->copy()->subMonth()->format('Y-m'),
        ]));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 前月ラベルと前月勤怠が表示されることを確認する
        $response->assertSeeText($baseDate->copy()->subMonth()->format('Y/m'));
        $response->assertSeeText($previousMonthDate->format('m/d'));
        $response->assertSeeText('08:30');
        $response->assertSeeText('17:30');
    }

    // 翌月を押下した時に表示月の翌月の情報が表示されることを確認するテスト
    public function test_next_month_information_is_displayed_when_next_month_is_opened(): void
    {
        // 今日の日付を基準にする
        $baseDate = now()->copy()->startOfMonth();

        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 翌月の勤怠を作成する
        $nextMonthDate = $baseDate->copy()->addMonth()->startOfMonth()->addDays(6);
        $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: $nextMonthDate,
            clockInAt: '11:00',
            clockOutAt: '20:00',
            breakStartAt: '16:00',
            breakEndAt: '16:45'
        );

        // 翌月の一覧画面を開く
        $response = $this->actingAs($user)->get(route('attendance.list', [
            'month' => $baseDate->copy()->addMonth()->format('Y-m'),
        ]));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 翌月ラベルと翌月勤怠が表示されることを確認する
        $response->assertSeeText($baseDate->copy()->addMonth()->format('Y/m'));
        $response->assertSeeText($nextMonthDate->format('m/d'));
        $response->assertSeeText('11:00');
        $response->assertSeeText('20:00');
    }

    // 詳細を押下するとその日の勤怠詳細画面に遷移することを確認するテスト
    public function test_detail_link_leads_to_attendance_detail_page(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 当日の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: now()->copy()->startOfMonth()->addDays(5),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        )['attendance'];

        // 勤怠一覧画面を開く
        $response = $this->actingAs($user)->get(route('attendance.list'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 詳細リンクが表示されていることを確認する
        $response->assertSee(route('attendance.detail', ['id' => $attendance->id]), false);

        // 詳細画面を開く
        $detailResponse = $this->actingAs($user)->get(route('attendance.detail', ['id' => $attendance->id]));

        // 詳細画面が正常に表示されることを確認する
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

    /**
     * 勤怠と休憩を1件まとめて作成する
     *
     * @return array{
     *     attendance: \App\Models\Attendance,
     *     workDate: \Carbon\Carbon
     * }
     */
    private function createAttendanceWithBreak(
        int $userId,
        Carbon $workDate,
        string $clockInAt,
        string $clockOutAt,
        string $breakStartAt,
        string $breakEndAt
    ): array {
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

        return [
            'attendance' => $attendance,
            'workDate' => $workDate,
        ];
    }
}
