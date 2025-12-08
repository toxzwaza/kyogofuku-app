<template>
    <Head title="ログ管理" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">ログ管理</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- 成功メッセージ -->
                        <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 p-4">
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

                        <!-- ブロックされているIPアドレス -->
                        <div v-if="blockedIps && blockedIps.length > 0" class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-semibold text-red-900 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    ブロックされているIPアドレス
                                </h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-red-200">
                                    <thead class="bg-red-100">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-red-900 uppercase">IPアドレス</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-red-900 uppercase">失敗回数</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-red-900 uppercase">ブロック日時</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-red-900 uppercase">最終失敗日時</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-red-200">
                                        <tr v-for="blockedIp in blockedIps" :key="blockedIp.id">
                                            <td class="px-4 py-3 text-sm font-mono text-gray-900">
                                                {{ blockedIp.ip_address }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-medium">
                                                    {{ blockedIp.failure_count }}回
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                {{ formatDateTime(blockedIp.blocked_at) }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                {{ formatDateTime(blockedIp.last_failed_at) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- フィルタリング -->
                        <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                            <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">スタッフ</label>
                                    <select
                                        v-model="filters.user_id"
                                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">すべて</option>
                                        <option
                                            v-for="user in filterOptions?.users"
                                            :key="user.id"
                                            :value="user.id"
                                        >
                                            {{ user.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">店舗</label>
                                    <select
                                        v-model="filters.shop_id"
                                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">すべて</option>
                                        <option
                                            v-for="shop in filterOptions?.shops"
                                            :key="shop.id"
                                            :value="shop.id"
                                        >
                                            {{ shop.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">処理区分</label>
                                    <select
                                        v-model="filters.action_type"
                                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">すべて</option>
                                        <option
                                            v-for="action in filterOptions?.actionTypes"
                                            :key="action.value"
                                            :value="action.value"
                                        >
                                            {{ action.label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">リソース種別</label>
                                    <select
                                        v-model="filters.resource_type"
                                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">すべて</option>
                                        <option
                                            v-for="resource in filterOptions?.resourceTypes"
                                            :key="resource.value"
                                            :value="resource.value"
                                        >
                                            {{ resource.label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">開始日</label>
                                    <input
                                        v-model="filters.date_from"
                                        type="date"
                                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">終了日</label>
                                    <input
                                        v-model="filters.date_to"
                                        type="date"
                                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>

                                <div class="md:col-span-3 lg:col-span-6 flex justify-end space-x-2">
                                    <button
                                        type="button"
                                        @click="resetFilters"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
                                    >
                                        リセット
                                    </button>
                                    <button
                                        type="submit"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700"
                                    >
                                        フィルター適用
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- ログ一覧 -->
                        <div v-if="activityLogs && activityLogs.data && activityLogs.data.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class=" whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">日時</th>
                                        <th class=" whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">スタッフ</th>
                                        <th class=" whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">店舗</th>
                                        <th class=" whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">処理区分</th>
                                        <th class=" whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">リソース</th>
                                        <th class=" whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">説明</th>
                                        <th class=" whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IPアドレス</th>
                                        <th class=" whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">URL</th>
                                        <th class=" whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="log in activityLogs.data" :key="log.id">
                                        <td class="whitespace-nowrap px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatDateTime(log.created_at) }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ log.user?.name || '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ log.shop?.name || '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 whitespace-nowrap text-sm">
                                            <span
                                                :class="[
                                                    'px-2 py-1 rounded text-xs font-medium',
                                                    getActionTypeClass(log.action_type)
                                                ]"
                                            >
                                                {{ getActionTypeLabel(log.action_type) }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ getResourceTypeLabel(log.resource_type) }}
                                            <span v-if="log.resource_id" class="text-gray-500">
                                                (ID: {{ log.resource_id }})
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                            {{ log.description || '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-mono text-gray-600">
                                            {{ log.ip_address || '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                            {{ log.url }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link
                                                :href="route('admin.activity-logs.show', log.id)"
                                                class="group relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                            >
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                詳細
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- ページネーション -->
                            <div v-if="activityLogs.links && activityLogs.links.length > 3" class="mt-4">
                                <div class="flex justify-center">
                                    <Link
                                        v-for="link in activityLogs.links"
                                        :key="link.label"
                                        :href="link.url"
                                        :class="[
                                            'px-4 py-2 mx-1 rounded-md text-sm',
                                            link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                                        ]"
                                    >
                                        <span v-html="link.label"></span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            ログデータがありません
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    activityLogs: Object,
    filterOptions: Object,
    filters: Object,
    blockedIps: {
        type: Array,
        default: () => [],
    },
});

// フィルタリング
const filters = ref({
    user_id: props.filters?.user_id || '',
    shop_id: props.filters?.shop_id || '',
    action_type: props.filters?.action_type || '',
    resource_type: props.filters?.resource_type || '',
    date_from: props.filters?.date_from || '',
    date_to: props.filters?.date_to || '',
});

const applyFilters = () => {
    router.get(route('admin.activity-logs.index'), filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filters.value = {
        user_id: '',
        shop_id: '',
        action_type: '',
        resource_type: '',
        date_from: '',
        date_to: '',
    };
    router.get(route('admin.activity-logs.index'), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatDateTime = (datetime) => {
    if (!datetime) return '-';
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP');
};


// アクションタイプのラベルを取得
const getActionTypeLabel = (actionType) => {
    const labels = {
        'view': '閲覧',
        'create': '作成',
        'update': '更新',
        'delete': '削除',
        'login': 'ログイン',
        'logout': 'ログアウト',
        'login_failed': 'ログイン失敗',
    };
    return labels[actionType] || actionType;
};

// アクションタイプのクラスを取得
const getActionTypeClass = (actionType) => {
    const classes = {
        'view': 'bg-blue-100 text-blue-800',
        'create': 'bg-green-100 text-green-800',
        'update': 'bg-yellow-100 text-yellow-800',
        'delete': 'bg-red-100 text-red-800',
        'login': 'bg-purple-100 text-purple-800',
        'logout': 'bg-gray-100 text-gray-800',
        'login_failed': 'bg-orange-100 text-orange-800',
    };
    return classes[actionType] || 'bg-gray-100 text-gray-800';
};

// リソースタイプのラベルを取得
const getResourceTypeLabel = (resourceType) => {
    const labels = {
        'Event': 'イベント',
        'EventReservation': '予約',
        'EventImage': 'イベント画像',
        'EventTimeslot': '予約枠',
        'Shop': '店舗',
        'User': 'スタッフ',
        'Venue': '会場',
        'ReservationNote': '予約メモ',
    };
    return labels[resourceType] || resourceType || '-';
};
</script>

