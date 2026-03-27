@extends('layouts.app')

@section('title', '勤怠登録')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endsection

@section('content')
@php
// 画面表示用の初期値
// コントローラーから値が渡されたら、その値を優先して表示する
$statusLabel = $statusLabel ?? '勤務外';
$currentDate = $currentDate ?? '2023年6月1日(木)';
$currentTime = $currentTime ?? '08:00';
$actionLabel = $actionLabel ?? '出勤';
@endphp

<section class="attendance">
    <div class="attendance__inner">
        {{-- 見た目には出さないが、見出し構造は保持する --}}
        <h1 class="visually-hidden">勤怠登録</h1>

        {{-- 勤務状態 --}}
        <p class="attendance__status">{{ $statusLabel }}</p>

        {{-- 日付 --}}
        <p class="attendance__date">{{ $currentDate }}</p>

        {{-- 時刻 --}}
        <p class="attendance__time">{{ $currentTime }}</p>

        {{-- 打刻ボタン --}}
        {{-- ※ action先は仮です。実際のルーティングに合わせて調整してください --}}
        <form action="{{ url('/attendance') }}" method="POST" class="attendance__form">
            @csrf

            <button type="submit" class="attendance__button">
                {{ $actionLabel }}
            </button>
        </form>
    </div>
</section>
@endsection