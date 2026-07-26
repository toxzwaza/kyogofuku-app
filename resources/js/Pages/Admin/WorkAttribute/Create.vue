<template>
    <Head title="勤務属性の追加" />

    <AdminLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-brand-text leading-tight">勤務属性の追加</h2>
                <Link :href="route('admin.work-attributes.index')" class="text-brand-primary hover:text-brand-primary-hover">← 一覧へ</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <form @submit.prevent="submit">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-brand-text mb-1">名称 <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" required class="w-full rounded-md border-brand-border shadow-sm" />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-brand-text mb-1">並び順</label>
                                <input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-md border-brand-border shadow-sm" />
                                <p class="mt-1 text-xs text-brand-text-muted">小さいほど上に表示されます</p>
                                <div v-if="form.errors.sort_order" class="mt-1 text-sm text-red-600">{{ form.errors.sort_order }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-brand-text mb-1">残業の判定方式 <span class="text-red-500">*</span></label>
                                <select v-model="form.overtime_mode" class="w-full rounded-md border-brand-border shadow-sm">
                                    <option value="base_end">パターン方式（所定終業を超えた分が残業）</option>
                                    <option value="threshold">閾値方式（1日の実働が閾値を超えた分が残業）</option>
                                </select>
                                <p class="mt-1 text-xs text-brand-text-muted">正社員はパターン方式、パート・時短は閾値方式です</p>
                                <div v-if="form.errors.overtime_mode" class="mt-1 text-sm text-red-600">{{ form.errors.overtime_mode }}</div>
                            </div>
                            <div v-if="form.overtime_mode === 'threshold'">
                                <label class="block text-sm font-medium text-brand-text mb-1">残業閾値（分） <span class="text-red-500">*</span></label>
                                <input v-model.number="form.overtime_threshold_minutes" type="number" min="1" max="1440" class="w-full rounded-md border-brand-border shadow-sm" />
                                <p class="mt-1 text-xs text-brand-text-muted">例: 480 = 8時間 / 470 = 7時間50分<span v-if="thresholdHint">（{{ thresholdHint }}）</span></p>
                                <div v-if="form.errors.overtime_threshold_minutes" class="mt-1 text-sm text-red-600">{{ form.errors.overtime_threshold_minutes }}</div>
                            </div>
                        </div>
                        <div class="mt-6 flex gap-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 bg-brand-primary text-white rounded-md text-sm hover:bg-brand-primary-hover disabled:opacity-50"
                            >
                                登録してパターン設定へ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm({
    name: '',
    sort_order: 0,
    overtime_mode: 'base_end',
    overtime_threshold_minutes: null,
});

const thresholdHint = computed(() => {
    const m = form.overtime_threshold_minutes;
    if (!m || m <= 0) return '';
    const h = Math.floor(m / 60);
    const min = m % 60;
    return min === 0 ? `${h}時間` : `${h}時間${min}分`;
});

function submit() {
    form.transform((data) => ({
        ...data,
        overtime_threshold_minutes: data.overtime_mode === 'threshold' ? data.overtime_threshold_minutes : null,
    })).post(route('admin.work-attributes.store'));
}
</script>
