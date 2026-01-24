<template>
    <Head title="予約枠テンプレート編集" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">予約枠テンプレート編集</h2>
                <Link
                    :href="route('admin.timeslot-templates.index')"
                    class="text-indigo-600 hover:text-indigo-900"
                >
                    ← テンプレート一覧に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- エラーメッセージ -->
                        <div v-if="$page.props.errors && Object.keys($page.props.errors).length > 0" class="mb-4 rounded-md bg-red-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">エラーが発生しました</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc list-inside space-y-1">
                                            <li v-for="(error, key) in $page.props.errors" :key="key">
                                                {{ Array.isArray(error) ? error[0] : error }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent="submit">
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">テンプレート名 <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="例: 午前の部、午後の部"
                                    />
                                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                                </div>

                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="block text-sm font-medium text-gray-700">時間枠 <span class="text-red-500">*</span></label>
                                        <button
                                            type="button"
                                            @click="addSlot"
                                            class="px-3 py-1 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                                        >
                                            + 時間枠を追加
                                        </button>
                                    </div>
                                    <div v-if="form.errors.slots" class="mb-2 text-sm text-red-600">{{ form.errors.slots }}</div>
                                    
                                    <div v-if="form.slots.length === 0" class="text-sm text-gray-500 py-4 text-center border border-gray-200 rounded-md">
                                        時間枠が登録されていません。上記のボタンから追加してください。
                                    </div>
                                    
                                    <div v-else class="space-y-3">
                                        <div
                                            v-for="(slot, index) in form.slots"
                                            :key="index"
                                            class="p-4 border border-gray-200 rounded-md bg-gray-50"
                                        >
                                            <div class="grid grid-cols-4 gap-3">
                                                <div>
                                                    <label class="block text-xs text-gray-500 mb-1">時</label>
                                                    <select
                                                        v-model="slot.hour"
                                                        required
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    >
                                                        <option
                                                            v-for="hour in hours"
                                                            :key="hour"
                                                            :value="hour"
                                                        >
                                                            {{ String(hour).padStart(2, '0') }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs text-gray-500 mb-1">分</label>
                                                    <select
                                                        v-model="slot.minute"
                                                        required
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    >
                                                        <option
                                                            v-for="minute in minutes"
                                                            :key="minute"
                                                            :value="minute"
                                                        >
                                                            {{ String(minute).padStart(2, '0') }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs text-gray-500 mb-1">枠数</label>
                                                    <input
                                                        v-model.number="slot.capacity"
                                                        type="number"
                                                        min="1"
                                                        required
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    />
                                                </div>
                                                <div class="flex items-end">
                                                    <button
                                                        type="button"
                                                        @click="removeSlot(index)"
                                                        class="w-full px-3 py-2 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-md border border-red-200"
                                                    >
                                                        削除
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-4 pt-4 border-t">
                                    <Link
                                        :href="route('admin.timeslot-templates.index')"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                    >
                                        キャンセル
                                    </Link>
                                    <button
                                        type="submit"
                                        :disabled="form.processing || form.slots.length === 0"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                                    >
                                        {{ form.processing ? '保存中...' : '更新' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    template: Object,
});

// 時（0-23）の選択肢を生成
const hours = Array.from({ length: 24 }, (_, i) => i);

// 分（10分単位：00, 10, 20, 30, 40, 50）の選択肢を生成
const minutes = [0, 10, 20, 30, 40, 50];

const form = useForm({
    name: props.template?.name || '',
    slots: props.template?.slots ? props.template.slots.map(slot => ({
        hour: slot.hour,
        minute: slot.minute,
        capacity: slot.capacity,
    })) : [],
});

const addSlot = () => {
    form.slots.push({
        hour: 0,
        minute: 0,
        capacity: 1,
    });
};

const removeSlot = (index) => {
    form.slots.splice(index, 1);
};

const submit = () => {
    if (form.slots.length === 0) {
        alert('時間枠を1つ以上追加してください。');
        return;
    }
    form.put(route('admin.timeslot-templates.update', props.template.id));
};
</script>

