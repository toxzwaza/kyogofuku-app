<template>
    <Head title="今月の支払確認" />

    <AdminLayout :breadcrumb="[{ label: 'クレジット' }, { label: '今月の支払確認' }]">
        <UiPageHeader
            title="今月の支払確認"
            description="自社クレジット・現金回収の分だけを表示します（信販会社の分は確認不要）。入金があったらチェックして「支払済にする」を押してください。"
        />

        <div class="flex items-center gap-2 mb-4">
            <UiButton variant="ghost" size="sm" @click="go(-1)"><ChevronLeft :size="14" /></UiButton>
            <div class="font-serif text-lg font-semibold tabular-nums w-32 text-center">{{ monthLabel }}</div>
            <UiButton variant="ghost" size="sm" @click="go(1)"><ChevronRight :size="14" /></UiButton>
            <UiButton variant="link" size="sm" @click="goThisMonth">今月</UiButton>
            <div class="ml-auto text-sm text-brand-text-muted">
                {{ paidCount }} / {{ payments.length }} 件 支払済　合計 <span class="tabular-nums font-semibold text-brand-text">{{ yen(totalAmount) }}</span>
            </div>
        </div>

        <UiCard padding="none" class="mb-6">
            <table class="w-full text-sm">
                <thead class="bg-brand-surface-2 text-brand-text-muted text-xs">
                    <tr>
                        <th v-if="canEdit" class="w-10 px-3 py-2"></th>
                        <th class="text-left px-3 py-2">お客様</th>
                        <th class="text-left px-3 py-2">支払方法</th>
                        <th class="text-left px-3 py-2 hidden md:table-cell">商品</th>
                        <th class="text-left px-3 py-2 hidden md:table-cell">担当</th>
                        <th class="text-right px-3 py-2">金額</th>
                        <th class="text-left px-3 py-2">状態</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in payments" :key="p.id" class="border-t border-brand-border" :class="p.is_paid ? 'text-brand-text-muted' : ''">
                        <td v-if="canEdit" class="px-3 py-2 text-center">
                            <input v-model="selected" :value="p.id" type="checkbox" class="rounded border-brand-border text-brand-primary" />
                        </td>
                        <td class="px-3 py-2">
                            <Link v-if="p.customer" :href="route('admin.customers.show', p.customer.id) + '?tab=credit'" class="font-medium hover:underline">{{ p.customer.name }}</Link>
                                <span v-if="p.customer?.guardian_name" class="ml-1 text-xs text-brand-text-muted">（保護者：{{ p.customer.guardian_name }}）</span>
                        </td>
                        <td class="px-3 py-2">{{ p.company }}</td>
                        <td class="px-3 py-2 hidden md:table-cell">{{ p.item_name }}</td>
                        <td class="px-3 py-2 hidden md:table-cell">{{ p.staff }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ yen(p.amount) }}</td>
                        <td class="px-3 py-2">
                            <UiBadge v-if="p.is_paid" size="sm" variant="success">支払済</UiBadge>
                            <UiBadge v-else size="sm" variant="warning">未払い</UiBadge>
                            <span v-if="p.is_paid && p.checker" class="ml-1 text-xs text-brand-text-muted">{{ p.checker }}</span>
                        </td>
                    </tr>
                    <tr v-if="payments.length === 0">
                        <td :colspan="canEdit ? 7 : 6" class="px-3 py-8 text-center text-brand-text-muted">この月の自社・現金の支払予定はありません。</td>
                    </tr>
                </tbody>
            </table>
        </UiCard>

        <div v-if="canEdit && payments.length" class="flex gap-2 mb-8">
            <UiButton variant="primary" :disabled="selected.length === 0" :loading="saving" @click="mark(true)">
                <template #leading><Check :size="14" /></template>
                選んだ {{ selected.length }} 件を支払済にする
            </UiButton>
            <UiButton variant="ghost" :disabled="selected.length === 0" @click="mark(false)">未払いに戻す</UiButton>
            <UiButton variant="link" @click="selectUnpaid">未払いをすべて選ぶ</UiButton>
        </div>

        <template v-if="overdue.length">
            <h3 class="font-serif text-base font-semibold mb-2 flex items-center gap-2">
                <AlertTriangle :size="15" class="text-brand-danger" />
                前の月までの未払い（{{ overdue.length }} 件）
            </h3>
            <UiCard padding="none">
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="p in overdue" :key="p.id" class="border-t first:border-t-0 border-brand-border">
                            <td v-if="canEdit" class="w-10 px-3 py-2 text-center">
                                <input v-model="selected" :value="p.id" type="checkbox" class="rounded border-brand-border text-brand-primary" />
                            </td>
                            <td class="px-3 py-2 tabular-nums w-24">{{ p.due_month }}</td>
                            <td class="px-3 py-2">
                                <Link v-if="p.customer" :href="route('admin.customers.show', p.customer.id) + '?tab=credit'" class="font-medium hover:underline">{{ p.customer.name }}</Link>
                                <span v-if="p.customer?.guardian_name" class="ml-1 text-xs text-brand-text-muted">（保護者：{{ p.customer.guardian_name }}）</span>
                            </td>
                            <td class="px-3 py-2">{{ p.company }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ yen(p.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </UiCard>
        </template>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { UiPageHeader, UiButton, UiCard, UiBadge } from '@/Components/UI';
import { ChevronLeft, ChevronRight, Check, AlertTriangle } from 'lucide-vue-next';

const props = defineProps({
    month: String,
    payments: Array,
    overdue: Array,
    canEdit: Boolean,
});

const selected = ref([]);
const saving = ref(false);

const monthLabel = computed(() => {
    const [y, m] = props.month.split('-');
    return `${y}年${Number(m)}月`;
});
const paidCount = computed(() => props.payments.filter((p) => p.is_paid).length);
const totalAmount = computed(() => props.payments.reduce((s, p) => s + p.amount, 0));
const yen = (v) => '¥' + Number(v || 0).toLocaleString();

const go = (delta) => {
    const [y, m] = props.month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    const ym = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    router.get(route('admin.credits.monthly'), { month: ym });
};
const goThisMonth = () => router.get(route('admin.credits.monthly'));

const selectUnpaid = () => {
    selected.value = props.payments.filter((p) => !p.is_paid).map((p) => p.id);
};

const mark = (isPaid) => {
    saving.value = true;
    router.post(route('admin.credits.payments.paid'), { ids: selected.value, is_paid: isPaid }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; },
        onFinish: () => { saving.value = false; },
    });
};
</script>
