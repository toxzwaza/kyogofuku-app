<template>
    <Head title="スタジオ一覧" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">スタジオ一覧</h2>
                <ActionButton variant="create" label="新規追加" :href="route('admin.photo-studios.create')" />
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">スタジオ名</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">住所</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">備考</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="studio in photoStudios.data" :key="studio.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ studio.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ studio.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ studio.address || '-' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ studio.remarks || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <ActionButton variant="edit" label="編集" size="sm" :href="route('admin.photo-studios.edit', studio.id)" />
                                                <ActionButton variant="delete" label="削除" size="sm" @click="deleteStudio(studio.id)" />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ページネーション -->
                        <div v-if="photoStudios.links && photoStudios.links.length > 3" class="mt-4">
                            <div class="flex justify-center">
                                <template v-for="link in photoStudios.links" :key="link.label">
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
    photoStudios: Object,
});

const deleteStudio = (id) => {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('admin.photo-studios.destroy', id));
    }
};
</script>

