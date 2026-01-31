<template>
    <Head title="顧客一覧" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客一覧</h2>
                <ActionButton variant="create" label="顧客追加" @click="showAddCustomerModal = true" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- 検索フォーム -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">検索条件</h3>
                            <button
                                @click="resetSearch"
                                class="text-sm text-gray-600 hover:text-gray-800"
                            >
                                リセット
                            </button>
                        </div>
                        <form @submit.prevent="searchCustomers" class="space-y-4">
                            <!-- 顧客情報での検索 -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">顧客名</label>
                                    <input
                                        v-model="searchForm.name"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        placeholder="顧客名で検索"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">ふりがな</label>
                                    <input
                                        v-model="searchForm.kana"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        placeholder="ふりがなで検索"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">成人式エリア</label>
                                    <select
                                        v-model="searchForm.ceremony_area_id"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option :value="null">全て</option>
                                        <option v-for="area in ceremonyAreas" :key="area.id" :value="area.id">
                                            {{ area.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">電話番号</label>
                                    <input
                                        v-model="searchForm.phone_number"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        placeholder="電話番号で検索"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">登録日（開始）</label>
                                    <input
                                        v-model="searchForm.created_at_from"
                                        type="date"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">登録日（終了）</label>
                                    <input
                                        v-model="searchForm.created_at_to"
                                        type="date"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                            </div>

                            <!-- 成約情報での検索 -->
                            <div class="border-t pt-4">
                                <h4 class="text-sm font-semibold text-gray-700 mb-3">成約情報での検索</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">成約日（開始）</label>
                                        <input
                                            v-model="searchForm.contract_date_from"
                                            type="date"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">成約日（終了）</label>
                                        <input
                                            v-model="searchForm.contract_date_to"
                                            type="date"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">店舗</label>
                                        <select
                                            v-model="searchForm.shop_id"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        >
                                            <option :value="null">全て</option>
                                            <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                                                {{ shop.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">プラン</label>
                                        <select
                                            v-model="searchForm.plan_id"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        >
                                            <option :value="null">全て</option>
                                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                                {{ plan.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">着物種別</label>
                                        <select
                                            v-model="searchForm.kimono_type"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        >
                                            <option :value="null">全て</option>
                                            <option value="振袖">振袖</option>
                                            <option value="袴">袴</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">成約ステータス</label>
                                        <select
                                            v-model="searchForm.contract_status"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        >
                                            <option :value="null">全て</option>
                                            <option value="確定">確定</option>
                                            <option value="保留">保留</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">安心保証</label>
                                        <select
                                            v-model="searchForm.warranty_flag"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        >
                                            <option :value="null">全て</option>
                                            <option :value="true">あり</option>
                                            <option :value="false">なし</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">担当スタッフ</label>
                                        <select
                                            v-model="searchForm.user_id"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        >
                                            <option :value="null">全て</option>
                                            <option v-for="user in users" :key="user.id" :value="user.id">
                                                {{ user.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">お仕度会場</label>
                                        <input
                                            v-model="searchForm.preparation_venue"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            placeholder="お仕度会場で検索"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">お仕度日程</label>
                                        <input
                                            v-model="searchForm.preparation_date"
                                            type="date"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- 前撮り情報での検索 -->
                            <div class="border-t pt-4">
                                <h4 class="text-sm font-semibold text-gray-700 mb-3">前撮り情報での検索</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">担当店舗</label>
                                        <select
                                            v-model="searchForm.photo_slot_shop_id"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        >
                                            <option :value="null">全て</option>
                                            <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                                                {{ shop.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">前撮り詳細未決定</label>
                                        <select
                                            v-model="searchForm.photo_slot_details_undecided"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        >
                                            <option :value="null">全て</option>
                                            <option :value="true">あり（詳細未決定あり）</option>
                                            <option :value="false">なし（詳細未決定なし）</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium"
                                >
                                    検索
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">画像</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">タグ</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">顧客名</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ふりがな</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">成人式エリア</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">電話番号</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="customer in customers.data" :key="customer.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="w-16 h-16 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center">
                                                <img
                                                    v-if="getFullBodyPhoto(customer)"
                                                    :src="getPhotoUrl(getFullBodyPhoto(customer).file_path)"
                                                    :alt="customer.name"
                                                    class="w-full h-full object-cover"
                                                />
                                                <svg
                                                    v-else
                                                    class="w-8 h-8 text-gray-400"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                    />
                                                </svg>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div v-if="customer.tags && customer.tags.length > 0" class="flex flex-wrap gap-1">
                                                <span
                                                    v-for="tag in customer.tags"
                                                    :key="tag.id"
                                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                                    :style="{
                                                        backgroundColor: tag.color ? tag.color + '20' : '#f3f4f620',
                                                        color: tag.color || '#6b7280',
                                                        border: `1px solid ${tag.color || '#e5e7eb'}`
                                                    }"
                                                >
                                                    {{ tag.name }}
                                                </span>
                                            </div>
                                            <span v-else class="text-sm text-gray-400">-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ customer.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ customer.kana || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ customer.ceremony_area?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ customer.phone_number || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link
                                                :href="route('admin.customers.show', customer.id)"
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
                        </div>

                        <!-- ページネーション -->
                        <div v-if="customers.links && customers.links.length > 3" class="mt-4">
                            <div class="flex justify-center">
                                <template v-for="link in customers.links" :key="link.label">
                                    <Link
                                        v-if="link.url"
                                        :href="link.url"
                                        :class="[
                                            'px-4 py-2 mx-1 rounded-md',
                                            link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                        ]"
                                    >
                                        <span v-html="link.label"></span>
                                    </Link>
                                    <span
                                        v-else
                                        :class="[
                                            'px-4 py-2 mx-1 rounded-md opacity-50 cursor-not-allowed',
                                            link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700',
                                        ]"
                                        v-html="link.label"
                                    ></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 顧客追加モーダル -->
        <transition name="modal">
            <div
                v-if="showAddCustomerModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="closeAddCustomerModal"
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
                            顧客情報追加
                        </h3>
                        <button
                            @click="closeAddCustomerModal"
                            type="button"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- コンテンツ（スクロール可能） -->
                    <form @submit.prevent="storeCustomer" class="overflow-y-auto flex-1 px-6 py-5">
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
                                @click="closeAddCustomerModal"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                キャンセル
                            </button>
                            <button
                                type="submit"
                                :disabled="customerForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="customerForm.processing">追加中...</span>
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
import { ref, reactive, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    customers: Object,
    ceremonyAreas: Array,
    shops: Array,
    plans: Array,
    users: Array,
    filters: Object,
    prefillFromReservation: Object,
});

// モーダル表示フラグ
const showAddCustomerModal = ref(false);

// 検索フォーム
const searchForm = reactive({
    name: props.filters?.name || '',
    kana: props.filters?.kana || '',
    ceremony_area_id: props.filters?.ceremony_area_id || null,
    phone_number: props.filters?.phone_number || '',
    created_at_from: props.filters?.created_at_from || '',
    created_at_to: props.filters?.created_at_to || '',
    contract_date_from: props.filters?.contract_date_from || '',
    contract_date_to: props.filters?.contract_date_to || '',
    shop_id: props.filters?.shop_id || null,
    plan_id: props.filters?.plan_id || null,
    kimono_type: props.filters?.kimono_type || null,
    contract_status: props.filters?.contract_status || null,
    photo_slot_shop_id: props.filters?.photo_slot_shop_id || null,
    warranty_flag: props.filters?.warranty_flag !== undefined ? props.filters.warranty_flag : null,
    user_id: props.filters?.user_id || null,
    preparation_venue: props.filters?.preparation_venue || '',
    preparation_date: props.filters?.preparation_date || '',
    photo_slot_details_undecided: (() => {
        const v = props.filters?.photo_slot_details_undecided;
        if (v === undefined || v === null || v === '') return null;
        if (v === true || v === 'true' || v === 1 || v === '1') return true;
        if (v === false || v === 'false' || v === 0 || v === '0') return false;
        return null;
    })(),
});

// 顧客追加フォーム
const customerForm = useForm({
    name: '',
    kana: '',
    guardian_name: '',
    birth_date: '',
    coming_of_age_year: null,
    ceremony_area_id: null,
    phone_number: '',
    postal_code: '',
    address: '',
    remarks: '',
    event_reservation_id: null,
});

// 検索実行
const searchCustomers = () => {
    // 空の値を除外してクエリパラメータを送信
    const params = {};
    Object.keys(searchForm).forEach(key => {
        const value = searchForm[key];
        if (value !== null && value !== '' && value !== undefined) {
            params[key] = value;
        }
    });
    router.get(route('admin.customers.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

// 検索リセット
const resetSearch = () => {
    searchForm.name = '';
    searchForm.kana = '';
    searchForm.ceremony_area_id = null;
    searchForm.phone_number = '';
    searchForm.created_at_from = '';
    searchForm.created_at_to = '';
    searchForm.contract_date_from = '';
    searchForm.contract_date_to = '';
    searchForm.shop_id = null;
    searchForm.plan_id = null;
    searchForm.kimono_type = null;
    searchForm.contract_status = null;
    searchForm.photo_slot_shop_id = null;
    searchForm.warranty_flag = null;
    searchForm.user_id = null;
    searchForm.preparation_venue = '';
    searchForm.preparation_date = '';
    searchForm.photo_slot_details_undecided = null;
    router.get(route('admin.customers.index'), {}, {
        preserveState: false,
        preserveScroll: false,
    });
};

// 予約からプリフィルをフォームに反映
const applyPrefillFromReservation = () => {
    if (!props.prefillFromReservation) return;
    const p = props.prefillFromReservation;
    customerForm.name = p.name ?? '';
    customerForm.kana = p.kana ?? '';
    customerForm.phone_number = p.phone_number ?? '';
    customerForm.postal_code = p.postal_code ?? '';
    customerForm.address = p.address ?? '';
    customerForm.birth_date = p.birth_date ?? '';
    customerForm.coming_of_age_year = p.coming_of_age_year ?? null;
    customerForm.remarks = p.remarks ?? '';
    customerForm.event_reservation_id = p.id ?? null;
};

// モーダルを閉じてフォームをリセット
const closeAddCustomerModal = () => {
    showAddCustomerModal.value = false;
    customerForm.reset();
};

// 予約詳細から遷移した場合はモーダルを開きプリフィルを反映
onMounted(() => {
    if (props.prefillFromReservation) {
        applyPrefillFromReservation();
        showAddCustomerModal.value = true;
    }
});

// 顧客情報追加
const storeCustomer = () => {
    customerForm.post(route('admin.customers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddCustomerModal.value = false;
            customerForm.reset();
        },
    });
};

// 全身写真を取得（photo_type_idが1の写真）
const getFullBodyPhoto = (customer) => {
    if (!customer.photos || customer.photos.length === 0) {
        return null;
    }
    // photo_type_idが1の写真を取得
    return customer.photos.find(photo => photo.photo_type_id === 1) || null;
};

// 写真URLを取得
const getPhotoUrl = (filePath) => {
    return `/storage/${filePath}`;
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

