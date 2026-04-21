<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 休憩機能
class Case007BreakTest extends TestCase
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

    // 休憩ボタンが正しく機能することを確認するテスト
    public function test_break_start_button_works_correctly(): void
    {
        // 現在日時を「今の分」で固定する
        $fixedNow = $this->freezeCurrentMinute();

        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 当日の出勤中データを作成する
        $attendance = $this->createTodayAttendance($user->id);

        // 勤怠打刻画面を開く
        $response = $this->actingAs($user)->get(route('attendance.index'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 出勤中なので、休憩入ボタンが表示されることを確認する
        $response->assertSeeText('休憩入');

        // 休憩入の処理を行う
        $response = $this->actingAs($user)->post(route('attendance.break-start'));

        // 勤怠打刻画面へリダイレクトされることを確認する
        $response->assertRedirect(route('attendance.index'));

        // 休憩データが登録されることを確認する
        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_start_at' => $fixedNow->toDateTimeString(),
            'break_end_at' => null,
        ]);

        // 処理後に休憩中と表示されることを確認する
        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSeeText('休憩中');
    }

    // 休憩は一日に何回でもできることを確認するテスト
    public function test_break_start_can_be_done_multiple_times_per_day(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 当日の出勤中データを作成する
        $this->createTodayAttendance($user->id);

        // 1回目の休憩入と休憩戻を行う
        $this->actingAs($user)->post(route('attendance.break-start'));
        $this->actingAs($user)->post(route('attendance.break-end'));

        // 再度勤怠打刻画面を開く
        $response = $this->actingAs($user)->get(route('attendance.index'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 再度休憩入ボタンが表示されることを確認する
        $response->assertSeeText('休憩入');
    }

    // 休憩戻ボタンが正しく機能することを確認するテスト
    public function test_break_end_button_works_correctly(): void
    {
        // 現在日時を「今の分」で固定する
        $fixedNow = $this->freezeCurrentMinute();

        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 当日の出勤中データを作成する
        $attendance = $this->createTodayAttendance($user->id);

        // 休憩入を行う
        $this->actingAs($user)->post(route('attendance.break-start'));

        // 休憩データが登録されることを確認する
        $attendanceBreak = AttendanceBreak::query()
            ->where('attendance_id', $attendance->id)
            ->whereNull('break_end_at')
            ->latest('id')
            ->firstOrFail();

        // 勤怠打刻画面を開く
        $response = $this->actingAs($user)->get(route('attendance.index'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 休憩中なので、休憩戻ボタンが表示されることを確認する
        $response->assertSeeText('休憩戻');

        // 休憩戻の処理を行う
        $response = $this->actingAs($user)->post(route('attendance.break-end'));

        // 勤怠打刻画面へリダイレクトされることを確認する
        $response->assertRedirect(route('attendance.index'));

        // 休憩データに終了時刻が入ることを確認する
        $this->assertDatabaseHas('attendance_breaks', [
            'id' => $attendanceBreak->id,
            'break_end_at' => $fixedNow->toDateTimeString(),
        ]);

        // 処理後に出勤中と表示されることを確認する
        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSeeText('出勤中');
    }

    // 休憩戻は一日に何回でもできることを確認するテスト
    public function test_break_end_can_be_done_multiple_times_per_day(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 当日の出勤中データを作成する
        $this->createTodayAttendance($user->id);

        // 1回目の休憩入と休憩戻を行う
        $this->actingAs($user)->post(route('attendance.break-start'));
        $this->actingAs($user)->post(route('attendance.break-end'));

        // 2回目の休憩入を行う
        $this->actingAs($user)->post(route('attendance.break-start'));

        // 再度勤怠打刻画面を開く
        $response = $this->actingAs($user)->get(route('attendance.index'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 再度休憩戻ボタンが表示されることを確認する
        $response->assertSeeText('休憩戻');
    }

    // 休憩時間が勤怠一覧画面で確認できることを確認するテスト
    public function test_break_duration_is_displayed_on_attendance_list(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 今日の日付を基準にする
        $baseDate = now()->startOfDay();

        // 当日の出勤中データを作成する
        $this->createTodayAttendance(
            userId: $user->id,
            clockInAt: $baseDate->copy()->setTime(9, 0)
        );

        // 12:00 に休憩入を行う
        Carbon::setTestNow($baseDate->copy()->setTime(12, 0));
        $this->actingAs($user)->post(route('attendance.break-start'));

        // 13:00 に休憩戻を行う
        Carbon::setTestNow($baseDate->copy()->setTime(13, 0));
        $this->actingAs($user)->post(route('attendance.break-end'));

        // 勤怠一覧画面を開く
        $response = $this->actingAs($user)->get(route('attendance.list'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 一覧画面の日付表示に合わせて期待値を作る
        $expectedDate = $baseDate->copy()->isoFormat('MM/DD');

        // その日の行に休憩時間 1:00 が表示されることを確認する
        $response->assertSeeInOrder([
            $expectedDate,
                '09:00',
                '1:00',
        ]);
    }

    // シーダーで投入した一般ユーザーを取得する
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

    // 現在日時を「今の分」で固定して返す
    private function freezeCurrentMinute(): Carbon
    {
        // 秒を 00 にそろえて、分またぎによるテスト失敗を防ぐ
        $fixedNow = now()->copy()->startOfMinute();
        Carbon::setTestNow($fixedNow);

        return $fixedNow;
    }
}
