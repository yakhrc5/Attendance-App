<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// 認証機能（一般ユーザー）
class Case001RegisterTest extends TestCase
{
    use RefreshDatabase;

    // 名前が未入力の場合、バリデーションメッセージが表示されることを確認するテスト
    public function test_register_name_is_required(): void
    {
        $response = $this->post(route('register'), [
            'name' => '',
            'email' => 'test-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('name');

        $errors = session('errors');
        $this->assertEquals('お名前を入力してください', $errors->first('name'));

        // ログインされていないことを確認する
        $this->assertGuest();
    }

    // メールアドレスが未入力の場合、バリデーションメッセージが表示されることを確認するテスト
    public function test_register_email_is_required(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertEquals('メールアドレスを入力してください', $errors->first('email'));

        // ログインされていないことを確認する
        $this->assertGuest();
    }

    // パスワードが8文字未満の場合、バリデーションメッセージが表示されることを確認するテスト
    public function test_register_password_must_be_at_least_8_characters(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => 'test-user@example.com',
            'password' => 'pass',
            'password_confirmation' => 'pass',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('password');

        $errors = session('errors');
        $this->assertEquals('パスワードは8文字以上で入力してください', $errors->first('password'));

        // ログインされていないことを確認する
        $this->assertGuest();
    }

    // パスワードが一致しない場合、バリデーションメッセージが表示されることを確認するテスト
    public function test_register_password_confirmation_does_not_match(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => 'test-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('password');

        $errors = session('errors');
        $this->assertEquals('パスワードと一致しません', $errors->first('password'));

        // ログインされていないことを確認する
        $this->assertGuest();
    }

    // パスワードが未入力の場合、バリデーションメッセージが表示されることを確認するテスト
    public function test_register_password_is_required(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => 'test-user@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('password');

        $errors = session('errors');
        $this->assertEquals('パスワードを入力してください', $errors->first('password'));

        // ログインされていないことを確認する
        $this->assertGuest();
    }

    // フォームに内容が入力されていた場合、データが正常に保存されることを確認するテスト
    public function test_register_user_successfully(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => 'test-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasNoErrors();

        // ユーザーが登録されたことを確認する
        $this->assertDatabaseHas('users', [
            'name' => 'テストユーザー',
            'email' => 'test-user@example.com',
            'role' => User::ROLE_USER,
        ]);

        $user = User::where('email', 'test-user@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password', $user->password));
    }
}
