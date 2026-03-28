<template>
    <Head title="給与計算閾値設定" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">給与計算閾値設定</h2>
                <div class="flex gap-3 text-sm">
                    <Link :href="route('admin.attendance.payroll-simulator.index')" class="text-indigo-600 hover:text-indigo-900">給与計算シミュレーター</Link>
                    <Link :href="route('admin.attendance.index')" class="text-gray-600 hover:text-gray-900">勤怠一覧へ</Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-md text-sm">
                    {{ $page.props.flash.success }}
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-8">
                    <form @submit.prevent="submit">
                        <section>
                            <h3 class="text-base font-semibold text-gray-900 mb-2">始業（出勤）</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                シフト開始より前の打刻の扱いと、給与用出勤時刻の丸め（単位への最近接）を設定します。端数の切り捨て上限（分）は終業・残業用のみです。
                            </p>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">早出とみなす閾値（分）</label>
                                    <input
                                        v-model.number="form.start_early_threshold_minutes"
                                        type="number"
                                        min="0"
                                        max="720"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm"
                                    />
                                    <p class="mt-1 text-xs text-gray-500">
                                        シフト開始時刻より、この分数<strong>より前</strong>に打刻した場合は<strong>実打刻時刻</strong>を出勤とします（早出として扱う）。それ以降〜シフト開始まではシフト開始にそろえます。
                                        <strong>0</strong> のときは従来どおり、シフト開始前の打刻はすべてシフト開始時刻にそろえます。
                                    </p>
                                    <div v-if="form.errors.start_early_threshold_minutes" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.start_early_threshold_minutes }}
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">始業・丸め単位（分）</label>
                                    <input
                                        v-model.number="form.start_rounding_unit_minutes"
                                        type="number"
                                        min="1"
                                        max="480"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm"
                                    />
                                    <p class="mt-1 text-xs text-gray-500">
                                        上記で決まった出勤時刻を、その日 0:00 からの経過<strong>分</strong>に対して、この単位の<strong>最近接</strong>に丸めます（例: 単位15分・シフト9:00・閾値30分のとき 8:16→8:15、8:25→8:30、8:30→8:30、8:31→9:00）。<strong>1</strong> で丸めなしです。
                                    </p>
                                    <div v-if="form.errors.start_rounding_unit_minutes" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.start_rounding_unit_minutes }}
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="pt-6 border-t border-gray-200">
                            <h3 class="text-base font-semibold text-gray-900 mb-2">終業（残業）</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                シフト終了以降に算出した残業（分）を、以下の単位で丸めます。
                            </p>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">残業・丸め単位（分）</label>
                                    <input
                                        v-model.number="form.overtime_rounding_unit_minutes"
                                        type="number"
                                        min="1"
                                        max="480"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm"
                                    />
                                    <div v-if="form.errors.overtime_rounding_unit_minutes" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.overtime_rounding_unit_minutes }}
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">残業・端数の切り捨て上限（分）</label>
                                    <input
                                        v-model.number="form.overtime_discard_remainder_upto_minutes"
                                        type="number"
                                        min="0"
                                        max="479"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm"
                                    />
                                    <p class="mt-1 text-xs text-gray-500">丸め単位未満の端数のうち、この値以下は捨て、超えると次の単位へ切り上げ（例: 単位30・上限15 → 44分→30分、46分→60分）</p>
                                    <div v-if="form.errors.overtime_discard_remainder_upto_minutes" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.overtime_discard_remainder_upto_minutes }}
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="mt-8">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50"
                            >
                                保存
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    setting: Object,
});

const form = useForm({
    start_early_threshold_minutes: props.setting.start_early_threshold_minutes ?? 0,
    start_rounding_unit_minutes: props.setting.start_rounding_unit_minutes ?? 1,
    overtime_rounding_unit_minutes: props.setting.overtime_rounding_unit_minutes,
    overtime_discard_remainder_upto_minutes: props.setting.overtime_discard_remainder_upto_minutes,
});

function submit() {
    form.put(route('admin.attendance.payroll-settings.update'), { preserveScroll: true });
}
</script>
