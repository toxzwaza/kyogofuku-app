<template>
    <Head title="スライドショー詳細" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">スライドショー詳細 - {{ slideshow.name }}</h2>
                <div class="flex space-x-4">
                    <Link
                        :href="route('admin.slideshows.index')"
                        class="text-indigo-600 hover:text-indigo-900"
                    >
                        ← スライドショー一覧に戻る
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- スライドショー名編集 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <form @submit.prevent="updateName">
                            <div class="flex items-end space-x-4">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">スライドショー名</label>
                                    <input
                                        v-model="nameForm.name"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    :disabled="nameForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                >
                                    {{ nameForm.processing ? '更新中...' : '名前を更新' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 画像一覧 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div v-if="slideshow.images && slideshow.images.length > 0" class="space-y-4">
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
                                        :alt="image.alt || 'スライドショー画像'"
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

                <!-- 画像追加 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">画像を追加</h3>
                        <form @submit.prevent="submitImage" enctype="multipart/form-data">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        画像 <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        multiple
                                        accept="image/*"
                                        @change="handleFileChange"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p class="mt-1 text-sm text-gray-500">複数の画像を選択できます（JPEG, PNG, JPG, GIF、最大10MB）</p>
                                    <div v-if="imageForm.errors.images" class="mt-1 text-sm text-red-600">{{ imageForm.errors.images }}</div>
                                    
                                    <!-- プレビュー -->
                                    <div v-if="previewImages.length > 0" class="mt-4 grid grid-cols-2 md:grid-cols-3 gap-4">
                                        <div v-for="(preview, index) in previewImages" :key="index" class="relative">
                                            <img
                                                :src="preview"
                                                alt="プレビュー"
                                                class="w-full h-32 object-cover rounded"
                                            />
                                            <button
                                                type="button"
                                                @click="removePreview(index)"
                                                class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Alt属性</label>
                                    <input
                                        v-model="imageForm.alt"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="画像の説明（オプション）"
                                    />
                                    <div v-if="imageForm.errors.alt" class="mt-1 text-sm text-red-600">{{ imageForm.errors.alt }}</div>
                                </div>

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        :disabled="imageForm.processing || selectedFiles.length === 0"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                    >
                                        {{ imageForm.processing ? 'アップロード中...' : '画像を追加' }}
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
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    slideshow: Object,
});

const sortedImages = ref([...props.slideshow.images || []]);
const draggedIndex = ref(null);
const isSaving = ref(false);
const fileInput = ref(null);
const selectedFiles = ref([]);
const previewImages = ref([]);

const nameForm = useForm({
    name: props.slideshow.name,
});

const imageForm = useForm({
    images: [],
    alt: '',
});

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
    
    router.post(route('admin.slideshows.images.sort', props.slideshow.id), {
        image_ids: imageIds,
    }, {
        onFinish: () => {
            isSaving.value = false;
        },
    });
};

const deleteImage = (id) => {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('admin.slideshow-images.destroy', id), {
            onSuccess: () => {
                sortedImages.value = sortedImages.value.filter(img => img.id !== id);
            },
        });
    }
};

const handleFileChange = (event) => {
    const files = Array.from(event.target.files);
    selectedFiles.value = files;
    previewImages.value = [];

    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImages.value.push(e.target.result);
        };
        reader.readAsDataURL(file);
    });

    imageForm.images = files;
};

const removePreview = (index) => {
    previewImages.value.splice(index, 1);
    selectedFiles.value.splice(index, 1);
    imageForm.images = selectedFiles.value;
    
    const dt = new DataTransfer();
    selectedFiles.value.forEach(file => dt.items.add(file));
    fileInput.value.files = dt.files;
};

const updateName = () => {
    nameForm.put(route('admin.slideshows.update', props.slideshow.id));
};

const submitImage = () => {
    imageForm.post(route('admin.slideshows.images.store', props.slideshow.id), {
        forceFormData: true,
        onSuccess: () => {
            imageForm.reset();
            selectedFiles.value = [];
            previewImages.value = [];
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};
</script>

