<template>
    <Head title="会社カレンダー" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">会社カレンダー（A/B/C）</h2>
                <Link :href="route('admin.attendance.index')" class="text-gray-600 hover:text-gray-900 text-sm">勤怠一覧へ</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-md text-sm">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="Object.keys(pageErrors).length" class="mb-4 p-3 bg-red-50 text-red-800 rounded-md text-sm space-y-1">
                    <p class="font-medium">保存できませんでした。入力を確認してください。</p>
                    <ul class="list-disc list-inside text-xs">
                        <li v-for="(msg, key) in pageErrors" :key="key">{{ key }}: {{ Array.isArray(msg) ? msg.join(', ') : msg }}</li>
                    </ul>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- 年月ナビ -->
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="p-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
                                    title="前月"
                                    @click="shiftMonth(-1)"
                                >
                                    <span class="sr-only">前月</span>
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </button>
                                <input
                                    v-model="yearMonth"
                                    type="month"
                                    class="rounded-md border-gray-300 text-sm"
                                    @change="goMonth"
                                />
                                <button
                                    type="button"
                                    class="p-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
                                    title="翌月"
                                    @click="shiftMonth(1)"
                                >
                                    <span class="sr-only">翌月</span>
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>
                            <p class="text-sm text-gray-500">日付をクリックして A / B / C を設定できます（未設定で登録解除）</p>
                        </div>

                        <!-- カレンダーグリッド（月曜始まり） -->
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div class="grid grid-cols-7 bg-gray-100 border-b border-gray-200 text-center text-xs font-semibold text-gray-600 py-2">
                                <div v-for="w in weekLabels" :key="w" class="py-1">{{ w }}</div>
                            </div>
                            <div v-for="(week, wi) in calendarWeeks" :key="wi" class="grid grid-cols-7 border-b border-gray-200 last:border-b-0 min-h-[4.5rem]">
                                <div
                                    v-for="(cell, ci) in week"
                                    :key="ci"
                                    class="border-r border-gray-200 last:border-r-0 p-1 min-h-[4.5rem]"
                                    :class="cell.kind === 'empty' ? 'bg-gray-50/80' : ''"
                                >
                                    <button
                                        v-if="cell.kind === 'day'"
                                        type="button"
                                        class="w-full h-full min-h-[4rem] rounded-lg flex flex-col items-center justify-start pt-1 px-1 transition-colors text-left"
                                        :class="cellButtonClass(cell)"
                                        @click="openPicker(cell.dateStr)"
                                    >
                                        <span class="text-sm font-medium text-gray-900">{{ cell.day }}</span>
                                        <span
                                            v-if="cell.pattern"
                                            class="mt-1 inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold text-white shadow-sm"
                                            :class="patternBadgeClass(cell.pattern)"
                                        >
                                            {{ cell.pattern }}
                                        </span>
                                        <span v-else class="mt-1 text-xs text-gray-400">未設定</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="submit"
                                :disabled="saving"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ saving ? '保存中...' : '保存' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- パターン選択モーダル -->
        <Teleport to="body">
            <div
                v-if="pickerDate"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
                role="dialog"
                aria-modal="true"
                @click.self="closePicker"
            >
                <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-5 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ pickerTitle }}</h3>
                    <p class="text-sm text-gray-500">この日の勤務パターンを選択してください</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="py-3 rounded-lg border-2 border-gray-200 text-gray-600 font-medium hover:bg-gray-50"
                            @click="applyPattern('')"
                        >
                            未設定
                        </button>
                        <button
                            type="button"
                            class="py-3 rounded-lg border-2 border-indigo-200 bg-indigo-50 text-indigo-800 font-bold hover:bg-indigo-100"
                            @click="applyPattern('A')"
                        >
                            A
                        </button>
                        <button
                            type="button"
                            class="py-3 rounded-lg border-2 border-emerald-200 bg-emerald-50 text-emerald-800 font-bold hover:bg-emerald-100"
                            @click="applyPattern('B')"
                        >
                            B
                        </button>
                        <button
                            type="button"
                            class="py-3 rounded-lg border-2 border-amber-200 bg-amber-50 text-amber-900 font-bold hover:bg-amber-100"
                            @click="applyPattern('C')"
                        >
                            C
                        </button>
                    </div>
                    <button
                        type="button"
                        class="w-full py-2 text-sm text-gray-600 hover:text-gray-900"
                        @click="closePicker"
                    >
                        キャンセル
                    </button>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const page = usePage();

const props = defineProps({
    yearMonth: String,
    days: Array,
});

