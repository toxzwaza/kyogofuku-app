<template>
    <Head title="共有ノート" />

    <AdminLayout :breadcrumb="[{ label: '業務マニュアル' }, { label: '共有ノート' }]">
        <UiPageHeader
            title="共有ノート"
            :description="`${groupName}のクレーム・問い合わせ・ミス、ヒヤッとしたこと、気づき・提案を共有するノートです（${tabCounts.all}件）。`"
        >
            <template #actions>
                <div v-if="groups.length" class="w-40">
                    <UiSelect :model-value="group" :options="groups" @update:model-value="changeGroup" />
                </div>
                <UiButton variant="primary" class="whitespace-nowrap" :href="route('admin.incidents.create', groups.length ? { group } : {})">
                    <template #leading><Plus :size="14" /></template>
                    共有する
                </UiButton>
            </template>
        </UiPageHeader>

        <!-- 種類のまとまりのタブ -->
        <div class="flex gap-1 overflow-x-auto border-b border-brand-border mb-4 -mx-1 px-1" role="tablist">
            <button
                v-for="t in TABS"
                :key="t.id"
                type="button"
                role="tab"
                :aria-selected="form.tab === t.id"
                class="shrink-0 inline-flex items-center gap-1.5 px-3.5 min-h-[44px] md:min-h-[38px] text-sm font-medium border-b-2 -mb-px transition-colors"
                :class="form.tab === t.id
                    ? 'border-brand-primary text-brand-primary'
                    : 'border-transparent text-brand-text-muted hover:text-brand-text'"
                @click="setTab(t.id)"
            >
                {{ t.label }}
                <span
                    class="text-[11px] tabular-nums rounded-full px-1.5 py-px"
                    :class="form.tab === t.id ? 'bg-brand-primary text-brand-on-primary' : 'bg-brand-surface-2 text-brand-text-muted'"
                >{{ tabCounts[t.id] ?? 0 }}</span>
            </button>
        </div>

        <!-- ステータス（会議では「議題」で絞り込むと、その日の議題の一覧になる） -->
        <div class="flex flex-wrap gap-1.5 mb-3" role="group" aria-label="ステータスで絞り込む">
            <button
                v-for="s in statusChips"
                :key="s.id"
                type="button"
                :aria-pressed="form.st === s.id"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 min-h-[40px] md:min-h-[34px] text-sm transition-colors"
                :class="form.st === s.id
                    ? 'border-brand-primary bg-brand-primary/10 text-brand-primary font-semibold'
                    : 'border-brand-border bg-brand-surface text-brand-text-muted hover:text-brand-text'"
                @click="setStatus(s.id)"
            >
                <span v-if="s.dot" class="w-2 h-2 rounded-full" :class="s.dot" />
                {{ s.label }}
                <span class="text-[11px] tabular-nums">{{ s.count }}</span>
            </button>
        </div>

        <!-- 絞り込み -->
        <div class="grid grid-cols-2 md:grid-cols-[1fr_140px_140px_170px] lg:grid-cols-[1fr_160px_160px_180px] gap-2 mb-4">
            <div class="col-span-2 md:col-span-1">
                <UiInput v-model="form.q" placeholder="件名・内容で探す（例：重ね衿、駐車場）" @update:model-value="searchLater">
                    <template #leading><Search :size="14" class="text-brand-text-muted" /></template>
                </UiInput>
            </div>
            <UiSelect v-model="form.cat" :options="[{ value: '', label: '区分：すべて' }, ...options.categories]" @update:model-value="apply" />
            <UiSelect v-model="form.shop" :options="[{ value: '', label: '店舗：すべて' }, ...options.shops]" @update:model-value="apply" />
            <div class="col-span-2 md:col-span-1">
                <UiSelect v-model="form.kind" :options="[{ value: '', label: '種類：すべて' }, ...options.kinds]" @update:model-value="apply" />
            </div>
        </div>

        <UiDataTable
            :columns="columns"
            :rows="incidents.data"
            :pagination="incidents"
            :row-href="(r) => route('admin.incidents.show', r.id)"
            empty-message="該当する記録はありません。"
        >
            <template #cell-title="{ row }">
                <div class="min-w-0">
                    <div class="font-medium text-brand-text"><StatusBadge :value="row.status" size="sm" class="mr-1.5 align-[1px]" />{{ row.title }}</div>
                    <div class="text-xs text-brand-text-muted truncate max-w-[520px]">{{ row.excerpt }}</div>
                </div>
            </template>
            <template #cell-category="{ value }"><KindBadge :value="value" type="category" /></template>
            <template #cell-kind="{ value }"><KindBadge :value="value" /></template>
            <template #cell-shop_name="{ value }"><span class="whitespace-nowrap">{{ value }}</span></template>
            <template #cell-occurred_on="{ row }">
                <div class="whitespace-nowrap tabular-nums">{{ dateLabel(row.occurred_on) }}</div>
                <div class="text-xs text-brand-text-muted">{{ row.creator }}</div>
            </template>

            <template #mobile-card="{ row }">
                <div class="flex flex-wrap gap-1.5 mb-1.5">
                    <StatusBadge :value="row.status" />
                    <KindBadge :value="row.category" type="category" />
                    <KindBadge :value="row.kind" />
                </div>
                <div class="font-medium text-brand-text">{{ row.title }}</div>
                <div class="text-xs text-brand-text-muted mt-0.5">
                    {{ dateLabel(row.occurred_on) }}・{{ row.shop_name }}・{{ row.creator }}
                </div>
            </template>
        </UiDataTable>
    </AdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiPageHeader, UiButton, UiDataTable, UiInput, UiSelect } from '@/Components/UI';
