<template>
    <Head title="予約詳細" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">予約詳細</h2>
                <div class="flex space-x-4">
                    <Link
                        :href="route('admin.reservations.edit', reservation.id)"
                        class="text-indigo-600 hover:text-indigo-900"
                    >
                        編集
                    </Link>
                    <Link
                        :href="route('admin.events.reservations.index', reservation.event_id)"
                        class="text-indigo-600 hover:text-indigo-900"
                    >
                        ← 予約一覧に戻る
                    </Link>
                </div>
            </div>
        </template>

        <div v-if="$page.props.success" class="mb-4 p-3 rounded bg-green-100 text-green-800 border border-green-200">
            {{ $page.props.success }}
        </div>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- 左側: 予約情報 -->
                    <div class="lg:col-span-2">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold mb-4">予約情報</h3>
                                <div class="space-y-4">
                                    <!-- 共通フィールド -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">お名前</label>
                                        <p class="text-sm text-gray-900">{{ reservation.name }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス</label>
                                        <p class="text-sm text-gray-900">{{ reservation.email }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">電話番号</label>
                                        <p class="text-sm text-gray-900">{{ reservation.phone }}</p>
                                    </div>

                                    <!-- 予約フォーム (reservation) -->
                                    <template v-if="event.form_type === 'reservation'">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">予約日時</label>
                                            <p class="text-sm text-gray-900">{{ reservation.reservation_datetime || '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">フリガナ</label>
                                            <p class="text-sm text-gray-900">{{ reservation.furigana || '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">ご来店会場</label>
                                            <p class="text-sm text-gray-900">{{ reservation.venue ? reservation.venue.name : '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">過去当店のご来店はありますか</label>
                                            <p class="text-sm text-gray-900">{{ reservation.has_visited_before ? 'あり' : 'なし' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">住所</label>
                                            <p class="text-sm text-gray-900">{{ reservation.address || '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">生年月日</label>
                                            <p class="text-sm text-gray-900">{{ reservation.birth_date ? formatDate(reservation.birth_date) : '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">成人式予定年月</label>
                                            <p class="text-sm text-gray-900">{{ reservation.seijin_year ? reservation.seijin_year + '年' : '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">学校名</label>
                                            <p class="text-sm text-gray-900">{{ reservation.school_name || '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">駐車場利用</label>
                                            <p class="text-sm text-gray-900">{{ reservation.parking_usage || '-' }}</p>
                                        </div>

                                        <div v-if="reservation.parking_usage === 'あり'">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">駐車台数</label>
                                            <p class="text-sm text-gray-900">{{ reservation.parking_car_count || '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">検討中のプラン</label>
                                            <p class="text-sm text-gray-900">
                                                {{ reservation.considering_plans && reservation.considering_plans.length > 0 ? reservation.considering_plans.join(', ') : '-' }}
                                            </p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">ご紹介者様お名前</label>
                                            <p class="text-sm text-gray-900">{{ reservation.referred_by_name || '-' }}</p>
                                        </div>
                                    </template>

                                    <!-- 資料請求フォーム (document) -->
                                    <template v-if="event.form_type === 'document'">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">請求方法</label>
                                            <p class="text-sm text-gray-900">{{ reservation.request_method || '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">フリガナ</label>
                                            <p class="text-sm text-gray-900">{{ reservation.furigana || '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">生年月日</label>
                                            <p class="text-sm text-gray-900">{{ reservation.birth_date ? formatDate(reservation.birth_date) : '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">郵便番号</label>
                                            <p class="text-sm text-gray-900">{{ reservation.postal_code || '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">住所</label>
                                            <p class="text-sm text-gray-900">{{ reservation.address || '-' }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">個人情報保護方針への同意</label>
                                            <p class="text-sm text-gray-900">{{ reservation.privacy_agreed ? '同意' : '-' }}</p>
                                        </div>
                                    </template>

                                    <!-- お問い合わせフォーム (contact) -->
                                    <template v-if="event.form_type === 'contact'">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">問い合わせ回答方法</label>
                                            <p class="text-sm text-gray-900">{{ reservation.heard_from || '-' }}</p>
                                        </div>
                                    </template>

                                    <!-- 共通: お問い合わせ内容 -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">お問い合わせ内容</label>
                                        <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ reservation.inquiry_message || '-' }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">登録日時</label>
                                        <p class="text-sm text-gray-900">{{ formatDateTime(reservation.created_at) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 右側: ステータス・メモ機能 -->
                    <div class="lg:col-span-1">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <!-- ステータス登録 -->
                                <div class="mb-6 pb-6 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold mb-4">対応ステータス</h3>
                                    <div v-if="reservation.status" class="mb-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-gray-600">現在のステータス:</span>
                                            <span :class="getStatusBadgeClass(reservation.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                                                {{ reservation.status }}
                                            </span>
                                        </div>
                                        <div v-if="reservation.status_updated_by" class="mt-2 text-xs text-gray-500">
                                            更新者: {{ reservation.status_updated_by.name }}
                                        </div>
                                    </div>
                                    <form @submit.prevent="updateStatus">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">ステータス</label>
                                            <select
                                                v-model="statusForm.status"
                                                required
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="未対応">未対応</option>
                                                <option value="確認中">確認中</option>
                                                <option value="返信待ち">返信待ち</option>
                                                <option value="対応完了済み">対応完了済み</option>
                                            </select>
                                            <div v-if="statusForm.errors.status" class="mt-1 text-sm text-red-600">{{ statusForm.errors.status }}</div>
                                        </div>
                                        <button
                                            type="submit"
                                            :disabled="statusForm.processing"
                                            class="mt-2 w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                        >
                                            {{ statusForm.processing ? '更新中...' : 'ステータスを更新' }}
                                        </button>
                                    </form>
                                </div>

                                <h3 class="text-lg font-semibold mb-4">メモ</h3>
                                
                                <!-- メモ登録フォーム -->
                                <form @submit.prevent="submitNote" class="mb-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">新しいメモ</label>
                                        <textarea
                                            v-model="noteForm.content"
                                            rows="4"
                                            required
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="メモを入力してください"
                                        ></textarea>
                                        <div v-if="noteForm.errors.content" class="mt-1 text-sm text-red-600">{{ noteForm.errors.content }}</div>
                                    </div>
                                    <button
                                        type="submit"
                                        :disabled="noteForm.processing"
                                        class="mt-2 w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                    >
                                        {{ noteForm.processing ? '登録中...' : 'メモを追加' }}
                                    </button>
                                </form>

                                <!-- メモ一覧 -->
                                <div class="space-y-4">
                                    <div
                                        v-for="note in notes"
                                        :key="note.id"
                                        class="border-b border-gray-200 pb-4 last:border-b-0 last:pb-0"
                                    >
                                        <div class="flex justify-between items-start mb-2">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">{{ note.user ? note.user.name : '不明' }}</p>
                                                <p class="text-xs text-gray-500">{{ formatDateTime(note.created_at) }}</p>
                                            </div>
                                            <button
                                                @click="deleteNote(note.id)"
                                                class="text-red-600 hover:text-red-900 text-sm"
                                            >
                                                削除
                                            </button>
                                        </div>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ note.content }}</p>
                                    </div>
                                    <p v-if="notes.length === 0" class="text-sm text-gray-500 text-center py-4">
                                        メモがありません
                                    </p>
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
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    reservation: Object,
    event: Object,
    venues: Array,
    notes: Array,
});

const formatDateTime = (datetime) => {
    if (!datetime) return '-';
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP');
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP');
};

const statusForm = useForm({
    status: props.reservation.status || '未対応',
});

const updateStatus = () => {
    statusForm.patch(route('admin.reservations.status.update', props.reservation.id), {
        onSuccess: (page) => {
            // ステータス更新後、ページをリロードして最新の状態を取得
            router.reload({ only: ['reservation'] });
            // 成功メッセージを表示
            if (page.props.success) {
                alert(page.props.success);
            }
        },
    });
};

const noteForm = useForm({
    content: '',
});

const submitNote = () => {
    noteForm.post(route('admin.reservations.notes.store', props.reservation.id), {
        onSuccess: () => {
            noteForm.reset();
        },
    });
};

const deleteNote = (noteId) => {
    if (confirm('このメモを削除しますか？')) {
        router.delete(route('admin.reservations.notes.destroy', noteId));
    }
};

const getStatusBadgeClass = (status) => {
    const classes = {
        '未対応': 'bg-gray-100 text-gray-800',
        '確認中': 'bg-yellow-100 text-yellow-800',
        '返信待ち': 'bg-blue-100 text-blue-800',
        '対応完了済み': 'bg-green-100 text-green-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

</script>

