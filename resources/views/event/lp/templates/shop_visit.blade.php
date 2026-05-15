@extends('event.lp.layouts.base')

@section('title', $event->title)
@section('description', 'お話を聞いてみたい・実物を見てみたい、そんな方こそお気軽にどうぞ。京呉服のご来店予約ページです。')

@php
    $imgBase = asset('images/lp/shop_visit');

    // ヒーローポスター画像
    // 優先: 管理画面でイベント画像として登録した先頭1枚（メディアライブラリ選択方式）
    // 無ければ slug でのブランド判定で静的画像にフォールバック
    $heroImage = $event->images->first();
    $posterImg = $heroImage?->url ?: match (true) {
        str_contains($event->slug, 'kouichi') => $imgBase.'/poster_kouichi.png',
        str_contains($event->slug, 'hirata')  => $imgBase.'/poster_hirata.png',
        default => $imgBase.'/hero.png',
    };
    $posterAlt = $heroImage?->alt ?: $event->title;

    // ブランド名（ヘッダ・フッタ）
    $isHirata = str_contains($event->slug, 'hirata');
    $brandJp = $isHirata ? '京呉服 平田' : '京呉服 好一';
    $brandEn = $isHirata ? 'KYOGOFUKU HIRATA' : 'KYOGOFUKU KOUICHI';

    // 代表店舗の電話番号（CTA用）：福井=福井店、岡山=岡山店
    $ctaPhone = $isHirata ? '0776-34-1529' : '086-242-1529';

    // 振袖プレビュー：固定の7枚（運用で追加の振袖画像を増やしたい場合は配列を編集）
    $previewImages = [
        ['url' => $imgBase.'/furisode_01.webp', 'alt' => '振袖 01'],
        ['url' => $imgBase.'/furisode_02.webp', 'alt' => '振袖 02'],
        ['url' => $imgBase.'/furisode_03.webp', 'alt' => '振袖 03'],
        ['url' => $imgBase.'/furisode_04.webp', 'alt' => '振袖 04'],
        ['url' => $imgBase.'/furisode_05.webp', 'alt' => '振袖 05'],
        ['url' => $imgBase.'/furisode_06.webp', 'alt' => '振袖 06'],
        ['url' => $imgBase.'/furisode_07.webp', 'alt' => '振袖 07'],
    ];
@endphp

