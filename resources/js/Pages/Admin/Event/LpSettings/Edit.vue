<template>
    <Head title="LP設定" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    LP設定 - {{ event.title }}
                </h2>
                <ActionButton
                    variant="back"
                    label="イベント詳細へ戻る"
                    :href="route('admin.events.show', event.id)"
                />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <EventNavigation :event="event" :show-url-button="false" />
                    </div>
                </div>

                <!-- 背景設定 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">背景設定</h3>
                        <p class="text-sm text-gray-600 mb-4">公開ページの背景色と背景画像の表示を設定できます。</p>
                        <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-50 p-4">
                            <p class="text-sm font-medium text-green-800">{{ $page.props.flash.success }}</p>
                        </div>
                        <form @submit.prevent="submitBackground" class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">背景色</label>
                                <div class="flex flex-wrap items-end gap-4">
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="backgroundForm.background_color"
                                            type="color"
                                            class="h-10 w-14 rounded border border-gray-300 cursor-pointer"
                                        />
                                        <input
                                            v-model="backgroundForm.background_color"
                                            type="text"
                                            class="rounded-md border-gray-300 shadow-sm w-28 font-mono text-sm"
                                            placeholder="#e9e2dc"
                                            maxlength="7"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        @click="resetBackground"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                    >
                                        デフォルトに戻す
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">コンテンツ色</label>
                                <p class="text-xs text-gray-500 mb-2">画像・スライドショーなどを囲むエリア（ボックスシャドウの要素）の背景色です。背景色とは別に設定できます。</p>
                                <div class="flex flex-wrap items-end gap-4">
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="backgroundForm.content_background_color"
                                            type="color"
                                            class="h-10 w-14 rounded border border-gray-300 cursor-pointer"
                                        />
                                        <input
                                            v-model="backgroundForm.content_background_color"
                                            type="text"
                                            class="rounded-md border-gray-300 shadow-sm w-28 font-mono text-sm"
                                            placeholder="#ffffff"
                                            maxlength="7"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        @click="resetContentColor"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                    >
                                        デフォルトに戻す
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">背景画像</label>
                                <p class="text-xs text-gray-500 mb-2">公開ページで表示する背景画像をアップロードできます。未設定の場合は背景画像なしです。</p>
                                <input
                                    ref="backgroundImageInput"
                                    type="file"
                                    accept="image/jpeg,image/png,image/jpg,image/gif"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                    @change="onBackgroundImageChange"
                                />
                                <p class="mt-1 text-xs text-gray-500">JPEG, PNG, JPG, GIF（最大10MB）</p>
                                <label class="mt-2 flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="backgroundForm.remove_background_image"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        @change="onRemoveBackgroundImageChange"
                                    />
                                    <span class="text-sm text-gray-700">背景画像を削除する</span>
                                </label>
                                <div v-if="previewBackgroundImage || (event.background_image_url && !backgroundForm.remove_background_image)" class="mt-3">
                                    <p class="text-sm text-gray-600 mb-1">{{ previewBackgroundImage ? 'プレビュー:' : '現在の画像:' }}</p>
                                    <img
                                        :src="previewBackgroundImage || event.background_image_url"
                                        alt="背景画像"
                                        class="max-w-xs h-24 object-cover rounded border border-gray-300"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="backgroundForm.background_image_enabled"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">背景画像を表示する</span>
                                </label>
                                <p class="mt-1 text-xs text-gray-500">オフのときは背景画像なし（デフォルト）です。</p>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    type="submit"
                                    :disabled="backgroundForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ backgroundForm.processing ? '保存中...' : '保存する' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 画像管理・CTAデザインへのリンク -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Link
                        :href="route('admin.events.images.index', event.id)"
                        class="block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:border-indigo-300 hover:shadow transition-all"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-emerald-100 flex items-center justify-center">
                                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-800">画像管理</h3>
                                <p class="text-sm text-gray-500">イベント画像の追加・並び替え・スライドショー・CTAボタン位置</p>
                            </div>
                        </div>
                    </Link>
                    <Link
                        :href="route('admin.events.cta-design.edit', event.id)"
                        class="block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:border-rose-300 hover:shadow transition-all"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-rose-100 flex items-center justify-center">
                                <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-800">CTAデザイン</h3>
                                <p class="text-sm text-gray-500">固定CTAのボタン背景・WEB予約・電話予約画像</p>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import EventNavigation from '@/Components/EventNavigation.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: { type: Object, required: true },
});

const defaultHex = '#e9e2dc';
const defaultContentHex = '#ffffff';
const backgroundImageInput = ref(null);
const previewBackgroundImage = ref(null);

function normalizeHex(value, defaultVal) {
    if (!value || !String(value).trim()) return defaultVal;
    const s = String(value).trim();
    return s.startsWith('#') ? s : '#' + s;
}

const backgroundForm = useForm({
    background_color: normalizeHex(props.event.background_color, defaultHex),
    content_background_color: normalizeHex(props.event.content_background_color, defaultContentHex),
    background_image_enabled: Boolean(props.event.background_image_enabled),
    background_image: null,
    remove_background_image: false,
});

function onBackgroundImageChange(event) {
    const file = event.target.files?.[0];
    if (file) {
        backgroundForm.background_image = file;
        backgroundForm.remove_background_image = false;
        const reader = new FileReader();
        reader.onload = (e) => { previewBackgroundImage.value = e.target?.result ?? null; };
        reader.readAsDataURL(file);
    }
}

function onRemoveBackgroundImageChange() {
    if (backgroundForm.remove_background_image) {
        backgroundForm.background_image = null;
        previewBackgroundImage.value = null;
        if (backgroundImageInput.value) backgroundImageInput.value.value = '';
    }
}

function toColorValue(hex, defaultVal) {
    const s = (hex || '').trim();
    if (!s || s === defaultVal) return null;
    return s.startsWith('#') ? s : '#' + s;
}

function getPayload() {
    return {
        background_color: toColorValue(backgroundForm.background_color, defaultHex),
        content_background_color: toColorValue(backgroundForm.content_background_color, defaultContentHex),
        background_image_enabled: backgroundForm.background_image_enabled,
        remove_background_image: backgroundForm.remove_background_image,
        background_image: backgroundForm.background_image,
    };
}

function submitBackground() {
    backgroundForm.transform(getPayload).post(route('admin.events.lp-settings.update', props.event.id), {
        forceFormData: true,
    });
}

function resetBackground() {
    backgroundForm.background_color = defaultHex;
    backgroundForm.transform(() => ({
        ...getPayload(),
        background_color: null,
    })).post(route('admin.events.lp-settings.update', props.event.id), {
        forceFormData: true,
    });
}

function resetContentColor() {
    backgroundForm.content_background_color = defaultContentHex;
    backgroundForm.transform(() => ({
        ...getPayload(),
        content_background_color: null,
    })).post(route('admin.events.lp-settings.update', props.event.id), {
        forceFormData: true,
    });
}
</script>
