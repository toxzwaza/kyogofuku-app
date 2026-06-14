@extends('event.lp.layouts.base')

@section('title', '前撮り打合せ会｜京呉服 好一 岡山店・城東店')
@section('description', '京呉服 好一 前撮り打合せ会のご予約ページ。岡山店・城東店にて開催。ご来場会場と日時をお選びのうえ、お気軽にお申し込みください。')

@php
    // ヒーロー画像：管理画面でイベント画像として登録した先頭1枚
    $heroImage = $event->images->first();
    $posterImg = $heroImage?->url;
    $posterAlt = $heroImage?->alt ?: '前撮り打合せ会 京呉服 好一';
@endphp

@section('styles')
<style>
:root {
    --mu-bg:        #fdf6f3;  /* 淡いピンクベージュ */
    --mu-bg-2:      #f7e7e0;  /* 深めのベージュ */
    --mu-paper:     #fffdfb;  /* 紙白 */
    --mu-rose:      #d07a8e;  /* ローズ（メイン） */
    --mu-rose-d:    #b25a70;  /* 濃いローズ */
    --mu-rose-l:    #e3a6b4;  /* 淡いローズ */
    --mu-gold:      #c9a961;  /* 金 */
    --mu-sumi:      #4a3b38;  /* 墨 */
    --mu-sumi-soft: #7a6862;  /* 柔らかい墨 */
    --mu-line:      #ecd8d1;  /* 罫線 */
}
*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    margin: 0;
    background:
        radial-gradient(circle at 10% 8%, rgba(227,166,180,.30), transparent 40%),
        radial-gradient(circle at 90% 88%, rgba(201,169,97,.10), transparent 46%),
        var(--mu-bg);
    color: var(--mu-sumi);
    font-family: 'Noto Sans JP', 'Hiragino Sans', system-ui, sans-serif;
    line-height: 1.85;
    -webkit-font-smoothing: antialiased;
    word-break: keep-all;
    overflow-wrap: anywhere;
    line-break: strict;
}
img { max-width: 100%; display: block; }
a { color: var(--mu-rose); }
[x-cloak] { display: none !important; }

.mu-wrap { max-width: 860px; margin: 0 auto; padding: 0 22px; }
.mu-wrap--narrow { max-width: 680px; }

/* ====== ヘッダ ====== */
.mu-header { padding: 18px 0; text-align: center; }
.mu-header__brand { font-family: 'Noto Serif JP', serif; font-size: .8rem;
    letter-spacing: .35em; color: var(--mu-sumi-soft); margin: 0; }
.mu-header__brand-jp { font-family: 'Noto Serif JP', serif; font-size: 1.3rem; font-weight: 700;
    color: var(--mu-rose); margin-top: 2px; letter-spacing: .18em; }

/* ====== ヒーロー（登録画像1枚を全幅表示） ====== */
.mu-hero {
    position: relative; padding: 0 0 40px; overflow: hidden;
    background:
        radial-gradient(ellipse 80% 60% at 50% 0%, rgba(255,253,251,1) 0%, transparent 70%),
        linear-gradient(180deg, var(--mu-bg) 0%, var(--mu-bg-2) 100%);
}
.mu-hero__inner { position: relative; max-width: 100%; margin: 0 auto; text-align: center; }
.mu-hero__poster-wrap { display: block; line-height: 0; margin: 0; }
.mu-hero__poster img {
    display: block; width: 100%; max-width: 720px; height: auto;
    margin: 0 auto; padding: 0; border: 0;
}
.mu-hero__copy { padding: 28px 22px 0; max-width: 620px; margin: 0 auto; text-align: center; }
.mu-hero__lead {
    font-family: 'Noto Serif JP', serif; font-weight: 600;
    font-size: clamp(1.05rem, 2.4vw, 1.25rem); color: var(--mu-sumi);
    margin: 0 0 20px; line-height: 1.9;
}
.mu-hero__lead strong { color: var(--mu-rose); }
.mu-hero__cta { display: flex; flex-direction: column; gap: 10px; align-items: center; }
.mu-hero__cta-note { font-family: 'Noto Serif JP', serif; color: var(--mu-sumi-soft); font-size: .85rem; }

