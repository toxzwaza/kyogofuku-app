<template>
    <Head title="ログ管理" />

    <AdminLayout :breadcrumb="[{ label: 'システム' }, { label: 'ログ管理' }]">
        <UiPageHeader
            title="ログ管理"
            description="スタッフ操作履歴・ログイン状況・ブロック済みIPの一元管理。"
        />

        <!-- ブロックされているIP -->
        <UiCard v-if="blockedIps && blockedIps.length" variant="outlined" padding="md" class="mb-4 border-brand-danger">
            <template #header>
                <h3 class="font-serif text-sm text-brand-danger flex items-center gap-2">
                    <ShieldAlert :size="14" />
                    ブロックされているIPアドレス ({{ blockedIps.length }})
                </h3>
            </template>
            <UiDataTable
                :columns="blockedColumns"
                :rows="blockedIps"
                :row-key="(r) => r.id"
            >
                <template #cell-ip_address="{ value }">
                    <span class="font-mono">{{ value }}</span>
                </template>
                <template #cell-failure_count="{ value }">
                    <UiBadge variant="danger" size="sm">{{ value }} 回</UiBadge>
                </template>
                <template #cell-blocked_at="{ value }">
                    <span class="text-xs text-brand-text-muted">{{ formatDateTime(value) }}</span>
                </template>
                <template #cell-last_failed_at="{ value }">
                    <span class="text-xs text-brand-text-muted">{{ formatDateTime(value) }}</span>
                </template>
            </UiDataTable>
        </UiCard>

        <!-- フィルタ -->
        <UiCard variant="default" padding="md" class="mb-4">
            <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <UiFormField label="スタッフ">
                    <UiSelect v-model="filters.user_id" size="sm" :options="[{ value: '', label: 'すべて' }, ...(filterOptions?.users || []).map(u => ({ value: u.id, label: u.name }))]" />
                </UiFormField>
                <UiFormField label="店舗">
                    <UiSelect v-model="filters.shop_id" size="sm" :options="[{ value: '', label: 'すべて' }, ...(filterOptions?.shops || []).map(s => ({ value: s.id, label: s.name }))]" />
                </UiFormField>
                <UiFormField label="処理区分">
                    <UiSelect v-model="filters.action_type" size="sm" :options="[{ value: '', label: 'すべて' }, ...(filterOptions?.actionTypes || [])]" />
                </UiFormField>
                <UiFormField label="リソース種別">
                    <UiSelect v-model="filters.resource_type" size="sm" :options="[{ value: '', label: 'すべて' }, ...(filterOptions?.resourceTypes || [])]" />
                </UiFormField>
                <UiFormField label="開始日">
                    <UiInput v-model="filters.date_from" type="date" size="sm" />
                </UiFormField>
                <UiFormField label="終了日">
                    <UiInput v-model="filters.date_to" type="date" size="sm" />
                </UiFormField>
                <div class="md:col-span-3 lg:col-span-6 flex justify-end gap-2">
                    <UiButton variant="ghost" size="sm" type="button" @click="resetFilters">リセット</UiButton>
                    <UiButton variant="primary" size="sm" type="submit">
                        <template #leading><Filter :size="13" /></template>
                        フィルター適用
                    </UiButton>
                </div>
            </form>
        </UiCard>

        <UiDataTable
            :columns="columns"
            :rows="activityLogs?.data || []"
            :pagination="activityLogs"
            empty-message="ログデータがありません"
        >
            <template #cell-created_at="{ value }">
                <span class="text-xs text-brand-text-muted whitespace-nowrap">{{ formatDateTime(value) }}</span>
            </template>
            <template #cell-user="{ row }">
                <span>{{ row.user?.name || '—' }}</span>
            </template>
            <template #cell-shop="{ row }">
                <span class="text-brand-text-muted">{{ row.shop?.name || '—' }}</span>
            </template>
            <template #cell-action_type="{ value }">
                <UiBadge :variant="actionVariant(value)" size="sm">{{ getActionTypeLabel(value) }}</UiBadge>
            </template>
            <template #cell-resource="{ row }">
                {{ getResourceTypeLabel(row.resource_type) }}
                <span v-if="row.resource_id" class="text-brand-text-muted text-xs">#{{ row.resource_id }}</span>
            </template>
            <template #cell-description="{ value }">
                <span class="text-brand-text-muted line-clamp-1">{{ value || '—' }}</span>
            </template>
            <template #cell-ip_address="{ value }">
                <span class="font-mono text-xs text-brand-text-muted">{{ value || '—' }}</span>
            </template>
            <template #cell-url="{ value }">
                <span class="text-xs text-brand-text-muted line-clamp-1 max-w-[240px]">{{ value || '—' }}</span>
            </template>
            <template #cell-actions="{ row }">
                <UiButton size="sm" variant="ghost" :href="route('admin.activity-logs.show', row.id)">
                    <Eye :size="13" />
                </UiButton>
            </template>
        </UiDataTable>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    UiPageHeader, UiButton, UiBadge, UiCard, UiDataTable,
    UiFormField, UiInput, UiSelect,
} from '@/Components/UI';
import { Filter, Eye, ShieldAlert } from 'lucide-vue-next';
import { formatDateTimeJa } from '@/utils/dateFormat';

