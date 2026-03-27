<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class RequestController extends Controller
{
    /**
     * 申請一覧画面を表示する
     *
     * @return View
     */
    public function index(): View
    {
        return view('requests.index');
    }
}
