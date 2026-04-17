<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

// メール認証機能
class Case016EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    // 会員登録後、認証メールが送信されることを確認するテスト
    public function test_verification_email_is_sent_after_user_registration(): void
    {
        // 通知を偽装する
        Notification::fake();

        // 会員登録を実行する
        $response = $this->post(route('register'), $this->registerUserData());

        // メール認証誘導画面へリダイレクトされることを確認する
        $response->assertRedirect(route('verification.notice'));

        // 登録ユーザーを取得する
        $user = User::query()
            ->where('email', 'case016_user@example.com')
            ->firstOrFail();

        // 一般ユーザーとして登録されていることを確認する
        $this->assertSame(User::ROLE_USER, $user->role);

        // メール認証前であることを確認する
        $this->assertNull($user->email_verified_at);

        // 認証メールが送信されていることを確認する
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    // メール認証誘導画面で「認証はこちらから」ボタンを押下するとメール認証サイトに遷移することを確認するテスト
    public function test_verify_email_prompt_contains_link_to_mailhog(): void
    {
        // 未認証の一般ユーザーを作成する
        $user = $this->createUnverifiedUser();

        // メール認証誘導画面を開く
        $response = $this->actingAs($user)->get(route('verification.notice'));

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 「認証はこちらから」ボタンが表示されていることを確認する
        $response->assertSeeText('認証はこちらから');

        // メール認証サイトへのリンクが表示されていることを確認する
        $response->assertSee('href="http://localhost:8025"', false);
    }

    // メール認証サイトのメール認証を完了すると、勤怠登録画面に遷移することを確認するテスト
    public function test_user_is_redirected_to_attendance_page_after_email_verification(): void
    {
        // 未認証の一般ユーザーを作成する
        $user = $this->createUnverifiedUser();

        // メール認証誘導画面を開いて認証後の遷移先をセッションに保存する
        $this->actingAs($user)->get(route('verification.notice'));

        // メール認証用の署名付きURLを作成する
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        // メール認証を完了する
        $response = $this->actingAs($user)->get($verificationUrl);

        // 勤怠登録画面へリダイレクトされることを確認する
        $response->assertRedirect(route('attendance.index'));

        // メール認証が完了していることを確認する
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    // 会員登録用の入力データを返す
    private function registerUserData(array $overrides = []): array
    {
        $defaultData = [
            'name' => 'Case016 ユーザー',
            'email' => 'case016_user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        return array_replace_recursive($defaultData, $overrides);
    }

    // 未認証の一般ユーザーを作成する
    private function createUnverifiedUser(): User
    {
        return User::factory()->create([
            'name' => '未認証ユーザー',
            'email' => 'unverified_user@example.com',
            'role' => User::ROLE_USER,
            'email_verified_at' => null,
        ]);
    }
}
