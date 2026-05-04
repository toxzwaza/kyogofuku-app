@extends('event.lp.layouts.base')

@section('title', '大創業祭｜京呉服 平田 福井店')
@section('description', 'タンスのきものとジュエリー、まるごと整理。京呉服 平田 大創業祭 5/22(金)〜5/25(月) 福井店にて開催。事前持込で着物丸洗い3,300円、しみ抜き・汗取り、紫真珠特別企画、ジュエリー高価下取り、たまごすくい取り。')

@php
    $imgBase = asset('images/lp/daisougyousai');
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
}
img { max-width: 100%; display: block; }
a { color: var(--ds-akane); }
[x-cloak] { display: none !important; }

.ds-wrap { max-width: 960px; margin: 0 auto; padding: 0 22px; }
.ds-wrap--narrow { max-width: 720px; }

/* ====== Sticky CTA（モバイル限定） ====== */
.ds-sticky-cta { display: none; }
@media (max-width: 879px) {
    .ds-sticky-cta {
        display: block; position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
        background: rgba(255,250,239,.96); border-top: 1px solid var(--ds-gold);
        padding: 10px 14px env(safe-area-inset-bottom, 10px);
        backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
        box-shadow: 0 -6px 24px rgba(125,28,37,.12);
    }
    .ds-sticky-cta a {
        display: block; text-align: center; padding: 14px;
        background: var(--ds-akane); color: #fff;
        font-family: 'Shippori Mincho B1', serif; font-weight: 700;
        text-decoration: none; border-radius: 4px; letter-spacing: .2em;
        font-size: 1rem; box-shadow: 0 6px 18px rgba(125,28,37,.32);
    }
    .ds-sticky-cta a::after { content: " ▶"; font-size: .8em; margin-left: 6px; }
    body { padding-bottom: 76px; }
}

/* ====== ヘッダ ====== */
.ds-header { padding: 16px 0; text-align: center; }
.ds-header__brand { font-family: 'Shippori Mincho B1', serif; font-size: .82rem;
    letter-spacing: .35em; color: var(--ds-sumi-soft); margin: 0; }
.ds-header__brand-jp { font-family: 'Yuji Syuku', serif; font-size: 1.3rem;
    color: var(--ds-akane); margin-top: 2px; letter-spacing: .15em; }

/* ====== ヒーロー（ポスター画像中心） ====== */
.ds-hero {
    position: relative; padding: 40px 0 60px; overflow: hidden;
    background:
        radial-gradient(ellipse 80% 60% at 50% 0%, rgba(255,247,222,1) 0%, transparent 70%),
        radial-gradient(circle at 12% 30%, rgba(245,212,220,.35), transparent 35%),
        radial-gradient(circle at 88% 70%, rgba(232,163,184,.3), transparent 35%),
        linear-gradient(180deg, var(--ds-bg) 0%, var(--ds-bg-2) 100%);
}
.ds-hero__inner {
    position: relative; padding: 0 22px;
    max-width: 720px; margin: 0 auto; text-align: center;
}
@media (min-width: 880px) {
    .ds-hero__inner {
        max-width: 1080px; display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 56px; align-items: center; text-align: left;
    }
    .ds-hero__poster-wrap { display: flex; justify-content: center; }
    .ds-hero__copy { padding-right: 8px; }
}

