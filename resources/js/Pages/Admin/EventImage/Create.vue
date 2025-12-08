<template>
    <Head title="イベント画像追加" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">イベント画像追加</h2>
                <Link
                    :href="route('admin.events.images.index', event.id)"
                    class="text-indigo-600 hover:text-indigo-900"
                >
                    ← 画像一覧に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit" enctype="multipart/form-data">
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
                                    <div v-if="form.errors.images" class="mt-1 text-sm text-red-600">{{ form.errors.images }}</div>
                                    
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
                                        v-model="form.alt"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="画像の説明（オプション）"
                                    />
                                    <div v-if="form.errors.alt" class="mt-1 text-sm text-red-600">{{ form.errors.alt }}</div>
                                </div>

                                <div class="flex justify-end space-x-4 pt-4">
                                    <Link
                                        :href="route('admin.events.images.index', event.id)"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                    >
                                        キャンセル
                                    </Link>
                                    <button
                                        type="submit"
                                        :disabled="form.processing || selectedFiles.length === 0"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                    >
                                        {{ form.processing ? 'アップロード中...' : 'アップロード' }}
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
    event: Object,
});

const fileInput = ref(null);
const selectedFiles = ref([]);
const previewImages = ref([]);

const form = useForm({
    images: [],
    alt: '',
});

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

    form.images = files;
};

const removePreview = (index) => {
    previewImages.value.splice(index, 1);
    selectedFiles.value.splice(index, 1);
    form.images = selectedFiles.value;
    
    // ファイル入力も更新
    const dt = new DataTransfer();
    selectedFiles.value.forEach(file => dt.items.add(file));
    fileInput.value.files = dt.files;
};

const submit = () => {
    form.post(route('admin.events.images.store', props.event.id), {
        forceFormData: true,
    });
};
</script>

