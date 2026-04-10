@extends('layouts.app')

@section('title', '管理者ログイン')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endsection

@section('content')
<div class="auth">
    <div class="auth__container">
        <h1 class="auth__title">管理者ログイン</h1>

        <form method="POST" action="{{ route('login') }}" class="auth__form" novalidate>
            @csrf

            {{--
                Fortify 側で一般ユーザー / 管理者を判別するための hidden
                管理者ログイン画面からは role=admin を送る
            --}}
            <input type="hidden" name="role" value="admin">

            {{-- メールアドレス --}}
            <div class="auth__field">
                <label class="auth__label" for="email">メールアドレス</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    class="auth__input"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    autofocus
                    required>

                <div class="auth__error-area">
                    @error('email')
                    <p class="auth__error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- パスワード --}}
            <div class="auth__field">
                <label class="auth__label" for="password">パスワード</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    class="auth__input"
                    autocomplete="current-password"
                    required>

                <div class="auth__error-area">
                    @error('password')
                    <p class="auth__error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- 管理者ログインボタン --}}
            <button type="submit" class="auth__btn auth__btn--login">
                管理者ログインする
            </button>
        </form>
    </div>
</div>
@endsection