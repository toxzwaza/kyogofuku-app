<script setup>
// 前撮り枠の印刷：期間（開始日〜終了日、月をまたいでよい）とスタジオを選び、ブラウザの印刷で出す。
// 1日ずつ「行＝時刻・列＝スタジオ」の表にする。日の途中ではページを切らない（1日が1ページに入らないときだけ切り、見出しの行を次のページにも出す）。
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';

const props = defineProps({
    studios: { type: Array, default: () => [] },
    selectedStudioIds: { type: Array, default: () => [] },
    from: { type: String, required: true },
    to: { type: String, required: true },
    days: { type: Array, default: () => [] },
    maxDays: { type: Number, default: 366 },
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
const loading = ref(false);

const errors = computed(() => usePage().props.errors || {});
const allChecked = computed(() => props.studios.length > 0 && form.value.studio_ids.length === props.studios.length);
const toggleAll = () => {
    form.value.studio_ids = allChecked.value ? [] : props.studios.map(s => s.id);
};

const show = () => {
    if (!form.value.studio_ids.length) return;
    router.get(route('admin.photo-slots.print'), form.value, {
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
onMounted(applyPageStyle);
onBeforeUnmount(() => { pageStyle?.remove(); pageStyle = null; });
watch(orientation, (v) => { store(ORIENTATION_KEY, v); applyPageStyle(); });
watch(showDetail, (v) => store(DETAIL_KEY, v ? '1' : '0'));

const dateLabel = (date) => {
    const [y, m, d] = date.split('-').map(Number);
    return `${y}/${m}/${d}（${WEEKDAYS[new Date(y, m - 1, d).getDay()]}）`;
};
const dayLabel = (day) => {
    const [, m, d] = day.date.split('-').map(Number);
    return `${m}/${d}（${WEEKDAYS[day.weekday]}）`;
};
const weekdayClass = (w) => (w === 0 ? 'is-sun' : w === 6 ? 'is-sat' : '');

const selectedNames = computed(() => props.studios
    .filter(s => props.selectedStudioIds.includes(s.id))
    .map(s => s.name)
    .join('・'));
const totals = computed(() => props.days.reduce((t, d) => ({ booked: t.booked + d.booked, capacity: t.capacity + d.capacity }), { booked: 0, capacity: 0 }));

const cellOf = (day, col, time) => day.cells?.[col.key]?.[time] || null;
const shopsOf = (day, col) => {
    const names = new Set();
    Object.values(day.cells?.[col.key] || {}).forEach(c => c.seats.forEach(s => s.shops.forEach(n => names.add(n))));
    return [...names].join('・');
};
const seatDetail = (seat) => [seat.shops.join('・'), seat.user, seat.plan].filter(Boolean).join(' / ');
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

                <p v-if="errors.from || errors.to" class="text-sm text-red-600">{{ errors.to || errors.from }}</p>
                <p v-else-if="!form.studio_ids.length" class="text-sm text-red-600">スタジオを1つ以上選んでください。</p>
                <p v-else-if="dirty" class="text-sm text-ai-700">期間・スタジオを変えました。「表示」を押すと下のプレビューが変わります。</p>
                <p v-else class="text-xs text-sumi-600">
                    1日ずつ表にします。日の途中ではページを切りません。期間は {{ maxDays }} 日まで。
                </p>
            </div>
        </div>

        <!-- プレビュー＝印刷するもの -->
        <div class="py-6 print:py-0">
            <div class="sheet mx-auto bg-white shadow print:shadow-none" :class="orientation">
                <header class="sheet-header">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 class="text-base font-bold">
                            前撮り枠　{{ dateLabel(from) }} 〜 {{ dateLabel(to) }}
                        </h2>
                        <span class="text-[9pt] text-sumi-600 whitespace-nowrap">印刷 {{ printedAt }}</span>
                    </div>
                    <div class="text-[9pt] text-sumi-700">
                        スタジオ：{{ selectedNames || '（なし）' }}
                        <span v-if="days.length" class="ml-3">予約 {{ totals.booked }}／定員 {{ totals.capacity }}（{{ days.length }}日）</span>
                    </div>
                </header>

                <p v-if="!days.length" class="py-10 text-center text-sm text-sumi-600">
                    この期間は、選んだスタジオに登録済みの枠がありません。
                </p>

                <section v-for="day in days" :key="day.date" class="day">
                    <table class="day-table">
                        <colgroup>
                            <col class="time-col-w" />
                            <col v-for="col in day.columns" :key="col.key" />
                        </colgroup>
                        <thead>
                            <tr>
                                <th :colspan="day.columns.length + 1" class="day-head">
                                    <span :class="weekdayClass(day.weekday)">{{ dayLabel(day) }}</span>
                                    <span class="day-count">予約 {{ day.booked }}／定員 {{ day.capacity }}</span>
                                </th>
                            </tr>
                            <tr>
                                <th class="time-col">時刻</th>
                                <th v-for="col in day.columns" :key="col.key" class="studio-head">
                                    <div>{{ col.studio.name }}</div>
                                    <div v-if="shopsOf(day, col)" class="studio-shops">{{ shopsOf(day, col) }}</div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="time in day.times" :key="time">
                                <th class="time-col">{{ time }}</th>
                                <td v-for="col in day.columns" :key="col.key + time" :class="cellOf(day, col, time) ? '' : 'no-slot'">
                                    <ul v-if="cellOf(day, col, time)">
                                        <li v-for="seat in cellOf(day, col, time).seats" :key="seat.id" :class="seat.customer ? 'booked' : 'vacant'">
                                            <template v-if="seat.customer">
                                                <span class="name">{{ seat.customer.name }}</span>
                                                <span v-if="showDetail && seatDetail(seat)" class="detail">{{ seatDetail(seat) }}</span>
                                                <span v-if="showDetail && seat.remarks" class="detail">備考：{{ seat.remarks }}</span>
                                            </template>
                                            <template v-else>空き</template>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* 画面ではA4の紙に見せる（幅は用紙から余白を引いた分） */
.sheet { padding: 10mm 8mm; }
.sheet.landscape { width: 297mm; }
.sheet.portrait { width: 210mm; }

.sheet-header { margin-bottom: 3mm; padding-bottom: 2mm; border-bottom: 1.5px solid #333; }

.day { margin-bottom: 4mm; break-inside: avoid; page-break-inside: avoid; }
.day-table { width: 100%; border-collapse: collapse; font-size: 9pt; table-layout: fixed; }
.day-table thead { display: table-header-group; }
.day-table tr { break-inside: avoid; page-break-inside: avoid; }
.day-table th, .day-table td { border: 1px solid #888; padding: 1mm 1.5mm; vertical-align: top; }
.day-head { text-align: left; font-size: 10.5pt; background: #e8e8e8; }
.day-count { margin-left: 4mm; font-size: 8.5pt; font-weight: normal; color: #444; }
.is-sat { color: #1d4ed8; }
.is-sun { color: #b91c1c; }
.time-col-w { width: 14mm; }
.time-col { text-align: center; background: #f3f3f3; font-variant-numeric: tabular-nums; }
.studio-head { text-align: left; background: #f8f8f8; font-weight: 600; }
.studio-shops { font-size: 7.5pt; font-weight: normal; color: #555; }
.no-slot { background: #ececec; }
.day-table ul { margin: 0; padding: 0; list-style: none; }
.day-table li + li { border-top: 1px dotted #bbb; margin-top: 0.5mm; padding-top: 0.5mm; }
.booked .name { font-weight: 600; }
.booked .detail { display: block; font-size: 7.5pt; color: #444; }
.vacant { color: #888; }

@media print {
    .sheet, .sheet.landscape, .sheet.portrait { width: auto; padding: 0; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