/* ====== CTAボタン ====== */
.mu-cta-button {
    display: inline-flex; flex-direction: column; align-items: center; justify-content: center;
    box-sizing: border-box; min-width: 300px; min-height: 64px; padding: 14px 28px;
    background: linear-gradient(135deg, var(--mu-rose) 0%, var(--mu-rose-d) 100%);
    color: #fff; font-family: 'Noto Serif JP', serif; font-weight: 700;
    font-size: 1.05rem; letter-spacing: .2em; line-height: 1.35;
    text-align: center; text-decoration: none;
    border: 1px solid var(--mu-gold); border-radius: 10px;
    box-shadow: 0 12px 28px rgba(178,90,112,.35), inset 0 1px 0 rgba(255,255,255,.25);
    transition: transform .25s ease, box-shadow .25s ease, background .35s ease;
}
.mu-cta-button:hover {
    transform: translateY(-2px);
    background: linear-gradient(135deg, var(--mu-rose-d) 0%, #93455a 100%);
    box-shadow: 0 16px 36px rgba(178,90,112,.45);
}
.mu-cta-button__label::after { content: " ▶"; font-size: .72em; opacity: .9; }
.mu-cta-button--phone {
    background: linear-gradient(180deg, #fffaf8 0%, #fff 100%);
    color: var(--mu-rose-d); border: 1.5px solid var(--mu-rose);
    box-shadow: 0 10px 24px rgba(178,90,112,.16), inset 0 1px 0 #fff;
}
.mu-cta-button--phone:hover {
    background: #fff; color: var(--mu-rose-d); border-color: var(--mu-rose-d);
    box-shadow: 0 14px 30px rgba(178,90,112,.22);
}
.mu-cta-button--phone .mu-cta-button__label::after { content: ""; }
.mu-cta-button__num { display: block; margin-top: 4px; font-size: 1.15rem; letter-spacing: .08em;
    font-weight: 800; font-feature-settings: "tnum" 1; }
.mu-cta-button__num::before {
    content: "TEL."; font-size: .62em; letter-spacing: .25em; margin-right: 8px;
    padding: 2px 6px; border-radius: 3px; background: var(--mu-rose); color: #fff;
    vertical-align: 2px; font-weight: 700;
}

/* ====== セクション共通 ====== */
.mu-section { padding: 64px 0; position: relative; }
.mu-section--paper { background: var(--mu-paper); border-top: 1px solid var(--mu-line); border-bottom: 1px solid var(--mu-line); }
.mu-eyebrow {
    text-align: center; font-family: 'Noto Serif JP', serif; font-size: .82rem;
    letter-spacing: .5em; color: var(--mu-rose-d); margin: 0 0 14px;
}
.mu-section__heading {
    text-align: center; margin: 0 0 14px; font-family: 'Noto Serif JP', serif;
    font-weight: 700; font-size: clamp(1.25rem, 3.5vw, 1.85rem); color: var(--mu-sumi);
    display: flex; align-items: center; justify-content: center; gap: 14px;
    flex-wrap: nowrap; white-space: nowrap;
}
.mu-section__heading::before, .mu-section__heading::after {
    content: ""; flex: 0 0 auto; width: 40px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--mu-gold), transparent);
}
.mu-section__lead { text-align: center; color: var(--mu-sumi-soft); max-width: 620px;
    margin: 0 auto 36px; font-family: 'Noto Serif JP', serif; font-size: 1rem; }

/* ====== フォーム ====== */
.mu-form { background: var(--mu-paper); border: 1px solid var(--mu-line); border-radius: 10px;
    padding: 40px 36px; box-shadow: 0 14px 40px rgba(178,90,112,.08); max-width: 620px; margin: 0 auto; }

/* --- 以下、共有コンポーネント lp-form/field.blade.php が出力するクラス（必須） --- */
.lp-field { margin-bottom: 22px; }
.lp-field__label { display: block; font-family: 'Noto Serif JP', serif; font-weight: 700;
    margin-bottom: 8px; font-size: .98rem; color: var(--mu-sumi); }
