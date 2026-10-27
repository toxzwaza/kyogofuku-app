<template>
    <Head title="予約枠の期間一括作成" />
    <AdminLayout :breadcrumb="[{ label: 'イベント・予約' }, { label: event.title, href: route('admin.events.show', event.id) }, { label: '予約枠', href: route('admin.events.timeslots.index', event.id) }, { label: '期間一括作成' }]">
        <UiPageHeader
            :title="`予約枠の期間一括作成 - ${event.title}`"
            description="期間・会場・時間帯を指定してプレビューし、作成する日を確認してから登録します。既にある枠は作りません。"
        >
            <template #actions>
                <Link :href="route('admin.events.timeslots.index', event.id)">
                    <UiButton variant="ghost">予約枠一覧に戻る</UiButton>
                </Link>
            </template>
        </UiPageHeader>

        <div class="space-y-6">
            <UiCard variant="default" padding="md">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <UiFormField label="期間" required :error="errors.start_date || errors.end_date">
                            <div class="flex flex-wrap items-center gap-2">
                                <UiInput v-model="cond.start_date" type="date" size="sm" class="w-40" />
                                <span class="text-brand-text-muted">〜</span>
                                <UiInput v-model="cond.end_date" type="date" size="sm" class="w-40" />
                                <UiButton size="sm" variant="ghost" @click="setMonth(0)">今月</UiButton>
                                <UiButton size="sm" variant="ghost" @click="setMonth(1)">来月</UiButton>
                            </div>
                        </UiFormField>

                        <UiFormField v-if="venues.length" label="会場" required :error="errors.venue_ids">
                            <div class="flex flex-wrap gap-2">
                                <label v-for="v in venues" :key="v.id" :class="chipClass(cond.venue_ids.includes(v.id))">
                                    <input v-model="cond.venue_ids" type="checkbox" :value="v.id" class="sr-only" />
                                    {{ v.name }}
                                </label>
                            </div>
                        </UiFormField>
                        <p v-else class="text-sm text-brand-text-muted">このイベントには会場が設定されていないため、会場なしの枠を作成します。</p>

                        <UiFormField label="除外する曜日" hint="定休日（火・水）が初期値です">
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="(w, i) in WEEKDAYS"
                                    :key="i"
                                    type="button"
                                    :class="chipClass(cond.exclude_weekdays.includes(i), 'exclude')"
                                    @click="toggle(cond.exclude_weekdays, i)"
                                >{{ w }}</button>
                            </div>
                        </UiFormField>

                        <UiFormField label="除外する日" hint="FAIRなど店舗を空けない日。重なりのある日はプレビューで自動的に外れます" :error="errors.exclude_dates">
                            <div class="flex items-center gap-2">
                                <UiInput v-model="excludeInput" type="date" size="sm" class="w-40" />
                                <UiButton size="sm" variant="subtle" :disabled="!excludeInput" @click="addExcludeDate">追加</UiButton>
                            </div>
                            <div v-if="manualExcludeDates.length" class="mt-2 flex flex-wrap gap-1.5">
                                <button
                                    v-for="d in manualExcludeDates"
                                    :key="d"
                                    type="button"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-brand-surface-2 border border-brand-border text-xs"
                                    @click="toggle(cond.exclude_dates, d)"
                                >{{ formatDay(d) }} <X :size="12" /></button>
                            </div>

                            <!-- 同じ店舗の別イベントに枠がある日（×で除外する／しないを切り替え） -->
                            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50/60 p-2.5">
                                <p class="text-xs font-medium text-amber-800 mb-1.5">
                                    別イベントに枠がある日
                                    <span v-if="overlapsLoading" class="font-normal text-brand-text-muted">（確認中…）</span>
                                    <span v-else-if="overlapDates.length" class="font-normal">{{ overlapDates.length }}日</span>
                                </p>
                                <p v-if="!overlapsLoading && !overlapDates.length" class="text-xs text-brand-text-muted">期間内に重なる別イベントはありません。</p>
                                <ul v-else class="space-y-1 max-h-56 overflow-y-auto">
                                    <li
                                        v-for="o in overlapDates"
                                        :key="o.date"
                                        class="flex items-start gap-2 rounded px-2 py-1 text-xs"
                                        :class="cond.exclude_dates.includes(o.date) ? 'bg-akane-50 text-brand-text-muted' : 'bg-brand-surface'"
                                    >
                                        <span class="w-20 shrink-0 tabular-nums font-medium" :class="[weekdayClass(o.date), cond.exclude_dates.includes(o.date) && 'line-through']">{{ formatDay(o.date) }}</span>
                                        <span class="flex-1 min-w-0">
                                            <span v-for="e in o.events" :key="e.event_id" class="block leading-snug">{{ e.title }}（{{ e.venue_names.join('・') }}）</span>
                                        </span>
                                        <span v-if="cond.exclude_weekdays.includes(dayOfWeek(o.date))" class="shrink-0 text-brand-text-subtle">除外曜日</span>
                                        <button
                                            v-else
                                            type="button"
                                            class="shrink-0 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded border"
                                            :class="cond.exclude_dates.includes(o.date) ? 'border-brand-danger text-brand-danger bg-brand-surface' : 'border-brand-border text-brand-text-muted hover:border-brand-danger hover:text-brand-danger'"
                                            :title="cond.exclude_dates.includes(o.date) ? '除外を取り消す' : 'この日を除外する'"
                                            @click="toggleExcludeDate(o.date)"
                                        >
                                            <X :size="12" />{{ cond.exclude_dates.includes(o.date) ? '除外中' : '除外' }}
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </UiFormField>
                    </div>

                    <div class="space-y-4">
                        <UiFormField label="時間帯" required :error="errors.times">
                            <div class="flex gap-4 text-sm mb-2">
                                <label class="inline-flex items-center gap-1.5"><input v-model="timeMode" type="radio" value="range" /> 時間と間隔で指定</label>
                                <label class="inline-flex items-center gap-1.5" :class="!templates.length && 'opacity-50'">
                                    <input v-model="timeMode" type="radio" value="template" :disabled="!templates.length" /> テンプレートから
                                </label>
                            </div>

                            <div v-if="timeMode === 'range'" class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                    <UiInput v-model="range.start" type="time" size="sm" class="w-28" />
                                    <span>〜</span>
                                    <UiInput v-model="range.end" type="time" size="sm" class="w-28" />
                                    <span class="text-brand-text-muted">開始まで</span>
                                    <select v-model.number="range.interval" class="rounded-md border-brand-border text-sm py-1">
                                        <option :value="30">30分ごと</option>
                                        <option :value="60">1時間ごと</option>
                                        <option :value="90">1時間30分ごと</option>
                                        <option :value="120">2時間ごと</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <span>定員</span>
                                    <UiInput v-model.number="range.capacity" type="number" min="1" size="sm" class="w-20" />
                                    <span class="text-brand-text-muted">枠／時間</span>
                                </div>
                            </div>
                            <div v-else>
                                <select v-model.number="templateId" class="w-full rounded-md border-brand-border text-sm">
                                    <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}（{{ t.slots.length }}枠）</option>
                                </select>
                            </div>

                            <div class="mt-3">
                                <p class="text-xs text-brand-text-muted mb-1">作成する時刻（クリックで除外／戻す）</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="t in candidateTimes"
                                        :key="t.time"
                                        type="button"
                                        :class="chipClass(!skippedTimes.includes(t.time))"
                                        @click="toggle(skippedTimes, t.time)"
                                    >
                                        {{ t.time }}<span v-if="timeMode === 'template' || t.capacity !== 1" class="text-[10px] opacity-70">×{{ t.capacity }}</span>
                                    </button>
                                    <span v-if="!candidateTimes.length" class="text-xs text-brand-danger">時刻がありません</span>
                                </div>
                            </div>
                        </UiFormField>

                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="cond.is_active" type="checkbox" class="rounded border-brand-border text-brand-primary focus:ring-brand-primary" />
                            作成した枠をすぐ有効にする（予約フォームに表示）
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <span v-if="stale && preview" class="text-xs text-brand-warning">条件が変わりました。もう一度プレビューしてください。</span>
                    <UiButton variant="primary" :loading="previewing" :disabled="!times.length" @click="runPreview">
                        <template #leading><Eye :size="14" /></template>
                        プレビュー
                    </UiButton>
                </div>
            </UiCard>

            <UiCard v-if="preview" variant="default" padding="md">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="font-semibold text-brand-text">プレビュー</h3>
                        <p class="text-sm text-brand-text-muted">
                            作成予定：<strong class="text-brand-text tabular-nums">{{ selectedCells.length }}</strong>日・会場／
                            <strong class="text-brand-text tabular-nums">{{ selectedSlotCount }}</strong>枠
                            <span v-if="conflictCount" class="ml-2 text-amber-700">重なり {{ conflictCount }}件（初期状態では作成しません）</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-uguisu-100 border border-brand-success"></span>作成</span>
                        <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-amber-50 border border-amber-400"></span>他イベントと重なり</span>
                        <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-brand-surface-2 border border-brand-border"></span>対象外・既存</span>
                    </div>
                </div>

                <div class="overflow-x-auto border border-brand-border rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-brand-surface-2 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium whitespace-nowrap w-32">日付</th>
                                <th v-for="v in preview.venues" :key="v.id ?? 'none'" class="px-3 py-2 text-left font-medium whitespace-nowrap">
                                    <label class="inline-flex items-center gap-1.5">
                                        <input
                                            type="checkbox"
                                            :checked="venueAllChecked(v.id)"
                                            class="rounded border-brand-border text-brand-primary"
                                            @change="setVenueAll(v.id, $event.target.checked)"
                                        />
                                        {{ v.name }}
                                    </label>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr v-for="row in rows" :key="row.date" :class="row.weekend && 'bg-brand-surface-2/40'">
                                <td class="px-3 py-1.5 whitespace-nowrap tabular-nums" :class="weekdayClass(row.date)">{{ formatDay(row.date) }}</td>
                                <td v-for="cell in row.cells" :key="cellKey(cell)" class="px-2 py-1">
                                    <label
                                        class="flex items-start gap-2 rounded px-2 py-1 border"
                                        :class="cellClass(cell)"
                                    >
                                        <input
                                            v-if="selectable(cell)"
                                            type="checkbox"
                                            :checked="checked[cellKey(cell)]"
                                            class="mt-0.5 rounded border-brand-border text-brand-primary"
                                            @change="checked[cellKey(cell)] = $event.target.checked"
                                        />
                                        <span class="min-w-0">
                                            <span class="block text-xs font-medium">{{ statusLabel(cell) }}</span>
                                            <span v-for="c in cell.conflicts" :key="c.event_id" class="block text-[11px] text-amber-800 leading-snug">
                                                {{ c.reason }}：{{ c.title }}（{{ c.venue_names.join('・') }}）
                                            </span>
                                        </span>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="errors.cells" class="mt-3 text-sm text-brand-danger">{{ errors.cells }}</div>
                <div class="mt-4 flex items-center justify-end gap-3">
                    <UiButton variant="primary" :loading="saving" :disabled="stale || !selectedCells.length" @click="submit">
                        <template #leading><Plus :size="14" /></template>
                        {{ selectedSlotCount }}枠を登録する
                    </UiButton>
                </div>
            </UiCard>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { UiPageHeader, UiCard, UiButton, UiFormField, UiInput } from '@/Components/UI';
