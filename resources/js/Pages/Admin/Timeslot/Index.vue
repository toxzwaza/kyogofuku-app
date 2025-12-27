<template>
    <Head title="予約枠一覧" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    予約枠一覧 - {{ event.title }}
                </h2>
                <div class="flex space-x-4">
                    <Link
                        :href="route('admin.events.timeslots.create', event.id)"
                        class="group relative inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        新規追加
                    </Link>
                    <Link
                        :href="route('admin.events.index')"
                        class="text-indigo-600 hover:text-indigo-900"
                    >
                        ← イベント一覧に戻る
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
                <div v-if="$page.props.errors?.error" class="mb-6 rounded-md bg-red-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ $page.props.errors.error }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- 会場ごと、その中で日付ごとにグループ化して表示 -->
                        <div v-if="groupedByVenue && Object.keys(groupedByVenue).length > 0" class="space-y-8">
                            <div
                                v-for="(venueGroup, venueKey) in groupedByVenue"
                                :key="venueKey"
                                class="border-b border-gray-300 pb-8 last:border-b-0 last:pb-0"
                            >
                                <h2 class="text-2xl font-bold mb-6 text-indigo-700">{{ getVenueName(venueKey) }}</h2>
                                <div v-for="(dateGroup, date) in venueGroup" :key="date" class="mb-6">
                                    <h3 class="text-xl font-semibold my-4 text-gray-800">{{ formatDateHeader(date) }}</h3>
                                    <div class="space-y-4">
                                        <div
                                            v-for="timeslot in dateGroup"
                                            :key="timeslot.id"
                                            class="border border-gray-200 rounded-lg overflow-hidden"
                                        >
                                            <!-- 時間枠ヘッダー -->
                                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                                <div class="flex justify-between items-center">
                                                    <div class="flex items-center space-x-4">
                                                        <span class="text-lg font-semibold text-gray-900">{{ formatTime(timeslot.start_at) }}</span>
                                                        <span class="text-sm text-gray-600">
                                                            定員: {{ timeslot.capacity }}枠
                                                            <span class="mx-2">|</span>
                                                            予約済み: {{ timeslot.capacity - timeslot.remaining_capacity }}枠
                                                            <span class="mx-2">|</span>
                                                            残り: <span :class="timeslot.remaining_capacity > 0 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'">{{ timeslot.remaining_capacity }}枠</span>
                                                        </span>
                                                        <span class="px-2 py-1 text-xs rounded-full" :class="timeslot.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                                                            {{ timeslot.is_active ? '有効' : '無効' }}
                                                        </span>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <Link
                                                        :href="`${route('admin.events.timeslots.create', event.id)}?duplicate=${timeslot.id}`"
                                                        class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-sm hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200"
                                                        title="複製"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                        </svg>
                                                    </Link>
                                                    <button
                                                        @click="adjustCapacity(timeslot.id, -1)"
                                                        :disabled="timeslot.capacity <= (timeslot.capacity - timeslot.remaining_capacity) || adjustingTimeslotId === timeslot.id"
                                                        class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-orange-600 to-orange-700 rounded-lg shadow-sm hover:from-orange-700 hover:to-orange-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                                                        title="枠を1つ減らす"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        @click="adjustCapacity(timeslot.id, 1)"
                                                        :disabled="adjustingTimeslotId === timeslot.id"
                                                        class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-green-600 to-green-700 rounded-lg shadow-sm hover:from-green-700 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200"
                                                        title="枠を1つ増やす"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        @click="adjustCapacity(timeslot.id, 5)"
                                                        :disabled="adjustingTimeslotId === timeslot.id"
                                                        class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                                        title="枠を5つ増やす"
                                                    >
                                                        +5
                                                    </button>
                                                    <button
                                                        @click="deleteTimeslot(timeslot.id)"
                                                        class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 rounded-lg shadow-sm hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 詳細情報 -->
                                        <div class="p-4 bg-white">
                                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                                                <div>
                                                    <span class="text-gray-500">ID:</span>
                                                    <span class="ml-2 text-gray-900 font-medium">{{ timeslot.id }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">開始日時:</span>
                                                    <span class="ml-2 text-gray-900">{{ formatDateTime(timeslot.start_at) }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">会場:</span>
                                                    <span class="ml-2 text-gray-900">{{ timeslot.venue?.name || '会場未設定' }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">状態:</span>
                                                    <span class="ml-2">
                                                        <span class="px-2 py-1 text-xs rounded-full" :class="timeslot.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                                                            {{ timeslot.is_active ? '有効' : '無効' }}
                                                        </span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-gray-500">
                            予約枠が登録されていません
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    event: Object,
    timeslots: Array,
});

const adjustingTimeslotId = ref(null);

// 予約枠を会場ごと、その中で日付ごとにグループ化
const groupedByVenue = computed(() => {
    if (!props.timeslots || props.timeslots.length === 0) return {};
    
    // まず会場ごとにグループ化
    const venueGroups = {};
    props.timeslots.forEach(timeslot => {
        // venue_idがnullの場合は「会場未設定」として扱う
        const venueKey = timeslot.venue_id || 'no_venue';
        if (!venueGroups[venueKey]) {
            venueGroups[venueKey] = {};
        }
        
        // 日付ごとにグループ化
        const date = new Date(timeslot.start_at);
        const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        if (!venueGroups[venueKey][dateKey]) {
            venueGroups[venueKey][dateKey] = [];
        }
        venueGroups[venueKey][dateKey].push(timeslot);
    });
    
    // 各会場の各日付の枠を時間順にソート
    Object.keys(venueGroups).forEach(venueKey => {
        Object.keys(venueGroups[venueKey]).forEach(dateKey => {
            venueGroups[venueKey][dateKey].sort((a, b) => {
                return new Date(a.start_at) - new Date(b.start_at);
            });
        });
    });
    
    return venueGroups;
});

// 会場名を取得
const getVenueName = (venueKey) => {
    if (venueKey === 'no_venue') {
        return '会場未設定';
    }
    // venueKeyはvenue_idなので、timeslotsから該当するvenueを探す
    const timeslot = props.timeslots.find(t => t.venue_id == venueKey);
    return timeslot?.venue?.name || `会場ID: ${venueKey}`;
};

const formatDateTime = (datetime) => {
    if (!datetime) return '-';
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP');
};

const formatDateHeader = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'long',
    });
};

const formatTime = (datetime) => {
    const date = new Date(datetime);
    return date.toLocaleTimeString('ja-JP', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const deleteTimeslot = (id) => {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('admin.timeslots.destroy', id));
    }
};

const adjustCapacity = async (timeslotId, amount) => {
    if (adjustingTimeslotId.value === timeslotId) return;
    
    adjustingTimeslotId.value = timeslotId;
    
    try {
        const response = await axios.post(route('admin.timeslots.adjust-capacity', timeslotId), {
            amount: amount,
        });
        
        if (response.data.success) {
            // ページをリロードして最新の状態を取得
            router.reload({
                preserveScroll: true,
            });
        } else {
            alert(response.data.message || '枠数の変更に失敗しました。');
            adjustingTimeslotId.value = null;
        }
    } catch (error) {
        if (error.response && error.response.data && error.response.data.message) {
            alert(error.response.data.message);
        } else {
            alert('枠数の変更に失敗しました。');
        }
        adjustingTimeslotId.value = null;
    }
};
</script>

