<template>
    <Head title="お支度場所" />

    <AdminLayout :breadcrumb="[{ label: '成人式' }, { label: 'お支度場所' }]">
        <UiPageHeader title="お支度場所" description="成人式のお支度をする自社会場・提携美容室。枠の刻みと、まとめて作るときの時刻・レーン数もここで決めます。">
            <template #actions>
                <UiButton variant="primary" :href="route('admin.seijin-prep-venues.create')">
                    <template #leading><Plus :size="14" /></template>
                    新規追加
                </UiButton>
            </template>
        </UiPageHeader>

        <UiDataTable :columns="columns" :rows="venues" empty-message="お支度場所が登録されていません。">
            <template #cell-name="{ row }">
                <div class="font-medium">{{ row.name }}</div>
                <div v-if="row.address || row.phone" class="text-xs text-brand-text-muted">{{ [row.address, row.phone].filter(Boolean).join(' ／ ') }}</div>
            </template>
            <template #cell-kind="{ value }">
                <UiBadge :variant="value === 'own' ? 'primary' : 'neutral'">{{ kindLabels[value] || value }}</UiBadge>
            </template>
            <template #cell-shop_group="{ value }">
                <span class="text-xs">{{ groupLabels[value] || '未設定' }}</span>
            </template>
            <template #cell-frame="{ row }">
                <span v-if="row.numbered" class="text-xs tabular-nums">番号で管理・1日 {{ row.default_capacity }}枠</span>
                <span v-else class="text-xs tabular-nums">
                    {{ row.slot_interval_minutes }}分刻み・{{ row.default_capacity }}レーン
                    <template v-if="row.default_start_time">（{{ row.default_start_time }}〜{{ row.default_end_time }}）</template>
                </span>
            </template>
            <template #cell-counts="{ row }">
                <span class="text-xs tabular-nums">枠 {{ row.booked }}/{{ row.capacity }}・{{ row.numbered ? '日未定' : '時刻未定' }} {{ row.unscheduled }}人</span>
            </template>
            <template #cell-is_active="{ value }">
                <UiBadge :variant="value ? 'success' : 'neutral'">{{ value ? '有効' : '無効' }}</UiBadge>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <UiButton size="sm" variant="ghost" :href="route('admin.seijin-prep-venues.edit', row.id)" aria-label="編集">
                        <Pencil :size="13" />
                    </UiButton>
                    <UiButton size="sm" variant="ghost" class="text-brand-danger" aria-label="削除" @click="askDelete(row)">
                        <Trash2 :size="13" />
                    </UiButton>
                </div>
            </template>
        </UiDataTable>

        <UiDialog v-model:open="confirmOpen" title="お支度場所を削除">
            <p class="text-sm text-brand-text-muted">
                <span class="font-medium text-brand-text">{{ target?.name }}</span> を削除します。元に戻せません。枠か登録がある場所は削除できません（無効にしてください）。
            </p>
            <template #footer>
                <UiButton variant="ghost" @click="confirmOpen = false">キャンセル</UiButton>
                <UiButton variant="danger" :loading="deleting" @click="confirmDelete">削除する</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { UiPageHeader, UiButton, UiDataTable, UiDialog, UiBadge } from '@/Components/UI';
import { Plus, Pencil, Trash2 } from 'lucide-vue-next';

defineProps({
    venues: { type: Array, default: () => [] },
    groupLabels: { type: Object, default: () => ({}) },
    kindLabels: { type: Object, default: () => ({}) },
});

const columns = [
    { key: 'name', label: 'お支度場所' },
    { key: 'kind', label: '種類', width: '110px' },
    { key: 'shop_group', label: 'グループ', width: '80px' },
    { key: 'frame', label: '枠の作り方', hideOnMobile: true },
    { key: 'counts', label: '登録', hideOnMobile: true },
    { key: 'is_active', label: '状態', width: '70px', hideOnMobile: true },
    { key: 'actions', label: '', align: 'right', width: '100px', noLink: true },
];

const confirmOpen = ref(false);
const target = ref(null);
const deleting = ref(false);
const askDelete = (v) => { target.value = v; confirmOpen.value = true; };
const confirmDelete = () => {
    if (!target.value) return;
    deleting.value = true;
    router.delete(route('admin.seijin-prep-venues.destroy', target.value.id), {
        onFinish: () => { deleting.value = false; confirmOpen.value = false; target.value = null; },
    });
};
</script>
