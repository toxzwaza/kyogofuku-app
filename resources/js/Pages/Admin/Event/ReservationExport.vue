<template>
    <Head title="イベント予約者出力" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">イベント予約者出力</h2>
                <button
                    type="button"
                    @click="exportCsv"
                    :disabled="!canExport"
                    :title="canExport ? '' : 'イベントを選択してください'"
                    class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-md text-sm font-medium shadow-sm hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                    </svg>
                    CSV出力
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- 店舗 -->
                <FilterBlock
                    title="店舗で絞り込む"
                    :count-selected="selectedShopIds.length"
                    :count-total="shops.length"
                    @select-all="selectedShopIds = shops.map(s => s.id)"
                    @clear-all="selectedShopIds = []"
                >
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="shop in shops"
                            :key="shop.id"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border cursor-pointer text-sm transition-colors"
                            :class="selectedShopIds.includes(shop.id)
                                ? 'bg-indigo-50 border-indigo-300 text-indigo-800'
                                : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'"
                        >
                            <input
                                type="checkbox"
                                :value="shop.id"
                                v-model="selectedShopIds"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            {{ shop.name }}
                        </label>
                    </div>
                </FilterBlock>

                <!-- イベント -->
                <FilterBlock
                    title="イベントを選択"
                    :count-selected="selectedEventIds.length"
                    :count-total="filteredEvents.length"
                    @select-all="selectedEventIds = filteredEvents.map(e => e.id)"
                    @clear-all="selectedEventIds = []"
                >
                    <div class="mb-2">
                        <input
                            type="text"
                            v-model="eventSearchQuery"
                            placeholder="イベント名で検索..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        />
                    </div>
                    <div v-if="visibleEvents.length === 0" class="text-sm text-gray-500 py-3">
                        該当するイベントがありません
                    </div>
                    <div v-else class="max-h-64 overflow-y-auto border border-gray-100 rounded-md divide-y">
                        <label
                            v-for="event in visibleEvents"
                            :key="event.id"
                            class="flex items-start gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                :value="event.id"
                                v-model="selectedEventIds"
                                class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate">{{ event.title }}</div>
                                <div class="text-xs text-gray-500">
                                    <span class="inline-block px-1.5 py-0.5 rounded bg-gray-100 mr-1">{{ formTypeLabel(event.form_type) }}</span>
                                    <span v-if="event.start_date">{{ event.start_date }}</span>
                                    <span v-if="event.end_date && event.end_date !== event.start_date"> 〜 {{ event.end_date }}</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </FilterBlock>

                <!-- ステータス -->
                <FilterBlock
                    title="ステータス"
                    :count-selected="selectedStatuses.length"
                    :count-total="statuses.length"
                    @select-all="selectedStatuses = [...statuses]"
                    @clear-all="selectedStatuses = []"
                >
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="status in statuses"
                            :key="status"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border cursor-pointer text-sm"
                            :class="selectedStatuses.includes(status)
                                ? statusPillClass(status, true)
                                : statusPillClass(status, false)"
                        >
                            <input
                                type="checkbox"
                                :value="status"
                                v-model="selectedStatuses"
                                class="h-4 w-4 rounded border-gray-300 focus:ring-indigo-500"
                            />
                            {{ status }}
                        </label>
                    </div>
                </FilterBlock>

                <!-- UTM流入元（イベント選択後のみ） -->
                <FilterBlock
                    v-if="utmSources.length > 0"
                    title="流入元（UTM）"
                    :count-selected="selectedUtmSources.length"
                    :count-total="utmSources.length"
                    @select-all="selectedUtmSources = utmSources.map(u => utmKey(u.value))"
                    @clear-all="selectedUtmSources = []"
                >
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="utm in utmSources"
                            :key="utmKey(utm.value)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border cursor-pointer text-sm transition-colors"
                            :class="selectedUtmSources.includes(utmKey(utm.value))
                                ? 'bg-sky-50 border-sky-300 text-sky-800'
                                : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'"
                        >
                            <input
                                type="checkbox"
                                :value="utmKey(utm.value)"
                                v-model="selectedUtmSources"
                                class="h-4 w-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                            />
                            <span>{{ utm.label }}</span>
                            <span class="text-xs text-gray-500">({{ utm.count }})</span>
                        </label>
                    </div>
                </FilterBlock>

                <!-- 出力カラム -->
                <FilterBlock
                    title="出力カラム（表示 & CSVに含める列）"
                    :count-selected="selectedColumns.length"
                    :count-total="columnList.length"
                    @select-all="selectedColumns = columnList.map(c => c.key)"
                    @clear-all="selectedColumns = []"
                >
                    <template #extra>
                        <button
                            type="button"
                            @click="selectedColumns = [...defaultColumns]"
                            class="text-xs px-2 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
                        >
                            主要のみ
                        </button>
                    </template>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-1.5">
                        <label
                            v-for="col in columnList"
                            :key="col.key"
                            class="inline-flex items-center gap-1.5 px-2 py-1 rounded text-sm cursor-pointer hover:bg-gray-50"
                        >
                            <input
                                type="checkbox"
                                :value="col.key"
                                v-model="selectedColumns"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="truncate">{{ col.label }}</span>
                        </label>
                    </div>
                </FilterBlock>

                <!-- サマリ -->
                <div v-if="summary" class="bg-white rounded-lg shadow-sm p-4 space-y-3">
                    <div class="flex items-baseline gap-2">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">対象</span>
                        <span class="text-2xl font-bold text-gray-900 tabular-nums">{{ summary.total }}</span>
                        <span class="text-sm text-gray-500">件</span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 mb-1">ステータス別</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="(count, i) in summary.status_counts"
                                :key="statuses[i]"
                                type="button"
                                @click="toggleStatusQuick(statuses[i])"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border transition-colors"
                                :class="statusPillClass(statuses[i], selectedStatuses.length === 1 && selectedStatuses[0] === statuses[i])"
                            >
                                <span>{{ statuses[i] }}</span>
                                <span class="tabular-nums font-bold">{{ count }}</span>
                            </button>
                        </div>
                    </div>
                    <div v-if="summary.venues.length > 0">
                        <p class="text-xs font-semibold text-gray-500 mb-1">会場別</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="v in summary.venues.slice(0, 5)"
                                :key="v.venue_id || 'none'"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-800 border border-rose-200"
                            >
                                <span>{{ v.name }}</span>
                                <span class="tabular-nums font-bold">{{ v.count }}</span>
                            </span>
                            <span
                                v-if="summary.venues.length > 5"
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600"
                            >
                                他 {{ summary.venues.length - 5 }} 会場
                            </span>
                        </div>
                    </div>
                </div>

                <!-- テーブル -->
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="p-4 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                v-model="searchQuery"
                                placeholder="氏名・フリガナ・電話・メール・紹介者名で検索..."
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-80"
                            />
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <label class="text-gray-600">1ページあたり</label>
                            <select
                                v-model.number="perPage"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            >
                                <option :value="50">50件</option>
                                <option :value="100">100件</option>
                                <option :value="200">200件</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="selectedEventIds.length === 0" class="p-12 text-center text-gray-500">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-sm">上記からイベントを選択してください</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        v-for="col in visibleColumns"
                                        :key="col.key"
                                        class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap"
                                    >
                                        {{ col.label }}
                                    </th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr v-if="!reservations || reservations.data.length === 0">
                                    <td :colspan="visibleColumns.length + 1" class="px-3 py-8 text-center text-gray-500">
                                        条件に一致する予約はありません
                                    </td>
                                </tr>
                                <tr
                                    v-for="row in reservations?.data || []"
                                    :key="row.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td
                                        v-for="col in visibleColumns"
                                        :key="col.key"
                                        class="px-3 py-2 whitespace-nowrap text-gray-800"
                                    >
                                        {{ formatCell(row, col.key) }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">
                                        <a
                                            :href="route('admin.reservations.show', row.id)"
                                            target="_blank"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 underline"
                                        >詳細</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ページネーション -->
                    <div v-if="reservations && reservations.last_page > 1" class="p-3 flex items-center justify-between border-t border-gray-100">
                        <p class="text-xs text-gray-500">
                            {{ reservations.from }} - {{ reservations.to }} / {{ reservations.total }} 件
                        </p>
                        <div class="flex gap-1">
                            <button
                                v-for="link in reservations.links"
                                :key="link.label"
                                :disabled="!link.url || link.active"
                                @click="gotoPage(link.url)"
                                v-html="link.label"
                                class="px-3 py-1 text-sm rounded border"
                                :class="link.active
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : link.url
                                        ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                        : 'bg-gray-50 text-gray-400 border-gray-200 cursor-not-allowed'"
                            ></button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FilterBlock from './ReservationExport/FilterBlock.vue';

const props = defineProps({
    shops: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    columnOptions: { type: Object, default: () => ({}) },
    defaultColumns: { type: Array, default: () => [] },
    utmSources: { type: Array, default: () => [] },
    summary: { type: Object, default: null },
    reservations: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
});

const FORM_TYPE_LABELS = {
    reservation: '振袖予約',
    reservation_hakama: '袴予約',
    document: '資料請求',
    contact: '問い合わせ',
};
const formTypeLabel = (t) => FORM_TYPE_LABELS[t] || t;

const UTM_NULL_KEY = '__null__';
const utmKey = (v) => (v === null || v === '' ? UTM_NULL_KEY : v);

const columnList = computed(() =>
    Object.entries(props.columnOptions).map(([key, label]) => ({ key, label }))
);

const initialShopIds = (props.filters?.shop_ids?.length ? props.filters.shop_ids : props.shops.map(s => s.id));
const initialEventIds = (props.filters?.event_ids || []).map(Number);
const initialStatuses = (props.filters?.statuses?.length
    ? props.filters.statuses
    : props.statuses.filter(s => s !== 'キャンセル済み'));
const initialUtmSources = (() => {
    if (!props.utmSources || props.utmSources.length === 0) return [];
    if (props.filters?.utm_sources !== undefined && props.filters.utm_sources !== null) {
        return props.filters.utm_sources.map(v => (v === null ? UTM_NULL_KEY : v));
    }
    return props.utmSources.map(u => utmKey(u.value));
})();

const selectedShopIds = ref([...initialShopIds]);
const selectedEventIds = ref([...initialEventIds]);
const selectedStatuses = ref([...initialStatuses]);
const selectedUtmSources = ref([...initialUtmSources]);
const selectedColumns = ref(props.defaultColumns.length > 0 ? [...props.defaultColumns] : []);
const searchQuery = ref(props.filters?.search || '');
const eventSearchQuery = ref('');
const perPage = ref(props.filters?.per_page || 50);

const statuses = computed(() => props.statuses);
const utmSources = computed(() => props.utmSources);

const filteredEvents = computed(() =>
    props.events.filter(e => {
        if (selectedShopIds.value.length === 0) return false;
        return (e.shop_ids || []).some(id => selectedShopIds.value.includes(id))
            || (e.shop_ids || []).length === 0;
    })
);

const visibleEvents = computed(() => {
    const q = eventSearchQuery.value.trim().toLowerCase();
    if (!q) return filteredEvents.value;
    return filteredEvents.value.filter(e => (e.title || '').toLowerCase().includes(q));
});

const visibleColumns = computed(() =>
    columnList.value.filter(c => selectedColumns.value.includes(c.key))
);

const canExport = computed(() =>
    selectedEventIds.value.length > 0 && selectedColumns.value.length > 0
);

// 選択イベントがフィルタ外になったら選択解除
watch(selectedShopIds, () => {
    const allowed = new Set(filteredEvents.value.map(e => e.id));
    selectedEventIds.value = selectedEventIds.value.filter(id => allowed.has(id));
}, { deep: true });

// 絞り込み変更時にサーバへ再リクエスト（デバウンス）
let reloadTimer = null;
const scheduleReload = () => {
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(reload, 350);
};

const reload = (page = null) => {
    const params = {
        shop_ids: selectedShopIds.value,
        event_ids: selectedEventIds.value,
        statuses: selectedStatuses.value,
        utm_sources: utmSources.value.length > 0
            ? selectedUtmSources.value.map(v => (v === UTM_NULL_KEY ? null : v))
            : undefined,
        search: searchQuery.value || undefined,
        per_page: perPage.value,
    };
    if (page) params.page = page;

    router.get(route('admin.events.reservations-export.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['reservations', 'summary', 'utmSources', 'filters'],
    });
};

watch([selectedEventIds, selectedStatuses, selectedUtmSources, searchQuery, perPage], scheduleReload, { deep: true });

const gotoPage = (url) => {
    if (!url) return;
    const u = new URL(url, window.location.origin);
    const page = u.searchParams.get('page');
    reload(page);
};

const toggleStatusQuick = (status) => {
    if (selectedStatuses.value.length === 1 && selectedStatuses.value[0] === status) {
        selectedStatuses.value = [...statuses.value];
    } else {
        selectedStatuses.value = [status];
    }
};

const statusPillClass = (status, active) => {
    const activeColors = {
        '未対応': 'bg-orange-100 border-orange-300 text-orange-800',
        '確認中': 'bg-blue-100 border-blue-300 text-blue-800',
        '返信待ち': 'bg-yellow-100 border-yellow-300 text-yellow-800',
        '対応完了済み': 'bg-green-100 border-green-300 text-green-800',
        'キャンセル済み': 'bg-gray-200 border-gray-300 text-gray-700',
    };
    if (active) return activeColors[status] || 'bg-indigo-100 border-indigo-300 text-indigo-800';
    return 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100';
};

const formatCell = (row, key) => {
    const v = row[key];
    switch (key) {
        case 'event_title': return row.event?.title || '';
        case 'venue_name': return row.venue?.name || '';
        case 'reservation_datetime':
        case 'created_at':
            return v ? formatDateTime(v) : '';
        case 'birth_date':
        case 'graduation_ceremony_date':
            return v ? formatDate(v) : '';
        case 'has_visited_before':
        case 'koichi_furisode_used':
        case 'companion_hakama_usage':
        case 'privacy_agreed':
        case 'cancel_flg':
            if (v === null || v === undefined) return '';
            return v ? 'はい' : 'いいえ';
        case 'companion_types':
        case 'visit_reasons':
        case 'considering_plans':
            if (!Array.isArray(v)) return '';
            return v.join(', ');
        default:
            return v ?? '';
    }
};

const formatDateTime = (v) => {
    const d = new Date(v);
    if (isNaN(d.getTime())) return v;
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    return `${y}-${m}-${day} ${hh}:${mm}`;
};
const formatDate = (v) => {
    const d = new Date(v);
    if (isNaN(d.getTime())) return v;
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const exportCsv = () => {
    if (!canExport.value) return;
    const total = props.summary?.total ?? 0;
    if (total > 5000) {
        if (!confirm(`${total} 件のCSVを出力しようとしています。実行しますか？`)) return;
    }

    // Build a form and submit to the POST endpoint (so browser triggers download)
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('admin.events.reservations-export.csv');
    form.style.display = 'none';

    const token = usePage().props.csrf_token
        || document.querySelector('meta[name="csrf-token"]')?.content;
    const append = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    };

    append('_token', token || '');
    selectedEventIds.value.forEach(id => append('event_ids[]', id));
    selectedStatuses.value.forEach(s => append('statuses[]', s));
    if (utmSources.value.length > 0) {
        selectedUtmSources.value.forEach(v => append('utm_sources[]', v === UTM_NULL_KEY ? '' : v));
    }
    selectedShopIds.value.forEach(id => append('shop_ids[]', id));
    if (searchQuery.value) append('search', searchQuery.value);
    selectedColumns.value.forEach(c => append('columns[]', c));

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
};
</script>
