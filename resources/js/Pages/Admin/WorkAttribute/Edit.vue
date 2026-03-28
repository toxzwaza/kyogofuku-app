<template>
    <Head :title="`勤務属性: ${workAttribute.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">勤務属性の編集</h2>
                <Link :href="route('admin.work-attributes.index')" class="text-indigo-600 hover:text-indigo-900">← 一覧へ</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-md text-sm">
                    {{ $page.props.flash.success }}
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <form @submit.prevent="submit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">名称 <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" required class="w-full rounded-md border-gray-300 shadow-sm" />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">並び順</label>
                                <input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-md border-gray-300 shadow-sm" />
                                <div v-if="form.errors.sort_order" class="mt-1 text-sm text-red-600">{{ form.errors.sort_order }}</div>
                            </div>
                        </div>

                        <h3 class="text-sm font-semibold text-gray-800 mb-2">パターン別業務時間（A/B/C × 平日・土日）</h3>
                        <p class="text-xs text-gray-500 mb-3">
                            未入力の組み合わせは、その日のベース勤務として使われません。時刻は「9:00」「09:00」形式で入力してください（24時間制）。
                        </p>
                        <div class="overflow-x-auto border border-gray-200 rounded-md">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left">パターン</th>
                                        <th class="px-3 py-2 text-left">曜日区分</th>
                                        <th class="px-3 py-2 text-left">業務開始</th>
                                        <th class="px-3 py-2 text-left">業務終了</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="(row, idx) in form.pattern_times" :key="idx">
                                        <td class="px-3 py-2 font-medium">{{ row.pattern }}</td>
                                        <td class="px-3 py-2">{{ dayTypeLabel(row.day_type) }}</td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model="row.work_start_time"
                                                type="text"
                                                inputmode="numeric"
                                                placeholder="例: 09:00"
                                                class="rounded-md border-gray-300 text-sm w-28"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model="row.work_end_time"
                                                type="text"
                                                inputmode="numeric"
                                                placeholder="例: 18:00"
                                                class="rounded-md border-gray-300 text-sm w-28"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="form.errors.pattern_times" class="mt-2 text-sm text-red-600">{{ form.errors.pattern_times }}</div>

                        <div class="mt-6 flex gap-2">
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
    workAttribute: Object,
    patternMatrix: Array,
});

function dayTypeLabel(dt) {
    return dt === 'weekend' ? '土日' : '平日';
}

const form = useForm({
    name: props.workAttribute.name,
    sort_order: props.workAttribute.sort_order ?? 0,
    pattern_times: props.patternMatrix.map((r) => ({
        pattern: r.pattern,
        day_type: r.day_type,
        work_start_time: r.work_start_time || '',
        work_end_time: r.work_end_time || '',
    })),
});

function submit() {
    form
        .transform((data) => ({
            name: data.name,
            sort_order: data.sort_order,
            pattern_times: data.pattern_times.map((r) => ({
                pattern: r.pattern,
                day_type: r.day_type,
                work_start_time: r.work_start_time || null,
                work_end_time: r.work_end_time || null,
            })),
        }))
        .put(route('admin.work-attributes.update', props.workAttribute.id), { preserveScroll: true });
}
</script>
