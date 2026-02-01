<template>
    <Head title="制約追加" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">制約追加</h2>
                <ActionButton variant="back" label="制約一覧に戻る" :href="route('admin.constraint-templates.index')" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- 左: 編集フォーム -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <form @submit.prevent="submit">
                                <div class="space-y-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            制約名 <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            required
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="例：京呉服好一 ご利用規約（レンタル・お持込）"
                                        />
                                        <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                            {{ form.errors.name }}
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            対象店舗
                                        </label>
                                        <p class="text-xs text-gray-500 mb-2">規約説明者の選択元となる店舗を選択（複数可）</p>
                                        <div class="flex flex-wrap gap-3">
                                            <label
                                                v-for="shop in shops"
                                                :key="shop.id"
                                                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm cursor-pointer hover:bg-gray-100"
                                            >
                                                <input
                                                    v-model="form.shop_ids"
                                                    type="checkbox"
                                                    :value="shop.id"
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                {{ shop.name }}
                                            </label>
                                        </div>
                                        <div v-if="form.errors.shop_ids" class="mt-1 text-sm text-red-600">
                                            {{ form.errors.shop_ids }}
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            制約本文（マークダウン） <span class="text-red-500">*</span>
                                        </label>
                                        <p class="text-xs text-gray-500 mb-2">
                                            マークダウン形式で記入。チェック項目は <code class="bg-gray-100 px-1 rounded">- [ ] ラベル</code> で記述してください。
                                        </p>
                                        <textarea
                                            v-model="form.body"
                                            rows="16"
                                            required
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                                            placeholder="# タイトル&#10;&#10;## セクション&#10;- 項目1&#10;- [ ] チェック項目1"
                                        ></textarea>
                                        <div v-if="form.errors.body" class="mt-1 text-sm text-red-600">
                                            {{ form.errors.body }}
                                        </div>
                                    </div>

                                    <!-- 表示設定 -->
                                    <div class="border-t border-gray-200 pt-6">
                                        <h3 class="text-sm font-semibold text-gray-800 mb-3">表示設定（印刷・プレビュー用）</h3>
                                        <p class="text-xs text-gray-500 mb-2">右側のプレビューでリアルタイムに確認できます。</p>
                                        <button
                                            type="button"
                                            @click="openPreviewPage"
                                            :disabled="!form.name || !form.body"
                                            class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium mb-4 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            実際の印刷プレビューページで確認（別タブで開く）
                                        </button>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">余白（mm）</label>
                                                <input
                                                    v-model.number="form.display_settings.padding_mm"
                                                    type="number"
                                                    min="2"
                                                    max="20"
                                                    step="1"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">行間</label>
                                                <input
                                                    v-model.number="form.display_settings.line_height"
                                                    type="number"
                                                    min="1"
                                                    max="2.5"
                                                    step="0.1"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">フォントサイズ（px）</label>
                                                <input
                                                    v-model.number="form.display_settings.font_size_px"
                                                    type="number"
                                                    min="6"
                                                    max="24"
                                                    step="1"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">署名欄高さ（px）</label>
                                                <input
                                                    v-model.number="form.display_settings.signature_height_px"
                                                    type="number"
                                                    min="40"
                                                    max="200"
                                                    step="5"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">チェックボックス（px）</label>
                                                <input
                                                    v-model.number="form.display_settings.checkbox_size_px"
                                                    type="number"
                                                    min="6"
                                                    max="24"
                                                    step="1"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                />
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
                                        <p class="mt-1 text-xs text-gray-500">無効にすると、顧客への制約追加で選択できなくなります。</p>
                                        <div v-if="form.errors.is_active" class="mt-1 text-sm text-red-600">
                                            {{ form.errors.is_active }}
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <ActionButton
                                            variant="save"
                                            type="submit"
                                            :disabled="form.processing"
                                        >
                                            {{ form.processing ? '保存中...' : '保存' }}
                                        </ActionButton>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 右: プレビュー -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg h-fit">
                        <div class="p-4 border-b border-gray-200 bg-gray-50">
                            <h3 class="text-sm font-semibold text-gray-800">プレビュー</h3>
                            <p class="text-xs text-gray-500 mt-0.5">印刷時の表示イメージです</p>
                        </div>
                        <div
                            class="p-4 overflow-auto max-h-[calc(100vh-280px)] preview-container"
                            :style="previewContainerStyle"
                        >
                            <div class="preview-inner bg-white border border-gray-200 rounded-lg shadow-inner" :style="previewInnerStyle">
                                <div class="mb-2 pb-2 border-b border-gray-200">
                                    <p class="text-gray-600" :style="{ fontSize: ds.font_size_px + 'px' }">
                                        顧客名: <span class="font-semibold text-gray-900">{{ form.name || '（制約名）' }}</span>
                                    </p>
                                </div>
                                <h2
                                    class="text-center font-bold text-gray-900 mb-4"
                                    :style="{ fontSize: Math.min(ds.font_size_px + 2, 14) + 'px', lineHeight: ds.line_height }"
                                >
                                    {{ form.name || '制約タイトル' }}
                                </h2>
                                <div class="constraint-preview-body" :style="{ fontSize: ds.font_size_px + 'px', lineHeight: ds.line_height }">
                                    <ConstraintBodyWithChecks
                                        :body="form.body"
                                        :check-values="previewCheckValues"
                                        @update:check-values="(v) => (previewCheckValues = v)"
                                        :readonly="false"
                                        :display-settings="ds"
                                    />
                                </div>
                                <div class="border-t border-gray-200 mt-4 pt-4">
                                    <div class="grid grid-cols-2 gap-4 mb-4" :style="{ fontSize: ds.font_size_px + 'px' }">
                                        <div>
                                            <span class="text-gray-600">日付：</span>
                                            <span class="font-semibold">____年____月____日</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">規約説明者：</span>
                                            <span class="font-semibold">________________</span>
                                        </div>
                                    </div>
                                    <div class="mb-2" :style="{ fontSize: ds.font_size_px + 'px' }">
                                        <span class="text-gray-600">ご署名</span>
                                    </div>
                                    <div
                                        class="border-2 border-dashed border-gray-300 rounded bg-gray-50 flex items-center justify-center text-gray-400 text-sm"
                                        :style="{ height: ds.signature_height_px + 'px', fontSize: Math.max(10, ds.font_size_px - 2) + 'px' }"
                                    >
                                        署名欄（{{ ds.signature_height_px }}px）
                                    </div>
                                    <div class="text-right mt-2" :style="{ fontSize: ds.font_size_px + 'px' }">
                                        <span class="font-semibold">__________</span> 様
                                    </div>
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
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import ConstraintBodyWithChecks from '@/Components/ConstraintBodyWithChecks.vue';
import { Head, useForm } from '@inertiajs/vue3';

