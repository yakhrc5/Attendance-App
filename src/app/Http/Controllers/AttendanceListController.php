<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AttendanceListController extends Controller
{
    /**
     * 勤怠一覧画面を表示する
     *
     * @return View
     */
    public function index(): View
    {
        return view('attendance.list');
    }
}
