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
                <div
                    v-if="$page.props.success"
                    class="mb-4 p-3 rounded bg-green-100 text-green-800 border border-green-200"
                >
                    {{ $page.props.success }}
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- 左側: 既存コンテンツ -->
                    <div class="lg:col-span-2 space-y-6">
                <!-- 顧客タグ（overflow-visible でツールチップが途切れないようにする） -->
                <div class="bg-white overflow-visible shadow-sm sm:rounded-lg mb-6">
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
                                role="button"
                                tabindex="0"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium cursor-pointer hover:ring-2 hover:ring-offset-1 hover:ring-indigo-400/50 transition-shadow"
                                :class="{ 'relative group cursor-help': tag.pivot?.note }"
                                :style="{
                                    backgroundColor: tag.color ? tag.color + '20' : '#f3f4f620',
                                    color: tag.color || '#6b7280',
                                    border: `1px solid ${tag.color || '#e5e7eb'}`
                                }"
                                @click="openEditTagModal(tag)"
                                @keydown.enter.prevent="openEditTagModal(tag)"
                            >
                                <span>{{ tag.name }}</span>
                                <!-- タグ付け理由・補足がある場合にコメントアイコンを表示（ホバーはタグ全体でツールチップ表示） -->
                                <span v-if="tag.pivot?.note" class="inline-flex ml-1 shrink-0" aria-hidden="true">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                </span>
                                <!-- タグ全体ホバーで表示するツールチップ -->
                                <div
                                    v-if="tag.pivot?.note"
                                    class="tag-note-tooltip absolute left-1/2 -translate-x-1/2 bottom-full mb-2 w-80 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-[100] pointer-events-none"
                                >
                                    <div class="tag-note-tooltip__inner">
                                        <div class="tag-note-tooltip__header">
                                            <svg class="w-4 h-4 shrink-0 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                            </svg>
                                            <span>タグ付け理由・補足</span>
                                        </div>
                                        <div class="tag-note-tooltip__body">
                                            <p class="tag-note-tooltip__text">{{ tag.pivot.note }}</p>
                                        </div>
                                        <div class="tag-note-tooltip__arrow" aria-hidden="true"></div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click.stop="removeTag(tag.id)"
                                    class="ml-2 text-gray-500 hover:text-red-600 shrink-0"
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

                <!-- 顧客基本情報（overflow-visible でツールチップが途切れないようにする） -->
                <div class="bg-white overflow-visible shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                基本情報
                            </h3>
                            <div class="flex items-center gap-2">
                                <div class="relative group">
                                    <Link
                                        :href="route('admin.customers.additional-info', customer.id)"
                                        class="flex items-center gap-2 border border-gray-300 bg-white text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 text-sm font-medium shadow-sm transition-colors"
                                        title="お客様にInformationシートを記入頂き、追加情報を取得します"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        追加情報
                                    </Link>
                                    <!-- ホバー時のツールチップ -->
                                    <div
                                        class="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 w-80 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-[100] pointer-events-none"
                                    >
                                        <div class="bg-gray-900 text-white text-xs rounded-lg py-2 px-3 shadow-lg">
                                            <p>お客様にInformationシートを記入頂き、追加情報を取得します</p>
                                            <div class="absolute left-1/2 -translate-x-1/2 top-full border-4 border-transparent border-t-gray-900" aria-hidden="true"></div>
                                        </div>
                                    </div>
                                </div>
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
                                    <p class="text-base font-medium text-gray-900">{{ formatPostalCode(customer.postal_code) }}</p>
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

                        <!-- 追加情報セクション（振袖アンケート）※customerの項目はcustomerから、それ以外はadditional_infoから表示 -->
                        <div v-if="hasAdditionalInfo" class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                追加情報（振袖アンケート）
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- 記入日（additional_info のみ） -->
                                <div v-if="formatAdditionalInfoDate(ai, 'entry_date')" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">記入日</label>
                                    <p class="text-base font-medium text-gray-900">{{ formatAdditionalInfoDate(ai, 'entry_date') }}</p>
                                </div>
                                <!-- お名前・ふりがな（お嬢様）＝ customer -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">お名前（お嬢様）</label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.name || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">ふりがな（お嬢様）</label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.kana || '-' }}</p>
                                </div>
                                <!-- お名前・ふりがな（お母様）＝ customer -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">お名前（お母様）</label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.guardian_name || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">ふりがな（お母様）</label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.guardian_name_kana || '-' }}</p>
                                </div>
                                <!-- お誕生日 ＝ customer -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">お誕生日</label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.birth_date || '-' }}</p>
                                </div>
                                <!-- 身長・足サイズ（additional_info のみ） -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">身長</label>
                                    <p class="text-base font-medium text-gray-900">{{ ai.height ? `${ai.height} cm` : '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">足サイズ</label>
                                    <p class="text-base font-medium text-gray-900">{{ ai.foot_size ? `${ai.foot_size} cm` : '-' }}</p>
                                </div>
                                <!-- 郵便番号・住所 ＝ customer -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">郵便番号</label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.postal_code || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">ご住所</label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.address || '-' }}</p>
                                </div>
                                <!-- お電話：ご自宅＝customer、お嬢様・お母様＝additional_info -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">お電話</label>
                                    <div class="space-y-1 text-sm text-gray-900">
                                        <p v-if="customer.phone_number">ご自宅：{{ customer.phone_number }}</p>
                                        <p v-if="formatAdditionalInfoPhone(ai, 'phone_daughter')">お嬢様：{{ formatAdditionalInfoPhone(ai, 'phone_daughter') }}</p>
                                        <p v-if="formatAdditionalInfoPhone(ai, 'phone_mother')">お母様：{{ formatAdditionalInfoPhone(ai, 'phone_mother') }}</p>
                                        <p v-if="!customer.phone_number && !formatAdditionalInfoPhone(ai, 'phone_daughter') && !formatAdditionalInfoPhone(ai, 'phone_mother')">-</p>
                                    </div>
                                </div>
                                <!-- 好きな色・好きな事 -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">好きな色</label>
                                    <p class="text-base font-medium text-gray-900">{{ formatAdditionalInfoArray(ai.color) || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">好きな事</label>
                                    <p class="text-base font-medium text-gray-900">{{ formatAdditionalInfoArray(ai.hobby) || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">スポーツ（自由記入）</label>
                                    <p class="text-base font-medium text-gray-900">{{ ai.sports_detail || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">お振袖の好きな柄やイメージ</label>
                                    <p class="text-base font-medium text-gray-900 whitespace-pre-wrap">{{ ai.furisode_image || '-' }}</p>
                                </div>
                                <!-- 学生・ご希望 -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">卒業予定年度</label>
                                    <p class="text-base font-medium text-gray-900">{{ ai.graduation_year ? `${ai.graduation_year}年3月` : '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">卒業式にハカマ</label>
                                    <p class="text-base font-medium text-gray-900">{{ ai.hakama || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">購入／レンタル</label>
                                    <p class="text-base font-medium text-gray-900">{{ ai.plan || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">ママ振り・お振袖フォト</label>
                                    <p class="text-base font-medium text-gray-900">{{ formatAdditionalInfoArray(ai.option) || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">価格帯</label>
                                    <p class="text-base font-medium text-gray-900">{{ formatAdditionalInfoArray(ai.price) || '-' }}</p>
                                </div>
                                <!-- 進路・状況 -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">大学生（学校名）</label>
                                    <p class="text-base font-medium text-gray-900">{{ ai.university || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">短大・専門（学校名）</label>
                                    <p class="text-base font-medium text-gray-900">{{ ai.college || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">アルバイト</label>
                                    <p class="text-base font-medium text-gray-900">{{ ai.parttime || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">お勤め</label>
                                    <p class="text-base font-medium text-gray-900">{{ ai.work || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">その他（進路・状況）</label>
                                    <p class="text-base font-medium text-gray-900">{{ ai.other_status || '-' }}</p>
                                </div>
                                <!-- ご姉妹 -->
                                <div v-if="formatAdditionalInfoSisters(ai.sisters)" class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">ご姉妹</label>
                                    <p class="text-base font-medium text-gray-900 whitespace-pre-wrap">{{ formatAdditionalInfoSisters(ai.sisters) }}</p>
                                </div>
                                <!-- 来店動機 ＝ customer.visit_reasons（チェックボックス表示・予約フォームと同一項目） -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">ご来店の動機</label>
                                    <div class="space-y-2">
                                        <div
                                            v-for="reason in visitReasonOptions"
                                            :key="reason.value"
                                            class="flex items-center gap-2"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="isVisitReasonSelected(reason.value, customer.visit_reasons)"
                                                disabled
                                                class="rounded accent-gray-800 w-4 h-4"
                                            />
                                            <span class="text-sm font-medium text-gray-900">{{ reason.label }}</span>
                                            <span v-if="reason.value === 'その他' && getVisitReasonOtherText(customer.visit_reasons)" class="text-sm text-gray-700">（{{ getVisitReasonOtherText(customer.visit_reasons) }}）</span>
                                        </div>
                                        <p v-if="!hasAnyVisitReason(customer.visit_reasons)" class="text-sm text-gray-500">-</p>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">担当</label>
                                    <p class="text-base font-medium text-gray-900">{{ customer.staff_name || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 参加イベント -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">参加イベント</h3>
                        </div>
                        <div v-if="eventReservationsList.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">予約ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">イベント名</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">予約日時</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">会場</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">担当者</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ステータス</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr
                                        v-for="reservation in eventReservationsList"
                                        :key="reservation.id"
                                        :class="{ 'bg-amber-50': reservation.cancel_flg }"
                                    >
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.event?.title || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.reservation_datetime ? formatDateTime(reservation.reservation_datetime) : '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.venue?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ getScheduleAssigneeNames(reservation) || '' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span
                                                v-if="reservation.cancel_flg"
                                                class="px-2 py-1 text-xs rounded-full bg-amber-100 text-amber-800"
                                            >
                                                キャンセル済み
                                            </span>
                                            <span
                                                v-else-if="reservation.status"
                                                class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800"
                                            >
                                                {{ reservation.status }}
                                            </span>
                                            <span v-else class="text-gray-500">-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <Link
                                                :href="route('admin.reservations.show', reservation.id)"
                                                class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-medium"
                                            >
                                                詳細
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            参加イベントはありません
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ステータス</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">成約金額</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">安心保証</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">担当スタッフ</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">お仕度会場</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">お仕度日程</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="contract in customer.contracts" :key="contract.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.contract_date }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.shop?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.plan?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.kimono_type }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 text-xs rounded-full" :class="(contract.status || '確定') === '確定' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
                                                {{ contract.status || '確定' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.total_amount ? `¥${contract.total_amount.toLocaleString()}` : '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 text-xs rounded-full" :class="contract.warranty_flag ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                                                {{ contract.warranty_flag ? 'あり' : 'なし' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.user?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.preparation_venue || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ contract.preparation_date || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <button
                                                type="button"
                                                @click="openEditContractModal(contract)"
                                                class="text-indigo-600 hover:text-indigo-800 font-medium"
                                            >
                                                編集
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            成約情報がありません
                        </div>
                    </div>
                </div>

                <!-- 制約情報 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">制約情報</h3>
                            <button
                                @click="openAddConstraintModal"
                                :disabled="!constraintTemplatesList.length"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
                            >
                                制約追加
                            </button>
                        </div>
                        <div v-if="customerConstraintsList.length > 0" class="space-y-4">
                            <div
                                v-for="cc in customerConstraintsList"
                                :key="cc.id"
                                class="border border-gray-200 rounded-lg p-4 bg-gray-50/50"
                            >
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ (cc.constraint_template || cc.constraintTemplate)?.name || '制約' }}</p>
                                        <p class="text-sm text-gray-500 mt-1">
                                            署名日: {{ cc.signed_at ? formatDate(cc.signed_at) : '-' }}
                                            <span v-if="cc.explainer_user || cc.explainerUser" class="ml-3">規約説明者: {{ (cc.explainer_user || cc.explainerUser)?.name }}</span>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <img
                                            v-if="cc.signature_image"
                                            :src="cc.signature_image"
                                            alt="署名"
                                            class="h-10 w-24 object-contain border border-gray-200 rounded"
                                        />
                                        <button
                                            type="button"
                                            @click="openEditConstraintModal(cc)"
                                            class="text-indigo-600 hover:text-indigo-800 font-medium text-sm"
                                        >
                                            編集
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            制約情報がありません
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">詳細</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">前撮り会場</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">撮影日</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">撮影開始時刻</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">担当店舗</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">担当者</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">プラン</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">担当者用メモラベル</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">備考</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="photoSlot in customer.photoSlots" :key="photoSlot.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ photoSlot.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span v-if="photoSlot.details_undecided" class="px-2 py-1 text-xs rounded-full bg-amber-100 text-amber-800">詳細未決定</span>
                                            <span v-else class="text-gray-500">-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ photoSlot.details_undecided ? '未定' : (photoSlot.studio?.name || '-') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ photoSlot.details_undecided ? '未定' : formatDate(photoSlot.shoot_date) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ photoSlot.details_undecided ? '未定' : formatTime(photoSlot.shoot_time) }}</td>
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
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <button
                                                type="button"
                                                @click="openEditPhotoSlotModal(photoSlot)"
                                                class="text-indigo-600 hover:text-indigo-800 font-medium"
                                            >
                                                編集
                                            </button>
                                        </td>
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
                                        capture="environment"
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
                                <div class="aspect-square rounded-lg overflow-hidden border border-gray-200 bg-gray-100 hover:border-indigo-500 transition-colors relative">
                                    <img
                                        :src="getPhotoUrl(photo.file_path)"
                                        :alt="photo.type?.name || '写真'"
                                        class="w-full h-full object-cover"
                                    />
                                    <button
                                        type="button"
                                        class="absolute top-1 right-1 w-7 h-7 flex items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600 shadow-md z-10"
                                        title="写真を削除"
                                        @click.stop="deleteCustomerPhoto(photo)"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
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

                    <!-- 右側: メモ -->
                    <div class="lg:col-span-1">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
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
                                        <div v-if="noteForm.errors.content" class="mt-1 text-sm text-red-600">
                                            {{ noteForm.errors.content }}
                                        </div>
                                    </div>
                                    <button
                                        type="submit"
                                        :disabled="noteForm.processing"
                                        class="mt-2 w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                    >
                                        {{ noteForm.processing ? "登録中..." : "メモを追加" }}
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
                                                <p class="text-sm font-medium text-gray-900">
                                                    {{ note.user ? note.user.name : "不明" }}
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    {{ formatDateTime(note.created_at) }}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                @click="deleteNote(note.id)"
                                                class="text-red-600 hover:text-red-900 text-sm"
                                            >
                                                削除
                                            </button>
                                        </div>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap">
                                            {{ note.content }}
                                        </p>
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
                                        ステータス <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="contractForm.status"
                                        required
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option value="保留">保留</option>
                                        <option value="確定">確定</option>
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

        <!-- 成約情報編集モーダル -->
        <transition name="modal">
            <div
                v-if="showEditContractModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="showEditContractModal = false"
            >
                <div
                    class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
                >
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-xl font-bold text-gray-900">成約情報編集</h3>
                        <button
                            @click="showEditContractModal = false"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form @submit.prevent="updateContract" class="overflow-y-auto flex-1 px-6 py-5">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">店舗 <span class="text-red-500">*</span></label>
                                    <select v-model="editContractForm.shop_id" required @change="onEditContractShopChange" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">選択してください</option>
                                        <option v-for="shop in shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">担当スタッフ</label>
                                    <select v-model="editContractForm.user_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option :value="null">選択してください</option>
                                        <option v-for="user in shopUsers" :key="user.id" :value="user.id">{{ user.name }}</option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">成約日 <span class="text-red-500">*</span></label>
                                    <input v-model="editContractForm.contract_date" type="date" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">着物種別 <span class="text-red-500">*</span></label>
                                    <select v-model="editContractForm.kimono_type" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">選択してください</option>
                                        <option value="振袖">振袖</option>
                                        <option value="袴">袴</option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">ステータス <span class="text-red-500">*</span></label>
                                    <select v-model="editContractForm.status" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="保留">保留</option>
                                        <option value="確定">確定</option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">成約金額（税込）</label>
                                    <input v-model.number="editContractForm.total_amount" type="number" min="0" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">プラン <span class="text-red-500">*</span></label>
                                    <select v-model="editContractForm.plan_id" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">選択してください</option>
                                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">お仕度会場</label>
                                    <input v-model="editContractForm.preparation_venue" type="text" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">お仕度日程</label>
                                    <input v-model="editContractForm.preparation_date" type="date" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="flex items-center cursor-pointer">
                                        <input v-model="editContractForm.warranty_flag" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span class="ml-2 text-sm text-gray-700">安心保証</span>
                                    </label>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">備考</label>
                                    <textarea v-model="editContractForm.remarks" rows="4" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                            <button type="button" @click="showEditContractModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">キャンセル</button>
                            <button type="submit" :disabled="editContractForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span v-if="editContractForm.processing">更新中...</span>
                                <span v-else>更新</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <!-- 制約情報追加・編集モーダル -->
        <transition name="modal">
            <div
                v-if="showConstraintModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="closeConstraintModal"
            >
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                    <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-xl font-bold text-gray-900">{{ editingConstraint ? '制約情報編集' : '制約追加 - 準備' }}</h3>
                        <button
                            @click="closeConstraintModal"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto flex-1 px-6 py-5">
                        <div class="space-y-6">
                            <!-- テンプレート選択（追加時のみ） -->
                            <div v-if="!editingConstraint" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <label class="block text-sm font-medium text-gray-700 mb-2">制約テンプレート <span class="text-red-500">*</span></label>
                                <select v-model="constraintForm.constraint_template_id" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">選択してください</option>
                                    <option v-for="t in constraintTemplatesList" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>

                            <!-- 制約本文（マークダウン＋チェックボックス） -->
                            <div v-if="selectedConstraintTemplate" class="border border-gray-200 rounded-lg p-4 max-h-80 overflow-y-auto">
                                <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ selectedConstraintTemplate.name }}</h4>
                                <div class="constraint-body prose prose-sm max-w-none text-gray-800">
                                    <ConstraintBodyWithChecks
                                        :body="selectedConstraintTemplate.body"
                                        :check-values="constraintForm.check_values"
                                        @update:check-values="(v) => constraintForm.check_values = v"
                                    />
                                </div>
                            </div>

                            <!-- 署名準備情報 -->
                            <div class="border-t pt-6 space-y-4">
                                <h4 class="text-sm font-semibold text-gray-700">署名準備情報</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">日付</label>
                                        <input
                                            v-model="constraintForm.signed_at"
                                            type="date"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        />
                                    </div>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">規約説明者（店舗）</label>
                                            <select
                                                v-model="constraintExplainerShopId"
                                                @change="onConstraintExplainerShopChange"
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            >
                                                <option value="">店舗を選択</option>
                                                <option v-for="s in constraintModalShops" :key="s.id" :value="s.id">{{ s.name }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">規約説明者（スタッフ）</label>
                                            <select
                                                v-model="constraintForm.explainer_user_id"
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                :disabled="!constraintExplainerShopId"
                                            >
                                                <option :value="null">{{ constraintExplainerShopId ? 'スタッフを選択' : 'まず店舗を選択してください' }}</option>
                                                <option v-for="u in constraintModalStaff" :key="u.id" :value="u.id">{{ u.name }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500">※「準備完了」をクリックすると、署名用ページに移動します。署名はそちらで行います。</p>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                            <button type="button" @click="closeConstraintModal" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                キャンセル
                            </button>
                            <button
                                type="button"
                                @click="goToConstraintSignPage"
                                :disabled="!selectedConstraintTemplate"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                準備完了
                            </button>
                        </div>
                    </div>
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
                            <div class="mb-4 p-4 bg-amber-50 rounded-lg border border-amber-200">
                                <label class="flex items-center cursor-pointer">
                                    <input
                                        v-model="photoSlotForm.details_undecided"
                                        type="checkbox"
                                        class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span class="ml-2 text-sm font-medium text-gray-700">詳細未決定（担当店舗のみで仮登録）</span>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        担当店舗 <span v-if="photoSlotForm.details_undecided" class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="photoSlotForm.shop_id"
                                        @change="onPhotoSlotShopChange"
                                        :required="photoSlotForm.details_undecided"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option value="">選択してください</option>
                                        <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                                            {{ shop.name }}
                                        </option>
                                    </select>
                                    <div v-if="photoSlotForm.errors.shop_id" class="mt-1 text-sm text-red-600">{{ photoSlotForm.errors.shop_id }}</div>
                                </div>
                                <div v-if="!photoSlotForm.details_undecided" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
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
                                <div v-if="!photoSlotForm.details_undecided" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
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
                                <div v-if="!photoSlotForm.details_undecided" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
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

        <!-- 前撮り情報編集モーダル -->
        <transition name="modal">
            <div
                v-if="showEditPhotoSlotModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="showEditPhotoSlotModal = false"
            >
                <div
                    class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
                >
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-xl font-bold text-gray-900">前撮り情報編集</h3>
                        <button
                            @click="showEditPhotoSlotModal = false"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form @submit.prevent="updatePhotoSlot" class="overflow-y-auto flex-1 px-6 py-5">
                        <div class="space-y-4">
                            <div class="mb-4 p-4 bg-amber-50 rounded-lg border border-amber-200">
                                <label class="flex items-center cursor-pointer">
                                    <input
                                        v-model="editPhotoSlotForm.details_undecided"
                                        type="checkbox"
                                        @change="onEditPhotoSlotDetailsUndecidedChange"
                                        class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span class="ml-2 text-sm font-medium text-gray-700">詳細未決定（担当店舗のみで仮登録）</span>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">担当店舗 <span class="text-red-500">*</span></label>
                                    <select v-model="editPhotoSlotForm.shop_id" required @change="onEditPhotoSlotShopChange" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">選択してください</option>
                                        <option v-for="shop in shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option>
                                    </select>
                                </div>
                                <div v-if="!editPhotoSlotForm.details_undecided" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">会場 <span class="text-red-500">*</span></label>
                                    <select v-model="editPhotoSlotForm.selected_studio_id" required :disabled="!editPhotoSlotForm.shop_id" @change="onEditPhotoSlotStudioChange" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed">
                                        <option value="">選択してください</option>
                                        <option v-for="studio in editAvailableStudios" :key="studio.id" :value="studio.id">{{ studio.name }}</option>
                                    </select>
                                </div>
                                <div v-if="!editPhotoSlotForm.details_undecided" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">撮影日 <span class="text-red-500">*</span></label>
                                    <select v-model="editPhotoSlotForm.selected_date" required :disabled="!editPhotoSlotForm.selected_studio_id" @change="onEditPhotoSlotDateChange" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed">
                                        <option value="">選択してください</option>
                                        <option v-for="date in editAvailableDates" :key="date" :value="date">{{ formatDate(date) }}</option>
                                    </select>
                                </div>
                                <div v-if="!editPhotoSlotForm.details_undecided" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">撮影時間 <span class="text-red-500">*</span></label>
                                    <select v-model="editPhotoSlotForm.photo_slot_id" required :disabled="!editPhotoSlotForm.selected_date" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed">
                                        <option value="">選択してください</option>
                                        <option v-for="slot in editAvailableTimeSlots" :key="slot.id" :value="slot.id">{{ formatTime(slot.shoot_time) }}<span v-if="slot.plan"> - {{ slot.plan.name }}</span></option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">担当者用メモラベル</label>
                                    <select v-model="editPhotoSlotForm.assignment_label" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option :value="null">選択してください</option>
                                        <option value="動員">動員</option>
                                        <option value="岡山店 / F">岡山店 / F</option>
                                        <option value="城東店 / F">城東店 / F</option>
                                        <option value="引継ぎ / F">引継ぎ / F</option>
                                        <option value="EXPO / F">EXPO / F</option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">担当者</label>
                                    <select v-model="editPhotoSlotForm.user_id" :disabled="!editPhotoSlotForm.shop_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed">
                                        <option :value="null">選択してください</option>
                                        <option v-for="user in photoSlotShopUsers" :key="user.id" :value="user.id">{{ user.name }}</option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">プラン</label>
                                    <select v-model="editPhotoSlotForm.plan_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option :value="null">選択してください</option>
                                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                                    </select>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">備考</label>
                                    <textarea v-model="editPhotoSlotForm.remarks" rows="4" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                            <button type="button" @click="showEditPhotoSlotModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">キャンセル</button>
                            <button type="submit" :disabled="editPhotoSlotForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span v-if="editPhotoSlotForm.processing">更新中...</span>
                                <span v-else>更新</span>
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

        <!-- 手書きメモ用モーダル -->
        <transition name="modal">
            <div
                v-if="showPhotoMemoModal"
                class="fixed inset-0 bg-black bg-opacity-75 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="closePhotoMemoModal"
            >
                <div class="relative bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-xl font-bold text-gray-900">手書きメモを追加</h3>
                        <button
                            type="button"
                            @click="closePhotoMemoModal"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-auto p-6 flex flex-col items-center">
                        <!-- ペン設定 -->
                        <div class="flex flex-wrap items-center gap-4 mb-4 w-full max-w-2xl">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-700">色</span>
                                <div class="flex gap-1">
                                    <button
                                        v-for="c in photoMemoPenColors"
                                        :key="c.value"
                                        type="button"
                                        :title="c.name"
                                        class="w-8 h-8 rounded-full border-2 transition-transform hover:scale-110"
                                        :class="photoMemoPenColor === c.value ? 'border-gray-900 scale-110 ring-2 ring-offset-1 ring-gray-400' : 'border-gray-300'"
                                        :style="{ backgroundColor: c.value }"
                                        @click="photoMemoPenColor = c.value; applyPhotoMemoBrushStyle()"
                                    />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-700">太さ</span>
                                <select
                                    v-model.number="photoMemoPenWidth"
                                    class="rounded-md border-gray-300 text-sm"
                                    @change="applyPhotoMemoBrushStyle()"
                                >
                                    <option :value="2">細い</option>
                                    <option :value="4">普通</option>
                                    <option :value="8">太い</option>
                                    <option :value="12">とても太い</option>
                                </select>
                            </div>
                        </div>
                        <!-- キャンバスラッパー: 明示的なサイズでイベントが確実に届くようにする -->
                        <div
                            ref="photoMemoCanvasWrap"
                            class="w-full bg-gray-100 rounded-lg overflow-hidden"
                            style="width: 100%; height: 60vh; min-height: 320px; max-height: 70vh;"
                        >
                            <canvas ref="photoMemoCanvas" />
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
                        <button
                            type="button"
                            @click="skipPhotoMemo"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100"
                        >
                            スキップ
                        </button>
                        <button
                            type="button"
                            @click="confirmPhotoMemo"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700"
                        >
                            確定
                        </button>
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

        <!-- タグ追加・編集モーダル -->
        <transition name="modal">
            <div
                v-if="showTagModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="closeTagModal"
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
                            {{ editingTag ? 'タグを編集' : 'タグを追加' }}
                        </h3>
                        <button
                            type="button"
                            @click="closeTagModal"
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
                            <!-- 追加時: タグ選択 -->
                            <div v-if="!editingTag">
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
                            <!-- 編集時: 対象タグを表示 -->
                            <div v-else class="flex items-center gap-2">
                                <label class="text-sm font-medium text-gray-700 shrink-0">編集対象タグ</label>
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                    :style="{
                                        backgroundColor: editingTag.color ? editingTag.color + '20' : '#f3f4f620',
                                        color: editingTag.color || '#6b7280',
                                        border: `1px solid ${editingTag.color || '#e5e7eb'}`
                                    }"
                                >
                                    {{ editingTag.name }}
                                </span>
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
                                @click="closeTagModal"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                キャンセル
                            </button>
                            <button
                                type="submit"
                                :disabled="tagForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="tagForm.processing">{{ editingTag ? '更新中...' : '追加中...' }}</span>
                                <span v-else>{{ editingTag ? '更新' : '追加' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed, nextTick, watch, onBeforeUnmount } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Canvas, FabricImage, PencilBrush } from 'fabric';
import ConstraintBodyWithChecks from '@/Components/ConstraintBodyWithChecks.vue';

const props = defineProps({
    customer: Object,
    notes: {
        type: Array,
        default: () => [],
    },
    ceremonyAreas: Array,
    shops: Array,
    plans: Array,
    users: Array,
    photoStudios: Array,
    photoTypes: Array,
    availablePhotoSlots: Array,
    userShops: Array,
    customerTags: Array,
    constraintTemplates: Array,
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || null);

// 参加イベント（予約）一覧（snake_case / camelCase 両対応）
const eventReservationsList = computed(() => {
    const list = props.customer?.event_reservations ?? props.customer?.eventReservations;
    return Array.isArray(list) ? list : [];
});

// スケジュールから担当者名を取得（スケジュール未紐づけの場合は空文字）
const getScheduleAssigneeNames = (reservation) => {
    const schedule = reservation?.schedule;
    if (!schedule) return '';
    const users = schedule.participant_users ?? schedule.participantUsers;
    if (!Array.isArray(users) || users.length === 0) return '';
    return users.map((u) => u?.name).filter(Boolean).join('、');
};

// モーダル表示フラグ
const showEditCustomerModal = ref(false);
const showAddContractModal = ref(false);
const showEditContractModal = ref(false);
const showAddPhotoSlotModal = ref(false);
const showEditPhotoSlotModal = ref(false);
const showPhotoPreviewModal = ref(false);
const showPhotoMemoModal = ref(false);
const showDeleteConfirmModal = ref(false);
const showTagModal = ref(false);
const editingTag = ref(null);
const showConstraintModal = ref(false);
const editingConstraint = ref(null);

// 手書きメモ用 Fabric
const photoMemoCanvasWrap = ref(null);
const photoMemoCanvas = ref(null);
const fabricCanvasRef = ref(null);

// ペン設定（色・太さ）
const photoMemoPenColors = [
    { name: '黒', value: '#000000' },
    { name: '赤', value: '#dc2626' },
    { name: '青', value: '#2563eb' },
    { name: '緑', value: '#16a34a' },
    { name: '黄', value: '#ca8a04' },
    { name: '白', value: '#ffffff' },
];
const photoMemoPenColor = ref('#000000');
const photoMemoPenWidth = ref(4);

// ペン設定を Fabric のブラシに反映
const applyPhotoMemoBrushStyle = () => {
    const canvas = fabricCanvasRef.value;
    if (canvas?.freeDrawingBrush) {
        canvas.freeDrawingBrush.color = photoMemoPenColor.value;
        canvas.freeDrawingBrush.width = photoMemoPenWidth.value;
    }
};

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

// 制約テンプレート・顧客制約
const constraintForm = useForm({
    constraint_template_id: '',
    signed_at: '',
    signature_image: null,
    explainer_user_id: null,
    check_values: {},
});

const constraintTemplatesList = computed(() => props.constraintTemplates || []);
const customerConstraintsList = computed(() => {
    const list = props.customer?.constraints ?? [];
    return Array.isArray(list) ? list : [];
});
const selectedConstraintTemplate = computed(() => {
    const id = constraintForm.constraint_template_id;
    if (!id) return null;
    return constraintTemplatesList.value.find((t) => t.id == id) || null;
});
const constraintModalShops = computed(() => {
    const t = selectedConstraintTemplate.value;
    return t?.shops ?? [];
});

const constraintExplainerShopId = ref('');
const constraintModalStaff = ref([]);

const loadConstraintExplainerStaff = async (shopId) => {
    if (!shopId) {
        constraintModalStaff.value = [];
        constraintForm.explainer_user_id = null;
        return;
    }
    try {
        const response = await axios.get(route('admin.schedules.shop-users'), {
            params: { shop_id: shopId },
        });
        constraintModalStaff.value = response.data || [];
        if (constraintForm.explainer_user_id && !constraintModalStaff.value.some((u) => u.id === constraintForm.explainer_user_id)) {
            constraintForm.explainer_user_id = null;
        }
    } catch {
        constraintModalStaff.value = [];
        constraintForm.explainer_user_id = null;
    }
};

const onConstraintExplainerShopChange = () => {
    loadConstraintExplainerStaff(constraintExplainerShopId.value);
};

watch(selectedConstraintTemplate, () => {
    constraintExplainerShopId.value = '';
    constraintModalStaff.value = [];
    constraintForm.explainer_user_id = null;
}, { immediate: false });

// 成約追加フォーム
const contractForm = useForm({
    shop_id: '',
    plan_id: '',
    contract_date: '',
    kimono_type: '',
    status: '確定',
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
const onEditContractShopChange = async () => {
    const shopId = editContractForm.shop_id;
    if (!shopId) {
        shopUsers.value = [];
        editContractForm.user_id = null;
        return;
    }
    await loadShopUsers(shopId);
    if (editContractForm.user_id && !shopUsers.value.some(u => u.id === editContractForm.user_id)) {
        editContractForm.user_id = null;
    }
};

// 成約追加モーダルを開く
const openAddContractModal = async () => {
    contractForm.reset();
    contractForm.status = '確定';
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

// 成約編集モーダルを開く
const editingContract = ref(null);
const editContractForm = useForm({
    shop_id: '',
    plan_id: '',
    contract_date: '',
    kimono_type: '',
    status: '確定',
    warranty_flag: false,
    total_amount: null,
    preparation_venue: '',
    preparation_date: '',
    user_id: null,
    remarks: '',
});
const openEditContractModal = async (contract) => {
    editingContract.value = contract;
    editContractForm.reset();
    editContractForm.shop_id = contract.shop_id || '';
    editContractForm.plan_id = contract.plan_id || '';
    editContractForm.contract_date = contract.contract_date || '';
    editContractForm.kimono_type = contract.kimono_type || '';
    editContractForm.status = contract.status || '確定';
    editContractForm.warranty_flag = contract.warranty_flag || false;
    editContractForm.total_amount = contract.total_amount ?? null;
    editContractForm.preparation_venue = contract.preparation_venue || '';
    editContractForm.preparation_date = contract.preparation_date || '';
    editContractForm.user_id = contract.user_id ?? null;
    editContractForm.remarks = contract.remarks || '';
    await loadShopUsers(contract.shop_id);
    showEditContractModal.value = true;
};

// 前撮り追加フォーム
const photoSlotForm = useForm({
    details_undecided: false,
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
const onEditPhotoSlotShopChange = async () => {
    editPhotoSlotForm.selected_studio_id = '';
    editPhotoSlotForm.selected_date = '';
    editPhotoSlotForm.photo_slot_id = '';
    if (editPhotoSlotForm.shop_id) {
        await loadPhotoSlotShopUsers(editPhotoSlotForm.shop_id);
        if (editPhotoSlotForm.user_id && !photoSlotShopUsers.value.some(u => u.id === editPhotoSlotForm.user_id)) {
            editPhotoSlotForm.user_id = null;
        }
    } else {
        photoSlotShopUsers.value = [];
        editPhotoSlotForm.user_id = null;
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
        // プレビュー用URLを生成後に手書きメモ用モーダルを開く
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview.value = e.target.result;
            showPhotoMemoModal.value = true;
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

// 顧客写真を削除
const deleteCustomerPhoto = (photo) => {
    if (!confirm('この写真を削除しますか？')) return;
    router.delete(route('admin.customers.photos.destroy', { customer: props.customer.id, photo: photo.id }), {
        preserveScroll: true,
    });
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

// 手書きメモ用 Fabric Canvas の破棄
const disposePhotoMemoCanvas = () => {
    if (fabricCanvasRef.value) {
        fabricCanvasRef.value.dispose();
        fabricCanvasRef.value = null;
    }
};

// 手書きメモ用モーダルを閉じる
const closePhotoMemoModal = () => {
    disposePhotoMemoCanvas();
    showPhotoMemoModal.value = false;
};

// 手書きメモ用 Fabric Canvas の初期化
const initPhotoMemoCanvas = async () => {
    const wrap = photoMemoCanvasWrap.value;
    const canvasEl = photoMemoCanvas.value;
    const dataUrl = photoPreview.value;
    if (!wrap || !canvasEl || !dataUrl) return;

    disposePhotoMemoCanvas();

    // ラッパーの実サイズを取得（明示的 style で 60vh 等が効いた後の値）
    const w = Math.max(wrap.clientWidth || 600, 100);
    const h = Math.max(wrap.clientHeight || 400, 100);

    const canvas = new Canvas(canvasEl, {
        width: w,
        height: h,
        enableRetinaScaling: false,
    });
    fabricCanvasRef.value = canvas;

    try {
        const img = await FabricImage.fromURL(dataUrl);
        img.set({ selectable: false, evented: false });
        const scale = Math.min(w / (img.width || 1), h / (img.height || 1));
        img.scale(scale);
        img.set({ left: w / 2, top: h / 2, originX: 'center', originY: 'center' });
        canvas.add(img);
        canvas.sendObjectToBack(img);
        canvas.isDrawingMode = true;
        // Fabric 6 では freeDrawingBrush は自前で設定する必要がある
        const brush = new PencilBrush(canvas);
        canvas.freeDrawingBrush = brush;
        applyPhotoMemoBrushStyle();
        canvas.requestRenderAll();
    } catch (err) {
        console.error('画像の読み込みに失敗しました:', err);
        disposePhotoMemoCanvas();
    }
};

// 手書きメモを確定して写真を更新
const confirmPhotoMemo = () => {
    const canvas = fabricCanvasRef.value;
    if (!canvas) {
        closePhotoMemoModal();
        return;
    }
    // 描画モードを解除し、手書きパスをすべてキャンバスオブジェクトとして確定させる
    canvas.isDrawingMode = false;
    canvas.requestRenderAll();
    // 1フレーム待ってからエクスポート（Fabric の内部レンダー完了を待つ）
    requestAnimationFrame(() => {
        const dataUrl = canvas.toDataURL({ format: 'image/png' });
        try {
            fetch(dataUrl)
                .then((res) => res.blob())
                .then((blob) => {
                    const file = new File([blob], 'photo-with-memo.png', { type: 'image/png' });
                    photoForm.photo = file;
                    photoPreview.value = dataUrl;
                })
                .catch((err) => console.error('画像の出力に失敗しました:', err))
                .finally(() => closePhotoMemoModal());
        } catch (err) {
            console.error('画像の出力に失敗しました:', err);
            closePhotoMemoModal();
        }
    });
};

// 手書きメモをスキップ
const skipPhotoMemo = () => {
    closePhotoMemoModal();
};

// 手書きメモモーダル表示時に Fabric を初期化（レイアウト完了後に実行）
watch(showPhotoMemoModal, (visible) => {
    if (visible) {
        nextTick(() => {
            requestAnimationFrame(() => {
                requestAnimationFrame(() => initPhotoMemoCanvas());
            });
        });
    } else {
        disposePhotoMemoCanvas();
    }
});

onBeforeUnmount(() => {
    disposePhotoMemoCanvas();
});

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

// 制約モーダル
const getTodayDateString = () => {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
};

const openAddConstraintModal = () => {
    editingConstraint.value = null;
    constraintForm.reset();
    constraintForm.constraint_template_id = constraintTemplatesList.value[0]?.id ?? '';
    constraintForm.signed_at = getTodayDateString();
    constraintForm.check_values = {};
    constraintForm.signature_image = null;
    constraintExplainerShopId.value = '';
    constraintModalStaff.value = [];
    showConstraintModal.value = true;
};

const openEditConstraintModal = (cc) => {
    // 編集時は署名ページへ直接遷移
    const tplId = cc.constraint_template_id ?? cc.constraintTemplateId;
    const checkValues = cc.check_values || cc.checkValues || {};
    const signedAt = cc.signed_at ? (typeof cc.signed_at === 'string' ? cc.signed_at.split('T')[0] : cc.signed_at) : getTodayDateString();
    const explainerId = cc.explainer_user_id ?? cc.explainerUserId ?? '';

    // 署名ページへ遷移（編集の場合も同じページで既存情報を表示）
    const params = new URLSearchParams({
        template_id: tplId,
        signed_at: signedAt,
        explainer_user_id: explainerId || '',
        check_values: JSON.stringify(checkValues),
        edit_id: cc.id,
    });
    router.visit(route('admin.customers.constraints.sign', props.customer.id) + '?' + params.toString());
};

const closeConstraintModal = () => {
    showConstraintModal.value = false;
    editingConstraint.value = null;
};

const goToConstraintSignPage = () => {
    if (!constraintForm.constraint_template_id) return;

    const params = new URLSearchParams({
        template_id: constraintForm.constraint_template_id,
        signed_at: constraintForm.signed_at || getTodayDateString(),
        explainer_user_id: constraintForm.explainer_user_id || '',
        check_values: JSON.stringify(constraintForm.check_values || {}),
    });

    closeConstraintModal();
    router.visit(route('admin.customers.constraints.sign', props.customer.id) + '?' + params.toString());
};

// 成約情報更新
const updateContract = () => {
    if (!editingContract.value) return;
    editContractForm.transform((data) => {
        const t = { ...data };
        t.warranty_flag = !!t.warranty_flag;
        t._method = 'PUT';
        return t;
    }).post(route('admin.customers.contracts.update', { customer: props.customer.id, contract: editingContract.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showEditContractModal.value = false;
            editingContract.value = null;
        },
    });
};

// 前撮り編集モーダルを開く
const editingPhotoSlot = ref(null);
const editPhotoSlotForm = useForm({
    details_undecided: false,
    shop_id: '',
    selected_studio_id: '',
    selected_date: '',
    photo_slot_id: '',
    assignment_label: null,
    user_id: null,
    plan_id: null,
    remarks: '',
});
// 編集用：利用可能な枠（空き枠 + 現在の枠を保持する選択肢）
const editAvailablePhotoSlots = computed(() => {
    const available = props.availablePhotoSlots || [];
    const current = editingPhotoSlot.value;
    if (!current || current.details_undecided || !current.photo_studio_id) return available;
    const exists = available.some(s => s.id === current.id);
    return exists ? available : [...available, current];
});
const editAvailableStudios = computed(() => {
    if (!editAvailablePhotoSlots.value.length || !editPhotoSlotForm.shop_id) return [];
    const studios = new Map();
    const shopId = Number(editPhotoSlotForm.shop_id);
    editAvailablePhotoSlots.value.forEach(slot => {
        if (slot.studio && slot.shops && slot.shops.some(s => s.id === shopId) && !studios.has(slot.studio.id)) {
            studios.set(slot.studio.id, slot.studio);
        }
    });
    return Array.from(studios.values()).sort((a, b) => a.name.localeCompare(b.name));
});
const editAvailableDates = computed(() => {
    if (!editPhotoSlotForm.selected_studio_id || !editPhotoSlotForm.shop_id || !editAvailablePhotoSlots.value.length) return [];
    const dates = new Set();
    const shopId = Number(editPhotoSlotForm.shop_id);
    editAvailablePhotoSlots.value.forEach(slot => {
        if (slot.studio?.id == editPhotoSlotForm.selected_studio_id && slot.shops?.some(s => s.id === shopId)) {
            dates.add(slot.shoot_date);
        }
    });
    return Array.from(dates).sort();
});
const editAvailableTimeSlots = computed(() => {
    if (!editPhotoSlotForm.selected_studio_id || !editPhotoSlotForm.selected_date || !editPhotoSlotForm.shop_id || !editAvailablePhotoSlots.value.length) return [];
    const shopId = Number(editPhotoSlotForm.shop_id);
    return editAvailablePhotoSlots.value.filter(slot =>
        slot.studio?.id == editPhotoSlotForm.selected_studio_id &&
        slot.shoot_date === editPhotoSlotForm.selected_date &&
        slot.shops?.some(s => s.id === shopId)
    ).sort((a, b) => (a.shoot_time || '').localeCompare(b.shoot_time || ''));
});
const openEditPhotoSlotModal = async (photoSlot) => {
    editingPhotoSlot.value = photoSlot;
    editPhotoSlotForm.reset();
    editPhotoSlotForm.details_undecided = !!photoSlot.details_undecided;
    editPhotoSlotForm.shop_id = (photoSlot.shops && photoSlot.shops[0]) ? photoSlot.shops[0].id : '';
    editPhotoSlotForm.assignment_label = photoSlot.assignment_label ?? null;
    editPhotoSlotForm.user_id = photoSlot.user_id ?? null;
    editPhotoSlotForm.plan_id = photoSlot.plan_id ?? null;
    editPhotoSlotForm.remarks = photoSlot.remarks || '';
    if (!photoSlot.details_undecided && photoSlot.studio && photoSlot.shoot_date && photoSlot.shoot_time) {
        editPhotoSlotForm.selected_studio_id = photoSlot.photo_studio_id || photoSlot.studio.id || '';
        editPhotoSlotForm.selected_date = photoSlot.shoot_date || '';
        editPhotoSlotForm.photo_slot_id = photoSlot.id || '';
    } else {
        editPhotoSlotForm.selected_studio_id = '';
        editPhotoSlotForm.selected_date = '';
        editPhotoSlotForm.photo_slot_id = '';
    }
    if (editPhotoSlotForm.shop_id) {
        await loadPhotoSlotShopUsers(editPhotoSlotForm.shop_id);
    } else {
        photoSlotShopUsers.value = [];
    }
    showEditPhotoSlotModal.value = true;
};
const onEditPhotoSlotDetailsUndecidedChange = () => {
    if (editPhotoSlotForm.details_undecided) {
        editPhotoSlotForm.selected_studio_id = '';
        editPhotoSlotForm.selected_date = '';
        editPhotoSlotForm.photo_slot_id = '';
    }
};
const onEditPhotoSlotStudioChange = () => {
    editPhotoSlotForm.selected_date = '';
    editPhotoSlotForm.photo_slot_id = '';
};
const onEditPhotoSlotDateChange = () => {
    editPhotoSlotForm.photo_slot_id = '';
};

// 前撮り追加モーダルを開く
const openAddPhotoSlotModal = async () => {
    photoSlotForm.details_undecided = false;
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

// 前撮り情報更新
const updatePhotoSlot = () => {
    if (!editingPhotoSlot.value) return;
    editPhotoSlotForm.transform((data) => {
        const t = { ...data };
        t.details_undecided = !!t.details_undecided;
        t.shop_id = t.shop_id || null;
        t.assignment_label = t.assignment_label || null;
        t.user_id = t.user_id || null;
        t.plan_id = t.plan_id || null;
        if (t.details_undecided) {
            delete t.photo_slot_id;
            delete t.selected_studio_id;
            delete t.selected_date;
        } else {
            t.photo_slot_id = t.photo_slot_id || null;
            delete t.selected_studio_id;
            delete t.selected_date;
        }
        t._method = 'PUT';
        return t;
    }).post(route('admin.customers.photo-slots.update', { customer: props.customer.id, photoSlot: editingPhotoSlot.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showEditPhotoSlotModal.value = false;
            editingPhotoSlot.value = null;
        },
    });
};

// 前撮り情報追加
const storePhotoSlot = () => {
    // フォームデータを変換（空文字列をnullに変換、不要なフィールドを削除）
    photoSlotForm.transform((data) => {
        const transformed = { ...data };
        transformed.details_undecided = !!transformed.details_undecided;
        transformed.shop_id = transformed.shop_id || null;
        transformed.assignment_label = transformed.assignment_label || null;
        transformed.user_id = transformed.user_id || null;
        transformed.plan_id = transformed.plan_id || null;
        // selected_studio_idとselected_dateは送信しない（photo_slot_idだけで十分）
        delete transformed.selected_studio_id;
        delete transformed.selected_date;
        if (transformed.details_undecided) {
            delete transformed.photo_slot_id;
        }
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

// 郵便番号を XXX-XXXX 形式で表示
const formatPostalCode = (val) => {
    if (!val) return '-';
    const digits = String(val).replace(/-/g, '').replace(/\D/g, '').slice(0, 7);
    if (digits.length === 7) return digits.slice(0, 3) + '-' + digits.slice(3, 7);
    return digits || '-';
};

// 追加情報（振袖アンケート）の表示用
const ai = computed(() => props.customer?.additional_info ?? {});
const hasAdditionalInfo = computed(() => {
    const a = props.customer?.additional_info;
    return a && typeof a === 'object' && Object.keys(a).length > 0;
});
const formatAdditionalInfoDate = (data, type) => {
    if (!data) return '';
    const prefix = type === 'entry_date' ? 'entry_date' : 'birth';
    const y = data[`${prefix}_year`];
    const m = data[`${prefix}_month`];
    const d = data[`${prefix}_day`];
    if (!y && !m && !d) return '';
    return [y, m, d].filter(Boolean).join('年') + (d ? '日' : '');
};
const formatAdditionalInfoPhone = (data, prefix) => {
    if (!data) return '';
    const a = data[`${prefix}_1`];
    const b = data[`${prefix}_2`];
    const c = data[`${prefix}_3`];
    if (!a && !b && !c) return '';
    return [a, b, c].filter(Boolean).join('－');
};
const formatAdditionalInfoArray = (arr) => {
    if (!Array.isArray(arr) || arr.length === 0) return '';
    return arr.filter(Boolean).join('、');
};
const formatAdditionalInfoSisters = (sisters) => {
    if (!Array.isArray(sisters) || sisters.length === 0) return '';
    return sisters
        .filter(s => s?.name || s?.year || s?.month || s?.day)
        .map(s => {
            const parts = [s.name].filter(Boolean);
            const y = s.year || s.month || s.day ? [s.year, s.month, s.day].filter(Boolean).join('年') + (s.day ? '日' : '') + '生' : '';
            if (y) parts.push(y);
            return parts.join(' ');
        })
        .join('\n');
};

// 来店動機の選択肢（Event/ReservationForm と同一）
const visitReasonOptions = [
    { value: '紹介', label: '紹介' },
    { value: 'DM・カタログ', label: 'DM・カタログ' },
    { value: 'SNS広告(Instaなど)', label: 'SNS広告(Instaなど)' },
    { value: 'WEB広告', label: 'WEB広告' },
    { value: 'その他', label: 'その他(テキスト入力)' },
];

const isVisitReasonSelected = (value, visitReasons) => {
    if (!Array.isArray(visitReasons)) return false;
    if (value === 'その他') {
        return visitReasons.some(r => typeof r === 'string' && r.startsWith('その他('));
    }
    return visitReasons.includes(value);
};

const getVisitReasonOtherText = (visitReasons) => {
    if (!Array.isArray(visitReasons)) return '';
    const other = visitReasons.find(r => typeof r === 'string' && r.startsWith('その他('));
    if (!other) return '';
    const m = other.match(/^その他\((.+)\)$/);
    return m && m[1] ? m[1] : '';
};

const hasAnyVisitReason = (visitReasons) => {
    if (!Array.isArray(visitReasons) || visitReasons.length === 0) return false;
    return visitReasons.some(Boolean);
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

// メモフォーム
const noteForm = useForm({
    content: '',
});

const submitNote = () => {
    noteForm.post(route('admin.customers.notes.store', props.customer.id), {
        onSuccess: () => {
            noteForm.reset();
        },
    });
};

const deleteNote = (noteId) => {
    if (confirm('このメモを削除しますか？')) {
        router.delete(route('admin.customers.notes.destroy', noteId));
    }
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

// タグ追加・編集モーダルを閉じる
const closeTagModal = () => {
    showTagModal.value = false;
    editingTag.value = null;
    tagForm.reset();
};

// タグ追加モーダルを開く
const openTagModal = () => {
    editingTag.value = null;
    tagForm.reset();
    showTagModal.value = true;
};

// タグ編集モーダルを開く（タグクリック時）
const openEditTagModal = (tag) => {
    editingTag.value = tag;
    tagForm.customer_tag_id = tag.id;
    tagForm.note = tag.pivot?.note ?? '';
    tagForm.clearErrors();
    showTagModal.value = true;
};

// タグを追加または更新
const attachTag = () => {
    tagForm.post(route('admin.customers.attach-tag', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeTagModal();
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

/* タグ付け理由・補足ツールチップ（おしゃれ表示・改行反映・overflow の外でも表示） */
.tag-note-tooltip__inner {
    position: relative;
    width: 100%;
    background: linear-gradient(145deg, rgba(30, 41, 59, 0.98) 0%, rgba(15, 23, 42, 0.99) 100%);
    backdrop-filter: blur(12px);
    border-radius: 12px;
    box-shadow:
        0 25px 50px -12px rgba(0, 0, 0, 0.35),
        0 0 0 1px rgba(255, 255, 255, 0.06) inset,
        0 0 0 1px rgba(99, 102, 241, 0.2);
    overflow: hidden;
}

.tag-note-tooltip__header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    background: linear-gradient(90deg, rgba(99, 102, 241, 0.2) 0%, transparent 100%);
    border-bottom: 1px solid rgba(148, 163, 184, 0.2);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.025em;
    color: rgba(203, 213, 225, 0.95);
}

.tag-note-tooltip__body {
    padding: 1rem 1.25rem;
    max-height: 12rem;
    overflow-y: auto;
}

.tag-note-tooltip__text {
    margin: 0;
    font-size: 0.875rem;
    line-height: 1.6;
    color: rgba(226, 232, 240, 0.95);
    white-space: pre-wrap;
    word-break: break-word;
}

.tag-note-tooltip__arrow {
    position: absolute;
    left: 50%;
    top: 100%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border: 8px solid transparent;
    border-top-color: rgba(15, 23, 42, 0.99);
    filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.2));
}
</style>