const DEFAULT_DISPLAY_SETTINGS = {
    padding_mm: 6,
    line_height: 1.25,
    font_size_px: 8,
    signature_height_px: 80,
    checkbox_size_px: 10,
};

const props = defineProps({
    shops: Array,
});

const form = useForm({
    name: '',
    body: '',
    is_active: true,
    shop_ids: [],
    display_settings: { ...DEFAULT_DISPLAY_SETTINGS },
});

const previewCheckValues = ref({});

const ds = computed(() => ({
    ...DEFAULT_DISPLAY_SETTINGS,
    ...form.display_settings,
}));

const previewContainerStyle = computed(() => ({
    padding: `${ds.value.padding_mm}mm`,
}));

const previewInnerStyle = computed(() => ({
    padding: `${Math.max(4, ds.value.padding_mm * 0.8)}mm`,
}));

const openPreviewPage = () => {
    if (!form.name || !form.body) return;
    const formEl = document.createElement('form');
    formEl.method = 'POST';
    formEl.action = route('admin.constraint-templates.preview');
    formEl.target = '_blank';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrf) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_token';
        input.value = csrf;
        formEl.appendChild(input);
    }
    const addInput = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = typeof value === 'object' ? JSON.stringify(value) : value;
        formEl.appendChild(input);
    };
    addInput('name', form.name);
    addInput('body', form.body);
    addInput('display_settings[padding_mm]', ds.value.padding_mm);
    addInput('display_settings[line_height]', ds.value.line_height);
    addInput('display_settings[font_size_px]', ds.value.font_size_px);
    addInput('display_settings[signature_height_px]', ds.value.signature_height_px);
    addInput('display_settings[checkbox_size_px]', ds.value.checkbox_size_px);
    document.body.appendChild(formEl);
    formEl.submit();
    document.body.removeChild(formEl);
};

const submit = () => {
    form.display_settings = { ...ds.value };
    form.post(route('admin.constraint-templates.store'));
};
</script>

<style scoped>
.constraint-preview-body :deep(.markdown-segment p) {
    margin: 0.3em 0;
}
.constraint-preview-body :deep(.markdown-segment ul),
.constraint-preview-body :deep(.markdown-segment ol) {
    margin: 0.3em 0;
    padding-left: 1.2em;
}
.constraint-preview-body :deep(.markdown-segment table) {
    border-collapse: collapse;
    width: 100%;
}
.constraint-preview-body :deep(.markdown-segment th),
.constraint-preview-body :deep(.markdown-segment td) {
    border: 1px solid #e5e7eb;
    padding: 2px 4px;
}
</style>
