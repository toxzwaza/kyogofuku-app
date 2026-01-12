<template>
    <Head title="イベント詳細" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">イベント詳細</h2>
                <ActionButton variant="back" label="イベント一覧に戻る" :href="route('admin.events.index')" />
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

                        <div class="space-y-6">
                            <!-- 操作ボタン -->
                            <div class="border-b border-gray-200 pb-6">
                                <h3 class="text-lg font-semibold mb-4 text-gray-800">操作</h3>
                                <div class="flex flex-wrap gap-3">
                                    <Link
                                        :href="route('event.show', event.slug)"
                                        target="_blank"
                                        class="group relative inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-sm hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                        公開ページを表示
                                    </Link>
                                    <Link
                                        :href="route('admin.events.images.index', event.id)"
                                        class="group relative inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-emerald-600 to-emerald-700 rounded-lg shadow-sm hover:from-emerald-700 hover:to-emerald-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-all duration-200"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        画像管理
                                    </Link>
                                    <Link
                                        :href="route('admin.events.reservations.index', event.id)"
                                        class="group relative inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        予約一覧
                                    </Link>
                                    <Link
                                        :href="route('admin.events.timeslots.index', event.id)"
                                        class="group relative inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-purple-600 to-purple-700 rounded-lg shadow-sm hover:from-purple-700 hover:to-purple-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-all duration-200"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        枠管理
                                    </Link>
                                </div>
                            </div>

                            <!-- 基本情報 -->
                            <div>
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold">基本情報</h3>
                                    <button
                                        v-if="!isEditing"
                                        @click="startEdit"
                                        class="group relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                    >
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        編集
                                    </button>
                                </div>

                                <div v-if="!isEditing">
                                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">ID</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ event.id }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">タイトル</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ event.title }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">スラッグ</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ event.slug }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">フォーム種別</dt>
                                            <dd class="mt-1">
                                                <span class="px-2 py-1 text-xs rounded-full" :class="{
                                                    'bg-blue-100 text-blue-800': event.form_type === 'reservation',
                                                    'bg-green-100 text-green-800': event.form_type === 'document',
                                                    'bg-purple-100 text-purple-800': event.form_type === 'contact',
                                                }">
                                                    {{ getFormTypeLabel(event.form_type) }}
                                                </span>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">受付開始日</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ formatDate(event.start_at) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">受付終了日</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ formatDate(event.end_at) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">GTM ID</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ event.gtm_id || '未設定' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">成功ページURLテキスト</dt>
                                            <dd class="mt-1 text-sm text-gray-900">
                                                <span v-if="event.success_text">{{ event.success_text }}</span>
                                                <span v-else class="text-gray-400">未設定</span>
                                            </dd>
                                        </div>
                                        
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">公開状態</dt>
                                            <dd class="mt-1">
                                                <span class="px-2 py-1 text-xs rounded-full" :class="event.is_public ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                                                    {{ event.is_public ? '公開' : '非公開' }}
                                                </span>
                                            </dd>
                                        </div>

                                    </dl>
                                </div>

                                <!-- 編集フォーム -->
                                <div v-else class="bg-gray-50 rounded-lg p-4">
                                    <form @submit.prevent="updateEvent">
                                        <div class="space-y-4">
                                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">タイトル <span class="text-red-500">*</span></label>
                                                    <input
                                                        v-model="editForm.title"
                                                        type="text"
                                                        required
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    />
                                                    <div v-if="editForm.errors.title" class="mt-1 text-sm text-red-600">{{ editForm.errors.title }}</div>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">スラッグ <span class="text-red-500">*</span></label>
                                                    <input
                                                        v-model="editForm.slug"
                                                        type="text"
                                                        required
                                                        @input="validateSlug"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        :class="slugError ? 'border-red-500' : ''"
                                                        placeholder="例: event-slug-2024"
                                                    />
                                                    <p class="mt-1 text-xs text-gray-500">URLに使用される文字列です。英数字、ハイフン(-)、アンダースコア(_)のみ使用可能です。</p>
                                                    <div v-if="slugError" class="mt-1 text-sm text-red-600">{{ slugError }}</div>
                                                    <div v-if="editForm.errors.slug" class="mt-1 text-sm text-red-600">{{ editForm.errors.slug }}</div>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">フォーム種別 <span class="text-red-500">*</span></label>
                                                    <select
                                                        v-model="editForm.form_type"
                                                        required
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    >
                                                        <option value="reservation">予約</option>
                                                        <option value="document">資料請求</option>
                                                        <option value="contact">問い合わせ</option>
                                                    </select>
                                                    <div v-if="editForm.errors.form_type" class="mt-1 text-sm text-red-600">{{ editForm.errors.form_type }}</div>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">受付開始日</label>
                                                    <input
                                                        v-model="editForm.start_at"
                                                        type="date"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    />
                                                    <div v-if="editForm.errors.start_at" class="mt-1 text-sm text-red-600">{{ editForm.errors.start_at }}</div>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">受付終了日</label>
                                                    <input
                                                        v-model="editForm.end_at"
                                                        type="date"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    />
                                                    <div v-if="editForm.errors.end_at" class="mt-1 text-sm text-red-600">{{ editForm.errors.end_at }}</div>
                                                </div>

                                                <div>
                                                    <label class="flex items-center">
                                                        <input
                                                            v-model="editForm.is_public"
                                                            type="checkbox"
                                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                        <span class="ml-2 text-sm text-gray-700">公開</span>
                                                    </label>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">GTM ID</label>
                                                    <input
                                                        v-model="editForm.gtm_id"
                                                        type="text"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        placeholder="例: GTM-5NP4QCSB"
                                                    />
                                                    <p class="mt-1 text-xs text-gray-500">Google Tag Manager IDを入力してください（任意）</p>
                                                    <div v-if="editForm.errors.gtm_id" class="mt-1 text-sm text-red-600">{{ editForm.errors.gtm_id }}</div>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">成功ページURLテキスト</label>
                                                    <input
                                                        v-model="editForm.success_text"
                                                        type="text"
                                                        @input="validateSuccessText"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        :class="successTextError ? 'border-red-500' : ''"
                                                    />
                                                    <p class="mt-1 text-xs text-gray-500">成功ページのURLに含めるテキストを入力してください（任意）。設定すると `/event/{event}/reserve/success/{text}` の形式になります。英数字、ハイフン、アンダースコアのみ使用可能です。</p>
                                                    <div v-if="successTextError" class="mt-1 text-sm text-red-600">{{ successTextError }}</div>
                                                    <div v-if="editForm.errors.success_text" class="mt-1 text-sm text-red-600">{{ editForm.errors.success_text }}</div>
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                                                <textarea
                                                    v-model="editForm.description"
                                                    rows="4"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                ></textarea>
                                                <div v-if="editForm.errors.description" class="mt-1 text-sm text-red-600">{{ editForm.errors.description }}</div>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">開催店舗</label>
                                                <div class="space-y-2 max-h-48 overflow-y-auto border border-gray-200 rounded-md p-3">
                                                    <label
                                                        v-for="shop in allShops"
                                                        :key="shop.id"
                                                        class="flex items-center"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            :value="shop.id"
                                                            v-model="editForm.shop_ids"
                                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                        <span class="ml-2 text-sm text-gray-700">{{ shop.name }}</span>
                                                    </label>
                                                </div>
                                                <div v-if="editForm.errors.shop_ids" class="mt-1 text-sm text-red-600">{{ editForm.errors.shop_ids }}</div>
                                            </div>

                                            <div class="flex justify-end space-x-3">
                                                <button
                                                    type="button"
                                                    @click="cancelEdit"
                                                    class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                                >
                                                    キャンセル
                                                </button>
                                                <button
                                                    type="submit"
                                                    :disabled="editForm.processing"
                                                    class="px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed transition-all duration-200"
                                                >
                                                    <span v-if="editForm.processing" class="inline-flex items-center">
                                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        更新中...
                                                    </span>
                                                    <span v-else class="inline-flex items-center">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        更新
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- 説明（編集モードでない場合のみ表示） -->
                            <div v-if="!isEditing && event.description">
                                <h3 class="text-lg font-semibold mb-4">説明</h3>
                                <div class="text-sm text-gray-900" v-html="event.description"></div>
                            </div>

                            <!-- 開催店舗（編集モードでない場合のみ表示） -->
                            <div v-if="!isEditing && event.shops && event.shops.length > 0">
                                <h3 class="text-lg font-semibold mb-4">開催店舗</h3>
                                <div class="space-y-2">
                                    <div v-for="shop in event.shops" :key="shop.id" class="text-sm text-gray-900">
                                        {{ shop.name }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="!isEditing && (!event.shops || event.shops.length === 0)" class="text-sm text-gray-500">
                                開催店舗が登録されていません。
                            </div>

                            <!-- 資料管理（資料請求フォームの場合のみ） -->
                            <div v-if="event.form_type === 'document'" class="border-t border-gray-200 pt-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold">資料管理</h3>
                                    <button
                                        @click="showAddDocumentForm = !showAddDocumentForm"
                                        class="group relative inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                    >
                                        <svg v-if="!showAddDocumentForm" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        {{ showAddDocumentForm ? 'キャンセル' : '資料を追加' }}
                                    </button>
                                </div>

                                <!-- 資料追加フォーム -->
                                <div v-if="showAddDocumentForm" class="mb-6 p-4 bg-gray-50 rounded-lg">
                                    <form @submit.prevent="uploadDocument">
                                        <div class="space-y-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">資料名 <span class="text-red-500">*</span></label>
                                                <input
                                                    v-model="newDocument.name"
                                                    type="text"
                                                    required
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="資料の名前を入力"
                                                />
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                                                <textarea
                                                    v-model="newDocument.description"
                                                    rows="2"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="資料の説明を入力（任意）"
                                                ></textarea>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">PDFファイル <span class="text-red-500">*</span></label>
                                                <input
                                                    ref="pdfFileInput"
                                                    type="file"
                                                    accept=".pdf"
                                                    @change="handlePdfFileChange"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                                <p class="mt-1 text-xs text-gray-500">PDFファイル（最大10MB）</p>
                                                <div v-if="newDocument.pdfFile" class="mt-2 p-2 bg-gray-100 rounded">
                                                    <p class="text-sm text-gray-700">{{ newDocument.pdfFile.name }}</p>
                                                    <button
                                                        type="button"
                                                        @click="removePdfFile"
                                                        class="mt-1 text-xs text-red-600 hover:text-red-800"
                                                    >
                                                        削除
                                                    </button>
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">サムネイル画像（任意）</label>
                                                <input
                                                    ref="thumbnailFileInput"
                                                    type="file"
                                                    accept="image/*"
                                                    @change="handleThumbnailFileChange"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                                <p class="mt-1 text-xs text-gray-500">サムネイル画像（JPEG、PNG、GIF、最大2MB）</p>
                                                <div v-if="newDocument.thumbnailPreview" class="mt-2">
                                                    <img
                                                        :src="newDocument.thumbnailPreview"
                                                        alt="サムネイルプレビュー"
                                                        class="w-32 h-32 object-cover border border-gray-300 rounded"
                                                    />
                                                    <button
                                                        type="button"
                                                        @click="removeThumbnailFile"
                                                        class="mt-1 text-xs text-red-600 hover:text-red-800"
                                                    >
                                                        削除
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="flex justify-end space-x-3">
                                                <button
                                                    type="button"
                                                    @click="showAddDocumentForm = false"
                                                    class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                                >
                                                    キャンセル
                                                </button>
                                                <button
                                                    type="submit"
                                                    :disabled="uploadingDocument || !newDocument.name || !newDocument.pdfFile"
                                                    class="px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed transition-all duration-200"
                                                >
                                                    <span v-if="uploadingDocument" class="inline-flex items-center">
                                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        アップロード中...
                                                    </span>
                                                    <span v-else class="inline-flex items-center">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                        追加
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- 既存資料から選択（編集モードの場合のみ） -->
                                <div v-if="isEditing" class="mb-6 p-4 border border-gray-300 rounded-lg">
                                    <h4 class="text-sm font-medium text-gray-700 mb-3">既存資料から選択</h4>
                                    <div v-if="documents.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                        <label
                                            v-for="document in documents"
                                            :key="document.id"
                                            class="relative cursor-pointer border-2 rounded-lg overflow-hidden transition-all"
                                            :class="editForm.document_ids.includes(document.id) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-gray-400'"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="document.id"
                                                v-model="editForm.document_ids"
                                                class="absolute top-2 right-2 w-5 h-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                                            />
                                            <div class="aspect-square bg-gray-100 flex items-center justify-center">
                                                <img
                                                    v-if="document.thumbnail_url"
                                                    :src="document.thumbnail_url"
                                                    :alt="document.name"
                                                    class="w-full h-full object-cover"
                                                />
                                                <div v-else class="text-gray-400 text-xs text-center p-2">
                                                    <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                    PDF
                                                </div>
                                            </div>
                                            <div class="p-2 bg-white">
                                                <p class="text-xs font-medium text-gray-900 line-clamp-2">{{ document.name }}</p>
                                            </div>
                                        </label>
                                    </div>
                                    <p v-else class="text-sm text-gray-500">既存の資料がありません</p>
                                    <div v-if="editForm.errors.document_ids" class="mt-1 text-sm text-red-600">{{ editForm.errors.document_ids }}</div>
                                </div>

                                <!-- 資料一覧 -->
                                <div v-if="event.documents && event.documents.length > 0" class="space-y-4">
                                    <div
                                        v-for="document in event.documents"
                                        :key="document.id"
                                        class="p-4 border border-gray-200 rounded-lg"
                                    >
                                        <div v-if="!editingDocuments[document.id]">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1 flex items-start space-x-4">
                                                    <!-- サムネイル画像 -->
                                                    <div class="flex-shrink-0">
                                                        <div class="w-24 h-24 bg-gray-100 rounded-lg overflow-hidden flex items-center justify-center">
                                                            <img
                                                                v-if="document.thumbnail_url"
                                                                :src="document.thumbnail_url"
                                                                :alt="document.name"
                                                                class="w-full h-full object-cover"
                                                            />
                                                            <div v-else class="text-gray-400 text-xs text-center p-2">
                                                                <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                                </svg>
                                                                PDF
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- 資料情報 -->
                                                    <div class="flex-1">
                                                        <h4 class="font-semibold text-gray-900">{{ document.name }}</h4>
                                                        <p v-if="document.description" class="text-sm text-gray-600 mt-1">{{ document.description }}</p>
                                                        <div class="mt-2 flex items-center space-x-4">
                                                            <div v-if="document.pdf_path" class="text-xs text-gray-500">
                                                                PDF: あり
                                                            </div>
                                                            <div v-if="document.thumbnail_path" class="text-xs text-gray-500">
                                                                サムネイル: あり
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex space-x-2 ml-4">
                                                    <button
                                                        @click="startEditDocument(document)"
                                                        class="group relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                                    >
                                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        編集
                                                    </button>
                                                    <button
                                                        @click="deleteDocument(document.id)"
                                                        class="group relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 rounded-lg shadow-sm hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                                                    >
                                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                        削除
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="space-y-4">
                                            <form @submit.prevent="updateDocument(document.id)">
                                                <div class="space-y-3">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">資料名 <span class="text-red-500">*</span></label>
                                                        <input
                                                            v-model="editingDocumentForms[document.id].name"
                                                            type="text"
                                                            required
                                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                                                        <textarea
                                                            v-model="editingDocumentForms[document.id].description"
                                                            rows="2"
                                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        ></textarea>
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">PDFファイル（変更する場合のみ）</label>
                                                        <input
                                                            type="file"
                                                            accept=".pdf"
                                                            @change="(e) => editingDocumentForms[document.id].pdfFile = e.target.files[0]"
                                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                        <p class="mt-1 text-xs text-gray-500">PDFファイル（最大10MB）</p>
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">サムネイル画像（変更する場合のみ）</label>
                                                        <input
                                                            type="file"
                                                            accept="image/*"
                                                            @change="(e) => {
                                                                const file = e.target.files[0];
                                                                if (file) {
                                                                    editingDocumentForms[document.id].thumbnailFile = file;
                                                                    const reader = new FileReader();
                                                                    reader.onload = (ev) => {
                                                                        editingDocumentForms[document.id].thumbnailPreview = ev.target.result;
                                                                    };
                                                                    reader.readAsDataURL(file);
                                                                }
                                                            }"
                                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                        <p class="mt-1 text-xs text-gray-500">サムネイル画像（JPEG、PNG、GIF、最大2MB）</p>
                                                        <div v-if="editingDocumentForms[document.id].thumbnailPreview" class="mt-2">
                                                            <img
                                                                :src="editingDocumentForms[document.id].thumbnailPreview"
                                                                alt="サムネイルプレビュー"
                                                                class="w-32 h-32 object-cover border border-gray-300 rounded"
                                                            />
                                                        </div>
                                                        <div v-if="document.thumbnail_path" class="mt-2">
                                                            <label class="flex items-center">
                                                                <input
                                                                    v-model="editingDocumentForms[document.id].removeThumbnail"
                                                                    type="checkbox"
                                                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                />
                                                                <span class="ml-2 text-sm text-gray-700">サムネイルを削除</span>
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <div class="flex justify-end space-x-3">
                                                        <button
                                                            type="button"
                                                            @click="cancelEditDocument(document.id)"
                                                            class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                                        >
                                                            キャンセル
                                                        </button>
                                                        <button
                                                            type="submit"
                                                            :disabled="editingDocumentForms[document.id].processing"
                                                            class="px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed transition-all duration-200"
                                                        >
                                                            <span v-if="editingDocumentForms[document.id].processing" class="inline-flex items-center">
                                                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                </svg>
                                                                更新中...
                                                            </span>
                                                            <span v-else class="inline-flex items-center">
                                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                                </svg>
                                                                更新
                                                            </span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-gray-500">
                                    資料が登録されていません。
                                </div>
                            </div>

                            <!-- 会場管理（予約フォームの場合のみ） -->
                            <div v-if="event.form_type === 'reservation'" class="border-t border-gray-200 pt-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold">会場管理</h3>
                                    <button
                                        @click="showAddVenueForm = !showAddVenueForm"
                                        class="group relative inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                    >
                                        <svg v-if="!showAddVenueForm" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        {{ showAddVenueForm ? 'キャンセル' : '会場を追加' }}
                                    </button>
                                </div>

                                <!-- 会場追加フォーム -->
                                <div v-if="showAddVenueForm" class="mb-6 p-4 bg-gray-50 rounded-lg">
                                    <form @submit.prevent="addVenue">
                                        <div class="space-y-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">会場名 <span class="text-red-500">*</span></label>
                                                <input
                                                    v-model="newVenueForm.name"
                                                    type="text"
                                                    list="venue-list"
                                                    required
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="既存の会場を選択するか、新規会場名を入力"
                                                />
                                                <datalist id="venue-list">
                                                    <option v-for="venue in allVenues" :key="venue.id" :value="venue.name">
                                                        {{ venue.name }}
                                                    </option>
                                                </datalist>
                                                <div v-if="newVenueForm.errors.name" class="mt-1 text-sm text-red-600">{{ newVenueForm.errors.name }}</div>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                                                <textarea
                                                    v-model="newVenueForm.description"
                                                    rows="2"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                ></textarea>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">住所</label>
                                                <input
                                                    v-model="newVenueForm.address"
                                                    type="text"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">電話番号</label>
                                                <input
                                                    v-model="newVenueForm.phone"
                                                    type="tel"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </div>

                                            <div class="flex justify-end space-x-3">
                                                <button
                                                    type="button"
                                                    @click="showAddVenueForm = false"
                                                    class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                                >
                                                    キャンセル
                                                </button>
                                                <button
                                                    type="submit"
                                                    :disabled="newVenueForm.processing"
                                                    class="px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed transition-all duration-200"
                                                >
                                                    <span v-if="newVenueForm.processing" class="inline-flex items-center">
                                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        追加中...
                                                    </span>
                                                    <span v-else class="inline-flex items-center">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                        追加
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- 会場一覧 -->
                                <div v-if="event.venues && event.venues.length > 0" class="space-y-4">
                                    <div
                                        v-for="venue in event.venues"
                                        :key="venue.id"
                                        class="p-4 border border-gray-200 rounded-lg"
                                    >
                                        <div v-if="!editingVenues[venue.id]">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <h4 class="font-semibold text-gray-900">{{ venue.name }}</h4>
                                                    <p v-if="venue.description" class="text-sm text-gray-600 mt-1">{{ venue.description }}</p>
                                                    <p v-if="venue.address" class="text-sm text-gray-600 mt-1">{{ venue.address }}</p>
                                                    <p v-if="venue.phone" class="text-sm text-gray-600 mt-1">{{ venue.phone }}</p>
                                                    <span class="inline-block mt-2 px-2 py-1 text-xs rounded-full" :class="venue.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                                                        {{ venue.is_active ? '有効' : '無効' }}
                                                    </span>
                                                </div>
                                                <div class="flex space-x-2 ml-4">
                                                    <button
                                                        @click="startEditVenue(venue)"
                                                        class="group relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                                    >
                                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        編集
                                                    </button>
                                                    <button
                                                        @click="deleteVenue(venue.id)"
                                                        class="group relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 rounded-lg shadow-sm hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                                                    >
                                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                        削除
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="space-y-4">
                                            <form @submit.prevent="updateVenue(venue.id)">
                                                <div class="space-y-3">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">会場名 <span class="text-red-500">*</span></label>
                                                        <input
                                                            v-model="editingVenueForms[venue.id].name"
                                                            type="text"
                                                            required
                                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                                                        <textarea
                                                            v-model="editingVenueForms[venue.id].description"
                                                            rows="2"
                                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        ></textarea>
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">住所</label>
                                                        <input
                                                            v-model="editingVenueForms[venue.id].address"
                                                            type="text"
                                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">電話番号</label>
                                                        <input
                                                            v-model="editingVenueForms[venue.id].phone"
                                                            type="tel"
                                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                    </div>

                                                    <div>
                                                        <label class="flex items-center">
                                                            <input
                                                                v-model="editingVenueForms[venue.id].is_active"
                                                                type="checkbox"
                                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            />
                                                            <span class="ml-2 text-sm text-gray-700">有効</span>
                                                        </label>
                                                    </div>

                                                    <div class="flex justify-end space-x-3">
                                                        <button
                                                            type="button"
                                                            @click="cancelEditVenue(venue.id)"
                                                            class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                                        >
                                                            キャンセル
                                                        </button>
                                                        <button
                                                            type="submit"
                                                            :disabled="editingVenueForms[venue.id].processing"
                                                            class="px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed transition-all duration-200"
                                                        >
                                                            <span v-if="editingVenueForms[venue.id].processing" class="inline-flex items-center">
                                                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                </svg>
                                                                更新中...
                                                            </span>
                                                            <span v-else class="inline-flex items-center">
                                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                                </svg>
                                                                更新
                                                            </span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-gray-500">
                                    会場が登録されていません。
                                </div>
                            </div>

                            <!-- 統計情報 -->
                            <div>
                                <h3 class="text-lg font-semibold mb-4">統計情報</h3>
                                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">画像数</dt>
                                        <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ event.images ? event.images.length : 0 }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">予約枠数</dt>
                                        <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ event.timeslots ? event.timeslots.length : 0 }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">予約数</dt>
                                        <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ event.reservations ? event.reservations.length : 0 }}</dd>
                                    </div>
                                </dl>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    event: Object,
    allVenues: Array,
    allShops: Array,
    allDocuments: Array,
});

