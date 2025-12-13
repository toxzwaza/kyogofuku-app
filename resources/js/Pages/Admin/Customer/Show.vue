<template>
    <Head :title="`顧客詳細 - ${customer.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客詳細</h2>
                <Link
                    :href="route('admin.customers.index')"
                    class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700"
                >
                    一覧に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- 顧客基本情報 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">基本情報</h3>
                            <button
                                @click="openEditCustomerModal"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium"
                            >
                                編集
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">顧客ID</label>
                                <p class="mt-1 text-sm text-gray-900">{{ customer.id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">顧客名</label>
                                <p class="mt-1 text-sm text-gray-900">{{ customer.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">ふりがな</label>
                                <p class="mt-1 text-sm text-gray-900">{{ customer.kana || '-' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">保護者名</label>
                                <p class="mt-1 text-sm text-gray-900">{{ customer.guardian_name || '-' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">生年月日</label>
                                <p class="mt-1 text-sm text-gray-900">{{ customer.birth_date || '-' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">成人年度</label>
                                <p class="mt-1 text-sm text-gray-900">{{ customer.coming_of_age_year || '-' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">成人式エリア</label>
                                <p class="mt-1 text-sm text-gray-900">{{ customer.ceremony_area?.name || '-' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">電話番号</label>
                                <p class="mt-1 text-sm text-gray-900">{{ customer.phone_number || '-' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">郵便番号</label>
                                <p class="mt-1 text-sm text-gray-900">{{ customer.postal_code || '-' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">住所</label>
                                <p class="mt-1 text-sm text-gray-900">{{ customer.address || '-' }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">備考</label>
                                <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ customer.remarks || '-' }}</p>
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
                                @click="showAddPhotoSlotModal = true"
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">備考</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="photoSlot in customer.photoSlots" :key="photoSlot.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ photoSlot.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ photoSlot.studio?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(photoSlot.shoot_date) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatTime(photoSlot.shoot_time) }}</td>
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
                                        前撮り会場 <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="photoSlotForm.photo_studio_id"
                                        required
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option value="">選択してください</option>
                                        <option v-for="studio in photoStudios" :key="studio.id" :value="studio.id">
                                            {{ studio.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        撮影日 <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="photoSlotForm.shoot_date"
                                        type="date"
                                        required
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        撮影開始時刻 <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="photoSlotForm.shoot_time"
                                        type="time"
                                        required
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
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
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
    userShops: Array,
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || null);

// モーダル表示フラグ
const showEditCustomerModal = ref(false);
const showAddContractModal = ref(false);
const showAddPhotoSlotModal = ref(false);
const showPhotoPreviewModal = ref(false);

// 選択中の写真（プレビュー用）
const selectedPhoto = ref(null);

// 店舗に所属するユーザーリスト
const shopUsers = ref([]);

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
    photo_studio_id: '',
    shoot_date: '',
    shoot_time: '',
    remarks: '',
});

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

// 前撮り情報追加
const storePhotoSlot = () => {
    photoSlotForm.post(route('admin.customers.photo-slots.store', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddPhotoSlotModal.value = false;
            photoSlotForm.reset();
        },
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
</script>

<style scoped>
.modal-enter-active, .modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from, .modal-leave-to {
    opacity: 0;
}
</style>

