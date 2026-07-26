<template>
    <Head title="勤務属性マスタ" />

    <AdminLayout :breadcrumb="[{ label: '勤怠' }, { label: '勤務属性' }]">
        <UiPageHeader
            title="勤務属性マスタ"
            description="勤怠集計に用いる勤務属性（正社員・パート等）を管理します。"
        >
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.attendance.index')">
                    勤怠一覧へ
                </UiButton>
                <UiButton variant="primary" :href="route('admin.work-attributes.create')">
                    <template #leading><Plus :size="14" /></template>
                    新規追加
                </UiButton>
            </template>
        </UiPageHeader>

        <UiDataTable
            :columns="columns"
            :rows="workAttributes || []"
            empty-message="勤務属性が登録されていません。"
        >
            <template #cell-sort_order="{ value }">
                <span class="text-brand-text-muted tabular-nums">{{ value }}</span>
            </template>
            <template #cell-name="{ value }">
                <span class="font-medium">{{ value }}</span>
            </template>
            <template #cell-overtime_mode="{ row }">
                <span v-if="row.overtime_mode === 'threshold'" class="inline-flex items-center rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-xs">
                    閾値方式<span v-if="row.overtime_threshold_minutes" class="ml-1 tabular-nums">（{{ minutesLabel(row.overtime_threshold_minutes) }}）</span>
                </span>
                <span v-else class="inline-flex items-center rounded-full bg-brand-surface-2 text-brand-text-muted px-2 py-0.5 text-xs">
                    パターン方式
                </span>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <UiButton size="sm" variant="ghost" :href="route('admin.work-attributes.edit', row.id)">
                        <Pencil :size="13" />
                    </UiButton>
                    <UiButton size="sm" variant="ghost" class="text-brand-danger" @click="askDelete(row)">
                        <Trash2 :size="13" />
                    </UiButton>
                </div>
            </template>
        </UiDataTable>

        <UiDialog v-model:open="confirmOpen" title="勤務属性を削除">
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
    workAttributes: Array,
});

const columns = [
    { key: 'sort_order',    label: '並び',     width: '80px' },
    { key: 'name',          label: '名称' },
    { key: 'overtime_mode', label: '残業方式', width: '200px' },
    { key: 'actions',       label: '',         align: 'right', width: '100px', noLink: true },
];

const minutesLabel = (m) => {
    if (!m || m <= 0) return '';
    const h = Math.floor(m / 60);
    const min = m % 60;
    return min === 0 ? `${h}時間` : `${h}時間${min}分`;
};

const confirmOpen = ref(false);
const target = ref(null);
const deleting = ref(false);

const askDelete = (w) => {
    target.value = w;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!target.value) return;
    deleting.value = true;
    router.delete(route('admin.work-attributes.destroy', target.value.id), {
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
            target.value = null;
        },
    });
};
</script>