@section('styles')
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=noto-serif-jp:400,500,700|noto-sans-jp:400,500,700|shippori-mincho-b1:400,500,600,700|yuji-syuku:400&display=swap" rel="stylesheet">
<style>
    :root {
        --sv-bg:        #fffaf6;
        --sv-bg-2:      #fdf3ec;
        --sv-paper:     #fff;
        --sv-beige:     #f5e6d3;
        --sv-beige-d:   #ead5be;
        --sv-pink:      #fbe4e3;
        --sv-pink-2:    #f5c5c4;
        --sv-pink-3:    #e8a3b8;
        --sv-accent:    #c46a7e;
        --sv-accent-d:  #a85065;
        --sv-text:      #3d2b2b;
        --sv-text-soft: #7a6363;
        --sv-line:      #e8d8c8;
        --sv-gold:      #c9a961;
    }
    * , *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
        margin: 0;
        background: var(--sv-bg);
        color: var(--sv-text);
        font-family: 'Noto Sans JP', 'Hiragino Sans', system-ui, sans-serif;
        line-height: 1.85;
        -webkit-font-smoothing: antialiased;
        word-break: keep-all;
        overflow-wrap: anywhere;
        line-break: strict;
    }
    img { max-width: 100%; display: block; }
    a { color: var(--sv-accent); }
    [x-cloak] { display: none !important; }

    .sv-wrap { max-width: 960px; margin: 0 auto; padding: 0 22px; }
    .sv-wrap--narrow { max-width: 720px; }

    /* ========== 共通要素 ========== */
    .sv-eyebrow {
        text-align: center; font-family: 'Shippori Mincho B1', serif;
        letter-spacing: .55em; font-size: .82rem; color: var(--sv-accent);
        margin: 0 0 14px; white-space: nowrap;
    }
    .sv-section { padding: 80px 0; }
    .sv-section--paper { background: var(--sv-paper); border-top: 1px solid var(--sv-line); border-bottom: 1px solid var(--sv-line); }
    .sv-section--beige { background: linear-gradient(180deg, var(--sv-bg-2) 0%, var(--sv-beige) 100%); }
    .sv-section__heading {
        text-align: center; margin: 0 0 12px; font-family: 'Shippori Mincho B1', serif;
        font-weight: 700; font-size: clamp(1.3rem, 3.4vw, 1.85rem); color: var(--sv-text);
        display: flex; align-items: center; justify-content: center; gap: 14px;
        white-space: nowrap;
    }
    .sv-section__heading::before, .sv-section__heading::after {
        content: ""; flex: 0 0 auto; width: 36px; height: 1px;
        background: linear-gradient(90deg, transparent, var(--sv-pink-3), transparent);
    }
    .sv-section__lead {
        text-align: center; color: var(--sv-text-soft); max-width: 640px;
        margin: 0 auto 40px; font-family: 'Shippori Mincho B1', serif;
        font-size: .98rem;
    }

    /* ========== Sticky CTA（モバイル） ========== */
    .sv-sticky-cta { display: none; }
    @media (max-width: 879px) {
        .sv-sticky-cta {
            display: block; position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
            background: rgba(255,250,246,.96); border-top: 1px solid var(--sv-pink-2);
            padding: 10px 14px env(safe-area-inset-bottom, 10px);
            backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 -6px 20px rgba(196,106,126,.12);
        }
        .sv-sticky-cta__row {
            display: flex; gap: 8px; align-items: stretch;
        }
        .sv-sticky-cta a {
            flex: 1 1 0; min-width: 0;
            display: inline-flex; flex-direction: column; align-items: center;
            justify-content: center; text-align: center;
            padding: 10px 12px; min-height: 52px; box-sizing: border-box;
            background: var(--sv-accent); color: #fff;
            font-family: 'Shippori Mincho B1', serif; font-weight: 700;
            text-decoration: none; border-radius: 999px; letter-spacing: .12em;
            font-size: .95rem; line-height: 1.2;
            box-shadow: 0 6px 14px rgba(196,106,126,.3);
        }
        .sv-sticky-cta a.sv-sticky-cta__phone {
            background: #fff; color: var(--sv-accent-d);
            border: 2px solid var(--sv-accent);
        }
        .sv-sticky-cta a.sv-sticky-cta__phone .sv-sticky-cta__num {
            font-size: .95rem; letter-spacing: .04em;
        }
        body { padding-bottom: 76px; }
    }

    /* ========== ヘッダ ========== */
    .sv-header { padding: 18px 0; text-align: center;
        background: var(--sv-bg); border-bottom: 1px solid var(--sv-line); }
    .sv-header__brand { font-family: 'Shippori Mincho B1', serif; font-size: .85rem;
        letter-spacing: .35em; color: var(--sv-text-soft); margin: 0; }
    .sv-header__brand-jp { font-family: 'Yuji Syuku', serif; font-size: 1.3rem;
        color: var(--sv-accent); margin-top: 2px; letter-spacing: .15em; }

    /* ========== ヒーロー（ポスター中心） ========== */
    .sv-hero {
        position: relative; padding: 30px 0 50px;
        background:
            radial-gradient(ellipse 80% 60% at 50% 0%, rgba(251,228,227,.7) 0%, transparent 70%),
            linear-gradient(180deg, var(--sv-bg) 0%, var(--sv-bg-2) 100%);
        overflow: hidden;
    }
    .sv-hero__inner { text-align: center; padding: 0 22px; }
    .sv-hero__poster {
        display: inline-block; max-width: 100%;
        border-radius: 6px; overflow: hidden;
        box-shadow:
            0 4px 0 rgba(0,0,0,.03),
            0 24px 56px rgba(196,106,126,.22),
            0 8px 16px rgba(196,106,126,.12);
        border: 6px solid #fffaf6;
        outline: 1px solid var(--sv-pink-2);
        transition: transform .3s ease;
    }
    .sv-hero__poster:hover { transform: translateY(-2px); }
    .sv-hero__poster img {
        display: block; width: auto; max-width: 460px; max-height: 78vh; height: auto;
        border-radius: 2px;
    }
    @media (max-width: 600px) {
        .sv-hero__poster { border-width: 4px; }
        .sv-hero__poster img { max-width: 100%; max-height: none; }
    }
    .sv-hero__copy {
        margin-top: 32px;
        max-width: 600px; margin-left: auto; margin-right: auto;
    }
    .sv-hero__title {
        font-family: 'Yuji Syuku', serif; font-weight: 400;
        font-size: clamp(1.45rem, 4.4vw, 2rem); color: var(--sv-accent-d);
        margin: 0 0 14px; letter-spacing: .08em; line-height: 1.5;
    }
    .sv-hero__lead {
        font-family: 'Shippori Mincho B1', serif; color: var(--sv-text);
        font-size: clamp(.96rem, 2.2vw, 1.08rem);
        margin: 0 0 24px; line-height: 1.95;
    }
    .sv-hero__lead strong { color: var(--sv-accent); font-weight: 700; }
    .sv-hero__cta-button {
        display: inline-block; padding: 17px 50px;
        background: var(--sv-accent); color: #fff;
        font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        font-size: 1.05rem; letter-spacing: .25em;
        text-decoration: none; border-radius: 999px;
        box-shadow: 0 12px 28px rgba(196,106,126,.32);
        transition: all .25s; white-space: nowrap;
    }
    .sv-hero__cta-button:hover {
        background: var(--sv-accent-d); transform: translateY(-2px);
        box-shadow: 0 16px 36px rgba(196,106,126,.42);
    }
    .sv-hero__cta-button::after { content: " ▶"; font-size: .8em; margin-left: 6px; }
    /* 電話ボタン（CTA第二ボタン） */
    .sv-hero__cta-button--phone {
        background: #fff; color: var(--sv-accent-d);
        border: 2px solid var(--sv-accent);
        box-shadow: 0 8px 22px rgba(196,106,126,.18);
    }
    .sv-hero__cta-button--phone:hover {
        background: var(--sv-pink); color: var(--sv-accent-d);
        box-shadow: 0 12px 28px rgba(196,106,126,.28);
    }
    .sv-hero__cta-button--phone::after { content: none; }
    .sv-hero__cta-button__num {
        font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        font-size: 1.05rem; letter-spacing: .08em; color: var(--sv-accent-d);
        line-height: 1.2;
    }
    /* CTAボタン縦並び（WEB + 電話）— 共通サイズ */
    .sv-hero__cta-group {
        display: flex; flex-direction: column; align-items: stretch; gap: 12px;
        max-width: 360px; margin: 0 auto;
    }
    .sv-hero__cta-group .sv-hero__cta-button {
        width: 100%; box-sizing: border-box;
        padding: 16px 28px; min-height: 64px;
        display: inline-flex; flex-direction: column; align-items: center;
        justify-content: center; gap: 2px;
        line-height: 1.25; letter-spacing: .2em;
    }
    .sv-hero__cta-group .sv-hero__cta-button::after {
        content: none;
    }
    .sv-hero__cta-note { display: block; margin-top: 12px;
        color: var(--sv-text-soft); font-size: .85rem;
        font-family: 'Shippori Mincho B1', serif; }

    /* ========== こんな方こそ ========== */
    .sv-welcome { max-width: 760px; margin: 0 auto; }
    .sv-welcome__title {
        text-align: center; font-family: 'Shippori Mincho B1', serif;
        font-weight: 700; font-size: clamp(1.25rem, 3.4vw, 1.75rem);
        color: var(--sv-text); margin: 0 0 36px; line-height: 1.7;
    }
    .sv-welcome__title em { font-style: normal; color: var(--sv-accent);
        background: linear-gradient(transparent 65%, var(--sv-pink) 65%); padding: 0 4px; }
    .sv-welcome__list { list-style: none; padding: 0; margin: 0;
        display: grid; grid-template-columns: 1fr; gap: 14px; }
    @media (min-width: 600px) { .sv-welcome__list { grid-template-columns: 1fr 1fr; } }
    .sv-welcome__item {
        display: flex; align-items: center; gap: 14px;
        background: var(--sv-paper); border: 1px solid var(--sv-line);
        border-radius: 999px; padding: 14px 22px;
        font-family: 'Shippori Mincho B1', serif;
        box-shadow: 0 4px 12px rgba(196,106,126,.06);
        transition: all .2s;
    }
    .sv-welcome__item:hover { background: var(--sv-pink); }
    .sv-welcome__check {
        flex-shrink: 0; width: 28px; height: 28px; border-radius: 50%;
        background: var(--sv-accent); color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: .9rem;
    }
    .sv-welcome__text { color: var(--sv-text); font-size: .95rem; flex: 1; }

    /* ========== ご来店でできること ========== */
    .sv-can-do { max-width: 1080px; margin: 0 auto;
        display: grid; gap: 20px; grid-template-columns: 1fr; }
    @media (min-width: 600px) { .sv-can-do { grid-template-columns: 1fr 1fr; } }
    @media (min-width: 980px) { .sv-can-do { grid-template-columns: 1fr 1fr 1fr 1fr; } }
    .sv-can-do__card {
        background: var(--sv-paper); border: 1px solid var(--sv-line); border-radius: 14px;
        overflow: hidden; text-align: center;
        box-shadow: 0 6px 18px rgba(196,106,126,.08);
        transition: transform .25s, box-shadow .25s;
        display: flex; flex-direction: column;
    }
    .sv-can-do__card:hover { transform: translateY(-4px); box-shadow: 0 18px 36px rgba(196,106,126,.16); }
    .sv-can-do__img { aspect-ratio: 4 / 3; overflow: hidden; background: var(--sv-beige); }
    .sv-can-do__img img { width: 100%; height: 100%; object-fit: cover;
        transition: transform .4s ease; }
    .sv-can-do__card:hover .sv-can-do__img img { transform: scale(1.06); }
    .sv-can-do__body { padding: 20px 18px 24px; flex: 1;
        display: flex; flex-direction: column; align-items: center; }
    .sv-can-do__num {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border-radius: 50%;
        background: linear-gradient(135deg, var(--sv-pink) 0%, var(--sv-pink-2) 100%);
        color: var(--sv-accent-d); font-family: 'Yuji Syuku', serif;
        font-size: .95rem; margin-bottom: 8px;
    }
    .sv-can-do__title {
        font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        font-size: 1.05rem; color: var(--sv-text); margin: 0 0 8px;
        white-space: nowrap;
    }
    .sv-can-do__desc { font-size: .88rem; color: var(--sv-text-soft); margin: 0; line-height: 1.75; }

    /* ========== 振袖プレビュー ========== */
    /* PC・タブレット：グリッド */
    .sv-preview { max-width: 1080px; margin: 0 auto;
        display: grid; gap: 14px; grid-template-columns: repeat(3, 1fr); }
    @media (min-width: 1000px) { .sv-preview { grid-template-columns: repeat(4, 1fr); } }
    .sv-preview__item {
        position: relative; aspect-ratio: 3 / 4; overflow: hidden;
        border-radius: 8px; cursor: pointer;
        background: var(--sv-beige);
        box-shadow: 0 6px 18px rgba(196,106,126,.10);
        transition: transform .25s, box-shadow .25s;
    }
    .sv-preview__item:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 16px 36px rgba(196,106,126,.18);
    }
    .sv-preview__item img {
        width: 100%; height: 100%; object-fit: cover;
        transition: transform .4s ease;
    }
    .sv-preview__item:hover img { transform: scale(1.05); }

    /* モバイル：横スクロール スライドショー */
    @media (max-width: 719px) {
        .sv-preview {
            display: flex; flex-direction: row; gap: 14px;
            grid-template-columns: none; /* reset */
            overflow-x: auto; scroll-snap-type: x mandatory;
            padding: 8px 22px 12px;
            margin: 0 -22px; /* 親のwrap padding を抜ける */
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
            scroll-padding: 22px;
        }
        .sv-preview::-webkit-scrollbar { display: none; }
        .sv-preview__item {
            flex: 0 0 78%;
            scroll-snap-align: center;
            box-shadow: 0 10px 28px rgba(196,106,126,.18);
        }
    }

    /* ドットインジケータ（モバイルのみ） */
    .sv-preview-dots { display: none; }
    @media (max-width: 719px) {
        .sv-preview-dots {
            display: flex; justify-content: center; gap: 8px;
            margin: 16px 0 0;
        }
        .sv-preview-dots__dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--sv-pink-2); transition: all .25s;
            cursor: pointer; border: 0; padding: 0;
        }
        .sv-preview-dots__dot.is-active {
            background: var(--sv-accent); transform: scale(1.3);
        }
    }

    .sv-preview__note {
        text-align: center; margin: 24px auto 0; max-width: 600px;
        font-family: 'Shippori Mincho B1', serif;
        color: var(--sv-text-soft); font-size: .92rem;
    }

    /* Lightbox */
    .sv-lightbox {
        position: fixed; inset: 0; background: rgba(61,43,43,.92);
        display: flex; align-items: center; justify-content: center;
        z-index: 200; padding: 20px; cursor: zoom-out;
    }
    .sv-lightbox img {
        max-width: 90vw; max-height: 90vh;
        object-fit: contain; border-radius: 6px;
        box-shadow: 0 20px 50px rgba(0,0,0,.5);
    }
    .sv-lightbox__close {
        position: absolute; top: 20px; right: 24px;
        background: none; border: 0; color: #fff; font-size: 2rem;
        cursor: pointer; line-height: 1; opacity: .8;
    }
    .sv-lightbox__close:hover { opacity: 1; }

    /* ========== FAQ ========== */
    .sv-faq { max-width: 720px; margin: 0 auto; }
    .sv-faq__item { background: var(--sv-paper); border: 1px solid var(--sv-line);
        border-radius: 10px; margin-bottom: 12px; overflow: hidden;
        transition: box-shadow .2s; }
    .sv-faq__item[open] { box-shadow: 0 6px 18px rgba(196,106,126,.08); border-color: var(--sv-pink-2); }
    .sv-faq__q {
        padding: 18px 48px 18px 20px; font-family: 'Shippori Mincho B1', serif;
        font-weight: 700; cursor: pointer; list-style: none; position: relative;
        color: var(--sv-text); font-size: .95rem; line-height: 1.7;
    }
    .sv-faq__q::-webkit-details-marker { display: none; }
    .sv-faq__q::before { content: "Q."; color: var(--sv-accent); margin-right: 8px;
        font-weight: 800; display: inline-block; }
    .sv-faq__q::after { content: "+"; position: absolute; right: 18px; top: 50%;
        transform: translateY(-50%); font-size: 1.3rem; color: var(--sv-accent);
        transition: transform .2s; }
    .sv-faq__item[open] .sv-faq__q::after { transform: translateY(-50%) rotate(45deg); }
    .sv-faq__a { padding: 0 20px 18px 46px; color: var(--sv-text-soft);
        font-family: 'Shippori Mincho B1', serif; line-height: 2; font-size: .9rem;
        position: relative; }
    .sv-faq__a::before { content: "A."; position: absolute; left: 20px; top: 0;
        color: var(--sv-pink-3); font-weight: 800; }

    /* ========== 会場 ========== */
    .sv-venues { display: grid; gap: 22px; grid-template-columns: 1fr;
        max-width: 960px; margin: 0 auto; }
    @media (min-width: 720px) {
        .sv-venues--multi { grid-template-columns: 1fr 1fr; }
    }
    .sv-venue { background: var(--sv-paper); border: 1px solid var(--sv-line);
        border-radius: 12px; overflow: hidden;
        box-shadow: 0 6px 18px rgba(196,106,126,.07);
        display: flex; flex-direction: column;
    }
    .sv-venue__img { width: 100%; aspect-ratio: 16/10; overflow: hidden; background: var(--sv-beige); }
    .sv-venue__img img { width: 100%; height: 100%; object-fit: cover;
        transition: transform .3s; }
    .sv-venue:hover .sv-venue__img img { transform: scale(1.03); }
    .sv-venue__body { padding: 22px 24px; flex: 1; }
    .sv-venue__name { font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        font-size: 1.15rem; color: var(--sv-accent); margin: 0 0 12px; }
    .sv-venue__meta { display: grid; grid-template-columns: 80px 1fr; gap: 10px 14px;
        margin: 0; font-family: 'Shippori Mincho B1', serif; }
    .sv-venue__meta dt { color: var(--sv-accent); font-weight: 700; font-size: .88rem; }
    .sv-venue__meta dd { margin: 0; color: var(--sv-text); font-size: .92rem; }
    .sv-venue__tel { color: var(--sv-accent); font-weight: 700;
        text-decoration: none; border-bottom: 1px dashed var(--sv-accent); }

    /* ========== フォーム ========== */
    .sv-form { background: var(--sv-paper); border: 1px solid var(--sv-line);
        border-radius: 12px; padding: 36px 32px;
        box-shadow: 0 14px 40px rgba(196,106,126,.10); max-width: 640px; margin: 0 auto; }
    @media (max-width: 640px) { .sv-form { padding: 24px 20px; } }
    .sv-form__intro { text-align: center; margin: 0 0 24px;
        color: var(--sv-text-soft); font-family: 'Shippori Mincho B1', serif; }

    .lp-field { margin-bottom: 22px; }
    .lp-field__label { display: block; font-family: 'Shippori Mincho B1', serif;
        font-weight: 700; margin-bottom: 8px; font-size: .95rem; color: var(--sv-text); }
    .lp-field__required { display: inline-block; margin-left: 8px; padding: 2px 8px;
        background: var(--sv-accent); color: #fff; font-size: .68rem;
        border-radius: 999px; font-weight: 700; white-space: nowrap; }
    .lp-field__input { width: 100%; padding: 11px 14px;
        border: 1px solid var(--sv-line); border-radius: 8px;
        font-size: 1rem; background: #fff; color: var(--sv-text);
        font-family: inherit; transition: border-color .15s, box-shadow .15s; }
    .lp-field__input:focus { outline: none; border-color: var(--sv-accent);
        box-shadow: 0 0 0 3px rgba(196,106,126,.15); }
    .lp-field__textarea { resize: vertical; min-height: 110px; }
    .lp-field__choices { display: flex; flex-wrap: wrap; gap: 12px 22px; }
    .lp-field__choice { display: flex; align-items: center; gap: 8px; cursor: pointer;
        font-family: 'Shippori Mincho B1', serif; }
    .lp-field__help { color: var(--sv-text-soft); font-size: .85rem; margin: 6px 0 0; }
    .lp-field__error { color: var(--sv-accent-d); font-size: .85rem; margin: 6px 0 0; font-weight: 700; }
    .lp-field--error .lp-field__input { border-color: var(--sv-accent); background: #fff5f5; }

    .sv-submit { display: block; width: 100%; padding: 17px;
        background: var(--sv-accent); color: #fff; border: 0;
        font-family: 'Shippori Mincho B1', serif; font-size: 1.08rem;
        font-weight: 700; cursor: pointer; border-radius: 999px;
        letter-spacing: .25em; transition: background .2s, transform .2s;
        box-shadow: 0 10px 24px rgba(196,106,126,.28); }
    .sv-submit:hover:not([disabled]) { background: var(--sv-accent-d); transform: translateY(-1px); }
    .sv-submit[disabled] { opacity: .6; cursor: not-allowed; }
    .sv-submit__hint {
        margin: 10px 0 0; text-align: center; color: var(--sv-accent-d);
        font-family: 'Shippori Mincho B1', serif; font-size: .9rem;
    }

    /* === 予約枠（lp-slot 共通スタイル：上書き） === */
    .lp-slot__guard {
        margin: 0 0 16px; padding: 18px 22px; text-align: center;
        background: linear-gradient(180deg, var(--sv-pink) 0%, var(--sv-bg) 100%);
        border: 1px dashed var(--sv-pink-3);
        border-radius: 8px;
        font-family: 'Shippori Mincho B1', serif;
        color: var(--sv-accent-d); font-weight: 700;
    }
    .lp-slot__day {
        background: var(--sv-paper); border: 1px solid var(--sv-line); border-radius: 10px;
        overflow: hidden; margin-bottom: 10px; transition: box-shadow .2s;
    }
    .lp-slot__day:last-child { margin-bottom: 0; }
    .lp-slot__day[open] { box-shadow: 0 6px 18px rgba(196,106,126,.10); }
    .lp-slot__date {
        display: flex; align-items: center; gap: 12px;
        font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        font-size: 1rem; color: var(--sv-text);
        padding: 14px 18px; cursor: pointer; list-style: none;
        background: linear-gradient(90deg, var(--sv-bg-2) 0%, var(--sv-paper) 100%);
        transition: background .2s;
    }
    .lp-slot__date::-webkit-details-marker { display: none; }
    .lp-slot__date:hover { background: var(--sv-pink); }
    .lp-slot__day[open] .lp-slot__date {
        background: linear-gradient(90deg, var(--sv-pink) 0%, var(--sv-bg-2) 100%);
        border-bottom: 1px solid var(--sv-line);
    }
    .lp-slot__date-icon { color: var(--sv-accent); font-size: .95em; flex-shrink: 0; }
    .lp-slot__date-text { flex: 1; }
    .lp-slot__date-toggle {
        width: 26px; height: 26px; display: inline-flex; align-items: center;
        justify-content: center; border-radius: 50%; background: var(--sv-bg);
        border: 1px solid var(--sv-line); color: var(--sv-accent); flex-shrink: 0;
        font-weight: 700; font-size: 1rem; transition: transform .2s, background .2s;
    }
    .lp-slot__day[open] .lp-slot__date-toggle {
        transform: rotate(45deg); background: var(--sv-accent); color: #fff;
        border-color: var(--sv-accent);
    }
    .lp-slot__grid { display: flex; flex-direction: column; gap: 8px; padding: 14px 12px; }
    .lp-slot__btn {
        background: #fff; border: 2px solid var(--sv-line); border-radius: 10px;
        padding: 12px 16px; cursor: pointer; transition: all .2s;
        font-family: inherit; text-align: left;
        display: flex; align-items: center; gap: 12px; width: 100%;
        color: var(--sv-text);
    }
    .lp-slot__btn:hover:not([disabled]) {
        border-color: var(--sv-pink-3);
        box-shadow: 0 4px 12px rgba(196,106,126,.12);
        background: var(--sv-bg);
    }
    .lp-slot__btn.is-selected {
        border-color: var(--sv-accent);
        background: linear-gradient(90deg, var(--sv-pink) 0%, #fff 100%);
        box-shadow: 0 0 0 3px rgba(196,106,126,.15);
    }
    .lp-slot__btn[disabled] {
        background: var(--sv-bg-2); color: #aaa; cursor: not-allowed;
        border-color: var(--sv-line); opacity: .7;
    }
    .lp-slot__time {
        font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        font-size: 1.05rem; margin: 0; color: var(--sv-text);
        flex-shrink: 0; min-width: 120px;
    }
    .lp-slot__center { flex: 1; display: flex; align-items: center; gap: 10px; min-width: 0; }
    .lp-slot__badges { display: flex; flex-wrap: wrap; gap: 6px; }
    .lp-slot__badge {
        display: inline-flex; align-items: center; padding: 2px 10px;
        border-radius: 999px; font-size: .7rem; font-weight: 700;
        white-space: nowrap; font-family: 'Noto Sans JP', sans-serif;
    }
    .lp-slot__badge--full { background: linear-gradient(90deg, #999, #707070); color: #fff; }
    .lp-slot__badge--nokori { background: linear-gradient(90deg, var(--sv-pink-3), var(--sv-accent)); color: #fff; }
    .lp-slot__badge--nerai { background: linear-gradient(90deg, #f4d68a, #e0b14a); color: #6b4c00; }
    .lp-slot__remaining {
        font-size: .85rem; color: var(--sv-text-soft); margin: 0;
        font-family: 'Shippori Mincho B1', serif; font-weight: 600;
        flex-shrink: 0; text-align: right;
    }
    .lp-slot__chevron { color: var(--sv-line); font-size: .9rem;
        transition: transform .2s, color .2s; flex-shrink: 0; }
    .lp-slot__btn:hover:not([disabled]) .lp-slot__chevron {
        transform: translateX(3px); color: var(--sv-accent);
    }
    .lp-slot__btn[disabled] .lp-slot__chevron { display: none; }
    @media (max-width: 480px) {
        .lp-slot__time { min-width: 100px; font-size: .95rem; }
    }

    /* ========== 最終CTA・フッター ========== */
    .sv-final-cta { padding: 70px 0; text-align: center;
        background: linear-gradient(135deg, var(--sv-pink) 0%, var(--sv-beige) 100%); }
    .sv-final-cta h2 {
        font-family: 'Yuji Syuku', serif; font-size: clamp(1.4rem, 4vw, 2rem);
        margin: 0 0 12px; letter-spacing: .08em; color: var(--sv-accent-d);
    }
    .sv-final-cta p { margin: 0 0 28px; color: var(--sv-text-soft);
        font-family: 'Shippori Mincho B1', serif; }
    .sv-final-cta .sv-hero__cta-button:not(.sv-hero__cta-button--phone) {
        background: var(--sv-paper); color: var(--sv-accent);
        box-shadow: 0 10px 24px rgba(0,0,0,.10);
    }
    .sv-final-cta .sv-hero__cta-button:not(.sv-hero__cta-button--phone):hover { background: #fff; color: var(--sv-accent-d); }

    .sv-footer { padding: 32px 0 24px; text-align: center;
        color: var(--sv-text-soft); font-family: 'Shippori Mincho B1', serif;
        font-size: .82rem; background: var(--sv-paper);
        border-top: 1px solid var(--sv-line); }
    .sv-footer__brand { font-family: 'Yuji Syuku', serif; font-size: 1.15rem;
        color: var(--sv-accent); margin: 0 0 4px; letter-spacing: .1em; }

    /* 共通: 改行抑止ヘルパー */
    .sv-nobr { white-space: nowrap; }
</style>
@endsection

@section('content')

{{-- ========== Sticky CTA（モバイル） ========== --}}
<div class="sv-sticky-cta">
    <div class="sv-sticky-cta__row">
        <a href="#reserve">WEBで予約する</a>
        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $ctaPhone) }}" class="sv-sticky-cta__phone">
            <span>お電話で予約</span>
            <span class="sv-sticky-cta__num">{{ $ctaPhone }}</span>
        </a>
    </div>
</div>

{{-- ========== ヘッダ ========== --}}
<header class="sv-header">
    <div class="sv-wrap">
        <p class="sv-header__brand">{{ $brandEn }}</p>
        <p class="sv-header__brand-jp">{{ $brandJp }}</p>
    </div>
</header>

{{-- ========== ヒーロー（ポスター中心） ========== --}}
<section class="sv-hero">
    <div class="sv-hero__inner">
        <h1 class="sv-hero__poster">
            <img src="{{ $posterImg }}" alt="{{ $posterAlt }}" loading="eager">
        </h1>
        <div class="sv-hero__copy">
            <p class="sv-hero__lead">
                「どんな着物があるか見てみたい」<br>
                「料金感だけ知りたい」<br>
                <strong>そんな方こそ、 ぜひお気軽に。</strong>
            </p>
            <div class="sv-hero__cta-group">
                <a href="#reserve" class="sv-hero__cta-button">WEBで予約する</a>
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $ctaPhone) }}" class="sv-hero__cta-button sv-hero__cta-button--phone">
                    <span>お電話で予約する</span>
                    <span class="sv-hero__cta-button__num">{{ $ctaPhone }}</span>
                </a>
            </div>
            <small class="sv-hero__cta-note">▼ 30秒で完了 ／ ご相談のみも歓迎です</small>
        </div>
    </div>
</section>

{{-- ========== こんな方こそ ========== --}}
<section class="sv-section">
    <div class="sv-wrap">
        <p class="sv-eyebrow">— FOR YOU —</p>
        <h2 class="sv-section__heading">こんな方こそ、お越しください</h2>
        <p class="sv-section__lead">
            私たちは、 強引な営業はいたしません。
            まずは <span class="sv-nobr">お話だけ・見るだけ</span> でも、 心より歓迎しております。
        </p>

        <div class="sv-welcome">
            <h3 class="sv-welcome__title">
                「<em>ちょっと相談したい</em>」<br>そんなお気持ちで、 大丈夫です。
            </h3>
            <ul class="sv-welcome__list">
                <li class="sv-welcome__item">
                    <span class="sv-welcome__check">✓</span>
                    <span class="sv-welcome__text">買う予定はないけれど、見てみたい</span>
                </li>
                <li class="sv-welcome__item">
                    <span class="sv-welcome__check">✓</span>
                    <span class="sv-welcome__text">料金だけ聞いてみたい</span>
                </li>
                <li class="sv-welcome__item">
                    <span class="sv-welcome__check">✓</span>
                    <span class="sv-welcome__text">家族と一緒に相談したい</span>
                </li>
                <li class="sv-welcome__item">
                    <span class="sv-welcome__check">✓</span>
                    <span class="sv-welcome__text">何から始めれば良いか分からない</span>
                </li>
                <li class="sv-welcome__item">
                    <span class="sv-welcome__check">✓</span>
                    <span class="sv-welcome__text">タンスの着物の相談をしたい</span>
                </li>
                <li class="sv-welcome__item">
                    <span class="sv-welcome__check">✓</span>
                    <span class="sv-welcome__text">写真だけでも撮ってみたい</span>
                </li>
            </ul>
        </div>
    </div>
</section>

{{-- ========== ご来店でできること ========== --}}
<section class="sv-section sv-section--paper">
    <div class="sv-wrap">
        <p class="sv-eyebrow">— WHAT YOU CAN DO —</p>
        <h2 class="sv-section__heading">ご来店でできること</h2>
        <p class="sv-section__lead">
            時間は短くても、ゆっくりでも。 ご都合に合わせてお過ごしいただけます。
        </p>

        <div class="sv-can-do">
            <div class="sv-can-do__card">
                <div class="sv-can-do__img">
                    <img src="{{ $imgBase }}/can_do_fitting.png" alt="振袖の試着" loading="lazy">
                </div>
                <div class="sv-can-do__body">
                    <span class="sv-can-do__num">壱</span>
                    <h3 class="sv-can-do__title">振袖の試着</h3>
                    <p class="sv-can-do__desc">気になる一着を、 実際に羽織ってみることができます。</p>
                </div>
            </div>
            <div class="sv-can-do__card">
                <div class="sv-can-do__img">
                    <img src="{{ $imgBase }}/can_do_consult.png" alt="料金のご相談" loading="lazy">
                </div>
                <div class="sv-can-do__body">
                    <span class="sv-can-do__num">弐</span>
                    <h3 class="sv-can-do__title">料金のご相談</h3>
                    <p class="sv-can-do__desc">プラン内容・お支払い方法など、 何でもお気軽に。</p>
                </div>
            </div>
            <div class="sv-can-do__card">
                <div class="sv-can-do__img">
                    <img src="{{ $imgBase }}/can_do_care.png" alt="お手入れ相談" loading="lazy">
                </div>
                <div class="sv-can-do__body">
                    <span class="sv-can-do__num">参</span>
                    <h3 class="sv-can-do__title">お手入れ相談</h3>
                    <p class="sv-can-do__desc">タンスのお着物を、 ご持参いただいてのご相談も。</p>
                </div>
            </div>
            <div class="sv-can-do__card">
                <div class="sv-can-do__img">
                    <img src="{{ $imgBase }}/can_do_photo.png" alt="記念のお写真" loading="lazy">
                </div>
                <div class="sv-can-do__body">
                    <span class="sv-can-do__num">四</span>
                    <h3 class="sv-can-do__title">記念のお写真</h3>
                    <p class="sv-can-do__desc">ご試着のお姿を、 そのままお写真にお残しいただけます。</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ========== 振袖プレビュー ========== --}}
