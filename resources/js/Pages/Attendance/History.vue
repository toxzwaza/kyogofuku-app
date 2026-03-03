<template>
    <Head title="勤怠履歴" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">勤怠履歴</h2>
                <div class="flex gap-2">
                    <Link
                        :href="route('attendance.provisional.create')"
                        class="text-indigo-600 hover:text-indigo-900"
                    >
                        仮登録
                    </Link>
                    <Link
                        :href="route('dashboard')"
                        class="text-gray-600 hover:text-gray-900"
                    >
                        ダッシュボードへ
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="applyFilters" class="mb-4 flex flex-wrap gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">開始日</label>
                                <input v-model="filters.from" type="date" class="rounded-md border-gray-300 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">終了日</label>
                                <input v-model="filters.to" type="date" class="rounded-md border-gray-300 text-sm" />
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                                    絞り込み
                                </button>
                            </div>
                        </form>

                        <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-md text-sm">
                            {{ $page.props.flash.success }}
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">日付</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">店舗</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">ステータス</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">出勤</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">退勤</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">休憩</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="r in records.data" :key="r.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-2">{{ formatDateJa(r.date) }}</td>
                                        <td class="px-4 py-2">{{ r.shop?.name ?? '-' }}</td>
                                        <td class="px-4 py-2">
                                            <span :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span>
                                        </td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.clock_in_at) }}</td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.clock_out_at) }}</td>
                                        <td class="px-4 py-2">{{ formatBreaks(r.breaks) }}</td>
                                        <td class="px-4 py-2">
                                            <template v-if="r.status === 'draft'">
                                                <Link
                                                    :href="route('attendance.provisional.edit', r.id)"
                                                    class="text-indigo-600 hover:text-indigo-900"
                                                >
                                                    編集
                                                </Link>
                                                <button
                                                    type="button"
                                                    @click="showApplyModal(r)"
                                                    class="text-amber-600 hover:text-amber-900 ml-2"
                                                >
                                                    申請
                                                </button>
                                            </template>
                                        </td>
                                    </tr>
                                    <tr v-if="!records.data || records.data.length === 0">
                                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">データがありません</td>
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
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 申請モーダル -->
        <div v-if="applyTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="applyTarget = null">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">申請（任意：理由を入力）</h3>
                <textarea
                    v-model="applyReason"
                    rows="3"
                    class="w-full rounded-md border-gray-300 text-sm mb-4"
                    placeholder="申請理由（任意）"
                />
                <div class="flex justify-end gap-2">
                    <button @click="applyTarget = null" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md">キャンセル</button>
                    <button @click="submitApply" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">申請する</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatTimeJa, formatDateJa } from '@/utils/dateFormat';

const props = defineProps({
    records: Object,
    filters: Object,
});

const filters = ref({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

const applyTarget = ref(null);
const applyReason = ref('');

function applyFilters() {
    router.get(route('attendance.history'), {
        from: filters.value.from || undefined,
        to: filters.value.to || undefined,
    }, { preserveState: true });
}

function statusLabel(s) {
    const labels = { draft: '未申請', applied: '申請済', approved: '承認済' };
    return labels[s] ?? s;
}

function statusClass(s) {
    const classes = {
        draft: 'px-2 py-0.5 bg-gray-100 text-gray-800 rounded text-xs',
        applied: 'px-2 py-0.5 bg-amber-100 text-amber-800 rounded text-xs',
        approved: 'px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs',
    };
    return classes[s] ?? '';
}

function formatBreaks(breaks) {
    if (!breaks || breaks.length === 0) return '-';
    return breaks.map(b => {
        const s = formatTimeJa(b.start_at);
        const e = b.end_at ? formatTimeJa(b.end_at) : '〜';
        return `${s}-${e}`;
    }).join(', ');
}

function showApplyModal(record) {
    applyTarget.value = record;
    applyReason.value = '';
}

function submitApply() {
    if (!applyTarget.value) return;
    router.post(route('attendance.provisional.apply', applyTarget.value.id), {
        application_reason: applyReason.value,
    });
    applyTarget.value = null;
}
</script>
