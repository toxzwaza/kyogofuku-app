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

const form = useForm({
    name: '',
    sort_order: 0,
});

function submit() {
    form.post(route('admin.work-attributes.store'));
}
</script>
