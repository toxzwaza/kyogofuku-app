<template>
    <Head title="勤怠履歴" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">勤怠履歴</h2>
                <div class="flex gap-2">
                    <Link
                        :href="route('attendance.provisional.create')"
                        class="text-indigo-600 hover:text-indigo-900"
                    >
                        仮登録
                    </Link>
                    <Link
                        :href="route('dashboard')"
                        class="text-gray-600 hover:text-gray-900"
                    >
                        ダッシュボードへ
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="w-full max-w-7xl 2xl:max-w-[min(100%,96rem)] mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 sm:p-8 lg:p-10">
                        <form @submit.prevent="applyFilters" class="mb-4 flex flex-wrap gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">開始日</label>
                                <input v-model="filters.from" type="date" class="rounded-md border-gray-300 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">終了日</label>
                                <input v-model="filters.to" type="date" class="rounded-md border-gray-300 text-sm" />
                            </div>
                            <div class="flex flex-wrap items-end gap-2">
                                <button
                                    type="button"
                                    class="px-3 py-2 rounded-md text-sm border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                                    title="終了日の月を基準に、ひとつ前の締め期間（前月21日〜当該月20日）へ移動します"
                                    @click="goAdjacentPeriod(-1)"
                                >
                                    ← 前月
                                </button>
                                <button
                                    type="button"
                                    class="px-3 py-2 rounded-md text-sm border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                                    title="終了日の月を基準に、ひとつ次の締め期間（前月21日〜当該月20日）へ移動します"
                                    @click="goAdjacentPeriod(1)"
                                >
                                    次月 →
                                </button>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                                    絞り込み
                                </button>
                            </div>
                        </form>
                        <p class="text-xs text-gray-500 mb-4">初期表示の期間は、前月21日〜今月20日です。「← 前月」「次月 →」はこの締め単位で期間を移動します。日付を空にして絞り込むと、初期の締め期間に戻ります。</p>

                        <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-md text-sm">
                            {{ $page.props.flash.success }}
                        </div>

                        <p class="text-xs text-gray-500 mb-4 leading-relaxed max-w-4xl">
                            <span class="font-medium text-gray-600">残業</span>は、シフト退勤より後の打刻からシフト終了後の休憩を控除した実分を、給与設定の<strong>終業（残業）</strong>丸めで算出した値です。出勤の早出・丸めは<strong>始業</strong>設定（管理画面の給与計算閾値）に従います。シフト未取得日は算出できません。
                        </p>

                        <div class="overflow-x-auto -mx-2 sm:mx-0 rounded-lg border border-gray-200">
                            <table class="history-table min-w-[1020px] w-full border-collapse text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="th-cell whitespace-nowrap">日付</th>
                                        <th class="th-cell whitespace-nowrap">店舗</th>
                                        <th class="th-cell whitespace-nowrap">ステータス</th>
                                        <th class="th-cell whitespace-nowrap">シフトパターン</th>
                                        <th class="th-cell whitespace-nowrap">シフト出勤</th>
                                        <th class="th-cell whitespace-nowrap">シフト退勤</th>
                                        <th class="th-cell whitespace-nowrap">打刻出勤</th>
                                        <th class="th-cell whitespace-nowrap">打刻退勤</th>
                                        <th class="th-cell whitespace-nowrap" title="シフト退勤以降の実労働から休憩を控除後、給与設定の丸め単位で算出">残業（丸め後）</th>
                                        <th class="th-cell whitespace-nowrap" title="合計時間。ホバーで各休憩の開始〜終了時刻を表示">休憩（合計）</th>
                                        <th class="th-cell whitespace-nowrap pr-6 sm:pr-8">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    <tr v-for="r in records.data" :key="r.id" class="hover:bg-gray-50/80 transition-colors">
                                        <td class="td-cell whitespace-nowrap font-medium text-gray-900">{{ formatDateJaWithWeekday(r.date) }}</td>
                                        <td class="td-cell whitespace-nowrap text-gray-800">{{ r.shop?.name ?? '-' }}</td>
                                        <td class="td-cell whitespace-nowrap">
                                            <span :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span>
                                        </td>
                                        <td class="td-cell whitespace-nowrap">
                                            <span
                                                v-if="r.shift?.calendar_pattern"
                                                :class="shiftPatternBadgeClass(r.shift.calendar_pattern)"
                                                class="inline-flex items-center justify-center min-w-[1.75rem] px-2.5 py-1 rounded-md text-xs font-semibold"
                                            >
                                                {{ r.shift.calendar_pattern }}
                                            </span>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="td-cell whitespace-nowrap tabular-nums text-gray-800">
                                            <template v-if="r.shift?.available">
                                                {{ formatTimeJa(r.shift.start_at) }}
                                            </template>
                                            <ShiftUnavailableHint v-else :reasons="r.shift?.help_reasons ?? []" />
                                        </td>
                                        <td class="td-cell whitespace-nowrap tabular-nums text-gray-800">
                                            <template v-if="r.shift?.available">
                                                {{ formatTimeJa(r.shift.end_at) }}
                                            </template>
                                            <ShiftUnavailableHint v-else :reasons="r.shift?.help_reasons ?? []" />
                                        </td>
                                        <td class="td-cell whitespace-nowrap tabular-nums text-gray-800">{{ formatTimeJa(r.clock_in_at) }}</td>
                                        <td class="td-cell whitespace-nowrap tabular-nums text-gray-800">{{ formatTimeJa(r.clock_out_at) }}</td>
                                        <td
                                            class="td-cell whitespace-nowrap tabular-nums text-gray-800"
                                            :title="overtimeTooltip(r.payroll)"
                                        >
                                            <span class="cursor-help border-b border-dotted border-gray-400">{{ formatOvertimeRounded(r.payroll) }}</span>
                                        </td>
                                        <td
                                            class="td-cell whitespace-nowrap tabular-nums text-gray-800"
                                            :title="formatBreaksTooltip(r.breaks)"
                                        >
                                            <span
                                                v-if="breaksHasTooltip(r.breaks)"
                                                class="cursor-help border-b border-dotted border-gray-400"
                                            >{{ formatBreakTotalLabel(r.breaks) }}</span>
                                            <template v-else>{{ formatBreakTotalLabel(r.breaks) }}</template>
                                        </td>
                                        <td class="td-cell whitespace-nowrap pr-6 sm:pr-8">
                                            <template v-if="r.status === 'draft'">
                                                <Link
                                                    :href="route('attendance.provisional.edit', r.id)"
                                                    class="text-indigo-600 hover:text-indigo-900"
                                                >
                                                    編集
                                                </Link>
                                                <button
                                                    type="button"
                                                    @click="showApplyModal(r)"
                                                    class="text-amber-600 hover:text-amber-900 ml-4"
                                                >
                                                    申請
                                                </button>
                                            </template>
                                        </td>
                                    </tr>
                                    <tr v-if="!records.data || records.data.length === 0">
                                        <td colspan="11" class="px-6 py-12 text-center text-gray-500 text-base">データがありません</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="records.last_page > 1" class="mt-4 flex justify-center gap-2">
                            <Link
                                v-for="link in records.links"
                                :key="link.label"
                                :href="link.url"
                                :class="[
                                    'px-3 py-1 rounded text-sm',
                                    link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                ]"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 申請モーダル -->
        <div v-if="applyTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="applyTarget = null">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">申請（任意：理由を入力）</h3>
                <textarea
                    v-model="applyReason"
                    rows="3"
                    class="w-full rounded-md border-gray-300 text-sm mb-4"
                    placeholder="申請理由（任意）"
                />
                <div class="flex justify-end gap-2">
                    <button @click="applyTarget = null" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md">キャンセル</button>
                    <button @click="submitApply" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">申請する</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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

