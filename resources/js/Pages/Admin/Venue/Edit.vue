<template>
    <Head title="会場編集" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">会場編集</h2>
                <ActionButton variant="back" label="会場一覧に戻る" :href="route('admin.venues.index')" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">会場名 <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                                    <textarea
                                        v-model="form.description"
                                        rows="4"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    ></textarea>
                                    <p class="mt-1 text-sm text-gray-500">HTMLタグが使用できます</p>
                                    <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">住所</label>
                                    <input
                                        v-model="form.address"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <div v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">電話番号</label>
                                    <input
                                        v-model="form.phone"
                                        type="tel"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <div v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">会場画像</label>
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        accept="image/*"
                                        @change="handleFileChange"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p class="mt-1 text-sm text-gray-500">JPEG, PNG, JPG, GIF（最大10MB）</p>
                                    <div v-if="form.errors.image" class="mt-1 text-sm text-red-600">{{ form.errors.image }}</div>
                                    
                                    <!-- 現在の画像プレビュー -->
                                    <div v-if="venue.image_url || previewImage" class="mt-4">
                                        <p class="text-sm text-gray-600 mb-2">
                                            {{ previewImage ? 'プレビュー:' : '現在の画像:' }}
                                        </p>
                                        <div class="relative inline-block">
                                            <img
                                                :src="previewImage || venue.image_url"
                                                alt="会場画像"
                                                class="w-32 h-32 object-cover rounded border border-gray-300"
                                            />
                                            <div v-if="venue.image_url && !previewImage" class="absolute top-0 right-0 flex gap-1 -mt-2 -mr-2">
                                                <button
                                                    v-if="venue.storage_disk !== 's3'"
                                                    type="button"
                                                    class="w-7 h-7 flex items-center justify-center rounded-full bg-indigo-500 text-white hover:bg-indigo-600 shadow-md"
                                                    title="S3 に移行"
                                                    :disabled="migratingImage"
                                                    @click="migrateImageToS3"
                                                >
                                                    <svg v-if="!migratingImage" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                    </svg>
                                                    <span v-else class="text-xs">...</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="w-7 h-7 flex items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600 shadow-md"
                                                    title="画像を削除"
                                                    @click="removeImage"
                                                >
                                                    ×
                                                </button>
                                            </div>
                                        </div>
                                    </div>
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
                                        :href="route('admin.venues.index')"
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
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    venue: Object,
});

const fileInput = ref(null);
const previewImage = ref(null);
const removeImageFlag = ref(false);
const migratingImage = ref(false);

const form = useForm({
    name: props.venue.name,
    description: props.venue.description || '',
    address: props.venue.address || '',
    phone: props.venue.phone || '',
    image: null,
    remove_image: false,
    is_active: props.venue.is_active,
    _redirect_to_index: true,
});

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.image = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImage.value = e.target.result;
        };
        reader.readAsDataURL(file);
        removeImageFlag.value = false;
    }
};

const removeImage = () => {
    if (!confirm('画像を削除しますか？')) return;
    form.remove_image = true;
    form.image = null;
    previewImage.value = null;
    removeImageFlag.value = true;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    form.transform((data) => ({
        ...data,
        _method: 'PUT',
    })).post(route('admin.venues.update', props.venue.id), {
        forceFormData: true,
    });
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        _method: 'PUT',
    })).post(route('admin.venues.update', props.venue.id), {
        forceFormData: true,
    });
};

const migrateImageToS3 = () => {
    if (migratingImage.value) return;
    if (!confirm('この画像を S3 に移行しますか？（WebP に変換して保存します）')) return;
    migratingImage.value = true;
    router.post(route('admin.venues.migrate-image-to-s3', props.venue.id), {}, {
        preserveScroll: true,
        onFinish: () => { migratingImage.value = false; },
    });
};
</script>

