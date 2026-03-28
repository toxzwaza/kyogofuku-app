<template>
    <Head title="給与計算シミュレーター" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">給与計算シミュレーター</h2>
                <div class="flex gap-3 text-sm">
                    <Link :href="route('admin.attendance.payroll-settings.edit')" class="text-indigo-600 hover:text-indigo-900">給与計算閾値</Link>
                    <Link :href="route('admin.attendance.index')" class="text-gray-600 hover:text-gray-900">勤怠一覧へ</Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <p class="text-sm text-gray-600">
                    勤務属性マスタの <strong>パターン（A/B/C）× 平日/土日</strong>を参照し、
                    <strong>平日または土日</strong>を選んだうえで、実打刻の<strong>出勤・退勤の時刻範囲を1分刻み</strong>で試算します。
                    <strong>結果が変わる分</strong>（前行との差）を行で強調表示します。退勤固定で出勤を動かす表と、出勤固定で退勤を動かす表の2つです。
                </p>

                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden border border-gray-200">
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-800">登録されているシフト（勤務属性 × パターン × 平日/土日）</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left text-gray-700">
                                <tr>
                                    <th class="px-3 py-2 font-medium">勤務属性</th>
                                    <th class="px-3 py-2 font-medium">パターン</th>
                                    <th class="px-3 py-2 font-medium">平日/土日</th>
                                    <th class="px-3 py-2 font-medium">始業（マスタ）</th>
                                    <th class="px-3 py-2 font-medium">終業（マスタ）</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template v-for="attr in patternMatrix" :key="attr.id">
                                    <tr v-for="(row, idx) in attr.rows" :key="`${attr.id}-${row.pattern}-${row.day_type}`" class="hover:bg-gray-50/80">
                                        <td class="px-3 py-2 text-gray-900">{{ idx === 0 ? attr.name : '' }}</td>
                                        <td class="px-3 py-2">
                                            <span class="font-mono font-semibold">{{ row.pattern }}</span>
                                        </td>
                                        <td class="px-3 py-2">{{ row.day_type_label }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ row.work_start_time ?? '—' }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ row.work_end_time ?? '—' }}</td>
                                    </tr>
                                    <tr v-if="!attr.rows?.length">
                                        <td class="px-3 py-2 text-gray-900">{{ attr.name }}</td>
                                        <td colspan="4" class="px-3 py-2 text-gray-400">パターン行がありません</td>
                                    </tr>
                                </template>
                                <tr v-if="!patternMatrix.length">
                                    <td colspan="5" class="px-3 py-6 text-center text-gray-500">勤務属性がありません</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-6 border border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">試算条件</h3>
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">勤務属性</label>
                                <select v-model="form.work_attribute_id" class="w-full rounded-md border-gray-300 text-sm" required>
                                    <option disabled value="">選択してください</option>
                                    <option v-for="a in patternMatrix" :key="a.id" :value="a.id">{{ a.name }}</option>
                                </select>
                                <p v-if="form.errors.work_attribute_id" class="mt-1 text-xs text-red-600">{{ form.errors.work_attribute_id }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">会社カレンダー相当のパターン</label>
                                <select v-model="form.pattern" class="w-full rounded-md border-gray-300 text-sm" required>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                </select>
                                <p v-if="form.errors.pattern" class="mt-1 text-xs text-red-600">{{ form.errors.pattern }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <span class="block text-xs font-medium text-gray-600 mb-2">平日 / 土日（マスタの day_type）</span>
                                <div class="flex flex-wrap gap-4">
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input v-model="form.day_type" type="radio" value="weekday" class="rounded-full border-gray-300 text-indigo-600" />
                                        平日（weekday）
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input v-model="form.day_type" type="radio" value="weekend" class="rounded-full border-gray-300 text-indigo-600" />
                                        土日（weekend）
                                    </label>
                                </div>
                                <p v-if="form.errors.day_type" class="mt-1 text-xs text-red-600">{{ form.errors.day_type }}</p>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50/50 space-y-3">
                            <h4 class="text-xs font-semibold text-gray-700">① 出勤を1分刻み（退勤は固定）</h4>
                            <p class="text-xs text-gray-500">表1: 実打刻・出勤を範囲内で1分ずつ動かし、退勤は下の「退勤（固定）」を使います。</p>
                            <div class="grid sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">出勤・開始</label>
                                    <input v-model="form.clock_in_from" type="time" step="60" class="w-full rounded-md border-gray-300 text-sm" required />
                                    <p v-if="form.errors.clock_in_from" class="mt-1 text-xs text-red-600">{{ form.errors.clock_in_from }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">出勤・終了</label>
                                    <input v-model="form.clock_in_to" type="time" step="60" class="w-full rounded-md border-gray-300 text-sm" required />
                                    <p v-if="form.errors.clock_in_to" class="mt-1 text-xs text-red-600">{{ form.errors.clock_in_to }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">退勤（固定）</label>
                                    <input v-model="form.clock_out_fixed" type="time" step="60" class="w-full rounded-md border-gray-300 text-sm" required />
                                    <p v-if="form.errors.clock_out_fixed" class="mt-1 text-xs text-red-600">{{ form.errors.clock_out_fixed }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50/50 space-y-3">
                            <h4 class="text-xs font-semibold text-gray-700">② 退勤を1分刻み（出勤は固定）</h4>
                            <p class="text-xs text-gray-500">表2: 実打刻・退勤を範囲内で1分ずつ動かし、出勤は下の「出勤（固定）」を使います。</p>
                            <div class="grid sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">退勤・開始</label>
                                    <input v-model="form.clock_out_from" type="time" step="60" class="w-full rounded-md border-gray-300 text-sm" required />
                                    <p v-if="form.errors.clock_out_from" class="mt-1 text-xs text-red-600">{{ form.errors.clock_out_from }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">退勤・終了</label>
                                    <input v-model="form.clock_out_to" type="time" step="60" class="w-full rounded-md border-gray-300 text-sm" required />
                                    <p v-if="form.errors.clock_out_to" class="mt-1 text-xs text-red-600">{{ form.errors.clock_out_to }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">出勤（固定）</label>
                                    <input v-model="form.clock_in_fixed" type="time" step="60" class="w-full rounded-md border-gray-300 text-sm" required />
                                    <p v-if="form.errors.clock_in_fixed" class="mt-1 text-xs text-red-600">{{ form.errors.clock_in_fixed }}</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <span class="block text-xs font-medium text-gray-600 mb-2">休憩（任意・シフト終了後にかかった分が残業から控除）</span>
                            <div v-for="(b, i) in form.breaks" :key="i" class="flex flex-wrap gap-2 items-center mb-2">
                                <input v-model="b.start" type="time" step="60" class="rounded-md border-gray-300 text-sm" />
                                <span class="text-gray-400">〜</span>
                                <input v-model="b.end" type="time" step="60" class="rounded-md border-gray-300 text-sm" />
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50"
                        >
                            試算する
                        </button>
                    </form>
                </div>

                <div v-if="lastResult" class="space-y-6">
                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-emerald-900 mb-3">試算サマリ（固定出勤・固定退勤の1ケース）</h3>
                        <p class="text-xs text-gray-600 mb-3">
                            表の見やすさ用に、「出勤（固定）」「退勤（固定）」の組み合わせ1件の解決結果です。マスタは
                            <strong>{{ dayTypeJa(lastResult.computed_summary?.day_type) }}</strong> を参照しています。
                        </p>
                        <template v-if="lastResult.computed_summary?.resolved">
                            <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="text-gray-600">シフト（解決値）</dt>
                                    <dd class="font-mono tabular-nums text-gray-900">
                                        {{ formatTimeJa(lastResult.computed_summary.base_start_at) }} 〜
                                        {{ formatTimeJa(lastResult.computed_summary.base_end_at) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600">実打刻（サマリ用）</dt>
                                    <dd class="font-mono tabular-nums text-gray-900">
                                        {{ lastResult.input.clock_in_fixed }} 〜 {{ lastResult.input.clock_out_fixed }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600">給与用・出勤（始業丸め後）</dt>
                                    <dd class="font-mono tabular-nums font-semibold text-emerald-900">
                                        {{ formatTimeJa(lastResult.computed_summary.payroll_clock_in_at) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600">残業（休憩控除後の実分 / 丸め後）</dt>
                                    <dd class="font-mono tabular-nums text-gray-900">
                                        {{
                                            lastResult.computed_summary.overtime_minutes_raw != null
                                                ? `${lastResult.computed_summary.overtime_minutes_raw}分`
                                                : '—'
                                        }}
                                        ／
                                        <span class="font-semibold text-emerald-900">{{ formatOvertimeRounded(lastResult.computed_summary) }}</span>
                                    </dd>
                                </div>
                            </dl>
                        </template>
                        <template v-else>
                            <p class="text-red-800 text-sm font-medium">{{ lastResult.computed_summary?.error ?? '解決できませんでした。' }}</p>
                            <p class="mt-2 text-xs text-gray-600">勤務属性・パターン・平日/土日の組み合わせがマスタにない可能性があります。</p>
                        </template>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                        <div class="px-4 py-3 bg-amber-50 border-b border-amber-100">
                            <h3 class="text-sm font-semibold text-gray-900">表1: 出勤を1分刻み（退勤 {{ lastResult.input.clock_out_fixed }} 固定）</h3>
                            <p class="text-xs text-gray-600 mt-1">
                                <span class="inline-block w-3 h-3 rounded-sm bg-amber-200 align-middle mr-1" aria-hidden="true" />
                                琥珀色の行は、直前の分と比べて<strong>給与出勤または残業のいずれかが変化</strong>した分です。
                            </p>
                        </div>
                        <div class="overflow-x-auto max-h-[28rem] overflow-y-auto">
                            <table class="min-w-full text-xs sm:text-sm">
                                <thead class="bg-gray-100 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-2 py-2 text-left font-medium text-gray-700">実打刻・出勤</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-700">給与出勤</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-700">残業実分</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-700">残業丸め</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr
                                        v-for="(row, idx) in lastResult.sweep_clock_in"
                                        :key="'in-' + idx"
                                        :class="row.transition ? 'bg-amber-100/90 font-medium' : idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/60'"
                                    >
                                        <td class="px-2 py-1 font-mono tabular-nums">{{ row.vary_time }}</td>
                                        <td class="px-2 py-1 font-mono tabular-nums">
                                            {{ row.resolved ? formatTimeJa(row.payroll_clock_in_at) : '—' }}
                                        </td>
                                        <td class="px-2 py-1 font-mono tabular-nums">
                                            {{ row.resolved && row.overtime_minutes_raw != null ? row.overtime_minutes_raw + '分' : '—' }}
                                        </td>
                                        <td class="px-2 py-1 font-mono tabular-nums">
                                            {{ row.resolved && row.overtime_minutes_rounded != null ? row.overtime_minutes_rounded + '分' : '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                        <div class="px-4 py-3 bg-amber-50 border-b border-amber-100">
                            <h3 class="text-sm font-semibold text-gray-900">表2: 退勤を1分刻み（出勤 {{ lastResult.input.clock_in_fixed }} 固定）</h3>
                            <p class="text-xs text-gray-600 mt-1">琥珀色の行は結果が切り替わった分です。</p>
                        </div>
                        <div class="overflow-x-auto max-h-[28rem] overflow-y-auto">
                            <table class="min-w-full text-xs sm:text-sm">
                                <thead class="bg-gray-100 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-2 py-2 text-left font-medium text-gray-700">実打刻・退勤</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-700">給与出勤</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-700">残業実分</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-700">残業丸め</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr
                                        v-for="(row, idx) in lastResult.sweep_clock_out"
                                        :key="'out-' + idx"
                                        :class="row.transition ? 'bg-amber-100/90 font-medium' : idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/60'"
                                    >
                                        <td class="px-2 py-1 font-mono tabular-nums">{{ row.vary_time }}</td>
                                        <td class="px-2 py-1 font-mono tabular-nums">
                                            {{ row.resolved ? formatTimeJa(row.payroll_clock_in_at) : '—' }}
                                        </td>
                                        <td class="px-2 py-1 font-mono tabular-nums">
                                            {{ row.resolved && row.overtime_minutes_raw != null ? row.overtime_minutes_raw + '分' : '—' }}
                                        </td>
                                        <td class="px-2 py-1 font-mono tabular-nums">
                                            {{ row.resolved && row.overtime_minutes_rounded != null ? row.overtime_minutes_rounded + '分' : '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">
                        1分刻みは各表最大 1440 行までです。退勤時刻は実打刻のまま残業計算に使われます。閾値は「給与計算閾値設定」に従います。
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { formatTimeJa } from '@/utils/dateFormat';

const props = defineProps({
    patternMatrix: Array,
    lastResult: Object,
});

const form = useForm({
    work_attribute_id: props.patternMatrix?.[0]?.id ?? '',
    day_type: 'weekday',
    pattern: 'A',
    clock_in_from: '08:00',
    clock_in_to: '10:00',
    clock_out_fixed: '18:00',
    clock_out_from: '17:00',
    clock_out_to: '20:00',
    clock_in_fixed: '09:00',
    breaks: [
        { start: '', end: '' },
        { start: '', end: '' },
    ],
});

function submit() {
    form.post(route('admin.attendance.payroll-simulator.simulate'), { preserveScroll: true });
}

function dayTypeJa(dt) {
    if (dt === 'weekend') return '土日';
    return '平日';
}

function formatOvertimeRounded(computed) {
    if (!computed || computed.overtime_minutes_rounded === null || computed.overtime_minutes_rounded === undefined) {
        return '—';
    }
    return `${computed.overtime_minutes_rounded}分`;
}
</script>
