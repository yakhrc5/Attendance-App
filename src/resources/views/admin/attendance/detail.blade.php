@extends('layouts.app')

@section('title', '管理者勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-detail.css') }}">
@endsection

@section('content')
<div class="attendance-detail">
    <div class="attendance-detail__inner">
        {{-- 画面見出し --}}
        <div class="attendance-detail__heading">
            <h1 class="attendance-detail__title">勤怠詳細</h1>
        </div>

        {{-- 更新失敗時のメッセージ --}}
        @if (session('error'))
        <p class="attendance-detail__pending-message">
            {{ session('error') }}
        </p>
        @endif

        <div class="attendance-detail__card">
            @if (! $isPending)
            {{-- 修正可能な場合はフォームとして出力する --}}
            <form
                action="{{ route('admin.attendance.update', ['id' => $attendance->id]) }}"
                method="POST"
                class="attendance-detail__form-wrap">
                @csrf
                @method('PATCH')
                @else
                {{-- 承認待ちの場合は読み取り専用のラッパーで出力する --}}
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
                                            value="{{ old('clock_in_at', $clockInValue) }}">

                                        <span class="attendance-detail__separator">～</span>

                                        <input
                                            type="time"
                                            name="clock_out_at"
                                            class="attendance-detail__time-input"
                                            value="{{ old('clock_out_at', $clockOutValue) }}">
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
                                        {{ $pendingClockInValue }}
                                    </span>

                                    <span class="attendance-detail__separator">～</span>

                                    <span class="attendance-detail__time-text">
                                        {{ $pendingClockOutValue }}
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
            *承認待ちのため修正はできません。
        </p>
        @endif
    </div>
</div>
</div>
@endsection