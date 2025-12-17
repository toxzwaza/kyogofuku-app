<template>
    <Head title="スタジオ編集" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">スタジオ編集</h2>
                <ActionButton variant="back" label="スタジオ一覧に戻る" :href="route('admin.photo-studios.index')" />
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
                                        スタジオ名 <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="スタジオ名を入力"
                                    />
                                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.name }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        住所
                                    </label>
                                    <input
                                        v-model="form.address"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="住所を入力"
                                    />
                                    <div v-if="form.errors.address" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.address }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        備考
                                    </label>
                                    <textarea
                                        v-model="form.remarks"
                                        rows="4"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="備考を入力"
                                    ></textarea>
                                    <div v-if="form.errors.remarks" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.remarks }}
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <ActionButton
                                        variant="save"
                                        type="submit"
                                        :disabled="form.processing"
                                    >
                                        {{ form.processing ? '更新中...' : '更新' }}
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

const props = defineProps({
    photoStudio: Object,
});

const form = useForm({
    name: props.photoStudio.name || '',
    address: props.photoStudio.address || '',
    remarks: props.photoStudio.remarks || '',
});

const submit = () => {
    form.put(route('admin.photo-studios.update', props.photoStudio.id));
};
</script>

