<template>
    <Head title="CTAデザイン設定" />

    <AdminLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-brand-text leading-tight">
                    CTAデザイン設定 - {{ event.title }}
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
                <!-- 操作ナビゲーション -->
                <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <EventNavigation :event="event" :show-url-button="false" />
                    </div>
                </div>

                <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <p class="text-sm text-brand-text-muted mb-2">
                            公開ページの予約エリアに表示する「ボタン背景ブロック」「WEB予約」「電話予約」の画像を設定できます。未設定の項目はデフォルトのデザインが使われます。
                        </p>
                        <p class="text-sm font-medium mb-6" style="color: #4f46e5;">変更を反映するには、下の「保存する」ボタンを押してください。</p>
                        <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-50 p-4">
                            <p class="text-sm font-medium text-green-800">{{ $page.props.flash.success }}</p>
                        </div>
                        <div v-if="$page.props.flash?.error" class="mb-4 rounded-md bg-red-50 p-4">
                            <p class="text-sm font-medium text-red-800">{{ $page.props.flash.error }}</p>
                        </div>

                        <form @submit.prevent="submit" class="space-y-8">
                            <!-- ボタン背景ブロック -->
                            <div class="border-b border-brand-border pb-8">
                                <label class="block text-sm font-medium text-brand-text mb-2">ボタン背景ブロック用画像</label>
                                <p class="text-xs text-brand-text-muted mb-3">固定CTAエリア全体の背景画像です。</p>
                                <div class="flex flex-wrap gap-3 items-center">
                                    <input
                                        ref="backgroundInput"
                                        type="file"
                                        accept="image/jpeg,image/png,image/jpg,image/gif"
                                        class="block flex-1 min-w-[200px] text-sm text-brand-text-muted file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-rose-50 file:text-rose-700 hover:file:bg-rose-100"
                                        @change="handleBackgroundChange"
                                    />
                                    <button
                                        type="button"
                                        @click="openPicker('background')"
                                        class="px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700"
                                    >
                                        ライブラリから選択
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-brand-text-muted">JPEG, PNG, JPG, GIF（最大10MB）</p>
                                <label class="mt-3 flex items-center">
                                    <input
                                        v-model="form.remove_cta_background"
                                        type="checkbox"
                                        class="rounded border-brand-border text-rose-600 focus:ring-rose-500"
                                        @change="onRemoveCheck('background')"
                                    />
                                    <span class="ml-2 text-sm text-brand-text">画像を削除して未設定に戻す</span>
                                </label>
                                <div v-if="previewBackground || (event.cta_background_url && !form.remove_cta_background)" class="mt-4">
                                    <p class="text-sm text-brand-text-muted mb-2">
                                        {{ previewBackground ? (mediaBackgroundName ? `ライブラリ: ${mediaBackgroundName}` : 'プレビュー:') : '現在の画像:' }}
                                    </p>
                                    <img
                                        :src="previewBackground || event.cta_background_url"
                                        alt="ボタン背景"
                                        class="max-w-md h-24 object-cover rounded border border-brand-border"
                                    />
                                </div>
                            </div>

                            <!-- WEB予約ボタン -->
                            <div class="border-b border-brand-border pb-8">
                                <label class="block text-sm font-medium text-brand-text mb-2">WEB予約ボタン画像</label>
                                <label class="mb-3 flex items-center">
                                    <input
                                        v-model="form.cta_web_button_enabled"
                                        type="checkbox"
                                        class="rounded border-brand-border text-rose-600 focus:ring-rose-500"
                                    />
                                    <span class="ml-2 text-sm text-brand-text">WEB予約ボタンを表示する</span>
                                </label>
                                <p class="text-xs text-brand-text-muted mb-3">
                                    「WEB予約」ボタンに表示する画像です。<span v-if="!form.cta_web_button_enabled" class="text-rose-600">（現在は非表示。電話予約のみ全幅表示されます）</span>
                                </p>
                                <div class="flex flex-wrap gap-3 items-center">
                                    <input
                                        ref="webButtonInput"
                                        type="file"
                                        accept="image/jpeg,image/png,image/jpg,image/gif"
                                        class="block flex-1 min-w-[200px] text-sm text-brand-text-muted file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-rose-50 file:text-rose-700 hover:file:bg-rose-100"
                                        @change="handleWebButtonChange"
                                    />
                                    <button
                                        type="button"
                                        @click="openPicker('web')"
                                        class="px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700"
                                    >
                                        ライブラリから選択
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-brand-text-muted">JPEG, PNG, JPG, GIF（最大10MB）</p>
                                <label class="mt-3 flex items-center">
                                    <input
                                        v-model="form.remove_cta_web_button"
                                        type="checkbox"
                                        class="rounded border-brand-border text-rose-600 focus:ring-rose-500"
                                        @change="onRemoveCheck('web')"
                                    />
                                    <span class="ml-2 text-sm text-brand-text">画像を削除して未設定に戻す</span>
                                </label>
                                <div v-if="previewWebButton || (event.cta_web_button_url && !form.remove_cta_web_button)" class="mt-4">
                                    <p class="text-sm text-brand-text-muted mb-2">
                                        {{ previewWebButton ? (mediaWebName ? `ライブラリ: ${mediaWebName}` : 'プレビュー:') : '現在の画像:' }}
                                    </p>
                                    <img
                                        :src="previewWebButton || event.cta_web_button_url"
                                        alt="WEB予約ボタン"
                                        class="max-w-xs h-16 object-contain rounded border border-brand-border"
                                    />
                                </div>
                            </div>

                            <!-- 電話予約ボタン -->
                            <div class="pb-8">
                                <label class="block text-sm font-medium text-brand-text mb-2">電話予約ボタン画像</label>
                                <label class="mb-3 flex items-center">
                                    <input
                                        v-model="form.cta_phone_button_enabled"
                                        type="checkbox"
                                        class="rounded border-brand-border text-rose-600 focus:ring-rose-500"
                                    />
                                    <span class="ml-2 text-sm text-brand-text">電話予約ボタンを表示する</span>
                                </label>
                                <p class="text-xs text-brand-text-muted mb-3">
                                    「電話予約」ボタンに表示する画像です。<span v-if="!form.cta_phone_button_enabled" class="text-rose-600">（現在は非表示。WEB予約のみ全幅表示されます）</span>
                                </p>
                                <div class="flex flex-wrap gap-3 items-center">
                                    <input
                                        ref="phoneButtonInput"
                                        type="file"
                                        accept="image/jpeg,image/png,image/jpg,image/gif"
                                        class="block flex-1 min-w-[200px] text-sm text-brand-text-muted file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-rose-50 file:text-rose-700 hover:file:bg-rose-100"
                                        @change="handlePhoneButtonChange"
                                    />
                                    <button
                                        type="button"
                                        @click="openPicker('phone')"
                                        class="px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700"
                                    >
                                        ライブラリから選択
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-brand-text-muted">JPEG, PNG, JPG, GIF（最大10MB）</p>
                                <label class="mt-3 flex items-center">
                                    <input
                                        v-model="form.remove_cta_phone_button"
                                        type="checkbox"
                                        class="rounded border-brand-border text-rose-600 focus:ring-rose-500"
                                        @change="onRemoveCheck('phone')"
                                    />
                                    <span class="ml-2 text-sm text-brand-text">画像を削除して未設定に戻す</span>
                                </label>
                                <div v-if="previewPhoneButton || (event.cta_phone_button_url && !form.remove_cta_phone_button)" class="mt-4">
                                    <p class="text-sm text-brand-text-muted mb-2">
                                        {{ previewPhoneButton ? (mediaPhoneName ? `ライブラリ: ${mediaPhoneName}` : 'プレビュー:') : '現在の画像:' }}
                                    </p>
                                    <img
                                        :src="previewPhoneButton || event.cta_phone_button_url"
                                        alt="電話予約ボタン"
                                        class="max-w-xs h-16 object-contain rounded border border-brand-border"
                                    />
                                </div>
                            </div>

                            <!-- テーマカラー（CTA・ナビ・予約フォーム共有） -->
                            <div class="border-b border-brand-border pb-8">
                                <label class="block text-sm font-medium text-brand-text mb-2">テーマカラー</label>
                                <p class="text-xs text-brand-text-muted mb-3">1色設定すると、固定CTAボタン・ヘッダー/メニュー・画像間の予約ボタン・予約フォームのアクセント色がLP全体で統一されます（旧「CTAボタンの色」設定はこちらに統合されました）。未設定の場合は従来の配色のまま表示されます。</p>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <input
                                        type="color"
                                        :value="form.theme_color || '#c96f4a'"
                                        class="h-10 w-16 rounded border border-brand-border cursor-pointer"
                                        @input="form.theme_color = $event.target.value"
                                    />
                                    <input
                                        v-model="form.theme_color"
                                        type="text"
                                        placeholder="#C96F4A"
                                        maxlength="7"
                                        class="rounded-md border-brand-border shadow-sm w-32 font-mono text-sm"
                                    />
                                    <button type="button" class="text-sm text-brand-text-muted underline" @click="form.theme_color = ''">解除（既定色）</button>
                                </div>
                                <div class="flex items-center gap-2 mt-3">
                                    <span class="text-xs text-brand-text-muted mr-1">プリセット:</span>
                                    <button
                                        v-for="preset in themePresets"
                                        :key="preset.color"
                                        type="button"
                                        class="h-8 w-8 rounded-full border-2 transition-transform hover:scale-110"
                                        :style="{ backgroundColor: preset.color, borderColor: form.theme_color === preset.color ? '#111' : '#fff' }"
                                        :title="preset.name"
                                        @click="form.theme_color = preset.color"
                                    ></button>
                                </div>
                            </div>

                            <div class="flex justify-end items-center gap-4 pt-6 border-t border-brand-border mt-8">
                                <span class="text-sm text-brand-text-muted">画像を選択・変更したら必ず押してください</span>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center px-5 py-2.5 border border-transparent rounded-md font-medium text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-primary disabled:opacity-50 disabled:cursor-not-allowed hover:opacity-90 transition-opacity"
                                    style="background-color: #4f46e5;"
                                >
                                    {{ form.processing ? '更新中...' : '保存する' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- メディアライブラリピッカー -->
        <MediaPicker
            :show="showMediaPicker"
            @close="showMediaPicker = false"
            @select="handleMediaSelect"
        />
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import EventNavigation from '@/Components/EventNavigation.vue';
import MediaPicker from '@/Components/MediaPicker.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
});

