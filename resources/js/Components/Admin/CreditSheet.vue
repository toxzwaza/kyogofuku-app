<template>
    <div>
        <!-- 凡例 -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-2 text-xs text-brand-text-muted">
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-green-50 border border-green-200"></span>支払済</span>
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-amber-100 border border-amber-300"></span>今月・未払い（自社・現金）</span>
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-red-100 border border-red-300"></span>遅れ（自社・現金）</span>
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-brand-surface border border-brand-border"></span>これから</span>
            <span>※ 信販会社の分は、月が過ぎると自動で支払済になります</span>
        </div>

        <div class="overflow-auto border border-brand-border rounded-soft max-h-[72vh] bg-brand-surface">
            <table class="text-xs border-separate border-spacing-0">
                <thead class="sticky top-0 z-20">
                    <tr class="bg-brand-surface-2 text-brand-text-muted">
                        <th class="sticky left-0 z-30 bg-brand-surface-2 text-left px-3 py-2 min-w-[220px] border-b border-r border-brand-border">お客様／支払方法</th>
                        <th class="px-3 py-2 text-right min-w-[96px] border-b border-r border-brand-border bg-brand-surface-2">残額</th>
                        <th
                            v-for="m in months"
                            :key="m"
                            class="px-2 py-2 text-right font-medium min-w-[76px] border-b border-brand-border tabular-nums"
                            :class="m === thisMonth ? 'bg-amber-50 text-brand-text font-semibold' : 'bg-brand-surface-2'"
                        >
                            <div v-if="m.endsWith('-01') || m === months[0]" class="text-[10px] font-normal">{{ m.slice(0, 4) }}年</div>
                            {{ Number(m.slice(5)) }}月
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="g in groups" :key="g.id">
                        <!-- お客様の行（全契約の合計）。クリックで契約ごとの行が開く -->
                        <tr class="group cursor-pointer" @click="toggle(g.id)">
                            <td class="sticky left-0 z-10 bg-brand-surface group-hover:bg-brand-surface-2 px-2 py-1.5 border-t border-r border-brand-border">
                                <div class="flex items-start gap-1">
                                    <ChevronRight :size="14" class="mt-0.5 shrink-0 text-brand-text-muted transition-transform" :class="isOpen(g.id) ? 'rotate-90' : ''" />
                                    <div class="min-w-0">
                                        <Link
                                            :href="route('admin.customers.show', g.customer.id) + '?tab=credit'"
                                            class="font-medium text-sm hover:underline"
                                            @click.stop
                                        >{{ g.customer.name }}</Link>
                                        <span v-if="g.customer.guardian_name" class="ml-1 text-brand-text-muted">（保護者：{{ g.customer.guardian_name }}）</span>
                                        <div class="text-brand-text-muted truncate max-w-[200px]">
                                            <span v-if="g.provisional_count" class="mr-1 rounded px-1 text-[10px] font-semibold bg-akane-100 text-akane-800 dark:bg-akane-900 dark:text-akane-100">仮登録</span>契約 {{ g.contract_count }} 件・{{ g.companies.join('・') }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td
                                class="px-3 py-1.5 text-right tabular-nums border-t border-r border-brand-border group-hover:bg-brand-surface-2"
                                :class="g.unpaid_total > 0 ? 'font-semibold' : 'text-brand-text-subtle'"
                            >{{ yen(g.unpaid_total) }}</td>
                            <td
                                v-for="m in months"
                                :key="m"
                                class="px-2 py-1.5 text-right tabular-nums whitespace-nowrap border-t border-l border-t-brand-border border-l-brand-border/50"
                                :class="groupCellClass(g, m)"
                            >
                                <template v-if="groupAmount(g, m)">
                                    {{ num(groupAmount(g, m)) }}<Check v-if="groupPaid(g, m)" :size="10" class="inline ml-0.5 text-green-600" />
                                </template>
                            </td>
                        </tr>

                        <!-- 契約ごとの行 -->
                        <template v-if="isOpen(g.id)">
                            <tr v-for="r in g.contracts" :key="r.id" class="group/row">
                                <td class="sticky left-0 z-10 bg-brand-surface group-hover/row:bg-brand-surface-2 pl-7 pr-3 py-1 border-r border-brand-border border-t border-dashed">
                                    <Link :href="route('admin.credits.edit', r.id)" class="block text-brand-text-muted hover:underline truncate max-w-[190px]">
                                        <span v-if="r.is_provisional" class="mr-1 rounded px-1 text-[10px] font-semibold bg-akane-100 text-akane-800 dark:bg-akane-900 dark:text-akane-100" :title="r.provisional_note || ''">仮登録</span>{{ r.company || '（未定）' }}<template v-if="r.item_name">・{{ r.item_name }}</template>
                                        <span v-if="r.status !== 'active'">（{{ r.status_label }}）</span>
                                    </Link>
                                </td>
                                <td
                                    class="px-3 py-1 text-right tabular-nums border-r border-brand-border border-t border-dashed"
                                    :class="r.unpaid_total > 0 ? '' : 'text-brand-text-subtle'"
                                >{{ yen(r.unpaid_total) }}</td>
                                <td
                                    v-for="m in months"
                                    :key="m"
                                    class="px-2 py-1 text-right tabular-nums whitespace-nowrap border-l border-brand-border/50 border-t border-dashed"
                                    :class="cellClass(r, m)"
                                >
                                    <template v-if="r.cells[m]">
                                        {{ num(r.cells[m].amount) }}<Check v-if="looksPaid(r, m)" :size="10" class="inline ml-0.5 text-green-600" />
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </template>
                    <tr v-if="groups.length === 0">
                        <td :colspan="months.length + 2" class="px-3 py-8 text-center text-brand-text-muted">該当するクレジットがありません。</td>
                    </tr>
                </tbody>
                <tfoot v-if="groups.length" class="sticky bottom-0 z-20">
                    <tr class="bg-brand-surface-2 font-semibold">
                        <td class="sticky left-0 z-30 bg-brand-surface-2 px-3 py-2 border-t-2 border-r border-brand-border">月合計（このページ）</td>
                        <td class="px-3 py-2 text-right tabular-nums border-t-2 border-r border-brand-border bg-brand-surface-2">{{ yen(groups.reduce((s, g) => s + g.unpaid_total, 0)) }}</td>
                        <td
                            v-for="m in months"
                            :key="m"
                            class="px-2 py-2 text-right tabular-nums border-t-2 border-brand-border"
                            :class="m === thisMonth ? 'bg-amber-50' : 'bg-brand-surface-2'"
                        >{{ num(monthTotal(m)) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { Check, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    groups: { type: Array, required: true },
    months: { type: Array, required: true },
    thisMonth: { type: String, required: true },
    isOpen: { type: Function, required: true },
    toggle: { type: Function, required: true },
});

// 信販会社の分は遅れが出ないので、過ぎた月は支払済として見せる
const looksPaid = (r, m) => {
    const c = r.cells[m];
    if (!c) return false;
    return c.is_paid || (!r.needs_check && m < props.thisMonth);
};

const cellClass = (r, m) => {
    const c = r.cells[m];
    if (!c) return '';
    if (looksPaid(r, m)) return 'bg-green-50 text-brand-text-muted';
    if (m < props.thisMonth) return 'bg-red-100 text-red-800 font-semibold';
    if (m === props.thisMonth && r.needs_check) return 'bg-amber-100 font-semibold';
    return '';
};

// お客様の行：その月の全契約の合計。1件でも遅れ・今月未払いがあればその色にする
const groupAmount = (g, m) => g.contracts.reduce((s, r) => s + (r.cells[m]?.amount || 0), 0);
const groupPaid = (g, m) => g.contracts.every((r) => !r.cells[m] || looksPaid(r, m));
const groupCellClass = (g, m) => {
    const classes = g.contracts.map((r) => cellClass(r, m));
    return classes.find((c) => c.includes('bg-red')) || classes.find((c) => c.includes('bg-amber'))
        || (groupAmount(g, m) && groupPaid(g, m) ? 'bg-green-50 text-brand-text-muted' : 'group-hover:bg-brand-surface-2');
};

const monthTotal = (m) => props.groups.reduce((s, g) => s + groupAmount(g, m), 0);
const num = (v) => Number(v || 0).toLocaleString();
const yen = (v) => '¥' + Number(v || 0).toLocaleString();
</script>