.ds-hero__poster {
    position: relative; display: inline-block; max-width: 100%;
    margin: 0 auto;
    border-radius: 6px;
    box-shadow:
        0 4px 0 rgba(0,0,0,.03),
        0 30px 70px rgba(125,28,37,.22),
        0 12px 28px rgba(125,28,37,.14);
    border: 6px solid #fffaef;
    outline: 1px solid rgba(201,169,97,.55);
    outline-offset: 0;
    transition: transform .3s ease;
}
.ds-hero__poster:hover { transform: translateY(-2px); }
.ds-hero__poster img {
    display: block; width: auto; max-width: 440px; max-height: 78vh; height: auto;
    border-radius: 2px;
}
@media (max-width: 600px) {
    .ds-hero__poster { border-width: 4px; }
    .ds-hero__poster img { max-width: 100%; max-height: none; }
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
@media (min-width: 880px) {
    .ds-hero__cta { align-items: flex-start; }
    .ds-hero__period-box { align-items: flex-start; }
    .ds-hero__points { align-items: flex-start; }
}
.ds-hero__cta-note { font-family: 'Shippori Mincho B1', serif;
    color: var(--ds-sumi-soft); font-size: .85rem; }

.ds-cta-button {
    display: inline-block; padding: 18px 56px; background: var(--ds-akane); color: #fff;
    font-family: 'Shippori Mincho B1', serif; font-weight: 700; font-size: 1.12rem;
    letter-spacing: .25em; text-decoration: none; border-radius: 4px;
    box-shadow: 0 10px 28px rgba(176,54,65,.45); transition: all .25s;
    border: 1px solid var(--ds-gold); position: relative;
}
.ds-cta-button:hover { background: var(--ds-akane-d); transform: translateY(-2px);
    box-shadow: 0 14px 36px rgba(176,54,65,.55); }
.ds-cta-button::after { content: " ▶"; font-size: .8em; margin-left: 6px; }
.ds-cta-button--ghost { background: transparent; color: #fff;
    box-shadow: 0 4px 14px rgba(0,0,0,.2); border-color: #fff; }
.ds-cta-button--ghost:hover { background: rgba(255,255,255,.12); }

/* ====== セクション共通 ====== */
.ds-section { padding: 80px 0; position: relative; }
.ds-section--paper { background: var(--ds-paper); border-top: 1px solid var(--ds-line); border-bottom: 1px solid var(--ds-line); }
.ds-section--accent { background: linear-gradient(180deg, var(--ds-bg-2) 0%, var(--ds-bg) 100%); }
.ds-section--dark { background: linear-gradient(180deg, var(--ds-purple-d) 0%, var(--ds-purple) 100%); color: #f4eaf5; }

.ds-section__heading {
    text-align: center; margin: 0 0 50px; font-family: 'Shippori Mincho B1', serif;
    font-weight: 700; font-size: clamp(1.45rem, 3.5vw, 1.95rem); color: var(--ds-sumi);
    position: relative; display: flex; align-items: center; justify-content: center; gap: 18px;
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

/* ====== オファー2: 紫真珠 ====== */
.ds-pearl-section { display: grid; gap: 36px; align-items: center;
    grid-template-columns: 1fr; max-width: 960px; margin: 0 auto; }
@media (min-width: 800px) { .ds-pearl-section { grid-template-columns: 1fr 1fr; } }
.ds-pearl-img { border-radius: 6px; overflow: hidden;
    box-shadow: 0 20px 50px rgba(0,0,0,.4); aspect-ratio: 16/10; }
.ds-pearl-img img { width: 100%; height: 100%; object-fit: cover; }
.ds-pearl-text__eyebrow { font-family: 'Shippori Mincho B1', serif; letter-spacing: .55em;
    font-size: .82rem; color: var(--ds-pink); margin: 0 0 16px; }
.ds-pearl-text__title { font-family: 'Yuji Syuku', serif; font-weight: 400;
    font-size: clamp(2.2rem, 5vw, 3rem); color: #fff; margin: 0 0 6px; letter-spacing: .12em; }
.ds-pearl-text__title small { display: block; font-family: 'Shippori Mincho B1', serif;
    font-size: .32em; letter-spacing: .4em; margin-top: 6px; opacity: .8; font-weight: 500; }
.ds-pearl-text__body { margin-top: 22px; line-height: 2.05; opacity: .92; font-size: 1rem; }
.ds-pearl-text__body strong { color: #fff; font-weight: 600; }

/* ====== オファー3: 下取り ====== */
.ds-takedori { display: grid; gap: 36px; align-items: center;
    grid-template-columns: 1fr; max-width: 920px; margin: 0 auto; }
@media (min-width: 800px) { .ds-takedori { grid-template-columns: 1fr 1fr; } }
.ds-takedori__img { border-radius: 6px; overflow: hidden; aspect-ratio: 16/10;
    box-shadow: 0 14px 36px rgba(0,0,0,.12); }
.ds-takedori__img img { width: 100%; height: 100%; object-fit: cover; }
.ds-takedori__title { font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    font-size: clamp(1.4rem, 3.5vw, 1.9rem); color: var(--ds-akane); margin: 0 0 14px;
    line-height: 1.5; }
.ds-takedori__title em { font-style: normal; color: var(--ds-akane-d);
    background: linear-gradient(transparent 65%, #ffe8a8 65%); padding: 0 4px; }
.ds-takedori p { color: var(--ds-sumi); font-family: 'Shippori Mincho B1', serif;
    line-height: 2; margin: 0 0 12px; }
.ds-takedori__points { list-style: none; padding: 0; margin: 16px 0 0; }
.ds-takedori__points li { padding: 6px 0 6px 28px; position: relative;
    font-family: 'Shippori Mincho B1', serif; }
.ds-takedori__points li::before { content: "❀"; color: var(--ds-akane);
    position: absolute; left: 0; top: 6px; }

/* ====== オファー4: すくい取り ====== */
.ds-sukui { display: grid; gap: 36px; align-items: center;
    grid-template-columns: 1fr; max-width: 920px; margin: 0 auto; }
@media (min-width: 800px) { .ds-sukui { grid-template-columns: 1fr 1fr; } }
.ds-sukui__img { border-radius: 6px; overflow: hidden; aspect-ratio: 16/10;
    box-shadow: 0 14px 36px rgba(0,0,0,.12); }
.ds-sukui__img img { width: 100%; height: 100%; object-fit: cover; }
.ds-sukui__label { display: inline-block; padding: 4px 16px; background: var(--ds-gold);
    color: #fff; font-family: 'Shippori Mincho B1', serif; font-size: .85rem;
    letter-spacing: .2em; border-radius: 2px; margin-bottom: 14px; }
.ds-sukui__title { font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    font-size: clamp(1.5rem, 4vw, 2rem); color: var(--ds-sumi); margin: 0 0 14px;
    line-height: 1.5; }
.ds-sukui__sub { color: var(--ds-akane); font-weight: 700; font-family: 'Shippori Mincho B1', serif;
    font-size: 1.05rem; margin: 0 0 8px; }
.ds-sukui p { font-family: 'Shippori Mincho B1', serif; color: var(--ds-sumi-soft); }

/* ====== ご来場の流れ ====== */
.ds-flow { display: grid; gap: 24px; max-width: 880px; margin: 0 auto;
    grid-template-columns: 1fr; }
@media (min-width: 768px) { .ds-flow { grid-template-columns: repeat(4, 1fr); } }
.ds-flow-step { background: var(--ds-paper); border: 1px solid var(--ds-line); border-radius: 6px;
    padding: 26px 20px; text-align: center; position: relative; }
.ds-flow-step__num { display: inline-flex; align-items: center; justify-content: center;
    width: 44px; height: 44px; border-radius: 50%; background: var(--ds-akane); color: #fff;
    font-family: 'Yuji Syuku', serif; font-size: 1.2rem; margin-bottom: 12px; }
.ds-flow-step__title { font-family: 'Shippori Mincho B1', serif; font-weight: 700;
    font-size: 1rem; color: var(--ds-sumi); margin: 0 0 8px; }
.ds-flow-step__desc { font-size: .85rem; color: var(--ds-sumi-soft); margin: 0; line-height: 1.7; }

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
.ds-faq__q { padding: 20px 26px; font-family: 'Shippori Mincho B1', serif;
    font-weight: 700; cursor: pointer; list-style: none; position: relative;
    padding-right: 56px; color: var(--ds-sumi); }
.ds-faq__q::-webkit-details-marker { display: none; }
.ds-faq__q::before { content: "Q."; color: var(--ds-akane); margin-right: 10px; font-weight: 800; }
.ds-faq__q::after { content: "+"; position: absolute; right: 22px; top: 50%; transform: translateY(-50%);
    font-size: 1.4rem; color: var(--ds-akane); transition: transform .2s; }
.ds-faq__item[open] .ds-faq__q::after { transform: translateY(-50%) rotate(45deg); }
.ds-faq__a { padding: 0 26px 22px 56px; color: var(--ds-sumi-soft); font-family: 'Shippori Mincho B1', serif;
    line-height: 2; font-size: .95rem; position: relative; }
.ds-faq__a::before { content: "A."; position: absolute; left: 26px; top: 0;
    color: var(--ds-gold); font-weight: 800; }

/* ====== アクセス ====== */
.ds-access { max-width: 760px; margin: 0 auto; background: var(--ds-paper);
    border: 1px solid var(--ds-line); border-radius: 6px; padding: 36px 32px; }
.ds-access dl { display: grid; grid-template-columns: 110px 1fr; gap: 14px 20px; margin: 0;
    font-family: 'Shippori Mincho B1', serif; }
.ds-access dt { color: var(--ds-akane); font-weight: 700; }
.ds-access dd { margin: 0; color: var(--ds-sumi); }

/* ====== 最終CTA・フッター ====== */
.ds-final-cta { padding: 80px 0; text-align: center;
    background: linear-gradient(135deg, var(--ds-akane) 0%, var(--ds-akane-d) 100%); color: #fff; }
.ds-final-cta h2 { font-family: 'Yuji Syuku', serif; font-size: clamp(1.8rem, 5vw, 2.6rem);
    margin: 0 0 16px; letter-spacing: .1em; }
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
    <a href="#reserve">ご来場予約はこちら</a>
</div>

{{-- ============== ヘッダ ============== --}}
<header class="ds-header">
    <div class="ds-wrap">
        <p class="ds-header__brand">KYOGOFUKU HIRATA</p>
        <p class="ds-header__brand-jp">京呉服 平田</p>
    </div>
</header>

{{-- ============== ヒーロー（チラシ画像 + コピー横並び） ============== --}}
<section class="ds-hero">
    <div class="ds-hero__inner">
        <div class="ds-hero__poster-wrap">
            <div class="ds-hero__poster">
                <img src="{{ $imgBase }}/poster.png" alt="大創業祭 5月22日(金)〜25日(月) 京呉服 平田" loading="eager">
            </div>
        </div>
        <div class="ds-hero__copy">
            <p class="ds-hero__eyebrow">FUKUI 2026 — SPRING</p>
            <h1 class="ds-hero__title">大創業祭</h1>
            <p class="ds-hero__lead">
                タンスの<strong>きもの</strong>と<strong>ジュエリー</strong>、<br>
                まるごと整理する 4 日間。
            </p>
            <div class="ds-hero__period-box">
                <p class="ds-hero__period-label">— 開催期間 —</p>
                <p class="ds-hero__period-date">2026年 5月22日（金）〜 25日（月）</p>
                <p class="ds-hero__period-venue">京呉服 平田 福井店</p>
            </div>
            <ul class="ds-hero__points">
                <li>着物丸洗い <strong>3,300円</strong>（しみ抜き・汗取りも特別価格）</li>
                <li>事前持込 3点以上で <strong>「トリマス」プレゼント</strong></li>
                <li>紫真珠 特別企画／ジュエリー <strong>高価下取り</strong></li>
                <li>ご来場記念 <strong>すくい取り</strong>（おたまご）</li>
            </ul>
            <div class="ds-hero__cta">
                <a href="#reserve" class="ds-cta-button">ご来場予約はこちら</a>
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
                お母様から譲り受けた振袖。<br>
                成人式で袖を通したきもの。<br>
                押し入れの奥に仕舞われた、思い出のジュエリー。
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
            <strong>着物のクリーニングも 京呉服 平田にお任せください。</strong>
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
            <p>京呉服 平田の確かな手仕事で、</p>
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
                判断が難しい品物こそ、京呉服 平田の職人が じっくり拝見いたします。<br>
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

{{-- ============== オファー2: 紫真珠 ============== --}}
<section class="ds-section ds-section--dark">
    <div class="ds-wrap">
        <p class="ds-eyebrow">— 企画 弐 —</p>
        <h2 class="ds-section__heading" style="color:#f4eaf5;">特別企画</h2>
        <p class="ds-section__lead">— 今がチャンス —</p>

        <div class="ds-pearl-section">
            <div class="ds-pearl-img">
                <img src="{{ $imgBase }}/pearl.png" alt="紫真珠">
            </div>
            <div class="ds-pearl-text">
                <p class="ds-pearl-text__eyebrow">SUPER PURPLE PEARL</p>
                <h3 class="ds-pearl-text__title">
                    紫真珠
                    <small>Murasaki Shinju</small>
                </h3>
                <p class="ds-pearl-text__body">
                    <strong>天然の紫を宿す、真珠の中の真珠。</strong><br>
                    透き通る艶やかさのなかに、 深く静かな紫が息づきます。<br>
                    "真珠の王様" とも称えられる、 希少なる一粒。<br>
                    あなたの装いに、 揺るぎない気品をそえる名宝を、<br>
                    この機会にぜひ、お手にとってご覧くださいませ。
                </p>
            </div>
        </div>
    </div>
</section>

{{-- ============== オファー3: 高価下取り ============== --}}
<section class="ds-section">
    <div class="ds-wrap">
        <p class="ds-eyebrow">— 企画 参 —</p>
        <h2 class="ds-section__heading">ジュエリー 高価下取り</h2>
        <p class="ds-section__lead">タンスの中で眠る、思い出の品。 もう一度 輝かせる時です。</p>

        <div class="ds-takedori">
            <div class="ds-takedori__img">
                <img src="{{ $imgBase }}/jewelry.png" alt="ヴィンテージジュエリーボックス">
            </div>
            <div>
                <h3 class="ds-takedori__title">眠るジュエリーを <em>高価下取り</em> いたします</h3>
                <p>真贋確認・査定はその場でお伝えいたします。下取り価格はお気軽にご相談ください。</p>
                <ul class="ds-takedori__points">
                    <li>査定はその場で・無料</li>
                    <li>金・プラチナ・真珠・宝石類 全般</li>
                    <li>新たなお品物への お充当も承ります</li>
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ============== オファー4: すくい取り ============== --}}
<section class="ds-section ds-section--paper">
    <div class="ds-wrap">
        <p class="ds-eyebrow">— 企画 四 / ご来場記念 —</p>
        <h2 class="ds-section__heading">たまご すくい取り</h2>

        <div class="ds-sukui">
            <div>
                <span class="ds-sukui__label">ひなたまごー</span>
                <h3 class="ds-sukui__title">どど〜んと、 すくった数だけ プレゼント！</h3>
                <p class="ds-sukui__sub">ご来場の皆さまへ、 京呉服 平田からのささやかなお礼。</p>
                <p>新鮮なたまごを、 思い切ってすくってお持ち帰りください。<br>
                ご家族の楽しいひと時にもなれば 幸いです。</p>
            </div>
            <div class="ds-sukui__img">
                <img src="{{ $imgBase }}/egg.png" alt="ひなたまごー すくい取り">
            </div>
        </div>
    </div>
</section>

{{-- ============== ご来場の流れ ============== --}}
<section class="ds-section ds-section--accent">
    <div class="ds-wrap">
        <h2 class="ds-section__heading">ご来場の流れ</h2>
        <p class="ds-section__lead">スムーズなご対応のため、 事前のご予約をおすすめしております。</p>

        <div class="ds-flow">
            <div class="ds-flow-step">
                <div class="ds-flow-step__num">壱</div>
                <h3 class="ds-flow-step__title">Web 予約</h3>
                <p class="ds-flow-step__desc">下のフォームより、ご来場希望をご予約ください。</p>
            </div>
            <div class="ds-flow-step">
                <div class="ds-flow-step__num">弐</div>
                <h3 class="ds-flow-step__title">事前お持込</h3>
                <p class="ds-flow-step__desc">お手入れご希望のお品物を、会期前に店頭へ。</p>
            </div>
            <div class="ds-flow-step">
                <div class="ds-flow-step__num">参</div>
                <h3 class="ds-flow-step__title">ご来場</h3>
                <p class="ds-flow-step__desc">5/22-25 のご都合の良い日にお越しください。</p>
            </div>
            <div class="ds-flow-step">
                <div class="ds-flow-step__num">四</div>
                <h3 class="ds-flow-step__title">ご相談・お受取</h3>
                <p class="ds-flow-step__desc">下取りのご相談、紫真珠のご拝見など。</p>
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
                <summary class="ds-faq__q">事前持込はいつまでに伺えばよいですか？</summary>
                <div class="ds-faq__a">
                    会期前日（5/21）までに店頭にお持ち込みください。 開催期間中の持込もお受けしますが、
                    特典「トリマス」プレゼントは <strong>事前持込</strong> が対象となります。
                </div>
            </details>
            <details class="ds-faq__item">
                <summary class="ds-faq__q">着物以外の品物も対象になりますか？</summary>
                <div class="ds-faq__a">
                    着物のお手入れ（丸洗い・しみ抜き・汗取り）、 真珠・ジュエリーの下取りが対象です。
                    その他のお品物につきましても、 お気軽にご相談ください。
                </div>
            </details>
            <details class="ds-faq__item">
                <summary class="ds-faq__q">下取りに費用はかかりますか？</summary>
                <div class="ds-faq__a">
                    査定は <strong>無料</strong> でその場でお伝えいたします。
                    お持ちいただくだけでも結構ですので、お気軽にお越しください。
                </div>
            </details>
            <details class="ds-faq__item">
                <summary class="ds-faq__q">紫真珠は当日お持ち帰りできますか？</summary>
                <div class="ds-faq__a">
                    在庫状況により異なります。 詳しくは店頭にてご相談ください。
                </div>
            </details>
        </div>
    </div>
</section>

{{-- ============== アクセス ============== --}}
<section class="ds-section">
    <div class="ds-wrap ds-wrap--narrow">
        <h2 class="ds-section__heading">会場のご案内</h2>
        <div class="ds-access">
            <dl>
                <dt>会場</dt>
                <dd>京呉服 平田 福井店</dd>
                <dt>会期</dt>
                <dd>2026年 5月22日（金） 〜 5月25日（月）</dd>
                <dt>開催時間</dt>
                <dd>10:00 〜 18:00 <small style="color:var(--ds-sumi-soft);">（最終日は17:00まで）</small></dd>
                <dt>住所</dt>
                <dd>※ 詳細はご予約後にご案内いたします</dd>
                <dt>お問い合わせ</dt>
                <dd>京呉服 平田 福井店 まで</dd>
            </dl>
        </div>
    </div>
</section>

{{-- ============== 最終CTA ============== --}}
<section class="ds-final-cta">
    <div class="ds-wrap">
        <h2>大創業祭、 はじまります。</h2>
        <p>
            タンスのきものとジュエリー、まるごと整理。<br>
            この4日間で、眠っていた美しさを ふたたび。
        </p>
        <a href="#reserve" class="ds-cta-button">ご来場予約はこちらから</a>
    </div>
</section>

<footer class="ds-footer">
    <div class="ds-wrap">
        <p class="ds-footer__brand">京呉服 平田</p>
        <p style="margin:0;">© {{ date('Y') }} 京呉服平田 all rights reserved.</p>
    </div>
</footer>
@endsection
