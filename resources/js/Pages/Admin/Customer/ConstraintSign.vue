<template>
    <Head :title="`制約署名 - ${customer.name}`" />

    <div class="min-h-screen bg-gray-100 print:bg-white">
        <!-- 印刷時非表示ヘッダー -->
        <nav class="bg-white border-b border-gray-200 print:hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <h1 class="text-xl font-semibold text-gray-800">{{ isEditMode ? '制約署名（編集）' : '制約署名' }}</h1>
                    <Link
                        :href="route('admin.customers.show', customer.id)"
                        class="text-gray-600 hover:text-gray-800 text-sm"
                    >
                        顧客詳細に戻る
                    </Link>
                </div>
            </div>
        </nav>

        <div class="py-6 print:py-0">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 print:max-w-none print:px-8">
                <!-- 印刷用コンテンツ -->
                <div ref="printArea" class="bg-white shadow-lg rounded-lg print:shadow-none print:rounded-none">
                    <!-- A4用紙スタイル -->
                    <div class="p-8 print:p-6 a4-page">
                        <!-- 顧客情報 -->
                        <div class="mb-4 pb-4 border-b border-gray-200 print:mb-3 print:pb-2">
                            <p class="text-sm text-gray-600">顧客名: <span class="font-semibold text-gray-900">{{ customer.name }}</span>
                                <span v-if="customer.kana" class="ml-2 text-gray-500">({{ customer.kana }})</span>
                            </p>
                        </div>

                        <!-- 制約タイトル -->
                        <h2 class="text-xl font-bold text-center text-gray-900 mb-6 print:text-lg print:mb-4">{{ template.name }}</h2>

                        <!-- 制約本文（チェックボックス付き） -->
                        <div class="constraint-body prose prose-sm max-w-none text-gray-800 mb-6 print:text-xs print:mb-4">
                            <ConstraintBodyWithChecks
                                :body="template.body"
                                :check-values="form.check_values"
                                @update:check-values="(v) => form.check_values = v"
                                :readonly="isPrinting"
                            />
                        </div>

                        <!-- 同意・署名セクション -->
                        <div class="border-t border-gray-200 pt-6 print:pt-4">
                            <div class="grid grid-cols-2 gap-6 mb-6 print:gap-4 print:mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1 print:text-xs">日付</label>
                                    <p class="text-gray-900 font-semibold print:text-sm">{{ formatDate(form.signed_at) }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1 print:text-xs">規約説明者</label>
                                    <p class="text-gray-900 font-semibold print:text-sm">{{ explainerDisplayName || '-' }}</p>
                                </div>
                            </div>

                            <!-- 署名欄 -->
                            <div class="mb-4">
                                <div class="flex items-center gap-2 mb-2 print:mb-1">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 print:bg-transparent print:text-black">
                                        <svg class="w-4 h-4 print:w-3 print:h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </div>
                                    <label class="text-sm font-semibold text-gray-700 print:text-xs">ご署名</label>
                                </div>
                                <div
                                    ref="signatureWrap"
                                    class="signature-pad relative overflow-hidden rounded-xl print:rounded-lg"
                                    style="height: 120px;"
                                >
                                    <!-- 背景パターン（署名エリアの視覚的ガイド） -->
                                    <div
                                        v-if="!isPrinting"
                                        class="absolute inset-0 pointer-events-none signature-guide"
                                        aria-hidden="true"
                                    />
                                    <!-- 印刷時: 署名画像を表示 -->
                                    <img
                                        v-if="isPrinting && form.signature_image"
                                        :src="form.signature_image"
                                        alt="署名"
                                        class="h-full w-auto mx-auto object-contain relative z-10"
                                    />
                                    <!-- 通常時: キャンバス -->
                                    <canvas v-show="!isPrinting" ref="signatureCanvas" class="relative z-10" />
                                    <!-- 未署名時のプレースホルダー -->
                                    <div
                                        v-if="!isPrinting && !hasSignature"
                                        class="absolute inset-0 flex items-center justify-center pointer-events-none z-0"
                                    >
                                        <span class="text-gray-400 text-sm font-medium tracking-wider">ここに署名してください</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center mt-3 print:hidden">
                                    <button
                                        type="button"
                                        @click="clearSignature"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        署名をクリア
                                    </button>
                                    <span class="text-xs text-gray-500 font-medium">※マウスまたはタッチで枠内に描いてください</span>
                                </div>
                            </div>

                            <!-- 顧客名表示（様付き） -->
                            <div class="text-right text-gray-700 print:text-sm">
                                <span class="font-semibold">{{ customer.name }}</span> 様
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ボタン（印刷時非表示） -->
                <div class="mt-6 flex justify-center gap-4 print:hidden">
                    <button
                        type="button"
                        @click="handlePrint"
                        class="inline-flex items-center gap-2 px-6 py-3 border border-gray-300 rounded-lg bg-white text-gray-700 font-medium hover:bg-gray-50 shadow-sm"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        印刷
                    </button>
                    <button
                        type="button"
                        @click="saveConstraint"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-8 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-if="form.processing">保存中...</span>
                        <span v-else>保存</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Canvas, PencilBrush } from 'fabric';
