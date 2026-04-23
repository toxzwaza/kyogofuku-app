<template>
    <Head title="スタジオ一覧" />

    <AdminLayout :breadcrumb="[{ label: '前撮り' }, { label: 'スタジオ' }]">
        <UiPageHeader
            title="スタジオ一覧"
            description="前撮り撮影スタジオの管理。住所・備考の編集ができます。"
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
            <template #cell-address="{ value }">
                <span class="text-brand-text-muted text-xs">{{ value || '—' }}</span>
            </template>
            <template #cell-remarks="{ value }">
                <span class="text-brand-text-muted text-xs line-clamp-1">{{ value || '—' }}</span>
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
import { UiPageHeader, UiButton, UiDataTable, UiDialog } from '@/Components/UI';
import { Plus, Pencil, Trash2 } from 'lucide-vue-next';

defineProps({
    photoStudios: Object,
});

const columns = [
    { key: 'id',      label: 'ID',          width: '70px' },
    { key: 'name',    label: 'スタジオ名',  width: '220px' },
    { key: 'address', label: '住所',        hideOnMobile: true },
    { key: 'remarks', label: '備考',        hideOnMobile: true },
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
