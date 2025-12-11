<template>
    <form @submit.prevent="submit">
        <h2 class="text-2xl font-bold mb-6">資料請求フォーム</h2>

        <div class="space-y-4">
            <!-- 資料選択 -->
            <div v-if="documents && documents.length > 0">
                <label class="block text-sm font-medium text-gray-700 mb-2">請求する資料を選択 <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                    <label
                        v-for="document in documents"
                        :key="document.id"
                        class="relative cursor-pointer border-2 rounded-lg overflow-hidden transition-all"
                        :class="form.document_id === document.id ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-500' : 'border-gray-300 hover:border-gray-400'"
                    >
                        <input
                            type="radio"
                            :value="document.id"
                            v-model="form.document_id"
                            required
                            class="sr-only"
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
                            <p v-if="document.description" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ document.description }}</p>
                        </div>
                        <div v-if="form.document_id === document.id" class="absolute top-2 right-2 bg-indigo-500 text-white rounded-full p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </label>
                </div>
                <div v-if="form.errors.document_id" class="mt-1 text-sm text-red-600">{{ form.errors.document_id }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">請求方法 <span class="text-red-500">*</span></label>
                <select
                    v-model="form.request_method"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">選択してください</option>
                    <option value="郵送">郵送</option>
                    <option value="デジタルカタログ">デジタルカタログ</option>
                </select>
                <div v-if="form.errors.request_method" class="mt-1 text-sm text-red-600">{{ form.errors.request_method }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">お名前 <span class="text-red-500">*</span></label>
                <input
                    v-model="form.name"
                    type="text"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">フリガナ</label>
                <input
                    v-model="form.furigana"
                    type="text"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <div v-if="form.errors.furigana" class="mt-1 text-sm text-red-600">{{ form.errors.furigana }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">生年月日</label>
                <input
                    v-model="form.birth_date"
                    type="date"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <div v-if="form.errors.birth_date" class="mt-1 text-sm text-red-600">{{ form.errors.birth_date }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス <span class="text-red-500">*</span></label>
                <input
                    v-model="form.email"
                    type="email"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">電話番号 <span class="text-red-500">*</span></label>
                <input
                    v-model="form.phone"
                    type="tel"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <div v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ご住所</label>
                <div class="space-y-2">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">郵便番号</label>
                        <input
                            v-model="form.postal_code"
                            type="text"
                            placeholder="例: 700-0012"
                            @blur="searchAddress"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <div v-if="form.errors.postal_code" class="mt-1 text-sm text-red-600">{{ form.errors.postal_code }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">住所</label>
                        <input
                            v-model="form.address"
                            type="text"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <div v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address }}</div>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-gray-200">
                <p class="text-sm text-gray-700 mb-4">
                    個人情報保護方針をお読みいただき、同意いただける場合は、<br>
                    「個人情報の取扱いについて同意する」にチェックを入れて送信をお願いいたします。
                </p>
                <label class="flex items-start">
                    <input
                        v-model="form.privacy_agreed"
                        type="checkbox"
                        required
                        class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <span class="ml-2 text-sm text-gray-700">個人情報の取扱いについて同意する</span>
                </label>
                <div v-if="form.errors.privacy_agreed" class="mt-1 text-sm text-red-600">{{ form.errors.privacy_agreed }}</div>
            </div>

            <div class="pt-4">
                <button
                    type="submit"
                    :disabled="processing || !form.privacy_agreed"
                    class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                >
                    {{ processing ? '送信中...' : '送信内容の確認へ' }}
                </button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    event: Object,
    selectedTimeslot: Object,
    documents: Array,
});

const emit = defineEmits(['submitted', 'confirm']);

const form = useForm({
    document_id: '',
    request_method: '',
    name: '',
    furigana: '',
    birth_date: '',
    email: '',
    phone: '',
    postal_code: '',
    address: '',
    privacy_agreed: false,
});

const processing = ref(false);

const searchAddress = async () => {
    if (!form.postal_code) return;
    
    // ハイフンを除去
    const postalCode = form.postal_code.replace(/-/g, '');
    
    // 7桁の数字かチェック
    if (!/^\d{7}$/.test(postalCode)) {
        return;
    }
    
    try {
        const response = await axios.get(route('api.postal-code.search'), {
            params: {
                postal_code: postalCode,
            },
        });
        
        if (response.data.address) {
            form.address = response.data.address;
        }
    } catch (error) {
        // エラーが発生しても処理を続行
        // ユーザーが手動で住所を入力できる
        if (error.response?.data?.error) {
            const errorMessage = error.response.data.error;
            console.warn('住所の取得に失敗しました:', errorMessage);
            // エラーメッセージをユーザーに表示する場合は、ここで処理
            // 例: alert(errorMessage) や toast など
        } else if (error.message) {
            console.error('住所の取得に失敗しました:', error.message);
        }
    }
};

const submit = () => {
    if (!form.privacy_agreed) {
        return;
    }
    
    // 確認ページに遷移
    emit('confirm', form.data());
};
</script>

