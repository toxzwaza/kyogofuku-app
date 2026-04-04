<template>
    <Head :title="event.title">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="" />
        <link
            href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700;900&family=Zen+Old+Mincho:wght@400;700;900&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        ref="rootRef"
        class="lp-pastel-root"
        :style="themeStyle"
    >
        <a href="#main-content" class="skip-link">メインコンテンツへ</a>

        <div class="fixed-cta">
            <a
                href="#"
                class="cta-btn cta-web"
                aria-label="WEB予約フォームへ"
                @click.prevent="goReserve"
            >
                <span class="cta-text">WEB予約</span>
                <span class="cta-sub">24時間受付</span>
            </a>
            <a :href="`tel:${primaryPhone}`" class="cta-btn cta-phone" :aria-label="`電話で予約する ${primaryPhone}`">
                <span class="cta-text">電話予約</span>
                <span class="cta-sub">{{ primaryPhone }}</span>
            </a>
        </div>

        <header class="hero">
            <div class="hero-photo">
                <img
                    src="https://to-murakami-amzn-s3.s3.ap-northeast-1.amazonaws.com/public/events/25/3LiZWGHf0e5RHcJ2vKpDViDpVlygV6SYjtCB6BQV.webp"
                    alt="振袖フェス メインビジュアル"
                    fetchpriority="high"
                    width="1080"
                    height="1361"
                />
                <div class="hero-photo-accent" />
            </div>
            <div class="hero-content">
                <div class="hero-vertical">振袖フェス</div>
                <div class="hero-text">
                    <p class="hero-event-label">ゴールデンウィーク特別企画</p>
                    <h1 class="hero-title">
                        <span class="hero-title-deco">振袖</span><br />
                        <span class="hero-title-accent">フェス</span>
                    </h1>
                    <p class="hero-location">in 岡山</p>
                    <div class="hero-dates">
                        <div class="hero-date">
                            <span class="hero-date-month">4</span>
                            <span class="hero-date-sep">/</span>
                            <span class="hero-date-days">25<small>金</small>・26<small>土</small></span>
                        </div>
                        <div class="hero-date">
                            <span class="hero-date-month">5</span>
                            <span class="hero-date-sep">/</span>
                            <span class="hero-date-days">2<small>金</small>〜4<small>日</small></span>
                        </div>
                    </div>
                    <p class="hero-catch">
                        運命の一着に、<br />
                        きっと出会える。
                    </p>
                    <a href="#reservation" class="hero-cta">来場予約する<span class="hero-cta-arrow">&rarr;</span></a>
                </div>
            </div>
            <svg class="hero-pattern" viewBox="0 0 100 100" preserveAspectRatio="none">
                <defs>
                    <pattern :id="patternId" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                        <path
                            d="M10 0L20 10L10 20L0 10Z"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="0.25"
                            opacity="0.12"
                        />
                        <path
                            d="M10 0L10 20M0 10L20 10"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="0.12"
                            opacity="0.08"
                        />
                    </pattern>
                </defs>
                <rect width="100" height="100" :fill="`url(#${patternId})`" />
            </svg>
        </header>

        <main id="main-content">
            <section class="benefits">
                <div class="benefits-inner">
                    <div class="benefits-label">
                        <span class="label-line" />
                        <span class="label-text">ご成約特典</span>
                        <span class="label-line" />
                    </div>
                    <div class="benefits-main">
                        <p class="benefits-headline">
                            事前予約 &amp; ご成約で<br /><span class="benefits-accent">豪華プレゼント</span>
                        </p>
                        <p class="benefits-sub">以下のいずれかをお選びいただけます</p>
                    </div>
                    <div class="benefit-select-label">以下のいずれか1つをお選びください</div>
                    <div class="benefit-options">
                        <div class="benefit-choice">
                            <div class="benefit-choice-badge">特典 A</div>
                            <div class="benefit-choice-body">
                                <div class="benefit-choice-img">
                                    <img
                                        src="https://www.mtgec.jp/assets/beauty/refa/haircare/products/finger_iron/img/img-cv-finger-iron.jpg?20240719"
                                        alt="ReFa フィンガーアイロン"
                                        loading="lazy"
                                    />
                                </div>
                                <div class="benefit-choice-text">
                                    <h3>ReFa フィンガーアイロン</h3>
                                    <p>指先感覚で使えるコンパクトヘアアイロン。前髪や顔まわりのスタイリングに。</p>
                                </div>
                            </div>
                        </div>
                        <div class="benefit-or">or</div>
                        <div class="benefit-choice">
                            <div class="benefit-choice-badge badge-b">特典 B</div>
                            <div class="benefit-choice-body">
                                <div class="benefit-choice-imgs">
                                    <img
                                        src="https://www.dior.com/dw/image/v2/BGXS_PRD/on/demandware.static/-/Sites-master_dior/default/dwa7919e42/Y0000163/Y0000163_E000001063_E01_RHC.jpg?sw=1024"
                                        alt="Dior アディクト リップグロウオイル"
                                        loading="lazy"
                                    />
                                </div>
                                <div class="benefit-choice-text">
                                    <h3>Dior コスメセット</h3>
                                    <p>MISS DIOR ブルーミングブーケ ミニミス &amp; アディクト リップグロウオイル</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="benefit-plus">
                        <div class="benefit-plus-mark">+</div>
                        <div class="benefit-plus-content">
                            <h3>某有名関西テーマパーク <span>1Dayチケット</span></h3>
                            <p>上記特典A or Bに加えて、テーマパークチケットもプレゼント!</p>
                        </div>
                    </div>
                    <p class="benefits-note">※ご成約金額220,000円（税込）以上の方が対象となります。</p>
                </div>
            </section>

            <section class="no1-section">
                <div class="no1-bg-text">NO.1</div>
                <div class="no1-content">
                    <p class="no1-area">岡山県</p>
                    <h2 class="no1-title">振袖取扱い多数</h2>
                    <div class="no1-number">N<span class="no1-o">O</span>.1</div>
                    <p class="no1-desc">
                        トレンド / 古典 / モダン<br />
                        圧倒的ラインナップ
                    </p>
                    <p class="no1-brand"><span> by 京呉服平田</span></p>
                </div>
            </section>

            <section class="gallery">
                <div class="gallery-header">
                    <h2 class="gallery-title">
                        <span class="gallery-title-ja">振袖コレクション</span>
                        <span class="gallery-title-en">FURISODE COLLECTION</span>
                    </h2>
                </div>
                <div id="galleryTrack" class="gallery-track">
                    <div v-for="(src, idx) in galleryImages" :key="idx" class="gallery-slide">
                        <img :src="src" alt="振袖コーディネート" loading="lazy" />
                    </div>
                </div>
            </section>

            <section class="hairset">
                <div class="hairset-inner">
                    <div class="hairset-badge">展示会限定!</div>
                    <h2 class="hairset-title">ヘアセット体験</h2>
                    <p class="hairset-lead">
                        写真映えするヘアセットをプロがご提案。<br />
                        そのまま最新振袖を試着することもできます。
                    </p>
                    <div class="hairset-features">
                        <div class="hairset-feature anim">
                            <span class="hf-num">01</span>
                            <div>
                                <h3>プロのヘアセット</h3>
                                <p>トレンドを押さえた写真映えスタイルを、プロのスタイリストがその場でご提案します。</p>
                            </div>
                        </div>
                        <div class="hairset-feature anim">
                            <span class="hf-num">02</span>
                            <div>
                                <h3>最新振袖をそのまま試着</h3>
                                <p>ヘアセットした状態で振袖を試着。成人式当日のイメージがそのまま体験できます。</p>
                            </div>
                        </div>
                        <div class="hairset-feature anim">
                            <span class="hf-num">03</span>
                            <div>
                                <h3>マンツーマンでご提案</h3>
                                <p>
                                    プロの振袖コーディネーターがお一人おひとりに合わせて丁寧にご説明。自分に一番似合う振袖がきっと見つかります。
                                </p>
                            </div>
                        </div>
                    </div>
                    <a href="#reservation" class="hairset-cta">ヘアセット体験を予約する <span>&rarr;</span></a>
                </div>
            </section>

            <section class="lineup">
                <div class="lineup-wrap">
                    <div class="lineup-text">
                        <p class="lineup-eyecatch">
                            圧倒的<br />
                            品揃え
                        </p>
                        <h2 class="lineup-title"><span class="lineup-num">0</span>点以上</h2>
                        <p class="lineup-desc">
                            最新トレンド、古典、モダン、レトロ ——<br />
                            あなたにぴったりの一着が必ず見つかります。
                        </p>
                    </div>
                </div>
            </section>

            <section id="plans" class="plans">
                <div class="plans-header">
                    <h2 class="plans-title">
                        <span class="plans-title-en">PLAN</span>
                        <span class="plans-title-ja">選べる2つのプラン</span>
                    </h2>
                </div>
                <div class="plans-grid plans-grid-2col">
                    <div class="plan-card anim">
                        <div class="plan-card-accent" />
                        <div class="plan-card-img">
                            <img :src="planRentalImg" alt="レンタルプラン" loading="lazy" />
                        </div>
                        <span class="plan-num">01</span>
                        <h3 class="plan-name">レンタルプラン</h3>
                        <div class="plan-price">
                            <span class="plan-price-amount" data-count="88000">0</span>
                            <span class="plan-price-unit">円<small>(税込)</small>より</span>
                        </div>
                        <p class="plan-price-note">分割払い可</p>
                        <p class="plan-lead">
                            小物一点からでもレンタル可能。<br />
                            今どきのコーデを思う存分楽しもう。
                        </p>
                        <div class="plan-includes-list">
                            <div class="plan-include-item">
                                <span class="plan-include-check" />
                                <p>
                                    前撮り記念写真フルサポート<br /><small>豪華3カットアルバムプレゼント</small>
                                </p>
                            </div>
                            <div class="plan-include-item">
                                <span class="plan-include-check" />
                                <p>
                                    成人式当日、ご仕度ご優待サービス<br /><small>ヘアセット・メイク・着付け</small>
                                </p>
                            </div>
                        </div>
                        <a href="#reservation" class="plan-cta">レンタルプランで予約</a>
                    </div>
                    <div class="plan-card anim">
                        <div class="plan-card-accent accent-lav" />
                        <div class="plan-card-img">
                            <img :src="planMamaImg" alt="ママ振袖プラン" loading="lazy" />
                        </div>
                        <span class="plan-num">02</span>
                        <h3 class="plan-name">ママ振袖プラン</h3>
                        <div class="plan-price">
                            <span class="plan-price-amount" data-count="66000">0</span>
                            <span class="plan-price-unit">円<small>(税込)</small>より</span>
                        </div>
                        <p class="plan-price-note">分割払い可</p>
                        <p class="plan-lead">
                            小物一点からでもレンタル可能。<br />
                            今どきのコーデを思う存分楽しもう。
                        </p>
                        <div class="plan-includes-list">
                            <div class="plan-include-item">
                                <span class="plan-include-check" />
                                <p>
                                    前撮り記念写真フルサポート<br /><small>ヘアセット・メイク・着付け・撮影</small>
                                </p>
                            </div>
                            <div class="plan-include-item">
                                <span class="plan-include-check" />
                                <p>
                                    記念写真3ポーズプレゼント<br /><small>豪華デザインアルバムにてプレゼント！</small>
                                </p>
                            </div>
                        </div>
                        <a href="#reservation" class="plan-cta">ママ振袖で予約</a>
                    </div>
                </div>
            </section>

            <section class="guarantee">
                <div class="guarantee-inner">
                    <img
                        src="https://to-murakami-amzn-s3.s3.ap-northeast-1.amazonaws.com/public/events/25/2q15QKkhfX36wekYC15Wq2GEXnqhiROFt75f1OmC.webp"
                        alt="あんしん保証のご案内"
                        loading="lazy"
                        class="guarantee-img anim"
                    />
                </div>
            </section>

            <section class="flow">
                <div class="flow-inner">
                    <h2 class="flow-title">
                        <span class="flow-title-en">FLOW</span>
                        <span class="flow-title-ja">ご来場の流れ</span>
                    </h2>
                    <div class="flow-timeline">
                        <div class="flow-item">
                            <div class="flow-marker"><span>1</span></div>
                            <div class="flow-body">
                                <h3>WEB予約</h3>
                                <p>下記フォームまたはお電話でご予約</p>
                            </div>
                        </div>
                        <div class="flow-item">
                            <div class="flow-marker"><span>2</span></div>
                            <div class="flow-body">
                                <h3>ご来場・受付</h3>
                                <p>当日会場へお越しください</p>
                            </div>
                        </div>
                        <div class="flow-item">
                            <div class="flow-marker"><span>3</span></div>
                            <div class="flow-body">
                                <h3>振袖選び・試着</h3>
                                <p>スタッフがお好みに合わせてご提案</p>
                            </div>
                        </div>
                        <div class="flow-item">
                            <div class="flow-marker"><span>4</span></div>
                            <div class="flow-body">
                                <h3>お見積り・ご成約</h3>
                                <p>プラン・お支払い方法を丁寧にご説明</p>
                            </div>
                        </div>
                    </div>
                    <p class="flow-note">所要時間：約90分〜2時間</p>
                </div>
            </section>

            <section id="venues" class="venues">
                <div class="venues-header">
                    <h2 class="venues-title">
                        <span class="venues-title-en">VENUE</span>
                        <span class="venues-title-ja">開催会場</span>
                    </h2>
                </div>
                <div class="venues-grid">
                    <article v-for="venue in venues" :key="venue.id" class="venue">
                        <div class="venue-photo">
                            <img
                                v-if="venue.image_url"
                                :src="venue.image_url"
                                :alt="venue.name"
                                loading="lazy"
                            />
                            <div v-if="venue.dates?.length" class="venue-date">
                                <template v-for="(block, blockIdx) in formatVenueDates(venue.dates)" :key="blockIdx">
                                    <span class="venue-date-m">{{ block.monthLabel }}</span>
                                    <span class="venue-date-d">{{ block.dayParts.join('・') }}</span>
                                </template>
                            </div>
                        </div>
                        <div class="venue-body">
                            <h3>{{ venue.name }}</h3>
                            <p v-if="venue.description" class="venue-room" v-html="venue.description" />
                            <p v-if="venue.address" class="venue-addr">{{ venue.address }}</p>
                            <p v-if="venue.phone" class="venue-tel">
                                <a :href="`tel:${venue.phone}`">{{ venue.phone }}</a>
                            </p>
                            <div v-if="venue.address && googleMapsEmbedApiKey" class="venue-map">
                                <iframe
                                    :src="getVenueMapEmbedUrl(venue.address)"
                                    width="100%"
                                    height="180"
                                    style="border: 0"
                                    allowfullscreen=""
                                    loading="lazy"
                                    title="会場の地図"
                                />
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section id="reservation" class="reservation">
                <div class="reservation-bg-text">RESERVE</div>
                <div class="reservation-inner">
                    <h2 class="reservation-title">ご来場予約</h2>
                    <p class="reservation-lead">WEB予約で待ち時間なし</p>
                    <div class="reservation-btns">
                        <a href="#" class="res-btn res-web" aria-label="WEB予約フォームへ" @click.prevent="goReserve">
                            <span class="res-btn-main">WEB予約</span>
                            <span class="res-btn-note">24時間受付中</span>
                        </a>
                        <a :href="`tel:${primaryPhone}`" class="res-btn res-tel" :aria-label="`電話で予約する ${primaryPhone}`">
                            <span class="res-btn-main">電話予約</span>
                            <span class="res-btn-note">{{ primaryPhone }}</span>
                        </a>
                    </div>
                    <p class="reservation-caveat">
                        ※ご予約なしでもご来場いただけますが、<br />
                        予約優先のためお待ちいただく場合がございます。
                    </p>
                </div>
            </section>
        </main>

        <footer class="footer">
            <p class="footer-brand">京呉服平田 / edel</p>
            <p class="footer-copy">&copy; {{ year }} 京呉服平田</p>
        </footer>

        <div
            v-if="isEnded"
            class="lp-ended-overlay"
            style="position: fixed; inset: 0; z-index: 100000; display: flex; align-items: center; justify-content: center; background-color: rgba(0, 0, 0, 0.7);"
        >
            <div style="background: #fff; border-radius: 12px; padding: 2rem; max-width: 28rem; margin: 1rem; text-align: center">
                <p style="font-size: 1.25rem; font-weight: 600; color: #1f2937">
                    このイベントは{{ endDate }}をもって終了いたしました。
                </p>
                <p v-if="event.ended_message_text" style="margin-top: 1rem; color: #dc2626; white-space: pre-line">
                    {{ event.ended_message_text }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import '@lp_design/style.css';
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { LP_GALLERY_IMAGES } from './lpGalleryImages';
import { usePastelFestLp } from './usePastelFestLp';

const props = defineProps({
    event: Object,
    images: Array,
    slideshowPositions: { type: Object, default: () => ({}) },
    slideshows: { type: Object, default: () => ({}) },
    timeslots: Array,
    shops: Array,
    venues: Array,
    documents: Array,
    isEnded: Boolean,
    endDate: String,
    canReserve: Boolean,
    showSuccess: { type: Boolean, default: false },
    successFormData: { type: Object, default: null },
    ctaButtonPositions: { type: Array, default: () => [] },
    lpThemeCssVars: { type: Object, default: () => ({}) },
});

const rootRef = ref(null);
usePastelFestLp(rootRef);

const patternId = computed(() => `asanoha-${props.event?.id ?? 'e'}`);

const themeStyle = computed(() => props.lpThemeCssVars || {});
const galleryImages = LP_GALLERY_IMAGES;
const year = new Date().getFullYear();

const planRentalImg =
    'https://to-murakami-amzn-s3.s3.ap-northeast-1.amazonaws.com/public/slideshows/12/ztKBeNGBBNcRNpWkfnk3sGpnxvwVCKYJoqG9DX5M.webp';
const planMamaImg =
    'https://to-murakami-amzn-s3.s3.ap-northeast-1.amazonaws.com/public/slideshows/12/LLzVrmnYgMcihiHfHpf5HnZSj3AyEfkeY0HfnJ71.webp';

const primaryPhone = computed(() => props.shops?.[0]?.phone || '0120-041-529');

const googleMapsEmbedApiKey = import.meta.env.VITE_GOOGLE_MAPS_EMBED_API_KEY || '';

function getVenueMapEmbedUrl(address) {
    if (!address || !googleMapsEmbedApiKey) return '';
    const q = encodeURIComponent(address);
    return `https://www.google.com/maps/embed/v1/place?key=${googleMapsEmbedApiKey}&q=${q}`;
}

function formatVenueDates(dateStrings) {
    if (!dateStrings || dateStrings.length === 0) return [];
    const sorted = [...dateStrings].sort();
    const byMonth = {};
    for (const d of sorted) {
        const [y, m] = d.split('-');
        const key = `${y}-${m}`;
        if (!byMonth[key]) byMonth[key] = [];
        byMonth[key].push(parseInt(d.split('-')[2], 10));
    }
    const monthOrder = Object.keys(byMonth).sort();
    return monthOrder.map((key) => {
        const [, m] = key.split('-');
        const monthNum = parseInt(m, 10);
        const monthLabel = `${monthNum}月`;
        const days = byMonth[key].sort((a, b) => a - b);
        const ranges = [];
        let start = days[0];
        let end = days[0];
        for (let i = 1; i < days.length; i += 1) {
            if (days[i] === end + 1) {
                end = days[i];
            } else {
                ranges.push({ start, end });
                start = days[i];
                end = days[i];
            }
        }
        ranges.push({ start, end });
        const dayParts = ranges.map(({ start: s, end: e }) => {
            if (s === e) return String(s);
            if (e === s + 1) return `${s}, ${e}`;
            return `${s} ~ ${e}`;
        });
        return { monthLabel, dayParts };
    });
}

function goReserve() {
    router.visit(route('event.reserve.page', props.event.slug));
}
</script>

<style scoped>
.venues-grid:empty::after {
    content: '';
}
</style>
