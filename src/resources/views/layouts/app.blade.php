<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Time and Attendance Management')</title>

    {{-- 共通CSS --}}
    <link rel="stylesheet" href="https://unpkg.com/sanitize.css">
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">

    {{-- フォント --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800&display=swap"
        rel="stylesheet">

    @yield('css')
</head>

<body>
    @php
        // 認証系画面では「ロゴのみヘッダー」にする
        $isAuthHeader = request()->routeIs(
            'login',
            'register',
            'verification.notice',
            'verification.verify'
        );
    @endphp

    {{-- ヘッダー --}}
    <header class="header">
        <div class="header__inner">
            {{-- ロゴ --}}
            <div class="header__logo">
                <a href="{{ url('/') }}" class="header__logo-link" aria-label="トップへ">
                    <img
                        src="{{ asset('images/logo.png') }}"
                        alt="COACHTECH"
                        class="header__logo-image">
                </a>
            </div>

            {{-- 認証系画面以外ではナビゲーションを表示 --}}
            @unless($isAuthHeader)
                <nav class="header__nav" aria-label="グローバルナビゲーション">
                    @auth
                        {{-- ※ パスは仮です。実際のルーティングに合わせて調整してください --}}
                        <a href="{{ url('/attendance') }}" class="header__nav-link">勤怠</a>
                        <a href="{{ url('/attendance/list') }}" class="header__nav-link">勤怠一覧</a>
                        <a href="{{ url('/requests') }}" class="header__nav-link">申請</a>

                        <form action="{{ route('logout') }}" method="POST" class="header__logout-form">
                            @csrf
                            <button type="submit" class="header__nav-button">ログアウト</button>
                        </form>
                    @endauth

                    @guest
                        <a href="{{ route('login') }}" class="header__nav-link">ログイン</a>
                    @endguest
                </nav>
            @endunless
        </div>
    </header>

    {{-- メインコンテンツ --}}
    <main class="layout__main">
        @yield('content')
    </main>

    @yield('js')
</body>

</html>