const isEditing = ref(false);
const showAddVenueForm = ref(false);
const editingVenues = reactive({});
const editingVenueForms = reactive({});
const showAddDocumentForm = ref(false);
const editingDocuments = reactive({});
const editingDocumentForms = reactive({});
const documents = ref(props.allDocuments || []);
const successTextError = ref('');
const slugError = ref('');

// 資料一覧を更新
const refreshDocuments = async () => {
    try {
        const response = await axios.get(route('admin.documents.index'));
        documents.value = response.data || [];
    } catch (error) {
        console.error('資料一覧の取得に失敗しました:', error);
    }
};
const pdfFileInput = ref(null);
const thumbnailFileInput = ref(null);
const uploadingDocument = ref(false);
const newDocument = ref({
    name: '',
    description: '',
    pdfFile: null,
    thumbnailFile: null,
    thumbnailPreview: null,
});

const editForm = useForm({
    title: props.event.title,
    slug: props.event.slug || '',
    description: props.event.description || '',
    form_type: props.event.form_type,
    start_at: props.event.start_at ? new Date(props.event.start_at).toISOString().split('T')[0] : '',
    end_at: props.event.end_at ? new Date(props.event.end_at).toISOString().split('T')[0] : '',
    is_public: props.event.is_public,
    shop_ids: props.event.shops ? props.event.shops.map(shop => shop.id) : [],
    document_ids: props.event.documents ? props.event.documents.map(doc => doc.id) : [],
    gtm_id: props.event.gtm_id || '',
    success_text: props.event.success_text || '',
});

