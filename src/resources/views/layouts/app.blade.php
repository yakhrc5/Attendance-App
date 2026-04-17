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
    {{-- ヘッダー --}}
    <header class="header">
        <div class="header__inner">
            {{-- ロゴ --}}
            <div class="header__logo">
                <a href="{{ route('login') }}" class="header__logo-link" aria-label="トップへ">
                    <img
                        src="{{ asset('images/logo.png') }}"
                        alt="COACHTECH"
                        class="header__logo-image">
                </a>
            </div>

            {{-- 認証系画面以外ではナビゲーションを表示 --}}
            @unless($isAuthHeader)
            @auth
            <nav class="header__nav" aria-label="グローバルナビゲーション">
                @if ($isAdminUser)
                {{-- 管理者ヘッダー --}}
                <a href="{{ route('admin.attendance.list') }}" class="header__nav-link">
                    勤怠一覧
                </a>
                <a href="{{ route('admin.staff.list') }}" class="header__nav-link">
                    スタッフ一覧
                </a>
                <a href="{{ route('stamp_correction_request.list') }}" class="header__nav-link">
                    申請一覧
                </a>
                @else
                {{-- 一般ユーザー --}}
                @if ($headerMode === 'after_clock_out')
                {{-- 退勤後ヘッダー --}}
                <a href="{{ route('attendance.list') }}" class="header__nav-link">
                    今月の出勤一覧
                </a>
                <a href="{{ route('stamp_correction_request.list') }}" class="header__nav-link">
                    申請一覧
                </a>
                @else
                {{-- 通常ヘッダー --}}
                <a href="{{ route('attendance.index') }}" class="header__nav-link">
                    勤怠
                </a>
                <a href="{{ route('attendance.list') }}" class="header__nav-link">
                    勤怠一覧
                </a>
                <a href="{{ route('stamp_correction_request.list') }}" class="header__nav-link">
                    申請
                </a>
                @endif
                @endif

                {{-- ログアウトボタン（管理者・一般共通） --}}
                <form action="{{ route('logout') }}" method="POST" class="header__logout-form">
                    @csrf
                    <button type="submit" class="header__nav-button">ログアウト</button>
                </form>
            </nav>
            @endauth
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