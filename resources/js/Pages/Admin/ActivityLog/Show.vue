<template>
    <Head title="ログ詳細" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">ログ詳細</h2>
                <Link
                    :href="route('admin.activity-logs.index')"
                    class="text-indigo-600 hover:text-indigo-900"
                >
                    ← ログ一覧に戻る
                </Link>
            </div>
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

                        <!-- エラーメッセージ -->
                        <div v-if="$page.props.flash?.error" class="mb-6 rounded-md bg-red-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-red-800">{{ $page.props.flash.error }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- ログ情報 -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">ログ情報</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">日時</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(activityLog.created_at) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">スタッフ</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ activityLog.user?.name || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">店舗</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ activityLog.shop?.name || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">処理区分</dt>
                                        <dd class="mt-1">
                                            <span
                                                :class="[
                                                    'px-2 py-1 rounded text-xs font-medium',
                                                    getActionTypeClass(activityLog.action_type)
                                                ]"
                                            >
                                                {{ getActionTypeLabel(activityLog.action_type) }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">リソース種別</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            {{ getResourceTypeLabel(activityLog.resource_type) }}
                                            <span v-if="activityLog.resource_id" class="text-gray-500">
                                                (ID: {{ activityLog.resource_id }})
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">説明</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ activityLog.description || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">IPアドレス</dt>
                                        <dd class="mt-1 text-sm font-mono text-gray-900">{{ activityLog.ip_address || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">URL</dt>
                                        <dd class="mt-1 text-sm text-gray-900 break-all">{{ activityLog.url || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">HTTPメソッド</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ activityLog.method || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">ルート名</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ activityLog.route_name || '-' }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- リクエストデータ -->
                        <div v-if="activityLog.new_values || activityLog.old_values" class="mb-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">リクエストデータ</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div v-if="activityLog.new_values" class="mb-4">
                                    <dt class="text-sm font-medium text-gray-500 mb-2">新規値</dt>
                                    <dd class="text-sm text-gray-900">
                                        <pre class="bg-white p-3 rounded border overflow-x-auto">{{ JSON.stringify(activityLog.new_values, null, 2) }}</pre>
                                    </dd>
                                </div>
                                <div v-if="activityLog.old_values">
                                    <dt class="text-sm font-medium text-gray-500 mb-2">更新前の値</dt>
                                    <dd class="text-sm text-gray-900">
                                        <pre class="bg-white p-3 rounded border overflow-x-auto">{{ JSON.stringify(activityLog.old_values, null, 2) }}</pre>
                                    </dd>
                                </div>
                            </div>
                        </div>

                        <!-- ブロック情報と解除 -->
                        <div v-if="activityLog.action_type === 'login_failed' && activityLog.ip_address" class="mb-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">IPアドレスブロック情報</h3>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div v-if="blockedIp" class="mb-4">
                                    <div class="flex items-center mb-2">
                                        <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <span class="text-lg font-semibold text-red-900">このIPアドレスはブロックされています</span>
                                    </div>
                                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                        <div>
                                            <dt class="text-sm font-medium text-gray-700">失敗回数</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ blockedIp.failure_count }}回</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-700">ブロック日時</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(blockedIp.blocked_at) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-700">最初の失敗日時</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(blockedIp.first_failed_at) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-700">最終失敗日時</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(blockedIp.last_failed_at) }}</dd>
                                        </div>
                                    </dl>
                                    <div class="mt-4">
                                        <button
                                            @click="showUnblockModal = true"
                                            class="px-4 py-2 bg-green-600 text-white rounded-md text-sm hover:bg-green-700 transition-colors duration-200"
                                        >
                                            ブロック解除
                                        </button>
                                    </div>
                                </div>
                                <div v-else>
                                    <p class="text-sm text-gray-600">このIPアドレスは現在ブロックされていません。</p>
                                </div>
                            </div>
                        </div>

                        <!-- ブロック解除モーダル -->
                        <div v-if="showUnblockModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click.self="showUnblockModal = false">
                            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                                <div class="mt-3">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">ブロック解除</h3>
                                    <p class="text-sm text-gray-600 mb-4">
                                        IPアドレス <span class="font-mono font-semibold">{{ activityLog.ip_address }}</span> のブロックを解除しますか？
                                    </p>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">解除理由（任意）</label>
                                        <textarea
                                            v-model="unblockReason"
                                            rows="3"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="解除理由を入力してください"
                                        ></textarea>
                                    </div>
                                    <div class="flex justify-end space-x-3">
                                        <button
                                            @click="showUnblockModal = false"
                                            class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
                                        >
                                            キャンセル
                                        </button>
                                        <button
                                            @click="unblockIp"
                                            :disabled="unblocking"
                                            class="px-4 py-2 bg-green-600 text-white rounded-md text-sm hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <span v-if="unblocking">解除中...</span>
                                            <span v-else>ブロック解除</span>
                                        </button>
                                    </div>
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
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    activityLog: Object,
    blockedIp: Object,
});

const showUnblockModal = ref(false);
const unblockReason = ref('');
const unblocking = ref(false);

const formatDateTime = (datetime) => {
    if (!datetime) return '-';
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP');
};

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

const unblockIp = () => {
    if (!props.activityLog.ip_address) return;
    
    unblocking.value = true;
    router.delete(route('admin.activity-logs.unblock', props.activityLog.ip_address), {
        data: {
            reason: unblockReason.value || '管理者による手動解除',
        },
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            showUnblockModal.value = false;
            unblockReason.value = '';
        },
        onFinish: () => {
            unblocking.value = false;
        },
    });
};
</script>

