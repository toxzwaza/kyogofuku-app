<template>
    <Head title="顧客タグ一覧" />

    <AdminLayout :breadcrumb="[{ label: '顧客' }, { label: '顧客タグ' }]">
        <UiPageHeader
            title="顧客タグ一覧"
            description="顧客を分類するタグの管理。色や有効状態を設定できます。"
        >
            <template #actions>
                <UiButton variant="primary" :href="route('admin.customer-tags.create')">
                    <template #leading><Plus :size="14" /></template>
                    新規追加
                </UiButton>
            </template>
        </UiPageHeader>

        <UiDataTable
            :columns="columns"
            :rows="customerTags.data"
            :pagination="customerTags"
            empty-message="タグが登録されていません。"
        >
            <template #cell-id="{ value }">
                <span class="text-brand-text-muted tabular-nums">#{{ value }}</span>
            </template>
            <template #cell-name="{ row }">
                <div class="flex items-center gap-2">
                    <span
                        v-if="row.color"
                        class="w-3 h-3 rounded-full flex-shrink-0 border border-brand-border"
                        :style="{ background: row.color }"
                    />
                    <span class="font-medium truncate">{{ row.name }}</span>
                </div>
            </template>
            <template #cell-description="{ value }">
                <span class="text-brand-text-muted text-xs">{{ value || '—' }}</span>
            </template>
            <template #cell-is_active="{ value }">
                <UiBadge :variant="value ? 'success' : 'neutral'" dot>{{ value ? '有効' : '無効' }}</UiBadge>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <UiButton size="sm" variant="ghost" :href="route('admin.customer-tags.show', row.id)">
                        <Eye :size="13" />
                    </UiButton>
                    <UiButton size="sm" variant="ghost" :href="route('admin.customer-tags.edit', row.id)">
                        <Pencil :size="13" />
                    </UiButton>
                    <UiButton size="sm" variant="ghost" class="text-brand-danger" @click="askDelete(row)">
                        <Trash2 :size="13" />
                    </UiButton>
                </div>
            </template>
        </UiDataTable>

        <UiDialog v-model:open="confirmOpen" title="タグを削除">
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
import { Plus, Pencil, Trash2, Eye } from 'lucide-vue-next';

defineProps({
    customerTags: Object,
});

const columns = [
    { key: 'id',          label: 'ID',    width: '70px' },
    { key: 'name',        label: 'タグ名' },
    { key: 'description', label: '説明',  hideOnMobile: true },
    { key: 'is_active',   label: '状態',  width: '100px' },
    { key: 'actions',     label: '',      align: 'right', width: '130px', noLink: true },
];

const confirmOpen = ref(false);
const target = ref(null);
const deleting = ref(false);

const askDelete = (tag) => {
    target.value = tag;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!target.value) return;
    deleting.value = true;
    router.delete(route('admin.customer-tags.destroy', target.value.id), {
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
            target.value = null;
        },
    });
};
</script>