import { Eye, Plus, X } from 'lucide-vue-next';

const props = defineProps({
    event: Object,
    venues: Array,
    shops: Array,
    templates: Array,
});

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const monthRange = (offset) => {
    const now = new Date();
    return [new Date(now.getFullYear(), now.getMonth() + offset, 1), new Date(now.getFullYear(), now.getMonth() + offset + 1, 0)];
};
const [nextStart, nextEnd] = monthRange(1);

const cond = reactive({
    start_date: ymd(nextStart),
    end_date: ymd(nextEnd),
    venue_ids: props.venues.map((v) => v.id),
    exclude_weekdays: [2, 3],
    exclude_dates: [],
    is_active: true,
});
const timeMode = ref('range');
const range = reactive({ start: '10:00', end: '17:00', interval: 60, capacity: 1 });
const templateId = ref(props.templates[0]?.id ?? null);
const skippedTimes = ref([]);
const excludeInput = ref('');

const setMonth = (offset) => {
    const [s, e] = monthRange(offset);
    cond.start_date = ymd(offset === 0 ? new Date() : s);
    cond.end_date = ymd(e);
};
const toggle = (list, value) => {
    const i = list.indexOf(value);
    if (i === -1) list.push(value);
    else list.splice(i, 1);
};
const addExcludeDate = () => {
    if (excludeInput.value && !cond.exclude_dates.includes(excludeInput.value)) {
        cond.exclude_dates.push(excludeInput.value);
        cond.exclude_dates.sort();
    }
    excludeInput.value = '';
};