import KindBadge from '@/Components/Incident/KindBadge.vue';
import StatusBadge from '@/Components/Incident/StatusBadge.vue';
import { Plus, Search } from 'lucide-vue-next';
import { TABS, STATUS, dateLabel } from '@/composables/useIncidentFlow.js';
import { useDebounceFn } from '@/composables/useDebounceFn.js';

const props = defineProps({
    incidents: Object,
    filters: Object,
    tabCounts: Object,
    statusCounts: { type: Object, default: () => ({}) },
    options: Object,
    group: String,
    groups: { type: Array, default: () => [] },
});

const groupName = computed(() => (props.group === 'fukui' ? '福井グループ' : '岡山グループ'));

const columns = [
    { key: 'title',       label: '件名' },
    { key: 'category',    label: '区分',  width: '110px' },
    { key: 'kind',        label: '種類',  width: '160px' },
    { key: 'shop_name',   label: '店舗',  width: '110px', hideOnMobile: true },
    { key: 'occurred_on', label: '発生日・記録者', width: '130px' },
];

const form = reactive({
    tab: props.filters.tab || 'all',
    q: props.filters.q || '',
    cat: props.filters.cat || '',
    shop: props.filters.shop || '',
    kind: props.filters.kind || '',
    st: props.filters.st || '',
});

const DOT = { open: 'bg-akane-500', agenda: 'bg-natane-500', resolved: 'bg-uguisu-500' };
const statusChips = computed(() => [
    { id: '', label: 'すべて', count: props.statusCounts.all ?? 0 },
    ...Object.entries(STATUS).map(([id, s]) => ({ id, label: s.label, count: props.statusCounts[id] ?? 0, dot: DOT[id] })),
]);

const setStatus = (id) => {
    form.st = id;
    apply();
};

const apply = () => {
    const params = Object.fromEntries(Object.entries(form).filter(([k, v]) => v && !(k === 'tab' && v === 'all')));
    if (props.groups.length) params.group = props.group;
    router.get(route('admin.incidents.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};
const searchLater = useDebounceFn(apply, 350);

const setTab = (id) => {
    form.tab = id;
    form.kind = '';
    apply();
};

const changeGroup = (g) => {
    router.get(route('admin.incidents.index'), { group: g });
};
</script>
