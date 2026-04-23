<template>
    <Head title="スライドショー作成" />

    <AdminLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-brand-text leading-tight">スライドショー作成</h2>
                <Link
                    :href="route('admin.slideshows.index')"
                    class="text-brand-primary hover:text-brand-primary-hover"
                >
                    ← スライドショー一覧に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-brand-text mb-1">スライドショー名 <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                                        placeholder="例: 新商品紹介スライドショー"
                                    />
                                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                                </div>

                                <div class="flex justify-end space-x-4 pt-4">
                                    <Link
                                        :href="route('admin.slideshows.index')"
                                        class="px-4 py-2 border border-brand-border rounded-md text-brand-text hover:bg-brand-surface-2"
                                    >
                                        キャンセル
                                    </Link>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="px-4 py-2 bg-brand-primary text-white rounded-md hover:bg-brand-primary-hover disabled:bg-gray-400"
                                    >
                                        {{ form.processing ? '作成中...' : '作成' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
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
});

const submit = () => {
    form.post(route('admin.slideshows.store'));
};
</script>




