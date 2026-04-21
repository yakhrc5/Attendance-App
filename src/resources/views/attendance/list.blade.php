@extends('layouts.app')

@section('title', '勤怠一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-list.css') }}">
@endsection

@section('content')
<div class="attendance-list">
    <div class="attendance-list__inner">
        {{-- 画面見出し --}}
        <div class="attendance-list__heading">
            <h1 class="attendance-list__title">勤怠一覧</h1>
        </div>

        {{-- 月切り替えナビ --}}
        <nav class="date-nav" aria-label="月切り替え">
            {{-- 前月へ移動 --}}
            <a
                class="date-nav__link"
                href="{{ route('attendance.list', ['month' => $previousMonth]) }}">
                ← 前月
            </a>

            {{-- 表示する月を選択するフォーム --}}
            <form
                action="{{ route('attendance.list') }}"
                method="GET"
                class="date-nav__form">
                {{-- 実際に送信する月入力 --}}
                <input
                    type="month"
                    name="month"
                    value="{{ $currentMonthInput }}"
                    class="date-nav__input"
                    id="month-picker"
                    onchange="this.form.submit()">

                {{-- カレンダーアイコン押下で月選択を開くボタン --}}
                <button
                    type="button"
                    class="date-nav__button"
                    data-date-trigger="month-picker"
                    aria-label="表示する月を選択">
                    <span class="date-nav__icon" aria-hidden="true">
                        <img
                            src="{{ asset('images/icons/calendar.svg') }}"
                            alt=""
                            class="date-nav__icon-svg">
                    </span>

                    {{-- 現在表示中の年月ラベル --}}
                    <span class="date-nav__label">{{ $currentMonthLabel }}</span>
                </button>
            </form>

            {{-- 翌月へ移動 --}}
            <a
                class="date-nav__link"
                href="{{ route('attendance.list', ['month' => $nextMonth]) }}">
                翌月 →
            </a>
        </nav>

        {{-- 勤怠一覧テーブル --}}
        <div class="attendance-list__table-wrap">
            <table class="attendance-list__table">
                <thead class="attendance-list__head">
                    <tr class="attendance-list__head-row">
                        <th class="attendance-list__head-cell" scope="col">日付</th>
                        <th class="attendance-list__head-cell" scope="col">出勤</th>
                        <th class="attendance-list__head-cell" scope="col">退勤</th>
                        <th class="attendance-list__head-cell" scope="col">休憩</th>
                        <th class="attendance-list__head-cell" scope="col">合計</th>
                        <th class="attendance-list__head-cell" scope="col">詳細</th>
                    </tr>
                </thead>

                <tbody class="attendance-list__body">
                    {{-- 対象月の全日付を1行ずつ表示する --}}
                    @foreach ($attendanceRows as $row)
                    <tr class="attendance-list__row">
                        {{-- 日付 --}}
                        <td class="attendance-list__cell">
                            {{ $row['workDate']->isoFormat('MM/DD(dd)') }}
                        </td>

                        {{-- 出勤時刻 --}}
                        <td class="attendance-list__cell">
                            {{ $row['clockIn'] }}
                        </td>

                        {{-- 退勤時刻 --}}
                        <td class="attendance-list__cell">
                            {{ $row['clockOut'] }}
                        </td>

                        {{-- 休憩時間 --}}
                        <td class="attendance-list__cell">
                            {{ $row['breakTime'] }}
                        </td>

                        {{-- 勤務合計時間 --}}
                        <td class="attendance-list__cell">
                            {{ $row['workTime'] }}
                        </td>

                        {{-- 勤怠データがある日だけ詳細リンクを表示する --}}
                        <td class="attendance-list__cell">
                            @if (!empty($row['detailUrl']))
                            <a
                                class="attendance-list__detail-link"
                                href="{{ $row['detailUrl'] }}">
                                詳細
                            </a>
                            @else
                            {{-- 勤怠データがない日は押せない見た目にする --}}
                            <span
                                class="attendance-list__detail-link attendance-list__detail-link--disabled"
                                aria-disabled="true">
                                詳細
                            </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const triggers = document.querySelectorAll('[data-date-trigger]');

        triggers.forEach(function(trigger) {
            const inputId = trigger.getAttribute('data-date-trigger');
            const targetInput = document.getElementById(inputId);

            if (!targetInput) {
                return;
            }

            trigger.addEventListener('click', function() {
                if (typeof targetInput.showPicker === 'function') {
                    targetInput.showPicker();
                    return;
                }

                targetInput.focus();
                targetInput.click();
            });
        });
    });
</script>
@endsection