const props = defineProps({
    activityLogs: Object,
    filterOptions: Object,
    filters: Object,
    blockedIps: { type: Array, default: () => [] },
});

const columns = [
    { key: 'created_at',  label: '日時',       width: '140px' },
    { key: 'user',        label: 'スタッフ',   width: '120px' },
    { key: 'shop',        label: '店舗',       hideOnMobile: true, width: '120px' },
    { key: 'action_type', label: '処理区分',   width: '100px' },
    { key: 'resource',    label: 'リソース',   hideOnMobile: true, width: '160px' },
    { key: 'description', label: '説明',       hideOnMobile: true },
    { key: 'ip_address',  label: 'IP',         hideOnMobile: true, width: '120px' },
    { key: 'url',         label: 'URL',        hideOnMobile: true },
    { key: 'actions',     label: '',           align: 'right', width: '60px', noLink: true },
];

const blockedColumns = [
    { key: 'ip_address',      label: 'IPアドレス', width: '180px' },
    { key: 'failure_count',   label: '失敗回数',   width: '120px' },
    { key: 'blocked_at',      label: 'ブロック日時', width: '180px' },
    { key: 'last_failed_at',  label: '最終失敗日時', width: '180px' },
];

const filters = ref({
    user_id:       props.filters?.user_id || '',
    shop_id:       props.filters?.shop_id || '',
    action_type:   props.filters?.action_type || '',
    resource_type: props.filters?.resource_type || '',
    date_from:     props.filters?.date_from || '',
    date_to:       props.filters?.date_to || '',
});

const applyFilters = () => {
    router.get(route('admin.activity-logs.index'), filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filters.value = { user_id: '', shop_id: '', action_type: '', resource_type: '', date_from: '', date_to: '' };
    router.get(route('admin.activity-logs.index'), {}, { preserveState: true, preserveScroll: true });
};

const formatDateTime = (dt) => formatDateTimeJa(dt);

const getActionTypeLabel = (t) => ({
    view: '閲覧', create: '作成', update: '更新', delete: '削除',
    login: 'ログイン', logout: 'ログアウト', login_failed: 'ログイン失敗',
    export: 'エクスポート', import: 'インポート',
}[t] || t);

const actionVariant = (t) => ({
    view: 'primary', create: 'success', update: 'warning', delete: 'danger',
    login: 'accent', logout: 'neutral', login_failed: 'danger',
    export: 'primary', import: 'primary',
}[t] || 'neutral');

const getResourceTypeLabel = (r) => ({
    Event: 'イベント', EventReservation: '予約', EventImage: 'イベント画像',
    EventTimeslot: '予約枠', Shop: '店舗', User: 'スタッフ', Venue: '会場',
    Customer: '顧客', ReservationNote: '予約メモ', CustomerNote: '顧客メモ',
}[r] || r || '—');
</script>