const toggleExcludeDate = (d) => {
    toggle(cond.exclude_dates, d);
    cond.exclude_dates.sort();
};

// 期間内に同じ店舗の別イベントの枠がある日（期間を変えたら取り直す）
const overlapDates = ref([]);
const overlapsLoading = ref(false);
const overlapDateSet = computed(() => new Set(overlapDates.value.map((o) => o.date)));
const manualExcludeDates = computed(() => cond.exclude_dates.filter((d) => !overlapDateSet.value.has(d)));
let overlapTimer = null;
let overlapSeq = 0;
const fetchOverlaps = () => {
    clearTimeout(overlapTimer);
    if (!cond.start_date || !cond.end_date || cond.end_date < cond.start_date) {
        overlapDates.value = [];
        return;
    }
    overlapTimer = setTimeout(async () => {
        const seq = ++overlapSeq;
        overlapsLoading.value = true;
        try {
            const { data } = await axios.get(route('admin.events.timeslots.bulk.overlaps', props.event.id), {
                params: { start_date: cond.start_date, end_date: cond.end_date },
            });
            if (seq === overlapSeq) overlapDates.value = data.dates ?? [];
        } catch {
            if (seq === overlapSeq) overlapDates.value = [];
        } finally {
            if (seq === overlapSeq) overlapsLoading.value = false;
        }
    }, 300);
};
watch(() => [cond.start_date, cond.end_date], fetchOverlaps, { immediate: true });

