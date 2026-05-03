@extends('event.lp.layouts.base')

@section('title', '大創業祭｜京呉服 平田 福井店')
@section('description', 'タンスのきものとジュエリー、まるごと整理。京呉服 平田 大創業祭 5/22(金)〜5/25(月) 福井店にて開催。事前持込キャンペーン、紫真珠特別企画、ジュエリー高価下取り、すくい取り。')

@section('styles')
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=noto-serif-jp:400,500,700,900|noto-sans-jp:400,500,700|shippori-mincho-b1:400,500,700,800,900|yuji-syuku:400&display=swap" rel="stylesheet">
<style>
    :root {
        --ds-bg:        #fdf6f0;
        --ds-bg-2:      #f7e6d4;
        --ds-paper:     #fffdf9;
        --ds-pink:      #f5d4dc;
        --ds-pink-2:    #e8a3b8;
        --ds-akane:     #b03641;
        --ds-akane-d:   #8a2730;
        --ds-gold:      #c9a961;
        --ds-gold-d:    #9c8240;
        --ds-sumi:      #2b1d1d;
        --ds-sumi-soft: #5a4540;
        --ds-line:      #d8b89a;
    }
    * , *::before, *::after { box-sizing: border-box; }
    body {
        margin: 0;
        background:
            radial-gradient(circle at 15% 20%, rgba(245, 212, 220, .55), transparent 40%),
            radial-gradient(circle at 85% 80%, rgba(232, 163, 184, .35), transparent 50%),
            var(--ds-bg);
        color: var(--ds-sumi);
        font-family: 'Noto Sans JP', 'Hiragino Sans', system-ui, sans-serif;
        line-height: 1.85;
        -webkit-font-smoothing: antialiased;
    }
    img { max-width: 100%; display: block; }
    a { color: var(--ds-akane); }

    .ds-wrap { max-width: 920px; margin: 0 auto; padding: 0 22px; }

    /* === ヘッダ === */
    .ds-header { padding: 18px 0; text-align: center; border-bottom: 1px solid var(--ds-line); }
    .ds-header__brand { font-family: 'Shippori Mincho B1', 'Noto Serif JP', serif;
        font-size: .9rem; letter-spacing: .35em; color: var(--ds-sumi-soft); }
    .ds-header__brand-jp { font-family: 'Yuji Syuku', 'Shippori Mincho B1', serif; font-size: 1.4rem;
        color: var(--ds-akane); margin-top: 4px; letter-spacing: .15em; }

    /* === ヒーロー === */
    .ds-hero { position: relative; padding: 64px 0 56px; text-align: center; overflow: hidden; }
    .ds-hero::before, .ds-hero::after {
        content: ""; position: absolute; width: 220px; height: 220px; border-radius: 50%;
        background: radial-gradient(circle, rgba(232,163,184,.4), transparent 70%); pointer-events: none;
    }
    .ds-hero::before { top: -60px; left: -60px; }
    .ds-hero::after  { bottom: -80px; right: -60px; }
    .ds-hero__inner { position: relative; }
    .ds-hero__eyebrow {
        font-family: 'Shippori Mincho B1', serif; font-size: .85rem; letter-spacing: .55em;
        color: var(--ds-akane-d); margin: 0 0 22px;
    }
    .ds-hero__title {
        font-family: 'Yuji Syuku', 'Shippori Mincho B1', serif;
        font-weight: 400; font-size: clamp(2.6rem, 7vw, 4.4rem); margin: 0 0 6px;
        color: var(--ds-akane); letter-spacing: .1em;
        text-shadow: 0 2px 0 #fff, 0 4px 14px rgba(176,54,65,.18);
    }
    .ds-hero__title-line {
        display: inline-block; padding: 0 12px; position: relative;
    }
    .ds-hero__title-line::before, .ds-hero__title-line::after {
        content: ""; position: absolute; top: 50%; width: 60px; height: 1px; background: var(--ds-gold);
    }
    .ds-hero__title-line::before { right: 100%; }
    .ds-hero__title-line::after  { left: 100%; }
    .ds-hero__period {
        display: inline-flex; align-items: baseline; gap: 14px; margin-top: 22px;
        font-family: 'Shippori Mincho B1', serif; font-size: 1.05rem; color: var(--ds-sumi);
        background: var(--ds-paper); border: 1px solid var(--ds-line); border-radius: 999px;
        padding: 12px 28px; box-shadow: 0 6px 20px rgba(176,54,65,.06);
    }
    .ds-hero__period strong { font-size: 1.6rem; color: var(--ds-akane); font-weight: 700; }
    .ds-hero__catch {
        margin-top: 32px; font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        font-size: clamp(1.15rem, 3vw, 1.6rem); color: var(--ds-sumi);
    }
    .ds-hero__catch span { color: var(--ds-akane); }
    .ds-hero__catch-emp {
        display: inline-block; margin-top: 14px; padding: 6px 30px;
        background: var(--ds-akane); color: #fff; font-size: 1.5em; font-weight: 900;
        font-family: 'Shippori Mincho B1', serif; letter-spacing: .15em;
        clip-path: polygon(6% 0, 94% 0, 100% 50%, 94% 100%, 6% 100%, 0% 50%);
    }
    .ds-hero__cta { margin-top: 36px; }

    .ds-cta-button {
        display: inline-block; padding: 18px 56px; background: var(--ds-akane); color: #fff;
        font-family: 'Shippori Mincho B1', serif; font-weight: 700; font-size: 1.15rem;
        letter-spacing: .25em; text-decoration: none; border-radius: 4px;
        box-shadow: 0 8px 24px rgba(176,54,65,.3); transition: all .25s;
        border: 1px solid var(--ds-gold); position: relative;
    }
    .ds-cta-button:hover { background: var(--ds-akane-d); transform: translateY(-2px);
        box-shadow: 0 12px 32px rgba(176,54,65,.4); }
    .ds-cta-button::after { content: " ▶"; font-size: .8em; margin-left: 4px; }

    /* === セクション共通 === */
    .ds-section { padding: 64px 0; position: relative; }
    .ds-section--paper { background: var(--ds-paper); border-top: 1px solid var(--ds-line); border-bottom: 1px solid var(--ds-line); }
    .ds-section--accent { background: linear-gradient(180deg, var(--ds-bg-2) 0%, var(--ds-bg) 100%); }
    .ds-section__heading {
        text-align: center; margin: 0 0 44px; font-family: 'Shippori Mincho B1', serif;
        font-weight: 700; font-size: clamp(1.4rem, 3.5vw, 1.85rem); color: var(--ds-sumi);
        position: relative; display: flex; align-items: center; justify-content: center; gap: 18px;
    }
    .ds-section__heading::before, .ds-section__heading::after {
        content: ""; flex: 0 0 auto; width: 36px; height: 1px;
        background: linear-gradient(90deg, transparent, var(--ds-gold), transparent);
    }
    .ds-section__lead { text-align: center; color: var(--ds-sumi-soft); max-width: 720px;
        margin: -20px auto 32px; font-family: 'Shippori Mincho B1', serif; }

    /* === メインメッセージ === */
    .ds-main-msg { text-align: center; padding: 56px 24px;
        background: linear-gradient(135deg, #fff 0%, #fdeae2 100%);
        border: 2px solid var(--ds-gold); border-radius: 6px; box-shadow: 0 10px 30px rgba(176,54,65,.08);
    }
    .ds-main-msg__lead { font-family: 'Shippori Mincho B1', serif; font-size: 1.1rem;
        color: var(--ds-sumi-soft); margin: 0 0 8px; }
    .ds-main-msg__title { font-family: 'Yuji Syuku', 'Shippori Mincho B1', serif;
        font-size: clamp(1.8rem, 5vw, 2.6rem); color: var(--ds-akane); margin: 0; letter-spacing: .08em; }

    /* === 特典・カード === */
    .ds-cards { display: grid; gap: 22px; grid-template-columns: 1fr; }
    @media (min-width: 720px) { .ds-cards--2 { grid-template-columns: 1fr 1fr; } }

    .ds-card { background: var(--ds-paper); border: 1px solid var(--ds-line); border-radius: 6px;
        padding: 30px 28px; box-shadow: 0 6px 20px rgba(0,0,0,.04); position: relative; overflow: hidden; }
    .ds-card::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        background: linear-gradient(90deg, var(--ds-akane) 0%, var(--ds-gold) 50%, var(--ds-akane) 100%);
    }
    .ds-card__badge { display: inline-block; padding: 4px 14px; background: var(--ds-akane); color: #fff;
        font-family: 'Shippori Mincho B1', serif; font-size: .85rem; font-weight: 700;
        letter-spacing: .15em; border-radius: 2px; margin-bottom: 14px; }
    .ds-card__title { font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        font-size: 1.25rem; color: var(--ds-sumi); margin: 0 0 12px; }
    .ds-card__title strong { color: var(--ds-akane); }
    .ds-card__price { font-family: 'Shippori Mincho B1', serif; font-size: 2rem; color: var(--ds-akane);
        font-weight: 700; margin: 8px 0; }
    .ds-card__price small { font-size: .55em; color: var(--ds-sumi-soft); margin-left: 4px; }
    .ds-card__note { font-size: .85rem; color: var(--ds-sumi-soft); margin: 0; }
    .ds-card__body { color: var(--ds-sumi); }

    /* === 紫真珠 === */
    .ds-pearl { background: linear-gradient(135deg, #4a2c5e 0%, #6b3d7e 100%); color: #fff;
        padding: 56px 32px; border-radius: 6px; text-align: center;
        box-shadow: 0 16px 40px rgba(74,44,94,.3); position: relative; overflow: hidden; }
    .ds-pearl::before { content: ""; position: absolute; inset: 8px; border: 1px solid rgba(255,255,255,.3);
        border-radius: 4px; pointer-events: none; }
    .ds-pearl__eyebrow { font-family: 'Shippori Mincho B1', serif; letter-spacing: .55em;
        font-size: .8rem; opacity: .8; margin: 0 0 14px; }
    .ds-pearl__title { font-family: 'Yuji Syuku', 'Shippori Mincho B1', serif;
        font-size: clamp(2rem, 5vw, 2.8rem); margin: 0; letter-spacing: .1em; }
    .ds-pearl__title small { display: block; font-family: 'Shippori Mincho B1', serif;
        font-size: .35em; letter-spacing: .4em; margin-top: 8px; opacity: .85; }
    .ds-pearl__body { margin-top: 24px; font-size: 1rem; max-width: 600px; margin-left: auto;
        margin-right: auto; line-height: 2; }

    /* === 下取り === */
    .ds-takedori { text-align: center; padding: 40px 24px; background: var(--ds-paper);
        border: 1px dashed var(--ds-akane); border-radius: 6px; }
    .ds-takedori__title { font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        font-size: clamp(1.3rem, 3.5vw, 1.8rem); color: var(--ds-akane); margin: 0 0 8px; }
    .ds-takedori__title em { font-style: normal; color: var(--ds-akane-d);
        background: linear-gradient(transparent 60%, #ffe8a8 60%); padding: 0 4px; }

    /* === すくい取り === */
    .ds-sukui { text-align: center; padding: 36px 24px;
        background: linear-gradient(180deg, #fff 0%, #fff7e8 100%);
        border: 1px solid var(--ds-gold); border-radius: 6px; }
    .ds-sukui__label { display: inline-block; padding: 4px 16px; background: var(--ds-gold);
        color: #fff; font-family: 'Shippori Mincho B1', serif; font-size: .85rem;
        letter-spacing: .2em; border-radius: 2px; margin-bottom: 14px; }
    .ds-sukui__title { font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        font-size: 1.4rem; color: var(--ds-sumi); margin: 0 0 6px; }
    .ds-sukui__sub { color: var(--ds-akane); font-weight: 700; }

    /* === フォーム === */
    .ds-form { background: var(--ds-paper); border: 1px solid var(--ds-line); border-radius: 6px;
        padding: 36px 32px; box-shadow: 0 12px 36px rgba(176,54,65,.08); max-width: 640px;
        margin: 0 auto; }
    .ds-form__intro { text-align: center; margin: 0 0 24px; color: var(--ds-sumi-soft);
        font-family: 'Shippori Mincho B1', serif; }

    .lp-field { margin-bottom: 22px; }
    .lp-field__label { display: block; font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        margin-bottom: 8px; font-size: .98rem; color: var(--ds-sumi); }
    .lp-field__required { display: inline-block; margin-left: 8px; padding: 2px 8px;
        background: var(--ds-akane); color: #fff; font-size: .7rem; border-radius: 2px; font-weight: 700; }
    .lp-field__input { width: 100%; padding: 11px 14px; border: 1px solid var(--ds-line);
        border-radius: 4px; font-size: 1rem; background: #fff; color: var(--ds-sumi);
        font-family: inherit; transition: border-color .15s, box-shadow .15s; }
    .lp-field__input:focus { outline: none; border-color: var(--ds-akane);
        box-shadow: 0 0 0 3px rgba(176,54,65,.12); }
    .lp-field__textarea { resize: vertical; min-height: 110px; }
    .lp-field__choices { display: flex; flex-wrap: wrap; gap: 12px 22px; }
    .lp-field__choice { display: flex; align-items: center; gap: 8px; cursor: pointer;
        font-family: 'Shippori Mincho B1', serif; }
    .lp-field__help { color: var(--ds-sumi-soft); font-size: .85rem; margin: 6px 0 0; }
    .lp-field__error { color: var(--ds-akane-d); font-size: .85rem; margin: 6px 0 0; font-weight: 700; }
    .lp-field--error .lp-field__input { border-color: var(--ds-akane); background: #fff5f5; }

    .ds-submit { display: block; width: 100%; padding: 18px; background: var(--ds-akane);
        color: #fff; border: 0; font-family: 'Shippori Mincho B1', serif; font-size: 1.15rem;
        font-weight: 700; cursor: pointer; border-radius: 4px; letter-spacing: .25em;
        transition: background .2s, transform .2s; box-shadow: 0 8px 24px rgba(176,54,65,.25); }
    .ds-submit:hover:not([disabled]) { background: var(--ds-akane-d); transform: translateY(-1px); }
    .ds-submit[disabled] { opacity: .6; cursor: not-allowed; }

    /* === フッター === */
    .ds-footer { padding: 40px 0 24px; text-align: center; color: var(--ds-sumi-soft);
        font-family: 'Shippori Mincho B1', serif; font-size: .85rem; border-top: 1px solid var(--ds-line);
        background: var(--ds-paper); }
    .ds-footer__brand { font-family: 'Yuji Syuku', serif; font-size: 1.2rem; color: var(--ds-akane);
        margin: 0 0 4px; }

    /* === 装飾的な区切り === */
    .ds-divider { display: flex; align-items: center; justify-content: center; margin: 0 auto;
        max-width: 280px; gap: 14px; color: var(--ds-gold); font-size: 1.4rem; }
    .ds-divider::before, .ds-divider::after { content: ""; flex: 1; height: 1px;
        background: linear-gradient(90deg, transparent, var(--ds-gold), transparent); }

    @media (max-width: 640px) {
        .ds-section { padding: 48px 0; }
        .ds-form { padding: 22px 18px; }
        .ds-pearl { padding: 40px 22px; }
        .ds-hero__title-line::before, .ds-hero__title-line::after { width: 36px; }
    }
</style>
@endsection

@section('content')

<header class="ds-header">
    <div class="ds-wrap">
        <p class="ds-header__brand">KYOGOFUKU HIRATA</p>
        <p class="ds-header__brand-jp">京呉服 平田</p>
    </div>
</header>

{{-- ============== ヒーロー ============== --}}
<section class="ds-hero">
    <div class="ds-wrap ds-hero__inner">
        <p class="ds-hero__eyebrow">2026 SPRING — FUKUI</p>
        <h1 class="ds-hero__title"><span class="ds-hero__title-line">大創業祭</span></h1>
        <p class="ds-hero__period">
            <strong>5/22</strong>(金) — <strong>5/25</strong>(月)
        </p>
        <p class="ds-hero__catch">
            タンスの<span>きもの</span>と<span>ジュエリー</span><br>
            <span class="ds-hero__catch-emp">まるごと整理！</span>
        </p>
        <div class="ds-hero__cta">
            <a href="#reserve" class="ds-cta-button">ご来場予約はこちら</a>
        </div>
    </div>
</section>

{{-- ============== トリマス・丸洗い ============== --}}
<section class="ds-section ds-section--paper">
    <div class="ds-wrap">
        <h2 class="ds-section__heading">事前持込キャンペーン</h2>
        <p class="ds-section__lead">
            会期前にお品物をお持ち込みいただいた方への特別なご優待をご用意いたしました。
        </p>

        <div class="ds-cards ds-cards--2">
            <div class="ds-card">
                <span class="ds-card__badge">更に！</span>
                <h3 class="ds-card__title">事前に <strong>3点以上</strong> お持ち頂いた方には<br>「<strong>トリマス</strong>」プレゼント！</h3>
                <p class="ds-card__note">※ 期間中の事前持込のみ対象となります。</p>
            </div>

            <div class="ds-card">
                <span class="ds-card__badge">着物丸洗い</span>
                <h3 class="ds-card__title">何枚でも丸洗い</h3>
                <p class="ds-card__price">3,300<small>円（税込）／ お一人様</small></p>
                <p class="ds-card__note">
                    お一人様 一点様より、何枚でも。<br>
                    ※ 事前持ち込みの方のみとさせて頂きます。
                </p>
            </div>
        </div>
    </div>
</section>

{{-- ============== メインメッセージ ============== --}}
<section class="ds-section">
    <div class="ds-wrap">
        <div class="ds-main-msg">
            <p class="ds-main-msg__lead">— 創業よりお客様への感謝を込めて —</p>
            <p class="ds-main-msg__title">今が、整える好機。</p>
            <div class="ds-divider" style="margin-top:20px;">❀</div>
        </div>
    </div>
</section>

{{-- ============== 紫真珠 ============== --}}
<section class="ds-section ds-section--accent">
    <div class="ds-wrap">
        <h2 class="ds-section__heading">特別企画</h2>
        <p class="ds-section__lead">— 今がチャンス！ —</p>

        <div class="ds-pearl">
            <p class="ds-pearl__eyebrow">SUPER PURPLE PEARL</p>
            <h3 class="ds-pearl__title">
                紫真珠
                <small>Murasaki Shinju</small>
            </h3>
            <p class="ds-pearl__body">
                天然の濃い紫色で知られる、真珠の王様とも称される一品。<br>
                真珠の艶やかさと、気品ある紫色の調合は、上品でいて深い無双の美しさをたたえます。<br>
                希少な逸品の数々をお手元に楽しみいただきとうございます。
            </p>
        </div>
    </div>
</section>

{{-- ============== 高価下取り ============== --}}
<section class="ds-section">
    <div class="ds-wrap">
        <div class="ds-takedori">
            <p style="margin:0 0 4px;font-family:'Shippori Mincho B1',serif;color:var(--ds-sumi-soft);">タンスの中で眠る、思い出の品</p>
            <h2 class="ds-takedori__title">ジュエリーの <em>高価下取り</em></h2>
            <p style="margin:0;color:var(--ds-sumi-soft);">真贋・査定はその場でお伝えいたします。</p>
        </div>
    </div>
</section>

{{-- ============== すくい取り ============== --}}
<section class="ds-section ds-section--paper">
    <div class="ds-wrap">
        <h2 class="ds-section__heading">ご来場記念企画</h2>
        <div class="ds-sukui">
            <span class="ds-sukui__label">ひなたまごー</span>
            <h3 class="ds-sukui__title">たまご すくい取り</h3>
            <p class="ds-sukui__sub">どど〜んと すくった数をプレゼント！</p>
        </div>
    </div>
</section>

{{-- ============== 予約フォーム ============== --}}
<section class="ds-section ds-section--accent" id="reserve">
    <div class="ds-wrap">
        <h2 class="ds-section__heading">ご来場のご予約</h2>

        @if($isEnded)
            <div class="ds-form">
                <p style="text-align:center;margin:0;color:var(--ds-sumi-soft);">
                    {{ $event->ended_message_text ?? 'このイベントは終了しました。' }}
                </p>
            </div>
        @elseif(empty($formSchema))
            <div class="ds-form">
                <p style="text-align:center;margin:0;color:var(--ds-sumi-soft);">フォームが設定されていません。</p>
            </div>
        @else
            @php
                $alpineInit = collect($formSchema)->mapWithKeys(function ($f) {
                    $key = $f['key'] ?? '';
                    $isMulti = ($f['type'] ?? null) === 'checkbox' && !empty($f['options']);
                    return [$key => $isMulti ? [] : ''];
                })->toArray();
            @endphp
            <form
                class="ds-form"
                method="POST"
                action="{{ route('blade-lp.reserve', $event) }}"
                x-data="{ submitting: false, values: @js($alpineInit) }"
                @submit="submitting = true"
                novalidate
            >
                @csrf
                <p class="ds-form__intro">下記フォームよりお気軽にお申込みください。</p>

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

                <button type="submit" class="ds-submit" :disabled="submitting">
                    <span x-show="!submitting">予約を申し込む</span>
                    <span x-show="submitting" x-cloak>送信中...</span>
                </button>
            </form>
        @endif
    </div>
</section>

<footer class="ds-footer">
    <div class="ds-wrap">
        <p class="ds-footer__brand">京呉服 平田</p>
        <p style="margin:0;">© {{ date('Y') }} 京呉服平田 all rights reserved.</p>
    </div>
</footer>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