const backgroundInput = ref(null);
const webButtonInput = ref(null);
const phoneButtonInput = ref(null);
const previewBackground = ref(null);
const previewWebButton = ref(null);
const previewPhoneButton = ref(null);

// ライブラリ選択時のファイル名表示用
const mediaBackgroundName = ref(null);
const mediaWebName = ref(null);
const mediaPhoneName = ref(null);

const showMediaPicker = ref(false);
const pickerTarget = ref(null); // 'background' | 'web' | 'phone'

const form = useForm({
    cta_background: null,
    cta_web_button: null,
    cta_phone_button: null,
    remove_cta_background: false,
    remove_cta_web_button: false,
    remove_cta_phone_button: false,
    media_cta_background_id: null,
    media_cta_web_button_id: null,
    media_cta_phone_button_id: null,
    cta_color_type: props.event.cta_color_type || 'red',
    theme_color: props.event.theme_color || '',
    cta_web_button_enabled: props.event.cta_web_button_enabled ?? true,
    cta_phone_button_enabled: props.event.cta_phone_button_enabled ?? true,
});

// テーマカラーのプリセット（4デザイン案）
const themePresets = [
    { name: '和モダン（臙脂）', color: '#8e2f3c' },
    { name: '青春ポップ（コーラル）', color: '#ff5d7e' },
    { name: 'パステル清楚（ローズ）', color: '#d98ba6' },
    { name: '秋オータム（テラコッタ）', color: '#c96f4a' },
];