const toMinutes = (hm) => {
    const [h, m] = (hm || '').split(':').map(Number);
    return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : null;
};
const candidateTimes = computed(() => {
    if (timeMode.value === 'template') {
        return props.templates.find((t) => t.id === templateId.value)?.slots ?? [];
    }
    const start = toMinutes(range.start);
    const end = toMinutes(range.end);
    const cap = Math.max(1, Number(range.capacity) || 1);
    if (start === null || end === null || end < start || !range.interval) return [];
    const list = [];
    for (let m = start; m <= end && list.length < 48; m += range.interval) {
        list.push({ time: `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`, capacity: cap });
    }
    return list;
});
const times = computed(() => candidateTimes.value.filter((t) => !skippedTimes.value.includes(t.time)));

// ---- プレビュー ----
const preview = ref(null);
const checked = reactive({});
const stale = ref(false);
const previewing = ref(false);
const saving = ref(false);
const errors = ref({});

const payload = () => ({ ...cond, times: times.value });

watch([cond, range, timeMode, templateId, skippedTimes], () => { stale.value = true; }, { deep: true });

const cellKey = (c) => `${c.date}|${c.venue_id ?? ''}`;
const selectable = (c) => c.status === 'create' || c.status === 'conflict';

const runPreview = async () => {
    previewing.value = true;
    errors.value = {};
    try {
        const { data } = await axios.post(route('admin.events.timeslots.bulk.preview', props.event.id), payload());
        preview.value = data;
        Object.keys(checked).forEach((k) => delete checked[k]);
        data.cells.forEach((c) => { if (selectable(c)) checked[cellKey(c)] = c.status === 'create'; });
        stale.value = false;
    } catch (e) {
        errors.value = flatErrors(e.response?.data?.errors) || { times: e.response?.data?.message || 'プレビューに失敗しました。' };
    } finally {
        previewing.value = false;
    }
};
const flatErrors = (errs) => {
    if (!errs) return null;
    const out = {};
    Object.entries(errs).forEach(([k, v]) => {
        out[k.split('.')[0]] ??= Array.isArray(v) ? v[0] : v;
    });
    return out;
};

