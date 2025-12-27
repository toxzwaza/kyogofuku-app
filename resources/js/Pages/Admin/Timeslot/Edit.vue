<template>
    <Head title="予約枠編集" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">予約枠編集</h2>
                <Link
                    :href="route('admin.events.timeslots.index', timeslot.event_id)"
                    class="text-indigo-600 hover:text-indigo-900"
                >
                    ← 予約枠一覧に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="timeslot.remaining_capacity !== undefined" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded">
                            <p class="text-blue-800">現在の予約数: {{ timeslot.capacity - timeslot.remaining_capacity }} / {{ timeslot.capacity }}</p>
                            <p class="text-blue-800 text-sm">残り枠: {{ timeslot.remaining_capacity }}</p>
                        </div>

                        <form @submit.prevent="submit">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">会場</label>
                                    <select
                                        v-model="form.venue_id"
                                        :disabled="venues.length === 1"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option :value="null">選択してください</option>
                                        <option
                                            v-for="venue in venues"
                                            :key="venue.id"
                                            :value="venue.id"
                                        >
                                            {{ venue.name }}
                                        </option>
                                    </select>
                                    <p v-if="venues.length === 1" class="mt-1 text-sm text-gray-500">
                                        このイベントには会場が1つしかないため、自動選択されます。
                                    </p>
                                    <p v-if="venues.length === 0" class="mt-1 text-sm text-orange-500">
                                        このイベントには会場が登録されていません。
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">開始日時 <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.start_at"
                                        type="datetime-local"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">枠数 <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.capacity"
                                        type="number"
                                        :min="Math.max(1, timeslot.capacity - timeslot.remaining_capacity)"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p class="mt-1 text-sm text-gray-500">
                                        最小値: {{ Math.max(1, timeslot.capacity - timeslot.remaining_capacity) }}（既存の予約数を下回ることはできません）
                                    </p>
                                </div>

                                <div>
                                    <label class="flex items-center">
                                        <input
                                            v-model="form.is_active"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">有効</span>
                                    </label>
                                </div>

                                <div class="flex justify-end space-x-4 pt-4">
                                    <Link
                                        :href="route('admin.events.timeslots.index', timeslot.event_id)"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                    >
                                        キャンセル
                                    </Link>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                    >
                                        {{ form.processing ? '更新中...' : '更新' }}
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
import { computed } from 'vue';

const props = defineProps({
    timeslot: Object,
    venues: Array,
});

const form = useForm({
    venue_id: props.timeslot.venue_id || (props.venues.length === 1 ? props.venues[0].id : null),
    start_at: formatDateTimeForInput(props.timeslot.start_at),
    capacity: props.timeslot.capacity,
    is_active: props.timeslot.is_active,
});

const formatDateTimeForInput = (datetime) => {
    if (!datetime) return '';
    const date = new Date(datetime);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const submit = () => {
    form.put(route('admin.timeslots.update', props.timeslot.id));
};
</script>

