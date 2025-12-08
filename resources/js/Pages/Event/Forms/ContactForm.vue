<template>
    <form @submit.prevent="submit">
        <h2 class="text-2xl font-bold mb-6">お問い合わせフォーム</h2>

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
    </form>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
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

const submit = () => {
    // 確認ページに遷移
    emit('confirm', form.data());
};
</script>

