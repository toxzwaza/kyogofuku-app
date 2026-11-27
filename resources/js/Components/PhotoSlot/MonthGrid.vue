<script setup>
// 前撮りの表の画面：選んだスタジオ（複数）の1か月。登録済みの枠がある日・時刻だけを出す。
// 列は「日付×スタジオ」、行は時刻。マスの中は席ごとにお客様の名前か「空き」
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, Printer } from 'lucide-vue-next';

const props = defineProps({
    studios: { type: Array, default: () => [] },
    // 成人式お支度でも使うため、差し替えられるようにしている（既定は前撮り）
    gridRoute: { type: String, default: 'admin.photo-slots.grid' },
    storageKey: { type: String, default: 'photoSlotGrid.studioIds' },
    placeLabel: { type: String, default: 'スタジオ' },
    seatLabel: { type: String, default: '枠' },
    // 期間を指定した印刷の画面（前撮りだけ。渡したときだけ「印刷」を出す）
    printRoute: { type: String, default: null },
});
const emit = defineEmits(['book', 'manage']);

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];
const STORAGE_KEY = props.storageKey;

const readStoredStudios = () => {
    try {
        const v = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        if (!Array.isArray(v)) return null;
        const ids = v.map(Number).filter(id => props.studios.some(s => s.id === id));
        return ids.length ? ids : null;
    } catch (e) {
        return null;
    }
};

// 既定は動いているスタジオすべて
const studioIds = ref(readStoredStudios() ?? props.studios.filter(s => s.is_active).map(s => s.id));
const month = ref(null); // null は今月
const data = ref(null);
const loading = ref(false);
const error = ref('');

let seq = 0;
const load = async () => {
    if (!studioIds.value.length) {
        data.value = null;
        return;
    }
    const mine = ++seq;
    loading.value = true;
    error.value = '';
    try {
        const res = await axios.get(route(props.gridRoute), {
            params: { studio_ids: studioIds.value, month: month.value || undefined },
        });
        if (mine !== seq) return; // 古い応答は捨てる
        data.value = res.data;
        month.value = res.data.month;
    } catch (e) {
        if (mine === seq) error.value = '表を読み込めませんでした。';
    } finally {
        if (mine === seq) loading.value = false;
    }
};

watch(studioIds, (v) => {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(v)); } catch (e) { /* 保存できなくても動く */ }
    load();
}, { deep: true });
onMounted(load);
defineExpose({ reload: load });

const allChecked = computed(() => props.studios.length > 0 && studioIds.value.length === props.studios.length);
const toggleAll = () => {
    studioIds.value = allChecked.value ? [] : props.studios.map(s => s.id);
};

const goMonth = (ym) => {
    month.value = ym;
    load();
};
const goThisMonth = () => goMonth(null);
const pickMonth = (e) => {
    if (e.target.value) goMonth(e.target.value);
};

const monthLabel = (ym) => {
    if (!ym) return '';
    const [y, m] = ym.split('-');
    return `${y}年${Number(m)}月`;
};

// 印刷の画面へ：今見ている月の1日〜末日・選んでいるスタジオから始める（期間は印刷の画面で変えられる）
const printHref = computed(() => {
    if (!props.printRoute || !data.value?.month) return null;
    const [y, m] = data.value.month.split('-').map(Number);
    const last = new Date(y, m, 0).getDate();
    return route(props.printRoute, {
        from: `${data.value.month}-01`,
        to: `${data.value.month}-${String(last).padStart(2, '0')}`,
        studio_ids: studioIds.value,
    });
});

const cellOf = (col, time) => data.value?.cells?.[col.key]?.[time] || null;

// 見出し1段目：同じ日付の列をまとめる
const dateGroups = computed(() => {
    const groups = [];
    for (const col of data.value?.columns || []) {
        const last = groups[groups.length - 1];
        if (last && last.date === col.date) last.span++;
        else groups.push({ date: col.date, weekday: col.weekday, is_today: col.is_today, span: 1 });
    }
    return groups;
});

// 列の担当店舗（その日・スタジオの枠についている店舗をまとめる）
const shopsOf = (col) => {
    const names = new Set();
    Object.values(data.value?.cells?.[col.key] || {}).forEach(c => c.seats.forEach(s => s.shops.forEach(n => names.add(n))));
    return [...names].join('・');
};

