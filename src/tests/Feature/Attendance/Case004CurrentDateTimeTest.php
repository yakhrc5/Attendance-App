<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 日時取得機能
class Case004CurrentDateTimeTest extends TestCase
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

    // 現在の日時情報がUIと同じ形式で出力されていることを確認するテスト
    public function test_current_datetime_is_displayed_in_same_format_as_ui(): void
    {
        // 曜日表示を日本語にする
        Carbon::setLocale('ja');

        // 「今の分」で現在日時を固定する
        $fixedNow = now()->copy()->startOfMinute();
        Carbon::setTestNow($fixedNow);

        // テスト用の一般ユーザーを取得する
        /** @var \App\Models\User $user */
        $user = User::query()
            ->where('role', User::ROLE_USER)
            ->firstOrFail();

        // コントローラの表示形式に合わせて期待値を作る
        $expectedDate = $fixedNow->isoFormat('YYYY年M月D日(dd)');
        $expectedTime = $fixedNow->format('H:i');

        // ログインした状態で勤怠打刻画面を開く
        $response = $this->actingAs($user)->get(route('attendance.index'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 現在日付がUIと同じ形式で表示されることを確認する
        $response->assertSeeText($expectedDate);

        // 現在時刻がUIと同じ形式で表示されることを確認する
        $response->assertSeeText($expectedTime);
    }
}
