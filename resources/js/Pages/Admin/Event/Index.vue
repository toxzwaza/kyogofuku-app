<template>
    <Head title="イベント一覧" />

    <AdminLayout :breadcrumb="[{ label: 'イベント・予約' }, { label: 'イベント一覧' }]">
        <UiPageHeader
            title="イベント一覧"
            description="予約・問い合わせ・資料請求などイベントページを一元管理します。"
        >
            <template #actions>
                <UiButton variant="primary" :href="route('admin.events.create')">
                    <template #leading><Plus :size="14" /></template>
                    新規追加
                </UiButton>
            </template>
        </UiPageHeader>

        <UiCard variant="default" padding="md" class="mb-4">
            <form @submit.prevent="search" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <UiFormField label="フォーム種別">
                    <UiSelect
                        v-model="searchForm.form_type"
                        :options="[
                            { value: '',                   label: 'すべて' },
                            { value: 'reservation',        label: '振袖予約' },
                            { value: 'reservation_hakama', label: '袴予約（岡山）' },
                            { value: 'document',           label: '資料請求' },
                            { value: 'contact',            label: '問い合わせ' },
                        ]"
                        size="sm"
                    />
                </UiFormField>
                <UiFormField label="担当店舗">
                    <UiSelect
                        v-model="searchForm.shop_id"
                        :options="[{ value: '', label: 'すべての店舗' }, ...shops.map(s => ({ value: s.id, label: s.name }))]"
                        size="sm"
                    />
                </UiFormField>
                <UiFormField label="公開状態">
                    <UiSelect
                        v-model="searchForm.public_status"
                        :options="[
                            { value: 'active',  label: '公開中' },
                            { value: 'ended',   label: '受付終了' },
                            { value: 'private', label: '非公開' },
                            { value: 'all',     label: 'すべて' },
                        ]"
                        size="sm"
                    />
                </UiFormField>
                <div class="flex items-end gap-2">
                    <UiButton variant="primary" size="sm" type="submit">
                        <template #leading><Search :size="13" /></template>
                        検索
                    </UiButton>
                    <UiButton variant="ghost" size="sm" type="button" @click="resetFilters">リセット</UiButton>
                </div>
            </form>
        </UiCard>

        <UiDataTable
            :columns="columns"
            :rows="events.data"
            :pagination="events"
            :row-href="(r) => route('admin.events.show', r.id)"
            empty-message="該当するイベントが見つかりませんでした。"
        >
            <template #cell-id="{ value }">
                <span class="text-brand-text-muted tabular-nums">#{{ value }}</span>
            </template>
            <template #cell-title="{ value }">
                <span class="font-medium">{{ value }}</span>
            </template>
            <template #cell-form_type="{ value }">
                <UiBadge :variant="formTypeVariant(value)" size="sm">{{ getFormTypeLabel(value) }}</UiBadge>
            </template>
            <template #cell-start_at="{ value }">
                <span class="text-xs text-brand-text-muted">{{ formatDate(value) }}</span>
            </template>
            <template #cell-end_at="{ value }">
                <span class="text-xs text-brand-text-muted">{{ formatDate(value) }}</span>
            </template>
            <template #cell-shops="{ row }">
                <div class="flex flex-wrap gap-1">
                    <UiBadge v-for="s in row.shops" :key="s.id" variant="neutral" size="sm">{{ s.name }}</UiBadge>
                </div>
            </template>
            <template #cell-status="{ row }">
                <UiBadge :variant="getPublicStatusVariant(row)" dot>{{ getPublicStatusLabel(row) }}</UiBadge>
            </template>
            <template #cell-actions="{ row }">
                <UiButton size="sm" variant="ghost" :href="route('admin.events.show', row.id)">
                    <Eye :size="13" />
                </UiButton>
            </template>
        </UiDataTable>
    </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    UiPageHeader, UiButton, UiBadge, UiCard, UiDataTable,
    UiFormField, UiSelect,
} from '@/Components/UI';
import { Plus, Search, Eye } from 'lucide-vue-next';
import { formatDateJa, formatDateInputValueJa } from '@/utils/dateFormat';

const props = defineProps({
    events:  Object,
    shops:   Array,
    filters: Object,
});

const columns = [
    { key: 'id',        label: 'ID',           width: '70px' },
    { key: 'title',     label: 'タイトル' },
    { key: 'form_type', label: 'フォーム種別', width: '140px', hideOnMobile: true },
    { key: 'start_at',  label: '受付開始',     width: '120px', hideOnMobile: true },
    { key: 'end_at',    label: '受付終了',     width: '120px', hideOnMobile: true },
    { key: 'shops',     label: '店舗',         hideOnMobile: true },
    { key: 'status',    label: '公開状態',     width: '120px' },
    { key: 'actions',   label: '',             align: 'right', width: '60px', noLink: true },
];

const searchForm = reactive({
    form_type:     props.filters?.form_type || '',
    shop_id:       props.filters?.shop_id || '',
    public_status: props.filters?.public_status || 'active',
});

const search = () => {
    router.get(route('admin.events.index'), {
        form_type: searchForm.form_type || undefined,
        shop_id:   searchForm.shop_id || undefined,
        public_status: searchForm.public_status !== 'all' ? searchForm.public_status : undefined,
    }, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
    searchForm.form_type = '';
    searchForm.shop_id = '';
    searchForm.public_status = 'active';
    router.get(route('admin.events.index'), { public_status: 'active' }, { preserveState: true, preserveScroll: true });
};

const getFormTypeLabel = (t) => ({
    reservation: '振袖予約',
    reservation_hakama: '袴予約（岡山）',
    document: '資料請求',
    contact: '問い合わせ',
}[t] || t);

const formTypeVariant = (t) => ({
    reservation: 'primary',
    reservation_hakama: 'accent',
    document: 'success',
    contact: 'warning',
}[t] || 'neutral');

const formatDate = (d) => d ? formatDateJa(d) : '常時受付中';

const isEnded = (event) => {
    if (!event.end_at) return false;
    const today = formatDateInputValueJa(new Date().toISOString());
    const end = formatDateInputValueJa(event.end_at);
    return end && today && end < today;
};

const getPublicStatusLabel = (event) => {
    if (!event.is_public) return '非公開';
    return isEnded(event) ? '受付終了' : '公開中';
};

const getPublicStatusVariant = (event) => {
    if (!event.is_public) return 'neutral';
    return isEnded(event) ? 'warning' : 'success';
};
</script>
