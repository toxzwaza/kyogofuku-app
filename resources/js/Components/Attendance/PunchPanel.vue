<script setup>
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    LogIn, LogOut, Coffee, X as XIcon, Plus, Trash2,
    BookOpen, FileEdit, History, CheckCircle2, Clock,
} from 'lucide-vue-next';
import {
    UiCard, UiBadge, UiSelect, UiFormField, UiDialog, UiButton,
} from '@/Components/UI';
import DigitalClock from '@/Components/DigitalClock.vue';

const props = defineProps({
    attendanceStatus: { type: Object, default: () => ({}) },
    userShops: { type: Array, default: () => [] },
    currentUser: { type: Object, default: null },
    registrableBreakDates: { type: Array, default: () => [] },
    attendanceManualUrl: { type: String, default: '' },
    attendanceManualUrlManager: { type: String, default: '' },
});

const attendanceManualLinkUrl = computed(() => {
    const isManager = props.currentUser?.canManageAttendance;
    const url = isManager
        ? (props.attendanceManualUrlManager || props.attendanceManualUrl)
        : props.attendanceManualUrl;
    return url || '';
});
const attendanceManualLabel = computed(() =>
    props.currentUser?.canManageAttendance ? '勤怠管理の使い方' : '勤怠打刻の使い方'
);

const cancellableActionLabel = computed(() => {
    const a = props.attendanceStatus?.cancellableAction;
    const labels = {
        clock_in: '出勤を取り消す',
        break_start: '休憩開始を取り消す',
        break_end: '休憩終了を取り消す',
        clock_out: '退勤を取り消す',
    };
    return labels[a] ?? '';
});

function isLastCompletedBreak(idx) {
    const breaks = props.attendanceStatus?.todayRecord?.breaks ?? [];
    const b = breaks[idx];
    if (!b?.end_at) return false;
    return !breaks.slice(idx + 1).some((x) => x.end_at);
}

const punchingAction = ref(null);
const attendanceCancelLoading = ref(false);
const clockInShopId = ref('');

const shopOptions = computed(() => (props.userShops || []).map((s) => ({ value: s.id, label: s.name })));

const canClockIn = computed(() => {
    const shops = props.userShops || [];
    return !props.attendanceStatus?.todayRecord && (shops.length <= 1 || !!clockInShopId.value);
});
const canClockOut = computed(() => !!props.attendanceStatus?.isWorking);

watch(
    () => props.userShops,
    (shops) => {
        if (shops && shops.length > 0 && !clockInShopId.value) {
            const main = shops.find((s) => s.main === true || s.main === 1);
            clockInShopId.value = (main || shops[0])?.id ?? '';
        }
    },
    { immediate: true }
);

const currentTime = ref('');
let clockInterval = null;
function updateClock() {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('ja-JP', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });
}
onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);
});
onUnmounted(() => {
    if (clockInterval) {
        clearInterval(clockInterval);
        clockInterval = null;
    }
});

function formatAttendanceTime(isoStr) {
    if (isoStr === null || isoStr === undefined) return '-';
    const date = new Date(isoStr);
    if (isNaN(date.getTime())) return '-';
    const h = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    return `${h}:${min}`;
}

async function clockIn() {
    const shops = props.userShops || [];
    const shopId = shops.length === 1 ? shops[0].id : clockInShopId.value;
    if (!shopId) return;
    punchingAction.value = 'clock_in';
    try {
        const { data } = await axios.post(route('attendance.clock-in'), { shop_id: shopId });
        if (data.success) router.reload();
        else alert(data.message || '出勤の登録に失敗しました。');
    } catch (err) {
        alert(err.response?.data?.message || '出勤の登録に失敗しました。');
    } finally {
        punchingAction.value = null;
    }
}

async function clockOut() {
    punchingAction.value = 'clock_out';
    try {
        const { data } = await axios.post(route('attendance.clock-out'));
        if (data.success) router.reload();
        else alert(data.message || '退勤の登録に失敗しました。');
    } catch (err) {
        alert(err.response?.data?.message || '退勤の登録に失敗しました。');
    } finally {
        punchingAction.value = null;
    }
}

// 休憩の事後登録（モーダル）
const showBreakModal = ref(false);
const breakSubmitting = ref(false);
const breakError = ref('');
const selectedBreakDate = ref('');
const breakRows = ref([]);

