@extends('layouts.app')

@section('title', '勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-detail.css') }}">
@endsection

@section('content')
@php
// 勤怠日を日本語表示用の Carbon に変換する
$workDate = \Carbon\Carbon::parse($attendance->work_date)->locale('ja');

// 日時を H:i 形式にそろえる共通関数
$formatTime = function ($value): string {
if (empty($value)) {
return '';
}

return \Carbon\Carbon::parse($value)->format('H:i');
};

// 編集可能画面で使う休憩行データを用意する
$editableBreakRows = collect(old('breaks', []));

// old() がない初回表示時は、現在の勤怠休憩データを使う
if ($editableBreakRows->isEmpty()) {
$editableBreakRows = $attendance->attendanceBreaks->map(function ($attendanceBreak) use ($formatTime) {
return [
'break_start_at' => $formatTime($attendanceBreak->break_start_at),
'break_end_at' => $formatTime($attendanceBreak->break_end_at),
];
});
}

// 編集可能な状態では、最後の行が未入力のときは追加せず、
// 最後の行に何か入っているときだけ空行を1つ追加する
if (! $isPending) {
$lastBreakRow = $editableBreakRows->last();
$hasNoRows = $editableBreakRows->isEmpty();

$lastRowHasInput = ! $hasNoRows && (
! empty($lastBreakRow['break_start_at']) ||
! empty($lastBreakRow['break_end_at'])
);

if ($hasNoRows || $lastRowHasInput) {
$editableBreakRows = $editableBreakRows->push([
'break_start_at' => '',
'break_end_at' => '',
]);
}
}

// 承認待ち画面で使う休憩行データを用意する
$pendingBreakRows = collect();

if ($pendingCorrectionRequest) {
$pendingBreakRows = $pendingCorrectionRequest->stampCorrectionBreaks->map(function ($stampCorrectionBreak) use ($formatTime) {
return [
'break_start_at' => $formatTime($stampCorrectionBreak->requested_break_start_at),
'break_end_at' => $formatTime($stampCorrectionBreak->requested_break_end_at),
];
});
}

// 承認待ち画面で表示する出退勤時刻を整形する
$pendingClockInAt = $pendingCorrectionRequest
? $formatTime($pendingCorrectionRequest->requested_clock_in_at)
: '';

$pendingClockOutAt = $pendingCorrectionRequest
? $formatTime($pendingCorrectionRequest->requested_clock_out_at)
: '';
@endphp