<section
    class="sv-section sv-section--beige"
    x-data="{
        openImg: null,
        currentIdx: 0,
        total: {{ count($previewImages) }},
        observer: null,
        initSlider() {
            const scroller = this.$refs.preview;
            if (!scroller) return;
            // スマホ時のみ IntersectionObserver でアクティブ判定
            if (window.matchMedia('(max-width: 719px)').matches) {
                this.observer = new IntersectionObserver((entries) => {
                    entries.forEach(e => {
                        if (e.isIntersecting && e.intersectionRatio > 0.6) {
                            const idx = parseInt(e.target.dataset.idx, 10);
                            if (!isNaN(idx)) this.currentIdx = idx;
                        }
                    });
                }, { root: scroller, threshold: [0.6] });
                Array.from(scroller.children).forEach(c => this.observer.observe(c));
            }
        },
        scrollTo(idx) {
            const scroller = this.$refs.preview;
            const child = scroller?.children?.[idx];
            if (child) child.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        },
    }"
    x-init="$nextTick(() => initSlider())"
>
    <div class="sv-wrap">
        <p class="sv-eyebrow">— COLLECTION —</p>
        <h2 class="sv-section__heading">こんな振袖があります</h2>
        <p class="sv-section__lead">
            ほんの一例です。 店頭ではさらに多くのお着物をご覧いただけます。
        </p>

        <div class="sv-preview" x-ref="preview">
            @foreach($previewImages as $i => $img)
                <div class="sv-preview__item" data-idx="{{ $i }}" @click="openImg = '{{ $img['url'] }}'">
                    <img src="{{ $img['url'] }}" alt="{{ $img['alt'] }}" loading="lazy">
                </div>
            @endforeach
        </div>

        {{-- ドットインジケータ（モバイルのみ表示） --}}
        <div class="sv-preview-dots" aria-label="スライドナビゲーション">
            @foreach($previewImages as $i => $img)
                <button
                    type="button"
                    class="sv-preview-dots__dot"
                    :class="{ 'is-active': currentIdx === {{ $i }} }"
                    @click="scrollTo({{ $i }})"
                    aria-label="画像 {{ $i + 1 }} を表示"
                ></button>
            @endforeach
        </div>

        <p class="sv-preview__note">
            画像はサンプルです。 実際の取り扱いお着物は店頭にてご確認ください。
        </p>

        {{-- Lightbox --}}
        <div
            class="sv-lightbox"
            x-show="openImg !== null"
            x-cloak
            x-transition.opacity.duration.200ms
            @click.self="openImg = null"
            @keydown.escape.window="openImg = null"
        >
            <button type="button" class="sv-lightbox__close" @click="openImg = null" aria-label="閉じる">×</button>
            <img :src="openImg" alt="">
        </div>
    </div>
