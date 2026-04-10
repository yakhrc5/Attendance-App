<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 管理者ユーザーを作成する
        $this->createAdminUser();

        // 一般ユーザーを作成する
        $this->createGeneralUsers();
    }

    // 管理者ユーザーを登録する
    private function createAdminUser(): void
    {
        User::updateOrCreate(
            [
                'email' => 'admin@example.com',
            ],
            [
                'name' => '管理者',
                'role' => 'admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
            ]
        );
    }

    // 一般ユーザーを登録する
    private function createGeneralUsers(): void
    {
        $users = [
            [
                'name' => '山田 太郎',
                'email' => 'user1@example.com',
            ],
            [
                'name' => '佐藤 花子',
                'email' => 'user2@example.com',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                [
                    'email' => $user['email'],
                ],
                [
                    'name' => $user['name'],
                    'role' => 'user',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password123'),
                ]
            );
        }
    }
}

