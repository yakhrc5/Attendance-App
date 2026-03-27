@extends('layouts.app')

@section('title', 'メール認証')

@section('css')
<link rel="stylesheet" href="{{ asset('css/verify-email.css') }}">
@endsection

@section('content')
<div class="verify">
    <div class="verify__container">
        <h1 class="visually-hidden">メール認証</h1>
        <p class="verify__message">
            登録していただいたメールアドレスに認証メールを送付しました。<br>
            メール認証を完了してください。
        </p>

        {{-- Mailhogへ（開発用） --}}
        <a
            href="http://localhost:8025"
            class="verify__open"
            target="_blank"
            rel="noopener">
            認証はこちらから
        </a>

        {{-- 再送 --}}
        <form method="POST" action="{{ route('verification.send') }}" class="verify__resend-form">
            @csrf

            <button type="submit" class="verify__resend">
                認証メールを再送する
            </button>
        </form>

        @if (session('status') === 'verification-link-sent')
        <p class="verify__status">認証メールを再送しました</p>
        @endif
    </div>
</div>
@endsection