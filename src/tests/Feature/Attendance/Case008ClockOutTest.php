<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 退勤機能
class Case008ClockOutTest extends TestCase
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

    // 退勤ボタンが正しく機能することを確認するテスト
    public function test_clock_out_button_works_correctly(): void
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

        // 出勤中なので、退勤ボタンが表示されることを確認する
        $response->assertSeeText('退勤');

        // 退勤の処理を行う
        $response = $this->actingAs($user)->post(route('attendance.clock-out'));

        // 勤怠打刻画面へリダイレクトされることを確認する
        $response->assertRedirect(route('attendance.index'));

        // 勤怠データに退勤時刻が登録されることを確認する
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_out_at' => $fixedNow->toDateTimeString(),
        ]);

        // 処理後に退勤済と表示されることを確認する
        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSeeText('退勤済');
    }

    // 退勤時刻が勤怠一覧画面で確認できることを確認するテスト
    public function test_clock_out_time_is_displayed_on_attendance_list(): void
    {
        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 今日の日付を基準にする
        $baseDate = now()->startOfDay();

        // 09:00 に出勤する
        Carbon::setTestNow($baseDate->copy()->setTime(9, 0));
        $this->actingAs($user)->post(route('attendance.clock-in'));

        // 18:00 に退勤する
        Carbon::setTestNow($baseDate->copy()->setTime(18, 0));
        $this->actingAs($user)->post(route('attendance.clock-out'));

        // 勤怠一覧画面を開く
        $response = $this->actingAs($user)->get(route('attendance.list'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 一覧画面の日付表示に合わせて期待値を作る
        $expectedDate = $baseDate->copy()->locale('ja')->isoFormat('MM/DD(dd)');

        // その日の行に退勤時刻が表示されることを確認する
        $response->assertSeeInOrder([
            $expectedDate,
            '09:00',
            '18:00',
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
