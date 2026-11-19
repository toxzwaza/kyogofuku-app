<template>
    <Head title="成人式お支度枠" />

    <AdminLayout :breadcrumb="[{ label: '成人式' }, { label: 'お支度枠' }]">
        <UiPageHeader title="成人式お支度枠" description="お支度場所×日×時刻の枠（定員＝レーン数）。定員を超えては入れられません。時刻が決まる前のお客様は「場所だけ」で登録し、下の一覧に出ます。">
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.seijin-prep-venues.index')">お支度場所</UiButton>
                <UiButton variant="ghost" :to="route('admin.seijin-prep-slots.csv')">
                    <template #leading><Download :size="14" /></template>
                    CSV（全部）
                </UiButton>
                <UiButton variant="primary" :disabled="!activeVenues.length" @click="openBulk()">
                    <template #leading><Plus :size="14" /></template>
                    1日分の枠を作る
                </UiButton>
            </template>
        </UiPageHeader>

        <div v-if="notice" class="mb-4 flex items-start justify-between gap-3 rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
            <span>{{ notice }}</span>
            <button type="button" class="text-green-700 hover:text-green-900" aria-label="閉じる" @click="notice = ''"><X :size="16" /></button>
        </div>

        <!-- お支度場所ごとの集計 -->
        <section class="mb-6">
            <div v-if="!venues.length" class="rounded-md border border-dashed border-brand-border p-6 text-center text-sm text-brand-text-muted">
                お支度場所が登録されていません。
                <Link :href="route('admin.seijin-prep-venues.create')" class="text-brand-primary hover:underline">お支度場所を追加</Link>
                してから枠を作ってください。
            </div>
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="v in venues" :key="v.id" class="rounded-md border border-brand-border bg-brand-surface p-3" :class="{ 'opacity-60': !v.is_active }">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-semibold text-sm">{{ v.name }}</div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-brand-text-muted">
                                <UiBadge :variant="v.kind === 'own' ? 'primary' : 'neutral'">{{ kindLabels[v.kind] || v.kind }}</UiBadge>
                                <span>{{ groupLabels[v.shop_group] || 'グループ未設定' }}</span>
                                <span>{{ v.slot_interval_minutes }}分刻み</span>
                                <span v-if="!v.is_active">（無効）</span>
                            </div>
                        </div>
                        <a :href="route('admin.seijin-prep-slots.csv', { venue_id: v.id })" class="text-xs text-brand-primary hover:underline shrink-0">CSV</a>
                    </div>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded bg-brand-surface-2 py-1.5">
                            <dt class="text-[11px] text-brand-text-muted">枠の定員</dt>
                            <dd class="text-base font-semibold tabular-nums">{{ v.capacity }}</dd>
                        </div>
                        <div class="rounded bg-brand-surface-2 py-1.5">
                            <dt class="text-[11px] text-brand-text-muted">枠に入った人</dt>
                            <dd class="text-base font-semibold tabular-nums">{{ v.booked }}</dd>
                        </div>
                        <div class="rounded py-1.5" :class="v.unscheduled ? 'bg-amber-50' : 'bg-brand-surface-2'">
                            <dt class="text-[11px] text-brand-text-muted">時刻未定</dt>
                            <dd class="text-base font-semibold tabular-nums" :class="{ 'text-amber-700': v.unscheduled }">{{ v.unscheduled }}</dd>
                        </div>
                    </dl>
                    <div v-if="v.is_active" class="mt-2 text-right">
                        <button type="button" class="text-xs text-brand-primary hover:underline" @click="openBulk(v)">この場所に1日分の枠を作る</button>
                    </div>
                </div>
            </div>
            <p class="mt-2 text-xs text-brand-text-muted">
                場所のないお客様：他店お支度 {{ counts.other_store }}人・場所未定 {{ counts.undecided }}人
                <a v-if="counts.other_store" :href="route('admin.seijin-prep-slots.csv', { venue_id: 'other' })" class="ml-2 text-brand-primary hover:underline">他店お支度のCSV</a>
            </p>
        </section>

        <!-- 月の表（前撮りと同じ部品） -->
        <MonthGrid
            v-if="venues.length"
            ref="gridRef"
            :studios="venues"
            grid-route="admin.seijin-prep-slots.grid"
            storage-key="seijinPrepGrid.venueIds"
            place-label="お支度場所"
            seat-label="レーン"
            @book="openBooking"
            @manage="openManage"
        />

        <!-- 時刻がまだ決まっていないお客様 -->
        <section class="mt-8">
            <h2 class="text-sm font-semibold text-brand-text mb-2">時刻がまだ決まっていないお客様（{{ unscheduled.length }}人）</h2>
            <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                <span class="text-brand-text-muted">絞り込み：</span>
                <button v-for="f in filterOptions" :key="f.value" type="button"
                    class="rounded-full border px-2.5 py-0.5"
                    :class="filter === f.value ? 'border-brand-primary bg-brand-primary text-white' : 'border-brand-border hover:bg-brand-surface-2'"
                    @click="filter = f.value">{{ f.label }}</button>
            </div>
            <UiDataTable :columns="columns" :rows="filteredUnscheduled" empty-message="時刻未定のお客様はいません。">
                <template #cell-customer="{ row }">
                    <Link v-if="row.customer" :href="route('admin.customers.show', row.customer.id)" class="font-medium text-brand-primary hover:underline">{{ row.customer.name }}</Link>
                    <div v-if="row.customer?.phone_number" class="text-xs text-brand-text-muted">{{ row.customer.phone_number }}</div>
                </template>
                <template #cell-place="{ row }">
                    <span v-if="row.venue">{{ row.venue.name }}</span>
                    <UiBadge v-else-if="row.is_other_store" variant="warning">他店お支度</UiBadge>
                    <UiBadge v-else variant="neutral">未定</UiBadge>
                </template>
                <template #cell-preferred_time="{ value }"><span class="text-xs">{{ value || '' }}</span></template>
                <template #cell-remarks="{ value }"><span class="text-xs whitespace-pre-wrap">{{ value || '' }}</span></template>
                <template #cell-shop="{ value }"><span class="text-xs">{{ value || '' }}</span></template>
            </UiDataTable>
            <p class="mt-2 text-xs text-brand-text-muted">枠に入れるときは、上の表の「空き」を押すか、お客様の詳細画面の「成人式お支度」から時刻を選んでください。</p>
        </section>

        <!-- 予約・レーンの数・まとめて作成の小さい画面 -->
        <BookingDialog
            v-model:open="booking.open"
            :venue="booking.venue"
            :date="booking.date"
            :time="booking.time"
            :shops="shops"
            :guides="guides"
            @booked="afterChange"
        />
        <SeatManageDialog
            v-model:open="seatManage.open"
            :studio="seatManage.studio"
            :date="seatManage.date"
            :time="seatManage.time"
            :capacity="seatManage.capacity"
            :booked="seatManage.booked"
            :max-capacity="maxCapacity"
            seats-route="admin.seijin-prep-slots.seats.store"
            capacity-route="admin.seijin-prep-slots.capacity"
            :fields="{ place: 'seijin_prep_venue_id', date: 'prep_date', time: 'prep_time' }"
            seat-label="レーン"
            booked-label="登録"
            @changed="afterChange"
        />

        <UiDialog v-model:open="bulk.open" title="1日分の枠を作る" size="md">
            <div class="space-y-4 text-sm">
                <UiFormField label="お支度場所" required :error="bulk.errors.seijin_prep_venue_id">
                    <UiSelect v-model="bulk.venueId" :options="activeVenues.map(v => ({ value: v.id, label: v.name }))" @update:model-value="applyVenueDefaults" />
                </UiFormField>
                <UiFormField label="日" required :error="bulk.errors.prep_date" hint="成人式の当日（市町村で日が違うときは、日ごとに作ってください）">
                    <UiInput v-model="bulk.date" type="date" :min="today" />
                </UiFormField>
                <div class="grid grid-cols-3 gap-3">
                    <UiFormField label="最初の時刻" required :error="bulk.errors.start_time">
                        <UiSelect v-model="bulk.start" :options="bulkTimes" />
                    </UiFormField>
                    <UiFormField label="最後の時刻" required :error="bulk.errors.end_time">
                        <UiSelect v-model="bulk.end" :options="bulkTimes" />
                    </UiFormField>
                    <UiFormField label="定員（レーン）" required :error="bulk.errors.capacity">
                        <UiSelect v-model="bulk.capacity" :options="capacityOptions" />
                    </UiFormField>
                </div>
                <p v-if="bulkPreview" class="rounded-md bg-brand-surface-2 px-3 py-2">
                    {{ bulkPreview.count }}時刻（{{ bulk.start }}〜{{ bulk.end }}・{{ bulkVenue?.slot_interval_minutes }}分刻み）× {{ bulk.capacity }}レーン ＝ {{ bulkPreview.count * bulk.capacity }}人分。もう枠のある時刻は飛ばします。
                </p>
                <p v-if="bulk.message" class="rounded-soft bg-red-50 border border-red-200 px-3 py-2 text-red-700">{{ bulk.message }}</p>
            </div>
            <template #footer>
                <UiButton variant="ghost" @click="bulk.open = false">キャンセル</UiButton>
                <UiButton :loading="bulk.saving" :disabled="!bulkPreview || !bulk.date" @click="submitBulk">作る</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { UiPageHeader, UiButton, UiBadge, UiDataTable, UiDialog, UiFormField, UiSelect, UiInput } from '@/Components/UI';
