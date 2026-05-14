@extends('event.lp.layouts.base')

@section('title', '大創業祭｜京呉服 好一 岡山店・城東店')
@section('description', 'タンスのきもの、まるごと整理。京呉服 好一 大創業祭、岡山店 5/28(木)〜5/30(土)・城東店 5/31(日)〜6/1(月) で開催。事前持込で着物丸洗い3,300円、しみ抜き・汗取りを特別価格でご提供。')

@php
    $imgBase = asset('images/lp/daisougyousai_okayama');
@endphp

@section('styles')
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=noto-serif-jp:400,500,700,900|noto-sans-jp:400,500,700|shippori-mincho-b1:400,500,600,700,800|yuji-syuku:400&display=swap" rel="stylesheet">
<style>
:root {
    --ds-bg:        #fbf3df;  /* 温かいクリーム（チラシ背景に近い） */
    --ds-bg-2:      #f3e2bc;  /* 深めのクリーム */
    --ds-bg-3:      #efd8a5;  /* 縁飾り部分 */
    --ds-paper:     #fffaef;  /* 紙白 */
    --ds-pink:      #f5d4dc;
    --ds-pink-2:    #e8a3b8;
    --ds-akane:     #a32630;  /* チラシのタイトル色に合わせた濃い茜 */
    --ds-akane-d:   #7d1c25;
    --ds-akane-l:   #c34250;
    --ds-gold:      #c9a961;
    --ds-gold-d:    #9c8240;
    --ds-sumi:      #2b1d1d;
    --ds-sumi-soft: #5a4540;
    --ds-line:      #d8b89a;
    --ds-purple:    #4a2c5e;
    --ds-purple-d:  #2e1a3d;
    --ds-purple-l:  #6b3d7e;
}
*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    margin: 0;
    background:
        radial-gradient(circle at 8% 12%, rgba(245,212,220,.45), transparent 38%),
        radial-gradient(circle at 92% 84%, rgba(232,163,184,.28), transparent 46%),
        radial-gradient(circle at 50% 50%, rgba(201,169,97,.06), transparent 70%),
        var(--ds-bg);
    color: var(--ds-sumi);
    font-family: 'Noto Sans JP', 'Hiragino Sans', system-ui, sans-serif;
    line-height: 1.85;
    -webkit-font-smoothing: antialiased;
    /* 日本語の禁則処理：単語境界の改行 + 句読点の文頭禁止 */
    word-break: keep-all;
    overflow-wrap: anywhere;
    line-break: strict;
}

/* 強調インライン要素は塊として扱い、内部で改行させない */
.ds-problem h2 em,
.ds-care-importance__title em,
.ds-soudan__title em,
.ds-final-cta h2 em,
.ds-bonus__title strong,
.ds-hero__points li strong,
.ds-soudan__body strong,
.ds-care-importance__body strong {
    display: inline-block;
}

/* CTA／日付など、絶対に折り返してはいけない要素 */
.ds-cta-button,
.ds-soudan__cta-button,
.ds-hero__period-date,
.ds-hero__period-venue,
.ds-soudan__highlight,
.ds-bonus__label,
.ds-price-card__badge,
.ds-eyebrow,
.ds-care-importance__eyebrow,
.ds-soudan__eyebrow,
.ds-hero__eyebrow,
.ds-hero__cta-note,
.ds-soudan__cta-note,
.ds-flow-step__title {
    white-space: nowrap;
}

/* 価格表示は単位とセットで改行制御 */
.ds-price-card__price,
.ds-price-card__price--negotiate,
.ds-price-card__regular {
    white-space: nowrap;
}

/* 汎用：内部で改行させたくない部分にスポット適用 */
.ds-nobr { white-space: nowrap; }
img { max-width: 100%; display: block; }
a { color: var(--ds-akane); }
[x-cloak] { display: none !important; }

.ds-wrap { max-width: 960px; margin: 0 auto; padding: 0 22px; }
.ds-wrap--narrow { max-width: 720px; }

/* ====== Sticky CTA（モバイル限定） ====== */
.ds-sticky-cta { display: none; }
@media (max-width: 879px) {
    .ds-sticky-cta {
        display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
        position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
        background: rgba(255,250,239,.96); border-top: 1px solid var(--ds-gold);
        padding: 10px 14px env(safe-area-inset-bottom, 10px);
        backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
        box-shadow: 0 -6px 24px rgba(125,28,37,.12);
    }
    .ds-sticky-cta a {
        display: block; text-align: center; padding: 12px 8px;
        font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        text-decoration: none; border-radius: 4px; letter-spacing: .18em;
        font-size: .95rem;
    }
    .ds-sticky-cta__web {
        background: var(--ds-akane); color: #fff;
        box-shadow: 0 6px 18px rgba(125,28,37,.32);
    }
    .ds-sticky-cta__web::after { content: " ▶"; font-size: .8em; margin-left: 4px; }
    .ds-sticky-cta__phone {
        background: #fff; color: var(--ds-akane);
        border: 1px solid var(--ds-akane);
    }
    body { padding-bottom: 76px; }
}

/* ====== ヘッダ ====== */
.ds-header { padding: 16px 0; text-align: center; }
.ds-header__brand { font-family: 'Shippori Mincho B1', serif; font-size: .82rem;
    letter-spacing: .35em; color: var(--ds-sumi-soft); margin: 0; }
.ds-header__brand-jp { font-family: 'Yuji Syuku', serif; font-size: 1.3rem;
    color: var(--ds-akane); margin-top: 2px; letter-spacing: .15em; }

/* ====== ヒーロー（ポスター画像中心・余白なし大きく） ====== */
.ds-hero {
    position: relative; padding: 0 0 56px; overflow: hidden;
    background:
        radial-gradient(ellipse 80% 60% at 50% 0%, rgba(255,247,222,1) 0%, transparent 70%),
        radial-gradient(circle at 12% 30%, rgba(245,212,220,.35), transparent 35%),
        radial-gradient(circle at 88% 70%, rgba(232,163,184,.3), transparent 35%),
        linear-gradient(180deg, var(--ds-bg) 0%, var(--ds-bg-2) 100%);
}
.ds-hero__inner {
    position: relative; max-width: 100%; margin: 0 auto; text-align: center;
}

