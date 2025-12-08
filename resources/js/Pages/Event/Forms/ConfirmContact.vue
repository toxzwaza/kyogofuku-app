<template>
    <div class="max-w-4xl mx-auto p-6">
        <h2 class="text-2xl font-bold mb-6">お問い合わせ内容の確認</h2>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">お名前</label>
                        <p class="text-gray-900">{{ formData.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス</label>
                        <p class="text-gray-900">{{ formData.email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">電話番号</label>
                        <p class="text-gray-900">{{ formData.phone }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">問い合わせ回答方法</label>
                        <p class="text-gray-900">{{ formData.heard_from || '-' }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">お問い合わせ内容</label>
                        <p class="text-gray-900 whitespace-pre-wrap">{{ formData.inquiry_message || '-' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-4">
            <button
                @click="$emit('back')"
                class="px-6 py-3 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors duration-200"
            >
                戻る
            </button>
            <button
                @click="submit"
                :disabled="processing"
                class="px-6 py-3 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors duration-200"
            >
                {{ processing ? '送信中...' : '送信する' }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
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
</script>