</section>

{{-- ========== FAQ ========== --}}
<section class="sv-section">
    <div class="sv-wrap">
        <p class="sv-eyebrow">— FAQ —</p>
        <h2 class="sv-section__heading">よくあるご質問</h2>
        <p class="sv-section__lead">不安・気になる点を、 あらかじめ解消しておけます。</p>

        <div class="sv-faq">
            <details class="sv-faq__item">
                <summary class="sv-faq__q">予約しないと入れませんか？</summary>
                <div class="sv-faq__a">
                    ご予約のない場合でもご来店いただけますが、 ご予約の方を優先してご案内するため
                    お待たせする場合がございます。 30秒で完了するご予約をおすすめしております。
                </div>
            </details>
            <details class="sv-faq__item">
                <summary class="sv-faq__q">来店だけで料金はかかりますか？</summary>
                <div class="sv-faq__a">
                    ご来店・ご相談・お見積もりは <strong>すべて無料</strong> です。
                    お気軽にお越しください。
                </div>
            </details>
            <details class="sv-faq__item">
                <summary class="sv-faq__q">強引な営業はされませんか？</summary>
                <div class="sv-faq__a">
                    お客様のお気持ちを最優先しております。
                    その日にご決断いただく必要はございませんので、 どうぞご安心ください。
                </div>
            </details>
            <details class="sv-faq__item">
                <summary class="sv-faq__q">所要時間はどのくらいですか？</summary>
                <div class="sv-faq__a">
                    ご相談のみであれば <strong>30分程度</strong>、 ご試着まで含めるなら
                    1時間〜1時間半ほどお見積もりください。 もちろんお時間に合わせます。
                </div>
            </details>
            <details class="sv-faq__item">
                <summary class="sv-faq__q">家族や友人と一緒でも大丈夫？</summary>
                <div class="sv-faq__a">
                    お母様・お友達・お子様連れも大歓迎です。 椅子・お茶のご用意もございます。
                </div>
            </details>
            <details class="sv-faq__item">
                <summary class="sv-faq__q">タンスの着物を持ち込んで相談できますか？</summary>
                <div class="sv-faq__a">
                    もちろん大丈夫です。 お手入れ・お仕立て直し・サイズ直しなど、
                    お品物を拝見しながらご提案させていただきます。
                </div>
            </details>
        </div>
    </div>
