<template>
    <Head title="顧客タグ詳細" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客タグ詳細</h2>
                <div class="flex space-x-2">
                    <ActionButton variant="edit" label="編集" :href="route('admin.customer-tags.edit', customerTag.id)" />
                    <ActionButton variant="back" label="顧客タグ一覧に戻る" :href="route('admin.customer-tags.index')" />
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">タグ名</label>
                                <p class="text-sm text-gray-900">{{ customerTag.name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                                <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ customerTag.description || '-' }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">表示色</label>
                                <div class="flex items-center space-x-2">
                                    <span
                                        v-if="customerTag.color"
                                        class="inline-block w-8 h-8 rounded-full border border-gray-300"
                                        :style="{ backgroundColor: customerTag.color }"
                                    ></span>
                                    <span class="text-sm text-gray-900">{{ customerTag.color || '-' }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">状態</label>
                                <span
                                    :class="[
                                        'px-3 py-1 text-sm font-semibold rounded-full',
                                        customerTag.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                    ]"
                                >
                                    {{ customerTag.is_active ? '有効' : '無効' }}
                                </span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">紐づいている顧客数</label>
                                <p class="text-sm text-gray-900">{{ customerTag.customers?.length || 0 }}件</p>
                            </div>

                            <div v-if="customerTag.customers && customerTag.customers.length > 0">
                                <label class="block text-sm font-medium text-gray-700 mb-3">紐づいている顧客</label>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">顧客名</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="customer in customerTag.customers" :key="customer.id">
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ customer.id }}</td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ customer.name }}</td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                                    <Link
                                                        :href="route('admin.customers.show', customer.id)"
                                                        class="text-indigo-600 hover:text-indigo-900"
                                                    >
                                                        詳細を見る
                                                    </Link>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    customerTag: Object,
});
</script>

