<template>
    <Head title="勤務属性マスタ" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">勤務属性マスタ</h2>
                <div class="flex gap-2">
                    <Link
                        :href="route('admin.work-attributes.create')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                    >
                        新規追加
                    </Link>
                    <Link :href="route('admin.attendance.index')" class="text-gray-600 hover:text-gray-900 text-sm">勤怠一覧へ</Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-md text-sm">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.error" class="mb-4 p-3 bg-red-100 text-red-800 rounded-md text-sm">
                    {{ $page.props.flash.error }}
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700">並び</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700">名称</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 w-40">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="wa in workAttributes" :key="wa.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-2">{{ wa.sort_order }}</td>
                                    <td class="px-4 py-2">{{ wa.name }}</td>
                                    <td class="px-4 py-2">
                                        <Link
                                            :href="route('admin.work-attributes.edit', wa.id)"
                                            class="text-indigo-600 hover:text-indigo-800 mr-3"
                                        >
                                            編集
                                        </Link>
                                        <button type="button" class="text-red-600 hover:text-red-800" @click="destroyWa(wa.id)">
                                            削除
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!workAttributes?.length">
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">データがありません</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    workAttributes: Array,
});

function destroyWa(id) {
    if (confirm('この勤務属性を削除しますか？')) {
        router.delete(route('admin.work-attributes.destroy', id));
    }
}
</script>
