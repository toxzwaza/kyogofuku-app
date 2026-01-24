<template>
    <Head title="予約枠テンプレート一覧" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">予約枠テンプレート一覧</h2>
                <ActionButton variant="create" label="新規追加" :href="route('admin.timeslot-templates.create')" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- 成功メッセージ -->
                <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ $page.props.flash.success }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="templates && templates.length > 0" class="space-y-6">
                            <div
                                v-for="template in templates"
                                :key="template.id"
                                class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow"
                            >
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">{{ template.name }}</h3>
                                        <p class="text-sm text-gray-500 mt-1">
                                            作成日: {{ formatDate(template.created_at) }}
                                        </p>
                                    </div>
                                    <div class="flex space-x-2">
                                        <ActionButton
                                            variant="edit"
                                            label="編集"
                                            size="sm"
                                            :href="route('admin.timeslot-templates.edit', template.id)"
                                        />
                                        <ActionButton
                                            variant="delete"
                                            label="削除"
                                            size="sm"
                                            @click="deleteTemplate(template.id)"
                                        />
                                    </div>
                                </div>

                                <div v-if="template.slots && template.slots.length > 0" class="mt-4">
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">時間枠（{{ template.slots.length }}件）</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <div
                                            v-for="slot in template.slots"
                                            :key="slot.id"
                                            class="bg-gray-50 rounded-md p-3 border border-gray-200"
                                        >
                                            <div class="text-sm">
                                                <span class="font-medium text-gray-900">
                                                    {{ String(slot.hour).padStart(2, '0') }}:{{ String(slot.minute).padStart(2, '0') }}
                                                </span>
                                                <span class="text-gray-500 ml-2">
                                                    （{{ slot.capacity }}枠）
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-gray-400 mt-4">
                                    時間枠が登録されていません
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-gray-500">
                            テンプレートグループが登録されていません
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
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    templates: Array,
});

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('ja-JP');
};

const deleteTemplate = (id) => {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('admin.timeslot-templates.destroy', id));
    }
};
</script>

