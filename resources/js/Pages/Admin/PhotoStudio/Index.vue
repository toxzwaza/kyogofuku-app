<template>
    <Head title="スタジオ一覧" />

    <AdminLayout :breadcrumb="[{ label: '前撮り' }, { label: 'スタジオ' }]">
        <UiPageHeader
            title="スタジオ一覧"
            description="前撮りのスタジオと、枠を作るときに使う営業時間・定員の管理。営業時間は各店舗で登録してください。"
        >
            <template #actions>
                <UiButton variant="primary" :href="route('admin.photo-studios.create')">
                    <template #leading><Plus :size="14" /></template>
                    新規追加
                </UiButton>
            </template>
        </UiPageHeader>

        <UiDataTable
            :columns="columns"
            :rows="photoStudios.data"
            :pagination="photoStudios"
            empty-message="スタジオが登録されていません。"
        >
            <template #cell-id="{ value }">
                <span class="text-brand-text-muted tabular-nums">#{{ value }}</span>
            </template>
            <template #cell-name="{ value }">
                <span class="font-medium">{{ value }}</span>
            </template>
            <template #cell-shop_group="{ value }">
                <span class="text-xs">{{ groupLabels[value] || '未設定' }}</span>
            </template>
            <template #cell-hours="{ row }">
                <ul v-if="row.summary.length" class="text-xs space-y-0.5">
                    <li v-for="g in row.summary" :key="g.days">
                        <span class="font-medium">{{ g.days }}</span>
                        {{ shortTime(g.start) }}〜{{ shortTime(g.end) }}
                        <span class="text-brand-text-muted">（{{ g.count }}枠）</span>
                    </li>
                </ul>
                <UiBadge v-else variant="warning">営業時間が未登録</UiBadge>
            </template>
            <template #cell-default_capacity="{ value }">
                <span class="text-xs tabular-nums">{{ value }}人</span>
            </template>
            <template #cell-is_active="{ value }">
                <UiBadge :variant="value ? 'success' : 'neutral'">{{ value ? '有効' : '無効' }}</UiBadge>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <UiButton size="sm" variant="ghost" :href="route('admin.photo-studios.edit', row.id)">
                        <Pencil :size="13" />
                    </UiButton>
                    <UiButton size="sm" variant="ghost" class="text-brand-danger" @click="askDelete(row)">
                        <Trash2 :size="13" />
                    </UiButton>
                </div>
            </template>
        </UiDataTable>

        <UiDialog v-model:open="confirmOpen" title="スタジオを削除">
            <p class="text-sm text-brand-text-muted">
                <span class="font-medium text-brand-text">{{ target?.name }}</span> を削除します。元に戻せません。
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
import { shortTime } from '@/utils/photoSlotTime';
import { Plus, Pencil, Trash2 } from 'lucide-vue-next';

defineProps({
    photoStudios: Object,
    groupLabels: { type: Object, default: () => ({}) },
});

const columns = [
    { key: 'id',      label: 'ID',          width: '70px' },
    { key: 'name',    label: 'スタジオ名',  width: '200px' },
    { key: 'shop_group', label: 'グループ', width: '80px' },
    { key: 'hours',   label: '営業時間' },
    { key: 'default_capacity', label: '定員', width: '60px', hideOnMobile: true },
    { key: 'is_active', label: '状態', width: '70px', hideOnMobile: true },
    { key: 'actions', label: '',            align: 'right', width: '100px', noLink: true },
];

const confirmOpen = ref(false);
const target = ref(null);
const deleting = ref(false);

const askDelete = (s) => {
    target.value = s;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!target.value) return;
    deleting.value = true;
    router.delete(route('admin.photo-studios.destroy', target.value.id), {
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
            target.value = null;
        },
    });
};
</script>
