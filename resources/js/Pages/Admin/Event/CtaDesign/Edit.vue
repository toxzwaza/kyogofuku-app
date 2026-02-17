<template>
    <Head title="CTAデザイン設定" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
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
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <EventNavigation :event="event" :show-url-button="false" />
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <p class="text-sm text-gray-600 mb-2">
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
                            <div class="border-b border-gray-200 pb-8">
                                <label class="block text-sm font-medium text-gray-700 mb-2">ボタン背景ブロック用画像</label>
                                <p class="text-xs text-gray-500 mb-3">固定CTAエリア全体の背景画像です。</p>
                                <input
                                    ref="backgroundInput"
                                    type="file"
                                    accept="image/jpeg,image/png,image/jpg,image/gif"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-rose-50 file:text-rose-700 hover:file:bg-rose-100"
                                    @change="handleBackgroundChange"
                                />
                                <p class="mt-1 text-xs text-gray-500">JPEG, PNG, JPG, GIF（最大10MB）</p>
                                <label class="mt-3 flex items-center">
                                    <input
                                        v-model="form.remove_cta_background"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-rose-600 focus:ring-rose-500"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">画像を削除して未設定に戻す</span>
                                </label>
                                <div v-if="previewBackground || (event.cta_background_url && !form.remove_cta_background)" class="mt-4">
                                    <p class="text-sm text-gray-600 mb-2">{{ previewBackground ? 'プレビュー:' : '現在の画像:' }}</p>
                                    <img
                                        :src="previewBackground || event.cta_background_url"
                                        alt="ボタン背景"
                                        class="max-w-md h-24 object-cover rounded border border-gray-300"
                                    />
                                </div>
                            </div>

                            <!-- WEB予約ボタン -->
                            <div class="border-b border-gray-200 pb-8">
                                <label class="block text-sm font-medium text-gray-700 mb-2">WEB予約ボタン画像</label>
                                <p class="text-xs text-gray-500 mb-3">「WEB予約」ボタンに表示する画像です。</p>
                                <input
                                    ref="webButtonInput"
                                    type="file"
                                    accept="image/jpeg,image/png,image/jpg,image/gif"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-rose-50 file:text-rose-700 hover:file:bg-rose-100"
                                    @change="handleWebButtonChange"
                                />
                                <p class="mt-1 text-xs text-gray-500">JPEG, PNG, JPG, GIF（最大10MB）</p>
                                <label class="mt-3 flex items-center">
                                    <input
                                        v-model="form.remove_cta_web_button"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-rose-600 focus:ring-rose-500"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">画像を削除して未設定に戻す</span>
                                </label>
                                <div v-if="previewWebButton || (event.cta_web_button_url && !form.remove_cta_web_button)" class="mt-4">
                                    <p class="text-sm text-gray-600 mb-2">{{ previewWebButton ? 'プレビュー:' : '現在の画像:' }}</p>
                                    <img
                                        :src="previewWebButton || event.cta_web_button_url"
                                        alt="WEB予約ボタン"
                                        class="max-w-xs h-16 object-contain rounded border border-gray-300"
                                    />
                                </div>
                            </div>

                            <!-- 電話予約ボタン -->
                            <div class="pb-8">
                                <label class="block text-sm font-medium text-gray-700 mb-2">電話予約ボタン画像</label>
                                <p class="text-xs text-gray-500 mb-3">「電話予約」ボタンに表示する画像です。</p>
                                <input
                                    ref="phoneButtonInput"
                                    type="file"
                                    accept="image/jpeg,image/png,image/jpg,image/gif"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-rose-50 file:text-rose-700 hover:file:bg-rose-100"
                                    @change="handlePhoneButtonChange"
                                />
                                <p class="mt-1 text-xs text-gray-500">JPEG, PNG, JPG, GIF（最大10MB）</p>
                                <label class="mt-3 flex items-center">
                                    <input
                                        v-model="form.remove_cta_phone_button"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-rose-600 focus:ring-rose-500"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">画像を削除して未設定に戻す</span>
                                </label>
                                <div v-if="previewPhoneButton || (event.cta_phone_button_url && !form.remove_cta_phone_button)" class="mt-4">
                                    <p class="text-sm text-gray-600 mb-2">{{ previewPhoneButton ? 'プレビュー:' : '現在の画像:' }}</p>
                                    <img
                                        :src="previewPhoneButton || event.cta_phone_button_url"
                                        alt="電話予約ボタン"
                                        class="max-w-xs h-16 object-contain rounded border border-gray-300"
                                    />
                                </div>
                            </div>

                            <!-- CTAボタンの色（画像間のインラインボタン） -->
                            <div class="border-b border-gray-200 pb-8">
                                <label class="block text-sm font-medium text-gray-700 mb-2">CTAボタンの色</label>
                                <p class="text-xs text-gray-500 mb-3">公開ページの画像間にある「予約する」ボタンの色です。アニメーションは共通で、色のみ変更できます。</p>
                                <select
                                    v-model="form.cta_color_type"
                                    class="rounded-md border-gray-300 shadow-sm max-w-xs"
                                >
                                    <option value="red">赤系（既定）</option>
                                    <option value="pink">ピンク系</option>
                                    <option value="rose">ローズ系</option>
                                    <option value="orange">オレンジ系</option>
                                    <option value="amber">アンバー系</option>
                                    <option value="purple">パープル系</option>
                                    <option value="violet">バイオレット系</option>
                                    <option value="indigo">インディゴ系</option>
                                    <option value="blue">青系</option>
                                    <option value="sky">スカイ系</option>
                                    <option value="cyan">シアン系</option>
                                    <option value="teal">ティール系</option>
                                    <option value="green">緑系</option>
                                    <option value="emerald">エメラルド系</option>
                                </select>
                            </div>

                            <div class="flex justify-end items-center gap-4 pt-6 border-t border-gray-200 mt-8">
                                <span class="text-sm text-gray-500">画像を選択・変更したら必ず押してください</span>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center px-5 py-2.5 border border-transparent rounded-md font-medium text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed hover:opacity-90 transition-opacity"
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
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import EventNavigation from '@/Components/EventNavigation.vue';
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

const form = useForm({
    cta_background: null,
    cta_web_button: null,
    cta_phone_button: null,
    remove_cta_background: false,
    remove_cta_web_button: false,
    remove_cta_phone_button: false,
    cta_color_type: props.event.cta_color_type || 'red',
});

function handleBackgroundChange(event) {
    const file = event.target.files?.[0];
    if (file) {
        form.cta_background = file;
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
