<template>
    <div class="w-full px-2 sm:px-4 py-3 sm:py-4 text-center animate-success-fade-in">
        <!-- 成功アニメーション背景 -->
        <div class="absolute inset-0 bg-gradient-to-br from-pink-50 via-rose-50 to-pink-50 opacity-50 -z-10"></div>
        
        <!-- メインカード -->
        <div class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-gray-100 overflow-hidden relative">
            <!-- 装飾的なグラデーション背景 -->
            <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-pink-400 via-rose-500 to-pink-500"></div>
            
            <div class="p-4 sm:p-6 lg:p-8 xl:p-12">
                <!-- 成功アイコン -->
                <div class="mb-4 sm:mb-6 lg:mb-8 relative">
                    <div class="mx-auto w-16 h-16 sm:w-20 sm:h-20 lg:w-24 lg:h-24 bg-gradient-to-br from-pink-400 to-rose-600 rounded-full flex items-center justify-center shadow-2xl transform transition-all duration-500 animate-success-bounce">
                        <svg class="w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <!-- パーティクルエフェクト -->
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 lg:w-24 lg:h-24 bg-pink-400 rounded-full opacity-20 animate-ping"></div>
                    </div>
                </div>

                <!-- タイトル -->
                <h2 class="text-xl sm:text-2xl lg:text-3xl xl:text-4xl font-bold bg-gradient-to-r from-pink-600 to-rose-600 bg-clip-text text-transparent mb-2 sm:mb-3 lg:mb-4">
                    予約が完了しました
                </h2>
                <p class="text-gray-600 text-xs sm:text-sm lg:text-base xl:text-lg mb-4 sm:mb-6 lg:mb-8 leading-relaxed px-2">
                    ご予約ありがとうございます。<br>
                    <span class="text-gray-500 text-xs sm:text-sm lg:text-base">予約内容を確認の上、ご来店をお待ちしております。</span>
                </p>

                <!-- 予約内容カード -->
                <div class="bg-gradient-to-br from-pink-50 to-rose-50 rounded-lg sm:rounded-xl lg:rounded-2xl p-3 sm:p-4 lg:p-6 xl:p-8 mb-4 sm:mb-6 lg:mb-8 text-left border border-pink-200 shadow-inner">
                    <div class="flex items-center space-x-2 sm:space-x-3 mb-3 sm:mb-4 lg:mb-6">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gradient-to-br from-pink-500 to-rose-600 rounded-lg flex items-center justify-center shadow-md flex-shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900">予約内容</h3>
                    </div>
                    
                    <div class="space-y-3 sm:space-y-4">
                        <div class="flex items-start space-x-3 sm:space-x-4 p-3 sm:p-4 bg-white rounded-lg sm:rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-pink-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">予約日時</p>
                                <div v-if="selectedVenueName" class="mb-2">
                                    <span class="inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium bg-pink-100 text-pink-800">
                                        <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1 sm:mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="truncate">{{ selectedVenueName }}</span>
                                    </span>
                                </div>
                                <p class="text-gray-900 font-semibold text-base sm:text-lg break-words">{{ formatDateTime(formData.reservation_datetime) }}</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 sm:space-x-4 p-3 sm:p-4 bg-white rounded-lg sm:rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-rose-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">お名前</p>
                                <p class="text-gray-900 font-semibold text-base sm:text-lg break-words">{{ formData.name }}</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 sm:space-x-4 p-3 sm:p-4 bg-white rounded-lg sm:rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-pink-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">メールアドレス</p>
                                <p class="text-gray-900 font-semibold text-sm sm:text-base break-all">{{ formData.email }}</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 sm:space-x-4 p-3 sm:p-4 bg-white rounded-lg sm:rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-rose-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">電話番号</p>
                                <p class="text-gray-900 font-semibold text-base sm:text-lg break-words">{{ formData.phone }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 閉じるボタン -->
                <button
                    @click="$emit('close')"
                    class="px-6 sm:px-8 lg:px-10 py-3 sm:py-3.5 lg:py-4 bg-gradient-to-r from-pink-600 to-rose-600 text-white rounded-xl font-semibold hover:from-pink-700 hover:to-rose-700 transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2 mx-auto text-sm sm:text-base"
                >
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>閉じる</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, computed } from 'vue';

const props = defineProps({
    formData: Object,
    venues: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const selectedVenueName = computed(() => {
    if (!props.formData?.venue_id || !props.venues) return null;
    const venue = props.venues.find(v => v.id === props.formData.venue_id);
    return venue ? venue.name : null;
});

const formatDateTime = (datetime) => {
    if (!datetime) return '-';
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

onMounted(() => {
    // 成功アニメーション用のスタイルを追加
    if (!document.getElementById('success-reservation-styles')) {
        const style = document.createElement('style');
        style.id = 'success-reservation-styles';
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

