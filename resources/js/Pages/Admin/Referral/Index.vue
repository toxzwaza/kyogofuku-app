<template>
    <Head title="ポイント付与一覧" />
    <AdminLayout :breadcrumb="[{ label: '顧客' }, { label: 'ポイント付与' }]">
        <UiPageHeader title="ポイント付与一覧" description="友達紹介（紹介者報酬・被紹介者特典）と、ご成約（平田ポイント）の付与状況を確認できます。" />

        <UiCard variant="default" padding="md" class="mb-4">
            <div class="flex flex-wrap gap-4 items-center">
                <div class="flex flex-wrap gap-2 items-center">
                    <span class="text-xs text-brand-text-muted">種類</span>
                    <button
                        v-for="k in kindList"
                        :key="k.value"
                        type="button"
                        class="px-3 py-1 rounded-full text-xs border"
                        :class="filters.kind === k.value ? 'bg-brand-primary text-brand-on-primary border-brand-primary' : 'bg-brand-surface text-brand-text border-brand-border hover:bg-brand-surface-2'"
                        @click="applyFilter('kind', k.value)"
                    >
                        {{ k.label }}<span class="ml-1 opacity-80">({{ counts[k.count] ?? 0 }})</span>
                    </button>
                </div>
                <div class="flex flex-wrap gap-2 items-center">
                    <span class="text-xs text-brand-text-muted">状態</span>
                    <button
                        v-for="s in statusList"
                        :key="s.value"
                        type="button"
                        class="px-3 py-1 rounded-full text-xs border"
                        :class="filters.status === s.value ? 'bg-brand-primary text-brand-on-primary border-brand-primary' : 'bg-brand-surface text-brand-text border-brand-border hover:bg-brand-surface-2'"
                        @click="applyFilter('status', s.value)"
                    >
                        {{ s.label }}<span v-if="s.count" class="ml-1 opacity-80">({{ counts[s.count] ?? 0 }})</span>
                    </button>
                </div>
            </div>
        </UiCard>

        <UiCard variant="default" padding="md">
            <div v-if="grants.data.length" class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-brand-surface-2">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">対象者</th>
                            <th class="px-4 py-2 text-left font-medium">種類</th>
                            <th class="px-4 py-2 text-left font-medium">内訳</th>
                            <th class="px-4 py-2 text-right font-medium">付与ポイント</th>
                            <th class="px-4 py-2 text-left font-medium">状態</th>
                            <th class="px-4 py-2 text-left font-medium">日付</th>
                            <th class="px-4 py-2 text-left font-medium">付与予定日</th>
                            <th class="px-4 py-2 text-left font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-for="g in grants.data" :key="g.key" class="hover:bg-brand-surface-2">
                            <td class="px-4 py-2">
                                <Link v-if="g.customer_id" :href="route('admin.customers.show', g.customer_id)" class="text-brand-primary hover:underline">{{ g.customer || '—' }}</Link>
                                <span v-else class="text-brand-text-muted">—</span>
                            </td>
                            <td class="px-4 py-2">
                                <span :class="kindClass(g.kind)" class="px-2 py-0.5 rounded text-xs">{{ kindLabel(g.kind) }}</span>
                            </td>
                            <td class="px-4 py-2">{{ subtypeLabel(g.subtype) }}</td>
                            <td class="px-4 py-2 text-right font-semibold" :class="g.point_type === 'hirata' ? 'text-emerald-700' : 'text-brand-primary'">
                                {{ (g.points ?? 0).toLocaleString() }} pt
                            </td>
                            <td class="px-4 py-2">
                                <span :class="g.status === 'granted' ? 'bg-green-100 text-green-900' : 'bg-amber-100 text-amber-900'" class="px-2 py-0.5 rounded text-xs">
                                    {{ g.status === 'granted' ? '付与済み' : '付与予定' }}
                                </span>
                            </td>
                            <td class="px-4 py-2">{{ g.date || '—' }}</td>
                            <td class="px-4 py-2">
                                <span v-if="g.status === 'granted'" class="text-brand-text-subtle">付与済み</span>
                                <span v-else>{{ g.scheduled_at || '—' }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <button
                                    v-if="g.status === 'pending' && g.referral_id"
                                    type="button"
                                    :disabled="processingId === g.referral_id"
                                    class="px-3 py-1 rounded-soft text-xs bg-brand-primary text-brand-on-primary hover:opacity-90 disabled:opacity-50"
                                    @click="matureReferral(g)"
                                >
                                    ポイント反映
                                </button>
                                <span v-else class="text-brand-text-subtle text-xs">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="grants.last_page > 1" class="mt-4 flex justify-center gap-1">
                    <Link
                        v-for="link in grants.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-2.5 py-1 rounded-soft border text-xs"
                        :class="link.active ? 'bg-brand-primary text-brand-on-primary border-brand-primary' : 'bg-brand-surface text-brand-text border-brand-border'"
                        v-html="link.label"
                        preserve-scroll
                    />
                </div>
            </div>
            <div v-else class="py-10 text-center text-brand-text-muted text-sm">付与データがありません</div>
        </UiCard>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { UiPageHeader, UiCard } from '@/Components/UI';

const props = defineProps({
    grants: Object,
    counts: Object,
    filters: Object,
});

const kindList = [
    { value: 'all', label: 'すべて', count: 'all' },
    { value: 'referral', label: '友達紹介', count: 'referral' },
    { value: 'contract', label: 'ご成約', count: 'contract' },
];
const statusList = [
    { value: 'all', label: 'すべて', count: null },
    { value: 'granted', label: '付与済み', count: 'granted' },
    { value: 'pending', label: '付与予定', count: 'pending' },
];

const kindLabel = (k) => (k === 'contract' ? 'ご成約' : '友達紹介');
const kindClass = (k) => (k === 'contract' ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900');
const subtypeLabel = (t) => ({
    referrer_reward: '紹介者報酬',
    referred_bonus: '被紹介者特典',
    hirata_reward: '平田ポイント',
}[t] || t);

const applyFilter = (key, value) => {
    const q = { kind: props.filters.kind, status: props.filters.status, [key]: value };
    router.get(route('admin.referral.list'), q, { preserveState: true, preserveScroll: true });
};

const processingId = ref(null);
const matureReferral = (g) => {
    if (!window.confirm(`「${g.customer || '—'}」さんを含む紹介の特典ポイントを反映します。よろしいですか？`)) return;
    processingId.value = g.referral_id;
    router.post(route('admin.referral.mature', g.referral_id), {}, {
        preserveScroll: true,
        onFinish: () => { processingId.value = null; },
    });
};
</script>
