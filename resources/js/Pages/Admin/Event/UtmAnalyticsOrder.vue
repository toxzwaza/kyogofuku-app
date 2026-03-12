<template>
    <Head title="UTM分析API 並び順" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">UTM分析API 並び順</h2>
                <ActionButton variant="back" label="イベント一覧に戻る" :href="route('admin.events.index')" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- 成功メッセージ -->
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 p-4">
                    <p class="text-sm font-medium text-green-800">{{ $page.props.flash.success }}</p>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <p class="text-sm text-gray-600 mb-4">
                            UTM分析APIに含める（ON）のイベント一覧です。ドラッグ＆ドロップで並び順を変更できます。変更は自動保存されます。
                        </p>

                        <div v-if="!sortedEvents.length" class="text-center py-12 text-gray-500">
                            UTM分析APIに含めるが ON のイベントがありません。<br />
                            イベント詳細の「UTM分析APIに含める」を ON にしたイベントがここに表示されます。
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="(event, index) in sortedEvents"
                                :key="event.id"
                                class="flex items-center gap-3 p-4 border rounded-lg transition-colors"
                                :class="[
                                    dropTargetIndex === index ? 'border-indigo-400 bg-indigo-50 ring-2 ring-indigo-200' : 'border-gray-200 hover:bg-gray-50',
                                    draggedIndex === index ? 'opacity-50' : '',
                                ]"
                                draggable="true"
                                @dragstart="handleDragStart(index, $event)"
                                @dragover.prevent="handleDragOver(index, $event)"
                                @drop="handleDrop(index, $event)"
                                @dragleave="handleDragLeave($event)"
                                @dragend="handleDragEnd"
                            >
                                <div
                                    class="flex-shrink-0 cursor-grab active:cursor-grabbing p-2 rounded hover:bg-gray-200 flex items-center justify-center text-gray-500"
                                    @click.stop
                                    title="ドラッグして並び替え"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                    </svg>
                                </div>
                                <span class="flex-shrink-0 w-8 text-sm font-medium text-gray-500">{{ index + 1 }}</span>
                                <div class="flex-1 min-w-0">
                                    <Link
                                        :href="route('admin.events.show', event.id)"
                                        class="text-indigo-600 hover:text-indigo-800 font-medium"
                                    >
                                        {{ event.title }}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';

const props = defineProps({
    events: Array,
});

const sortedEvents = ref([...(props.events || [])]);

watch(() => props.events, (newEvents) => {
    if (newEvents && newEvents.length > 0) {
        sortedEvents.value = [...newEvents];
    }
}, { deep: true });
const draggedIndex = ref(null);
const dropTargetIndex = ref(null);

const handleDragStart = (index, event) => {
    draggedIndex.value = index;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(index));
};

const handleDragOver = (index, event) => {
    event.dataTransfer.dropEffect = 'move';
    dropTargetIndex.value = index;
};

const handleDragLeave = (event) => {
    if (event.relatedTarget && event.currentTarget.contains(event.relatedTarget)) return;
    dropTargetIndex.value = null;
};

const handleDrop = (dropIndex, event) => {
    event.preventDefault();
    const dragIndex = draggedIndex.value;
    dropTargetIndex.value = null;
    draggedIndex.value = null;
    if (dragIndex == null || dragIndex === dropIndex) return;

    const arr = [...sortedEvents.value];
    const [removed] = arr.splice(dragIndex, 1);
    arr.splice(dropIndex, 0, removed);
    sortedEvents.value = arr;

    saveSortOrder();
};

const handleDragEnd = () => {
    draggedIndex.value = null;
    dropTargetIndex.value = null;
};

const saveSortOrder = () => {
    const eventIds = sortedEvents.value.map(e => e.id);
    router.post(route('admin.events.utm-analytics-order.update'), { event_ids: eventIds }, {
        preserveScroll: true,
    });
};
</script>