const newVenueForm = useForm({
    name: '',
    description: '',
    address: '',
    phone: '',
    is_active: true,
});

const addVenue = () => {
    // datalistから選択された既存会場かチェック
    const existingVenue = props.allVenues.find(v => v.name === newVenueForm.name);
    
    if (existingVenue) {
        // 既存会場を関連付け
        router.post(route('admin.events.venues.store', props.event.id), {
            venue_id: existingVenue.id,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showAddVenueForm.value = false;
                newVenueForm.reset();
            },
        });
    } else if (newVenueForm.name) {
        // 新規会場を作成
        newVenueForm.post(route('admin.events.venues.store', props.event.id), {
            preserveScroll: true,
            onSuccess: () => {
                showAddVenueForm.value = false;
                newVenueForm.reset();
            },
        });
    }
};

const startEditVenue = (venue) => {
    editingVenues[venue.id] = true;
    editingVenueForms[venue.id] = useForm({
        name: venue.name,
        description: venue.description || '',
        address: venue.address || '',
        phone: venue.phone || '',
        is_active: venue.is_active,
    });
};

const cancelEditVenue = (venueId) => {
    editingVenues[venueId] = false;
    delete editingVenueForms[venueId];
};

const updateVenue = (venueId) => {
    editingVenueForms[venueId].transform((data) => ({
        ...data,
        _method: 'PUT',
    })).post(route('admin.venues.update', venueId), {
        preserveScroll: true,
        onSuccess: () => {
            editingVenues[venueId] = false;
            delete editingVenueForms[venueId];
        },
    });
};

