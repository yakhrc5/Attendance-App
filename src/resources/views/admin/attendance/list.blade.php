@extends('layouts.app')

@section('title', '勤怠一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-attendance-list.css') }}">
@endsection

@section('content')
<div class="admin-attendance-list">
    <div class="admin-attendance-list__inner">
        {{-- 画面見出し --}}
        <div class="admin-attendance-list__heading">
            <h1 class="admin-attendance-list__title">{{ $currentDateLabel }}の勤怠</h1>
        </div>

        {{-- 日付切り替えナビ --}}
        <nav class="date-nav" aria-label="日付切り替え">
            {{-- 前日へ --}}
            <a
                class="date-nav__link"
                href="{{ route('admin.attendance.list', ['date' => $previousDate]) }}">
                ← 前日
            </a>

            {{-- 日付選択フォーム --}}
            <form
                action="{{ route('admin.attendance.list') }}"
                method="GET"
                class="date-nav__form">
                {{-- 実際に送信する日付入力 --}}
                <input
                    type="date"
                    name="date"
                    value="{{ $currentDateInput }}"
                    class="date-nav__input"
                    id="date-picker"
                    onchange="this.form.submit()">

                {{-- カレンダーアイコン押下で日付選択を開く --}}
                <button
                    type="button"
                    class="date-nav__button"
                    data-date-trigger="date-picker"
                    aria-label="表示する日付を選択">
                    <span class="date-nav__icon" aria-hidden="true">
                        <img
                            src="{{ asset('images/icons/calendar.svg') }}"
                            alt=""
                            class="date-nav__icon-svg">
                    </span>

                    <span class="date-nav__label">{{ $currentDate }}</span>
                </button>
            </form>

            {{-- 翌日へ --}}
            <a
                class="date-nav__link"
                href="{{ route('admin.attendance.list', ['date' => $nextDate]) }}">
                翌日 →
            </a>
        </nav>

        {{-- 勤怠一覧テーブル --}}
        <div class="admin-attendance-list__table-wrap">
            <table class="admin-attendance-list__table">
                <thead class="admin-attendance-list__head">
                    <tr class="admin-attendance-list__head-row">
                        <th
                            class="admin-attendance-list__head-cell admin-attendance-list__head-cell--name"
                            scope="col">
                            名前
                        </th>
                        <th class="admin-attendance-list__head-cell" scope="col">出勤</th>
                        <th class="admin-attendance-list__head-cell" scope="col">退勤</th>
                        <th class="admin-attendance-list__head-cell" scope="col">休憩</th>
                        <th class="admin-attendance-list__head-cell" scope="col">合計</th>
                        <th
                            class="admin-attendance-list__head-cell admin-attendance-list__head-cell--detail"
                            scope="col">
                            詳細
                        </th>
                    </tr>
                </thead>

                <tbody class="admin-attendance-list__body">
                    @forelse ($attendanceRows as $row)
                    <tr class="admin-attendance-list__row">
                        <td class="admin-attendance-list__cell admin-attendance-list__cell--name">
                            {{ $row['staffName'] }}
                        </td>
                        <td class="admin-attendance-list__cell">
                            {{ $row['clockIn'] }}
                        </td>
                        <td class="admin-attendance-list__cell">
                            {{ $row['clockOut'] }}
                        </td>
                        <td class="admin-attendance-list__cell">
                            {{ $row['breakTime'] }}
                        </td>
                        <td class="admin-attendance-list__cell">
                            {{ $row['workTime'] }}
                        </td>
                        <td class="admin-attendance-list__cell admin-attendance-list__cell--detail">
                            <a
                                class="admin-attendance-list__detail-link"
                                href="{{ $row['detailUrl'] }}">
                                詳細
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr class="admin-attendance-list__row">
                        <td
                            class="admin-attendance-list__cell admin-attendance-list__cell--empty"
                            colspan="6">
                            該当する勤怠はありません。
                        </td>
                    </tr>
                    @endforelse
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