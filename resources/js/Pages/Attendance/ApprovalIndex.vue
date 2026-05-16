<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2, RotateCcw, Inbox,
} from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    UiCard, UiBadge, UiButton, UiPageHeader, UiAlert,
} from '@/Components/UI';
import { formatTimeJa, formatDateJaWithWeekday } from '@/utils/dateFormat';
import {
    shiftPatternBadgeClass,
    formatOvertimeRounded,
    overtimeTooltip,
    formatBreakTotalLabel,
    breaksHasTooltip,
    formatBreaksTooltip,
} from '@/utils/attendanceHistoryDisplay';
import ShiftUnavailableHint from '@/Components/Attendance/ShiftUnavailableHint.vue';

const props = defineProps({
    records: Object,
});

const selectedIds = ref([]);

const pageIds = computed(() => (props.records?.data ?? []).map((r) => r.id));

const isAllSelected = computed(() => {
    const ids = pageIds.value;
    return ids.length > 0 && ids.every((id) => selectedIds.value.includes(id));
});

const isPartiallySelected = computed(() => {
    const ids = pageIds.value;
    const selected = selectedIds.value.filter((id) => ids.includes(id));
    return selected.length > 0 && selected.length < ids.length;
});

const selectAllCheckboxRef = ref(null);
watch([isPartiallySelected], () => {
    if (selectAllCheckboxRef.value) {
        selectAllCheckboxRef.value.indeterminate = isPartiallySelected.value;
    }
}, { immediate: true });

function toggleSelectAll() {
    const ids = pageIds.value;
    if (isAllSelected.value) {
        selectedIds.value = selectedIds.value.filter((id) => !ids.includes(id));
    } else {
        const merged = [...new Set([...selectedIds.value, ...ids])];
        selectedIds.value = merged;
    }
}

function approve(id) {
    router.post(route('attendance.approvals.approve', id));
}

function reject(id) {
    if (confirm('差し戻しますか？')) {
        router.post(route('attendance.approvals.reject', id));
    }
}

function batchApprove() {
    if (selectedIds.value.length === 0) return;
    if (!confirm(`選択した ${selectedIds.value.length} 件を承認しますか？`)) return;
    router.post(route('attendance.approvals.batch-approve'), { ids: selectedIds.value });
}

function batchReject() {
    if (selectedIds.value.length === 0) return;
    if (!confirm(`選択した ${selectedIds.value.length} 件を差し戻しますか？`)) return;
    router.post(route('attendance.approvals.batch-reject'), { ids: selectedIds.value });
}
</script>

