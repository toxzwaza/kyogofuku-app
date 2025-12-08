<template>
    <div class="max-w-4xl mx-auto p-6 text-center">
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="mb-6">
                <svg class="mx-auto h-16 w-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="text-3xl font-bold text-gray-900 mb-4">お問い合わせが完了しました</h2>
            <p class="text-gray-700 mb-6">
                お問い合わせありがとうございます。<br>
                {{ formData.heard_from === 'メール' ? 'メールにてご返信いたします。' : formData.heard_from === '電話' ? '電話にてご連絡いたします。' : '担当者よりご連絡いたします。' }}
            </p>
            <div class="bg-gray-50 rounded-lg p-6 mb-6 text-left">
                <h3 class="text-lg font-semibold mb-4 text-gray-900">お問い合わせ内容</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">お名前:</span>
                        <span class="text-gray-900 font-medium">{{ formData.name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">メールアドレス:</span>
                        <span class="text-gray-900 font-medium">{{ formData.email }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">電話番号:</span>
                        <span class="text-gray-900 font-medium">{{ formData.phone }}</span>
                    </div>
                </div>
            </div>
            <button
                @click="$emit('close')"
                class="px-6 py-3 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors duration-200"
            >
                閉じる
            </button>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    formData: Object,
});

const emit = defineEmits(['close']);
</script>