function openPicker(target) {
    pickerTarget.value = target;
    showMediaPicker.value = true;
}

function handleMediaSelect(selectedMedia) {
    if (!selectedMedia || selectedMedia.length === 0) return;
    const media = selectedMedia[0]; // CTA画像は1枚ずつ

    if (pickerTarget.value === 'background') {
        form.media_cta_background_id = media.id;
        form.cta_background = null;
        form.remove_cta_background = false;
        previewBackground.value = media.url;
        mediaBackgroundName.value = media.original_filename;
        if (backgroundInput.value) backgroundInput.value.value = '';
    } else if (pickerTarget.value === 'web') {
        form.media_cta_web_button_id = media.id;
        form.cta_web_button = null;
        form.remove_cta_web_button = false;
        previewWebButton.value = media.url;
        mediaWebName.value = media.original_filename;
        if (webButtonInput.value) webButtonInput.value.value = '';
    } else if (pickerTarget.value === 'phone') {
        form.media_cta_phone_button_id = media.id;
        form.cta_phone_button = null;
        form.remove_cta_phone_button = false;
        previewPhoneButton.value = media.url;
        mediaPhoneName.value = media.original_filename;
        if (phoneButtonInput.value) phoneButtonInput.value.value = '';
    }
}

// 「削除」チェックが入ったらライブラリ選択をクリア
function onRemoveCheck(target) {
    if (target === 'background' && form.remove_cta_background) {
        form.media_cta_background_id = null;
        form.cta_background = null;
        previewBackground.value = null;
        mediaBackgroundName.value = null;
    } else if (target === 'web' && form.remove_cta_web_button) {
        form.media_cta_web_button_id = null;
        form.cta_web_button = null;
        previewWebButton.value = null;
        mediaWebName.value = null;
    } else if (target === 'phone' && form.remove_cta_phone_button) {
        form.media_cta_phone_button_id = null;
        form.cta_phone_button = null;
        previewPhoneButton.value = null;
        mediaPhoneName.value = null;
    }
}

