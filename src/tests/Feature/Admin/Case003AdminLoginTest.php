<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ログイン認証機能（管理者）
class Case003AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 勤怠データは入れず、ユーザーだけ作成する
        $this->seed(UserSeeder::class);
    }

    // メールアドレスが未入力の場合、バリデーションメッセージが表示されることを確認するテスト
    public function test_admin_login_requires_email(): void
    {
        $response = $this->from(route('admin.login'))->post(route('login'), [
            'email' => '',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertEquals('メールアドレスを入力してください', $errors->first('email'));

        // ログインされていないことを確認する
        $this->assertGuest();
    }

    // パスワードが未入力の場合、バリデーションメッセージが表示されることを確認するテスト
    public function test_admin_login_requires_password(): void
    {
        $response = $this->from(route('admin.login'))->post(route('login'), [
            'email' => 'admin@example.com',
            'password' => '',
            'role' => User::ROLE_ADMIN,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('password');

        $errors = session('errors');
        $this->assertEquals('パスワードを入力してください', $errors->first('password'));

        // ログインされていないことを確認する
        $this->assertGuest();
    }

    // 登録内容と一致しない場合、バリデーションメッセージが表示されることを確認するテスト
    public function test_admin_login_fails_with_invalid_credentials(): void
    {
        $response = $this->from(route('admin.login'))->post(route('login'), [
            'email' => 'not-found@example.com',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertEquals('ログイン情報が登録されていません', $errors->first('email'));

        // ログインされていないことを確認する
        $this->assertGuest();
    }
}