<template>
    <Head title="承認依頼" />
    <AdminLayout :breadcrumb="[{ label: '勤怠' }, { label: '承認依頼' }]">

        <UiPageHeader
            title="承認依頼"
            description="申請された勤怠を承認・差し戻しできます。"
        >
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.attendance.index')">勤怠管理へ</UiButton>
            </template>
        </UiPageHeader>

        <UiAlert v-if="$page.props.flash?.success" variant="success" class="mb-4">
            {{ $page.props.flash.success }}
        </UiAlert>

        <UiAlert variant="info" class="mb-4">
            <span class="text-xs leading-relaxed">
                <strong>残業（丸め後）</strong>は終業（残業）の丸め設定に基づきます。出勤の早出・始業丸めは給与計算閾値の始業設定に従います。シフト未取得時は「？」アイコンで理由を確認できます。
            </span>
        </UiAlert>

        <!-- 一括操作バー -->
        <UiCard
            v-if="records.data?.length > 0"
            variant="default"
            padding="sm"
            class="mb-3"
        >
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm text-brand-text-muted">
                    選択中: <span class="font-semibold text-brand-text">{{ selectedIds.length }}</span> 件
                </span>
                <div class="ml-auto flex items-center gap-2">
                    <UiButton
                        type="button"
                        variant="primary"
                        size="sm"
                        :disabled="selectedIds.length === 0"
                        @click="batchApprove"
                    >
                        <template #leading><CheckCircle2 :size="14" /></template>
                        まとめて承認
                    </UiButton>
                    <UiButton
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="selectedIds.length === 0"
                        @click="batchReject"
                    >
                        <template #leading><RotateCcw :size="14" /></template>
                        まとめて差し戻し
                    </UiButton>
                </div>
            </div>
        </UiCard>

        <!-- 承認テーブル -->
        <UiCard variant="default" padding="none">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-brand-surface-2 text-brand-text-muted">
                        <tr>
                            <th class="w-10 px-3 py-2.5">
                                <input
                                    ref="selectAllCheckboxRef"
                                    type="checkbox"
                                    :checked="isAllSelected"
                                    @change="toggleSelectAll"
                                    class="rounded border-brand-border-strong text-brand-primary focus:ring-brand-primary"
                                />
                            </th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">日付</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">ユーザー</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">店舗</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">シフトパターン</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">シフト出勤</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">シフト退勤</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">打刻出勤</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">打刻退勤</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">残業（丸め後）</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">休憩（合計）</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider min-w-[8rem]">申請理由</th>
                            <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap pr-5">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-for="r in records.data" :key="r.id" class="hover:bg-brand-surface-2 transition-colors">
                            <td class="w-10 px-3 py-3">
                                <input
                                    type="checkbox"
                                    :value="r.id"
                                    v-model="selectedIds"
                                    class="rounded border-brand-border-strong text-brand-primary focus:ring-brand-primary"
                                />
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-brand-text font-medium">{{ formatDateJaWithWeekday(r.date) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-brand-text">{{ r.user?.name ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-brand-text">{{ r.shop?.name ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span
                                    v-if="r.shift?.calendar_pattern"
                                    :class="shiftPatternBadgeClass(r.shift.calendar_pattern)"
                                    class="inline-flex items-center justify-center min-w-[1.75rem] px-2.5 py-1 rounded-md text-xs font-semibold"
                                >
                                    {{ r.shift.calendar_pattern }}
                                </span>
                                <span v-else class="text-brand-text-subtle">—</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap tabular-nums text-brand-text">
                                <template v-if="r.shift?.available">{{ formatTimeJa(r.shift.start_at) }}</template>
                                <ShiftUnavailableHint v-else :reasons="r.shift?.help_reasons ?? []" />
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap tabular-nums text-brand-text">
                                <template v-if="r.shift?.available">{{ formatTimeJa(r.shift.end_at) }}</template>
                                <ShiftUnavailableHint v-else :reasons="r.shift?.help_reasons ?? []" />
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap tabular-nums text-brand-text">{{ formatTimeJa(r.clock_in_at) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap tabular-nums text-brand-text">{{ formatTimeJa(r.clock_out_at) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap tabular-nums text-brand-text" :title="overtimeTooltip(r.payroll)">
                                <span class="cursor-help border-b border-dotted border-brand-border-strong">{{ formatOvertimeRounded(r.payroll) }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap tabular-nums text-brand-text" :title="formatBreaksTooltip(r.breaks)">
                                <span
                                    v-if="breaksHasTooltip(r.breaks)"
                                    class="cursor-help border-b border-dotted border-brand-border-strong"
                                >{{ formatBreakTotalLabel(r.breaks) }}</span>
                                <template v-else>{{ formatBreakTotalLabel(r.breaks) }}</template>
                            </td>
                            <td class="px-4 py-3 max-w-[12rem] text-brand-text-muted text-xs leading-snug align-top">
                                {{ r.application_reason || '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right pr-5">
                                <button
                                    type="button"
                                    @click="approve(r.id)"
                                    class="inline-flex items-center gap-1 text-uguisu-700 hover:underline text-xs"
                                >
                                    <CheckCircle2 :size="12" /> 承認
                                </button>
                                <button
                                    type="button"
                                    @click="reject(r.id)"
                                    class="ml-3 inline-flex items-center gap-1 text-natane-700 hover:underline text-xs"
                                >
                                    <RotateCcw :size="12" /> 差し戻し
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!records.data || records.data.length === 0">
                            <td colspan="13" class="px-4 py-12 text-center text-brand-text-muted">
                                <div class="flex flex-col items-center gap-2">
                                    <Inbox :size="24" class="text-brand-text-subtle" />
                                    <span class="text-sm">承認待ちの申請はありません。</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ページネーション -->
            <div
                v-if="records.last_page > 1"
                class="flex items-center justify-between flex-wrap gap-2 px-5 py-3 border-t border-brand-border bg-brand-surface-2 text-sm"
            >
                <div class="text-brand-text-muted text-xs">
                    {{ records.from ?? 0 }} 〜 {{ records.to ?? 0 }} / 全 {{ records.total }} 件
                </div>
                <nav class="flex items-center gap-1">
                    <template v-for="(link, idx) in records.links" :key="idx">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            v-html="link.label"
                            :class="[
                                'px-2.5 py-1 rounded border transition-colors text-xs',
                                link.active
                                    ? 'bg-brand-primary text-brand-on-primary border-brand-primary'
                                    : 'bg-brand-surface border-brand-border text-brand-text hover:bg-brand-surface-2',
                            ]"
                            preserve-scroll
                        />
                        <span
                            v-else
                            v-html="link.label"
                            class="px-2.5 py-1 rounded border border-brand-border text-xs text-brand-text-subtle"
                        />
                    </template>
                </nav>
            </div>
        </UiCard>

    </AdminLayout>
</template>
