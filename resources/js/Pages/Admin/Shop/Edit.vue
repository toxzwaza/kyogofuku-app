<template>
    <Head title="店舗編集" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">店舗編集</h2>
                <Link
                    :href="route('admin.shops.index')"
                    class="text-indigo-600 hover:text-indigo-900"
                >
                    ← 店舗一覧に戻る
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
                                    <label class="block text-sm font-medium text-gray-700 mb-1">店舗名 <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
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
                                    <label class="block text-sm font-medium text-gray-700 mb-1">店舗画像</label>
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
                                    <div v-if="shop.image_url || previewImage" class="mt-4">
                                        <p class="text-sm text-gray-600 mb-2">
                                            {{ previewImage ? 'プレビュー:' : '現在の画像:' }}
                                        </p>
                                        <div class="relative inline-block">
                                            <img
                                                :src="previewImage || shop.image_url"
                                                alt="店舗画像"
                                                class="w-32 h-32 object-cover rounded border border-gray-300"
                                            />
                                            <button
                                                v-if="shop.image_url && !previewImage"
                                                type="button"
                                                @click="removeImage"
                                                class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs -mt-2 -mr-2 hover:bg-red-600"
                                                title="画像を削除"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">LINEグループID</label>
                                    <input
                                        v-model="form.line_group_id"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="LINEグループIDを入力してください"
                                    />
                                    <p class="mt-1 text-sm text-gray-500">予約通知を送信するLINEグループのIDを設定します</p>
                                    <div v-if="form.errors.line_group_id" class="mt-1 text-sm text-red-600">{{ form.errors.line_group_id }}</div>
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
                                        :href="route('admin.shops.index')"
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
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    shop: Object,
});

const fileInput = ref(null);
const previewImage = ref(null);
const removeImageFlag = ref(false);

const form = useForm({
    name: props.shop.name,
    address: props.shop.address || '',
    phone: props.shop.phone || '',
    image: null,
    remove_image: false,
    is_active: props.shop.is_active,
    line_group_id: props.shop.line_group_id || '',
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
    form.remove_image = true;
    form.image = null;
    previewImage.value = null;
    removeImageFlag.value = true;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        _method: 'PUT',
    })).post(route('admin.shops.update', props.shop.id), {
        forceFormData: true,
    });
};
</script>

