<template>
    <div class="max-w-5xl mx-auto p-6 animate-fade-in">
        <!-- ヘッダーセクション -->
        <div class="mb-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full mb-4 shadow-lg">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
            </div>
            <h2 class="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent mb-2">
                お問い合わせ内容の確認
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold text-lg">お問い合わせ情報</h3>
                        <p class="text-white/80 text-sm">Inquiry Details</p>
                    </div>
                </div>
            </div>

            <!-- カードボディ -->
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

                    <!-- 問い合わせ回答方法 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-8 h-8 bg-teal-100 rounded-lg flex items-center justify-center group-hover:bg-teal-200 transition-colors">
                                <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">問い合わせ回答方法</label>
                        </div>
                        <p class="text-gray-900 font-medium pl-10">{{ formData.heard_from || '-' }}</p>
                    </div>

                    <!-- お問い合わせ内容 -->
                    <div class="md:col-span-2 group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">お問い合わせ内容</label>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 ml-10">
                            <p class="text-gray-900 whitespace-pre-wrap leading-relaxed">{{ formData.inquiry_message || '-' }}</p>
                        </div>
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
    if (!document.getElementById('confirm-contact-styles')) {
        const style = document.createElement('style');
        style.id = 'confirm-contact-styles';
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

