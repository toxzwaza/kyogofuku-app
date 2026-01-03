<template>
    <div class="max-w-4xl mx-auto p-6 text-center animate-success-fade-in">
        <!-- 成功アニメーション背景 -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-cyan-50 to-teal-50 opacity-50 -z-10"></div>
        
        <!-- メインカード -->
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden relative">
            <!-- 装飾的なグラデーション背景 -->
            <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-blue-400 via-cyan-500 to-teal-500"></div>
            
            <div class="p-12">
                <!-- 成功アイコン -->
                <div class="mb-8 relative">
                    <div class="mx-auto w-24 h-24 bg-gradient-to-br from-blue-400 to-cyan-600 rounded-full flex items-center justify-center shadow-2xl transform transition-all duration-500 animate-success-bounce">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <!-- パーティクルエフェクト -->
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-24 h-24 bg-blue-400 rounded-full opacity-20 animate-ping"></div>
                    </div>
                </div>

                <!-- タイトル -->
                <h2 class="text-4xl font-bold bg-gradient-to-r from-blue-600 to-cyan-600 bg-clip-text text-transparent mb-4">
                    お問い合わせが完了しました
                </h2>
                <p class="text-gray-600 text-lg mb-8 leading-relaxed">
                    お問い合わせありがとうございます。<br>
                    <span class="text-gray-500 text-base">
                        {{ formData.heard_from === 'メール' ? 'メールにてご返信いたします。' : formData.heard_from === '電話' ? '電話にてご連絡いたします。' : '担当者よりご連絡いたします。' }}
                    </span>
                </p>

                <!-- お問い合わせ内容カード -->
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl p-8 mb-8 text-left border border-gray-200 shadow-inner">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-lg flex items-center justify-center shadow-md">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">お問い合わせ内容</h3>
                    </div>
                    
                    <div class="space-y-4">
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

                        <div class="flex items-start space-x-4 p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">電話番号</p>
                                <p class="text-gray-900 font-semibold text-lg">{{ formData.phone }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 閉じるボタン -->
                <button
                    @click="$emit('close')"
                    class="px-10 py-4 bg-gradient-to-r from-blue-600 to-cyan-600 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-cyan-700 transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2 mx-auto"
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
    if (!document.getElementById('success-contact-styles')) {
        const style = document.createElement('style');
        style.id = 'success-contact-styles';
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

