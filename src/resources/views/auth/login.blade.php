@extends('layouts.app')

@section('title', 'ログイン')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endsection

@section('content')
<div class="auth">
    <div class="auth__container">
        <h1 class="auth__title">ログイン</h1>

        <form method="POST" action="{{ route('login') }}" class="auth__form" novalidate>
            @csrf
            <input type="hidden" name="role" value="{{ \App\Models\User::ROLE_USER }}">

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
            {{-- ログインボタン --}}
            <button type="submit" class="auth__btn auth__btn--login">ログインする</button>

            {{-- 会員登録リンク --}}
            <p class="auth__link">
                <a href="{{ route('register') }}" class="auth__link-anchor">会員登録はこちら</a>
            </p>
        </form>
    </div>
</div>
@endsection