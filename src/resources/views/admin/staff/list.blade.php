@extends('layouts.app')

@section('title', 'スタッフ一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-staff-list.css') }}">
@endsection

@section('content')
<div class="admin-staff-list">
    <div class="admin-staff-list__inner">
        {{-- 画面見出し --}}
        <div class="admin-staff-list__heading">
            <h1 class="admin-staff-list__title">スタッフ一覧</h1>
        </div>

        {{-- スタッフ一覧テーブル --}}
        <div class="admin-staff-list__table-wrap">
            <table class="admin-staff-list__table">
                <thead class="admin-staff-list__head">
                    <tr class="admin-staff-list__head-row">
                        <th class="admin-staff-list__head-cell admin-staff-list__head-cell--name">名前</th>
                        <th class="admin-staff-list__head-cell admin-staff-list__head-cell--email">メールアドレス</th>
                        <th class="admin-staff-list__head-cell admin-staff-list__head-cell--detail">月次勤怠</th>
                    </tr>
                </thead>

                <tbody class="admin-staff-list__body">
                    @forelse ($staffUsers as $staffUser)
                    <tr class="admin-staff-list__row">
                        <td class="admin-staff-list__cell admin-staff-list__cell--name">
                            {{ $staffUser->name }}
                        </td>
                        <td class="admin-staff-list__cell admin-staff-list__cell--email">
                            {{ $staffUser->email }}
                        </td>
                        <td class="admin-staff-list__cell admin-staff-list__cell--detail">
                            <a
                                href="{{ route('admin.attendance.staff', ['id' => $staffUser->id]) }}"
                                class="admin-staff-list__detail-link">
                                詳細
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr class="admin-staff-list__row">
                        <td
                            class="admin-staff-list__cell admin-staff-list__cell--empty"
                            colspan="3">
                            スタッフが登録されていません。
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection