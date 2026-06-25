<template>
    <Head title="友達紹介一覧" />
    <AdminLayout :breadcrumb="[{ label: '顧客' }, { label: '友達紹介' }]">
        <UiPageHeader title="友達紹介一覧" description="紹介関係とステータスを確認できます。" />

        <UiCard variant="default" padding="md" class="mb-4">
            <div class="flex flex-wrap gap-2 items-center">
                <button
                    v-for="s in statusList"
                    :key="s.value"
                    type="button"
                    class="px-3 py-1 rounded-full text-xs border"
                    :class="filters.status === s.value ? 'bg-brand-primary text-brand-on-primary border-brand-primary' : 'bg-brand-surface text-brand-text border-brand-border hover:bg-brand-surface-2'"
                    @click="filterBy(s.value)"
                >
                    {{ s.label }}<span v-if="s.value" class="ml-1 opacity-80">({{ counts[s.value] ?? 0 }})</span>
                </button>
            </div>
        </UiCard>

        <UiCard variant="default" padding="md">
            <div v-if="referrals.data.length" class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-brand-surface-2">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">紹介者</th>
                            <th class="px-4 py-2 text-left font-medium">被紹介者</th>
                            <th class="px-4 py-2 text-left font-medium">ステータス</th>
                            <th class="px-4 py-2 text-left font-medium">成約日</th>
                            <th class="px-4 py-2 text-left font-medium">確定日</th>
                            <th class="px-4 py-2 text-left font-medium">期限</th>
                            <th class="px-4 py-2 text-left font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-for="r in referrals.data" :key="r.id" class="hover:bg-brand-surface-2">
                            <td class="px-4 py-2">
                                <Link v-if="r.referrer_id" :href="route('admin.customers.show', r.referrer_id)" class="text-brand-primary hover:underline">{{ r.referrer || '—' }}</Link>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-2">
                                <Link v-if="r.referred_customer_id" :href="route('admin.customers.show', r.referred_customer_id)" class="text-brand-primary hover:underline">{{ r.referred || '—' }}</Link>
                                <span v-else class="text-brand-text-muted">{{ r.referred_line_user_id }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <span :class="statusClass(r.status)" class="px-2 py-0.5 rounded text-xs">{{ statusLabel(r.status) }}</span>
                                <span v-if="r.reject_reason" class="text-[10px] text-brand-text-subtle ml-1">{{ r.reject_reason }}</span>
                            </td>
                            <td class="px-4 py-2">{{ r.contracted_at || '—' }}</td>
                            <td class="px-4 py-2">{{ r.matured_at || '—' }}</td>
                            <td class="px-4 py-2">{{ r.expires_at || '—' }}</td>
                            <td class="px-4 py-2">
                                <button
                                    v-if="r.status === 'contracted'"
                                    type="button"
                                    :disabled="processingId === r.id"
                                    class="px-3 py-1 rounded-soft text-xs bg-brand-primary text-brand-on-primary hover:opacity-90 disabled:opacity-50"
                                    @click="matureReferral(r)"
                                >
                                    ポイント反映
                                </button>
                                <span v-else class="text-brand-text-subtle text-xs">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="referrals.last_page > 1" class="mt-4 flex justify-center gap-1">
                    <Link
                        v-for="link in referrals.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-2.5 py-1 rounded-soft border text-xs"
                        :class="link.active ? 'bg-brand-primary text-brand-on-primary border-brand-primary' : 'bg-brand-surface text-brand-text border-brand-border'"
                        v-html="link.label"
                        preserve-scroll
                    />
                </div>
            </div>
            <div v-else class="py-10 text-center text-brand-text-muted text-sm">紹介データがありません</div>
        </UiCard>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { UiPageHeader, UiCard } from '@/Components/UI';

const props = defineProps({
    referrals: Object,
    counts: Object,
    filters: Object,
});

const statusList = [
    { value: '', label: 'すべて' },
    { value: 'linked', label: '成立待ち' },
    { value: 'contracted', label: '成約（仮）' },
    { value: 'matured', label: '確定' },
    { value: 'expired', label: '期限切れ' },
    { value: 'rejected', label: '無効' },
];

const statusLabel = (s) => statusList.find((x) => x.value === s)?.label || s;
const statusClass = (s) => ({
    linked: 'bg-blue-100 text-blue-900',
    contracted: 'bg-amber-100 text-amber-900',
    matured: 'bg-green-100 text-green-900',
    expired: 'bg-gray-200 text-gray-700',
    rejected: 'bg-red-100 text-red-900',
}[s] || 'bg-gray-100');

const filterBy = (status) => {
    router.get(route('admin.referral.list'), status ? { status } : {}, { preserveState: true });
};

const processingId = ref(null);
const matureReferral = (r) => {
    if (!window.confirm(`「${r.referrer || '—'}」さんと被紹介者に紹介特典ポイントを反映します。よろしいですか？`)) return;
    processingId.value = r.id;
    router.post(route('admin.referral.mature', r.id), {}, {
        preserveScroll: true,
        onFinish: () => { processingId.value = null; },
    });
};
</script>
