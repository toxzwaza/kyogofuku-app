<template>
    <Head title="前撮り枠をまとめて作る" />

    <AdminLayout :breadcrumb="[{ label: '前撮り' }, { label: '前撮り一覧', href: route('admin.photo-slots.index') }, { label: '枠をまとめて作る' }]">
        <UiPageHeader
            title="前撮り枠をまとめて作る"
            description="スタジオと期間を選んでプレビューし、日ごとに直してから登録します。時刻はスタジオの営業時間から30分ごとに作ります。もうある枠は作りません。"
        >
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.photo-slots.index')">前撮り一覧に戻る</UiButton>
            </template>
        </UiPageHeader>

        <div class="space-y-6">
            <!-- 1. 条件 -->
            <UiCard padding="md">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <UiFormField label="スタジオ" required :error="errors.photo_studio_id">
                            <UiSelect v-model="cond.photo_studio_id" :options="studioOptions" placeholder="選んでください" />
                        </UiFormField>
                        <div v-if="studio" class="rounded-md bg-brand-surface-2 px-3 py-2 text-sm">
                            <template v-if="studio.has_hours">
                                <p class="text-xs text-brand-text-muted mb-0.5">営業時間（スタジオのマスタ）</p>
                                <p v-for="g in studio.summary" :key="g.days">
                                    <span class="font-medium">{{ g.days }}</span>：{{ shortTime(g.start) }}〜{{ shortTime(g.last) }} の {{ g.count }}枠
                                </p>
                                <p class="text-xs text-brand-text-muted mt-0.5">定員 {{ studio.default_capacity }}人</p>
                            </template>
                            <p v-else class="text-brand-danger">
                                営業時間が未登録です。
                                <Link :href="route('admin.photo-studios.edit', studio.id)" class="underline">スタジオのマスタ</Link>で登録してください。
                            </p>
                        </div>
                        <p v-if="!studios.length" class="text-sm text-brand-danger">
                            選べるスタジオがありません。<Link :href="route('admin.photo-studios.index')" class="underline">スタジオのマスタ</Link>を確認してください。
                        </p>
                    </div>
                    <div class="space-y-4">
                        <UiFormField label="期間" required :hint="`最大${maxDays}日`" :error="errors.start_date || errors.end_date">
                            <div class="flex flex-wrap items-center gap-2">
                                <UiInput v-model="cond.start_date" type="date" size="sm" class="w-40" />
                                <span class="text-brand-text-muted">〜</span>
                                <UiInput v-model="cond.end_date" type="date" size="sm" class="w-40" />
                                <UiButton size="sm" variant="ghost" @click="setMonth(0)">今月</UiButton>
                                <UiButton size="sm" variant="ghost" @click="setMonth(1)">来月</UiButton>
                            </div>
                        </UiFormField>
                        <UiFormField label="除外する曜日">
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="wd in WEEKDAY_ORDER" :key="wd" type="button"
                                    :class="chipClass(cond.exclude_weekdays.includes(wd), 'exclude')"
                                    @click="toggle(cond.exclude_weekdays, wd)">{{ WEEKDAY_NAMES[wd] }}</button>
                            </div>
                        </UiFormField>
                        <UiFormField label="除外する日" :error="errors.exclude_dates">
                            <div class="flex items-center gap-2">
                                <UiInput v-model="excludeInput" type="date" size="sm" class="w-40" />
                                <UiButton size="sm" variant="subtle" :disabled="!excludeInput" @click="addExcludeDate">追加</UiButton>
                            </div>
                            <div v-if="cond.exclude_dates.length" class="mt-2 flex flex-wrap gap-1.5">
                                <button v-for="d in cond.exclude_dates" :key="d" type="button"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-brand-surface-2 border border-brand-border text-xs"
                                    @click="toggle(cond.exclude_dates, d)">{{ formatDay(d) }} <X :size="12" /></button>
                            </div>
                        </UiFormField>
                    </div>
                </div>
                <div class="mt-6 flex items-center justify-end gap-3">
                    <span v-if="stale && preview" class="text-xs text-amber-700">条件が変わりました。もう一度プレビューしてください。</span>
                    <UiButton variant="primary" :loading="previewing" :disabled="!canPreview" @click="runPreview">
                        <Eye :size="16" class="mr-1" />プレビュー
                    </UiButton>
                </div>
            </UiCard>

            <!-- 2. プレビューと直し -->
            <UiCard v-if="preview" padding="md">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-sm font-semibold">作る枠（{{ studioName }}）</h3>
                        <p class="text-xs text-brand-text-muted">
                            チェックを外した日は作りません。時刻をクリックすると定員を変えたり外したりできます。灰色の時刻はもうある枠です。
                        </p>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <UiButton size="sm" variant="ghost" @click="setAll(true)">全部の日を選ぶ</UiButton>
                        <UiButton size="sm" variant="ghost" @click="setAll(false)">全部外す</UiButton>
                    </div>
                </div>

                <!-- 全部の日にかける -->
                <div class="mb-4 flex flex-wrap items-center gap-2 rounded-md border border-brand-border bg-brand-surface-2 px-3 py-2 text-sm">
                    <span class="text-xs font-medium text-brand-text-muted">選んだ日すべてに：</span>
                    <div class="w-28"><UiSelect v-model="bulkOp.time" :options="allTimes" size="sm" /></div>
                    <span>を</span>
                    <UiButton size="sm" variant="subtle" @click="applyAll('remove')">外す</UiButton>
                    <UiButton size="sm" variant="subtle" @click="applyAll('add')">足す</UiButton>
                    <span class="mx-1 text-brand-text-subtle">／</span>
                    <span>定員を</span>
                    <div class="w-20"><UiSelect v-model="bulkOp.capacity" :options="capacityOptions" size="sm" /></div>
                    <UiButton size="sm" variant="subtle" @click="applyAll('capacity')">にする</UiButton>
                    <span class="text-xs text-brand-text-subtle">（定員は、時刻を選ばずに全部の時刻へ）</span>
                </div>

                <div class="divide-y divide-brand-border rounded-md border border-brand-border">
                    <div v-for="row in rows" :key="row.date" class="flex flex-wrap items-start gap-3 px-3 py-2"
                        :class="selectable(row) ? (row.checked ? '' : 'bg-brand-surface-2/60') : 'bg-brand-surface-2 text-brand-text-subtle'">
                        <label class="flex items-center gap-2 w-32 shrink-0 pt-1">
                            <input v-if="selectable(row)" v-model="row.checked" type="checkbox"
                                class="rounded border-brand-border text-brand-primary focus:ring-brand-primary" />
                            <span v-else class="inline-block w-4" />
                            <span class="tabular-nums font-medium" :class="weekdayClass(row.weekday)">{{ formatDay(row.date) }}</span>
                        </label>
                        <div class="w-28 shrink-0 pt-1 text-xs text-brand-text-muted">
                            <template v-if="row.hours">
                                {{ shortTime(row.hours.start) }}〜{{ shortTime(row.hours.end) }}
                                <span v-if="row.hours.source === 'weekday'" class="block">（曜日の営業時間）</span>
                            </template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p v-if="!selectable(row)" class="pt-1 text-xs">{{ statusLabel(row) }}</p>
                            <div v-else class="flex flex-wrap items-center gap-1.5" :class="!row.checked && 'opacity-50'">
                                <template v-for="t in rowTimes(row)" :key="t.time">
                                    <span v-if="t.existing" class="inline-flex items-center px-2 py-0.5 rounded border border-transparent bg-brand-surface-2 text-xs text-brand-text-subtle"
                                        :title="`作成済み（${t.seats}席）`">{{ shortTime(t.time) }}</span>
                                    <div v-else class="relative">
                                        <button type="button"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded border text-xs bg-ai-50 border-brand-primary text-brand-primary"
                                            :class="t.outside && 'border-dashed'"
                                            :title="t.outside ? '営業時間の外' : ''"
                                            @click="openChip(row, t.time)">
                                            {{ shortTime(t.time) }}<span v-if="t.capacity !== 1" class="opacity-70">×{{ t.capacity }}</span>
                                        </button>
                                        <div v-if="editing.date === row.date && editing.time === t.time"
                                            class="absolute z-20 mt-1 left-0 w-56 rounded-md border border-brand-border bg-brand-surface p-2 shadow-lg">
                                            <p class="text-xs text-brand-text-muted mb-1">{{ shortTime(t.time) }} の定員</p>
                                            <div class="grid grid-cols-5 gap-1 mb-2">
                                                <button v-for="n in maxCapacity" :key="n" type="button"
                                                    class="rounded border px-1 py-0.5 text-xs"
                                                    :class="n === t.capacity ? 'bg-brand-primary text-white border-brand-primary' : 'border-brand-border'"
                                                    @click="setCapacity(row, t.time, n)">{{ n }}</button>
                                            </div>
                                            <div class="flex justify-between">
                                                <button type="button" class="text-xs text-brand-danger" @click="removeTime(row, t.time)">この時刻を外す</button>
                                                <button type="button" class="text-xs text-brand-text-muted" @click="closeChip">閉じる</button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <select class="rounded-md border-brand-border text-xs py-0.5 pl-1.5 pr-6"
                                    :value="''" @change="addTime(row, $event.target.value); $event.target.value = ''">
                                    <option value="">＋時刻</option>
                                    <option v-for="tm in addableTimes(row)" :key="tm" :value="tm">{{ shortTime(tm) }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="w-20 shrink-0 pt-1 text-right text-xs tabular-nums text-brand-text-muted">
                            <template v-if="selectable(row) && row.checked">{{ row.slots.length }}枠・{{ seatsOf(row) }}席</template>
                        </div>
                    </div>
                </div>

                <UiAlert v-if="errors.days" variant="danger" class="mt-4">{{ errors.days }}</UiAlert>
                <UiAlert v-if="totalSeats > maxSeats" variant="danger" class="mt-4">
                    一度に作れるのは{{ maxSeats }}席までです（今 {{ totalSeats }}席）。期間を分けてください。
                </UiAlert>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                    <span class="text-sm">{{ selectedRows.length }}日分・{{ totalTimes }}枠・<span class="font-semibold">{{ totalSeats }}席</span>を作ります</span>
                    <UiButton variant="primary" :loading="saving"
                        :disabled="stale || !selectedRows.length || totalSeats > maxSeats" @click="submit">
                        <Plus :size="16" class="mr-1" />登録する
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
import { UiPageHeader, UiCard, UiButton, UiFormField, UiInput, UiSelect, UiAlert } from '@/Components/UI';
import { Eye, Plus, X } from 'lucide-vue-next';
import { WEEKDAY_NAMES, WEEKDAY_ORDER, timeOptions, timesBetween, shortTime, toMinutes } from '@/utils/photoSlotTime';

const props = defineProps({
    studios: { type: Array, default: () => [] },
    maxDays: { type: Number, default: 93 },
    maxSeats: { type: Number, default: 3000 },
    maxCapacity: { type: Number, default: 10 },
    initialStudioId: { type: Number, default: null },
});

// ---- 条件 ----
const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const monthRange = (offset) => {
    const now = new Date();
    return [ymd(new Date(now.getFullYear(), now.getMonth() + offset, 1)), ymd(new Date(now.getFullYear(), now.getMonth() + offset + 1, 0))];
};
const [nextStart, nextEnd] = monthRange(1);
const firstStudio = props.studios.find((s) => s.id === props.initialStudioId) || (props.studios.length === 1 ? props.studios[0] : null);
const cond = reactive({
    photo_studio_id: firstStudio ? firstStudio.id : '',
    start_date: nextStart,
    end_date: nextEnd,
    exclude_weekdays: [],
    exclude_dates: [],
});
const excludeInput = ref('');
const setMonth = (offset) => {
    const [s, e] = monthRange(offset);
    cond.start_date = s;
    cond.end_date = e;
};
const toggle = (list, value) => {
    const i = list.indexOf(value);
    if (i >= 0) list.splice(i, 1);
    else list.push(value);
};
const addExcludeDate = () => {
    if (excludeInput.value && !cond.exclude_dates.includes(excludeInput.value)) {
        cond.exclude_dates.push(excludeInput.value);
        cond.exclude_dates.sort();
    }
    excludeInput.value = '';
};

const studioOptions = computed(() => props.studios.map((s) => ({ value: s.id, label: s.has_hours ? s.name : `${s.name}（営業時間が未登録）` })));
const studio = computed(() => props.studios.find((s) => String(s.id) === String(cond.photo_studio_id)) || null);
const studioName = ref('');
const canPreview = computed(() => !!studio.value && studio.value.has_hours && cond.start_date && cond.end_date);

// ---- プレビュー ----
const preview = ref(null);
const rows = ref([]);
const defaultCapacity = ref(1);
const stale = ref(false);
const previewing = ref(false);
const saving = ref(false);
const errors = ref({});
watch(cond, () => { stale.value = true; }, { deep: true });

const flatErrors = (errs) => {
    if (!errs) return null;
    const out = {};
    Object.entries(errs).forEach(([k, v]) => { out[k.split('.')[0]] ??= Array.isArray(v) ? v[0] : v; });
    return out;
};

const runPreview = async () => {
    previewing.value = true;
    errors.value = {};
    closeChip();
    try {
        const { data } = await axios.post(route('admin.photo-slots.bulk.preview'), cond);
        defaultCapacity.value = data.default_capacity || 1;
        studioName.value = studio.value?.name || '';
        rows.value = data.days.map((d) => {
            const hourTimes = d.hours ? timesBetween(d.hours.start, d.hours.end) : [];
            return {
                ...d,
                hourTimes,
                checked: d.status === 'create',
                slots: d.times.map((time) => ({ time, capacity: defaultCapacity.value })),
            };
        });
        preview.value = data;
        stale.value = false;
    } catch (e) {
        errors.value = flatErrors(e.response?.data?.errors) || { photo_studio_id: e.response?.data?.message || 'プレビューに失敗しました。' };
    } finally {
        previewing.value = false;
    }
};

// 作れる日：営業時間のある「作る」日と、作成済みだけの日（時刻を足せば作れる）
const selectable = (row) => row.status === 'create' || row.status === 'existing';
const statusLabel = (row) => ({
    past: '過去の日',
    excluded: '除外',
    no_hours: 'この曜日は営業時間がありません',
}[row.status] || '');
const sortTimes = (list) => list.sort((a, b) => toMinutes(a.time) - toMinutes(b.time));

const rowTimes = (row) => {
    const list = [
        ...row.existing_times.map((time) => ({ time, existing: true, seats: row.existing_seats[time] })),
        ...row.slots.map((s) => ({ ...s, outside: !row.hourTimes.includes(s.time) })),
    ];
    return sortTimes(list);
};
const allTimes = timeOptions();
const capacityOptions = computed(() => Array.from({ length: props.maxCapacity }, (_, i) => ({ value: i + 1, label: `${i + 1}人` })));
const addableTimes = (row) => allTimes.filter((t) => !row.existing_times.includes(t) && !row.slots.some((s) => s.time === t));

const addTime = (row, time) => {
    if (!time || row.existing_times.includes(time) || row.slots.some((s) => s.time === time)) return;
    row.slots.push({ time, capacity: defaultCapacity.value });
    sortTimes(row.slots);
    row.checked = true;
};
const removeTime = (row, time) => {
    row.slots = row.slots.filter((s) => s.time !== time);
    if (!row.slots.length) row.checked = false;
    closeChip();
};
const setCapacity = (row, time, n) => {
    const s = row.slots.find((x) => x.time === time);
    if (s) s.capacity = n;
    closeChip();
};
const editing = reactive({ date: null, time: null });
const openChip = (row, time) => {
    if (editing.date === row.date && editing.time === time) return closeChip();
    editing.date = row.date;
    editing.time = time;
};
const closeChip = () => { editing.date = null; editing.time = null; };

const setAll = (value) => rows.value.forEach((r) => {
    if (selectable(r)) r.checked = value && r.slots.length > 0;
});

const bulkOp = reactive({ time: '12:00', capacity: 1 });
const applyAll = (op) => {
    rows.value.filter((r) => selectable(r) && r.checked).forEach((r) => {
        if (op === 'remove') {
            r.slots = r.slots.filter((s) => s.time !== bulkOp.time);
            if (!r.slots.length) r.checked = false;
        } else if (op === 'add') {
            addTime(r, bulkOp.time);
        } else if (op === 'capacity') {
            r.slots.forEach((s) => { s.capacity = Number(bulkOp.capacity); });
        }
    });
};

const selectedRows = computed(() => rows.value.filter((r) => selectable(r) && r.checked && r.slots.length));
const seatsOf = (row) => row.slots.reduce((n, s) => n + Number(s.capacity), 0);
const totalTimes = computed(() => selectedRows.value.reduce((n, r) => n + r.slots.length, 0));
const totalSeats = computed(() => selectedRows.value.reduce((n, r) => n + seatsOf(r), 0));

// ---- 表示 ----
const formatDay = (d) => {
    const dt = new Date(`${d}T00:00:00`);
    return `${dt.getMonth() + 1}/${dt.getDate()}（${WEEKDAY_NAMES[dt.getDay()]}）`;
};
const weekdayClass = (wd) => (wd === 0 ? 'text-red-600' : wd === 6 ? 'text-blue-600' : '');
const chipClass = (on, kind = 'include') => [
    'inline-flex items-center gap-1 px-3 py-1 rounded-lg border text-sm cursor-pointer select-none transition-colors',
    on
        ? (kind === 'exclude' ? 'bg-akane-50 border-brand-danger text-brand-danger' : 'bg-ai-50 border-brand-primary text-brand-primary')
        : 'bg-brand-surface border-brand-border text-brand-text-muted',
];

// ---- 保存 ----
const submit = () => {
    if (!confirm(`${studioName.value} に ${selectedRows.value.length}日分・${totalTimes.value}枠（${totalSeats.value}席）を登録します。よろしいですか？`)) return;
    saving.value = true;
    router.post(route('admin.photo-slots.bulk.store'), {
        ...cond,
        days: selectedRows.value.map((r) => ({ date: r.date, slots: r.slots.map((s) => ({ time: s.time, capacity: s.capacity })) })),
    }, {
        onError: (errs) => { errors.value = flatErrors(errs); },
        onFinish: () => { saving.value = false; },
    });
};
</script>
