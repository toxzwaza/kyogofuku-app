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
                <!-- 絞り込みフォーム -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">検索条件</h3>
                            <button
                                @click="resetFilters"
                                class="text-sm text-gray-600 hover:text-gray-800"
                            >
                                リセット
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">店舗</label>
                                <select
                                    v-model="filters.shop_id"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">全て</option>
                                    <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                                        {{ shop.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">スタジオ</label>
                                <select
                                    v-model="filters.studio_id"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">全て</option>
                                    <option v-for="studio in photoStudios" :key="studio.id" :value="studio.id">
                                        {{ studio.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">予約状況</label>
                                <select
                                    v-model="filters.reservation_status"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">全て</option>
                                    <option value="reserved">予約済み</option>
                                    <option value="available">空き</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">日付範囲（開始）</label>
                                <input
                                    v-model="filters.start_date"
                                    type="date"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">日付範囲（終了）</label>
                                <input
                                    v-model="filters.end_date"
                                    type="date"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- 日付ごとにグルーピング表示（アコーディオン） -->
                        <div v-if="filteredGroupedSlots && Object.keys(filteredGroupedSlots).length > 0" class="space-y-2">
                            <div
                                v-for="(dateGroup, date) in filteredGroupedSlots"
                                :key="date"
                                class="border border-gray-300 rounded-lg overflow-hidden"
                            >
                                <!-- 日付ヘッダー（クリック可能） -->
                                <div class="w-full bg-gray-100 hover:bg-gray-200 px-4 py-3 flex items-center justify-between transition-colors">
                                    <button
                                        @click="toggleDate(date)"
                                        class="flex-1 flex items-center space-x-4"  
                                    >
                                        <svg
                                            :class="['w-5 h-5 text-gray-600 transition-transform', expandedDates.has(date) ? 'transform rotate-90' : '']"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                        <h3 class="text-lg font-semibold text-gray-800">{{ formatDateHeader(date) }}</h3>
                                        <span class="text-sm text-gray-600">（{{ dateGroup.length }}件）</span>
                                    </button>
                                    <div class="flex items-center space-x-4">
                                        <div class="flex items-center space-x-2 flex-wrap">
                                            <span
                                                v-for="shop in getShopsForDate(dateGroup)"
                                                :key="shop.id"
                                                class="px-2 py-1 text-xs font-medium rounded-full bg-indigo-100 text-indigo-800"
                                            >
                                                {{ shop.name }}
                                            </span>
                                        </div>
                                        <div class="flex items-center space-x-4 text-sm text-gray-600">
                                            <span>予約済み: {{ getReservedCount(dateGroup) }}件</span>
                                            <span>空き: {{ getAvailableCount(dateGroup) }}件</span>
                                        </div>
                                        <button
                                            @click.stop="openDateEditModal(date, dateGroup)"
                                            class="ml-4 px-3 py-1 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 flex items-center gap-1"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            編集
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- 展開された内容 -->
                                <div v-if="expandedDates.has(date)" class="p-4 bg-white space-y-3">
                                    <template v-for="(slot, index) in dateGroup" :key="slot.id">
                                        <!-- 店舗が切り替わる際の区切り線 -->
                                        <div
                                            v-if="index > 0 && getMinShopId(slot) !== getMinShopId(dateGroup[index - 1])"
                                            class="my-8 border-t-2 border-gray-400"
                                        >
                                        </div>
                                        <!-- 最初の枠または店舗が切り替わる際の店舗名表示 -->
                                        <div
                                            v-if="index === 0 || getMinShopId(slot) !== getMinShopId(dateGroup[index - 1])"
                                            class="mb-2"
                                        >
                                            <div class="text-sm font-semibold text-gray-700">
                                                {{ getShopName(slot) }}
                                            </div>
                                        </div>
                                        <div
                                            class="border border-gray-200 rounded-lg overflow-hidden"
                                        >
                                        <!-- 時間枠ヘッダー -->
                                        <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                                            <div class="flex justify-between items-center">
                                                <div class="flex items-center space-x-4">
                                                    <span class="text-base font-semibold text-gray-900">{{ formatTime(slot.shoot_time) }}</span>
                                                    <span class="text-sm text-gray-600">
                                                        スタジオ: {{ slot.studio?.name || '-' }}
                                                    </span>
                                                    <span v-if="slot.shops && slot.shops.length > 0" class="text-sm text-gray-600">
                                                        店舗: <span v-for="(shop, index) in slot.shops" :key="shop.id">
                                                            {{ shop.name }}<span v-if="index < slot.shops.length - 1">, </span>
                                                        </span>
                                                    </span>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <span
                                                        :class="[
                                                            'px-2 py-1 text-xs rounded-full',
                                                            slot.customer ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                                        ]"
                                                    >
                                                        {{ slot.customer ? '予約済み' : '空き' }}
                                                    </span>
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

                                        <!-- 詳細情報（簡潔版） -->
                                        <div v-if="slot.customer || slot.assignment_label || slot.user || slot.plan || slot.remarks" class="p-3 bg-white">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                                <div v-if="slot.customer">
                                                    <span class="text-gray-500 font-medium">顧客:</span>
                                                    <span class="ml-2 text-gray-900">
                                                        {{ slot.customer.name }}<span v-if="slot.customer.name_kana && slot.customer.name_kana.trim()">（{{ slot.customer.name_kana }}）</span>
                                                    </span>
                                                </div>
                                                <div v-if="slot.assignment_label">
                                                    <span class="text-gray-500 font-medium">ラベル:</span>
                                                    <span class="ml-2 text-gray-900">{{ slot.assignment_label }}</span>
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
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-gray-500">
                            条件に一致する前撮り枠がありません
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
                                        :disabled="!editForm.shop_ids || editForm.shop_ids.length === 0"
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
                                    <p v-if="!editForm.shop_ids || editForm.shop_ids.length === 0" class="mt-1 text-xs text-gray-500">まず担当店舗を選択してください</p>
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

        <!-- 日付グループ編集モーダル -->
        <transition name="modal">
            <div
                v-if="showDateEditModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="showDateEditModal = false"
            >
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            日付グループ編集
                        </h3>
                        <button
                            @click="showDateEditModal = false"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- コンテンツ（スクロール可能） -->
                    <form @submit.prevent="updateDateGroup" class="overflow-y-auto flex-1 px-6 py-5">
                        <div class="space-y-4">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                                <p class="text-sm text-blue-800">
                                    <strong>{{ selectedDateGroupSlots.length }}件</strong>の前撮り枠の日付・担当店舗・スタジオを一括変更します。
                                </p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        撮影日 <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="dateGroupEditForm.shoot_date"
                                        type="date"
                                        required
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                    <div v-if="dateGroupEditForm.errors.shoot_date" class="mt-1 text-sm text-red-600">{{ dateGroupEditForm.errors.shoot_date }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        スタジオ <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="dateGroupEditForm.photo_studio_id"
                                        required
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option value="">選択してください</option>
                                        <option v-for="studio in photoStudios" :key="studio.id" :value="studio.id">
                                            {{ studio.name }}
                                        </option>
                                    </select>
                                    <div v-if="dateGroupEditForm.errors.photo_studio_id" class="mt-1 text-sm text-red-600">{{ dateGroupEditForm.errors.photo_studio_id }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        担当店舗
                                    </label>
                                    <div class="space-y-2 border border-gray-300 rounded-md p-3 bg-white">
                                        <label
                                            v-for="shop in shops"
                                            :key="shop.id"
                                            class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded-md transition-colors"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="shop.id"
                                                v-model="dateGroupEditForm.shop_ids"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span class="text-sm text-gray-700">{{ shop.name }}</span>
                                        </label>
                                        <p v-if="shops.length === 0" class="text-sm text-gray-500">
                                            店舗が登録されていません
                                        </p>
                                    </div>
                                    <div v-if="dateGroupEditForm.errors.shop_ids" class="mt-1 text-sm text-red-600">{{ dateGroupEditForm.errors.shop_ids }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- エラーメッセージ表示 -->
                        <div v-if="Object.keys(dateGroupEditForm.errors).length > 0" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="text-sm text-red-800">
                                <p class="font-semibold mb-2">以下のエラーが発生しました：</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li v-for="(error, key) in dateGroupEditForm.errors" :key="key">{{ error }}</li>
                                </ul>
                            </div>
                        </div>

                        <!-- フッター -->
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                            <button
                                type="button"
                                @click="showDateEditModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                キャンセル
                            </button>
                            <button
                                type="submit"
                                :disabled="dateGroupEditForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="dateGroupEditForm.processing">更新中...</span>
                                <span v-else>更新</span>
                            </button>
                        </div>
                    </form>
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
const showDateEditModal = ref(false);
const selectedSlot = ref(null);
const selectedDateGroupSlots = ref([]);
const selectedDateKey = ref('');
const editShopUsers = ref([]);
const expandedDates = ref(new Set());

// 絞り込み条件
const filters = ref({
    shop_id: '',
    studio_id: '',
    reservation_status: '',
    start_date: '',
    end_date: '',
});

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

// 日付グループ編集フォーム
const dateGroupEditForm = useForm({
    shoot_date: '',
    photo_studio_id: '',
    shop_ids: [],
});

// フィルタリングされた前撮り枠
const filteredSlots = computed(() => {
    if (!props.photoSlots || props.photoSlots.length === 0) return [];
    
    let filtered = [...props.photoSlots];
    
    // 店舗でフィルタリング
    if (filters.value.shop_id) {
        const shopId = Number(filters.value.shop_id);
        filtered = filtered.filter(slot => {
            return slot.shops && slot.shops.some(shop => shop.id === shopId);
        });
    }
    
    // スタジオでフィルタリング
    if (filters.value.studio_id) {
        const studioId = Number(filters.value.studio_id);
        filtered = filtered.filter(slot => slot.studio?.id === studioId);
    }
    
    // 予約状況でフィルタリング
    if (filters.value.reservation_status === 'reserved') {
        filtered = filtered.filter(slot => slot.customer !== null);
    } else if (filters.value.reservation_status === 'available') {
        filtered = filtered.filter(slot => slot.customer === null);
    }
    
    // 日付範囲でフィルタリング
    if (filters.value.start_date) {
        filtered = filtered.filter(slot => slot.shoot_date >= filters.value.start_date);
    }
    if (filters.value.end_date) {
        filtered = filtered.filter(slot => slot.shoot_date <= filters.value.end_date);
    }
    
    return filtered;
});

// フィルタリングされた前撮り枠を日付ごとにグループ化
const filteredGroupedSlots = computed(() => {
    if (!filteredSlots.value || filteredSlots.value.length === 0) return {};
    const groups = {};
    filteredSlots.value.forEach(slot => {
        const date = new Date(slot.shoot_date);
        const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        if (!groups[dateKey]) {
            groups[dateKey] = [];
        }
        groups[dateKey].push(slot);
    });
    // 各日付の枠を店舗順（shop.id昇順）、同じ店舗内では時間順にソート
    Object.keys(groups).forEach(dateKey => {
        groups[dateKey].sort((a, b) => {
            // 最初の店舗IDを取得（店舗が存在しない場合は0）
            const aShopId = a.shops && a.shops.length > 0 ? Math.min(...a.shops.map(shop => shop.id)) : 0;
            const bShopId = b.shops && b.shops.length > 0 ? Math.min(...b.shops.map(shop => shop.id)) : 0;
            
            // 店舗IDで比較
            if (aShopId !== bShopId) {
                return aShopId - bShopId;
            }
            
            // 同じ店舗の場合は時間順
            return a.shoot_time.localeCompare(b.shoot_time);
        });
    });
    return groups;
});

// 日付の展開/折りたたみ
const toggleDate = (date) => {
    if (expandedDates.value.has(date)) {
        expandedDates.value.delete(date);
    } else {
        expandedDates.value.add(date);
    }
};

// 予約済み件数を取得
const getReservedCount = (slots) => {
    return slots.filter(slot => slot.customer !== null).length;
};

// 空き件数を取得
const getAvailableCount = (slots) => {
    return slots.filter(slot => slot.customer === null).length;
};

// 日付グループに紐づく店舗を取得（重複除去）
const getShopsForDate = (slots) => {
    const shopMap = new Map();
    slots.forEach(slot => {
        if (slot.shops && slot.shops.length > 0) {
            slot.shops.forEach(shop => {
                if (!shopMap.has(shop.id)) {
                    shopMap.set(shop.id, shop);
                }
            });
        }
    });
    return Array.from(shopMap.values()).sort((a, b) => a.name.localeCompare(b.name));
};

// スロットの最小店舗IDを取得
const getMinShopId = (slot) => {
    if (!slot.shops || slot.shops.length === 0) return 0;
    return Math.min(...slot.shops.map(shop => shop.id));
};

// スロットの店舗名を取得（複数の場合はすべての店舗名を「・」で区切って表示）
const getShopName = (slot) => {
    if (!slot.shops || slot.shops.length === 0) return '店舗未設定';
    // 店舗IDでソートして店舗名を取得
    const sortedShops = [...slot.shops].sort((a, b) => a.id - b.id);
    return sortedShops.map(shop => shop.name).join('・');
};

// 絞り込み条件をリセット
const resetFilters = () => {
    filters.value = {
        shop_id: '',
        studio_id: '',
        reservation_status: '',
        start_date: '',
        end_date: '',
    };
};

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

// 日付グループ編集モーダルを開く
const openDateEditModal = (date, dateGroup) => {
    selectedDateKey.value = date;
    selectedDateGroupSlots.value = dateGroup;
    
    // フォームに現在の値を設定（最初の枠の値をデフォルトとして使用）
    if (dateGroup && dateGroup.length > 0) {
        const firstSlot = dateGroup[0];
        dateGroupEditForm.shoot_date = firstSlot.shoot_date || '';
        dateGroupEditForm.photo_studio_id = firstSlot.studio?.id || '';
        dateGroupEditForm.shop_ids = firstSlot.shops ? firstSlot.shops.map(shop => shop.id) : [];
    } else {
        dateGroupEditForm.shoot_date = '';
        dateGroupEditForm.photo_studio_id = '';
        dateGroupEditForm.shop_ids = [];
    }
    
    showDateEditModal.value = true;
};

// 日付グループを一括更新
const updateDateGroup = () => {
    if (!selectedDateGroupSlots.value || selectedDateGroupSlots.value.length === 0) return;
    
    const slotIds = selectedDateGroupSlots.value.map(slot => slot.id);
    
    dateGroupEditForm.transform((data) => {
        return {
            ...data,
            slot_ids: slotIds,
        };
    }).put(route('admin.photo-slots.bulk-update'), {
        preserveScroll: true,
        onSuccess: () => {
            showDateEditModal.value = false;
            dateGroupEditForm.reset();
            selectedDateGroupSlots.value = [];
            selectedDateKey.value = '';
        },
    });
};
</script>

