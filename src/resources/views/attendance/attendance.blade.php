@extends('layouts.app')

@section('title', '勤怠登録')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endsection

@section('content')
<section class="attendance">
    <div class="attendance__inner">
        <div class="attendance__content">
            <h1 class="visually-hidden">勤怠登録</h1>

            {{-- ステータス表示 --}}
            <p class="attendance__status">{{ $statusLabel }}</p>

            {{-- 日付表示 --}}
            <p class="attendance__date">{{ $currentDate }}</p>

            {{-- 時刻表示 --}}
            <p class="attendance__time">{{ $currentTime }}</p>

            {{-- ボタン・メッセージ表示エリア --}}
            <div class="attendance__actions">
                {{-- 勤務外：出勤ボタン --}}
                @if ($showClockIn)
                <form class="attendance__form" action="{{ route('attendance.clock-in') }}" method="POST">
                    @csrf
                    <button class="attendance__button attendance__button--primary" type="submit">出勤</button>
                </form>
                @endif

                {{-- 出勤中：退勤ボタン --}}
                @if ($showClockOut)
                <form class="attendance__form" action="{{ route('attendance.clock-out') }}" method="POST">
                    @csrf
                    <button class="attendance__button attendance__button--primary" type="submit">退勤</button>
                </form>
                @endif

                {{-- 出勤中：休憩入ボタン --}}
                @if ($showBreakStart)
                <form class="attendance__form" action="{{ route('attendance.break-start') }}" method="POST">
                    @csrf
                    <button class="attendance__button attendance__button--secondary" type="submit">休憩入</button>
                </form>
                @endif

                {{-- 休憩中：休憩戻ボタン --}}
                @if ($showBreakEnd)
                <form class="attendance__form" action="{{ route('attendance.break-end') }}" method="POST">
                    @csrf
                    <button class="attendance__button attendance__button--secondary" type="submit">休憩戻</button>
                </form>
                @endif

                {{-- 退勤後メッセージ --}}
                @if ($statusLabel === '退勤済')
                <p class="attendance__message">お疲れ様でした。</p>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection