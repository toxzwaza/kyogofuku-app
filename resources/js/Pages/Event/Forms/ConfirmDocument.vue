<template>
    <div class="max-w-5xl mx-auto p-6 animate-fade-in">
        <!-- ヘッダーセクション -->
        <div class="mb-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full mb-4 shadow-lg">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h2 class="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent mb-2">
                送信内容の確認
            </h2>
            <p class="text-gray-500 text-sm">内容をご確認の上、送信ボタンを押してください</p>
        </div>
        
        <!-- メインカード -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden mb-8 transform transition-all duration-300 hover:shadow-2xl">
            <!-- カードヘッダー -->
            <div class="bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 p-6">
                <div class="flex items-center space-x-3">
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-2">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold text-lg">資料請求情報</h3>
                        <p class="text-white/80 text-sm">Document Request Details</p>
                    </div>
                </div>
            </div>

            <!-- カードボディ -->
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- 請求方法 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">請求方法</label>
                        </div>
                        <p class="text-gray-900 font-medium text-lg pl-10">{{ formData.request_method }}</p>
                    </div>

                    <!-- お名前 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">お名前</label>
                        </div>
                        <p class="text-gray-900 font-medium text-lg pl-10">{{ formData.name }}</p>
                    </div>

                    <!-- フリガナ -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-8 h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors">
                                <svg class="w-4 h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">フリガナ</label>
                        </div>
                        <p class="text-gray-900 font-medium pl-10">{{ formData.furigana || '-' }}</p>
                    </div>

                    <!-- 生年月日 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">生年月日</label>
                        </div>
                        <p class="text-gray-900 font-medium pl-10">{{ formData.birth_date ? formatDate(formData.birth_date) : '-' }}</p>
                    </div>

                    <!-- メールアドレス -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">メールアドレス</label>
                        </div>
                        <p class="text-gray-900 font-medium pl-10 break-all">{{ formData.email }}</p>
                    </div>

                    <!-- 電話番号 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition-colors">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">電話番号</label>
                        </div>
                        <p class="text-gray-900 font-medium pl-10">{{ formData.phone }}</p>
                    </div>

                    <!-- 郵便番号 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">郵便番号</label>
                        </div>
                        <p class="text-gray-900 font-medium pl-10">{{ formData.postal_code || '-' }}</p>
                    </div>

                    <!-- 住所 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-8 h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors">
                                <svg class="w-4 h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">住所</label>
                        </div>
                        <p class="text-gray-900 font-medium pl-10">{{ formData.address || '-' }}</p>
                    </div>

                    <!-- 個人情報の取扱いについて -->
                    <div class="md:col-span-2 group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">個人情報の取扱いについて</label>
                        </div>
                        <p class="text-gray-900 font-medium pl-10">
                            <span :class="formData.privacy_agreed ? 'text-green-600' : 'text-gray-500'">
                                {{ formData.privacy_agreed ? '同意済み' : '未同意' }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ボタンセクション -->
        <div class="flex flex-col sm:flex-row justify-end gap-4">
            <button
                @click="$emit('back')"
                class="px-8 py-3.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 transform hover:scale-105 active:scale-95 flex items-center justify-center space-x-2"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>戻る</span>
            </button>
            <button
                @click="submit"
                :disabled="processing"
                class="px-8 py-3.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-semibold hover:from-indigo-700 hover:to-purple-700 disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2"
            >
                <svg v-if="!processing" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ processing ? '送信中...' : '送信する' }}</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    formData: Object,
});

const emit = defineEmits(['back']);

const processing = ref(false);

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const submit = () => {
    processing.value = true;
    const form = useForm(props.formData);
    
    // 送信後、成功ページにリダイレクトされる
    form.post(route('event.reserve', props.event.id), {
        preserveState: false, // 状態を保持しない（リダイレクトを確実にする）
        preserveScroll: false, // スクロール位置を保持しない
        onSuccess: () => {
            // リダイレクトが成功した場合、このコールバックは呼ばれない
            // リダイレクト先のページが読み込まれる
            processing.value = false;
        },
        onError: (errors) => {
            processing.value = false;
            console.error('送信エラー:', errors);
            // エラーがある場合はアラートを表示
            if (errors && Object.keys(errors).length > 0) {
                alert('送信に失敗しました。入力内容を確認してください。');
            }
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

onMounted(() => {
    // フェードインアニメーション用のスタイルを追加
    if (!document.getElementById('confirm-document-styles')) {
        const style = document.createElement('style');
        style.id = 'confirm-document-styles';
        style.textContent = `
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .animate-fade-in {
                animation: fadeIn 0.5s ease-out;
            }
        `;
        document.head.appendChild(style);
    }
});
</script>