.lp-field__required { display: inline-block; margin-left: 8px; padding: 2px 8px;
    background: var(--mu-rose); color: #fff; font-size: .7rem; border-radius: 2px; font-weight: 700; }
.lp-field__input { width: 100%; padding: 11px 14px; border: 1px solid var(--mu-line);
    border-radius: 6px; font-size: 1rem; background: #fff; color: var(--mu-sumi);
    font-family: inherit; transition: border-color .15s, box-shadow .15s; }
.lp-field__input:focus { outline: none; border-color: var(--mu-rose);
    box-shadow: 0 0 0 3px rgba(208,122,142,.14); }
.lp-field__textarea { resize: vertical; min-height: 110px; }
.lp-field__choices { display: flex; flex-wrap: wrap; gap: 12px 22px; }
.lp-field__choice { display: flex; align-items: center; gap: 8px; cursor: pointer;
    font-family: 'Noto Serif JP', serif; }
.lp-field__help { color: var(--mu-sumi-soft); font-size: .85rem; margin: 6px 0 0; }
.lp-field__error { color: var(--mu-rose-d); font-size: .85rem; margin: 6px 0 0; font-weight: 700; }
.lp-field--error .lp-field__input { border-color: var(--mu-rose); background: #fff5f6; }

/* 予約枠（venue 未選択ガイド） */
.lp-slot__guard { text-align: center; color: var(--mu-sumi-soft);
    font-family: 'Noto Serif JP', serif; font-size: .92rem; margin: 0 0 8px; }
/* 予約枠（日付トグル） */
.lp-slot__day {
    background: #fff; border: 1px solid var(--mu-line); border-radius: 8px;
    overflow: hidden; margin-bottom: 10px; transition: box-shadow .2s;
}
.lp-slot__day:last-child { margin-bottom: 0; }
.lp-slot__day[open] { box-shadow: 0 6px 20px rgba(178,90,112,.08); }
.lp-slot__date {
    display: flex; align-items: center; gap: 12px;
    font-family: 'Noto Serif JP', serif; font-weight: 700;
    font-size: 1.05rem; color: var(--mu-sumi);
    padding: 16px 20px; cursor: pointer; list-style: none;
    background: linear-gradient(90deg, var(--mu-paper) 0%, #fff 100%);
    transition: background .2s;
}
.lp-slot__date::-webkit-details-marker { display: none; }
.lp-slot__date:hover { background: #fffaf8; }
.lp-slot__day[open] .lp-slot__date {
    background: linear-gradient(90deg, #fdf0f2 0%, #fffaf8 100%);
    border-bottom: 1px solid var(--mu-line);
}
.lp-slot__date-icon { color: var(--mu-rose); font-size: .95em; flex-shrink: 0; }
.lp-slot__date-text { flex: 1; }
.lp-slot__date-toggle {
    width: 26px; height: 26px; display: inline-flex; align-items: center;
    justify-content: center; border-radius: 50%; background: var(--mu-paper);
    border: 1px solid var(--mu-line); color: var(--mu-rose); flex-shrink: 0;
    font-weight: 700; font-size: 1rem; transition: transform .2s, background .2s;
}
.lp-slot__day[open] .lp-slot__date-toggle {
    transform: rotate(45deg); background: var(--mu-rose); color: #fff; border-color: var(--mu-rose);
}
.lp-slot__grid { display: flex; flex-direction: column; gap: 8px; padding: 16px 14px; }
.lp-slot__btn {
    background: #fff; border: 2px solid var(--mu-line); border-radius: 8px;
    padding: 14px 18px; cursor: pointer; transition: all .2s;
    font-family: inherit; text-align: left;
    display: flex; align-items: center; gap: 14px; width: 100%;
    color: var(--mu-sumi); position: relative;
}
.lp-slot__btn:hover:not([disabled]) {
    border-color: var(--mu-rose-l);
    box-shadow: 0 4px 14px rgba(208,122,142,.12);
    background: #fffaf8;
}
.lp-slot__btn:hover:not([disabled]) .lp-slot__chevron { transform: translateX(3px); color: var(--mu-rose); }
.lp-slot__btn.is-selected {
    border-color: var(--mu-rose); background: linear-gradient(90deg, #fdf0f2 0%, #fff 100%);
    box-shadow: 0 0 0 3px rgba(208,122,142,.18);
}
.lp-slot__btn.is-selected .lp-slot__chevron { color: var(--mu-rose); }
.lp-slot__btn[disabled] {
    background: #f6f0ee; color: #aaa; cursor: not-allowed;
    border-color: var(--mu-line); opacity: .75;
}
.lp-slot__time {
    font-family: 'Noto Serif JP', serif; font-weight: 700;
    font-size: 1.18rem; margin: 0; color: var(--mu-sumi);
    flex-shrink: 0; min-width: 110px;
}
.lp-slot__btn[disabled] .lp-slot__time { color: #999; }
.lp-slot__center { flex: 1; display: flex; align-items: center; gap: 10px; min-width: 0; }
.lp-slot__badges { display: flex; flex-wrap: wrap; gap: 6px; }
.lp-slot__badge {
    display: inline-flex; align-items: center; padding: 3px 12px;
    border-radius: 999px; font-size: .75rem; font-weight: 700; white-space: nowrap;
    font-family: 'Noto Sans JP', sans-serif; letter-spacing: .03em;
}
.lp-slot__badge--full { background: linear-gradient(90deg, #9a9a9a, #707070); color: #fff; }
.lp-slot__badge--nokori { background: linear-gradient(90deg, #e08398, var(--mu-rose-d)); color: #fff;
    box-shadow: 0 2px 6px rgba(178,90,112,.3); }
.lp-slot__badge--nerai { background: linear-gradient(90deg, #f4d68a, #e0b14a); color: #6b4c00;
    box-shadow: 0 2px 6px rgba(201,169,97,.3); }
.lp-slot__remaining {
    font-size: .9rem; color: var(--mu-sumi-soft); margin: 0;
    font-family: 'Noto Serif JP', serif; font-weight: 600;
    flex-shrink: 0; text-align: right;
}
.lp-slot__btn[disabled] .lp-slot__remaining { color: #aaa; }
.lp-slot__chevron { color: var(--mu-line); font-size: 1rem;
    transition: transform .2s, color .2s; flex-shrink: 0; }
.lp-slot__btn[disabled] .lp-slot__chevron { display: none; }
@media (max-width: 480px) {
    .lp-slot__btn { padding: 12px 14px; gap: 10px; }
    .lp-slot__time { font-size: 1.05rem; min-width: 92px; }
    .lp-slot__remaining { font-size: .82rem; }
    .lp-slot__badge { font-size: .7rem; padding: 2px 9px; }
}

.mu-submit { display: block; width: 100%; padding: 18px; background: var(--mu-rose);
    color: #fff; border: 0; font-family: 'Noto Serif JP', serif; font-size: 1.15rem;
    font-weight: 700; cursor: pointer; border-radius: 8px; letter-spacing: .2em;
    transition: background .2s, transform .2s; box-shadow: 0 10px 28px rgba(178,90,112,.28); }
.mu-submit:hover:not([disabled]) { background: var(--mu-rose-d); transform: translateY(-1px); }
.mu-submit[disabled] { opacity: .6; cursor: not-allowed; }

/* ====== 会場のご案内 ====== */
.mu-venue {
    background: var(--mu-paper); border: 1px solid var(--mu-line); border-radius: 10px;
    overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,.04); margin-bottom: 20px;
}
.mu-venue:last-child { margin-bottom: 0; }
.mu-venue__img { width: 100%; aspect-ratio: 16/9; overflow: hidden; background: var(--mu-bg-2); }
.mu-venue__img img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .4s ease; }
.mu-venue:hover .mu-venue__img img { transform: scale(1.02); }
.mu-venue__body { padding: 28px 32px; }
.mu-venue__name { font-family: 'Noto Serif JP', serif; font-weight: 700;
    font-size: 1.3rem; color: var(--mu-rose); margin: 0 0 12px; }
.mu-venue__desc { color: var(--mu-sumi-soft); font-size: .95rem; margin: 0 0 18px;
    font-family: 'Noto Serif JP', serif; line-height: 1.85; }
.mu-venue__meta { display: grid; grid-template-columns: 100px 1fr; gap: 12px 20px; margin: 0;
    font-family: 'Noto Serif JP', serif; }
.mu-venue__meta dt { color: var(--mu-rose); font-weight: 700; font-size: .92rem; }
.mu-venue__meta dd { margin: 0; color: var(--mu-sumi); font-size: .96rem; line-height: 1.7; }
.mu-venue__tel { color: var(--mu-rose); font-weight: 700; text-decoration: none;
    border-bottom: 1px dashed var(--mu-rose); }
.mu-venue__tel:hover { color: var(--mu-rose-d); border-bottom-style: solid; }
@media (max-width: 600px) {
    .mu-venue__body { padding: 22px 20px; }
    .mu-venue__meta { grid-template-columns: 78px 1fr; gap: 10px 14px; }
}

/* ====== フッター ====== */
.mu-footer { padding: 40px 0 28px; text-align: center; color: var(--mu-sumi-soft);
    font-family: 'Noto Serif JP', serif; font-size: .85rem;
    background: var(--mu-paper); border-top: 1px solid var(--mu-line); }
.mu-footer__brand { font-family: 'Noto Serif JP', serif; font-weight: 700; font-size: 1.25rem;
    color: var(--mu-rose); margin: 0 0 6px; letter-spacing: .1em; }

@media (max-width: 640px) {
    .mu-section { padding: 48px 0; }
    .mu-form { padding: 26px 20px; }
}
</style>
@endsection

@section('content')

{{-- ============== ヘッダ ============== --}}
<header class="mu-header">
    <div class="mu-wrap">
        <p class="mu-header__brand">KYOGOFUKU KOUICHI</p>
        <p class="mu-header__brand-jp">京呉服 好一</p>
    </div>
</header>

{{-- ============== ① ヒーロー（登録画像1枚目） ============== --}}
<section class="mu-hero">
    <div class="mu-hero__inner">
        @if($posterImg)
            <div class="mu-hero__poster-wrap">
                <div class="mu-hero__poster">
                    <img src="{{ $posterImg }}" alt="{{ $posterAlt }}" loading="eager">
                </div>
            </div>
        @endif
        <div class="mu-hero__copy">
            <p class="mu-hero__lead">
                この度は <strong>前撮り打合せ会</strong> へ<br>
                ご関心をお寄せいただき、ありがとうございます。
            </p>
            <div class="mu-hero__cta">
                <a href="#reserve" class="mu-cta-button">
                    <span class="mu-cta-button__label">ご予約はこちら</span>
                </a>
                <small class="mu-hero__cta-note">▼ ご希望の会場・日時をお選びください</small>
            </div>
        </div>
    </div>
</section>

{{-- ============== ② 予約フォーム ============== --}}
<section class="mu-section" id="reserve">
    <div class="mu-wrap">
        <p class="mu-eyebrow">— RESERVATION —</p>
        <h2 class="mu-section__heading">ご来場のご予約</h2>
        <p class="mu-section__lead">下記フォームよりお気軽にお申込みください。</p>

        @if($isEnded)
            <div class="mu-form">
                <p style="text-align:center;margin:0;color:var(--mu-sumi-soft);">
                    {{ $event->ended_message_text ?? 'このイベントは終了しました。' }}
                </p>
            </div>
        @elseif(empty($formSchema))
            <div class="mu-form">
                <p style="text-align:center;margin:0;color:var(--mu-sumi-soft);">フォームが設定されていません。</p>
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
                class="mu-form"
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
                        <x-lp-form.field :field="$field" :event="$event" />
                    </div>
                @endforeach

                <button type="submit" class="mu-submit" :disabled="submitting">
                    <span x-show="!submitting">予約を申し込む</span>
                    <span x-show="submitting" x-cloak>送信中...</span>
                </button>
            </form>
        @endif
    </div>
</section>

{{-- ============== ③ 開催会場のご案内 ============== --}}
@if($event->venues->isNotEmpty())
<section class="mu-section mu-section--paper">
    <div class="mu-wrap mu-wrap--narrow">
        <p class="mu-eyebrow">— VENUE —</p>
        <h2 class="mu-section__heading">開催会場のご案内</h2>
        <p class="mu-section__lead" style="margin-bottom:36px;"></p>

        @foreach($event->venues as $venue)
            <div class="mu-venue">
                @if($venue->image_url)
                    <div class="mu-venue__img">
                        <img src="{{ $venue->image_url }}" alt="{{ $venue->name }}" loading="lazy">
                    </div>
                @endif
                <div class="mu-venue__body">
                    <h3 class="mu-venue__name">{{ $venue->name }}</h3>
                    @if($venue->description)
                        <p class="mu-venue__desc">{{ $venue->description }}</p>
                    @endif
                    <dl class="mu-venue__meta">
                        @if($venue->address)
                            <dt>住所</dt>
                            <dd>{{ $venue->address }}</dd>
                        @endif
                        @if($venue->phone)
                            <dt>電話番号</dt>
                            <dd>
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $venue->phone) }}" class="mu-venue__tel">
                                    {{ $venue->phone }}
                                </a>
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- ============== フッター ============== --}}
<footer class="mu-footer">
    <div class="mu-wrap">
        <p class="mu-footer__brand">京呉服 好一</p>
        <p style="margin:0;">© {{ date('Y') }} 京呉服 好一 all rights reserved.</p>
    </div>
</footer>
@endsection