.ds-hero__poster-wrap {
    display: block; line-height: 0; margin: 0 0 32px;
}
.ds-hero__poster {
    display: block; width: 100%; padding: 0; margin: 0;
    border: 0; outline: none; box-shadow: none; background: transparent;
}
.ds-hero__poster img {
    display: block; width: 100%; max-width: 720px; height: auto;
    margin: 0 auto; padding: 0; border: 0;
}

.ds-hero__copy {
    padding: 0 22px; max-width: 640px; margin: 0 auto; text-align: center;
}
.ds-hero__hidden {
    position: absolute; left: -9999px; width: 1px; height: 1px;
    overflow: hidden; clip: rect(0,0,0,0);
}

.ds-hero__eyebrow {
    font-family: 'Shippori Mincho B1', serif; font-size: .82rem; letter-spacing: .55em;
    color: var(--ds-akane-d); margin: 0 0 18px;
}
.ds-hero__title {
    font-family: 'Yuji Syuku', serif; font-weight: 400;
    font-size: clamp(2.4rem, 6vw, 3.4rem); margin: 0 0 14px;
    color: var(--ds-akane); letter-spacing: .12em; line-height: 1.15;
}
.ds-hero__lead {
    font-family: 'Shippori Mincho B1', serif; font-weight: 600;
    font-size: clamp(1.05rem, 2.4vw, 1.25rem); color: var(--ds-sumi);
    margin: 0 0 22px; line-height: 1.85;
}
.ds-hero__lead strong { color: var(--ds-akane); }
.ds-hero__period-box {
    display: inline-flex; flex-direction: column; align-items: flex-start;
    background: var(--ds-paper); border: 1px solid var(--ds-gold);
    border-radius: 4px; padding: 14px 22px; margin: 0 0 24px;
    box-shadow: 0 6px 18px rgba(125,28,37,.08);
}
.ds-hero__period-label {
    font-family: 'Shippori Mincho B1', serif; font-size: .8rem;
    letter-spacing: .35em; color: var(--ds-sumi-soft); margin: 0 0 6px;
}
.ds-hero__period-date {
    font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    color: var(--ds-akane); font-size: clamp(1.05rem, 2.4vw, 1.35rem); line-height: 1.25;
    letter-spacing: .04em; margin: 0;
}
.ds-hero__period-venue {
    font-family: 'Shippori Mincho B1', serif; font-size: .85rem;
    color: var(--ds-sumi-soft); margin: 6px 0 0; font-weight: 500;
}
.ds-hero__points {
    list-style: none; padding: 0; margin: 0 0 28px;
    display: flex; flex-direction: column; gap: 8px;
}
.ds-hero__points li {
    font-family: 'Shippori Mincho B1', serif; color: var(--ds-sumi);
    font-size: .98rem; padding-left: 28px; position: relative;
}
.ds-hero__points li::before {
    content: "❀"; position: absolute; left: 0; top: 0;
    color: var(--ds-akane); font-size: 1.05em;
}
.ds-hero__points li strong { color: var(--ds-akane); font-weight: 700; }

.ds-hero__cta { display: flex; flex-direction: column; gap: 10px;
    align-items: center; }
.ds-hero__period-box { margin-left: auto; margin-right: auto; }
.ds-hero__points { align-items: center; max-width: 480px; margin-left: auto; margin-right: auto; }
.ds-hero__cta-note { font-family: 'Shippori Mincho B1', serif;
    color: var(--ds-sumi-soft); font-size: .85rem; }

