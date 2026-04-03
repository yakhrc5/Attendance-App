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
        <div class="attendance-list__month-nav">
            <a
                class="attendance-list__month-link attendance-list__month-link--prev"
                href="{{ route('attendance.list', ['month' => $previousMonth]) }}">
                ← 前月
            </a>

            <p class="attendance-list__month-current">
                {{-- カレンダーアイコン --}}
                <span class="attendance-list__month-icon" aria-hidden="true">
                    <svg
                        class="attendance-list__month-icon-svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <rect
                            x="3"
                            y="5"
                            width="18"
                            height="16"
                            rx="2"
                            stroke="currentColor"
                            stroke-width="2" />
                        <path
                            d="M8 3V7"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round" />
                        <path
                            d="M16 3V7"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round" />
                        <path
                            d="M3 10H21"
                            stroke="currentColor"
                            stroke-width="2" />
                    </svg>
                </span>

                <span>{{ $currentMonthLabel }}</span>
            </p>

            <a
                class="attendance-list__month-link attendance-list__month-link--next"
                href="{{ route('attendance.list', ['month' => $nextMonth]) }}">
                翌月 →
            </a>
        </div>

        {{-- 勤怠一覧テーブル --}}
        <div class="attendance-list__table-wrap">
            <table class="attendance-list__table">
                <thead class="attendance-list__head">
                    <tr class="attendance-list__head-row">
                        <th class="attendance-list__head-cell">日付</th>
                        <th class="attendance-list__head-cell">出勤</th>
                        <th class="attendance-list__head-cell">退勤</th>
                        <th class="attendance-list__head-cell">休憩</th>
                        <th class="attendance-list__head-cell">合計</th>
                        <th class="attendance-list__head-cell">詳細</th>
                    </tr>
                </thead>

                <tbody class="attendance-list__body">
                    {{-- 対象月の全日付を表示する --}}
                    @foreach ($attendanceRows as $row)
                    @php
                    /** @var \Carbon\Carbon $workDate */
                    $workDate = $row['workDate'];
                    @endphp

                    <tr class="attendance-list__row">
                        <td class="attendance-list__cell">
                            {{ $workDate->isoFormat('MM/DD(dd)') }}
                        </td>
                        <td class="attendance-list__cell">
                            {{ $row['clockIn'] }}
                        </td>
                        <td class="attendance-list__cell">
                            {{ $row['clockOut'] }}
                        </td>
                        <td class="attendance-list__cell">
                            {{ $row['breakTime'] }}
                        </td>
                        <td class="attendance-list__cell">
                            {{ $row['workTime'] }}
                        </td>
                        <td class="attendance-list__cell">
                            @if(!empty($row['detailUrl']))
                            {{-- 勤怠データがある日は詳細画面へ遷移できる --}}
                            <a
                                class="attendance-list__detail-link"
                                href="{{ $row['detailUrl'] }}">
                                詳細
                            </a>
                            @else
                            {{-- 勤怠データがない日はグレーアウト表示にする --}}
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