const deleteVenue = (venueId) => {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('admin.events.venues.destroy', [props.event.id, venueId]), {
            preserveScroll: true,
        });
    }
};

const startEdit = () => {
    isEditing.value = true;
    editForm.title = props.event.title;
    editForm.slug = props.event.slug || '';
    editForm.description = props.event.description || '';
    editForm.form_type = props.event.form_type;
    editForm.start_at = props.event.start_at ? new Date(props.event.start_at).toISOString().split('T')[0] : '';
    editForm.end_at = props.event.end_at ? new Date(props.event.end_at).toISOString().split('T')[0] : '';
    editForm.is_public = props.event.is_public;
    editForm.shop_ids = props.event.shops ? props.event.shops.map(shop => shop.id) : [];
    editForm.document_ids = props.event.documents ? props.event.documents.map(doc => doc.id) : [];
    editForm.gtm_id = props.event.gtm_id || '';
    editForm.success_text = props.event.success_text || '';
    successTextError.value = '';
    slugError.value = '';
};

const cancelEdit = () => {
    isEditing.value = false;
    editForm.reset();
    editForm.clearErrors();
    successTextError.value = '';
    slugError.value = '';
};

// slugのバリデーション
const validateSlug = () => {
    slugError.value = '';
    
    if (!editForm.slug) {
        return;
    }
    
    // マルチバイト文字のチェック
    if (/[^\x00-\x7F]/.test(editForm.slug)) {
        slugError.value = 'スラッグには英数字、ハイフン、アンダースコアのみ使用できます。マルチバイト文字は使用できません。';
        return;
    }
    
    // 英数字、ハイフン、アンダースコアのみ許可
    if (!/^[a-zA-Z0-9_-]+$/.test(editForm.slug)) {
        slugError.value = 'スラッグには英数字、ハイフン(-)、アンダースコア(_)のみ使用できます。';
        return;
    }
};

