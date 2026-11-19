<template>
    <div>
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm text-brand-text-muted">
                <template v-if="contracts.length">
                    契約 {{ contracts.length }} 件　残額 <span class="tabular-nums font-semibold text-brand-text">{{ yen(totalUnpaid) }}</span>
                </template>
            </div>
            <UiButton v-if="canEdit" variant="primary" size="sm" :href="route('admin.credits.create', { customer_id: customerId })">
                <template #leading><Plus :size="13" /></template>
                契約を追加
            </UiButton>
        </div>

        <div v-if="loading" class="py-10 text-center text-sm text-brand-text-muted">読み込み中…</div>
        <div v-else-if="contracts.length === 0" class="py-10 text-center text-sm text-brand-text-muted">クレジットの契約はありません。</div>

        <!-- Excel の支払一覧表と同じ並び：行＝年月、列＝契約、右端＝月合計 -->
        <div v-else class="overflow-auto border border-brand-border rounded-soft max-h-[70vh]">
            <table class="text-sm min-w-full">
                <thead class="bg-brand-surface-2 text-xs sticky top-0 z-10">
                    <tr>
                        <th class="px-3 py-2 text-left sticky left-0 bg-brand-surface-2">年月</th>
                        <th v-for="c in contracts" :key="c.id" class="px-3 py-2 text-right align-top min-w-[130px]">
                            <Link :href="route('admin.credits.edit', c.id)" class="hover:underline">
                                <div class="font-semibold text-brand-text">{{ c.company || '（未定）' }}</div>
                                <div class="font-normal text-brand-text-muted">{{ c.contract_date || '—' }}</div>
                                <div class="font-normal text-brand-text-muted">{{ c.item_name || '' }}</div>
                                <div class="font-normal text-brand-text-muted">元金 {{ yen(c.principal) }}<span v-if="c.fee">／手数料 {{ yen(c.fee) }}</span></div>
                                <div class="font-normal text-brand-text-muted">担当 {{ c.staff || '—' }}</div>
                            </Link>
                            <UiBadge size="sm" :variant="statusVariant(c.status)" class="mt-1">{{ c.status_label }}</UiBadge>
                            <UiBadge v-if="c.is_provisional" size="sm" variant="danger" class="mt-1 ml-1" :title="c.provisional_note || ''">仮登録</UiBadge>
                        </th>
                        <th class="px-3 py-2 text-right">月合計</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in months" :key="m" class="border-t border-brand-border" :class="m === thisMonth ? 'bg-amber-50' : ''">
                        <td class="px-3 py-1 tabular-nums sticky left-0 bg-brand-surface">{{ m }}</td>
                        <td v-for="c in contracts" :key="c.id" class="px-3 py-1 text-right tabular-nums">
                            <template v-if="cell(c, m)">
                                <span :class="cell(c, m).is_paid ? 'text-brand-text-muted' : 'font-semibold'">{{ yen(cell(c, m).amount) }}</span>
                                <Check v-if="cell(c, m).is_paid" :size="12" class="inline ml-1 text-green-600" />
                            </template>
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums">{{ yen(monthTotal(m)) }}</td>
                    </tr>
                </tbody>
                <tfoot class="bg-brand-surface-2 text-xs">
                    <tr class="border-t-2 border-brand-border">
                        <td class="px-3 py-2 sticky left-0 bg-brand-surface-2">合計</td>
                        <td v-for="c in contracts" :key="c.id" class="px-3 py-2 text-right tabular-nums">{{ yen(c.scheduled_total) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ yen(contracts.reduce((s, c) => s + c.scheduled_total, 0)) }}</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 sticky left-0 bg-brand-surface-2 font-semibold">残額</td>
                        <td v-for="c in contracts" :key="c.id" class="px-3 py-2 text-right tabular-nums font-semibold">{{ yen(c.unpaid_total) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ yen(totalUnpaid) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p v-if="contracts.length" class="mt-2 text-xs text-brand-text-muted">
            <Check :size="11" class="inline text-green-600" /> は支払済。契約の見出しを押すと、内容の確認・修正ができます。
        </p>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { Link } from '@inertiajs/vue3';
import { UiButton, UiBadge } from '@/Components/UI';
import { Plus, Check } from 'lucide-vue-next';

const props = defineProps({
    customerId: { type: Number, required: true },
});

const loading = ref(true);
const contracts = ref([]);
const canEdit = ref(false);

onMounted(async () => {
    try {
        const { data } = await axios.get(route('admin.customers.credits', props.customerId));
        contracts.value = data.contracts || [];
        canEdit.value = !!data.canEdit;
    } finally {
        loading.value = false;
    }
});

const now = new Date();
const thisMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

const months = computed(() => {
    const set = new Set();
    contracts.value.forEach((c) => c.payments.forEach((p) => set.add(p.due_month)));
    return [...set].sort();
});

const index = computed(() => {
    const map = {};
    contracts.value.forEach((c) => {
        map[c.id] = {};
        c.payments.forEach((p) => { map[c.id][p.due_month] = p; });
    });
    return map;
});

const cell = (c, m) => index.value[c.id]?.[m];
const monthTotal = (m) => contracts.value.reduce((s, c) => s + (cell(c, m)?.amount || 0), 0);
const totalUnpaid = computed(() => contracts.value.reduce((s, c) => s + c.unpaid_total, 0));
const yen = (v) => '¥' + Number(v || 0).toLocaleString();
const statusVariant = (s) => ({ active: 'primary', completed: 'success', cancelled: 'neutral' }[s] || 'neutral');
</script>