import MonthGrid from '@/Components/PhotoSlot/MonthGrid.vue';
import SeatManageDialog from '@/Components/PhotoSlot/SeatManageDialog.vue';
import BookingDialog from '@/Components/SeijinPrep/BookingDialog.vue';
import { Plus, Download, X } from 'lucide-vue-next';

const props = defineProps({
    venues: { type: Array, default: () => [] },
    unscheduled: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({ other_store: 0, undecided: 0 }) },
    shops: { type: Array, default: () => [] },
    guides: { type: Object, default: () => ({}) },
    groupLabels: { type: Object, default: () => ({}) },
    kindLabels: { type: Object, default: () => ({}) },
    maxCapacity: { type: Number, default: 20 },
});

const pad = (n) => String(n).padStart(2, '0');
const now = new Date();
const today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
const toMinutes = (hm) => { const [h, m] = String(hm).split(':').map(Number); return h * 60 + m; };
const hm = (m) => `${pad(Math.floor(m / 60))}:${pad(m % 60)}`;

const activeVenues = computed(() => props.venues.filter(v => v.is_active));
const notice = ref('');
const gridRef = ref(null);
const afterChange = (message) => {
    if (message) notice.value = message;
    gridRef.value?.reload();
    router.reload({ only: ['venues', 'unscheduled', 'counts'], preserveScroll: true });
};