// success_textのバリデーション
const validateSuccessText = () => {
    successTextError.value = '';
    
    if (!editForm.success_text) {
        return;
    }
    
    // マルチバイト文字のチェック
    if (/[^\x00-\x7F]/.test(editForm.success_text)) {
        successTextError.value = '成功ページURLテキストには英数字、ハイフン、アンダースコアのみ使用できます。マルチバイト文字は使用できません。';
        return;
    }
    
    // 英数字、ハイフン、アンダースコアのみ許可
    if (!/^[a-zA-Z0-9_-]+$/.test(editForm.success_text)) {
        successTextError.value = '成功ページURLテキストには英数字、ハイフン(-)、アンダースコア(_)のみ使用できます。';
        return;
    }
};

const updateEvent = () => {
    // slugのバリデーション
    validateSlug();
    if (slugError.value) {
        return;
    }
    
    // success_textのバリデーション
    validateSuccessText();
    if (successTextError.value) {
        return;
    }
    
    editForm.transform((data) => ({
        ...data,
        _method: 'PUT',
    })).post(route('admin.events.update', props.event.id), {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
            successTextError.value = '';
            slugError.value = '';
            // 資料一覧を更新
            refreshDocuments();
        },
    });
};

const getFormTypeLabel = (formType) => {
    const labels = {
        reservation: '予約',
        document: '資料請求',
        contact: '問い合わせ',
    };
    return labels[formType] || formType;
};

