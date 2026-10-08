<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="google-site-verification" content="N-GUynu2OYPqnKfgjqwAvpl5fveY1eepKUFSqsfoLok" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
    <meta name="theme-color" content="#1f2937">
    <link rel="manifest" href="/build/manifest.webmanifest">

    <title inertia>{{ isset($lpSeo) ? $lpSeo['title'] : (config('app.name') !== 'Laravel' ? config('app.name') : '京呉服平田・好一 イベント予約') }}</title>
    @if (request()->is('login') || request()->is('admin*'))
    <meta name="robots" content="noindex, nofollow">
    @endif

    @isset($lpSeo)
    <!-- イベントLP SEO（サーバー出力層） -->
    <meta name="description" content="{{ $lpSeo['description'] }}">
    <link rel="canonical" href="{{ $lpSeo['canonical'] }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $lpSeo['siteName'] }}">
    <meta property="og:title" content="{{ $lpSeo['title'] }}">
    <meta property="og:description" content="{{ $lpSeo['description'] }}">
    <meta property="og:url" content="{{ $lpSeo['canonical'] }}">
    @if($lpSeo['ogImage'])
    <meta property="og:image" content="{{ $lpSeo['ogImage'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $lpSeo['ogImage'] }}">
    @else
    <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="{{ $lpSeo['title'] }}">
    <meta name="twitter:description" content="{{ $lpSeo['description'] }}">
    <script type="application/ld+json">{!! json_encode($lpSeo['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endisset

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @routes(auth()->check() ? null : 'guest')
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead

    @php
        $gtmId = $page['props']['gtmId'] ?? null;
    @endphp

    @if($gtmId)
    <!-- Google Tag Manager -->
    <script>
        (function(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src =
                'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', '{{ $gtmId }}');
    </script>
    <!-- End Google Tag Manager -->
    @endif
</head>

<body class="font-sans antialiased">
    @if($gtmId)
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    @endif

    @inertia

    @isset($lpSeo)
    <!-- イベント概要（クローラー・JS非実行環境向けの可視テキスト層） -->
    <section style="max-width: 672px; margin: 0 auto; padding: 28px 20px 110px; color: #555; font-size: 13px; line-height: 1.9; background: #fff;">
        <h1 style="font-size: 15px; font-weight: 700; color: #333; margin-bottom: 8px;">{{ $lpSeo['summary']['title'] }}</h1>
        @if($lpSeo['summary']['period'])
        <p>開催期間: {{ $lpSeo['summary']['period'] }}</p>
        @endif
        @foreach($lpSeo['summary']['venues'] as $venue)
        <p>{{ $venue->name }}@if($venue->address)（{{ $venue->address }}）@endif</p>
        @endforeach
        @if($lpSeo['summary']['description'])
        <p style="margin-top: 8px;">{{ $lpSeo['summary']['description'] }}</p>
        @endif
        <p style="margin-top: 8px; color: #999;">主催: 京呉服 好一（岡山の振袖専門店）｜ご来店のご予約はページ内の「WEB来店予約」から承ります。</p>
    </section>
    @endisset
</body>

</html>