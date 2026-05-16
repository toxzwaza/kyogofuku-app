<template>
    <Head title="承認依頼" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">承認依頼</h2>
                <Link :href="route('dashboard')" class="text-gray-600 hover:text-gray-900">ダッシュボードへ</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="w-full max-w-7xl 2xl:max-w-[min(100%,96rem)] mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 sm:p-8">
                        <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-md text-sm">
                            {{ $page.props.flash.success }}
                        </div>

                        <p class="text-xs text-gray-500 mb-4 leading-relaxed max-w-4xl">
                            <span class="font-medium text-gray-600">残業（丸め後）</span>は終業（残業）の丸め設定に基づきます。出勤の早出・始業丸めは給与計算閾値の始業設定に従います。シフト未取得時は「？」で理由を確認できます。
                        </p>

                        <div v-if="records.data?.length > 0" class="mb-4 flex flex-wrap items-center gap-2">
                            <span class="text-sm text-gray-600">選択中: {{ selectedIds.length }}件</span>
                            <button
                                type="button"
                                :disabled="selectedIds.length === 0"
                                @click="batchApprove"
                                class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                まとめて承認
                            </button>
                            <button
                                type="button"
                                :disabled="selectedIds.length === 0"
                                @click="batchReject"
                                class="px-3 py-1.5 text-sm bg-amber-500 text-white rounded-md hover:bg-amber-600 disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                まとめて差し戻し
                            </button>
                        </div>

                        <div class="overflow-x-auto -mx-2 sm:mx-0 rounded-lg border border-gray-200">
                            <table class="history-table min-w-[1180px] w-full border-collapse text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="th-cell-checkbox w-10 px-2">
                                            <input
                                                ref="selectAllCheckboxRef"
                                                type="checkbox"
                                                :checked="isAllSelected"
                                                @change="toggleSelectAll"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                            />
                                        </th>
                                        <th class="th-cell whitespace-nowrap">日付</th>
                                        <th class="th-cell whitespace-nowrap">ユーザー</th>
                                        <th class="th-cell whitespace-nowrap">店舗</th>
                                        <th class="th-cell whitespace-nowrap">シフトパターン</th>
                                        <th class="th-cell whitespace-nowrap">シフト出勤</th>
                                        <th class="th-cell whitespace-nowrap">シフト退勤</th>
                                        <th class="th-cell whitespace-nowrap">打刻出勤</th>
                                        <th class="th-cell whitespace-nowrap">打刻退勤</th>
                                        <th class="th-cell whitespace-nowrap" title="シフト退勤以降の実労働から休憩を控除後、給与設定の丸め単位で算出">残業（丸め後）</th>
                                        <th class="th-cell whitespace-nowrap" title="合計時間。ホバーで各休憩の開始〜終了時刻を表示">休憩（合計）</th>
                                        <th class="th-cell min-w-[6rem]">申請理由</th>
                                        <th class="th-cell whitespace-nowrap pr-6 sm:pr-8">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    <tr v-for="r in records.data" :key="r.id" class="hover:bg-gray-50/80 transition-colors">
                                        <td class="td-cell-checkbox px-2 w-10 align-middle">
                                            <input
                                                type="checkbox"
                                                :value="r.id"
                                                v-model="selectedIds"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                            />
                                        </td>
                                        <td class="td-cell whitespace-nowrap font-medium text-gray-900">{{ formatDateJaWithWeekday(r.date) }}</td>
                                        <td class="td-cell whitespace-nowrap text-gray-800">{{ r.user?.name ?? '-' }}</td>
                                        <td class="td-cell whitespace-nowrap text-gray-800">{{ r.shop?.name ?? '-' }}</td>
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
                                        <td class="td-cell max-w-[10rem] text-gray-700 text-xs leading-snug align-top">{{ r.application_reason ?? '—' }}</td>
                                        <td class="td-cell whitespace-nowrap pr-6 sm:pr-8">
                                            <form :action="route('attendance.approvals.approve', r.id)" method="POST" class="inline" @submit.prevent="approve(r.id)">
                                                <input type="hidden" name="_token" :value="$page.props.auth?.csrf_token ?? ''" />
                                                <button type="submit" class="text-green-600 hover:text-green-900">承認</button>
                                            </form>
                                            <span class="mx-1">|</span>
                                            <form :action="route('attendance.approvals.reject', r.id)" method="POST" class="inline" @submit.prevent="reject(r.id)">
                                                <input type="hidden" name="_token" :value="$page.props.auth?.csrf_token ?? ''" />
                                                <button type="submit" class="text-amber-600 hover:text-amber-900">差し戻し</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr v-if="!records.data || records.data.length === 0">
                                        <td colspan="13" class="px-6 py-12 text-center text-gray-500 text-base">承認待ちの申請はありません</td>
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
                            >
                                <span v-html="link.label" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
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
    router.post(route('attendance.approvals.batch-approve'), { ids: selectedIds.value });
}

function batchReject() {
    if (selectedIds.value.length === 0) return;
    if (!confirm(`選択した ${selectedIds.value.length} 件を差し戻しますか？`)) return;
    router.post(route('attendance.approvals.batch-reject'), { ids: selectedIds.value });
}
</script>

<style scoped>
.history-table .th-cell,
.history-table .td-cell {
    @apply px-4 py-3 text-left text-sm border-b border-gray-100 sm:px-5 sm:py-3.5;
}
.history-table thead .th-cell {
    @apply font-semibold text-gray-700 border-b border-gray-200 bg-gray-50;
}
.history-table thead .th-cell-checkbox {
    @apply w-10 px-2 py-3 text-center align-middle font-semibold text-gray-700 border-b border-gray-200 bg-gray-50;
}
.history-table tbody .td-cell-checkbox {
    @apply w-10 px-2 py-3 text-center align-middle border-b border-gray-100 bg-white;
}
</style>
