<script setup>
import { ref, watch, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    UiPageHeader, UiButton, UiCard, UiFormField, UiSelect, UiDataTable,
} from '@/Components/UI';
import { ArrowLeft, MessageCircle } from 'lucide-vue-next';

const props = defineProps({
    groups: Array,
    shops: Array,
    filters: Object,
});

const columns = [
    { key: 'shop_name',           label: '店舗',       width: '140px' },
    { key: 'line_user_id_masked', label: 'LINEユーザー', width: '180px' },
    { key: 'message_count',       label: '件数',       width: '80px', align: 'right' },
    { key: 'last_at',             label: '最新',       width: '160px' },
    { key: 'last_text',           label: 'プレビュー' },
    { key: 'actions',             label: '',           align: 'right', width: '130px', noLink: true },
];

function initialShopFilter() {
    if (props.filters?.unassigned) return '__unassigned__';
    if (props.filters?.shop_id != null) return String(props.filters.shop_id);
    return '';
}

const shopFilter = ref(initialShopFilter());

watch(
    () => [props.filters?.shop_id, props.filters?.unassigned],
    () => { shopFilter.value = initialShopFilter(); },
);

const shopOptions = computed(() => [
    { value: '', label: 'すべての店舗' },
    { value: '__unassigned__', label: '店舗未分類' },
    ...(props.shops || []).map((s) => ({ value: String(s.id), label: s.name })),
]);

function applyFilter() {
    const params = {};
    if (shopFilter.value === '__unassigned__') {
        params.unassigned = 1;
    } else if (shopFilter.value !== '') {
        params.shop_id = Number(shopFilter.value);
    }
    router.get(route('admin.line-unknown-inbox.index'), params, { preserveState: true });
}

function showHref(g) {
    const q = { line_user_id: g.line_user_id };
    if (g.shop_id != null) q.shop_id = g.shop_id;
    return route('admin.line-unknown-inbox.show', q);
}

const fmtDT = (v) => v ? new Date(v).toLocaleString('ja-JP') : '—';
</script>

<template>
    <Head title="LINE 不明メッセージ" />

    <AdminLayout :breadcrumb="[{ label: '顧客' }, { label: 'LINE 不明メッセージ' }]">
        <UiPageHeader
            title="LINE 不明メッセージ"
            description="友だち未登録の LINE ユーザーから受信したメッセージを顧客へ紐づけできます。"
        >
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.customers.index')">
                    <template #leading><ArrowLeft :size="14" /></template>
                    顧客一覧
                </UiButton>
            </template>
        </UiPageHeader>

        <UiCard variant="default" padding="md" class="mb-4">
            <UiFormField label="店舗で絞り込み">
                <UiSelect
                    v-model="shopFilter"
                    :options="shopOptions"
                    size="sm"
                    class="max-w-xs"
                    @update:modelValue="applyFilter"
                />
            </UiFormField>
        </UiCard>

        <UiDataTable
            :columns="columns"
            :rows="groups || []"
            :row-key="(r) => `${r.shop_id ?? 'na'}-${r.line_user_id}`"
            empty-message="不明メッセージはありません。"
        >
            <template #cell-shop_name="{ value }">
                <span class="text-brand-text">{{ value || '—' }}</span>
            </template>
            <template #cell-line_user_id_masked="{ value }">
                <span class="font-mono text-xs text-brand-text-muted">{{ value }}</span>
            </template>
            <template #cell-message_count="{ value }">
                <span class="tabular-nums text-brand-text-muted">{{ value }}</span>
            </template>
            <template #cell-last_at="{ value }">
                <span class="text-brand-text-muted text-xs">{{ fmtDT(value) }}</span>
            </template>
            <template #cell-last_text="{ value }">
                <span class="text-brand-text-muted text-xs line-clamp-1">{{ value || '—' }}</span>
            </template>
            <template #cell-actions="{ row }">
                <UiButton size="sm" variant="primary" :href="showHref(row)">
                    <template #leading><MessageCircle :size="12" /></template>
                    詳細・紐づけ
                </UiButton>
            </template>
        </UiDataTable>
    </AdminLayout>
</template>
