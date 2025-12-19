<template>
    <Head :title="`顧客詳細 - ${customer.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客詳細</h2>
                <div class="flex items-center space-x-3">
                    <ActionButton variant="delete" label="削除" @click="openDeleteConfirmModal" />
                    <ActionButton variant="back" label="一覧に戻る" :href="route('admin.customers.index')" />
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- 顧客タグ -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">顧客タグ</h3>
                            <button
                                @click="openTagModal"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium"
                            >
                                タグを追加
                            </button>
                        </div>
                        <div v-if="customer.tags && customer.tags.length > 0" class="flex flex-wrap gap-2">
                            <div
                                v-for="tag in customer.tags"
                                :key="tag.id"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                :style="{
                                    backgroundColor: tag.color ? tag.color + '20' : '#f3f4f620',
                                    color: tag.color || '#6b7280',
                                    border: `1px solid ${tag.color || '#e5e7eb'}`
                                }"
                            >
                                <span>{{ tag.name }}</span>
                                <button
                                    @click="removeTag(tag.id)"
                                    class="ml-2 text-gray-500 hover:text-red-600"
                                    title="タグを削除"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-sm text-gray-500">
                            タグが設定されていません
                        </div>
                    </div>
                </div>

                <!-- 顧客基本情報 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                基本情報
                            </h3>
                            <button
                                @click="openEditCustomerModal"
                                class="flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm font-medium shadow-sm transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                編集
                            </button>
                        </div>

                        <!-- 基本情報セクション -->
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                基本情報
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- ID -->
                                <div class="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-lg p-4 border border-indigo-100 md:col-span-2">
                                    <label class="block text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">顧客ID</label>
                                    <p class="text-lg font-bold text-gray-900">{{ customer.id }}</p>
                                </div>
                                <!-- 顧客名、ふりがな -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        顧客名
                                    </label>
                                    <p class="text-lg font-semibold text-gray-900">{{ customer.name }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                        </svg>
                                        ふりがな
                                    </label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.kana || '-' }}</p>
                                </div>
                                <!-- 生年月日、成人年度 -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        生年月日
                                    </label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.birth_date || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        成人年度
                                    </label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.coming_of_age_year || '-' }}</p>
                                </div>
                                <!-- 成人エリア、保護者名 -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        成人式エリア
                                    </label>
                                    <p class="text-base font-semibold text-gray-900">{{ customer.ceremony_area?.name || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        保護者名
                                    </label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.guardian_name || '-' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- 連絡先情報セクション -->
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                連絡先情報
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        電話番号
                                    </label>
                                    <p class="text-base font-semibold text-gray-900">{{ customer.phone_number || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        郵便番号
                                    </label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.postal_code || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        住所
                                    </label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.address || '-' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- システム情報セクション -->
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                システム情報
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        登録日
                                    </label>
                                    <p class="text-base font-semibold text-gray-900">{{ formatDateTime(customer.created_at) }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        最終更新日
                                    </label>
                                    <p class="text-base font-semibold text-gray-900">{{ formatDateTime(customer.updated_at) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- 備考セクション -->
                        <div v-if="customer.remarks" class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                備考
                            </h4>
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <p class="text-sm text-gray-900 whitespace-pre-wrap leading-relaxed">{{ customer.remarks }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 成約情報 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">成約情報</h3>
                            <button
                                @click="openAddContractModal"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium"
                            >
                                成約追加
                            </button>
                        </div>
                        <div v-if="customer.contracts && customer.contracts.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">成約ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">成約日</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">店舗</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">プラン</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">着物種別</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">成約金額</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">安心保証</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">担当スタッフ</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">お仕度会場</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">お仕度日程</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="contract in customer.contracts" :key="contract.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.contract_date }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.shop?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.plan?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.kimono_type }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.total_amount ? `¥${contract.total_amount.toLocaleString()}` : '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 text-xs rounded-full" :class="contract.warranty_flag ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                                                {{ contract.warranty_flag ? 'あり' : 'なし' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.user?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.preparation_venue || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.preparation_date || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            成約情報がありません
                        </div>
                    </div>
                </div>

                <!-- 前撮り情報 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">前撮り情報</h3>
                            <button
                                @click="openAddPhotoSlotModal"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium"
                            >
                                前撮り追加
                            </button>
                        </div>

                        <div v-if="customer.photoSlots && customer.photoSlots.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">前撮り枠ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">前撮り会場</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">撮影日</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">撮影開始時刻</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">担当店舗</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">担当者</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">プラン</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">担当者用メモラベル</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">備考</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="photoSlot in customer.photoSlots" :key="photoSlot.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ photoSlot.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ photoSlot.studio?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(photoSlot.shoot_date) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatTime(photoSlot.shoot_time) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span v-if="photoSlot.shops && photoSlot.shops.length > 0">
                                                <span v-for="(shop, index) in photoSlot.shops" :key="shop.id">
                                                    {{ shop.name }}<span v-if="index < photoSlot.shops.length - 1">, </span>
                                                </span>
                                            </span>
                                            <span v-else>-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ photoSlot.user?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ photoSlot.plan?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ photoSlot.assignment_label || '-' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ photoSlot.remarks || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            前撮り情報がありません
                        </div>
                    </div>
                </div>

                <!-- 顧客写真 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">顧客写真</h3>
                        
                        <!-- 写真追加フォーム -->
                        <form @submit.prevent="storeCustomerPhoto" class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        写真種類 <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="photoForm.photo_type_id"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option value="">選択してください</option>
                                        <option v-for="photoType in photoTypes" :key="photoType.id" :value="photoType.id">
                                            {{ photoType.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        画像ファイル <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="file"
                                        @change="onPhotoFileChange"
                                        accept="image/*"
                                        required
                                        class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                    />
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                    備考
                                </label>
                                <textarea
                                    v-model="photoForm.remarks"
                                    rows="3"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    placeholder="写真に関する備考を入力"
                                ></textarea>
                            </div>
                            <!-- プレビュー画像 -->
                            <div v-if="photoPreview" class="mt-4">
                                <label class="block text-xs font-medium text-gray-700 mb-2">
                                    プレビュー
                                </label>
                                <div class="w-32 h-32 rounded-lg overflow-hidden border border-gray-200 bg-gray-100">
                                    <img
                                        :src="photoPreview"
                                        alt="プレビュー"
                                        class="w-full h-full object-cover"
                                    />
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="photoForm.processing || !photoForm.photo"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span v-if="photoForm.processing">アップロード中...</span>
                                    <span v-else>写真を追加</span>
                                </button>
                            </div>
                        </form>

                        <!-- 写真一覧 -->
                        <div v-if="customer.photos && customer.photos.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <div v-for="photo in customer.photos" :key="photo.id" class="relative group cursor-pointer" @click="openPhotoPreview(photo)">
                                <div class="aspect-square rounded-lg overflow-hidden border border-gray-200 bg-gray-100 hover:border-indigo-500 transition-colors">
                                    <img
                                        :src="getPhotoUrl(photo.file_path)"
                                        :alt="photo.type?.name || '写真'"
                                        class="w-full h-full object-cover"
                                    />
                                </div>
                                <div class="mt-2 text-sm">
                                    <div class="font-medium text-gray-900">{{ photo.type?.name || '-' }}</div>
                                    <div v-if="photo.remarks" class="text-gray-600 text-xs mt-1 line-clamp-2">{{ photo.remarks }}</div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            写真がありません
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 基本情報編集モーダル -->
        <transition name="modal">
            <div
                v-if="showEditCustomerModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="showEditCustomerModal = false"
            >
                <div
                    class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
                >
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            顧客基本情報編集
                        </h3>
                        <button
                            @click="showEditCustomerModal = false"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- コンテンツ（スクロール可能） -->
                    <form @submit.prevent="updateCustomer" class="overflow-y-auto flex-1 px-6 py-5">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        顧客名 <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="customerForm.name"
                                        type="text"
                                        required
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        ふりがな
                                    </label>
                                    <input
                                        v-model="customerForm.kana"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        保護者名
                                    </label>
                                    <input
                                        v-model="customerForm.guardian_name"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        生年月日
                                    </label>
                                    <input
                                        v-model="customerForm.birth_date"
                                        type="date"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        成人年度
                                    </label>
                                    <input
                                        v-model.number="customerForm.coming_of_age_year"
                                        type="number"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        成人式エリア
                                    </label>
                                    <select
                                        v-model="customerForm.ceremony_area_id"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option :value="null">選択してください</option>
                                        <option v-for="area in ceremonyAreas" :key="area.id" :value="area.id">
                                            {{ area.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        電話番号
                                    </label>
                                    <input
                                        v-model="customerForm.phone_number"
                                        type="tel"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        郵便番号
                                    </label>
                                    <input
                                        v-model="customerForm.postal_code"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        住所
                                    </label>
                                    <input
                                        v-model="customerForm.address"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        備考
                                    </label>
                                    <textarea
                                        v-model="customerForm.remarks"
                                        rows="4"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- フッター -->
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                            <button
                                type="button"
                                @click="showEditCustomerModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                キャンセル
                            </button>
                            <button
                                type="submit"
                                :disabled="customerForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="customerForm.processing">保存中...</span>
                                <span v-else>保存</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <!-- 成約情報追加モーダル -->
        <transition name="modal">
            <div
                v-if="showAddContractModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="showAddContractModal = false"
            >
                <div
                    class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
                >
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            成約情報追加
                        </h3>
                        <button
                            @click="showAddContractModal = false"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- コンテンツ（スクロール可能） -->
                    <form @submit.prevent="storeContract" class="overflow-y-auto flex-1 px-6 py-5">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        店舗 <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="contractForm.shop_id"
                                        required
                                        @change="onShopChange"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option value="">選択してください</option>
                                        <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                                            {{ shop.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        担当スタッフ
                                    </label>
                                    <select
                                        v-model="contractForm.user_id"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option :value="null">選択してください</option>
                                        <option v-for="user in shopUsers" :key="user.id" :value="user.id">
                                            {{ user.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        成約日 <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="contractForm.contract_date"
                                        type="date"
                                        required
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        着物種別 <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="contractForm.kimono_type"
                                        required
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option value="">選択してください</option>
                                        <option value="振袖">振袖</option>
                                        <option value="袴">袴</option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        成約金額（税込）
                                    </label>
                                    <input
                                        v-model.number="contractForm.total_amount"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        プラン <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="contractForm.plan_id"
                                        required
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option value="">選択してください</option>
                                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                            {{ plan.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        お仕度会場
                                    </label>
                                    <input
                                        v-model="contractForm.preparation_venue"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        お仕度日程
                                    </label>
                                    <input
                                        v-model="contractForm.preparation_date"
                                        type="date"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="flex items-center cursor-pointer">
                                        <input
                                            v-model="contractForm.warranty_flag"
                                            type="checkbox"
                                            class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">安心保証</span>
                                    </label>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        備考
                                    </label>
                                    <textarea
                                        v-model="contractForm.remarks"
                                        rows="4"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- フッター -->
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                            <button
                                type="button"
                                @click="showAddContractModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                キャンセル
                            </button>
                            <button
                                type="submit"
                                :disabled="contractForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="contractForm.processing">追加中...</span>
                                <span v-else>追加</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <!-- 前撮り情報追加モーダル -->
        <transition name="modal">
            <div
                v-if="showAddPhotoSlotModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="showAddPhotoSlotModal = false"
            >
                <div
                    class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
                >
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            前撮り情報追加
                        </h3>
                        <button
                            @click="showAddPhotoSlotModal = false"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- コンテンツ（スクロール可能） -->
                    <form @submit.prevent="storePhotoSlot" class="overflow-y-auto flex-1 px-6 py-5">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        担当店舗
                                    </label>
                                    <select
                                        v-model="photoSlotForm.shop_id"
                                        @change="onPhotoSlotShopChange"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option value="">選択してください</option>
                                        <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                                            {{ shop.name }}
                                        </option>
                                    </select>
                                    <div v-if="photoSlotForm.errors.shop_id" class="mt-1 text-sm text-red-600">{{ photoSlotForm.errors.shop_id }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        会場 <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="photoSlotForm.selected_studio_id"
                                        required
                                        :disabled="!photoSlotForm.shop_id"
                                        @change="onStudioChange"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                        <option value="">選択してください</option>
                                        <option 
                                            v-for="studio in availableStudios" 
                                            :key="studio.id" 
                                            :value="studio.id"
                                        >
                                            {{ studio.name }}
                                        </option>
                                    </select>
                                    <p v-if="!photoSlotForm.shop_id" class="mt-1 text-xs text-gray-500">まず担当店舗を選択してください</p>
                                    <div v-if="photoSlotForm.errors.selected_studio_id" class="mt-1 text-sm text-red-600">{{ photoSlotForm.errors.selected_studio_id }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        撮影日 <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="photoSlotForm.selected_date"
                                        required
                                        :disabled="!photoSlotForm.selected_studio_id"
                                        @change="onDateChange"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                        <option value="">選択してください</option>
                                        <option 
                                            v-for="date in availableDates" 
                                            :key="date" 
                                            :value="date"
                                        >
                                            {{ formatDate(date) }}
                                        </option>
                                    </select>
                                    <p v-if="!photoSlotForm.selected_studio_id" class="mt-1 text-xs text-gray-500">まず担当店舗と会場を選択してください</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        撮影時間 <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="photoSlotForm.photo_slot_id"
                                        required
                                        :disabled="!photoSlotForm.selected_date"
                                        @change="onPhotoSlotChange"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                        <option value="">選択してください</option>
                                        <option 
                                            v-for="slot in availableTimeSlots" 
                                            :key="slot.id" 
                                            :value="slot.id"
                                        >
                                            {{ formatTime(slot.shoot_time) }}
                                            <span v-if="slot.plan"> - {{ slot.plan.name }}</span>
                                        </option>
                                    </select>
                                    <p v-if="!photoSlotForm.selected_date" class="mt-1 text-xs text-gray-500">まず担当店舗、会場、撮影日を選択してください</p>
                                    <div v-if="photoSlotForm.errors.photo_slot_id" class="mt-1 text-sm text-red-600">{{ photoSlotForm.errors.photo_slot_id }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        担当者用メモラベル
                                    </label>
                                    <select
                                        v-model="photoSlotForm.assignment_label"
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
                                        v-model="photoSlotForm.user_id"
                                        :disabled="!photoSlotForm.shop_id"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                        <option :value="null">選択してください</option>
                                        <option v-for="user in photoSlotShopUsers" :key="user.id" :value="user.id">
                                            {{ user.name }}
                                        </option>
                                    </select>
                                    <p v-if="!photoSlotForm.shop_id" class="mt-1 text-xs text-gray-500">まず担当店舗を選択してください</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        プラン
                                    </label>
                                    <select
                                        v-model="photoSlotForm.plan_id"
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
                                        v-model="photoSlotForm.remarks"
                                        rows="4"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- エラーメッセージ表示 -->
                        <div v-if="Object.keys(photoSlotForm.errors).length > 0" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="text-sm text-red-800">
                                <p class="font-semibold mb-2">以下のエラーが発生しました：</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li v-for="(error, key) in photoSlotForm.errors" :key="key">{{ error }}</li>
                                </ul>
                            </div>
                        </div>

                        <!-- フッター -->
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                            <button
                                type="button"
                                @click="showAddPhotoSlotModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                キャンセル
                            </button>
                            <button
                                type="submit"
                                :disabled="photoSlotForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="photoSlotForm.processing">追加中...</span>
                                <span v-else>追加</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <!-- 写真プレビューモーダル -->
        <transition name="modal">
            <div
                v-if="showPhotoPreviewModal && selectedPhoto"
                class="fixed inset-0 bg-black bg-opacity-75 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="showPhotoPreviewModal = false"
            >
                <div class="relative bg-white rounded-xl shadow-2xl max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-xl font-bold text-gray-900">
                            {{ selectedPhoto.type?.name || '写真' }}
                        </h3>
                        <button
                            @click="showPhotoPreviewModal = false"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- コンテンツ -->
                    <div class="overflow-y-auto flex-1 p-6">
                        <div class="text-center">
                            <img
                                :src="getPhotoUrl(selectedPhoto.file_path)"
                                :alt="selectedPhoto.type?.name || '写真'"
                                class="max-w-full max-h-[70vh] mx-auto object-contain rounded-lg"
                            />
                        </div>
                        <div v-if="selectedPhoto.remarks" class="mt-4 p-4 bg-gray-50 rounded-lg">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">備考</h4>
                            <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ selectedPhoto.remarks }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- 削除確認モーダル -->
        <transition name="modal">
            <div
                v-if="showDeleteConfirmModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="showDeleteConfirmModal = false"
            >
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-red-50 to-pink-50">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            顧客削除の確認
                        </h3>
                        <button
                            @click="showDeleteConfirmModal = false"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-700 mb-4">
                            「<span class="font-semibold">{{ customer.name }}</span>」を削除しますか？
                        </p>
                        <p class="text-sm text-red-600 mb-6">
                            この操作は取り消せません。関連する成約情報、前撮り情報、写真もすべて削除されます。
                        </p>
                        <div class="flex justify-end space-x-3">
                            <button
                                type="button"
                                @click="showDeleteConfirmModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                キャンセル
                            </button>
                            <button
                                @click="deleteCustomer"
                                :disabled="deleteForm.processing"
                                class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="deleteForm.processing">削除中...</span>
                                <span v-else>削除する</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- タグ追加モーダル -->
        <transition name="modal">
            <div
                v-if="showTagModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="showTagModal = false"
            >
                <div
                    class="relative bg-white rounded-xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col"
                >
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            タグを追加
                        </h3>
                        <button
                            @click="showTagModal = false"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- コンテンツ（スクロール可能） -->
                    <form @submit.prevent="attachTag" class="overflow-y-auto flex-1 px-6 py-5">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    タグを選択 <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="tagForm.customer_tag_id"
                                    required
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option :value="null">選択してください</option>
                                    <option
                                        v-for="tag in availableTags"
                                        :key="tag.id"
                                        :value="tag.id"
                                    >
                                        {{ tag.name }}
                                    </option>
                                </select>
                                <div v-if="tagForm.errors.customer_tag_id" class="mt-1 text-sm text-red-600">
                                    {{ tagForm.errors.customer_tag_id }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    備考（タグ付与理由など）
                                </label>
                                <textarea
                                    v-model="tagForm.note"
                                    rows="3"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="タグを付与した理由や補足情報を入力"
                                ></textarea>
                                <div v-if="tagForm.errors.note" class="mt-1 text-sm text-red-600">
                                    {{ tagForm.errors.note }}
                                </div>
                            </div>
                        </div>

                        <!-- フッター -->
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                            <button
                                type="button"
                                @click="showTagModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                キャンセル
                            </button>
                            <button
                                type="submit"
                                :disabled="tagForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="tagForm.processing">追加中...</span>
                                <span v-else>追加</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    customer: Object,
    ceremonyAreas: Array,
    shops: Array,
    plans: Array,
    users: Array,
    photoStudios: Array,
    photoTypes: Array,
    availablePhotoSlots: Array,
    userShops: Array,
    customerTags: Array,
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || null);

// モーダル表示フラグ
const showEditCustomerModal = ref(false);
const showAddContractModal = ref(false);
const showAddPhotoSlotModal = ref(false);
const showPhotoPreviewModal = ref(false);
const showDeleteConfirmModal = ref(false);
const showTagModal = ref(false);

// 選択中の写真（プレビュー用）
const selectedPhoto = ref(null);

// 店舗に所属するユーザーリスト（成約用）
const shopUsers = ref([]);

// 前撮り追加用の店舗ユーザーリスト
const photoSlotShopUsers = ref([]);

// 写真プレビュー用URL
const photoPreview = ref(null);

// 顧客編集フォーム
const customerForm = useForm({
    name: props.customer.name || '',
    kana: props.customer.kana || '',
    guardian_name: props.customer.guardian_name || '',
    birth_date: props.customer.birth_date || '',
    coming_of_age_year: props.customer.coming_of_age_year || null,
    ceremony_area_id: props.customer.ceremony_area_id || null,
    phone_number: props.customer.phone_number || '',
    postal_code: props.customer.postal_code || '',
    address: props.customer.address || '',
    remarks: props.customer.remarks || '',
});

// 成約追加フォーム
const contractForm = useForm({
    shop_id: '',
    plan_id: '',
    contract_date: '',
    kimono_type: '',
    warranty_flag: false,
    total_amount: null,
    preparation_venue: '',
    preparation_date: '',
    user_id: null,
    remarks: '',
});

// 店舗に所属するユーザーを取得
const loadShopUsers = async (shopId) => {
    if (!shopId) {
        shopUsers.value = [];
        contractForm.user_id = null;
        return;
    }
    
    try {
        const response = await axios.get(route('admin.schedules.shop-users'), {
            params: { shop_id: shopId }
        });
        shopUsers.value = response.data;
        
        // 選択されたユーザーが新しいリストに含まれていない場合はクリア
        if (contractForm.user_id && !shopUsers.value.some(u => u.id === contractForm.user_id)) {
            contractForm.user_id = null;
        }
    } catch (error) {
        console.error('店舗ユーザーの取得に失敗しました:', error);
        shopUsers.value = [];
    }
};

// 店舗変更時の処理
const onShopChange = async () => {
    await loadShopUsers(contractForm.shop_id);
};

// 成約追加モーダルを開く
const openAddContractModal = async () => {
    // ログインユーザーの所属店舗をデフォルトで設定
    if (props.userShops && props.userShops.length > 0) {
        contractForm.shop_id = props.userShops[0].id;
        await loadShopUsers(props.userShops[0].id);
        // ログインユーザーをデフォルトで設定
        if (currentUser.value) {
            const userInShop = shopUsers.value.find(u => u.id === currentUser.value.id);
            if (userInShop) {
                contractForm.user_id = currentUser.value.id;
            }
        }
    }
    showAddContractModal.value = true;
};

// 前撮り追加フォーム
const photoSlotForm = useForm({
    shop_id: '',
    selected_studio_id: '',
    selected_date: '',
    photo_slot_id: '',
    assignment_label: null,
    user_id: null,
    plan_id: null,
    remarks: '',
});

// 利用可能な会場（スタジオ）を取得（担当店舗でフィルタリング）
const availableStudios = computed(() => {
    if (!props.availablePhotoSlots || props.availablePhotoSlots.length === 0) {
        return [];
    }
    if (!photoSlotForm.shop_id) {
        return [];
    }
    const studios = new Map();
    const shopId = Number(photoSlotForm.shop_id);
    props.availablePhotoSlots.forEach(slot => {
        if (slot.studio && slot.shops && slot.shops.some(shop => shop.id === shopId) && !studios.has(slot.studio.id)) {
            studios.set(slot.studio.id, slot.studio);
        }
    });
    return Array.from(studios.values()).sort((a, b) => a.name.localeCompare(b.name));
});

// 選択された会場の利用可能な日付を取得（担当店舗でフィルタリング）
const availableDates = computed(() => {
    if (!photoSlotForm.selected_studio_id || !photoSlotForm.shop_id || !props.availablePhotoSlots) {
        return [];
    }
    const dates = new Set();
    const shopId = Number(photoSlotForm.shop_id);
    props.availablePhotoSlots.forEach(slot => {
        if (slot.studio?.id == photoSlotForm.selected_studio_id && 
            slot.shops && slot.shops.some(shop => shop.id === shopId)) {
            dates.add(slot.shoot_date);
        }
    });
    return Array.from(dates).sort();
});

// 選択された会場と日付の利用可能な時間枠を取得（担当店舗でフィルタリング）
const availableTimeSlots = computed(() => {
    if (!photoSlotForm.selected_studio_id || !photoSlotForm.selected_date || !photoSlotForm.shop_id || !props.availablePhotoSlots) {
        return [];
    }
    const shopId = Number(photoSlotForm.shop_id);
    return props.availablePhotoSlots.filter(slot => {
        return slot.studio?.id == photoSlotForm.selected_studio_id && 
               slot.shoot_date === photoSlotForm.selected_date &&
               slot.shops && slot.shops.some(shop => shop.id === shopId);
    }).sort((a, b) => a.shoot_time.localeCompare(b.shoot_time));
});

// 担当店舗変更時の処理
const onPhotoSlotShopChange = async () => {
    // 会場、日付、時間枠をリセット
    photoSlotForm.selected_studio_id = '';
    photoSlotForm.selected_date = '';
    photoSlotForm.photo_slot_id = '';
    
    // 担当者を取得
    if (photoSlotForm.shop_id) {
        await loadPhotoSlotShopUsers(photoSlotForm.shop_id);
    } else {
        photoSlotShopUsers.value = [];
        photoSlotForm.user_id = null;
    }
};

// 会場選択時の処理
const onStudioChange = () => {
    photoSlotForm.selected_date = '';
    photoSlotForm.photo_slot_id = '';
};

// 日付選択時の処理
const onDateChange = () => {
    photoSlotForm.photo_slot_id = '';
};

// 前撮り枠選択時の処理
const onPhotoSlotChange = () => {
    // 選択された枠の情報を表示（必要に応じて）
};

// 前撮り追加用の店舗ユーザーを取得
const loadPhotoSlotShopUsers = async (shopId) => {
    if (!shopId) {
        photoSlotShopUsers.value = [];
        photoSlotForm.user_id = null;
        return;
    }
    
    try {
        const response = await axios.get(route('admin.schedules.shop-users'), {
            params: { shop_id: shopId }
        });
        photoSlotShopUsers.value = response.data;
        
        // 選択されたユーザーが新しいリストに含まれていない場合はクリア
        if (photoSlotForm.user_id && !photoSlotShopUsers.value.some(u => u.id === photoSlotForm.user_id)) {
            photoSlotForm.user_id = null;
        }
    } catch (error) {
        console.error('店舗ユーザーの取得に失敗しました:', error);
        photoSlotShopUsers.value = [];
    }
};

// 顧客写真追加フォーム
const photoForm = useForm({
    photo_type_id: '',
    photo: null,
    remarks: '',
});

// 写真ファイル選択時の処理
const onPhotoFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        photoForm.photo = file;
        // プレビュー用URLを生成
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        photoPreview.value = null;
    }
};

// 写真プレビューを開く
const openPhotoPreview = (photo) => {
    selectedPhoto.value = photo;
    showPhotoPreviewModal.value = true;
};

// 写真URLを取得
const getPhotoUrl = (filePath) => {
    return `/storage/${filePath}`;
};

// 顧客写真追加
const storeCustomerPhoto = () => {
    if (!photoForm.photo) {
        return;
    }

    photoForm.post(route('admin.customers.photos.store', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            photoForm.reset();
            photoPreview.value = null;
            // ファイル入力もリセット
            const fileInput = document.querySelector('input[type="file"][accept="image/*"]');
            if (fileInput) {
                fileInput.value = '';
            }
        },
    });
};

// 顧客編集モーダルを開く
const openEditCustomerModal = () => {
    customerForm.name = props.customer.name || '';
    customerForm.kana = props.customer.kana || '';
    customerForm.guardian_name = props.customer.guardian_name || '';
    customerForm.birth_date = props.customer.birth_date || '';
    customerForm.coming_of_age_year = props.customer.coming_of_age_year || null;
    customerForm.ceremony_area_id = props.customer.ceremony_area_id || null;
    customerForm.phone_number = props.customer.phone_number || '';
    customerForm.postal_code = props.customer.postal_code || '';
    customerForm.address = props.customer.address || '';
    customerForm.remarks = props.customer.remarks || '';
    showEditCustomerModal.value = true;
};

// 顧客情報更新
const updateCustomer = () => {
    customerForm.put(route('admin.customers.update', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditCustomerModal.value = false;
        },
    });
};

// 成約情報追加
const storeContract = () => {
    contractForm.post(route('admin.customers.contracts.store', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddContractModal.value = false;
            contractForm.reset();
            shopUsers.value = [];
        },
    });
};

// 前撮り追加モーダルを開く
const openAddPhotoSlotModal = async () => {
    // ログインユーザーの所属店舗をデフォルトで設定
    if (props.userShops && props.userShops.length > 0) {
        photoSlotForm.shop_id = props.userShops[0].id;
        await loadPhotoSlotShopUsers(props.userShops[0].id);
    } else {
        photoSlotForm.shop_id = '';
    }
    
    // その他のフィールドをリセット
    photoSlotForm.selected_studio_id = '';
    photoSlotForm.selected_date = '';
    photoSlotForm.photo_slot_id = '';
    photoSlotForm.assignment_label = null;
    photoSlotForm.user_id = null;
    photoSlotForm.plan_id = null;
    photoSlotForm.remarks = '';
    
    showAddPhotoSlotModal.value = true;
};

// 前撮り情報追加
const storePhotoSlot = () => {
    // フォームデータを変換（空文字列をnullに変換、不要なフィールドを削除）
    photoSlotForm.transform((data) => {
        const transformed = { ...data };
        transformed.shop_id = transformed.shop_id || null;
        transformed.assignment_label = transformed.assignment_label || null;
        transformed.user_id = transformed.user_id || null;
        transformed.plan_id = transformed.plan_id || null;
        // selected_studio_idとselected_dateは送信しない（photo_slot_idだけで十分）
        delete transformed.selected_studio_id;
        delete transformed.selected_date;
        return transformed;
    }).post(route('admin.customers.photo-slots.store', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddPhotoSlotModal.value = false;
            photoSlotForm.reset();
            // リセット後、デフォルト店舗を再設定
            if (props.userShops && props.userShops.length > 0) {
                photoSlotForm.shop_id = props.userShops[0].id;
            }
        },
        onError: (errors) => {
            console.error('前撮り情報追加エラー:', errors);
        },
    });
};

// 日付と時刻をフォーマット（例: 2024年1月1日 14:30）
const formatDateTime = (dateTimeString) => {
    if (!dateTimeString) return '-';
    const date = new Date(dateTimeString);
    return date.toLocaleString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// 日付をフォーマット（例: 2024年1月1日）
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

// 時刻をフォーマット（例: 14:30）
const formatTime = (timeString) => {
    if (!timeString) return '-';
    // time型は"HH:MM:SS"形式で来る可能性があるので、最初の5文字（HH:MM）を取得
    if (timeString.length >= 5) {
        return timeString.substring(0, 5);
    }
    return timeString;
};

// 削除フォーム
const deleteForm = useForm({});

// タグ追加フォーム
const tagForm = useForm({
    customer_tag_id: null,
    note: '',
});

// 利用可能なタグ（既に紐づいているタグを除外）
const availableTags = computed(() => {
    if (!props.customerTags) return [];
    const assignedTagIds = props.customer.tags?.map(tag => tag.id) || [];
    return props.customerTags.filter(tag => !assignedTagIds.includes(tag.id));
});

// タグ追加モーダルを開く
const openTagModal = () => {
    tagForm.reset();
    showTagModal.value = true;
};

// タグを追加
const attachTag = () => {
    tagForm.post(route('admin.customers.attach-tag', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            showTagModal.value = false;
            tagForm.reset();
        },
    });
};

// タグを削除
const removeTag = (tagId) => {
    if (confirm('このタグを削除しますか？')) {
        router.delete(route('admin.customers.detach-tag', [props.customer.id, tagId]), {
            preserveScroll: true,
        });
    }
};

// 削除確認モーダルを開く
const openDeleteConfirmModal = () => {
    showDeleteConfirmModal.value = true;
};

// 顧客を削除
const deleteCustomer = () => {
    deleteForm.delete(route('admin.customers.destroy', props.customer.id), {
        onSuccess: () => {
            showDeleteConfirmModal.value = false;
        },
    });
};
</script>

<style scoped>
.modal-enter-active, .modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from, .modal-leave-to {
    opacity: 0;
}
</style>

