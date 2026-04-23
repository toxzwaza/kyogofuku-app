<template>
    <Head title="制約一覧" />

    <AdminLayout :breadcrumb="[{ label: '顧客' }, { label: '制約テンプレート' }]">
        <UiPageHeader
            title="制約テンプレート一覧"
            description="顧客に対する制約事項のテンプレートを管理します。"
        >
            <template #actions>
                <UiButton variant="primary" :href="route('admin.constraint-templates.create')">
                    <template #leading><Plus :size="14" /></template>
                    制約追加
                </UiButton>
            </template>
        </UiPageHeader>

        <UiDataTable
            :columns="columns"
            :rows="constraintTemplates.data"
            :pagination="constraintTemplates"
            empty-message='制約テンプレートがありません。「制約追加」から登録してください。'
        >
            <template #cell-id="{ value }">
                <span class="text-brand-text-muted tabular-nums">#{{ value }}</span>
            </template>
            <template #cell-name="{ value }">
                <span class="font-medium">{{ value }}</span>
            </template>
            <template #cell-shops="{ row }">
                <div v-if="row.shops?.length" class="flex flex-wrap gap-1">
                    <UiBadge v-for="s in row.shops" :key="s.id" variant="neutral" size="sm">{{ s.name }}</UiBadge>
                </div>
                <span v-else class="text-brand-text-subtle">未設定</span>
            </template>
            <template #cell-is_active="{ value }">
                <UiBadge :variant="value ? 'success' : 'neutral'" dot>{{ value ? '有効' : '無効' }}</UiBadge>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <UiButton size="sm" variant="ghost" :href="route('admin.constraint-templates.edit', row.id)">
                        <Pencil :size="13" />
                    </UiButton>
                    <UiButton
                        v-if="!row.customer_constraints_count"
                        size="sm"
                        variant="ghost"
                        class="text-brand-danger"
                        @click="askDelete(row)"
                    >
                        <Trash2 :size="13" />
                    </UiButton>
                </div>
            </template>
        </UiDataTable>

        <UiDialog v-model:open="confirmOpen" title="制約を削除">
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
    constraintTemplates: Object,
});

const columns = [
    { key: 'id',        label: 'ID',         width: '70px' },
    { key: 'name',      label: '制約名' },
    { key: 'shops',     label: '対象店舗' },
    { key: 'is_active', label: '状態',       width: '100px' },
    { key: 'actions',   label: '',           align: 'right', width: '100px', noLink: true },
];

const confirmOpen = ref(false);
const target = ref(null);
const deleting = ref(false);

const askDelete = (t) => {
    target.value = t;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!target.value) return;
    deleting.value = true;
    router.delete(route('admin.constraint-templates.destroy', target.value.id), {
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
            target.value = null;
        },
    });
};
</script>
