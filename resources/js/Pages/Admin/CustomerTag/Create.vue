<template>
    <Head title="顧客タグ追加" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客タグ追加</h2>
                <ActionButton variant="back" label="顧客タグ一覧に戻る" :href="route('admin.customer-tags.index')" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        タグ名 <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="タグ名を入力"
                                    />
                                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.name }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        説明
                                    </label>
                                    <textarea
                                        v-model="form.description"
                                        rows="4"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="タグの説明を入力"
                                    ></textarea>
                                    <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.description }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        表示色
                                    </label>
                                    <div class="flex items-center space-x-4">
                                        <input
                                            v-model="form.color"
                                            type="color"
                                            class="h-10 w-20 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-pointer"
                                        />
                                        <input
                                            v-model="form.color"
                                            type="text"
                                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="#000000"
                                            pattern="^#[0-9A-Fa-f]{6}$"
                                        />
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">タグの表示色を設定します（例: #FF5733）</p>
                                    <div v-if="form.errors.color" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.color }}
                                    </div>
                                </div>

                                <div>
                                    <label class="flex items-center">
                                        <input
                                            v-model="form.is_active"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">有効</span>
                                    </label>
                                    <p class="mt-1 text-xs text-gray-500">無効にすると、このタグは使用できなくなります。</p>
                                    <div v-if="form.errors.is_active" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.is_active }}
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <ActionButton
                                        variant="save"
                                        type="submit"
                                        :disabled="form.processing"
                                    >
                                        {{ form.processing ? '保存中...' : '保存' }}
                                    </ActionButton>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    description: '',
    color: '',
    is_active: true,
});

const submit = () => {
    form.post(route('admin.customer-tags.store'));
};
</script>

