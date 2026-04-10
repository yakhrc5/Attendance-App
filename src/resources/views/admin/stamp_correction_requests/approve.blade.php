@extends('layouts.app')

@section('title', '勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-detail.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-stamp-correction-approve.css') }}">
@endsection

@section('content')
@php
// 対象勤怠日を日本語表示用の Carbon に変換する
$workDate = \Carbon\Carbon::parse($stampCorrectionRequest->attendance->work_date)->locale('ja');

// 申請休憩行を表示用データに整形する
$breakRows = $stampCorrectionRequest->stampCorrectionBreaks->map(function ($stampCorrectionBreak) {
return [
'break_start_at' => optional($stampCorrectionBreak->requested_break_start_at)->format('H:i'),
'break_end_at' => optional($stampCorrectionBreak->requested_break_end_at)->format('H:i'),
];
});

// 休憩が1件もない場合でも1行は表示する
if ($breakRows->isEmpty()) {
$breakRows = collect([
[
'break_start_at' => '',
'break_end_at' => '',
],
]);
}
@endphp

<div class="attendance-detail">
    <div class="attendance-detail__inner">
        {{-- 画面見出し --}}
        <div class="attendance-detail__heading">
            <h1 class="attendance-detail__title">勤怠詳細</h1>
        </div>

        <div class="attendance-detail__card">
            <div class="attendance-detail__form-wrap attendance-detail__form-wrap--readonly">
                <div class="attendance-detail__form">
                    {{-- 名前 --}}
                    <div class="attendance-detail__row">
                        <div class="attendance-detail__label">名前</div>
                        <div class="attendance-detail__value attendance-detail__value--text attendance-detail__value--name">
                            {{ $stampCorrectionRequest->user->name }}
                        </div>
                    </div>

                    {{-- 日付 --}}
                    <div class="attendance-detail__row">
                        <div class="attendance-detail__label">日付</div>
                        <div class="attendance-detail__value attendance-detail__value--date">
                            <span class="attendance-detail__date-part attendance-detail__date-part--year">
                                {{ $workDate->format('Y年') }}
                            </span>
                            <span class="attendance-detail__date-part attendance-detail__date-part--day">
                                {{ $workDate->format('n月j日') }}
                            </span>
                        </div>
                    </div>

                    {{-- 出勤・退勤 --}}
                    <div class="attendance-detail__row">
                        <div class="attendance-detail__label">出勤・退勤</div>

                        <div class="attendance-detail__value attendance-detail__value--time">
                            <div class="attendance-detail__time-group attendance-detail__time-group--readonly">
                                <span class="attendance-detail__time-text">
                                    {{ optional($stampCorrectionRequest->requested_clock_in_at)->format('H:i') }}
                                </span>

                                <span class="attendance-detail__separator">～</span>

                                <span class="attendance-detail__time-text">
                                    {{ optional($stampCorrectionRequest->requested_clock_out_at)->format('H:i') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- 休憩 --}}
                    @foreach ($breakRows as $index => $breakRow)
                    <div class="attendance-detail__row">
                        <div class="attendance-detail__label">
                            {{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}
                        </div>

                        <div class="attendance-detail__value attendance-detail__value--time">
                            <div class="attendance-detail__time-group attendance-detail__time-group--readonly">
                                <span class="attendance-detail__time-text">
                                    {{ $breakRow['break_start_at'] }}
                                </span>

                                <span class="attendance-detail__separator">～</span>

                                <span class="attendance-detail__time-text">
                                    {{ $breakRow['break_end_at'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach

                    {{-- 備考 --}}
                    <div class="attendance-detail__row attendance-detail__row--note">
                        <div class="attendance-detail__label">備考</div>

                        <div class="attendance-detail__value attendance-detail__value--note">
                            <div class="attendance-detail__note-text">
                                {{ $stampCorrectionRequest->reason }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 承認ボタン / 承認済み表示 --}}
                <div class="attendance-detail__actions">
                    @if ($canApprove)
                    <form
                        action="{{ route('admin.stamp_correction_request.approve.update', ['id' => $stampCorrectionRequest->id]) }}"
                        method="POST">
                        @csrf
                        @method('PATCH')

                        <button type="submit" class="attendance-detail__submit-button">
                            承認
                        </button>
                    </form>
                    @else
                    <span class="attendance-detail__submit-button attendance-detail__submit-button--approved">
                        承認済み
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection