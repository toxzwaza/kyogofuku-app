<template>
    <Head title="イベント画像管理" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    イベント画像管理 - {{ event.title }}
                </h2>
                <div class="flex space-x-4">
                    <Link
                        :href="route('admin.events.images.create', event.id)"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700"
                    >
                        画像追加
                    </Link>
                    <Link
                        :href="route('admin.events.index')"
                        class="text-indigo-600 hover:text-indigo-900"
                    >
                        ← イベント一覧に戻る
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="images && images.length > 0" class="space-y-4">
                            <div class="mb-4">
                                <p class="text-sm text-gray-600">画像をドラッグ&ドロップで並び替えできます</p>
                            </div>
                            <div
                                v-for="(image, index) in sortedImages"
                                :key="image.id"
                                class="flex items-center space-x-4 p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
                                :draggable="true"
                                @dragstart="handleDragStart(index, $event)"
                                @dragover.prevent="handleDragOver($event)"
                                @drop="handleDrop(index, $event)"
                                @dragend="handleDragEnd"
                            >
                                <div class="flex-shrink-0 cursor-move">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                    </svg>
                                </div>
                                <div class="flex-shrink-0">
                                    <img
                                        :src="getImageUrl(image.path)"
                                        :alt="image.alt || 'イベント画像'"
                                        class="w-32 h-32 object-cover rounded"
                                    />
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm text-gray-600">順序: {{ image.sort_order }}</p>
                                    <p v-if="image.alt" class="text-sm text-gray-600">Alt: {{ image.alt }}</p>
                                </div>
                                <div class="flex-shrink-0">
                                    <button
                                        @click="deleteImage(image.id)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        削除
                                    </button>
                                </div>
                            </div>
                            <div class="pt-4">
                                <button
                                    @click="saveSortOrder"
                                    :disabled="isSaving"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                >
                                    {{ isSaving ? '保存中...' : 'ソート順を保存' }}
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            画像が登録されていません
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    images: Array,
});

const sortedImages = ref([...props.images]);
const draggedIndex = ref(null);
const isSaving = ref(false);

const getImageUrl = (path) => {
    if (path.startsWith('http')) {
        return path;
    }
    return `/storage/${path}`;
};

const handleDragStart = (index, event) => {
    draggedIndex.value = index;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/html', event.target);
};

const handleDragOver = (event) => {
    event.dataTransfer.dropEffect = 'move';
};

const handleDrop = (dropIndex, event) => {
    event.preventDefault();
    if (draggedIndex.value === null) return;

    const draggedItem = sortedImages.value[draggedIndex.value];
    sortedImages.value.splice(draggedIndex.value, 1);
    sortedImages.value.splice(dropIndex, 0, draggedItem);

    // sort_orderを更新
    sortedImages.value.forEach((image, index) => {
        image.sort_order = index + 1;
    });
};

const handleDragEnd = () => {
    draggedIndex.value = null;
};

const saveSortOrder = () => {
    isSaving.value = true;
    const imageIds = sortedImages.value.map(img => img.id);
    
    router.post(route('admin.events.images.sort', props.event.id), {
        image_ids: imageIds,
    }, {
        onFinish: () => {
            isSaving.value = false;
        },
    });
};

const deleteImage = (id) => {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('admin.images.destroy', id), {
            onSuccess: () => {
                sortedImages.value = sortedImages.value.filter(img => img.id !== id);
            },
        });
    }
};
</script>

