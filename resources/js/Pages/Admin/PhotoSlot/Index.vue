<template>
    <Head title="前撮り管理" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">前撮り管理</h2>
                <Link
                    :href="route('admin.photo-slots.create')"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700"
                >
                    新規追加
                </Link>
            </div>
        </template>

        <div v-if="$page.props.success" class="mb-4 p-3 rounded bg-green-100 text-green-800 border border-green-200">
            {{ $page.props.success }}
        </div>

        <div v-if="$page.props.slotErrors && $page.props.slotErrors.length > 0" class="mb-4 p-3 rounded bg-yellow-100 text-yellow-800 border border-yellow-200">
            <ul class="list-disc list-inside">
                <li v-for="(error, index) in $page.props.slotErrors" :key="index">{{ error }}</li>
            </ul>
        </div>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- 日付ごとにグルーピング表示 -->
                        <div v-if="groupedSlots && Object.keys(groupedSlots).length > 0" class="space-y-6">
                            <div
                                v-for="(dateGroup, date) in groupedSlots"
                                :key="date"
                                class="border-b border-gray-200 pb-6 last:border-b-0 last:pb-0"
                            >
                                <h3 class="text-xl font-semibold my-4 text-gray-800">{{ formatDateHeader(date) }}</h3>
                                <div class="space-y-4">
                                    <div
                                        v-for="slot in dateGroup"
                                        :key="slot.id"
                                        class="border-2 border-gray-300 rounded-lg overflow-hidden"
                                    >
                                        <!-- 時間枠ヘッダー -->
                                        <div class="bg-gray-100 px-4 py-3 border-b border-gray-200">
                                            <div class="flex justify-between items-center">
                                                <div class="flex items-center space-x-4">
                                                    <span class="text-lg font-semibold text-gray-900">{{ formatTime(slot.shoot_time) }}</span>
                                                    <span class="text-sm text-gray-600">
                                                        スタジオ: {{ slot.studio?.name || '-' }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <button
                                                        v-if="slot.customer"
                                                        @click="openEditModal(slot)"
                                                        class="px-3 py-1 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700"
                                                    >
                                                        編集
                                                    </button>
                                                    <button
                                                        v-else
                                                        @click="confirmDelete(slot)"
                                                        class="px-3 py-1 bg-red-600 text-white text-sm rounded-md hover:bg-red-700"
                                                    >
                                                        削除
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 詳細情報 -->
                                        <div class="p-4 bg-white">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div>
                                                    <span class="text-gray-500 font-medium">ID:</span>
                                                    <span class="ml-2 text-gray-900">{{ slot.id }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500 font-medium">顧客:</span>
                                                    <span class="ml-2 text-gray-900">
                                                        <template v-if="slot.customer">
                                                            {{ slot.customer.name }}<span v-if="slot.customer.name_kana && slot.customer.name_kana.trim()">（{{ slot.customer.name_kana }}）</span>
                                                        </template>
                                                        <template v-else>
                                                            未予約
                                                        </template>
                                                    </span>
                                                </div>
                                                <div v-if="slot.assignment_label">
                                                    <span class="text-gray-500 font-medium">担当者用メモラベル:</span>
                                                    <span class="ml-2 text-gray-900">{{ slot.assignment_label }}</span>
                                                </div>
                                                <div v-if="slot.shops && slot.shops.length > 0">
                                                    <span class="text-gray-500 font-medium">担当店舗:</span>
                                                    <span class="ml-2 text-gray-900">
                                                        <span v-for="(shop, index) in slot.shops" :key="shop.id">
                                                            {{ shop.name }}<span v-if="index < slot.shops.length - 1">, </span>
                                                        </span>
                                                    </span>
                                                </div>
                                                <div v-if="slot.user">
                                                    <span class="text-gray-500 font-medium">担当者:</span>
                                                    <span class="ml-2 text-gray-900">{{ slot.user.name }}</span>
                                                </div>
                                                <div v-if="slot.plan">
                                                    <span class="text-gray-500 font-medium">プラン:</span>
                                                    <span class="ml-2 text-gray-900">{{ slot.plan.name }}</span>
                                                </div>
                                                <div v-if="slot.remarks" class="md:col-span-2">
                                                    <span class="text-gray-500 font-medium">備考:</span>
                                                    <span class="ml-2 text-gray-900">{{ slot.remarks }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-gray-500">
                            前撮り枠が登録されていません
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 編集モーダル -->
        <transition name="modal">
            <div
                v-if="showEditModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="showEditModal = false"
            >
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            前撮り枠編集
                        </h3>
                        <button
                            @click="showEditModal = false"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- コンテンツ（スクロール可能） -->
                    <form @submit.prevent="updatePhotoSlot" class="overflow-y-auto flex-1 px-6 py-5">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        担当店舗
                                    </label>
                                    <select
                                        v-model="editForm.shop_ids"
                                        multiple
                                        @change="onEditShopChange"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        size="5"
                                    >
                                        <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                                            {{ shop.name }}
                                        </option>
                                    </select>
                                    <div v-if="editForm.errors.shop_ids" class="mt-1 text-sm text-red-600">{{ editForm.errors.shop_ids }}</div>
                                    <p class="mt-1 text-xs text-gray-500">Ctrlキー（MacではCommandキー）を押しながらクリックで複数選択</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        会場 <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="editForm.selected_studio_id"
                                        required
                                        :disabled="!editForm.shop_id"
                                        @change="onEditStudioChange"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                        <option value="">選択してください</option>
                                        <option 
                                            v-for="studio in availableEditStudios" 
                                            :key="studio.id" 
                                            :value="studio.id"
                                        >
                                            {{ studio.name }}
                                        </option>
                                    </select>
                                    <p v-if="!editForm.shop_id" class="mt-1 text-xs text-gray-500">まず担当店舗を選択してください</p>
                                    <div v-if="editForm.errors.selected_studio_id" class="mt-1 text-sm text-red-600">{{ editForm.errors.selected_studio_id }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        撮影日 <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="editForm.selected_date"
                                        required
                                        :disabled="!editForm.selected_studio_id"
                                        @change="onEditDateChange"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                        <option value="">選択してください</option>
                                        <option 
                                            v-for="date in availableEditDates" 
                                            :key="date" 
                                            :value="date"
                                        >
                                            {{ formatDate(date) }}
                                        </option>
                                    </select>
                                    <p v-if="!editForm.selected_studio_id" class="mt-1 text-xs text-gray-500">まず担当店舗と会場を選択してください</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        撮影時間 <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="editForm.photo_slot_id"
                                        required
                                        :disabled="!editForm.selected_date"
                                        @change="onEditPhotoSlotChange"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                        <option value="">選択してください</option>
                                        <option 
                                            v-for="slot in availableEditTimeSlots" 
                                            :key="slot.id" 
                                            :value="slot.id"
                                        >
                                            {{ formatTime(slot.shoot_time) }}
                                        </option>
                                    </select>
                                    <p v-if="!editForm.selected_date" class="mt-1 text-xs text-gray-500">まず担当店舗、会場、撮影日を選択してください</p>
                                    <div v-if="editForm.errors.photo_slot_id" class="mt-1 text-sm text-red-600">{{ editForm.errors.photo_slot_id }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        担当者用メモラベル
                                    </label>
                                    <select
                                        v-model="editForm.assignment_label"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option :value="null">選択してください</option>
                                        <option value="動員">動員</option>
                                        <option value="岡山店 / F">岡山店 / F</option>
                                        <option value="城東店 / F">城東店 / F</option>
                                        <option value="引継ぎ / F">引継ぎ / F</option>
                                        <option value="EXPO / F">EXPO / F</option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        担当者
                                    </label>
                                    <select
                                        v-model="editForm.user_id"
                                        :disabled="!editForm.shop_ids || editForm.shop_ids.length === 0"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                        <option :value="null">選択してください</option>
                                        <option v-for="user in editShopUsers" :key="user.id" :value="user.id">
                                            {{ user.name }}
                                        </option>
                                    </select>
                                    <p v-if="!editForm.shop_ids || editForm.shop_ids.length === 0" class="mt-1 text-xs text-gray-500">まず担当店舗を選択してください</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        プラン
                                    </label>
                                    <select
                                        v-model="editForm.plan_id"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option :value="null">選択してください</option>
                                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                            {{ plan.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        備考
                                    </label>
                                    <textarea
                                        v-model="editForm.remarks"
                                        rows="4"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- エラーメッセージ表示 -->
                        <div v-if="Object.keys(editForm.errors).length > 0" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="text-sm text-red-800">
                                <p class="font-semibold mb-2">以下のエラーが発生しました：</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li v-for="(error, key) in editForm.errors" :key="key">{{ error }}</li>
                                </ul>
                            </div>
                        </div>

                        <!-- フッター -->
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                            <button
                                type="button"
                                @click="showEditModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                キャンセル
                            </button>
                            <button
                                type="submit"
                                :disabled="editForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="editForm.processing">更新中...</span>
                                <span v-else>更新</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <!-- 削除確認モーダル -->
        <transition name="modal">
            <div
                v-if="showDeleteModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="showDeleteModal = false"
            >
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">削除確認</h3>
                        <p class="text-sm text-gray-600 mb-6">
                            この前撮り枠を削除してもよろしいですか？この操作は取り消せません。
                        </p>
                        <div class="flex justify-end space-x-3">
                            <button
                                @click="showDeleteModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                キャンセル
                            </button>
                            <button
                                @click="deletePhotoSlot"
                                class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700"
                            >
                                削除
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    photoSlots: Array,
    photoStudios: Array,
    shops: Array,
    plans: Array,
    users: Array,
    availablePhotoSlots: Array,
});

const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedSlot = ref(null);
const editShopUsers = ref([]);

const editForm = useForm({
    photo_slot_id: '',
    shop_ids: [],
    selected_studio_id: '',
    selected_date: '',
    assignment_label: null,
    user_id: null,
    plan_id: null,
    remarks: '',
});

// 前撮り枠を日付ごとにグループ化
const groupedSlots = computed(() => {
    if (!props.photoSlots || props.photoSlots.length === 0) return {};
    const groups = {};
    props.photoSlots.forEach(slot => {
        const date = new Date(slot.shoot_date);
        const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        if (!groups[dateKey]) {
            groups[dateKey] = [];
        }
        groups[dateKey].push(slot);
    });
    // 各日付の枠を時間順にソート
    Object.keys(groups).forEach(dateKey => {
        groups[dateKey].sort((a, b) => {
            return a.shoot_time.localeCompare(b.shoot_time);
        });
    });
    return groups;
});

const formatDate = (date) => {
    if (!date) {
        return '-';
    }
    const d = new Date(date);
    return d.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
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

const formatTime = (time) => {
    if (!time) {
        return '-';
    }
    // time形式（HH:mm:ss）から時刻部分のみを取得
    const parts = time.split(':');
    return `${parts[0]}:${parts[1]}`;
};

// 編集モーダルを開く
const openEditModal = (slot) => {
    selectedSlot.value = slot;
    
    // フォームに現在の値を設定
    editForm.photo_slot_id = slot.id;
    editForm.shop_ids = slot.shops ? slot.shops.map(shop => shop.id) : [];
    editForm.selected_studio_id = slot.studio?.id || '';
    editForm.selected_date = slot.shoot_date || '';
    editForm.assignment_label = slot.assignment_label || null;
    editForm.user_id = slot.user?.id || null;
    editForm.plan_id = slot.plan?.id || null;
    editForm.remarks = slot.remarks || '';
    
    // 店舗が選択されている場合はユーザーを取得（最初の店舗を使用）
    if (editForm.shop_ids && editForm.shop_ids.length > 0) {
        loadEditShopUsers(editForm.shop_ids[0]);
    }
    
    showEditModal.value = true;
};

// 利用可能な会場（スタジオ）を取得（担当店舗でフィルタリング）
const availableEditStudios = computed(() => {
    if (!props.availablePhotoSlots || props.availablePhotoSlots.length === 0) {
        return props.photoStudios || [];
    }
    if (!editForm.shop_ids || editForm.shop_ids.length === 0) {
        return [];
    }
    const studios = new Map();
    const shopIds = editForm.shop_ids.map(id => Number(id));
    props.availablePhotoSlots.forEach(slot => {
        if (slot.studio && slot.shops && slot.shops.some(shop => shopIds.includes(shop.id)) && !studios.has(slot.studio.id)) {
            studios.set(slot.studio.id, slot.studio);
        }
    });
    // 現在選択中のスタジオも含める
    if (editForm.selected_studio_id) {
        const currentStudio = props.photoStudios?.find(s => s.id == editForm.selected_studio_id);
        if (currentStudio) {
            studios.set(currentStudio.id, currentStudio);
        }
    }
    return Array.from(studios.values()).sort((a, b) => a.name.localeCompare(b.name));
});

// 選択された会場の利用可能な日付を取得（担当店舗でフィルタリング）
const availableEditDates = computed(() => {
    if (!editForm.selected_studio_id || !editForm.shop_ids || editForm.shop_ids.length === 0 || !props.availablePhotoSlots) {
        return [];
    }
    const dates = new Set();
    const shopIds = editForm.shop_ids.map(id => Number(id));
    props.availablePhotoSlots.forEach(slot => {
        if (slot.studio?.id == editForm.selected_studio_id && 
            slot.shops && slot.shops.some(shop => shopIds.includes(shop.id))) {
            dates.add(slot.shoot_date);
        }
    });
    // 現在選択中の日付も含める
    if (editForm.selected_date) {
        dates.add(editForm.selected_date);
    }
    return Array.from(dates).sort();
});

// 選択された会場と日付の利用可能な時間枠を取得（担当店舗でフィルタリング）
const availableEditTimeSlots = computed(() => {
    if (!editForm.selected_studio_id || !editForm.selected_date || !editForm.shop_ids || editForm.shop_ids.length === 0 || !props.availablePhotoSlots) {
        return [];
    }
    const shopIds = editForm.shop_ids.map(id => Number(id));
    const slots = props.availablePhotoSlots.filter(slot => {
        return slot.studio?.id == editForm.selected_studio_id && 
               slot.shoot_date === editForm.selected_date &&
               slot.shops && slot.shops.some(shop => shopIds.includes(shop.id));
    });
    // 現在選択中のスロットも含める
    if (selectedSlot.value && selectedSlot.value.id) {
        const currentSlot = {
            id: selectedSlot.value.id,
            shoot_time: selectedSlot.value.shoot_time,
        };
        if (!slots.find(s => s.id === currentSlot.id)) {
            slots.push(currentSlot);
        }
    }
    return slots.sort((a, b) => a.shoot_time.localeCompare(b.shoot_time));
});

// 編集フォームの店舗変更時の処理
const onEditShopChange = async () => {
    editForm.selected_studio_id = '';
    editForm.selected_date = '';
    editForm.photo_slot_id = '';
    
    if (editForm.shop_ids && editForm.shop_ids.length > 0) {
        await loadEditShopUsers(editForm.shop_ids[0]);
    } else {
        editShopUsers.value = [];
        editForm.user_id = null;
    }
};

// 編集フォームの会場変更時の処理
const onEditStudioChange = () => {
    editForm.selected_date = '';
    editForm.photo_slot_id = '';
};

// 編集フォームの日付変更時の処理
const onEditDateChange = () => {
    editForm.photo_slot_id = '';
};

// 編集フォームの時間枠変更時の処理
const onEditPhotoSlotChange = () => {
    // 必要に応じて処理を追加
};

// 編集用の店舗ユーザーを取得
const loadEditShopUsers = async (shopId) => {
    if (!shopId) {
        editShopUsers.value = [];
        editForm.user_id = null;
        return;
    }
    try {
        const response = await axios.get(route('admin.schedules.shop-users'), {
            params: { shop_id: shopId }
        });
        editShopUsers.value = response.data;
        if (editForm.user_id && !editShopUsers.value.some(u => u.id === editForm.user_id)) {
            editForm.user_id = null;
        }
    } catch (error) {
        console.error('店舗ユーザーの取得に失敗しました:', error);
        editShopUsers.value = [];
    }
};

// 前撮り枠を更新
const updatePhotoSlot = () => {
    if (!selectedSlot.value) return;
    
    const formData = {
        ...editForm.data(),
        shop_ids: editForm.shop_ids || [],
        assignment_label: editForm.assignment_label || null,
        user_id: editForm.user_id || null,
        plan_id: editForm.plan_id || null,
    };
    
    delete formData.selected_studio_id;
    delete formData.selected_date;
    
    editForm.transform(() => formData).put(route('admin.photo-slots.update', selectedSlot.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
            selectedSlot.value = null;
        },
    });
};

// 削除確認
const confirmDelete = (slot) => {
    selectedSlot.value = slot;
    showDeleteModal.value = true;
};

// 前撮り枠を削除
const deletePhotoSlot = () => {
    if (!selectedSlot.value) return;
    
    router.delete(route('admin.photo-slots.destroy', selectedSlot.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedSlot.value = null;
        },
    });
};
</script>