// 月の表から
const booking = ref({ open: false, venue: null, date: '', time: '' });
const seatManage = ref({ open: false, studio: null, date: '', time: '', capacity: 0, booked: 0 });
const openBooking = ({ studio, date, time }) => { booking.value = { open: true, venue: studio, date, time }; };
const openManage = ({ studio, date, time, capacity, booked }) => {
    seatManage.value = { open: true, studio, date, time, capacity, booked };
};

// 時刻未定の一覧
const filter = ref('all');
const filterOptions = computed(() => [
    { value: 'all', label: 'すべて' },
    ...props.venues.filter(v => v.unscheduled).map(v => ({ value: `v${v.id}`, label: `${v.name}（${v.unscheduled}）` })),
    ...(props.counts.other_store ? [{ value: 'other', label: `他店お支度（${props.counts.other_store}）` }] : []),
    ...(props.counts.undecided ? [{ value: 'undecided', label: `未定（${props.counts.undecided}）` }] : []),
]);
const filteredUnscheduled = computed(() => props.unscheduled.filter((r) => {
    if (filter.value === 'all') return true;
    if (filter.value === 'other') return r.is_other_store;
    if (filter.value === 'undecided') return !r.is_other_store && !r.venue;
    return r.venue && `v${r.venue.id}` === filter.value;
}));
const columns = [
    { key: 'customer', label: 'お客様', width: '180px' },
    { key: 'place', label: 'お支度場所', width: '180px' },
    { key: 'preferred_time', label: '希望時刻', width: '110px' },
    { key: 'remarks', label: '備考', hideOnMobile: true },
    { key: 'shop', label: '担当店舗', width: '100px', hideOnMobile: true },
];

// まとめて作成（1日分）
const bulk = reactive({ open: false, venueId: '', date: '', start: '', end: '', capacity: 1, saving: false, errors: {}, message: '' });
const bulkVenue = computed(() => props.venues.find(v => v.id === Number(bulk.venueId)) || null);
const bulkTimes = computed(() => {
    const step = bulkVenue.value?.slot_interval_minutes || 30;
    const list = [];
    for (let m = 4 * 60; m <= 20 * 60; m += step) list.push({ value: hm(m), label: `${Math.floor(m / 60)}:${pad(m % 60)}` });
    return list;
});
const capacityOptions = computed(() => Array.from({ length: props.maxCapacity }, (_, i) => ({ value: i + 1, label: `${i + 1}レーン` })));
const bulkPreview = computed(() => {
    const step = bulkVenue.value?.slot_interval_minutes;
    if (!step || !bulk.start || !bulk.end || toMinutes(bulk.start) > toMinutes(bulk.end)) return null;
    return { count: Math.floor((toMinutes(bulk.end) - toMinutes(bulk.start)) / step) + 1 };
});
const applyVenueDefaults = () => {
    const v = bulkVenue.value;
    if (!v) return;
    bulk.start = v.default_start_time || '07:00';
    bulk.end = v.default_end_time || bulk.start;
    bulk.capacity = v.default_capacity || 1;
};
const openBulk = (venue = null) => {
    Object.assign(bulk, { open: true, errors: {}, message: '', saving: false });
    bulk.venueId = venue?.id ?? (activeVenues.value.length === 1 ? activeVenues.value[0].id : bulk.venueId || '');
    applyVenueDefaults();
};
const submitBulk = async () => {
    bulk.saving = true;
    bulk.errors = {};
    bulk.message = '';
    try {
        const res = await axios.post(route('admin.seijin-prep-slots.bulk'), {
            seijin_prep_venue_id: bulk.venueId,
            prep_date: bulk.date,
            start_time: bulk.start,
            end_time: bulk.end,
            capacity: bulk.capacity,
        });
        bulk.open = false;
        afterChange(res.data?.message || '枠を作りました。');
    } catch (e) {
        const errors = e.response?.data?.errors;
        if (errors) bulk.errors = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v.join(' ')]));
        else bulk.message = e.response?.data?.message || '枠を作れませんでした。';
    } finally {
        bulk.saving = false;
    }
};
</script>
