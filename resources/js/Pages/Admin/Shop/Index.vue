<template>
    <Head title="店舗一覧" />

    <AdminLayout :breadcrumb="[{ label: 'マスタ' }, { label: '店舗' }]">
        <UiPageHeader
            title="店舗一覧"
            description="運営店舗の管理。有効/無効の切替や連絡先の編集ができます。"
        >
            <template #actions>
                <UiButton variant="primary" :href="route('admin.shops.create')">
                    <template #leading><Plus :size="14" /></template>
                    新規追加
                </UiButton>
            </template>
        </UiPageHeader>

        <UiDataTable
            :columns="columns"
            :rows="shops.data"
            :pagination="shops"
            empty-message="店舗が登録されていません。"
        >
            <template #cell-id="{ value }">
                <span class="text-brand-text-muted tabular-nums">#{{ value }}</span>
            </template>
            <template #cell-name="{ value }">
                <span class="font-medium">{{ value }}</span>
            </template>
            <template #cell-address="{ value }">
                <span class="text-brand-text-muted">{{ value || '—' }}</span>
            </template>
            <template #cell-phone="{ value }">
                <span class="tabular-nums text-brand-text-muted">{{ value || '—' }}</span>
            </template>
            <template #cell-is_active="{ value }">
                <UiBadge :variant="value ? 'success' : 'neutral'" dot>{{ value ? '有効' : '無効' }}</UiBadge>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <UiButton size="sm" variant="ghost" :href="route('admin.shops.edit', row.id)">
                        <Pencil :size="13" />
                    </UiButton>
                    <UiButton size="sm" variant="ghost" class="text-brand-danger" @click="askDelete(row)">
                        <Trash2 :size="13" />
                    </UiButton>
                </div>
            </template>
        </UiDataTable>

        <UiDialog v-model:open="confirmOpen" title="店舗を削除">
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
import { UiPageHeader, UiButton, UiBadge, UiDataTable, UiDialog } from '@/Components/UI';
import { Plus, Pencil, Trash2 } from 'lucide-vue-next';

defineProps({
    shops: Object,
});

const columns = [
    { key: 'id',        label: 'ID',    width: '80px' },
    { key: 'name',      label: '店舗名' },
    { key: 'address',   label: '住所',  hideOnMobile: true },
    { key: 'phone',     label: '電話',  hideOnMobile: true, width: '160px' },
    { key: 'is_active', label: '状態',  width: '100px' },
    { key: 'actions',   label: '',      align: 'right', width: '100px', noLink: true },
];

const confirmOpen = ref(false);
const target = ref(null);
const deleting = ref(false);

const askDelete = (shop) => {
    target.value = shop;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!target.value) return;
    deleting.value = true;
    router.delete(route('admin.shops.destroy', target.value.id), {
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
            target.value = null;
        },
    });
};
</script>
