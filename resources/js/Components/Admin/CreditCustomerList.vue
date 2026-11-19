<template>
    <!-- 契約リスト：1行＝お客様1人。行をクリックすると契約の明細が開く -->
    <div class="overflow-x-auto border border-brand-border rounded-soft bg-brand-surface">
        <table class="w-full text-sm border-separate border-spacing-0">
            <thead>
                <tr class="bg-brand-surface-2 text-xs text-brand-text-muted">
                    <th class="w-8 border-b border-brand-border"></th>
                    <th class="text-left px-3 py-2 border-b border-brand-border">お客様</th>
                    <th class="text-left px-3 py-2 border-b border-brand-border w-[200px]">信販会社</th>
                    <th class="text-left px-3 py-2 border-b border-brand-border w-[120px] hidden md:table-cell">契約日</th>
                    <th class="text-left px-3 py-2 border-b border-brand-border hidden md:table-cell">商品</th>
                    <th class="text-right px-3 py-2 border-b border-brand-border w-[110px] hidden md:table-cell">元金</th>
                    <th class="text-right px-3 py-2 border-b border-brand-border w-[110px]">残額</th>
                    <th class="text-left px-3 py-2 border-b border-brand-border w-[100px] hidden md:table-cell">担当</th>
                    <th class="text-left px-3 py-2 border-b border-brand-border w-[90px]">状態</th>
                    <th class="border-b border-brand-border w-[80px]"></th>
                </tr>
            </thead>
            <tbody>
                <template v-for="g in groups" :key="g.id">
                    <!-- お客様の行（まとめ） -->
                    <tr class="cursor-pointer hover:bg-brand-surface-2" :class="isOpen(g.id) ? 'bg-brand-surface-2' : ''" @click="toggle(g.id)">
                        <td class="pl-2 py-2 border-t border-brand-border text-brand-text-muted">
                            <ChevronRight :size="16" class="transition-transform" :class="isOpen(g.id) ? 'rotate-90' : ''" />
                        </td>
                        <td class="px-3 py-2 border-t border-brand-border">
                            <Link
                                :href="route('admin.customers.show', g.customer.id) + '?tab=credit'"
                                class="font-medium hover:underline"
                                @click.stop
                            >{{ g.customer.name }}</Link>
                            <span v-if="g.customer.guardian_name" class="ml-1 text-xs text-brand-text-muted">（保護者：{{ g.customer.guardian_name }}）</span>
                            <div class="text-xs text-brand-text-muted">{{ g.customer.shop || '店舗未設定' }}</div>
                        </td>
                        <td class="px-3 py-2 border-t border-brand-border">
                            <span class="text-xs line-clamp-2" :title="g.companies.join('・')">{{ g.companies.join('・') }}</span>
                            <UiBadge v-if="g.needs_check" size="sm" variant="warning" class="ml-1">要確認</UiBadge>
                            <UiBadge v-if="g.provisional_count" size="sm" variant="danger" class="ml-1">仮登録{{ g.provisional_count > 1 ? ` ${g.provisional_count}` : '' }}</UiBadge>
                        </td>
                        <td class="px-3 py-2 border-t border-brand-border tabular-nums whitespace-nowrap hidden md:table-cell">
                            <div class="text-[10px] text-brand-text-muted leading-none">最新</div>{{ g.latest_date || '—' }}
                        </td>
                        <td class="px-3 py-2 border-t border-brand-border hidden md:table-cell">
                            <span class="inline-flex items-center whitespace-nowrap rounded-full bg-brand-surface-2 border border-brand-border px-2 text-xs">契約 {{ g.contract_count }} 件</span>
                            <span v-if="g.active_count && g.active_count !== g.contract_count" class="ml-1 whitespace-nowrap text-xs text-brand-text-muted">（支払中 {{ g.active_count }}）</span>
                        </td>
                        <td class="px-3 py-2 border-t border-brand-border text-right tabular-nums hidden md:table-cell">{{ yen(g.principal_total) }}</td>
                        <td class="px-3 py-2 border-t border-brand-border text-right tabular-nums" :class="g.unpaid_total > 0 ? 'font-semibold' : 'text-brand-text-muted'">{{ yen(g.unpaid_total) }}</td>
                        <td class="px-3 py-2 border-t border-brand-border text-xs hidden md:table-cell"><span class="line-clamp-2" :title="g.staffs.join('・')">{{ g.staffs.join('・') }}</span></td>
                        <td class="px-3 py-2 border-t border-brand-border">
                            <UiBadge v-if="g.active_count" size="sm" variant="primary">支払中</UiBadge>
                            <UiBadge v-else size="sm" :variant="statusVariant(g.contracts[0]?.status)">{{ g.contracts[0]?.status_label }}</UiBadge>
                        </td>
                        <td class="px-3 py-2 border-t border-brand-border text-right text-xs text-brand-text-muted whitespace-nowrap">
                            {{ isOpen(g.id) ? '閉じる' : '明細' }}
                        </td>
                    </tr>

                    <!-- 契約の明細 -->
                    <template v-if="isOpen(g.id)">
                        <tr v-for="r in g.contracts" :key="r.id" class="bg-brand-surface text-[13px]">
                            <td></td>
                            <td class="pl-8 pr-3 py-1.5 border-t border-dashed border-brand-border"></td>
                            <td class="px-3 py-1.5 border-t border-dashed border-brand-border">
                                <span>{{ r.company || '（未定）' }}</span>
                                <UiBadge v-if="r.needs_check" size="sm" variant="warning" class="ml-1">要確認</UiBadge>
                                <UiBadge v-if="r.is_provisional" size="sm" variant="danger" class="ml-1" :title="r.provisional_note || ''">仮登録</UiBadge>
                            </td>
                            <td class="px-3 py-1.5 border-t border-dashed border-brand-border tabular-nums hidden md:table-cell">{{ r.contract_date || '—' }}</td>
                            <td class="px-3 py-1.5 border-t border-dashed border-brand-border hidden md:table-cell">{{ r.item_name }}</td>
                            <td class="px-3 py-1.5 border-t border-dashed border-brand-border text-right tabular-nums hidden md:table-cell">{{ yen(r.principal) }}</td>
                            <td class="px-3 py-1.5 border-t border-dashed border-brand-border text-right tabular-nums" :class="r.unpaid_total > 0 ? 'font-semibold' : 'text-brand-text-muted'">{{ yen(r.unpaid_total) }}</td>
                            <td class="px-3 py-1.5 border-t border-dashed border-brand-border text-xs hidden md:table-cell">{{ r.staff }}</td>
                            <td class="px-3 py-1.5 border-t border-dashed border-brand-border">
                                <UiBadge size="sm" :variant="statusVariant(r.status)">{{ r.status_label }}</UiBadge>
                            </td>
                            <td class="px-3 py-1.5 border-t border-dashed border-brand-border text-right">
                                <UiButton size="sm" variant="ghost" :href="route('admin.credits.edit', r.id)">{{ canEdit ? '編集' : '詳細' }}</UiButton>
                            </td>
                        </tr>
                    </template>
                </template>
                <tr v-if="groups.length === 0">
                    <td colspan="10" class="px-3 py-8 text-center text-brand-text-muted">該当するクレジットがありません。</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { UiBadge, UiButton } from '@/Components/UI';

defineProps({
    groups: { type: Array, required: true },
    canEdit: Boolean,
    isOpen: { type: Function, required: true },
    toggle: { type: Function, required: true },
});

const yen = (v) => (v === null || v === undefined ? '—' : '¥' + Number(v).toLocaleString());
const statusVariant = (s) => ({ active: 'primary', completed: 'success', cancelled: 'neutral' }[s] || 'neutral');
</script>
