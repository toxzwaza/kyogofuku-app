<template>
    <div class="max-w-4xl mx-auto p-6 text-center animate-success-fade-in">
        <!-- 成功アニメーション背景 -->
        <div class="absolute inset-0 bg-gradient-to-br from-orange-50 via-red-50 to-pink-50 opacity-50 -z-10"></div>
        
        <!-- メインカード -->
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden relative">
            <!-- 装飾的なグラデーション背景 -->
            <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-orange-400 via-red-500 to-pink-500"></div>
            
            <div class="p-12">
                <!-- 成功アイコン -->
                <div class="mb-8 relative">
                    <div class="mx-auto w-24 h-24 bg-gradient-to-br from-orange-400 to-red-600 rounded-full flex items-center justify-center shadow-2xl transform transition-all duration-500 animate-success-bounce">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <!-- パーティクルエフェクト -->
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-24 h-24 bg-orange-400 rounded-full opacity-20 animate-ping"></div>
                    </div>
                </div>

                <!-- タイトル -->
                <h2 class="text-4xl font-bold bg-gradient-to-r from-orange-600 to-red-600 bg-clip-text text-transparent mb-4">
                    資料請求が完了しました
                </h2>
                <p class="text-gray-600 text-lg mb-8 leading-relaxed">
                    資料請求ありがとうございます。<br>
                    <span class="text-gray-500 text-base">
                        {{ formData.request_method === '郵送' ? 'ご指定の住所に資料をお送りいたします。' : 'デジタルカタログをメールアドレスに送信いたします。' }}
                    </span>
                </p>

                <!-- 請求内容カード -->
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl p-8 mb-8 text-left border border-gray-200 shadow-inner">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-600 rounded-lg flex items-center justify-center shadow-md">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">請求内容</h3>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex items-start space-x-4 p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">請求方法</p>
                                <p class="text-gray-900 font-semibold text-lg">{{ formData.request_method }}</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-4 p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">お名前</p>
                                <p class="text-gray-900 font-semibold text-lg">{{ formData.name }}</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-4 p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">メールアドレス</p>
                                <p class="text-gray-900 font-semibold break-all">{{ formData.email }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 閉じるボタン -->
                <button
                    @click="$emit('close')"
                    class="px-10 py-4 bg-gradient-to-r from-orange-600 to-red-600 text-white rounded-xl font-semibold hover:from-orange-700 hover:to-red-700 transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2 mx-auto"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>閉じる</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';

const props = defineProps({
    formData: Object,
});

const emit = defineEmits(['close']);

onMounted(() => {
    // 成功アニメーション用のスタイルを追加
    if (!document.getElementById('success-document-styles')) {
        const style = document.createElement('style');
        style.id = 'success-document-styles';
        style.textContent = `
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(30px) scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
            @keyframes bounce {
                0%, 100% {
                    transform: translateY(0) scale(1);
                }
                50% {
                    transform: translateY(-10px) scale(1.05);
                }
            }
            .animate-success-fade-in {
                animation: fadeIn 0.6s ease-out;
            }
            .animate-success-bounce {
                animation: bounce 1s ease-in-out;
            }
        `;
        document.head.appendChild(style);
    }
});
</script>