const breakDateOptions = computed(() =>
    (props.registrableBreakDates || []).map((d) => ({ value: d.date, label: d.label }))
);
const selectedBreakDateInfo = computed(() =>
    (props.registrableBreakDates || []).find((d) => d.date === selectedBreakDate.value) || null
);

const hourOptions = Array.from({ length: 24 }, (_, i) => {
    const v = String(i).padStart(2, '0');
    return { value: v, label: v };
});
const minuteOptions = ['00', '10', '20', '30', '40', '50'].map((v) => ({ value: v, label: v }));

function emptyBreakRow() {
    return { start_h: '', start_m: '', end_h: '', end_m: '' };
}

function openBreakModal() {
    selectedBreakDate.value = props.registrableBreakDates?.[0]?.date ?? '';
    breakRows.value = [emptyBreakRow()];
    breakError.value = '';
    showBreakModal.value = true;
}

function addBreakRow() {
    breakRows.value.push(emptyBreakRow());
}

function removeBreakRow(idx) {
    breakRows.value.splice(idx, 1);
    if (breakRows.value.length === 0) {
        breakRows.value.push(emptyBreakRow());
    }
}

async function submitBreak() {
    breakError.value = '';
    if (!selectedBreakDate.value) {
        breakError.value = '日付を選択してください。';
        return;
    }
    const breaks = [];
    for (let i = 0; i < breakRows.value.length; i++) {
        const r = breakRows.value[i];
        if (!r.start_h || !r.start_m || !r.end_h || !r.end_m) {
            breakError.value = `${i + 1}件目：休憩開始・休憩終了をすべて選択してください。`;
            return;
        }
        const start_time = `${r.start_h}:${r.start_m}`;
        const end_time = `${r.end_h}:${r.end_m}`;
        if (end_time <= start_time) {
            breakError.value = `${i + 1}件目：休憩終了は休憩開始より後の時刻を選択してください。`;
            return;
        }
        breaks.push({ start_time, end_time });
    }

    breakSubmitting.value = true;
    try {
        const { data } = await axios.post(route('attendance.break-register'), {
            date: selectedBreakDate.value,
            breaks,
        });
        if (data.success) {
            showBreakModal.value = false;
            router.reload();
        } else {
            breakError.value = data.message || '休憩の登録に失敗しました。';
        }
    } catch (err) {
        breakError.value = err.response?.data?.message || '休憩の登録に失敗しました。';
    } finally {
        breakSubmitting.value = false;
    }
}