</section>

{{-- ========== 予約フォーム ========== --}}
<section class="sv-section sv-section--paper" id="reserve">
    <div class="sv-wrap">
        <p class="sv-eyebrow">— RESERVATION —</p>
        <h2 class="sv-section__heading">ご来店予約</h2>
        <p class="sv-section__lead">
            必要事項のみ、 シンプルなフォームです。 30秒ほどで完了いたします。
        </p>

        @if($isEnded)
            <div class="sv-form">
                <p style="text-align:center;margin:0;color:var(--sv-text-soft);">
                    {{ $event->ended_message_text ?? 'このご予約は受付を終了しました。' }}
                </p>
            </div>
        @elseif(empty($formSchema))
            <div class="sv-form">
                <p style="text-align:center;margin:0;color:var(--sv-text-soft);">フォームが設定されていません。</p>
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
                class="sv-form"
                method="POST"
                action="{{ route('blade-lp.reserve', $event) }}"
                x-data="{ submitting: false, values: @js($alpineInit) }"
                x-init="$watch('values.birth_date', (v) => {
                    if (!v || !('seijin_year' in values)) return;
                    const d = new Date(String(v));
                    if (Number.isNaN(d.getTime())) return;
                    const y = d.getFullYear();
                    const m = d.getMonth() + 1;
                    const day = d.getDate();
                    const isEarly = (m < 4) || (m === 4 && day === 1);
                    const seijinYear = isEarly ? (y + 20) : (y + 21);
                    values.seijin_year = String(seijinYear);
                })"
                @submit="submitting = true"
                novalidate
            >
                @csrf
                <p class="sv-form__intro">お気軽にお申込みください。</p>

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

                <button type="submit" class="sv-submit"
                    :disabled="submitting || ('privacy_agreed' in values && !values.privacy_agreed)">
                    <span x-show="!submitting">予約する</span>
                    <span x-show="submitting" x-cloak>送信中...</span>
                </button>
                <p class="sv-submit__hint" x-show="'privacy_agreed' in values && !values.privacy_agreed" x-cloak>
                    ↑ 個人情報の取り扱いに同意するとご予約いただけます
                </p>
            </form>
        @endif
    </div>