<div class="attendance-detail">
    <div class="attendance-detail__inner">
        {{-- 画面見出し --}}
        <div class="attendance-detail__heading">
            <h1 class="attendance-detail__title">勤怠詳細</h1>
        </div>

        @if (session('error'))
        <p class="attendance-detail__pending-message">
            {{ session('error') }}
        </p>
        @endif

        <div class="attendance-detail__card">
            @if (! $isPending)
            {{-- 修正可能画面では form として出力する --}}
            <form
                action="{{ route('admin.attendance.update', ['id' => $attendance->id]) }}"
                method="POST"
                class="attendance-detail__form-wrap">
                @csrf
                @method('PATCH')
                @else
                {{-- 承認待ち画面ではラッパーのみ出力する --}}
                <div class="attendance-detail__form-wrap attendance-detail__form-wrap--readonly">
                    @endif

                    <div class="attendance-detail__form">
                        {{-- 名前 --}}
                        <div class="attendance-detail__row">
                            <div class="attendance-detail__label">名前</div>
                            <div class="attendance-detail__value attendance-detail__value--text attendance-detail__value--name">
                                {{ $attendance->user->name }}
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

                        @if (! $isPending)
                        {{-- 出勤・退勤 --}}
                        <div class="attendance-detail__row attendance-detail__row--time-with-error">
                            <div class="attendance-detail__label">出勤・退勤</div>

                            <div class="attendance-detail__value attendance-detail__value--time attendance-detail__value--time-with-error">
                                <div class="attendance-detail__time-block">
                                    <div class="attendance-detail__time-group">
                                        <input
                                            type="time"
                                            name="clock_in_at"
                                            class="attendance-detail__time-input"
                                            value="{{ old('clock_in_at', $formatTime($attendance->clock_in_at)) }}">

                                        <span class="attendance-detail__separator">～</span>

                                        <input
                                            type="time"
                                            name="clock_out_at"
                                            class="attendance-detail__time-input"
                                            value="{{ old('clock_out_at', $formatTime($attendance->clock_out_at)) }}">
                                    </div>

                                    <div class="attendance-detail__error-area attendance-detail__error-area--time">
                                        <div class="attendance-detail__time-error attendance-detail__time-error--start">
                                            @error('clock_in_at')
                                            <p class="attendance-detail__error">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="attendance-detail__time-error attendance-detail__time-error--end">
                                            @error('clock_out_at')
                                            <p class="attendance-detail__error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 休憩 --}}
                        @foreach ($editableBreakRows as $index => $breakRow)
                        <div class="attendance-detail__row attendance-detail__row--break">
                            <div class="attendance-detail__label">
                                {{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}
                            </div>

                            <div class="attendance-detail__value attendance-detail__value--time attendance-detail__value--break">
                                <div class="attendance-detail__break-block">
                                    <div class="attendance-detail__time-group">
                                        <input
                                            type="time"
                                            name="breaks[{{ $index }}][break_start_at]"
                                            class="attendance-detail__time-input"
                                            value="{{ old("breaks.$index.break_start_at", $breakRow['break_start_at']) }}">

                                        <span class="attendance-detail__separator">～</span>

                                        <input
                                            type="time"
                                            name="breaks[{{ $index }}][break_end_at]"
                                            class="attendance-detail__time-input"
                                            value="{{ old("breaks.$index.break_end_at", $breakRow['break_end_at']) }}">
                                    </div>

                                    <div class="attendance-detail__error-area attendance-detail__error-area--break">
                                        @error("breaks.$index.break_time")
                                        <p class="attendance-detail__error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach

                        {{-- 備考 --}}
                        <div class="attendance-detail__row attendance-detail__row--note">
                            <div class="attendance-detail__label">備考</div>

                            <div class="attendance-detail__value attendance-detail__value--note">
                                <div class="attendance-detail__note-block">
                                    <textarea
                                        name="reason"
                                        class="attendance-detail__note-input"
                                        rows="3">{{ old('reason') }}</textarea>

                                    <div class="attendance-detail__error-area attendance-detail__error-area--note">
                                        @error('reason')
                                        <p class="attendance-detail__error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        @else
                        {{-- 出勤・退勤 --}}
                        <div class="attendance-detail__row">
                            <div class="attendance-detail__label">出勤・退勤</div>

                            <div class="attendance-detail__value attendance-detail__value--time">
                                <div class="attendance-detail__time-group attendance-detail__time-group--readonly">
                                    <span class="attendance-detail__time-text">
                                        {{ $pendingClockInAt }}
                                    </span>

                                    <span class="attendance-detail__separator">～</span>

                                    <span class="attendance-detail__time-text">
                                        {{ $pendingClockOutAt }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- 申請休憩 --}}
                        @forelse ($pendingBreakRows as $index => $breakRow)
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
                        @empty
                        <div class="attendance-detail__row">
                            <div class="attendance-detail__label">休憩</div>

                            <div class="attendance-detail__value attendance-detail__value--time">
                                <div class="attendance-detail__time-group attendance-detail__time-group--readonly">
                                    <span class="attendance-detail__time-text"></span>
                                    <span class="attendance-detail__separator">～</span>
                                    <span class="attendance-detail__time-text"></span>
                                </div>
                            </div>
                        </div>
                        @endforelse

                        {{-- 備考 --}}
                        <div class="attendance-detail__row attendance-detail__row--note">
                            <div class="attendance-detail__label">備考</div>

                            <div class="attendance-detail__value attendance-detail__value--note">
                                <div class="attendance-detail__note-text">
                                    {{ $pendingCorrectionRequest->reason }}
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    @if (! $isPending)
                    {{-- 修正ボタン --}}
                    <div class="attendance-detail__actions">
                        <button type="submit" class="attendance-detail__submit-button">
                            修正
                        </button>
                    </div>
            </form>
            @else
        </div>

        {{-- 承認待ちメッセージ --}}
        <p class="attendance-detail__pending-message">
            ※承認待ちのため修正はできません。
        </p>
        @endif
    </div>
</div>
</div>
@endsection