async function cancelLastAttendanceAction() {
    const label = cancellableActionLabel.value;
    if (!label || !confirm(`${label}。よろしいですか？`)) return;
    attendanceCancelLoading.value = true;
    try {
        await axios.post(route('attendance.cancel-last'), {}, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        router.reload();
    } catch (err) {
        alert(err.response?.data?.message || '取消に失敗しました。');
    } finally {
        attendanceCancelLoading.value = false;
    }
}

const statusBadgeVariant = computed(() => {
    const s = props.attendanceStatus;
    if (!s?.todayRecord) return 'neutral';
    if (s.isOnBreak) return 'warning';
    if (s.isWorking) return 'success';
    if (s.todayRecord.clock_out_at) return 'neutral';
    return 'success';
});
const statusBadgeLabel = computed(() => {
    const s = props.attendanceStatus;
    if (!s?.todayRecord) return '-';
    if (s.isOnBreak) return '休憩中';
    if (s.isWorking) return '勤務中';
    if (s.todayRecord.clock_out_at) return '退勤済み';
    return '勤務中';
});
const statusHelperText = computed(() => {
    const s = props.attendanceStatus;
    if (!s?.todayRecord) return '';
    if (s.isOnBreak) return '休憩終了の打刻をお願いします';
    if (s.isWorking) return '退勤の打刻が可能です';
    if (s.todayRecord.clock_out_at) return '本日の勤務は終了しています';
    return '退勤の打刻が可能です';
});

const punchButtonClasses = (variant, enabled) => {
    const variants = {
        success: 'bg-uguisu-600 hover:bg-uguisu-700 text-white focus-visible:ring-uguisu-500',
        warning: 'bg-natane-500 hover:bg-natane-600 text-white focus-visible:ring-natane-400',
        primary: 'bg-brand-primary hover:bg-brand-primary-hover text-brand-on-primary focus-visible:ring-brand-primary',
        danger:  'bg-akane-600 hover:bg-akane-700 text-white focus-visible:ring-akane-500',
    };
    return [
        'inline-flex items-center justify-center gap-1.5 rounded-soft px-4 py-2.5 h-10 text-sm font-medium',
        'transition-all duration-150 select-none',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-brand-bg',
        variants[variant] || variants.primary,
        enabled ? 'opacity-100 cursor-pointer' : 'opacity-40 cursor-not-allowed',
    ];
};
</script>

<template>
    <UiCard variant="default" padding="none">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-serif text-base text-brand-text flex items-center gap-2">
                    <Clock :size="16" class="text-brand-primary" />
                    勤怠打刻
                </h2>
                <UiBadge v-if="attendanceStatus?.todayRecord" :variant="statusBadgeVariant" size="sm" dot>
                    {{ statusBadgeLabel }}
                </UiBadge>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-5">
            <!-- 左：時計 + 打刻ボタン -->
            <div class="space-y-4">
                <DigitalClock :time="currentTime" />

                <UiFormField v-if="userShops.length > 1" label="店舗">
                    <UiSelect
                        v-model="clockInShopId"
                        :options="shopOptions"
                        placeholder="選択してください"
                        size="md"
                    />
                </UiFormField>

                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        :disabled="!canClockIn || !!punchingAction"
                        @click="clockIn"
                        :class="punchButtonClasses('success', canClockIn && !punchingAction)"
                    >
                        <LogIn :size="16" />
                        {{ punchingAction === 'clock_in' ? '処理中…' : '出勤' }}
                    </button>
                    <button
                        type="button"
                        :disabled="!canClockOut || !!punchingAction"
                        @click="clockOut"
                        :class="punchButtonClasses('danger', canClockOut && !punchingAction)"
                    >
                        <LogOut :size="16" />
                        {{ punchingAction === 'clock_out' ? '処理中…' : '退勤' }}
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 pt-2 text-sm">
                    <button
                        type="button"
                        @click="openBreakModal"
                        class="inline-flex items-center gap-1 text-natane-700 hover:underline"
                    >
                        <Coffee :size="14" /> 休憩登録
                    </button>
                    <Link
                        :href="route('attendance.provisional.create')"
                        class="inline-flex items-center gap-1 text-brand-primary hover:underline"
                    >
                        <FileEdit :size="14" /> 日付指定で仮登録
                    </Link>
                    <Link
                        :href="route('attendance.history')"
                        class="inline-flex items-center gap-1 text-brand-text-muted hover:text-brand-text"
                    >
                        <History :size="14" /> 勤怠履歴
                    </Link>
                    <template v-if="currentUser?.canManageAttendance">
                        <Link
                            :href="route('attendance.approvals')"
                            class="inline-flex items-center gap-1 text-natane-700 hover:underline"
                        >
                            <CheckCircle2 :size="14" /> 承認依頼
                        </Link>
                        <Link
                            :href="route('admin.attendance.index')"
                            class="inline-flex items-center gap-1 text-enji-700 hover:underline"
                        >
                            <Clock :size="14" /> 勤怠管理
                        </Link>
                    </template>
                    <a
                        v-if="attendanceManualLinkUrl"
                        :href="attendanceManualLinkUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 text-brand-primary hover:underline"
                    >
                        <BookOpen :size="14" /> {{ attendanceManualLabel }}
                    </a>
                </div>
            </div>

            <!-- 右：本日の打刻状況 -->
            <div
                :class="[
                    'rounded-soft-lg border p-4',
                    attendanceStatus?.todayRecord
                        ? attendanceStatus.isOnBreak
                            ? 'bg-natane-50/60 border-natane-200 dark:bg-natane-900/20 dark:border-natane-800'
                            : attendanceStatus.isWorking
                                ? 'bg-uguisu-50/60 border-uguisu-200 dark:bg-uguisu-900/20 dark:border-uguisu-800'
                                : 'bg-brand-surface-2 border-brand-border'
                        : 'bg-brand-surface-2 border-brand-border'
                ]"
            >
                <h3 class="text-sm font-semibold text-brand-text mb-3">本日の勤務ステータス</h3>

                <div v-if="attendanceStatus?.todayRecord" class="mb-4 text-center">
                    <UiBadge :variant="statusBadgeVariant" size="md" class="text-sm px-3 py-1">
                        {{ statusBadgeLabel }}
                    </UiBadge>
                    <p class="mt-1.5 text-xs text-brand-text-muted">{{ statusHelperText }}</p>
                </div>

                <div v-if="attendanceStatus?.todayRecord" class="space-y-1.5 text-sm">
                    <p class="text-[11px] font-medium text-brand-text-subtle uppercase tracking-wider mb-1">打刻履歴</p>

                    <div class="flex items-center gap-3 py-1.5 border-b border-brand-border">
                        <span class="w-6 h-6 rounded-full bg-uguisu-100 text-uguisu-700 flex items-center justify-center text-[11px] font-bold shrink-0 dark:bg-uguisu-900 dark:text-uguisu-200">出</span>
                        <span class="text-brand-text-muted">勤務開始</span>
                        <div class="ml-auto flex items-center gap-2">
                            <button
                                v-if="attendanceStatus.cancellableAction === 'clock_in'"
                                type="button"
                                :disabled="attendanceCancelLoading"
                                @click="cancelLastAttendanceAction"
                                class="inline-flex items-center gap-0.5 text-[11px] text-akane-600 hover:text-akane-800 hover:underline disabled:opacity-50 shrink-0"
                            >
                                <XIcon :size="12" />
                                {{ attendanceCancelLoading ? '取消中' : '取消' }}
                            </button>
                            <span class="font-mono text-brand-text tabular-nums">{{ formatAttendanceTime(attendanceStatus.todayRecord.clock_in_at) }}</span>
                        </div>
                    </div>

                    <template v-for="(b, idx) in attendanceStatus.todayRecord.breaks" :key="idx">
                        <div class="flex items-center gap-3 py-1.5 border-b border-brand-border">
                            <span class="w-6 h-6 rounded-full bg-natane-100 text-natane-700 flex items-center justify-center text-[11px] font-bold shrink-0 dark:bg-natane-900 dark:text-natane-200">休</span>
                            <span class="text-brand-text-muted">休憩{{ attendanceStatus.todayRecord.breaks.length > 1 ? idx + 1 : '' }} 開始</span>
                            <UiBadge v-if="!b.end_at" variant="warning" size="sm">休憩中</UiBadge>
                            <div class="ml-auto flex items-center gap-2">
                                <button
                                    v-if="attendanceStatus.cancellableAction === 'break_start' && !b.end_at"
                                    type="button"
                                    :disabled="attendanceCancelLoading"
                                    @click="cancelLastAttendanceAction"
                                    class="inline-flex items-center gap-0.5 text-[11px] text-akane-600 hover:text-akane-800 hover:underline disabled:opacity-50 shrink-0"
                                >
                                    <XIcon :size="12" />
                                    {{ attendanceCancelLoading ? '取消中' : '取消' }}
                                </button>
                                <span class="font-mono text-brand-text tabular-nums">{{ formatAttendanceTime(b.start_at) }}</span>
                            </div>
                        </div>
                        <div v-if="b.end_at" class="flex items-center gap-3 py-1.5 border-b border-brand-border pl-9">
                            <span class="text-brand-text-subtle text-xs">→ 終了</span>
                            <div class="ml-auto flex items-center gap-2">
                                <button
                                    v-if="attendanceStatus.cancellableAction === 'break_end' && isLastCompletedBreak(idx)"
                                    type="button"
                                    :disabled="attendanceCancelLoading"
                                    @click="cancelLastAttendanceAction"
                                    class="inline-flex items-center gap-0.5 text-[11px] text-akane-600 hover:text-akane-800 hover:underline disabled:opacity-50 shrink-0"
                                >
                                    <XIcon :size="12" />
                                    {{ attendanceCancelLoading ? '取消中' : '取消' }}
                                </button>
                                <span class="font-mono text-brand-text-muted tabular-nums">{{ formatAttendanceTime(b.end_at) }}</span>
                            </div>
                        </div>
                    </template>

                    <div v-if="attendanceStatus.todayRecord.clock_out_at" class="flex items-center gap-3 py-1.5">
                        <span class="w-6 h-6 rounded-full bg-akane-100 text-akane-700 flex items-center justify-center text-[11px] font-bold shrink-0 dark:bg-akane-900 dark:text-akane-200">退</span>
                        <span class="text-brand-text-muted">退勤</span>
                        <div class="ml-auto flex items-center gap-2">
                            <button
                                v-if="attendanceStatus.cancellableAction === 'clock_out'"
                                type="button"
                                :disabled="attendanceCancelLoading"
                                @click="cancelLastAttendanceAction"
                                class="inline-flex items-center gap-0.5 text-[11px] text-akane-600 hover:text-akane-800 hover:underline disabled:opacity-50 shrink-0"
                            >
                                <XIcon :size="12" />
                                {{ attendanceCancelLoading ? '取消中' : '取消' }}
                            </button>
                            <span class="font-mono text-brand-text tabular-nums">{{ formatAttendanceTime(attendanceStatus.todayRecord.clock_out_at) }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="text-sm text-brand-text-muted py-6 text-center">
                    本日の勤怠はまだありません。<br>左の「出勤」ボタンから打刻してください。
                </div>
            </div>
        </div>
    </UiCard>

    <!-- 休憩の事後登録モーダル -->
    <UiDialog v-model:open="showBreakModal" title="休憩登録" size="md">
        <p class="text-xs text-brand-text-muted mb-4 leading-relaxed">
            通常、休憩の登録は不要です。休憩を超過して取得した場合や、取得できなかった場合のみ登録してください。<br>
            登録した休憩は指定日の勤怠に追加され、CSV出力に反映されます。
        </p>

        <div v-if="breakDateOptions.length === 0" class="text-sm text-brand-text-muted py-4 text-center">
            休憩を登録できる勤怠記録がありません。<br>先に出勤打刻または仮登録を行ってください。
        </div>

        <div v-else class="space-y-4">
            <UiFormField label="日付">
                <UiSelect
                    v-model="selectedBreakDate"
                    :options="breakDateOptions"
                    placeholder="選択してください"
                    size="md"
                />
            </UiFormField>

            <div
                v-if="selectedBreakDateInfo"
                class="flex items-center gap-4 rounded-soft bg-brand-surface-2 border border-brand-border px-3 py-2 text-sm"
            >
                <span class="text-brand-text-muted">出勤
                    <span class="font-mono text-brand-text tabular-nums ml-1">{{ selectedBreakDateInfo.clock_in_at || '-' }}</span>
                </span>
                <span class="text-brand-text-muted">退勤
                    <span class="font-mono text-brand-text tabular-nums ml-1">{{ selectedBreakDateInfo.clock_out_at || '-' }}</span>
                </span>
            </div>

            <div class="space-y-2">
                <p class="text-[11px] font-medium text-brand-text-subtle uppercase tracking-wider">休憩時間</p>
                <div
                    v-for="(row, idx) in breakRows"
                    :key="idx"
                    class="flex items-end gap-2"
                >
                    <div class="flex-1">
                        <label class="block text-[11px] text-brand-text-muted mb-1">休憩開始</label>
                        <div class="flex items-center gap-1">
                            <UiSelect v-model="row.start_h" :options="hourOptions" placeholder="時" size="md" />
                            <span class="text-brand-text-muted">:</span>
                            <UiSelect v-model="row.start_m" :options="minuteOptions" placeholder="分" size="md" />
                        </div>
                    </div>
                    <span class="pb-2 text-brand-text-subtle">〜</span>
                    <div class="flex-1">
                        <label class="block text-[11px] text-brand-text-muted mb-1">休憩終了</label>
                        <div class="flex items-center gap-1">
                            <UiSelect v-model="row.end_h" :options="hourOptions" placeholder="時" size="md" />
                            <span class="text-brand-text-muted">:</span>
                            <UiSelect v-model="row.end_m" :options="minuteOptions" placeholder="分" size="md" />
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="removeBreakRow(idx)"
                        class="pb-1.5 text-brand-text-muted hover:text-akane-600 transition-colors"
                        title="この行を削除"
                    >
                        <Trash2 :size="16" />
                    </button>
                </div>

                <button
                    type="button"
                    @click="addBreakRow"
                    class="inline-flex items-center gap-1 text-sm text-brand-primary hover:underline pt-1"
                >
                    <Plus :size="14" /> 休憩を追加
                </button>
            </div>

            <p v-if="breakError" class="text-sm text-akane-600">{{ breakError }}</p>
        </div>

        <template #footer>
            <UiButton variant="ghost" @click="showBreakModal = false">キャンセル</UiButton>
            <UiButton
                variant="primary"
                :loading="breakSubmitting"
                :disabled="breakDateOptions.length === 0"
                @click="submitBreak"
            >登録する</UiButton>
        </template>
    </UiDialog>
</template>
