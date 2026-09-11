<template>
    <Head :title="`LINE広告: ${broadcast.title}`" />

    <AdminLayout :breadcrumb="[
        { label: '顧客' },
        { label: 'LINE広告', href: route('admin.line-broadcasts.index') },
        { label: broadcast.title },
    ]">
        <UiPageHeader :title="broadcast.title" description="配信内容と配信履歴を確認できます。">
            <template #actions>
                <UiButton variant="primary" size="sm" :href="route('admin.line-broadcasts.recipients', broadcast.id)">
                    <template #leading><Send :size="14" /></template>
                    送る（追加送信）
                </UiButton>
                <UiButton variant="ghost" size="sm" :href="route('admin.line-broadcasts.edit', broadcast.id)">編集</UiButton>
            </template>
        </UiPageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <!-- 左：配信内容 -->
            <UiCard variant="default" padding="md">
                <h3 class="text-sm font-semibold text-brand-text mb-3">配信内容</h3>
                <div class="rounded-xl p-4" style="background-color: #8cabd8;">
                    <div class="flex items-start gap-2">
                        <div class="h-8 w-8 rounded-full bg-white/80 flex items-center justify-center text-xs font-bold text-[#8cabd8] shrink-0">呉</div>
                        <div class="space-y-2 max-w-[80%]">
                            <div v-if="broadcast.image_url" class="rounded-2xl overflow-hidden bg-white shadow">
                                <img :src="broadcast.image_url" class="w-full object-contain" alt="バナー画像" />
                            </div>
                            <div v-if="broadcast.text" class="rounded-2xl bg-white px-3 py-2 shadow text-sm text-gray-800 whitespace-pre-wrap break-words">{{ broadcast.text }}</div>
                        </div>
                    </div>
                </div>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-brand-text-muted">状態</dt>
                        <dd>
                            <UiBadge :variant="statusVariant" size="sm">{{ statusLabel }}</UiBadge>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-brand-text-muted">送信成功</dt>
                        <dd class="tabular-nums font-medium">{{ summary.sent }} 件</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-brand-text-muted">送信失敗</dt>
                        <dd class="tabular-nums" :class="summary.failed > 0 ? 'text-brand-danger font-medium' : ''">{{ summary.failed }} 件</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-brand-text-muted">最終送信</dt>
                        <dd class="text-brand-text-muted">{{ formatDate(broadcast.last_sent_at) || '—' }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-brand-text-muted">
                    同じ方に同じ広告が二重に送信されることはありません（送信済みの方は追加送信時に自動でスキップされます）。
                </p>
            </UiCard>

            <!-- 右：配信履歴 -->
            <div class="lg:col-span-2">
                <UiCard variant="default" padding="md">
                    <h3 class="text-sm font-semibold text-brand-text mb-3">
                        配信履歴
                        <span class="ml-1 text-xs font-normal text-brand-text-muted">全 {{ recipients.meta?.total ?? 0 }} 件</span>
                    </h3>
                    <UiDataTable
                        :columns="columns"
                        :rows="recipients.data"
                        :row-key="(r) => r.id"
                        empty-message="まだ配信されていません。「送る」から送信先を選択してください。"
                    >
                        <template #cell-name="{ row }">
                            <span class="font-medium">{{ row.name }}</span>
                        </template>
                        <template #cell-kind="{ row }">
                            <UiBadge :variant="row.kind === 'customer' ? 'primary' : row.kind === 'reservation' ? 'success' : 'neutral'" size="sm">
                                {{ row.kind === 'customer' ? '顧客' : row.kind === 'reservation' ? '予約' : '未紐付' }}
                            </UiBadge>
                        </template>
                        <template #cell-status="{ row }">
                            <UiBadge :variant="row.status === 'sent' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="sm">
                                {{ row.status === 'sent' ? '送信済' : row.status === 'failed' ? '失敗' : '処理中' }}
                            </UiBadge>
                            <p v-if="row.error_message" class="mt-0.5 text-[11px] text-brand-danger leading-snug">{{ row.error_message }}</p>
                        </template>
                        <template #cell-sent_at="{ row }">
                            <span class="text-xs text-brand-text-muted">{{ formatDate(row.sent_at) || '—' }}</span>
                        </template>
                    </UiDataTable>

                    <div v-if="recipients.meta?.last_page > 1" class="mt-4 flex flex-wrap gap-1 justify-center">
                        <Link
                            v-for="(link, idx) in recipients.links"
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
                </UiCard>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { UiPageHeader, UiButton, UiBadge, UiCard, UiDataTable } from '@/Components/UI';
import { Send } from 'lucide-vue-next';

const props = defineProps({
    broadcast:  { type: Object, required: true },
    recipients: { type: Object, required: true },
    summary:    { type: Object, required: true },
});

const columns = [
    { key: 'name',    label: '宛先' },
    { key: 'kind',    label: '種別',   width: '90px' },
    { key: 'status',  label: '結果',   width: '160px' },
    { key: 'sent_at', label: '送信日時', width: '150px' },
];

const statusLabel = computed(() => {
    if (props.broadcast.status === 'draft') return '下書き';
    if (props.broadcast.status === 'sending') return '送信中';
    return props.summary.failed > 0 ? '一部失敗' : '送信済';
});

const statusVariant = computed(() => {
    if (props.broadcast.status === 'draft') return 'neutral';
    if (props.broadcast.status === 'sending') return 'warning';
    return props.summary.failed > 0 ? 'danger' : 'success';
});

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