/** 終了日が無いときは開始日（21日開始想定）から終了日20日を推定 */
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

/**
 * 締め期間を1つずらす（終了日の属する月を delta だけシフトし、その月の20日締め・前月21日開始）
 * @param {string} anchorToYmd - 基準となる終了日 Y-m-d
 * @param {number} deltaMonths - -1: 前月 / +1: 次月
 */
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

function statusLabel(s) {
    const labels = { draft: '未申請', applied: '申請済', approved: '承認済' };
    return labels[s] ?? s;
}

function statusClass(s) {
    const classes = {
        draft: 'px-2 py-0.5 bg-gray-100 text-gray-800 rounded text-xs',
        applied: 'px-2 py-0.5 bg-amber-100 text-amber-800 rounded text-xs',
        approved: 'px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs',
    };
    return classes[s] ?? '';
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
</script>

<style scoped>
.history-table .th-cell {
    @apply px-5 py-3.5 text-left text-sm font-semibold text-gray-700 border-b border-gray-200 sm:px-7 sm:py-4;
}
.history-table .th-cell:first-child {
    @apply pl-6 sm:pl-9;
}
.history-table .td-cell {
    @apply px-5 py-3.5 text-[15px] leading-relaxed text-gray-800 border-b border-gray-100 align-middle sm:px-7 sm:py-4;
}
.history-table .td-cell:first-child {
    @apply pl-6 sm:pl-9;
}
</style>
