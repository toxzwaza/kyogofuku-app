@php
    $lpBrand = \App\Support\LpBrand::forEvent($event);
    // title：テンプレ側の title にブランド名が入っていなければ「 | ブランド名」を付ける
    $pageTitle = trim($__env->yieldContent('title', $event->title));
    $brandNameEscaped = e($lpBrand['name']);
    if (!str_contains(str_replace(' ', '', $pageTitle), str_replace(' ', '', $brandNameEscaped))) {
        $pageTitle .= ' | '.$brandNameEscaped;
    }
    // 索引の制御：送信完了ページ・終了したイベント・問い合わせフォームは noindex
    $noindex = request()->routeIs('event.reserve.success')
        || ($isEnded ?? false)
        || $event->form_type === 'contact';
    // 社内共通 GTM（GA4）。Inertia の LP（app.blade.php）と同じ ID
    $companyGtmId = config('services.company_gtm_id');
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta name="google-site-verification" content="N-GUynu2OYPqnKfgjqwAvpl5fveY1eepKUFSqsfoLok" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <meta name="theme-color" content="#1f2937">
    <meta name="description" content="@yield('description', $event->description ?? $event->title)">
    @if($noindex)
    <meta name="robots" content="noindex, follow">
    @endif

    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('title', $event->title)">
    <meta property="og:description" content="@yield('description', $event->description ?? $event->title)">
    <meta property="og:site_name" content="{{ $lpBrand['name'] }}">
    @php($ogImage = $event->images->first()->url ?? ($lpBrand['logo_url'] ?? null))
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif

    <title>{!! $pageTitle !!}</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-serif-jp:400,500,700|noto-sans-jp:400,500,700&display=swap" rel="stylesheet">

    @if(!empty($reservationTracking))
    {{-- 予約完了の計測（予約直後の1回だけ。GTM より先に積んでおく） --}}
    <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({!! json_encode($reservationTracking, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!});
    </script>
    @endif

    {{-- GTM（社内共通） --}}
    @if(!empty($companyGtmId))
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $companyGtmId }}');
    </script>
    @endif

    {{-- GTM（代理店・イベント別） --}}
    @if(!empty($event->gtm_id))
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $event->gtm_id }}');
    </script>
    @endif

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @yield('styles')
</head>
<body>
    @if(!empty($companyGtmId))
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $companyGtmId }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif
    @if(!empty($event->gtm_id))
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $event->gtm_id }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    @if(session('errors') && session('errors')->any())
        <div role="alert" style="background:#fff3f3;color:#a33;padding:.75rem 1rem;border-bottom:1px solid #f5c6c6;">
            <ul style="margin:0;padding-left:1.25rem;">
                @foreach(session('errors')->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')

    @yield('scripts')
</body>
</html>
