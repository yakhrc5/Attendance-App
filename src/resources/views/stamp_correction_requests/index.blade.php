@extends('layouts.app')

@section('title', '申請一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/stamp-correction-request-list.css') }}">
@endsection

@section('content')
<div class="request-list">
    <div class="request-list__inner">
        {{-- 見出し --}}
        <div class="request-list__heading">
            <h1 class="request-list__title">申請一覧</h1>
        </div>

        {{-- タブ --}}
        <div class="request-list__tabs" aria-label="申請ステータス切り替え">
            <a
                href="{{ route('stamp_correction_request.list', ['status' => 'pending']) }}"
                class="request-list__tab {{ $currentStatus === 'pending' ? 'request-list__tab--active' : '' }}">
                承認待ち
            </a>

            <a
                href="{{ route('stamp_correction_request.list', ['status' => 'approved']) }}"
                class="request-list__tab {{ $currentStatus === 'approved' ? 'request-list__tab--active' : '' }}">
                承認済み
            </a>
        </div>

        {{-- 一覧テーブル --}}
        <div class="request-list__table-wrap">
            <table class="request-list__table">
                <thead class="request-list__table-head">
                    <tr class="request-list__table-row request-list__table-row--head">
                        <th class="request-list__head-cell">状態</th>
                        <th class="request-list__head-cell">名前</th>
                        <th class="request-list__head-cell">対象日時</th>
                        <th class="request-list__head-cell">申請理由</th>
                        <th class="request-list__head-cell">申請日時</th>
                        <th class="request-list__head-cell">詳細</th>
                    </tr>
                </thead>

                <tbody class="request-list__table-body">
                    @forelse ($requests as $requestItem)
                    <tr class="request-list__table-row">
                        <td class="request-list__body-cell">{{ $requestItem['statusLabel'] }}</td>
                        <td class="request-list__body-cell">{{ $requestItem['userName'] }}</td>
                        <td class="request-list__body-cell">{{ $requestItem['workDate'] }}</td>
                        <td
                            class="request-list__body-cell request-list__body-cell--reason"
                            title="{{ $requestItem['reason'] }}">
                            {{ $requestItem['reason'] }}
                        </td>
                        <td class="request-list__body-cell">{{ $requestItem['appliedAt'] }}</td>
                        <td class="request-list__body-cell request-list__body-cell--detail">
                            @if (!empty($requestItem['detailUrl']))
                            <a href="{{ $requestItem['detailUrl'] }}" class="request-list__detail-link">詳細</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr class="request-list__table-row">
                        <td colspan="6" class="request-list__empty">
                            申請はありません。
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection