<template>
    <Head title="LINE 連携一覧" />

    <AdminLayout :breadcrumb="[{ label: '顧客' }, { label: 'LINE連携' }]">
        <UiPageHeader
            title="LINE 連携一覧"
            description="LINE ID の紐付け状況（顧客／予約／未紐付）を横断的に確認できます。"
        />

        <!-- 絞り込み -->
        <UiCard variant="default" padding="md" class="mb-4">
            <form @submit.prevent="search" class="grid grid-cols-1 md:grid-cols-[auto_auto_1fr_auto_auto] gap-3">
                <UiFormField label="種別">
                    <UiSelect
                        v-model="form.type"
                        :options="[
                            { value: 'all',         label: `全て（${counts.all}）` },
                            { value: 'customer',    label: `顧客紐付け（${counts.customer}）` },
                            { value: 'reservation', label: `予約紐付け（${counts.reservation}）` },
                            { value: 'unbound',     label: `未紐付け（${counts.unbound}）` },
                        ]"
                        size="sm"
                    />
                </UiFormField>
                <UiFormField label="店舗">
                    <UiSelect
                        v-model="form.shop_id"
                        :options="[{ value: '', label: '全て' }, ...shops.map(s => ({ value: s.id, label: s.name }))]"
                        size="sm"
                    />
                </UiFormField>
                <UiFormField label="キーワード" hint="名前・カナ・電話・LINE userId・ラベルで横断検索">
                    <UiInput v-model="form.q" placeholder="例: 村上 / ムラカミ / 09012345678" size="sm" />
                </UiFormField>
                <div class="flex items-end gap-2">
                    <UiButton variant="primary" size="sm" type="submit">
                        <template #leading><Search :size="13" /></template>
                        検索
                    </UiButton>
                    <UiButton variant="ghost" size="sm" type="button" @click="reset">リセット</UiButton>
                </div>
            </form>
        </UiCard>

        <UiDataTable
            :columns="columns"
            :rows="contacts.data"
            :row-key="(r) => r.id"
            :row-href="(r) => r.target?.detail_url || null"
            empty-message="該当する LINE 連携が見つかりません。"
        >
            <template #cell-kind="{ value }">
                <UiBadge
                    :variant="value === 'customer' ? 'primary' : value === 'reservation' ? 'success' : 'neutral'"
                    size="sm"
                >
                    {{ value === 'customer' ? '顧客' : value === 'reservation' ? '予約' : '未紐付' }}
                </UiBadge>
            </template>
            <template #cell-name="{ row }">
                <span class="font-medium">{{ row.target?.name || '—' }}</span>
            </template>
            <template #cell-kana="{ row }">
                <span class="text-brand-text-muted">{{ row.target?.kana || '—' }}</span>
            </template>
            <template #cell-phone="{ row }">
                <span class="tabular-nums text-brand-text-muted">{{ row.target?.phone || '—' }}</span>
            </template>
            <template #cell-shop="{ row }">
                <span class="text-brand-text-muted">{{ row.shop?.name || '—' }}</span>
            </template>
            <template #cell-extra="{ row }">
                <span v-if="row.kind === 'reservation' && row.target?.event_title" class="text-xs text-brand-text-muted">
                    {{ row.target.event_title }}
                </span>
                <span v-else-if="row.kind === 'unbound'" class="font-mono text-xs text-brand-text-subtle">
                    {{ shortLineId(row.line_user_id) }}
                </span>
                <span v-else></span>
            </template>
            <template #cell-label="{ value }">
                <span class="text-xs text-brand-text-muted">{{ value || '—' }}</span>
            </template>
            <template #cell-created_at="{ value }">
                <span class="text-xs text-brand-text-muted">{{ formatDate(value) }}</span>
            </template>
            <template #cell-actions="{ row }">
                <UiButton size="sm" variant="ghost" class="text-brand-danger" @click.stop="askUnlink(row)">
                    解除
                </UiButton>
            </template>
        </UiDataTable>

        <!-- ページネーション（Laravel paginator API resource 形式） -->
        <div v-if="contacts.meta?.last_page > 1" class="mt-4 flex flex-wrap gap-1 justify-center">
            <Link
                v-for="(link, idx) in contacts.links"
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

        <UiDialog v-model:open="confirmOpen" title="LINE 連携を解除">
            <p class="text-sm text-brand-text-muted">
                <span class="font-medium text-brand-text">{{ target?.target?.name || shortLineId(target?.line_user_id) }}</span>
                の LINE 連携を解除します。
            </p>
            <template #footer>
                <UiButton variant="ghost" @click="confirmOpen = false">キャンセル</UiButton>
                <UiButton variant="danger" :loading="unlinking" @click="confirmUnlink">解除する</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    UiPageHeader, UiButton, UiBadge, UiCard, UiDataTable, UiDialog,
    UiFormField, UiInput, UiSelect,
} from '@/Components/UI';
import { Search } from 'lucide-vue-next';

const props = defineProps({
    contacts: { type: Object, required: true },
    filters:  { type: Object, required: true },
    shops:    { type: Array, required: true },
    counts:   { type: Object, required: true },
});

const columns = [
    { key: 'kind',       label: '種別',   width: '90px' },
    { key: 'name',       label: '名前',   width: '180px' },
    { key: 'kana',       label: 'カナ',   hideOnMobile: true, width: '140px' },
    { key: 'phone',      label: '電話',   hideOnMobile: true, width: '140px' },
    { key: 'shop',       label: '店舗',   hideOnMobile: true, width: '120px' },
    { key: 'extra',      label: '追加情報', hideOnMobile: true },
    { key: 'label',      label: '表示名', hideOnMobile: true, width: '140px' },
    { key: 'created_at', label: '連携日時', hideOnMobile: true, width: '140px' },
    { key: 'actions',    label: '',       align: 'right', width: '80px', noLink: true },
];

const form = reactive({
    type:    props.filters.type || 'all',
    shop_id: props.filters.shop_id ?? '',
    q:       props.filters.q || '',
});

function search() {
    router.get(route('admin.line-contacts.index'), {
        type: form.type,
        shop_id: form.shop_id === '' ? null : form.shop_id,
        q: form.q,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function reset() {
    form.type = 'all';
    form.shop_id = '';
    form.q = '';
    search();
}

const confirmOpen = ref(false);
const target = ref(null);
const unlinking = ref(false);

function askUnlink(c) {
    target.value = c;
    confirmOpen.value = true;
}

function confirmUnlink() {
    if (!target.value) return;
    unlinking.value = true;
    router.delete(route('admin.line-contacts.destroy', target.value.id), {
        preserveScroll: true,
        onFinish: () => {
            unlinking.value = false;
            confirmOpen.value = false;
            target.value = null;
        },
    });
}

function shortLineId(id) {
    if (!id) return '';
    return id.substring(0, 6) + '…' + id.substring(id.length - 4);
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
