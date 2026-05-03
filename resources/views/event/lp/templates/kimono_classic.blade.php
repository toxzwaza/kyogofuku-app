@extends('event.lp.layouts.base')

@section('title', $event->title)
@section('description', $event->description ?? $event->title)

@section('styles')
<style>
    :root {
        --kc-bg: #fdfaf3;
        --kc-bg-2: #f5ede0;
        --kc-text: #2b1d12;
        --kc-text-soft: #6e5944;
        --kc-accent: #8c2f25;
        --kc-accent-soft: #c46a5d;
        --kc-line: #d9c8a9;
        --kc-shadow: 0 6px 24px rgba(0,0,0,.08);
        --kc-radius: 6px;
    }
    *,*::before,*::after { box-sizing: border-box; }
    body { margin:0; background:var(--kc-bg); color:var(--kc-text);
        font-family: 'Noto Sans JP', system-ui, -apple-system, sans-serif;
        line-height:1.7; }
    a { color: var(--kc-accent); }
    .kc-wrap { max-width: 920px; margin: 0 auto; padding: 0 20px; }
    .kc-hero { background: linear-gradient(135deg, var(--kc-bg-2) 0%, var(--kc-bg) 100%);
        padding: 64px 0 48px; border-bottom: 1px solid var(--kc-line); text-align:center; }
    .kc-hero__eyebrow { font-family:'Noto Serif JP', serif; letter-spacing:.4em; font-size:.85rem;
        color: var(--kc-accent); margin: 0 0 12px; }
    .kc-hero h1 { font-family:'Noto Serif JP', serif; font-weight:700; font-size: clamp(1.6rem,4vw,2.4rem);
        margin: 0 0 12px; }
    .kc-hero__period { color: var(--kc-text-soft); font-size: .95rem; }
    .kc-section { padding: 56px 0; border-bottom: 1px solid var(--kc-line); }
    .kc-section h2 { font-family:'Noto Serif JP', serif; font-size: 1.35rem; margin: 0 0 24px;
        text-align:center; position:relative; padding-bottom:14px; }
    .kc-section h2::after { content:''; display:block; width:48px; height:2px; background:var(--kc-accent);
        position:absolute; left:50%; bottom:0; transform:translateX(-50%); }
    .kc-prose { font-size: 1rem; color: var(--kc-text-soft); }
    .kc-prose p { margin: 0 0 1em; }
    .kc-images { display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
    .kc-images img { width:100%; height: 200px; object-fit: cover; border-radius: var(--kc-radius);
        box-shadow: var(--kc-shadow); }

    .kc-form { background: #fff; border:1px solid var(--kc-line); border-radius: var(--kc-radius);
        padding: 32px; box-shadow: var(--kc-shadow); }
    .lp-field { margin-bottom: 22px; }
    .lp-field__label { display:block; font-weight:600; margin-bottom:6px; font-size:.95rem; }
    .lp-field__required { display:inline-block; margin-left:8px; padding:2px 8px;
        background:var(--kc-accent); color:#fff; font-size:.7rem; border-radius:3px; }
    .lp-field__input { width:100%; padding: 10px 12px; border:1px solid var(--kc-line);
        border-radius: 4px; font-size:1rem; background:#fff; color:var(--kc-text); font-family:inherit; }
    .lp-field__input:focus { outline:none; border-color: var(--kc-accent); box-shadow: 0 0 0 3px rgba(140,47,37,.12); }
    .lp-field__textarea { resize: vertical; }
    .lp-field__choices { display:flex; flex-wrap:wrap; gap: 12px 20px; }
    .lp-field__choice { display:flex; align-items:center; gap:8px; cursor:pointer; }
    .lp-field__choice--single { font-weight:normal; }
    .lp-field__help { color: var(--kc-text-soft); font-size:.85rem; margin: 6px 0 0; }
    .lp-field__error { color: #c0392b; font-size:.85rem; margin: 6px 0 0; }
    .lp-field--error .lp-field__input { border-color:#c0392b; }

    .kc-submit { display:block; width:100%; padding: 14px 18px; background: var(--kc-accent);
        color:#fff; border:0; font-size:1.05rem; font-weight:600; cursor:pointer; border-radius:4px;
        transition: background .2s; }
    .kc-submit:hover { background: var(--kc-accent-soft); }
    .kc-submit[disabled] { opacity:.6; cursor: not-allowed; }

    .kc-footer { padding: 32px 0; text-align:center; color: var(--kc-text-soft); font-size:.85rem; }

    @media (max-width: 600px) {
        .kc-section { padding: 40px 0; }
        .kc-form { padding: 20px; }
    }

    [x-cloak] { display: none !important; }
</style>
@endsection

@section('content')
<header class="kc-hero">
    <div class="kc-wrap">
        <p class="kc-hero__eyebrow">KYOGOFUKU HIRATA</p>
        <h1>{{ $event->title }}</h1>
        @if($event->start_at || $event->end_at)
            <p class="kc-hero__period">
                @if($event->start_at){{ \Carbon\Carbon::parse($event->start_at)->format('Y年n月j日') }}@endif
                @if($event->start_at && $event->end_at) 〜 @endif
                @if($event->end_at){{ \Carbon\Carbon::parse($event->end_at)->format('Y年n月j日') }}@endif
            </p>
        @endif
    </div>
</header>

@if($event->description)
<section class="kc-section">
    <div class="kc-wrap">
        <h2>イベント概要</h2>
        <div class="kc-prose">
            {!! nl2br(e($event->description)) !!}
        </div>
    </div>
</section>
@endif

@if($event->images->isNotEmpty())
<section class="kc-section">
    <div class="kc-wrap">
        <h2>ギャラリー</h2>
        <div class="kc-images">
            @foreach($event->images->take(6) as $image)
                <img src="{{ $image->url }}" alt="{{ $image->alt ?? $event->title }}" loading="lazy">
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="kc-section" id="reserve">
    <div class="kc-wrap">
        <h2>ご予約・お問い合わせ</h2>

        @if($isEnded)
            <p style="text-align:center;color:var(--kc-text-soft);">
                {{ $event->ended_message_text ?? 'このイベントは終了しました。' }}
            </p>
        @elseif(empty($formSchema))
            <p style="text-align:center;color:var(--kc-text-soft);">
                フォームが設定されていません。
            </p>
        @else
            @php
                $alpineInit = collect($formSchema)->mapWithKeys(function ($f) {
                    $key = $f['key'] ?? '';
                    $isMulti = ($f['type'] ?? null) === 'checkbox' && !empty($f['options']);
                    return [$key => $isMulti ? [] : ''];
                })->toArray();
            @endphp
            <form
                class="kc-form"
                method="POST"
                action="{{ route('blade-lp.reserve', $event) }}"
                x-data="{ submitting: false, values: @js($alpineInit) }"
                @submit="submitting = true"
                novalidate
            >
                @csrf

                @foreach($formSchema as $field)
                    @php
                        $showIf = $field['show_if'] ?? null;
                        $wrapAttrs = '';
                        if (is_array($showIf) && !empty($showIf['key'])) {
                            $depKey = $showIf['key'];
                            $op = $showIf['op'] ?? 'not_empty';
                            $value = $showIf['value'] ?? null;
                            if ($op === 'equals' && $value !== null) {
                                $expr = "values['{$depKey}'] === ".json_encode($value);
                            } else {
                                $expr = "values['{$depKey}'] !== '' && values['{$depKey}'] !== null && !(Array.isArray(values['{$depKey}']) && values['{$depKey}'].length === 0)";
                            }
                            $wrapAttrs = ' x-show="'.htmlspecialchars($expr, ENT_QUOTES).'" x-cloak';
                        }
                    @endphp
                    <div{!! $wrapAttrs !!}>
                        <x-lp-form.field :field="$field" />
                    </div>
                @endforeach

                <button type="submit" class="kc-submit" :disabled="submitting">
                    <span x-show="!submitting">送信する</span>
                    <span x-show="submitting" x-cloak>送信中...</span>
                </button>
            </form>
        @endif
    </div>
</section>

<footer class="kc-footer">
    <div class="kc-wrap">
        © {{ date('Y') }} 京呉服平田
    </div>
</footer>
@endsection
