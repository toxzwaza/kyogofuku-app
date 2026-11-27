<script setup>
// 前撮り枠の印刷：期間（開始日〜終了日、月をまたいでよい）とスタジオを選び、ブラウザの印刷で出す。
// 1日ずつ「行＝時刻・列＝スタジオ」の表にする。日の途中ではページを切らない（1日が1ページに入らないときだけ切り、見出しの行を次のページにも出す）。
// 1ページに何日分載せるかを選べる（A4 横は横に並べ、A4 縦は縦に積む）。入りきらない日は次のページへ送る。
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import DayTable from '@/Components/PhotoSlot/PrintDayTable.vue';

const props = defineProps({
    studios: { type: Array, default: () => [] },
    selectedStudioIds: { type: Array, default: () => [] },
    from: { type: String, required: true },
    to: { type: String, required: true },
    days: { type: Array, default: () => [] },
    maxDays: { type: Number, default: 366 },
    perPage: { type: Number, default: 1 },
    perPageOptions: { type: Array, default: () => [1, 2, 3, 4, 6] },
    printedAt: { type: String, default: '' },
});

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];
const ORIENTATION_KEY = 'photoSlotPrint.orientation';
const DETAIL_KEY = 'photoSlotPrint.detail';

const readStored = (key, fallback) => {
    try {
        return localStorage.getItem(key) ?? fallback;
    } catch (e) {
        return fallback;
    }
};
const store = (key, value) => {
    try { localStorage.setItem(key, value); } catch (e) { /* 保存できなくても動く */ }
};

const form = ref({ from: props.from, to: props.to, studio_ids: [...props.selectedStudioIds] });
const orientation = ref(readStored(ORIENTATION_KEY, 'landscape') === 'portrait' ? 'portrait' : 'landscape');
const showDetail = ref(readStored(DETAIL_KEY, '1') === '1');
const perPage = ref(props.perPageOptions.includes(props.perPage) ? props.perPage : 1);
const loading = ref(false);

const errors = computed(() => usePage().props.errors || {});
const allChecked = computed(() => props.studios.length > 0 && form.value.studio_ids.length === props.studios.length);
const toggleAll = () => {
    form.value.studio_ids = allChecked.value ? [] : props.studios.map(s => s.id);
};

const show = () => {
    if (!form.value.studio_ids.length) return;
    router.get(route('admin.photo-slots.print'), { ...form.value, per_page: perPage.value }, {
        preserveScroll: true,
        onStart: () => { loading.value = true; },
        onFinish: () => { loading.value = false; },
    });
};

// 入れた期間・スタジオが表示中のものと違うときは、印刷の前に「表示」を押してもらう
const dirty = computed(() => form.value.from !== props.from
    || form.value.to !== props.to
    || [...form.value.studio_ids].sort().join(',') !== [...props.selectedStudioIds].sort().join(','));

const printNow = () => window.print();

// 用紙の向き：@page はテンプレートに書けないので head に style を足す
let pageStyle = null;
const applyPageStyle = () => {
    if (!pageStyle) {
        pageStyle = document.createElement('style');
        document.head.appendChild(pageStyle);
    }
    pageStyle.textContent = `@page { size: A4 ${orientation.value}; margin: 10mm 8mm 12mm; @bottom-center { content: counter(page) " / " counter(pages); font-size: 9pt; color: #555; } }`;
};
onBeforeUnmount(() => { pageStyle?.remove(); pageStyle = null; });
watch(orientation, (v) => { store(ORIENTATION_KEY, v); applyPageStyle(); });
watch(showDetail, (v) => store(DETAIL_KEY, v ? '1' : '0'));

// 1ページの日数を変えたら URL にも残す（読み直し・ブックマークで同じまとめ方になる）
watch(perPage, (v) => {
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', String(v));
        window.history.replaceState(window.history.state, '', url);
    } catch (e) { /* URL を変えられなくても動く */ }
});

// ---- ページ割り ----
// 用紙から余白（上10・左右8・下12mm）を引いた、印刷できる広さ（mm）
const PAGE_MM = { landscape: { w: 281, h: 188 }, portrait: { w: 194, h: 275 } };
// 何日分をいくつの列に並べるか：横は横に並べる（4・6日は2段）、縦は縦に積む（4・6日は2列）
const COLUMNS = {
    landscape: { 1: 1, 2: 2, 3: 3, 4: 2, 6: 3 },
    portrait: { 1: 1, 2: 1, 3: 1, 4: 2, 6: 2 },
};
const GAP_MM = 3;      // 日と日の間
const SAFETY_MM = 2;   // 画面と印刷の文字の差のゆとり
const PX_PER_MM = 96 / 25.4;