</section>

{{-- ========== 会場 ========== --}}
@if($event->venues->isNotEmpty())
<section class="sv-section sv-section--beige">
    <div class="sv-wrap">
        <p class="sv-eyebrow">— ACCESS —</p>
        <h2 class="sv-section__heading">店舗のご案内</h2>

        <div class="sv-venues {{ $event->venues->count() > 1 ? 'sv-venues--multi' : '' }}">
            @foreach($event->venues as $venue)
                <div class="sv-venue">
                    @if($venue->image_url)
                        <div class="sv-venue__img">
                            <img src="{{ $venue->image_url }}" alt="{{ $venue->name }}" loading="lazy">
                        </div>
                    @endif
                    <div class="sv-venue__body">
                        <h3 class="sv-venue__name">{{ $venue->name }}</h3>
                        <dl class="sv-venue__meta">
                            @if($venue->address)
                                <dt>住所</dt>
                                <dd>{{ $venue->address }}</dd>
                            @endif
                            @if($venue->phone)
                                <dt>電話</dt>
                                <dd>
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $venue->phone) }}" class="sv-venue__tel">
                                        {{ $venue->phone }}
                                    </a>
                                </dd>
                            @endif
                        </dl>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ========== 最終CTA ========== --}}
<section class="sv-final-cta">
    <div class="sv-wrap">
        <h2>お話だけでも、 歓迎しております。</h2>
        <p>少しでも気になったら、 まずはお気軽にどうぞ。</p>
        <div class="sv-hero__cta-group">
            <a href="#reserve" class="sv-hero__cta-button">WEBで予約する</a>
            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $ctaPhone) }}" class="sv-hero__cta-button sv-hero__cta-button--phone">
                <span>お電話で予約する</span>
                <span class="sv-hero__cta-button__num">{{ $ctaPhone }}</span>
            </a>
        </div>
    </div>
</section>

<footer class="sv-footer">
    <div class="sv-wrap">
        <p class="sv-footer__brand">{{ $brandJp }}</p>
        <p style="margin:0;">© {{ date('Y') }} all rights reserved.</p>
    </div>
</footer>
@endsection
