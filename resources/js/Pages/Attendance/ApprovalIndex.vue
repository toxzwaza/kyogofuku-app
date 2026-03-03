<template>
    <Head title="承認依頼" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">承認依頼</h2>
                <Link :href="route('dashboard')" class="text-gray-600 hover:text-gray-900">ダッシュボードへ</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-md text-sm">
                            {{ $page.props.flash.success }}
                        </div>

                        <div v-if="records.data?.length > 0" class="mb-4 flex flex-wrap items-center gap-2">
                            <span class="text-sm text-gray-600">選択中: {{ selectedIds.length }}件</span>
                            <button
                                type="button"
                                :disabled="selectedIds.length === 0"
                                @click="batchApprove"
                                class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                まとめて承認
                            </button>
                            <button
                                type="button"
                                :disabled="selectedIds.length === 0"
                                @click="batchReject"
                                class="px-3 py-1.5 text-sm bg-amber-500 text-white rounded-md hover:bg-amber-600 disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                まとめて差し戻し
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-2 w-10">
                                            <input
                                                ref="selectAllCheckboxRef"
                                                type="checkbox"
                                                :checked="isAllSelected"
                                                @change="toggleSelectAll"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                            />
                                        </th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">日付</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">ユーザー</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">店舗</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">出勤</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">退勤</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">申請理由</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="r in records.data" :key="r.id" class="hover:bg-gray-50">
                                        <td class="px-2 py-2 w-10">
                                            <input
                                                type="checkbox"
                                                :value="r.id"
                                                v-model="selectedIds"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                            />
                                        </td>
                                        <td class="px-4 py-2">{{ formatDateJa(r.date) }}</td>
                                        <td class="px-4 py-2">{{ r.user?.name ?? '-' }}</td>
                                        <td class="px-4 py-2">{{ r.shop?.name ?? '-' }}</td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.clock_in_at) }}</td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.clock_out_at) }}</td>
                                        <td class="px-4 py-2 max-w-xs truncate">{{ r.application_reason ?? '-' }}</td>
                                        <td class="px-4 py-2">
                                            <form :action="route('attendance.approvals.approve', r.id)" method="POST" class="inline" @submit.prevent="approve(r.id)">
                                                <input type="hidden" name="_token" :value="$page.props.auth?.csrf_token ?? ''" />
                                                <button type="submit" class="text-green-600 hover:text-green-900">承認</button>
                                            </form>
                                            <span class="mx-1">|</span>
                                            <form :action="route('attendance.approvals.reject', r.id)" method="POST" class="inline" @submit.prevent="reject(r.id)">
                                                <input type="hidden" name="_token" :value="$page.props.auth?.csrf_token ?? ''" />
                                                <button type="submit" class="text-amber-600 hover:text-amber-900">差し戻し</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr v-if="!records.data || records.data.length === 0">
                                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">承認待ちの申請はありません</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="records.last_page > 1" class="mt-4 flex justify-center gap-2">
                            <Link
                                v-for="link in records.links"
                                :key="link.label"
                                :href="link.url"
                                :class="[
                                    'px-3 py-1 rounded text-sm',
                                    link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                ]"
                            >
                                <span v-html="link.label" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatTimeJa, formatDateJa } from '@/utils/dateFormat';

const props = defineProps({
    records: Object,
});

const selectedIds = ref([]);

const pageIds = computed(() => (props.records?.data ?? []).map((r) => r.id));

const isAllSelected = computed(() => {
    const ids = pageIds.value;
    return ids.length > 0 && ids.every((id) => selectedIds.value.includes(id));
});

const isPartiallySelected = computed(() => {
    const ids = pageIds.value;
    const selected = selectedIds.value.filter((id) => ids.includes(id));
    return selected.length > 0 && selected.length < ids.length;
});

const selectAllCheckboxRef = ref(null);
watch([isPartiallySelected], () => {
    if (selectAllCheckboxRef.value) {
        selectAllCheckboxRef.value.indeterminate = isPartiallySelected.value;
    }
}, { immediate: true });

function toggleSelectAll() {
    const ids = pageIds.value;
    if (isAllSelected.value) {
        selectedIds.value = selectedIds.value.filter((id) => !ids.includes(id));
    } else {
        const merged = [...new Set([...selectedIds.value, ...ids])];
        selectedIds.value = merged;
    }
}

function approve(id) {
    router.post(route('attendance.approvals.approve', id));
}

function reject(id) {
    if (confirm('差し戻しますか？')) {
        router.post(route('attendance.approvals.reject', id));
    }
}

function batchApprove() {
    if (selectedIds.value.length === 0) return;
    router.post(route('attendance.approvals.batch-approve'), { ids: selectedIds.value });
}

function batchReject() {
    if (selectedIds.value.length === 0) return;
    if (!confirm(`選択した ${selectedIds.value.length} 件を差し戻しますか？`)) return;
    router.post(route('attendance.approvals.batch-reject'), { ids: selectedIds.value });
}
</script>
