<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 出勤機能
class Case006ClockInTest extends TestCase
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

    // 出勤ボタンが正しく機能することを確認するテスト
    public function test_clock_in_button_works_correctly(): void
    {
        // 現在日時を「今の分」で固定する
        $fixedNow = $this->freezeCurrentMinute();

        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 勤務外画面を開き、出勤ボタンが表示されることを確認する
        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSeeText('出勤');

        // 出勤処理を実行する
        $response = $this->actingAs($user)->post(route('attendance.clock-in'));

        // 勤怠打刻画面へリダイレクトされることを確認する
        $response->assertRedirect(route('attendance.index'));

        // 当日の出勤データが登録されることを確認する
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => $fixedNow->toDateString(),
            'clock_in_at' => $fixedNow->toDateTimeString(),
        ]);

        // 出勤後の画面で、ステータスが出勤中になることを確認する
        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSeeText('出勤中');
    }

    // 出勤は一日一回のみできることを確認するテスト
    public function test_clock_in_button_is_not_displayed_after_clock_out(): void
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

        // 退勤済のため、出勤ボタンが表示されないことを確認する
        $response->assertSeeText('退勤済');
        $response->assertDontSee(route('attendance.clock-in'), false);
    }

    // 出勤時刻が勤怠一覧画面で確認できることを確認するテスト
    public function test_clock_in_time_is_displayed_on_attendance_list(): void
    {
        // 現在日時を「今の分」で固定する
        $fixedNow = $this->freezeCurrentMinute();

        // 一般ユーザーを取得する
        $user = $this->findGeneralUser();

        // 出勤処理を実行する
        $this->actingAs($user)->post(route('attendance.clock-in'));

        // 勤怠一覧画面を開く
        $response = $this->actingAs($user)->get(route('attendance.list'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 一覧画面に出勤時刻が表示されることを確認する
        $response->assertSeeText($fixedNow->locale('ja')->isoFormat('MM/DD(dd)'));
        $response->assertSeeText($fixedNow->format('H:i'));
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