const gridCols = computed(() => COLUMNS[orientation.value][perPage.value] ?? 1);
// 2日分以上は文字と余白を少し詰める
const dense = computed(() => perPage.value >= 2);

const measureEl = ref(null);
const pages = ref(props.days.map((_, i) => ({ items: [i], overflow: false, sent: false })));

// 画面に見えない所で各日の表の高さを測り、上から順に「指定の日数まで・ページの高さに入るまで」詰める。
// 入らない日は次のページへ送る。1日だけでも入らない日は、その日だけのページにして表の途中で切る（見出しの行は次のページにも出る）。
const paginate = async () => {
    await nextTick();
    const el = measureEl.value;
    if (!el || !props.days.length) {
        pages.value = [];
        return;
    }
    const mm = (node) => (node ? node.getBoundingClientRect().height / PX_PER_MM : 0);
    const heights = [...el.querySelectorAll('[data-measure-day]')].map(mm);
    const header = el.querySelector('[data-measure-header]');
    const headerMm = mm(header) + parseFloat(getComputedStyle(header).marginBottom || 0) / PX_PER_MM;
    const avail = PAGE_MM[orientation.value].h - headerMm - SAFETY_MM;
    const cols = gridCols.value;
    const n = perPage.value;

    const heightOf = (items) => {
        let total = 0;
        for (let r = 0; r < items.length; r += cols) {
            total += Math.max(...items.slice(r, r + cols).map(i => heights[i])) + (r > 0 ? GAP_MM : 0);
        }
        return total;
    };

    const result = [];
    let cur = [];
    props.days.forEach((_, i) => {
        if (cur.length < n && (cur.length === 0 || heightOf([...cur, i]) <= avail)) {
            cur.push(i);
            return;
        }
        result.push({ items: cur, sent: cur.length < n });
        cur = [i];
    });
    if (cur.length) result.push({ items: cur, sent: false });
    result.forEach((p) => {
        p.overflow = p.items.length === 1 && heights[p.items[0]] > avail;
        if (p.overflow) p.sent = false; // 1日で入りきらない日は別に知らせる
    });
    pages.value = result;
};

const sentCount = computed(() => pages.value.filter(p => p.sent).length);
const overflowDays = computed(() => pages.value.filter(p => p.overflow).map(p => props.days[p.items[0]]));

onMounted(() => {
    applyPageStyle();
    paginate();
    document.fonts?.ready?.then(paginate);
});
watch([orientation, perPage, showDetail, () => props.days], paginate);

const dateLabel = (date) => {
    const [y, m, d] = date.split('-').map(Number);
    return `${y}/${m}/${d}（${WEEKDAYS[new Date(y, m - 1, d).getDay()]}）`;
};
const dayLabel = (day) => {
    const [, m, d] = day.date.split('-').map(Number);
    return `${m}/${d}（${WEEKDAYS[day.weekday]}）`;
};

const selectedNames = computed(() => props.studios
    .filter(s => props.selectedStudioIds.includes(s.id))
    .map(s => s.name)
    .join('・'));
const totals = computed(() => props.days.reduce((t, d) => ({ booked: t.booked + d.booked, capacity: t.capacity + d.capacity }), { booked: 0, capacity: 0 }));
</script>

