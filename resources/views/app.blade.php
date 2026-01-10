<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead

    @php
        $gtmId = $page['props']['gtmId'] ?? null;
        $hasGtmId = $gtmId !== null && $gtmId !== '';
        $gtmIdJson = json_encode($gtmId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $hasGtmIdJson = json_encode($hasGtmId);
        $pagePropsJson = json_encode($page['props'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    @endphp

    <!-- GTM ID 取得状況のデバッグ -->
    <script>
        console.log('[GTM Debug] GTM ID取得状況:', {
            gtmId: {!! $gtmIdJson !!},
            hasGtmId: {!! $hasGtmIdJson !!},
            pageProps: {!! $pagePropsJson !!}
        });
    </script>

    @if($gtmId)
    <!-- Google Tag Manager -->
    <script>
        console.log('[GTM Debug] GTMタグを読み込みます:', '{{ $gtmId }}');
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
    @else
    <script>
        console.log('[GTM Debug] GTM IDが設定されていないため、GTMタグは読み込みません');
    </script>
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
</body>

</html>