import ConstraintBodyWithChecks from '@/Components/ConstraintBodyWithChecks.vue';

const props = defineProps({
    customer: Object,
    template: Object,
    staff: Array,
    signedAt: String,
    explainerUserId: [String, Number],
    explainerName: String,
    checkValues: Object,
    editId: [String, Number],
    existingSignature: String,
});

const isEditMode = computed(() => !!props.editId);

const hasDrawnSignature = ref(false);
const hasSignature = computed(() => form.signature_image || hasDrawnSignature.value);

const form = useForm({
    constraint_template_id: props.template.id,
    signed_at: props.signedAt || new Date().toISOString().split('T')[0],
    signature_image: props.existingSignature || null,
    explainer_user_id: props.explainerUserId || null,
    check_values: props.checkValues || {},
});

const signatureWrap = ref(null);
const signatureCanvas = ref(null);
const fabricCanvas = ref(null);
const isPrinting = ref(false);

const explainerDisplayName = computed(() => {
    if (props.explainerName) return props.explainerName;
    if (!form.explainer_user_id || !props.staff) return null;
    const user = props.staff.find(u => u.id == form.explainer_user_id);
    return user?.name || null;
});

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日`;
};

const initSignatureCanvas = () => {
    disposeSignatureCanvas();
    const wrap = signatureWrap.value;
    const canvasEl = signatureCanvas.value;
    if (!wrap || !canvasEl) return;

    const w = wrap.clientWidth || 400;
    const h = 100;

    const canvas = new Canvas(canvasEl, {
        width: w,
        height: h,
        backgroundColor: '#ffffff',
        enableRetinaScaling: false,
    });
    fabricCanvas.value = canvas;

    const brush = new PencilBrush(canvas);
    brush.color = '#1e3a5f';
    brush.width = 2;
    canvas.freeDrawingBrush = brush;
    canvas.isDrawingMode = true;

    canvas.on('path:created', () => { hasDrawnSignature.value = true; });

    // 既存署名がある場合は読み込む
    if (props.existingSignature) {
        hasDrawnSignature.value = true;
        import('fabric').then(({ FabricImage }) => {
            FabricImage.fromURL(props.existingSignature).then((img) => {
                img.set({ selectable: false, evented: false });
                const scale = Math.min(w / (img.width || 1), h / (img.height || 1), 1);
                img.scale(scale);
                img.set({ left: w / 2, top: h / 2, originX: 'center', originY: 'center' });
                canvas.add(img);
                canvas.sendObjectToBack(img);
                canvas.requestRenderAll();
            }).catch(() => {});
        });
    }

    canvas.requestRenderAll();
};

const disposeSignatureCanvas = () => {
    if (fabricCanvas.value) {
        fabricCanvas.value.dispose();
        fabricCanvas.value = null;
    }
};

const clearSignature = () => {
    const canvas = fabricCanvas.value;
    if (canvas) {
        canvas.clear();
        canvas.backgroundColor = '#ffffff';
        canvas.requestRenderAll();
        hasDrawnSignature.value = false;
    }
};

const captureSignature = () => {
    const canvas = fabricCanvas.value;
    if (canvas) {
        canvas.isDrawingMode = false;
        canvas.requestRenderAll();
        return canvas.toDataURL({ format: 'image/png' });
    }
    return null;
};

const saveConstraint = () => {
    form.signature_image = captureSignature();

    if (isEditMode.value) {
        form.transform((data) => ({
            signed_at: data.signed_at,
            signature_image: data.signature_image,
            explainer_user_id: data.explainer_user_id,
            check_values: data.check_values,
            _method: 'PUT',
        })).post(route('admin.customers.constraints.update', { customer: props.customer.id, customerConstraint: props.editId }), {
            preserveScroll: true,
        });
    } else {
        form.post(route('admin.customers.constraints.store', props.customer.id), {
            preserveScroll: true,
        });
    }
};

const handlePrint = () => {
    // 印刷前に署名をキャプチャ
    form.signature_image = captureSignature();
    isPrinting.value = true;

    nextTick(() => {
        window.print();
        // 印刷ダイアログが閉じた後に戻す
        setTimeout(() => {
            isPrinting.value = false;
            nextTick(() => initSignatureCanvas());
        }, 500);
    });
};

onMounted(() => {
    nextTick(() => initSignatureCanvas());
});

onBeforeUnmount(() => {
    disposeSignatureCanvas();
});
</script>

<style scoped>
/* 署名欄スタイル */
.signature-pad {
    background: linear-gradient(to bottom, #fafafa 0%, #ffffff 100%);
    border: 2px solid #e5e7eb;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.signature-pad:focus-within {
    border-color: #6366f1;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.04), 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.signature-guide {
    background-image: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 39px,
        rgba(99, 102, 241, 0.04) 39px,
        rgba(99, 102, 241, 0.04) 40px
    );
}

@media print {
    .signature-pad {
        border: 1px solid #9ca3af;
        background: #fff;
        box-shadow: none;
    }
}

/* A4サイズ用印刷スタイル */
@media print {
    @page {
        size: A4;
        margin: 10mm;
    }

    body {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .a4-page {
        width: 100%;
        min-height: auto;
        page-break-inside: avoid;
    }

    /* チェックボックスを印刷で表示 */
    input[type="checkbox"] {
        -webkit-appearance: checkbox !important;
        appearance: checkbox !important;
        width: 12px;
        height: 12px;
    }

    input[type="checkbox"]:checked {
        background-color: #4f46e5 !important;
    }
}

/* 制約本文のスタイル */
.constraint-body :deep(p) {
    margin: 0.4em 0;
}

.constraint-body :deep(h1),
.constraint-body :deep(h2),
.constraint-body :deep(h3) {
    margin-top: 1em;
    margin-bottom: 0.5em;
    font-weight: 600;
}

.constraint-body :deep(h1) {
    font-size: 1.25rem;
}

.constraint-body :deep(h2) {
    font-size: 1.1rem;
}

.constraint-body :deep(ul) {
    list-style: disc;
    padding-left: 1.5em;
}

.constraint-body :deep(ol) {
    list-style: decimal;
    padding-left: 1.5em;
}

.constraint-body :deep(table) {
    border-collapse: collapse;
    width: 100%;
    margin: 0.5em 0;
}

.constraint-body :deep(th),
.constraint-body :deep(td) {
    border: 1px solid #d1d5db;
    padding: 0.25em 0.5em;
    font-size: 0.875rem;
}

.constraint-body :deep(th) {
    background-color: #f9fafb;
}

.constraint-body :deep(hr) {
    border: none;
    border-top: 1px solid #e5e7eb;
    margin: 1em 0;
}

@media print {
    .constraint-body :deep(p) {
        margin: 0.2em 0;
        font-size: 10px;
    }

    .constraint-body :deep(h1),
    .constraint-body :deep(h2),
    .constraint-body :deep(h3) {
        margin-top: 0.5em;
        margin-bottom: 0.25em;
    }

    .constraint-body :deep(h1) {
        font-size: 14px;
    }

    .constraint-body :deep(h2) {
        font-size: 12px;
    }

    .constraint-body :deep(table) {
        font-size: 9px;
    }

    .constraint-body :deep(th),
    .constraint-body :deep(td) {
        padding: 2px 4px;
    }
}
</style>
