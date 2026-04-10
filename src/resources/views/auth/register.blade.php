@extends('layouts.app')

@section('title', '会員登録')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endsection

@section('content')
<div class="auth auth--register">
    <div class="auth__container">
        <h1 class="auth__title">会員登録</h1>

        <form method="POST" action="{{ route('register') }}" class="auth__form" novalidate>
            @csrf

            {{-- 名前 --}}
            <div class="auth__field">
                <label class="auth__label" for="name">名前</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    class="auth__input"
                    value="{{ old('name') }}"
                    autocomplete="name"
                    autofocus
                    required>
                <div class="auth__error-area">
                    @error('name')
                    <p class="auth__error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

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
                    autocomplete="new-password"
                    required>
                <div class="auth__error-area">
                    @error('password')
                    <p class="auth__error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- パスワード確認 --}}
            <div class="auth__field">
                <label class="auth__label" for="password_confirmation">パスワード確認</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    class="auth__input"
                    autocomplete="new-password"
                    required>
                <div class="auth__error-area">
                    @error('password_confirmation')
                    <p class="auth__error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- 登録ボタン --}}
            <button type="submit" class="auth__btn auth__btn--register">登録する</button>

            {{-- ログインリンク --}}
            <p class="auth__link">
                <a href="{{ route('login') }}" class="auth__link-anchor">ログインはこちら</a>
            </p>
        </form>
    </div>
</div>
@endsection