function handleBackgroundChange(event) {
    const file = event.target.files?.[0];
    if (file) {
        form.cta_background = file;
        form.media_cta_background_id = null;
        mediaBackgroundName.value = null;
        const reader = new FileReader();
        reader.onload = (e) => { previewBackground.value = e.target?.result ?? null; };
        reader.readAsDataURL(file);
        form.remove_cta_background = false;
    }
}

function handleWebButtonChange(event) {
    const file = event.target.files?.[0];
    if (file) {
        form.cta_web_button = file;
        form.media_cta_web_button_id = null;
        mediaWebName.value = null;
        const reader = new FileReader();
        reader.onload = (e) => { previewWebButton.value = e.target?.result ?? null; };
        reader.readAsDataURL(file);
        form.remove_cta_web_button = false;
    }
}

function handlePhoneButtonChange(event) {
    const file = event.target.files?.[0];
    if (file) {
        form.cta_phone_button = file;
        form.media_cta_phone_button_id = null;
        mediaPhoneName.value = null;
        const reader = new FileReader();
        reader.onload = (e) => { previewPhoneButton.value = e.target?.result ?? null; };
        reader.readAsDataURL(file);
        form.remove_cta_phone_button = false;
    }
}

function submit() {
    form.post(route('admin.events.cta-design.update', props.event.id), {
        forceFormData: true,
    });
}
</script>
