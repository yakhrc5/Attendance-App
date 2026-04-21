<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class StaffListController extends Controller
{
    /**
     * 管理者用 スタッフ一覧画面
     */
    public function index(): View
    {
        // 一般ユーザーだけを一覧表示対象にする
        $staffUsers = User::query()
            ->where('role', User::ROLE_USER)
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'email',
            ]);

        // スタッフ一覧画面を表示する
        return view('admin.staff.list', [
            'staffUsers' => $staffUsers,
        ]);
    }
}
