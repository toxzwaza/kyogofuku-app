<template>
    <Head title="勤務属性の追加" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">勤務属性の追加</h2>
                <Link :href="route('admin.work-attributes.index')" class="text-indigo-600 hover:text-indigo-900">← 一覧へ</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <form @submit.prevent="submit">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">名称 <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" required class="w-full rounded-md border-gray-300 shadow-sm" />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">並び順</label>
                                <input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-md border-gray-300 shadow-sm" />
                                <p class="mt-1 text-xs text-gray-500">小さいほど上に表示されます</p>
                                <div v-if="form.errors.sort_order" class="mt-1 text-sm text-red-600">{{ form.errors.sort_order }}</div>
                            </div>
                        </div>
                        <div class="mt-6 flex gap-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50"
                            >
                                登録してパターン設定へ
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

const form = useForm({
    name: '',
    sort_order: 0,
});

function submit() {
    form.post(route('admin.work-attributes.store'));
}
</script>
