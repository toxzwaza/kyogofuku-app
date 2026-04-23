<template>
    <Head title="顧客タグ一覧" />

    <AdminLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-brand-text leading-tight">顧客タグ一覧</h2>
                <ActionButton variant="create" label="新規追加" :href="route('admin.customer-tags.create')" />
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

                <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-brand-border">
                                <thead class="bg-brand-surface-2">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">タグ名</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">説明</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">色</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">状態</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-brand-surface divide-y divide-brand-border">
                                    <tr v-for="tag in customerTags.data" :key="tag.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-text">{{ tag.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-text">{{ tag.name }}</td>
                                        <td class="px-6 py-4 text-sm text-brand-text">{{ tag.description || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                v-if="tag.color"
                                                class="inline-block w-6 h-6 rounded-full border border-brand-border"
                                                :style="{ backgroundColor: tag.color }"
                                                :title="tag.color"
                                            ></span>
                                            <span v-else class="text-sm text-brand-text-subtle">-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                :class="[
                                                    'px-2 py-1 text-xs font-semibold rounded-full',
                                                    tag.is_active ? 'bg-green-100 text-green-800' : 'bg-brand-surface-2 text-brand-text'
                                                ]"
                                            >
                                                {{ tag.is_active ? '有効' : '無効' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <ActionButton variant="detail" label="詳細" size="sm" :href="route('admin.customer-tags.show', tag.id)" />
                                                <ActionButton variant="edit" label="編集" size="sm" :href="route('admin.customer-tags.edit', tag.id)" />
                                                <ActionButton variant="delete" label="削除" size="sm" @click="deleteTag(tag.id)" />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ページネーション -->
                        <div v-if="customerTags.links && customerTags.links.length > 3" class="mt-4">
                            <div class="flex justify-center">
                                <template v-for="link in customerTags.links" :key="link.label">
                                    <Link
                                        v-if="link.url"
                                        :href="link.url"
                                        :class="[
                                            'px-4 py-2 mx-1 rounded-md',
                                            link.active ? 'bg-brand-primary text-white' : 'bg-brand-surface text-brand-text hover:bg-brand-surface-2',
                                        ]"
                                    >
                                        <span v-html="link.label"></span>
                                    </Link>
                                    <span
                                        v-else
                                        :class="[
                                            'px-4 py-2 mx-1 rounded-md opacity-50 cursor-not-allowed',
                                            link.active ? 'bg-brand-primary text-white' : 'bg-brand-surface text-brand-text',
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
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    customerTags: Object,
});

const deleteTag = (id) => {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('admin.customer-tags.destroy', id));
    }
};
</script>

