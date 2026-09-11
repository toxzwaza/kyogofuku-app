<template>
    <Head title="LINE広告" />

    <AdminLayout :breadcrumb="[{ label: '顧客' }, { label: 'LINE広告' }]">
        <UiPageHeader
            title="LINE広告"
            description="LINE連携済みのお客様へキャンペーン告知（テキスト・バナー画像）を一斉配信できます。"
        >
            <template #actions>
                <UiButton variant="primary" size="sm" :href="route('admin.line-broadcasts.create')">
                    <template #leading><Plus :size="14" /></template>
                    新規作成
                </UiButton>
            </template>
        </UiPageHeader>

        <UiDataTable
            :columns="columns"
            :rows="broadcasts.data"
            :row-key="(r) => r.id"
            :row-href="(r) => route('admin.line-broadcasts.show', r.id)"
            empty-message="LINE広告はまだありません。「新規作成」から作成してください。"
        >
            <template #cell-image="{ row }">
                <img
                    v-if="row.image_url"
                    :src="row.image_url"
                    class="h-10 w-10 rounded-md object-cover border border-brand-border"
                    alt=""
                />
                <span v-else class="text-xs text-brand-text-subtle">—</span>
            </template>
            <template #cell-title="{ row }">
                <div class="font-medium">{{ row.title }}</div>
                <div v-if="row.text" class="text-xs text-brand-text-muted truncate max-w-[280px]">{{ row.text }}</div>
            </template>
            <template #cell-status="{ row }">
                <UiBadge :variant="statusVariant(row)" size="sm">{{ statusLabel(row) }}</UiBadge>
            </template>
            <template #cell-sent_count="{ row }">
                <span class="tabular-nums">
                    {{ row.sent_count }}
                    <span v-if="row.failed_count > 0" class="text-brand-danger text-xs">（失敗 {{ row.failed_count }}）</span>
                </span>
            </template>
            <template #cell-last_sent_at="{ row }">
                <span class="text-xs text-brand-text-muted">{{ formatDate(row.last_sent_at) || '—' }}</span>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <UiButton size="sm" variant="primary" @click.stop="goRecipients(row)">
                        <template #leading><Send :size="12" /></template>
                        送る
                    </UiButton>
                    <UiButton size="sm" variant="ghost" @click.stop="goEdit(row)">編集</UiButton>
                    <UiButton size="sm" variant="ghost" @click.stop="duplicate(row)">複製</UiButton>
                    <UiButton
                        v-if="row.sent_count === 0"
                        size="sm"
                        variant="ghost"
                        class="text-brand-danger"
                        @click.stop="askDelete(row)"
                    >
                        削除
                    </UiButton>
                </div>
            </template>
        </UiDataTable>

        <!-- ページネーション -->
        <div v-if="broadcasts.meta?.last_page > 1" class="mt-4 flex flex-wrap gap-1 justify-center">
            <Link
                v-for="(link, idx) in broadcasts.links"
                :key="idx"
                :href="link.url || '#'"
                v-html="link.label"
                class="px-3 py-1 text-sm rounded-soft border"
                :class="link.active
                    ? 'bg-brand-primary text-brand-on-primary border-brand-primary'
                    : (link.url ? 'bg-brand-surface text-brand-text hover:bg-brand-surface-2 border-brand-border' : 'text-brand-text-subtle border-brand-border cursor-not-allowed')"
                preserve-scroll
            />
        </div>

        <UiDialog v-model:open="confirmOpen" title="LINE広告を削除">
            <p class="text-sm text-brand-text-muted">
                <span class="font-medium text-brand-text">{{ target?.title }}</span>
                を削除します。よろしいですか？
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
import { Head, Link, router } from '@inertiajs/vue3';
import { UiPageHeader, UiButton, UiBadge, UiDataTable, UiDialog } from '@/Components/UI';
import { Plus, Send } from 'lucide-vue-next';

defineProps({
    broadcasts: { type: Object, required: true },
});

const columns = [
    { key: 'image',        label: '画像',     width: '60px' },
    { key: 'title',        label: 'タイトル' },
    { key: 'status',       label: '状態',     width: '100px' },
    { key: 'sent_count',   label: '送信数',   width: '110px', hideOnMobile: true },
    { key: 'last_sent_at', label: '最終送信', width: '140px', hideOnMobile: true },
    { key: 'actions',      label: '',         align: 'right', width: '250px', noLink: true },
];

function statusLabel(row) {
    if (row.status === 'draft') return '下書き';
    if (row.status === 'sending') return '送信中';
    return row.failed_count > 0 ? '一部失敗' : '送信済';
}

function statusVariant(row) {
    if (row.status === 'draft') return 'neutral';
    if (row.status === 'sending') return 'warning';
    return row.failed_count > 0 ? 'danger' : 'success';
}

function goRecipients(row) {
    router.visit(route('admin.line-broadcasts.recipients', row.id));
}

function goEdit(row) {
    router.visit(route('admin.line-broadcasts.edit', row.id));
}

function duplicate(row) {
    router.post(route('admin.line-broadcasts.duplicate', row.id));
}

const confirmOpen = ref(false);
const target = ref(null);
const deleting = ref(false);

function askDelete(row) {
    target.value = row;
    confirmOpen.value = true;
}

function confirmDelete() {
    if (!target.value) return;
    deleting.value = true;
    router.delete(route('admin.line-broadcasts.destroy', target.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
            target.value = null;
        },
    });
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    return `${y}/${m}/${day} ${hh}:${mm}`;
}
</script>
