<template>
    <Head title="前撮り枠追加" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">前撮り枠追加</h2>
                <Link
                    :href="route('admin.photo-slots.index')"
                    class="text-indigo-600 hover:text-indigo-900"
                >
                    ← 前撮り管理に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        スタジオ <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="form.photo_studio_id"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">選択してください</option>
                                        <option
                                            v-for="studio in photoStudios"
                                            :key="studio.id"
                                            :value="studio.id"
                                        >
                                            {{ studio.name }}
                                        </option>
                                    </select>
                                    <div v-if="form.errors.photo_studio_id" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.photo_studio_id }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        担当店舗
                                    </label>
                                    <div class="space-y-2 border border-gray-300 rounded-md p-4 bg-gray-50">
                                        <label
                                            v-for="shop in shops"
                                            :key="shop.id"
                                            class="flex items-center space-x-2 cursor-pointer hover:bg-gray-100 p-2 rounded-md transition-colors"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="shop.id"
                                                v-model="form.shop_ids"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span class="text-sm text-gray-700">{{ shop.name }}</span>
                                        </label>
                                        <p v-if="shops.length === 0" class="text-sm text-gray-500">
                                            店舗が登録されていません
                                        </p>
                                    </div>
                                    <div v-if="form.errors.shop_ids" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.shop_ids }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        撮影日 <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="form.shoot_date"
                                        type="date"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <div v-if="form.errors.shoot_date" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.shoot_date }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        撮影時間 <span class="text-red-500">*</span>
                                    </label>
                                    
                                    <!-- 時間ボタン（8:00-16:00、30分間隔） -->
                                    <div class="mb-4">
                                        <p class="text-sm text-gray-600 mb-2">時間を選択してください（クリックで選択/解除）</p>
                                        <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2">
                                            <button
                                                v-for="time in timeSlots"
                                                :key="time"
                                                type="button"
                                                @click="toggleTimeSlot(time)"
                                                :class="[
                                                    'px-3 py-2 text-sm font-medium rounded-md border-2 transition-all',
                                                    selectedTimes.includes(time)
                                                        ? 'bg-indigo-600 text-white border-indigo-600 opacity-100'
                                                        : 'bg-gray-100 text-gray-600 border-gray-300 opacity-50 hover:opacity-75'
                                                ]"
                                            >
                                                {{ time }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- カスタム時間入力 -->
                                    <div class="border-t border-gray-200 pt-4">
                                        <p class="text-sm text-gray-600 mb-2">その他の時間を追加</p>
                                        <div class="space-y-2">
                                            <div
                                                v-for="(time, index) in customTimes"
                                                :key="index"
                                                class="flex items-center space-x-2"
                                            >
                                                <input
                                                    v-model="customTimes[index]"
                                                    type="time"
                                                    class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="HH:mm"
                                                />
                                                <button
                                                    type="button"
                                                    @click="removeCustomTime(index)"
                                                    class="px-3 py-2 text-sm font-medium text-red-600 hover:text-red-800"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <button
                                                type="button"
                                                @click="addCustomTime"
                                                class="w-full px-4 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-md hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            >
                                                + カスタム時間を追加
                                            </button>
                                        </div>
                                    </div>

                                    <!-- 選択された時間のプレビュー -->
                                    <div v-if="allSelectedTimes.length > 0" class="mt-4 p-3 bg-gray-50 rounded-md">
                                        <p class="text-sm font-medium text-gray-700 mb-2">選択された時間（{{ allSelectedTimes.length }}件）:</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span
                                                v-for="(time, index) in allSelectedTimes"
                                                :key="index"
                                                class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded-full"
                                            >
                                                {{ time }}
                                            </span>
                                        </div>
                                    </div>

                                    <div v-if="form.errors.shoot_times" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.shoot_times }}
                                    </div>
                                    <div v-if="form.errors['shoot_times.0']" class="mt-1 text-sm text-red-600">
                                        {{ form.errors['shoot_times.0'] }}
                                    </div>
                                </div>


                                <div class="flex justify-end space-x-4 pt-4">
                                    <Link
                                        :href="route('admin.photo-slots.index')"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                    >
                                        キャンセル
                                    </Link>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                    >
                                        {{ form.processing ? '保存中...' : '保存' }}
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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    photoStudios: Array,
    shops: Array,
    photoSlotsWithShops: Array,
    userShops: Array,
});

const form = useForm({
    photo_studio_id: '',
    shoot_date: '',
    shoot_times: [],
    shop_ids: [],
});

// ログインユーザーの所属店舗をデフォルトで設定
if (props.userShops && props.userShops.length > 0) {
    form.shop_ids = props.userShops.map(shop => shop.id);
}

// 8:00から16:00まで30分間隔の時間スロットを生成
const generateTimeSlots = () => {
    const slots = [];
    for (let hour = 8; hour <= 16; hour++) {
        slots.push(`${String(hour).padStart(2, '0')}:00`);
        if (hour < 16) {
            slots.push(`${String(hour).padStart(2, '0')}:30`);
        }
    }
    return slots;
};

const timeSlots = ref(generateTimeSlots());
const selectedTimes = ref([]);
const customTimes = ref(['']);

// 時間スロットの選択/解除をトグル
const toggleTimeSlot = (time) => {
    const index = selectedTimes.value.indexOf(time);
    if (index > -1) {
        selectedTimes.value.splice(index, 1);
    } else {
        selectedTimes.value.push(time);
    }
};

// カスタム時間を追加
const addCustomTime = () => {
    customTimes.value.push('');
};

// カスタム時間を削除
const removeCustomTime = (index) => {
    customTimes.value.splice(index, 1);
};

// すべての選択された時間（ボタン選択 + カスタム時間）
const allSelectedTimes = computed(() => {
    const times = [...selectedTimes.value];
    customTimes.value.forEach(time => {
        if (time && time.trim() !== '') {
            // 既に選択されている時間でない場合のみ追加
            if (!times.includes(time)) {
                times.push(time);
            }
        }
    });
    return times.sort();
});

const submit = () => {
    // 選択された時間をform.shoot_timesに設定
    form.shoot_times = allSelectedTimes.value;
    
    if (form.shoot_times.length === 0) {
        alert('少なくとも1つの時間枠を選択してください。');
        return;
    }
    
    form.post(route('admin.photo-slots.store'));
};
</script>