const weekLabels = ['月', '火', '水', '木', '金', '土', '日'];
const weekdayJa = ['日', '月', '火', '水', '木', '金', '土'];

const yearMonth = ref(props.yearMonth);
const saving = ref(false);
const pickerDate = ref(null);

function mapDaysToRows(days) {
    return (days || []).map((d) => ({
        calendar_date: d.calendar_date,
        weekday_label: d.weekday_label,
        pattern: d.pattern == null || d.pattern === '' ? '' : String(d.pattern),
    }));
}

const daysRows = ref(mapDaysToRows(props.days));

watch(
    () => props.days,
    (newDays) => {
        daysRows.value = mapDaysToRows(newDays);
        yearMonth.value = props.yearMonth;
    },
    { deep: true }
);

const pageErrors = computed(() => page.props.errors || {});

function parseYearMonth(ym) {
    const [y, m] = (ym || '').split('-').map((n) => parseInt(n, 10));
    if (!y || !m) return null;
    return { y, m };
}

/** 月曜始まりの列インデックス (0=月 … 6=日) */
function mondayColumnIndex(jsDay) {
    return (jsDay + 6) % 7;
}

function patternForDate(dateStr) {
    const row = daysRows.value.find((r) => r.calendar_date === dateStr);
    return row ? row.pattern : '';
}

const calendarWeeks = computed(() => {
    const parsed = parseYearMonth(yearMonth.value);
    if (!parsed) return [];
    const { y, m } = parsed;
    const first = new Date(y, m - 1, 1);
    const lastDay = new Date(y, m, 0).getDate();
    const padStart = mondayColumnIndex(first.getDay());

    const flat = [];
    for (let i = 0; i < padStart; i++) {
        flat.push({ kind: 'empty' });
    }
    for (let d = 1; d <= lastDay; d++) {
        const mm = String(m).padStart(2, '0');
        const dd = String(d).padStart(2, '0');
        const dateStr = `${y}-${mm}-${dd}`;
        flat.push({
            kind: 'day',
            dateStr,
            day: d,
            pattern: patternForDate(dateStr),
        });
    }
    while (flat.length % 7 !== 0) {
        flat.push({ kind: 'empty' });
    }

    const weeks = [];
    for (let i = 0; i < flat.length; i += 7) {
        weeks.push(flat.slice(i, i + 7));
    }
    return weeks;
});

const todayYmd = computed(() => {
    const t = new Date();
    return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
});

function cellButtonClass(cell) {
    const classes = ['hover:bg-indigo-50/80 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1'];
    if (cell.dateStr === todayYmd.value) {
        classes.push('ring-2 ring-indigo-400 ring-offset-1 bg-indigo-50/30');
    } else {
        classes.push('hover:border hover:border-indigo-200');
    }
    return classes;
}

function patternBadgeClass(p) {
    if (p === 'A') return 'bg-indigo-600';
    if (p === 'B') return 'bg-emerald-600';
    if (p === 'C') return 'bg-amber-500';
    return 'bg-gray-400';
}

const pickerTitle = computed(() => {
    if (!pickerDate.value) return '';
    const [Y, M, D] = pickerDate.value.split('-').map((n) => parseInt(n, 10));
    const dt = new Date(Y, M - 1, D);
    const w = weekdayJa[dt.getDay()];
    return `${Y}年${M}月${D}日（${w}）`;
});

function goMonth() {
    router.get(route('admin.attendance.company-calendar.index'), { year_month: yearMonth.value }, { preserveState: true });
}

function shiftMonth(delta) {
    const parsed = parseYearMonth(yearMonth.value);
    if (!parsed) return;
    const d = new Date(parsed.y, parsed.m - 1 + delta, 1);
    yearMonth.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    goMonth();
}

function openPicker(dateStr) {
    pickerDate.value = dateStr;
}

function closePicker() {
    pickerDate.value = null;
}

function applyPattern(pattern) {
    if (!pickerDate.value) return;
    const row = daysRows.value.find((r) => r.calendar_date === pickerDate.value);
    if (row) {
        row.pattern = pattern;
    }
    closePicker();
}

function buildPayload() {
    return {
        year_month: yearMonth.value,
        days: daysRows.value.map(({ calendar_date, pattern }) => ({
            calendar_date,
            pattern: pattern === '' ? null : pattern,
        })),
    };
}

function submit() {
    saving.value = true;
    router.post(route('admin.attendance.company-calendar.update'), buildPayload(), {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
    });
}

function onKeydown(e) {
    if (e.key === 'Escape' && pickerDate.value) {
        closePicker();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>
