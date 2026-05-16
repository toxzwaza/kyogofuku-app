<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft, ChevronRight, Filter, FileEdit, Send, Info,
} from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    UiCard, UiBadge, UiButton, UiPageHeader,
    UiInput, UiFormField, UiAlert, UiTextarea,
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
    filters: Object,
});

const filters = ref({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

watch(
    () => [props.filters?.from, props.filters?.to],
    ([from, to]) => {
        filters.value.from = from ?? '';
        filters.value.to = to ?? '';
    },
);

const applyTarget = ref(null);
const applyReason = ref('');

function pad2(n) {
    return String(n).padStart(2, '0');
}

function formatYmd(d) {
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function resolveAnchorToYmd(fromYmd, toYmd) {
    if (toYmd && /^\d{4}-\d{2}-\d{2}$/.test(toYmd)) {
        return toYmd;
    }
    if (fromYmd && /^\d{4}-\d{2}-\d{2}$/.test(fromYmd)) {
        const [y, m, d] = fromYmd.split('-').map(Number);
        const start = new Date(y, m - 1, d);
        const end = new Date(start);
        end.setMonth(end.getMonth() + 1);
        end.setDate(20);
        return formatYmd(end);
    }
    return null;
}

function shiftCloseMonthPeriod(anchorToYmd, deltaMonths) {
    if (!anchorToYmd || !/^\d{4}-\d{2}-\d{2}$/.test(anchorToYmd)) {
        return null;
    }
    const [y, m] = anchorToYmd.split('-').map(Number);
    const base = new Date(y, m - 1 + deltaMonths, 1);
    const ey = base.getFullYear();
    const em = base.getMonth() + 1;
    const closeBefore = new Date(ey, em - 2, 1);
    const fy = closeBefore.getFullYear();
    const fm = closeBefore.getMonth() + 1;
    return {
        from: `${fy}-${pad2(fm)}-21`,
        to: `${ey}-${pad2(em)}-20`,
    };
}

function goAdjacentPeriod(deltaMonths) {
    const anchor = resolveAnchorToYmd(filters.value.from, filters.value.to);
    const next = shiftCloseMonthPeriod(anchor, deltaMonths);
    if (!next) {
        return;
    }
    router.get(route('attendance.history'), {
        from: next.from,
        to: next.to,
    }, { preserveState: true });
}

function applyFilters() {
    router.get(route('attendance.history'), {
        from: filters.value.from || undefined,
        to: filters.value.to || undefined,
    }, { preserveState: true });
}

function statusBadgeVariant(s) {
    const map = { draft: 'neutral', applied: 'warning', approved: 'success' };
    return map[s] ?? 'neutral';
}

function statusLabel(s) {
    const labels = { draft: '未申請', applied: '申請済', approved: '承認済' };
    return labels[s] ?? s;
}

function showApplyModal(record) {
    applyTarget.value = record;
    applyReason.value = '';
}

function submitApply() {
    if (!applyTarget.value) return;
    router.post(route('attendance.provisional.apply', applyTarget.value.id), {
        application_reason: applyReason.value,
    });
    applyTarget.value = null;
}

const flashMessage = computed(() => {
    return null;
});
</script>

<template>
    <Head title="勤怠履歴" />
    <AdminLayout :breadcrumb="[{ label: '勤怠' }, { label: '勤怠履歴' }]">

        <UiPageHeader
            title="勤怠履歴"
            description="自分の勤怠記録を期間指定で確認できます。"
        >
            <template #actions>
                <UiButton variant="ghost" :href="route('attendance.index')">打刻へ</UiButton>
                <UiButton variant="primary" :href="route('attendance.provisional.create')">仮登録</UiButton>
            </template>
        </UiPageHeader>

        <UiAlert
            v-if="$page.props.flash?.success"
            variant="success"
            class="mb-4"
        >
            {{ $page.props.flash.success }}
        </UiAlert>

        <!-- フィルタ -->
        <UiCard variant="default" padding="md" class="mb-4">
            <template #header>
                <div class="flex items-center gap-2">
                    <Filter :size="14" class="text-brand-text-muted" />
                    <h2 class="font-serif text-base text-brand-text">期間絞り込み</h2>
                </div>
            </template>
            <form @submit.prevent="applyFilters" class="flex flex-wrap items-end gap-3">
                <UiFormField label="開始日">
                    <UiInput v-model="filters.from" type="date" size="md" />
                </UiFormField>
                <UiFormField label="終了日">
                    <UiInput v-model="filters.to" type="date" size="md" />
                </UiFormField>
                <div class="flex flex-wrap items-center gap-2">
                    <UiButton type="button" variant="ghost" size="md" @click="goAdjacentPeriod(-1)">
                        <template #leading><ChevronLeft :size="14" /></template>
                        前月
                    </UiButton>
                    <UiButton type="button" variant="ghost" size="md" @click="goAdjacentPeriod(1)">
                        次月
                        <template #trailing><ChevronRight :size="14" /></template>
                    </UiButton>
                    <UiButton type="submit" variant="primary" size="md">絞り込み</UiButton>
                </div>
            </form>
            <p class="mt-3 text-xs text-brand-text-subtle">
                初期表示は前月21日〜今月20日。「前月／次月」はこの締め単位で期間を移動します。日付を空にして絞り込むと初期の締め期間に戻ります。
            </p>
        </UiCard>

        <!-- 残業の説明 -->
        <UiAlert variant="info" class="mb-4">
            <template #default>
                <span class="text-xs leading-relaxed">
                    <strong>残業</strong>は、シフト退勤より後の打刻からシフト終了後の休憩を控除した実分を、給与設定の<strong>終業（残業）</strong>丸めで算出した値です。
                    出勤の早出・丸めは<strong>始業</strong>設定（管理画面の給与計算閾値）に従います。シフト未取得日は算出できません。
                </span>
            </template>
        </UiAlert>

        <!-- 勤怠履歴テーブル -->
        <UiCard variant="default" padding="none">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-brand-surface-2 text-brand-text-muted">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">日付</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">店舗</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">ステータス</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">シフトパターン</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">シフト出勤</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">シフト退勤</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">打刻出勤</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">打刻退勤</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">残業（丸め後）</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap">休憩（合計）</th>
                            <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap pr-5">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-for="r in records.data" :key="r.id" class="hover:bg-brand-surface-2 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-brand-text font-medium">{{ formatDateJaWithWeekday(r.date) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-brand-text">{{ r.shop?.name ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <UiBadge :variant="statusBadgeVariant(r.status)" size="sm">{{ statusLabel(r.status) }}</UiBadge>
                            </td>
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
                            <td class="px-4 py-3 whitespace-nowrap text-right pr-5">
                                <template v-if="r.status === 'draft'">
                                    <Link
                                        :href="route('attendance.provisional.edit', r.id)"
                                        class="inline-flex items-center gap-1 text-brand-primary hover:underline text-xs"
                                    >
                                        <FileEdit :size="12" /> 編集
                                    </Link>
                                    <button
                                        type="button"
                                        @click="showApplyModal(r)"
                                        class="ml-3 inline-flex items-center gap-1 text-natane-700 hover:underline text-xs"
                                    >
                                        <Send :size="12" /> 申請
                                    </button>
                                </template>
                                <span v-else class="text-brand-text-subtle text-xs">—</span>
                            </td>
                        </tr>
                        <tr v-if="!records.data || records.data.length === 0">
                            <td colspan="11" class="px-4 py-12 text-center text-brand-text-muted">
                                <div class="flex flex-col items-center gap-2">
                                    <Info :size="24" class="text-brand-text-subtle" />
                                    <span class="text-sm">該当する勤怠記録がありません。</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ページネーション -->
            <div v-if="records.last_page > 1" class="flex items-center justify-between flex-wrap gap-2 px-5 py-3 border-t border-brand-border bg-brand-surface-2 text-sm">
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

        <!-- 申請モーダル -->
        <div
            v-if="applyTarget"
            class="fixed inset-0 bg-sumi-950/50 backdrop-blur-sm flex items-center justify-center z-50 p-4"
            @click.self="applyTarget = null"
        >
            <div class="bg-brand-surface rounded-soft-lg shadow-soft-lg max-w-md w-full p-6">
                <h3 class="font-serif text-lg text-brand-text mb-4">申請（任意：理由を入力）</h3>
                <UiTextarea
                    v-model="applyReason"
                    :rows="3"
                    placeholder="申請理由（任意）"
                    class="mb-4"
                />
                <div class="flex justify-end gap-2">
                    <UiButton variant="ghost" @click="applyTarget = null">キャンセル</UiButton>
                    <UiButton variant="primary" @click="submitApply">申請する</UiButton>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