const formatDate = (date) => {
    if (!date) {
        return '常時受付中';
    }
    const d = new Date(date);
    return d.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

// 資料管理機能
const handlePdfFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        newDocument.value.pdfFile = file;
    }
};

const removePdfFile = () => {
    newDocument.value.pdfFile = null;
    if (pdfFileInput.value) {
        pdfFileInput.value.value = '';
    }
};

const handleThumbnailFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        newDocument.value.thumbnailFile = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            newDocument.value.thumbnailPreview = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const removeThumbnailFile = () => {
    newDocument.value.thumbnailFile = null;
    newDocument.value.thumbnailPreview = null;
    if (thumbnailFileInput.value) {
        thumbnailFileInput.value.value = '';
    }
};

const uploadDocument = async () => {
    if (!newDocument.value.name || !newDocument.value.pdfFile) {
        return;
    }

    uploadingDocument.value = true;

    try {
        const formData = new FormData();
        formData.append('name', newDocument.value.name);
        formData.append('description', newDocument.value.description || '');
        formData.append('pdf_file', newDocument.value.pdfFile);
        
        if (newDocument.value.thumbnailFile) {
            formData.append('thumbnail_file', newDocument.value.thumbnailFile);
        }

        const response = await axios.post(route('admin.documents.store'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        if (response.data.success) {
            // 資料一覧を更新
            await refreshDocuments();
            // フォームに選択状態で追加
            if (!editForm.document_ids.includes(response.data.document.id)) {
                editForm.document_ids.push(response.data.document.id);
            }
            // フォームをリセット
            newDocument.value = {
                name: '',
                description: '',
                pdfFile: null,
                thumbnailFile: null,
                thumbnailPreview: null,
            };
            if (pdfFileInput.value) {
                pdfFileInput.value.value = '';
            }
            if (thumbnailFileInput.value) {
                thumbnailFileInput.value.value = '';
            }
            showAddDocumentForm.value = false;
        }
    } catch (error) {
        console.error('資料のアップロードに失敗しました:', error);
        alert('資料のアップロードに失敗しました。');
    } finally {
        uploadingDocument.value = false;
    }
};

const startEditDocument = (document) => {
    editingDocuments[document.id] = true;
    editingDocumentForms[document.id] = useForm({
        name: document.name,
        description: document.description || '',
        pdfFile: null,
        thumbnailFile: null,
        thumbnailPreview: document.thumbnail_url || null,
        removeThumbnail: false,
    });
};

const cancelEditDocument = (documentId) => {
    editingDocuments[documentId] = false;
    delete editingDocumentForms[documentId];
};

const updateDocument = async (documentId) => {
    const form = editingDocumentForms[documentId];
    
    try {
        const formData = new FormData();
        formData.append('name', form.name);
        formData.append('description', form.description || '');
        formData.append('_method', 'PUT');
        
        if (form.pdfFile) {
            formData.append('pdf_file', form.pdfFile);
        }
        
        if (form.thumbnailFile) {
            formData.append('thumbnail_file', form.thumbnailFile);
        }
        
        if (form.removeThumbnail) {
            formData.append('remove_thumbnail', '1');
        }

        const response = await axios.post(route('admin.documents.update', documentId), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        if (response.data.success) {
            // 資料一覧を更新
            await refreshDocuments();
            // イベントの資料も更新
            const eventDocIndex = props.event.documents.findIndex(d => d.id === documentId);
            if (eventDocIndex !== -1) {
                props.event.documents[eventDocIndex] = response.data.document;
            }
            editingDocuments[documentId] = false;
            delete editingDocumentForms[documentId];
        }
    } catch (error) {
        console.error('資料の更新に失敗しました:', error);
        alert('資料の更新に失敗しました。');
    }
};

const deleteDocument = async (documentId) => {
    if (!confirm('本当に削除しますか？')) {
        return;
    }

    try {
        const response = await axios.delete(route('admin.documents.destroy', documentId));

        if (response.data.success) {
            // 資料一覧を更新
            await refreshDocuments();
            // フォームからも削除
            editForm.document_ids = editForm.document_ids.filter(id => id !== documentId);
            // イベントの資料からも削除
            if (props.event.documents) {
                props.event.documents = props.event.documents.filter(d => d.id !== documentId);
            }
        }
    } catch (error) {
        console.error('資料の削除に失敗しました:', error);
        alert('資料の削除に失敗しました。');
    }
};
</script>

