<template>
    <form @submit.prevent="submit">
        <h2 class="text-2xl font-bold mb-6">お問い合わせフォーム</h2>

        <div class="space-y-6">
            <!-- 送信先店舗 -->
            <div v-if="shops && shops.length > 0">
                <h2 class="text-xl font-semibold mb-4">送信先店舗</h2>
                <div class="space-y-6">
                    <div v-for="shop in shops" :key="shop.id" class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                        <!-- 画像とテキストのグリッドレイアウト -->
                        <div class="md:flex">
                            <!-- テキスト情報（左側または上側） -->
                            <div class="flex-1 p-6">
                                <h3 class="font-bold text-xl text-gray-900 mb-3">{{ shop.name }}</h3>
                                
                                <div class="space-y-3">
                                    <!-- 住所 -->
                                    <div v-if="shop.address" class="flex items-start space-x-3">
                                        <div class="flex-shrink-0 mt-0.5">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </div>
                                        <p class="text-sm text-gray-700 flex-1">{{ shop.address }}</p>
                                    </div>
                                    
                                    <!-- 電話番号 -->
                                    <div v-if="shop.phone" class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                        </div>
                                        <a :href="`tel:${shop.phone}`" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                            {{ shop.phone }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 画像（右側または下側） -->
                            <div v-if="getShopImageUrl(shop)" class="md:w-1/2 lg:w-2/5 flex-shrink-0">
                                <img
                                    :src="getShopImageUrl(shop)"
                                    :alt="shop.name"
                                    class="w-full h-full object-cover"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 区切り線 -->
            <div v-if="shops && shops.length > 0" class="border-t border-gray-200"></div>

            <!-- フォームフィールド -->
            <div class="space-y-4">
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
                <label class="block text-sm font-medium text-gray-700 mb-1">問い合わせ回答方法</label>
                <select
                    v-model="form.heard_from"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">選択してください</option>
                    <option value="メール">メール</option>
                    <option value="電話">電話</option>
                </select>
                <div v-if="form.errors.heard_from" class="mt-1 text-sm text-red-600">{{ form.errors.heard_from }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">お問い合わせ内容</label>
                <textarea
                    v-model="form.inquiry_message"
                    rows="6"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                ></textarea>
                <div v-if="form.errors.inquiry_message" class="mt-1 text-sm text-red-600">{{ form.errors.inquiry_message }}</div>
            </div>

                <div class="pt-4">
                    <button
                        type="submit"
                        :disabled="processing"
                        class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                    >
                        {{ processing ? '送信中...' : 'お問い合わせを送信' }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    shops: Array,
    selectedTimeslot: Object,
});

const emit = defineEmits(['submitted', 'confirm']);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    heard_from: '',
    inquiry_message: '',
});

const processing = ref(false);

// 店舗画像のURLを取得
const getShopImageUrl = (shop) => {
    // image_urlが優先（バックエンドから渡される）
    if (shop.image_url) {
        return shop.image_url;
    }
    // image_urlがない場合はimageを使用（フォールバック）
    if (shop.image) {
        // 既にURLの場合はそのまま返す
        if (shop.image.startsWith('http')) {
            return shop.image;
        }
        // パスの場合はstorageから取得
        return `/storage/${shop.image}`;
    }
    return null;
};

const submit = () => {
    // 確認ページに遷移
    emit('confirm', form.data());
};
</script>

