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
        <div class="admin-attendance-list__date-nav">
            <a
                class="admin-attendance-list__date-link admin-attendance-list__date-link--prev"
                href="{{ route('admin.attendance.list', ['date' => $previousDate]) }}">
                ← 前日
            </a>

            <p class="admin-attendance-list__date-current">
                {{-- カレンダーアイコン --}}
                <span class="admin-attendance-list__date-icon" aria-hidden="true">
                    <svg
                        class="admin-attendance-list__date-icon-svg"
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

                <span>{{ $currentDate }}</span>
            </p>

            <a
                class="admin-attendance-list__date-link admin-attendance-list__date-link--next"
                href="{{ route('admin.attendance.list', ['date' => $nextDate]) }}">
                翌日 →
            </a>
        </div>

        {{-- 勤怠一覧テーブル --}}
        <div class="admin-attendance-list__table-wrap">
            <table class="admin-attendance-list__table">
                <thead class="admin-attendance-list__head">
                    <tr class="admin-attendance-list__head-row">
                        <th class="admin-attendance-list__head-cell admin-attendance-list__head-cell--name">名前</th>
                        <th class="admin-attendance-list__head-cell">出勤</th>
                        <th class="admin-attendance-list__head-cell">退勤</th>
                        <th class="admin-attendance-list__head-cell">休憩</th>
                        <th class="admin-attendance-list__head-cell">合計</th>
                        <th class="admin-attendance-list__head-cell admin-attendance-list__head-cell--detail">詳細</th>
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