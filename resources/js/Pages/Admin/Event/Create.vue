<template>
    <Head title="イベント追加" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">イベント追加</h2>
                <Link
                    :href="route('admin.events.index')"
                    class="text-indigo-600 hover:text-indigo-900"
                >
                    ← イベント一覧に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">タイトル <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.title"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <div v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                                    <textarea
                                        v-model="form.description"
                                        rows="4"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    ></textarea>
                                    <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">フォーム種別 <span class="text-red-500">*</span></label>
                                    <select
                                        v-model="form.form_type"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="reservation">予約</option>
                                        <option value="document">資料請求</option>
                                        <option value="contact">問い合わせ</option>
                                    </select>
                                    <div v-if="form.errors.form_type" class="mt-1 text-sm text-red-600">{{ form.errors.form_type }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">受付開始日</label>
                                    <input
                                        v-model="form.start_at"
                                        type="date"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p class="mt-1 text-sm text-gray-500">指定した日より予約受付が可能になります</p>
                                    <div v-if="form.errors.start_at" class="mt-1 text-sm text-red-600">{{ form.errors.start_at }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">受付終了日</label>
                                    <input
                                        v-model="form.end_at"
                                        type="date"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p class="mt-1 text-sm text-gray-500">指定した日を過ぎると予約ボタンが非表示になります</p>
                                    <div v-if="form.errors.end_at" class="mt-1 text-sm text-red-600">{{ form.errors.end_at }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">開催店舗</label>
                                    <div class="space-y-2">
                                        <label
                                            v-for="shop in shops"
                                            :key="shop.id"
                                            class="flex items-center"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="shop.id"
                                                v-model="form.shop_ids"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                            <span class="ml-2 text-sm text-gray-700">{{ shop.name }}</span>
                                        </label>
                                    </div>
                                    <div v-if="form.errors.shop_ids" class="mt-1 text-sm text-red-600">{{ form.errors.shop_ids }}</div>
                                </div>

                                <!-- 会場（予約フォームの場合のみ） -->
                                <div v-if="form.form_type === 'reservation'">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">会場</label>
                                    <div class="space-y-4">
                                        <!-- 既存会場の選択 -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-600 mb-2">既存の会場を選択</label>
                                            <div class="space-y-2">
                                                <label
                                                    v-for="venue in venues"
                                                    :key="venue.id"
                                                    class="flex items-center"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :value="venue.id"
                                                        v-model="form.venue_ids"
                                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    />
                                                    <span class="ml-2 text-sm text-gray-700">{{ venue.name }}</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- 新規会場の追加 -->
                                        <div class="border-t border-gray-200 pt-4">
                                            <label class="block text-sm font-medium text-gray-600 mb-2">新規会場を追加</label>
                                            <div class="space-y-3">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">会場名</label>
                                                    <input
                                                        v-model="form.new_venue_name"
                                                        type="text"
                                                        list="venue-list"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        placeholder="既存の会場を選択するか、新規会場名を入力"
                                                    />
                                                    <datalist id="venue-list">
                                                        <option v-for="venue in venues" :key="venue.id" :value="venue.name">
                                                            {{ venue.name }}
                                                        </option>
                                                    </datalist>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                                                    <textarea
                                                        v-model="form.new_venue_description"
                                                        rows="2"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    ></textarea>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">住所</label>
                                                    <input
                                                        v-model="form.new_venue_address"
                                                        type="text"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    />
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">電話番号</label>
                                                    <input
                                                        v-model="form.new_venue_phone"
                                                        type="tel"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="form.errors.venue_ids" class="mt-1 text-sm text-red-600">{{ form.errors.venue_ids }}</div>
                                </div>

                                <!-- 資料管理（資料請求フォームの場合のみ） -->
                                <div v-if="form.form_type === 'document'">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">資料</label>
                                    <div class="space-y-4">
                                        <!-- 新規資料アップロード -->
                                        <div class="border border-gray-300 rounded-lg p-4 bg-gray-50">
                                            <h3 class="text-sm font-medium text-gray-700 mb-3">新規資料をアップロード</h3>
                                            <div class="space-y-3">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">資料名 <span class="text-red-500">*</span></label>
                                                    <input
                                                        v-model="newDocument.name"
                                                        type="text"
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
                                                
                                                <button
                                                    type="button"
                                                    @click="uploadDocument"
                                                    :disabled="uploadingDocument || !newDocument.name || !newDocument.pdfFile"
                                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-sm"
                                                >
                                                    {{ uploadingDocument ? 'アップロード中...' : '資料をアップロード' }}
                                                </button>
                                            </div>
                                        </div>

                                        <!-- 既存資料から選択 -->
                                        <div class="border border-gray-300 rounded-lg p-4">
                                            <h3 class="text-sm font-medium text-gray-700 mb-3">既存資料から選択</h3>
                                            <div v-if="documents.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                                <label
                                                    v-for="document in documents"
                                                    :key="document.id"
                                                    class="relative cursor-pointer border-2 rounded-lg overflow-hidden transition-all"
                                                    :class="form.document_ids.includes(document.id) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-gray-400'"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :value="document.id"
                                                        v-model="form.document_ids"
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
                                        </div>
                                    </div>
                                    <div v-if="form.errors.document_ids" class="mt-1 text-sm text-red-600">{{ form.errors.document_ids }}</div>
                                </div>

                                <div>
                                    <label class="flex items-center">
                                        <input
                                            v-model="form.is_public"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">公開</span>
                                    </label>
                                </div>

                                <div class="flex justify-end space-x-4 pt-4">
                                    <Link
                                        :href="route('admin.events.index')"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                    >
                                        キャンセル
                                    </Link>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                    >
                                        {{ form.processing ? '保存中...' : '保存' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    shops: Array,
    venues: Array,
    documents: Array,
});

const form = useForm({
    title: '',
    description: '',
    form_type: 'reservation',
    start_at: '',
    end_at: '',
    shop_ids: [],
    venue_ids: [],
    document_ids: [],
    new_venue_name: '',
    new_venue_description: '',
    new_venue_address: '',
    new_venue_phone: '',
    is_public: true,
});

const documents = ref(props.documents || []);
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
            // 資料一覧に追加
            documents.value.unshift(response.data.document);
            // フォームに選択状態で追加
            if (!form.document_ids.includes(response.data.document.id)) {
                form.document_ids.push(response.data.document.id);
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
        }
    } catch (error) {
        console.error('資料のアップロードに失敗しました:', error);
        alert('資料のアップロードに失敗しました。');
    } finally {
        uploadingDocument.value = false;
    }
};

const submit = () => {
    // datalistから選択された既存会場かチェック
    if (form.new_venue_name) {
        const existingVenue = props.venues.find(v => v.name === form.new_venue_name);
        if (existingVenue) {
            // 既存会場をvenue_idsに追加
            if (!form.venue_ids.includes(existingVenue.id)) {
                form.venue_ids.push(existingVenue.id);
            }
            // 新規会場情報をクリア
            form.new_venue_name = '';
            form.new_venue_description = '';
            form.new_venue_address = '';
            form.new_venue_phone = '';
        }
    }
    
    form.post(route('admin.events.store'));
};
</script>