/* ====== CTAボタン（WEB / 電話 共通サイズ） ====== */
.ds-cta-button {
    display: inline-flex; flex-direction: column; align-items: center; justify-content: center;
    box-sizing: border-box; min-width: 320px; min-height: 72px;
    padding: 14px 28px;
    background: linear-gradient(135deg, var(--ds-akane) 0%, var(--ds-akane-d) 100%);
    color: #fff; font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    font-size: 1.05rem; letter-spacing: .25em; line-height: 1.35;
    text-align: center; text-decoration: none;
    border: 1px solid var(--ds-gold); border-radius: 8px;
    box-shadow: 0 12px 30px rgba(176,54,65,.42), inset 0 1px 0 rgba(255,255,255,.22);
    position: relative; overflow: hidden;
    transition: transform .25s ease, box-shadow .25s ease, background .35s ease;
}
.ds-cta-button::before {
    content: ""; position: absolute; top: 0; left: -120%; width: 60%; height: 100%;
    background: linear-gradient(120deg, transparent 0%, rgba(255,255,255,.22) 50%, transparent 100%);
    transform: skewX(-20deg); transition: left .9s ease;
    pointer-events: none;
}
.ds-cta-button:hover {
    transform: translateY(-2px);
    background: linear-gradient(135deg, var(--ds-akane-d) 0%, #5d141c 100%);
    box-shadow: 0 18px 40px rgba(176,54,65,.55), inset 0 1px 0 rgba(255,255,255,.22);
}
.ds-cta-button:hover::before { left: 130%; }
.ds-cta-button:active { transform: translateY(0); box-shadow: 0 8px 20px rgba(176,54,65,.4); }
.ds-cta-button__label { display: inline-flex; align-items: center; gap: 8px; }
.ds-cta-button__label::after { content: "▶"; font-size: .72em; opacity: .9; }

.ds-cta-button--ghost { background: transparent; color: #fff;
    box-shadow: 0 4px 14px rgba(0,0,0,.2); border-color: #fff; }
.ds-cta-button--ghost:hover { background: rgba(255,255,255,.12); }

/* 電話予約ボタン（白背景の上で使う想定） */
.ds-cta-button--phone {
    background: linear-gradient(180deg, #fffaf3 0%, #fff 100%);
    color: var(--ds-akane);
    border: 1.5px solid var(--ds-akane);
    box-shadow: 0 10px 26px rgba(125,28,37,.18), inset 0 1px 0 #fff;
}
.ds-cta-button--phone:hover {
    background: linear-gradient(180deg, #fff 0%, var(--ds-paper) 100%);
    color: var(--ds-akane-d);
    border-color: var(--ds-akane-d);
    box-shadow: 0 16px 32px rgba(125,28,37,.24), inset 0 1px 0 #fff;
}
.ds-cta-button--phone .ds-cta-button__label::after { content: ""; }
.ds-cta-button__num {
    display: block; margin-top: 6px;
    font-size: 1.18rem; letter-spacing: .08em; font-weight: 800;
    font-feature-settings: "tnum" 1;
}
.ds-cta-button__num::before {
    content: "TEL."; font-size: .62em; letter-spacing: .25em;
    margin-right: 8px; padding: 2px 6px; border-radius: 3px;
    background: var(--ds-akane); color: #fff; vertical-align: 2px;
    font-weight: 700;
}

/* 最終CTA（akane背景）の上では透過＋白縁の電話ボタン */
.ds-final-cta .ds-cta-button--phone {
    background: transparent; color: #fff;
    border-color: rgba(255,255,255,.85);
    box-shadow: 0 6px 18px rgba(0,0,0,.22), inset 0 1px 0 rgba(255,255,255,.15);
}
.ds-final-cta .ds-cta-button--phone:hover {
    background: rgba(255,255,255,.12); color: #fff; border-color: #fff;
    box-shadow: 0 10px 24px rgba(0,0,0,.28);
}
.ds-final-cta .ds-cta-button--phone .ds-cta-button__num::before {
    background: #fff; color: var(--ds-akane);
}
.ds-final-cta__buttons {
    display: flex; flex-direction: column; gap: 16px;
    align-items: center; justify-content: center; flex-wrap: wrap;
}
@media (min-width: 640px) {
    .ds-final-cta__buttons { flex-direction: row; align-items: stretch; }
    .ds-final-cta__buttons .ds-cta-button { min-height: 88px; }
}

/* ====== セクション共通 ====== */
.ds-section { padding: 80px 0; position: relative; }
.ds-section--paper { background: var(--ds-paper); border-top: 1px solid var(--ds-line); border-bottom: 1px solid var(--ds-line); }
.ds-section--accent { background: linear-gradient(180deg, var(--ds-bg-2) 0%, var(--ds-bg) 100%); }
.ds-section--dark { background: linear-gradient(180deg, var(--ds-purple-d) 0%, var(--ds-purple) 100%); color: #f4eaf5; }

.ds-section__heading {
    text-align: center; margin: 0 0 50px; font-family: 'Shippori Mincho B1', serif;
    font-weight: 700; font-size: clamp(1.2rem, 3.5vw, 1.95rem); color: var(--ds-sumi);
    position: relative; display: flex; align-items: center; justify-content: center; gap: 14px;
    flex-wrap: nowrap; white-space: nowrap;
}
.ds-section__heading::before, .ds-section__heading::after {
    content: ""; flex: 0 0 auto; width: 44px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--ds-gold), transparent);
}
.ds-section--dark .ds-section__heading { color: #f4eaf5; }
.ds-section__lead { text-align: center; color: var(--ds-sumi-soft); max-width: 680px;
    margin: -28px auto 36px; font-family: 'Shippori Mincho B1', serif; font-size: 1.02rem; }
.ds-section--dark .ds-section__lead { color: #d8c8dd; }

.ds-eyebrow {
    text-align: center; font-family: 'Shippori Mincho B1', serif; font-size: .85rem;
    letter-spacing: .55em; color: var(--ds-akane-d); margin: 0 0 14px;
}
.ds-section--dark .ds-eyebrow { color: var(--ds-pink); }

/* ====== PROBLEM / 問題喚起 ====== */
.ds-problem { text-align: center; max-width: 720px; margin: 0 auto; }
.ds-problem h2 { font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    font-size: clamp(1.5rem, 4vw, 2.05rem); color: var(--ds-sumi); margin: 0 0 28px; line-height: 1.5; }
.ds-problem h2 em { font-style: normal; color: var(--ds-akane);
    background: linear-gradient(transparent 60%, #ffe8a8 60%); padding: 0 6px; }
.ds-problem p { font-family: 'Shippori Mincho B1', serif; color: var(--ds-sumi-soft);
    font-size: 1.02rem; line-height: 2.05; margin: 0 0 14px; }
.ds-problem__quote { background: var(--ds-paper); border: 1px solid var(--ds-line);
    border-radius: 6px; padding: 28px 32px; margin: 32px auto 0; max-width: 580px;
    font-family: 'Shippori Mincho B1', serif; color: var(--ds-akane-d); font-weight: 600;
    box-shadow: 0 8px 24px rgba(176,54,65,.06); position: relative; }
.ds-problem__quote::before, .ds-problem__quote::after { content: '〝';
    font-size: 2.4rem; color: var(--ds-akane); position: absolute; line-height: 1;
    font-family: 'Yuji Syuku', serif; }
.ds-problem__quote::before { top: -8px; left: 12px; }
.ds-problem__quote::after { content: '〟'; bottom: -28px; right: 12px; }

/* ====== お手入れの必要性（背景: 振袖画像） ====== */
.ds-care-importance {
    position: relative; padding: 120px 0; overflow: hidden;
    background: #2b1d1d;
}
.ds-care-importance__bg {
    position: absolute; inset: 0; background-size: cover; background-position: center;
    background-image: url('{{ $imgBase }}/kimono_quiet.png');
    filter: brightness(.78) saturate(.95);
}
.ds-care-importance__bg::after {
    content: ""; position: absolute; inset: 0;
    background:
        linear-gradient(180deg, rgba(43,29,29,.55) 0%, rgba(43,29,29,.35) 50%, rgba(43,29,29,.65) 100%),
        radial-gradient(ellipse at 30% 50%, rgba(125,28,37,.25), transparent 60%);
}
.ds-care-importance__inner { position: relative; text-align: center; color: #fff;
    max-width: 760px; margin: 0 auto; padding: 0 22px; }
.ds-care-importance__eyebrow {
    font-family: 'Shippori Mincho B1', serif; letter-spacing: .55em;
    font-size: .85rem; color: #f5d4dc; margin: 0 0 22px;
    text-shadow: 0 2px 8px rgba(0,0,0,.6);
}
.ds-care-importance__title {
    font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    font-size: clamp(1.4rem, 3.5vw, 2.05rem); margin: 0 0 26px;
    line-height: 1.85; letter-spacing: .04em;
    text-shadow: 0 2px 14px rgba(0,0,0,.6);
}
.ds-care-importance__title em { font-style: normal; color: #ffd9a0; font-weight: 700;
    border-bottom: 1px solid rgba(255,217,160,.5); padding-bottom: 2px; }
.ds-care-importance__body {
    font-family: 'Shippori Mincho B1', serif; font-size: 1.02rem;
    line-height: 2.05; color: #f5e9d3; margin: 0;
    text-shadow: 0 2px 10px rgba(0,0,0,.5);
}
.ds-care-importance__body strong { color: #fff; font-weight: 700; }
@media (max-width: 600px) {
    .ds-care-importance { padding: 80px 0; }
}

/* ====== オファー1: お手入れ ====== */
.ds-offer-cleaning__hero {
    text-align: center; padding: 48px 24px; margin: 0 auto 36px;
    background: linear-gradient(135deg, #fff 0%, #fdeae2 100%);
    border: 2px solid var(--ds-gold); border-radius: 6px;
    box-shadow: 0 14px 36px rgba(176,54,65,.08); max-width: 760px;
}
.ds-offer-cleaning__hero p { margin: 0 0 4px; font-family: 'Shippori Mincho B1', serif;
    color: var(--ds-sumi-soft); }
.ds-offer-cleaning__hero h3 { font-family: 'Yuji Syuku', serif; font-weight: 400;
    font-size: clamp(1.7rem, 4.5vw, 2.4rem); color: var(--ds-akane); margin: 8px 0;
    letter-spacing: .08em; }

.ds-pricing { display: grid; gap: 18px; margin: 0 auto 40px; max-width: 880px; }
@media (min-width: 768px) { .ds-pricing { grid-template-columns: repeat(3, 1fr); } }
.ds-price-card { background: var(--ds-paper); border: 1px solid var(--ds-line); border-radius: 6px;
    box-shadow: 0 6px 20px rgba(0,0,0,.04); position: relative; overflow: hidden;
    display: flex; flex-direction: column; }
.ds-price-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--ds-akane), var(--ds-gold), var(--ds-akane));
    z-index: 2; }
.ds-price-card--main { background: linear-gradient(180deg, #fff 0%, #fdf0eb 100%); border-color: var(--ds-akane); }
.ds-price-card__img { width: 100%; aspect-ratio: 4/3; overflow: hidden; background: var(--ds-bg-2); }
.ds-price-card__img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ds-price-card__body { padding: 24px 22px; flex: 1; display: flex; flex-direction: column; }
.ds-price-card__badge { display: inline-block; padding: 3px 12px; background: var(--ds-akane);
    color: #fff; font-family: 'Shippori Mincho B1', serif; font-size: .78rem;
    letter-spacing: .15em; border-radius: 2px; margin-bottom: 14px; align-self: flex-start; }
.ds-price-card__title { font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    font-size: 1.18rem; color: var(--ds-sumi); margin: 0 0 12px; }
.ds-price-card__price { font-family: 'Shippori Mincho B1', serif; color: var(--ds-akane);
    font-weight: 700; margin: 12px 0; line-height: 1.1; }
.ds-price-card__price strong { font-size: 2.1rem; }
.ds-price-card__price small { font-size: .8rem; color: var(--ds-sumi-soft); margin-left: 4px; font-weight: 400; }
.ds-price-card__price--negotiate { font-size: 1.3rem; }
.ds-price-card__regular { font-size: .82rem; color: var(--ds-sumi-soft); text-decoration: line-through;
    margin: 4px 0; }
.ds-price-card__desc { font-size: .9rem; color: var(--ds-sumi-soft); margin: 8px 0 0; line-height: 1.7; }

.ds-process { max-width: 880px; margin: 0 auto 40px; padding: 36px 28px;
    background: var(--ds-paper); border: 1px solid var(--ds-line); border-radius: 6px; }
.ds-process__title { font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    text-align: center; color: var(--ds-akane); margin: 0 0 24px; font-size: 1.05rem;
    letter-spacing: .15em; }
.ds-steps { display: grid; gap: 14px; grid-template-columns: repeat(2, 1fr); }
@media (min-width: 720px) { .ds-steps { grid-template-columns: repeat(6, 1fr); } }
.ds-step { text-align: center; padding: 14px 8px; }
.ds-step__num { display: inline-flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border-radius: 50%; background: var(--ds-akane); color: #fff;
    font-family: 'Shippori Mincho B1', serif; font-weight: 700; margin-bottom: 8px; }
.ds-step__label { font-family: 'Shippori Mincho B1', serif; font-size: .85rem; color: var(--ds-sumi); }

.ds-bonus { max-width: 760px; margin: 0 auto;
    text-align: center; padding: 36px 28px; border: 2px dashed var(--ds-akane); border-radius: 6px;
    background: linear-gradient(135deg, #fff 0%, #fff7ea 100%); }
.ds-bonus__label { display: inline-block; padding: 4px 18px; background: var(--ds-akane); color: #fff;
    font-family: 'Shippori Mincho B1', serif; letter-spacing: .25em; font-size: .85rem;
    border-radius: 2px; margin-bottom: 14px; }
.ds-bonus__title { font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    font-size: clamp(1.2rem, 3.4vw, 1.55rem); color: var(--ds-sumi); margin: 0 0 8px; line-height: 1.6; }
.ds-bonus__title strong { color: var(--ds-akane); font-size: 1.2em; }
.ds-bonus__sub { color: var(--ds-sumi-soft); font-family: 'Shippori Mincho B1', serif; margin: 0; }

/* ====== 相談ハードル下げ ====== */
.ds-soudan {
    max-width: 760px; margin: 0 auto; text-align: center;
    background: linear-gradient(180deg, #fff 0%, var(--ds-bg) 100%);
    border: 1px solid var(--ds-gold); border-radius: 6px;
    padding: 56px 36px; box-shadow: 0 14px 40px rgba(125,28,37,.08);
    position: relative; overflow: hidden;
}
.ds-soudan::before, .ds-soudan::after {
    content: "❀"; position: absolute; color: var(--ds-akane); opacity: .12;
    font-size: 8rem; font-family: serif;
}
.ds-soudan::before { top: -20px; left: -10px; }
.ds-soudan::after { bottom: -40px; right: -10px; transform: rotate(180deg); }
.ds-soudan__eyebrow {
    font-family: 'Shippori Mincho B1', serif; letter-spacing: .55em;
    font-size: .82rem; color: var(--ds-akane-d); margin: 0 0 14px;
    position: relative;
}
.ds-soudan__title {
    font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    font-size: clamp(1.3rem, 3.4vw, 1.8rem); color: var(--ds-sumi);
    margin: 0 0 24px; line-height: 1.65; position: relative;
}
.ds-soudan__title em { font-style: normal; color: var(--ds-akane);
    background: linear-gradient(transparent 65%, #ffe8a8 65%); padding: 0 4px; }
.ds-soudan__body {
    font-family: 'Shippori Mincho B1', serif; color: var(--ds-sumi-soft);
    font-size: 1rem; line-height: 2; margin: 0 0 24px; position: relative;
}
.ds-soudan__body strong { color: var(--ds-akane); font-weight: 700; }
.ds-soudan__highlight {
    display: inline-block; padding: 14px 28px;
    background: var(--ds-paper); border: 1px dashed var(--ds-akane); border-radius: 4px;
    font-family: 'Shippori Mincho B1', serif; color: var(--ds-akane-d);
    font-weight: 700; margin: 0 auto 28px;
    position: relative;
}
.ds-soudan__cta { position: relative; }
.ds-soudan__cta-button {
    display: inline-block; padding: 16px 44px; background: var(--ds-akane); color: #fff;
    font-family: 'Shippori Mincho B1', serif; font-weight: 700; font-size: 1.05rem;
    letter-spacing: .25em; text-decoration: none; border-radius: 4px;
    box-shadow: 0 8px 24px rgba(125,28,37,.3); transition: all .25s;
    border: 1px solid var(--ds-gold);
}
.ds-soudan__cta-button:hover { background: var(--ds-akane-d); transform: translateY(-2px); }
.ds-soudan__cta-button::after { content: " ▶"; font-size: .8em; margin-left: 4px; }
.ds-soudan__cta-note {
    display: block; margin-top: 12px; font-family: 'Shippori Mincho B1', serif;
    color: var(--ds-sumi-soft); font-size: .85rem;
}

/* ====== フォーム ====== */
.ds-form { background: var(--ds-paper); border: 1px solid var(--ds-line); border-radius: 6px;
    padding: 40px 36px; box-shadow: 0 14px 40px rgba(176,54,65,.08); max-width: 640px;
    margin: 0 auto; }
.ds-form__intro { text-align: center; margin: 0 0 28px; color: var(--ds-sumi-soft);
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
/* 予約枠（トグル） */
.lp-slot__day {
    background: #fff; border: 1px solid var(--ds-line); border-radius: 8px;
    overflow: hidden; margin-bottom: 10px; transition: box-shadow .2s;
}
.lp-slot__day:last-child { margin-bottom: 0; }
.lp-slot__day[open] { box-shadow: 0 6px 20px rgba(125,28,37,.08); }
.lp-slot__date {
    display: flex; align-items: center; gap: 12px;
    font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    font-size: 1.05rem; color: var(--ds-sumi);
    padding: 16px 20px; cursor: pointer; list-style: none;
    background: linear-gradient(90deg, var(--ds-paper) 0%, #fff 100%);
    transition: background .2s;
}
.lp-slot__date::-webkit-details-marker { display: none; }
.lp-slot__date:hover { background: #fffaef; }
.lp-slot__day[open] .lp-slot__date {
    background: linear-gradient(90deg, #fdf0eb 0%, #fffaef 100%);
    border-bottom: 1px solid var(--ds-line);
}
.lp-slot__date-icon { color: var(--ds-akane); font-size: .95em; flex-shrink: 0; }
.lp-slot__date-text { flex: 1; }
.lp-slot__date-toggle {
    width: 26px; height: 26px; display: inline-flex; align-items: center;
    justify-content: center; border-radius: 50%; background: var(--ds-paper);
    border: 1px solid var(--ds-line); color: var(--ds-akane); flex-shrink: 0;
    font-weight: 700; font-size: 1rem; transition: transform .2s, background .2s;
}
.lp-slot__day[open] .lp-slot__date-toggle {
    transform: rotate(45deg); background: var(--ds-akane); color: #fff;
    border-color: var(--ds-akane);
}
.lp-slot__grid {
    display: flex; flex-direction: column; gap: 8px;
    padding: 16px 14px;
}
.lp-slot__btn {
    background: #fff; border: 2px solid var(--ds-line); border-radius: 8px;
    padding: 14px 18px; cursor: pointer; transition: all .2s;
    font-family: inherit; text-align: left;
    display: flex; align-items: center; gap: 14px; width: 100%;
    color: var(--ds-sumi);
    position: relative;
}
.lp-slot__btn:hover:not([disabled]) {
    border-color: var(--ds-akane-l);
    box-shadow: 0 4px 14px rgba(176,54,65,.12);
    background: #fffaef;
}
.lp-slot__btn:hover:not([disabled]) .lp-slot__chevron {
    transform: translateX(3px); color: var(--ds-akane);
}
.lp-slot__btn.is-selected {
    border-color: var(--ds-akane); background: linear-gradient(90deg, #fdf0eb 0%, #fff 100%);
    box-shadow: 0 0 0 3px rgba(163,38,48,.18);
}
.lp-slot__btn.is-selected .lp-slot__chevron { color: var(--ds-akane); }
.lp-slot__btn[disabled] {
    background: #f5f0e8; color: #aaa; cursor: not-allowed;
    border-color: var(--ds-line); opacity: .75;
}
.lp-slot__time {
    font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    font-size: 1.18rem; margin: 0; color: var(--ds-sumi);
    flex-shrink: 0; min-width: 130px;
}
.lp-slot__btn[disabled] .lp-slot__time { color: #999; }
.lp-slot__center { flex: 1; display: flex; align-items: center; gap: 10px; min-width: 0; }
.lp-slot__badges { display: flex; flex-wrap: wrap; gap: 6px; }
.lp-slot__badge {
    display: inline-flex; align-items: center; padding: 3px 12px;
    border-radius: 999px; font-size: .75rem; font-weight: 700; white-space: nowrap;
    font-family: 'Noto Sans JP', sans-serif; letter-spacing: .03em;
}
.lp-slot__badge--full {
    background: linear-gradient(90deg, #9a9a9a, #707070); color: #fff;
}
.lp-slot__badge--nokori {
    background: linear-gradient(90deg, #d96477, #a32630); color: #fff;
    box-shadow: 0 2px 6px rgba(163,38,48,.3);
}
.lp-slot__badge--nerai {
    background: linear-gradient(90deg, #f4d68a, #e0b14a); color: #6b4c00;
    box-shadow: 0 2px 6px rgba(201,169,97,.3);
}
.lp-slot__remaining {
    font-size: .9rem; color: var(--ds-sumi-soft); margin: 0;
    font-family: 'Shippori Mincho B1', serif; font-weight: 600;
    flex-shrink: 0; text-align: right;
}
.lp-slot__btn[disabled] .lp-slot__remaining { color: #aaa; }
.lp-slot__chevron {
    color: var(--ds-line); font-size: 1rem;
    transition: transform .2s, color .2s; flex-shrink: 0;
}
.lp-slot__btn[disabled] .lp-slot__chevron { display: none; }
@media (max-width: 480px) {
    .lp-slot__btn { padding: 12px 14px; gap: 10px; }
    .lp-slot__time { font-size: 1.05rem; min-width: 110px; }
    .lp-slot__remaining { font-size: .82rem; }
    .lp-slot__badge { font-size: .7rem; padding: 2px 9px; }
}

.ds-submit { display: block; width: 100%; padding: 19px; background: var(--ds-akane);
    color: #fff; border: 0; font-family: 'Shippori Mincho B1', serif; font-size: 1.18rem;
    font-weight: 700; cursor: pointer; border-radius: 4px; letter-spacing: .25em;
    transition: background .2s, transform .2s; box-shadow: 0 10px 28px rgba(176,54,65,.28); }
.ds-submit:hover:not([disabled]) { background: var(--ds-akane-d); transform: translateY(-1px); }
.ds-submit[disabled] { opacity: .6; cursor: not-allowed; }

/* ====== FAQ ====== */
.ds-faq { max-width: 760px; margin: 0 auto; }
.ds-faq__item { background: var(--ds-paper); border: 1px solid var(--ds-line); border-radius: 6px;
    margin-bottom: 12px; overflow: hidden; transition: box-shadow .2s; }
.ds-faq__item[open] { box-shadow: 0 6px 20px rgba(0,0,0,.05); }
.ds-faq__q { padding: 18px 44px 18px 18px; font-family: 'Shippori Mincho B1', serif;
    font-weight: 700; cursor: pointer; list-style: none; position: relative;
    color: var(--ds-sumi); font-size: .95rem; line-height: 1.7; }
.ds-faq__q::-webkit-details-marker { display: none; }
.ds-faq__q::before { content: "Q."; color: var(--ds-akane); margin-right: 8px; font-weight: 800;
    display: inline-block; }
.ds-faq__q::after { content: "+"; position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
    font-size: 1.4rem; color: var(--ds-akane); transition: transform .2s; }
.ds-faq__item[open] .ds-faq__q::after { transform: translateY(-50%) rotate(45deg); }
.ds-faq__a { padding: 0 18px 18px 46px; color: var(--ds-sumi-soft); font-family: 'Shippori Mincho B1', serif;
    line-height: 2; font-size: .92rem; position: relative; }
.ds-faq__a::before { content: "A."; position: absolute; left: 18px; top: 0;
    color: var(--ds-gold); font-weight: 800; }
@media (min-width: 600px) {
    .ds-faq__q { padding: 20px 56px 20px 26px; font-size: 1rem; }
    .ds-faq__q::after { right: 22px; }
    .ds-faq__a { padding: 0 26px 22px 56px; font-size: .95rem; }
    .ds-faq__a::before { left: 26px; }
}

/* ====== 会場 ====== */
.ds-venue {
    background: var(--ds-paper); border: 1px solid var(--ds-line); border-radius: 6px;
    overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,.04);
    margin-bottom: 20px;
}
.ds-venue:last-child { margin-bottom: 0; }
.ds-venue__img { width: 100%; aspect-ratio: 16/9; overflow: hidden; background: var(--ds-bg-2); }
.ds-venue__img img { width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform .4s ease; }
.ds-venue:hover .ds-venue__img img { transform: scale(1.02); }
.ds-venue__body { padding: 28px 32px; }
.ds-venue__name {
    font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    font-size: 1.3rem; color: var(--ds-akane); margin: 0 0 12px;
}
.ds-venue__desc {
    color: var(--ds-sumi-soft); font-size: .95rem;
    margin: 0 0 18px; font-family: 'Shippori Mincho B1', serif; line-height: 1.85;
}
.ds-venue__meta {
    display: grid; grid-template-columns: 110px 1fr; gap: 12px 20px; margin: 0;
    font-family: 'Shippori Mincho B1', serif;
}
.ds-venue__meta dt { color: var(--ds-akane); font-weight: 700; font-size: .92rem; }
.ds-venue__meta dd { margin: 0; color: var(--ds-sumi); font-size: .96rem; line-height: 1.7; }
.ds-venue__tel { color: var(--ds-akane); font-weight: 700; text-decoration: none;
    border-bottom: 1px dashed var(--ds-akane); }
.ds-venue__tel:hover { color: var(--ds-akane-d); border-bottom-style: solid; }
@media (max-width: 600px) {
    .ds-venue__body { padding: 22px 20px; }
    .ds-venue__meta { grid-template-columns: 80px 1fr; gap: 10px 14px; }
    .ds-venue__meta dt { font-size: .88rem; }
    .ds-venue__meta dd { font-size: .92rem; }
}

/* ====== 最終CTA・フッター ====== */
.ds-final-cta { padding: 80px 0; text-align: center;
    background: linear-gradient(135deg, var(--ds-akane) 0%, var(--ds-akane-d) 100%); color: #fff; }
.ds-final-cta h2 { font-family: 'Yuji Syuku', serif; font-size: clamp(1.55rem, 5vw, 2.6rem);
    margin: 0 0 16px; letter-spacing: .06em; line-height: 1.3; }
.ds-final-cta p { margin: 0 0 30px; font-family: 'Shippori Mincho B1', serif; opacity: .92; }
.ds-final-cta .ds-cta-button { background: #fff; color: var(--ds-akane);
    border-color: var(--ds-gold); box-shadow: 0 12px 30px rgba(0,0,0,.25); }
.ds-final-cta .ds-cta-button:hover { background: var(--ds-bg); color: var(--ds-akane-d); }

.ds-footer { padding: 40px 0 28px; text-align: center; color: var(--ds-sumi-soft);
    font-family: 'Shippori Mincho B1', serif; font-size: .85rem;
    background: var(--ds-paper); border-top: 1px solid var(--ds-line); }
.ds-footer__brand { font-family: 'Yuji Syuku', serif; font-size: 1.25rem; color: var(--ds-akane);
    margin: 0 0 6px; letter-spacing: .1em; }

/* 区切り装飾 */
.ds-divider { display: flex; align-items: center; justify-content: center;
    margin: 0 auto; max-width: 280px; gap: 14px; color: var(--ds-gold); font-size: 1.4rem; }
.ds-divider::before, .ds-divider::after { content: ""; flex: 1; height: 1px;
    background: linear-gradient(90deg, transparent, var(--ds-gold), transparent); }

@media (max-width: 640px) {
    .ds-section { padding: 56px 0; }
    .ds-form { padding: 26px 20px; }
    .ds-offer-cleaning__hero { padding: 32px 20px; }
    .ds-process { padding: 24px 18px; }
}
</style>
@endsection

@section('content')

{{-- ============== Sticky CTA（モバイル時のみ表示） ============== --}}
<div class="ds-sticky-cta">
    <a href="#reserve" class="ds-sticky-cta__web">WEBで予約</a>
    <a href="tel:086-242-1529" class="ds-sticky-cta__phone">お電話で予約</a>
</div>

{{-- ============== ヘッダ ============== --}}
<header class="ds-header">
    <div class="ds-wrap">
        <p class="ds-header__brand">KYOGOFUKU KOUICHI</p>
        <p class="ds-header__brand-jp">京呉服 好一</p>
    </div>
</header>

{{-- ============== ヒーロー（チラシ画像 + コピー横並び） ============== --}}
<section class="ds-hero">
    <div class="ds-hero__inner">
        <div class="ds-hero__poster-wrap">
            <div class="ds-hero__poster">
                <img src="{{ $imgBase }}/poster.png" alt="大創業祭 5月28日(木)〜6月1日(月) 京呉服 好一" loading="eager">
            </div>
        </div>
        <h1 class="ds-hero__hidden">大創業祭 — 京呉服 好一 岡山店・城東店</h1>
        <div class="ds-hero__copy">
            <div class="ds-hero__period-box">
                <p class="ds-hero__period-label">— 開催期間 —</p>
                <p class="ds-hero__period-date">2026年 5月28日（木）〜 6月1日（月）</p>
                <p class="ds-hero__period-venue">京呉服 好一 岡山店・城東店</p>
            </div>
            <div class="ds-hero__cta">
                <a href="#reserve" class="ds-cta-button">
                    <span class="ds-cta-button__label">WEBで予約する</span>
                </a>
                <a href="tel:086-242-1529" class="ds-cta-button ds-cta-button--phone">
                    <span class="ds-cta-button__label">お電話で予約する</span>
                    <span class="ds-cta-button__num">086-242-1529</span>
                </a>
                <small class="ds-hero__cta-note">▼ 30秒で完了 ／ 事前予約優先でご案内</small>
            </div>
        </div>
    </div>
</section>

{{-- ============== PROBLEM / 痛み喚起 ============== --}}
<section class="ds-section">
    <div class="ds-wrap">
        <div class="ds-problem">
            <p class="ds-eyebrow">— PROBLEM —</p>
            <h2>タンスの中、<br>そのまま <em>眠らせていませんか？</em></h2>
            <p>
                お母様から譲り受けた着物、<br>
                成人式で一度袖を通した着物。<br>
                押し入れの奥に、 そっと仕舞い込まれた一枚。
            </p>
            <p>
                美しい品々は、 手をかけるほどに、永くお傍にいてくれます。<br>
                けれど、放っておけば——
            </p>
            <div class="ds-problem__quote">
                シミや汚れは放置しておくと、<br>
                <strong style="color:var(--ds-akane);font-size:1.1em;">3年〜7年で黄変</strong> してしまいます。
            </div>
        </div>
    </div>
</section>

{{-- ============== お手入れの必要性 ============== --}}
<section class="ds-care-importance">
    <div class="ds-care-importance__bg" role="presentation"></div>
    <div class="ds-care-importance__inner">
        <p class="ds-care-importance__eyebrow">— なぜ、お手入れが必要なのか —</p>
        <h2 class="ds-care-importance__title">
            着物の寿命は、<br>
            <em>"上手なお手入れ"</em> と <em>"上手な保管"</em> で決まります。
        </h2>
        <p class="ds-care-importance__body">
            いつまでも大切に着ていただきたいから、<br>
            着物のクリーニングも、<br>
            <strong>京呉服 好一にお任せください。</strong>
        </p>
    </div>
</section>

{{-- ============== オファー1: 着物お手入れ ============== --}}
<section class="ds-section">
    <div class="ds-wrap">
        <p class="ds-eyebrow">— 企画 壱 —</p>
        <h2 class="ds-section__heading">着物お手入れキャンペーン</h2>
        <p class="ds-section__lead">
            事前持込限定。 丸洗い・しみ抜き・汗取りを、特別価格でご提供いたします。
        </p>

        <div class="ds-offer-cleaning__hero">
            <p><span class="ds-nobr">京呉服 好一</span>の確かな手仕事で、</p>
            <h3>眠らせていた一枚を、<br>もう一度 輝く一枚へ。</h3>
        </div>

        <div class="ds-pricing">
            <div class="ds-price-card ds-price-card--main">
                <div class="ds-price-card__img">
                    <img src="{{ $imgBase }}/maruarai.png" alt="着物の丸洗い" loading="lazy">
                </div>
                <div class="ds-price-card__body">
                    <span class="ds-price-card__badge">MAIN</span>
                    <h3 class="ds-price-card__title">丸洗い</h3>
                    <p class="ds-price-card__regular">通常 4,950円〜</p>
                    <p class="ds-price-card__price"><strong>3,300</strong><small>円（税込）／ 1点</small></p>
                    <p class="ds-price-card__desc">
                        有機溶剤によるドライクリーニング。<br>
                        油性の汚れ・埃などの除去に有効。<br>
                        <strong style="color:var(--ds-akane);">何枚でも 同一価格。</strong>
                    </p>
                </div>
            </div>

            <div class="ds-price-card">
                <div class="ds-price-card__img">
                    <img src="{{ $imgBase }}/shimi.png" alt="しみ抜き加工" loading="lazy">
                </div>
                <div class="ds-price-card__body">
                    <span class="ds-price-card__badge">CARE</span>
                    <h3 class="ds-price-card__title">しみ抜き加工</h3>
                    <p class="ds-price-card__regular">通常 ご相談</p>
                    <p class="ds-price-card__price ds-price-card__price--negotiate">特別価格でご提供</p>
                    <p class="ds-price-card__desc">
                        食事や汗ジミなど、部分的に付いてしまった汚れを丁寧に落とします。
                    </p>
                </div>
            </div>

            <div class="ds-price-card">
                <div class="ds-price-card__img">
                    <img src="{{ $imgBase }}/ase.png" alt="汗取り加工" loading="lazy">
                </div>
                <div class="ds-price-card__body">
                    <span class="ds-price-card__badge">CARE</span>
                    <h3 class="ds-price-card__title">汗取り加工</h3>
                    <p class="ds-price-card__regular">通常 ご相談</p>
                    <p class="ds-price-card__price ds-price-card__price--negotiate">特別価格でご提供</p>
                    <p class="ds-price-card__desc">
                        きものを傷めぬよう、水を使った 手仕事 で しっかりと汗を取り除きます。
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============== 相談ハードル下げ ============== --}}
<section class="ds-section ds-section--paper">
    <div class="ds-wrap">
        <div class="ds-soudan">
            <p class="ds-soudan__eyebrow">— お気軽にどうぞ —</p>
            <h2 class="ds-soudan__title">
                「お手入れが必要かどうか、<br>
                わからない」 そんな方こそ、<br>
                <em>一度お持ち寄りください。</em>
            </h2>
            <p class="ds-soudan__body">
                どこを直せばいいか、 何にお金をかけるべきか。<br>
                判断が難しい品物こそ、<span class="ds-nobr">京呉服 好一</span>の職人が<span class="ds-nobr">じっくり拝見いたします</span>。<br>
                ご相談だけのご来店も、 心より歓迎しております。
            </p>
            <div class="ds-soudan__highlight">
                ❀ ご診断・お見積もり 無料 ❀
            </div>
            <div class="ds-soudan__cta">
                <a href="#reserve" class="ds-soudan__cta-button">まずは相談する</a>
                <small class="ds-soudan__cta-note">ご予約フォームに「ご相談のみ」でも大丈夫です</small>
            </div>
        </div>
    </div>
</section>

{{-- ============== 予約フォーム ============== --}}
<section class="ds-section" id="reserve">
    <div class="ds-wrap">
        <p class="ds-eyebrow">— RESERVATION —</p>
        <h2 class="ds-section__heading">ご来場のご予約</h2>
        <p class="ds-section__lead">下記フォームよりお気軽にお申込みください。</p>

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

                <button type="submit" class="ds-submit" :disabled="submitting">
                    <span x-show="!submitting">予約を申し込む</span>
                    <span x-show="submitting" x-cloak>送信中...</span>
                </button>
            </form>
        @endif
    </div>
</section>

{{-- ============== FAQ ============== --}}
<section class="ds-section ds-section--paper">
    <div class="ds-wrap">
        <p class="ds-eyebrow">— FAQ —</p>
        <h2 class="ds-section__heading">よくあるご質問</h2>

        <div class="ds-faq">
            <details class="ds-faq__item">
                <summary class="ds-faq__q">予約は必要ですか？</summary>
                <div class="ds-faq__a">
                    ご予約をいただいた方を優先してご案内いたしますので、 事前のWeb予約をおすすめしております。
                    当日のご来場も承りますが、お待たせする場合がございます。
                </div>
            </details>
            <details class="ds-faq__item">
                <summary class="ds-faq__q">事前持込はいつまで受け付けていますか？</summary>
                <div class="ds-faq__a">
                    各会場の<strong>会期前日</strong>までに、ご来店店舗へお持ち込みください。
                    （岡山店：5/27までに岡山店へ／城東店：5/30までに城東店へ）
                    開催期間中の持込もお受けしますが、特典「トリマス」プレゼントは <strong>事前持込</strong> が対象となります。
                </div>
            </details>
            <details class="ds-faq__item">
                <summary class="ds-faq__q">対象となるお品物は？</summary>
                <div class="ds-faq__a">
                    着物のお手入れ（丸洗い・しみ抜き・汗取り）が対象です。
                    その他のお品物につきましても、 お気軽にご相談ください。
                </div>
            </details>
        </div>
    </div>
</section>

{{-- ============== 会場のご案内 ============== --}}
@if($event->venues->isNotEmpty())
<section class="ds-section">
    <div class="ds-wrap ds-wrap--narrow">
        <h2 class="ds-section__heading">会場のご案内</h2>

        @foreach($event->venues as $venue)
            <div class="ds-venue">
                @if($venue->image_url)
                    <div class="ds-venue__img">
                        <img src="{{ $venue->image_url }}" alt="{{ $venue->name }}" loading="lazy">
                    </div>
                @endif
                <div class="ds-venue__body">
                    <h3 class="ds-venue__name">{{ $venue->name }}</h3>
                    @if($venue->description)
                        <p class="ds-venue__desc">{{ $venue->description }}</p>
                    @endif
                    <dl class="ds-venue__meta">
                        @if($venue->address)
                            <dt>住所</dt>
                            <dd>{{ $venue->address }}</dd>
                        @endif
                        @if($venue->phone)
                            <dt>電話番号</dt>
                            <dd>
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $venue->phone) }}" class="ds-venue__tel">
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

{{-- ============== 最終CTA ============== --}}
<section class="ds-final-cta">
    <div class="ds-wrap">
        <h2>大創業祭、はじまります。</h2>
        <p>
            タンスのきもの、まるごと整理。<br>
            この5日間で、眠っていた美しさをふたたび。
        </p>
        <div class="ds-final-cta__buttons">
            <a href="#reserve" class="ds-cta-button">
                <span class="ds-cta-button__label">WEBで予約する</span>
            </a>
            <a href="tel:086-242-1529" class="ds-cta-button ds-cta-button--phone">
                <span class="ds-cta-button__label">お電話で予約する</span>
                <span class="ds-cta-button__num">086-242-1529</span>
            </a>
        </div>
    </div>
</section>

<footer class="ds-footer">
    <div class="ds-wrap">
        <p class="ds-footer__brand">京呉服 好一</p>
        <p style="margin:0;">© {{ date('Y') }} 京呉服 好一 all rights reserved.</p>
    </div>
</footer>
@endsection
