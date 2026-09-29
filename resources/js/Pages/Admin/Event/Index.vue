<template>
    <Head title="イベント一覧" />

    <AdminLayout :breadcrumb="[{ label: 'イベント・予約' }, { label: 'イベント一覧' }]">
        <UiPageHeader
            title="イベント一覧"
            description="予約・問い合わせ・資料請求などイベントページを一元管理します。"
        >
            <template #actions>
                <!-- モバイル/タブレット：検索条件モーダルを開く（PCでは非表示・上部バーを使用） -->
                <UiButton variant="secondary" class="lg:hidden" @click="mobileSearchOpen = true">
                    <template #leading><Search :size="14" /></template>
                    検索
                </UiButton>
                <UiButton variant="primary" :href="route('admin.events.create')">
                    <template #leading><Plus :size="14" /></template>
                    新規追加
                </UiButton>
            </template>
        </UiPageHeader>

        <!-- モバイル：半透明の黒背景（中央モーダルの外に一覧がうっすら見える） -->
        <div
            v-if="mobileSearchOpen"
            class="fixed inset-0 z-40 bg-sumi-950/50 lg:hidden"
            @click="mobileSearchOpen = false"
            aria-hidden="true"
        />

        <UiCard
            variant="default"
            padding="md"
            :class="[
                mobileSearchOpen
                    ? 'fixed left-1/2 top-1/2 z-50 -translate-x-1/2 -translate-y-1/2 w-[92vw] max-w-lg max-h-[85vh] overflow-y-auto shadow-2xl'
                    : 'hidden',
                'mb-4 lg:block lg:static lg:left-auto lg:top-auto lg:translate-x-0 lg:translate-y-0 lg:z-auto lg:w-auto lg:max-w-none lg:max-h-none lg:overflow-visible lg:shadow-sm',
            ]"
        >
            <!-- モバイル用ヘッダー（閉じる） -->
            <div class="flex items-center justify-between mb-3 lg:hidden">
                <h2 class="text-base font-semibold text-brand-text">検索条件</h2>
                <button
                    type="button"
                    class="p-2 -mr-2 rounded hover:bg-brand-surface-2 text-brand-text-muted"
                    aria-label="閉じる"
                    @click="mobileSearchOpen = false"
                >
                    <X :size="22" />
                </button>
            </div>
            <form @submit.prevent="search" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <UiFormField label="フォーム種別">
                    <UiSelect
                        v-model="searchForm.form_type"
                        :options="[
                            { value: '',                   label: 'すべて' },
                            { value: 'reservation',        label: '振袖予約' },
                            { value: 'reservation_hakama', label: '袴予約（岡山）' },
                            { value: 'reservation_hakama_fukui', label: '袴予約（福井）' },
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
            <template #cell-thumbnail="{ row }">
                <img
                    v-if="row.thumbnail_url"
                    :src="row.thumbnail_url"
                    alt=""
                    class="w-12 h-12 rounded-md object-cover border border-brand-border"
                />
                <div
                    v-else
                    class="w-12 h-12 rounded-md bg-brand-surface-2 border border-brand-border flex items-center justify-center text-brand-text-subtle"
                >
                    <ImageIcon :size="16" />
                </div>
            </template>
            <template #cell-id="{ value }">
                <span class="text-brand-text-muted tabular-nums">#{{ value }}</span>
            </template>
            <template #cell-title="{ value }">
                <span class="font-medium">{{ value }}</span>
            </template>
            <template #cell-reservations="{ row }">
                <span class="tabular-nums text-sm whitespace-nowrap">
                    <span class="font-semibold text-brand-text">{{ row.reservations_count ?? 0 }}</span>
                    <span class="text-brand-text-muted">/{{ row.capacity_total ?? 0 }}</span>
                </span>
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
import { reactive, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    UiPageHeader, UiButton, UiBadge, UiCard, UiDataTable,
    UiFormField, UiSelect,
} from '@/Components/UI';
import { Plus, Search, Eye, X, Image as ImageIcon } from 'lucide-vue-next';
import { formatDateJa, formatDateInputValueJa } from '@/utils/dateFormat';
import { useDebounceFn } from '@/composables/useDebounceFn.js';
import { useScrollLock } from '@/composables/useScrollLock.js';

const props = defineProps({
    events:  Object,
    shops:   Array,
    filters: Object,
});

// モバイル/タブレット：検索条件を中央モーダルで開く（PCは上部バーを常時表示）
const mobileSearchOpen = ref(false);
useScrollLock(mobileSearchOpen); // モーダル表示中は背後の一覧をスクロールさせない

const columns = [
    { key: 'thumbnail',    label: '',             width: '64px' },
    { key: 'id',           label: 'ID',           width: '64px', hideOnMobile: true },
    { key: 'title',        label: 'タイトル' },
    { key: 'reservations', label: '予約/枠',      width: '92px' },
    { key: 'form_type',    label: 'フォーム種別', width: '140px', hideOnMobile: true },
    { key: 'start_at',     label: '受付開始',     width: '120px', hideOnMobile: true },
    { key: 'end_at',       label: '受付終了',     width: '120px', hideOnMobile: true },
    { key: 'shops',        label: '店舗',         hideOnMobile: true },
    { key: 'status',       label: '公開状態',     width: '120px' },
    { key: 'actions',      label: '',             align: 'right', width: '60px', noLink: true },
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

// ライブ検索：ボタンを押さなくても条件変更で自動更新（デバウンス）
watch(searchForm, useDebounceFn(search, 400), { deep: true });

const resetFilters = () => {
    searchForm.form_type = '';
    searchForm.shop_id = '';
    searchForm.public_status = 'active';
    router.get(route('admin.events.index'), { public_status: 'active' }, { preserveState: true, preserveScroll: true });
};

const getFormTypeLabel = (t) => ({
    reservation: '振袖予約',
    reservation_hakama: '袴予約（岡山）',
    reservation_hakama_fukui: '袴予約（福井）',
    document: '資料請求',
    contact: '問い合わせ',
}[t] || t);

const formTypeVariant = (t) => ({
    reservation: 'primary',
    reservation_hakama: 'accent',
    reservation_hakama_fukui: 'accent',
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