const dayLabel = (date) => {
    const [, m, day] = date.split('-');
    return `${Number(m)}/${Number(day)}`;
};
const weekdayClass = (w) => (w === 0 ? 'text-red-600' : w === 6 ? 'text-blue-600' : '');

const cellClass = (col, time) => {
    const c = cellOf(col, time);
    const base = ['align-top border border-brand-border p-1 min-w-[7.5rem]'];
    if (!c) base.push('bg-sumi-100');
    else if (c.booked >= c.capacity) base.push('bg-uguisu-50');
    else base.push('bg-white');
    if (col.is_today) base.push('ring-2 ring-inset ring-brand-primary/40');
    if (col.is_past) base.push('opacity-70');
    return base;
};

const book = (col, time) => emit('book', { studio: col.studio, date: col.date, time });
const manage = (col, time) => {
    const c = cellOf(col, time);
    emit('manage', { studio: col.studio, date: col.date, time, capacity: c?.capacity || 0, booked: c?.booked || 0 });
};
</script>

<template>
    <div class="bg-brand-surface shadow-sm sm:rounded-lg">
        <!-- 操作：スタジオ（複数選択） -->
        <div class="px-4 py-3 border-b border-brand-border space-y-3">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                <span class="text-xs font-medium text-brand-text-muted">{{ placeLabel }}</span>
                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" :checked="allChecked" class="rounded border-brand-border text-brand-primary focus:ring-brand-primary" @change="toggleAll" />
                    <span>すべて</span>
                </label>
                <label v-for="s in studios" :key="s.id" class="inline-flex items-center gap-1.5 cursor-pointer">
                    <input v-model="studioIds" type="checkbox" :value="s.id" class="rounded border-brand-border text-brand-primary focus:ring-brand-primary" />
                    <span>{{ s.name?.trim() }}<span v-if="!s.is_active" class="text-brand-text-muted">（停止中）</span></span>
                </label>
            </div>

            <!-- 月 -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1">
                    <button type="button" class="p-2 rounded-soft border border-brand-border hover:bg-brand-surface-2 disabled:opacity-40" :disabled="!data" aria-label="前の月" @click="goMonth(data.prev_month)">
                        <ChevronLeft :size="16" />
                    </button>
                    <button type="button" class="px-3 py-1.5 rounded-soft border border-brand-border text-sm hover:bg-brand-surface-2" @click="goThisMonth">今月</button>
                    <button type="button" class="p-2 rounded-soft border border-brand-border hover:bg-brand-surface-2 disabled:opacity-40" :disabled="!data" aria-label="次の月" @click="goMonth(data.next_month)">
                        <ChevronRight :size="16" />
                    </button>
                </div>
                <input type="month" :value="month || ''" class="rounded-md border-brand-border text-sm focus:border-brand-primary focus:ring-brand-primary" aria-label="月を選ぶ" @change="pickMonth" />
                <span class="text-sm font-semibold tabular-nums text-brand-text">{{ monthLabel(data?.month) }}</span>
                <span v-if="loading" class="text-xs text-brand-text-muted">読み込み中…</span>
                <a
                    v-if="printHref && studioIds.length"
                    :href="printHref"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-soft border border-brand-border text-sm hover:bg-brand-surface-2"
                    title="期間を指定して印刷する（別のタブで開きます）"
                >
                    <Printer :size="15" />印刷
                </a>
                <div class="ml-auto flex items-center gap-3 text-xs text-brand-text-muted">
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 border border-brand-border bg-white"></span>空きあり</span>
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 border border-brand-border bg-uguisu-50"></span>満員</span>
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 border border-brand-border bg-sumi-100"></span>枠なし</span>
                </div>
            </div>

            <!-- 枠のある月（今月以降）：押すとその月へ -->
            <div v-if="data?.months?.length" class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs text-brand-text-muted mr-1">枠のある月</span>
                <button
                    v-for="m in data.months"
                    :key="m.month"
                    type="button"
                    class="px-2.5 py-1 rounded-soft border text-xs tabular-nums transition-colors"
                    :class="m.month === data.month ? 'bg-brand-primary text-white border-brand-primary' : 'border-brand-border hover:bg-brand-surface-2'"
                    :title="`予約 ${m.booked}／定員 ${m.capacity}`"
                    @click="goMonth(m.month)"
                >
                    {{ monthLabel(m.month) }}
                    <span class="ml-0.5 opacity-80">{{ m.booked }}/{{ m.capacity }}</span>
                </button>
            </div>
        </div>

        <div v-if="!studios.length" class="p-8 text-center text-sm text-brand-text-muted">選べる{{ placeLabel }}がありません。</div>
        <div v-else-if="!studioIds.length" class="p-8 text-center text-sm text-brand-text-muted">{{ placeLabel }}を1つ以上選んでください。</div>
        <div v-else-if="error" class="p-8 text-center text-sm text-red-600">{{ error }}</div>
        <div v-else-if="data && !data.columns.length" class="p-8 text-center text-sm text-brand-text-muted">
            {{ monthLabel(data.month) }}は、選んだ{{ placeLabel }}に登録済みの枠がありません。
        </div>

        <!-- 表：スマホでは横にスクロールし、時刻の列を固定する -->
        <div v-else-if="data" class="overflow-x-auto">
            <table class="border-collapse text-xs w-full">
                <thead>
                    <tr>
                        <th rowspan="2" class="sticky left-0 z-10 bg-brand-surface-2 border border-brand-border px-2 py-2 w-14">時刻</th>
                        <th
                            v-for="g in dateGroups"
                            :key="g.date"
                            :colspan="g.span"
                            class="border border-brand-border px-2 py-1.5 font-semibold"
                            :class="g.is_today ? 'bg-brand-primary text-white' : 'bg-brand-surface-2'"
                        >
                            <span class="tabular-nums">{{ dayLabel(g.date) }}</span>
                            <span class="ml-0.5" :class="g.is_today ? '' : weekdayClass(g.weekday)">({{ WEEKDAYS[g.weekday] }})</span>
                            <span v-if="g.is_today" class="ml-1 text-[10px] font-normal">今日</span>
                        </th>
                    </tr>
                    <tr>
                        <th v-for="col in data.columns" :key="col.key" class="border border-brand-border bg-brand-surface px-2 py-1 font-medium text-left">
                            <div class="truncate max-w-[10rem]">{{ col.studio.name }}</div>
                            <div v-if="shopsOf(col)" class="text-[10px] font-normal text-brand-text-muted truncate max-w-[10rem]">{{ shopsOf(col) }}</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="time in data.times" :key="time">
                        <th class="sticky left-0 z-10 bg-brand-surface-2 border border-brand-border px-2 py-1 tabular-nums font-semibold align-top">{{ time }}</th>
                        <td v-for="col in data.columns" :key="col.key + time" :class="cellClass(col, time)">
                            <template v-if="cellOf(col, time)">
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="tabular-nums text-[11px] text-brand-text-muted">{{ cellOf(col, time).booked }}/{{ cellOf(col, time).capacity }}</span>
                                    <button
                                        v-if="!col.is_past"
                                        type="button"
                                        class="p-0.5 rounded text-brand-text-muted hover:bg-brand-surface-2 hover:text-brand-primary"
                                        :title="`1${seatLabel}足す・定員を変える`"
                                        @click="manage(col, time)"
                                    >
                                        <Plus :size="14" />
                                    </button>
                                </div>
                                <ul class="space-y-0.5">
                                    <li v-for="seat in cellOf(col, time).seats" :key="seat.id">
                                        <Link
                                            v-if="seat.customer"
                                            :href="route('admin.customers.show', seat.customer.id)"
                                            class="block truncate rounded px-1 py-0.5 bg-uguisu-100 text-uguisu-900 hover:underline"
                                            :title="[seat.customer.name, seat.shops.join('・'), seat.user, seat.plan].filter(Boolean).join(' / ')"
                                        >
                                            {{ seat.customer.name }}
                                        </Link>
                                        <button
                                            v-else-if="!col.is_past"
                                            type="button"
                                            class="block w-full text-left rounded px-1 py-0.5 border border-dashed border-brand-border text-brand-text-muted hover:border-brand-primary hover:text-brand-primary"
                                            @click="book(col, time)"
                                        >
                                            空き
                                        </button>
                                        <span v-else class="block px-1 py-0.5 text-brand-text-subtle">空き</span>
                                    </li>
                                </ul>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