<template>
    <Head title="前撮り枠の印刷" />

    <div class="min-h-screen bg-sumi-100 print:bg-white text-sumi-900">
        <!-- 操作（印刷には出さない） -->
        <div class="print:hidden sticky top-0 z-10 bg-white border-b border-sumi-200 shadow-sm">
            <div class="max-w-6xl mx-auto px-4 py-3 space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <Link :href="route('admin.photo-slots.index')" class="inline-flex items-center gap-1 text-sm text-sumi-600 hover:text-sumi-900">
                        <ArrowLeft :size="16" />前撮り管理へ戻る
                    </Link>
                    <h1 class="text-lg font-semibold">前撮り枠の印刷</h1>
                </div>

                <form class="flex flex-wrap items-end gap-3" @submit.prevent="show">
                    <label class="text-sm">
                        <span class="block text-xs text-sumi-600 mb-1">開始日</span>
                        <input v-model="form.from" type="date" required class="rounded-md border-sumi-300 text-sm" />
                    </label>
                    <span class="pb-2 text-sumi-500">〜</span>
                    <label class="text-sm">
                        <span class="block text-xs text-sumi-600 mb-1">終了日</span>
                        <input v-model="form.to" type="date" required :min="form.from" class="rounded-md border-sumi-300 text-sm" />
                    </label>
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-md text-sm border disabled:opacity-40"
                        :class="dirty ? 'bg-ai-700 text-white border-ai-700' : 'bg-white border-sumi-300 hover:bg-sumi-50'"
                        :disabled="loading || !form.studio_ids.length"
                    >{{ loading ? '読み込み中…' : '表示' }}</button>

                    <div class="flex items-center gap-3 text-sm ml-auto">
                        <label class="inline-flex items-center gap-1.5">
                            <span class="text-xs text-sumi-600">1ページに</span>
                            <select v-model.number="perPage" class="rounded-md border-sumi-300 text-sm py-1.5" data-test="per-page">
                                <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}日分</option>
                            </select>
                        </label>
                        <label class="inline-flex items-center gap-1.5">
                            <span class="text-xs text-sumi-600">用紙</span>
                            <select v-model="orientation" class="rounded-md border-sumi-300 text-sm py-1.5">
                                <option value="landscape">A4 横</option>
                                <option value="portrait">A4 縦</option>
                            </select>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input v-model="showDetail" type="checkbox" class="rounded border-sumi-300" />
                            <span>担当店舗・担当者・プランも出す</span>
                        </label>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md text-sm bg-ai-700 text-white hover:bg-ai-800 disabled:opacity-40"
                            :disabled="dirty || !days.length"
                            :title="dirty ? '期間・スタジオを変えたら、先に「表示」を押してください' : ''"
                            @click="printNow"
                        >
                            <Printer :size="16" />印刷
                        </button>
                    </div>
                </form>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <span class="text-xs text-sumi-600">スタジオ</span>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" :checked="allChecked" class="rounded border-sumi-300" @change="toggleAll" />
                        <span>すべて</span>
                    </label>
                    <label v-for="s in studios" :key="s.id" class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input v-model="form.studio_ids" type="checkbox" :value="s.id" class="rounded border-sumi-300" />
                        <span>{{ s.name }}<span v-if="!s.is_active" class="text-sumi-500">（停止中）</span></span>
                    </label>
                </div>

                <p v-if="errors.from || errors.to || errors.per_page" class="text-sm text-red-600">{{ errors.to || errors.from || errors.per_page }}</p>
                <p v-else-if="!form.studio_ids.length" class="text-sm text-red-600">スタジオを1つ以上選んでください。</p>
                <p v-else-if="dirty" class="text-sm text-ai-700">期間・スタジオを変えました。「表示」を押すと下のプレビューが変わります。</p>
                <template v-else>
                    <p class="text-xs text-sumi-600">
                        1日ずつ表にし、1ページに{{ perPage }}日分まで{{ perPage > 1 ? (gridCols > 1 ? '並べます' : '縦に積みます') : '載せます' }}。日の途中ではページを切りません。期間は {{ maxDays }} 日まで。
                        <span v-if="days.length" class="ml-2 font-semibold text-sumi-800">全 {{ pages.length }} ページ<template v-if="overflowDays.length">（1日で入りきらない日の続きの分は増えます）</template></span>
                    </p>
                    <p v-if="sentCount" class="text-xs text-amber-700">
                        枠が多く{{ perPage }}日分が入りきらないページが {{ sentCount }} ページあります。入りきらない日は次のページへ送りました。
                    </p>
                    <p v-if="overflowDays.length" class="text-xs text-amber-700">
                        {{ overflowDays.map(dayLabel).join('・') }} は1日で1ページに入りきらないため、その日だけ表の途中で次のページに続けます（見出しの行は次のページにも出ます）。
                    </p>
                </template>
            </div>
        </div>

        <!-- プレビュー＝印刷するもの（1枚ずつ紙に見せる） -->
        <div class="py-6 print:py-0 space-y-2 print:space-y-0">
            <p v-if="!days.length" class="sheet mx-auto bg-white shadow py-10 text-center text-sm text-sumi-600" :class="orientation">
                この期間は、選んだスタジオに登録済みの枠がありません。
            </p>
            <template v-for="(page, pi) in pages" :key="pi">
                <div class="sheet mx-auto bg-white shadow print:shadow-none" :class="[orientation, { dense }]" data-test="page">
                    <header class="sheet-header">
                        <div class="flex items-baseline justify-between gap-4">
                            <h2 class="text-base font-bold">
                                前撮り枠　{{ dateLabel(from) }} 〜 {{ dateLabel(to) }}
                            </h2>
                            <span class="text-[9pt] text-sumi-600 whitespace-nowrap">印刷 {{ printedAt }}</span>
                        </div>
                        <div class="text-[9pt] text-sumi-700">
                            スタジオ：{{ selectedNames || '（なし）' }}
                            <span class="ml-3">予約 {{ totals.booked }}／定員 {{ totals.capacity }}（{{ days.length }}日）</span>
                        </div>
                    </header>
                    <div
                        class="page-grid"
                        :class="{ 'is-block': gridCols === 1 || page.overflow }"
                        :style="{ gridTemplateColumns: `repeat(${gridCols}, minmax(0, 1fr))` }"
                    >
                        <section v-for="i in page.items" :key="days[i].date" class="day" :class="{ 'may-split': page.overflow }">
                            <DayTable :day="days[i]" :show-detail="showDetail" :dense="dense" />
                        </section>
                    </div>
                </div>
                <p class="print:hidden text-center text-xs text-sumi-500">
                    {{ pi + 1 }} / {{ pages.length }} ページ
                    <span v-if="page.sent">（{{ perPage }}日分が入りきらないので、次の日は次のページへ）</span>
                    <span v-if="page.overflow">（1日で入りきらないので、印刷では表の途中で次のページに続きます）</span>
                </p>
            </template>
        </div>

        <!-- ページ割りのための見えない測り場（印刷には出さない） -->
        <div class="measure print:hidden" aria-hidden="true">
            <div ref="measureEl" class="sheet" :class="[orientation, { dense }]">
                <header class="sheet-header" data-measure-header>
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 class="text-base font-bold">前撮り枠　{{ dateLabel(from) }} 〜 {{ dateLabel(to) }}</h2>
                        <span class="text-[9pt] whitespace-nowrap">印刷 {{ printedAt }}</span>
                    </div>
                    <div class="text-[9pt]">スタジオ：{{ selectedNames || '（なし）' }}<span class="ml-3">予約 {{ totals.booked }}／定員 {{ totals.capacity }}（{{ days.length }}日）</span></div>
                </header>
                <div class="page-grid measure-grid" :style="{ gridTemplateColumns: `repeat(${gridCols}, minmax(0, 1fr))` }">
                    <section v-for="day in days" :key="day.date" class="day" data-measure-day>
                        <DayTable :day="day" :show-detail="showDetail" :dense="dense" />
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* 画面ではA4の紙に見せる（余白も印刷と同じ：上10・左右8・下12mm） */
.sheet { padding: 10mm 8mm 12mm; box-sizing: border-box; }
.sheet.landscape { width: 297mm; min-height: 210mm; }
.sheet.portrait { width: 210mm; min-height: 297mm; }

.sheet-header { margin-bottom: 3mm; padding-bottom: 2mm; border-bottom: 1.5px solid #333; }

.page-grid { display: grid; gap: 3mm; align-items: start; }
.page-grid.is-block { display: block; }
.day { break-inside: avoid; page-break-inside: avoid; }
.page-grid.is-block .day + .day { margin-top: 3mm; }
.day.may-split { break-inside: auto; page-break-inside: auto; }

/* 測り場：同じ幅で見えない所に置く */
.measure { position: absolute; left: -10000px; top: 0; visibility: hidden; pointer-events: none; }
.measure .sheet { min-height: 0; }

@media print {
    .sheet, .sheet.landscape, .sheet.portrait { width: auto; min-height: 0; padding: 0; margin: 0; }
    .sheet { break-after: page; page-break-after: always; }
    .sheet:last-of-type { break-after: auto; page-break-after: auto; }
    .measure { display: none; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