const rows = computed(() => {
    if (!preview.value) return [];
    const byDate = new Map();
    preview.value.cells.forEach((c) => {
        if (!byDate.has(c.date)) byDate.set(c.date, { date: c.date, weekend: [0, 6].includes(new Date(`${c.date}T00:00:00`).getDay()), cells: [] });
        byDate.get(c.date).cells.push(c);
    });
    return [...byDate.values()];
});
const selectedCells = computed(() => (preview.value?.cells ?? []).filter((c) => selectable(c) && checked[cellKey(c)]));
const selectedSlotCount = computed(() => selectedCells.value.reduce((n, c) => n + c.new_times.length, 0));
const conflictCount = computed(() => (preview.value?.cells ?? []).filter((c) => c.status === 'conflict').length);

const venueCells = (venueId) => (preview.value?.cells ?? []).filter((c) => c.venue_id === venueId && selectable(c));
const venueAllChecked = (venueId) => {
    const list = venueCells(venueId);
    return list.length > 0 && list.every((c) => checked[cellKey(c)]);
};
const setVenueAll = (venueId, value) => venueCells(venueId).forEach((c) => { checked[cellKey(c)] = value; });

const statusLabel = (c) => {
    const n = c.new_times.length;
    const existing = c.existing_count ? `（既存${c.existing_count}枠）` : '';
    switch (c.status) {
        case 'past': return '過去日';
        case 'weekday': return '除外曜日';
        case 'excluded': return '除外日';
        case 'existing': return `既存のみ${existing}`;
        case 'conflict': return `重なり・${n}枠${existing}`;
        default: return `${n}枠作成${existing}`;
    }
};
const cellClass = (c) => {
    if (c.status === 'create' || (c.status === 'conflict' && checked[cellKey(c)])) {
        return checked[cellKey(c)] ? 'bg-uguisu-50 border-brand-success cursor-pointer' : 'bg-brand-surface border-brand-border text-brand-text-muted cursor-pointer';
    }
    if (c.status === 'conflict') return 'bg-amber-50 border-amber-300 cursor-pointer';
    return 'bg-brand-surface-2 border-transparent text-brand-text-subtle';
};

const formatDay = (d) => {
    const dt = new Date(`${d}T00:00:00`);
    return `${dt.getMonth() + 1}/${dt.getDate()}（${WEEKDAYS[dt.getDay()]}）`;
};
const dayOfWeek = (d) => new Date(`${d}T00:00:00`).getDay();
const weekdayClass = (d) => {
    const w = new Date(`${d}T00:00:00`).getDay();
    return w === 0 ? 'text-red-600' : w === 6 ? 'text-blue-600' : '';
};
const chipClass = (on, kind = 'include') => [
    'inline-flex items-center gap-1 px-3 py-1 rounded-lg border text-sm cursor-pointer select-none transition-colors',
    on
        ? (kind === 'exclude' ? 'bg-akane-50 border-brand-danger text-brand-danger' : 'bg-ai-50 border-brand-primary text-brand-primary')
        : 'bg-brand-surface border-brand-border text-brand-text-muted',
];

const submit = () => {
    if (!confirm(`${selectedSlotCount.value}枠を登録します。よろしいですか？`)) return;
    saving.value = true;
    router.post(route('admin.events.timeslots.bulk.store', props.event.id), {
        ...payload(),
        cells: selectedCells.value.map((c) => ({ date: c.date, venue_id: c.venue_id })),
    }, {
        onError: (errs) => { errors.value = flatErrors(errs); },
        onFinish: () => { saving.value = false; },
    });
};
</script>
