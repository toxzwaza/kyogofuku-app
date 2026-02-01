<template>
    <Head title="制約一覧" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">制約一覧</h2>
                <ActionButton variant="create" label="制約追加" :href="route('admin.constraint-templates.create')" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success" class="mb-4 p-3 rounded bg-green-100 text-green-800 border border-green-200">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.error" class="mb-4 p-3 rounded bg-red-100 text-red-800 border border-red-200">
                    {{ $page.props.flash.error }}
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">制約名</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">対象店舗</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状態</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="template in constraintTemplates.data" :key="template.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ template.id }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ template.name }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <span v-if="template.shops && template.shops.length > 0">
                                                <span v-for="(shop, index) in template.shops" :key="shop.id">
                                                    {{ shop.name }}<span v-if="index < template.shops.length - 1">, </span>
                                                </span>
                                            </span>
                                            <span v-else class="text-gray-400">未設定</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                :class="[
                                                    'px-2 py-1 text-xs font-semibold rounded-full',
                                                    template.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                                ]"
                                            >
                                                {{ template.is_active ? '有効' : '無効' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <ActionButton variant="edit" label="編集" size="sm" :href="route('admin.constraint-templates.edit', template.id)" />
                                            <ActionButton
                                                v-if="!template.customer_constraints_count"
                                                variant="delete"
                                                label="削除"
                                                size="sm"
                                                @click="deleteTemplate(template.id)"
                                                class="ml-2"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="!constraintTemplates.data?.length" class="text-center py-12 text-gray-500">
                            制約テンプレートがありません。「制約追加」から登録してください。
                        </div>

                        <!-- ページネーション -->
                        <div v-if="constraintTemplates.links && constraintTemplates.links.length > 3" class="mt-4">
                            <div class="flex justify-center">
                                <template v-for="link in constraintTemplates.links" :key="link.label">
                                    <Link
                                        v-if="link.url"
                                        :href="link.url"
                                        :class="[
                                            'px-4 py-2 mx-1 rounded-md',
                                            link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                        ]"
                                    >
                                        <span v-html="link.label"></span>
                                    </Link>
                                    <span
                                        v-else
                                        :class="[
                                            'px-4 py-2 mx-1 rounded-md opacity-50 cursor-not-allowed',
                                            link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700',
                                        ]"
                                        v-html="link.label"
                                    ></span>
                                </template>
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
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    constraintTemplates: Object,
});

const deleteTemplate = (id) => {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('admin.constraint-templates.destroy', id));
    }
};
</script>
