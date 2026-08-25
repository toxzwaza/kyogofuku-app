<script setup>
import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Canvas, FabricImage, Textbox } from 'fabric';
import Modal from '@/Components/Modal.vue';
import { UiButton } from '@/Components/UI';
import { Image as ImageIcon, Trash2, X as XIcon, Save, Type as TypeIcon, RotateCcw } from 'lucide-vue-next';

const props = defineProps({
    show: { type: Boolean, default: false },
    customer: { type: Object, required: true },
    questionnaire: { type: Object, required: true }, // page2_url, placements
});

const emit = defineEmits(['close']);

// キャンバス表示幅（高さはA4比率で決まる）
const CANVAS_WIDTH = 560;
const CANVAS_HEIGHT = Math.round(CANVAS_WIDTH * 297 / 210);
// 合成画像の出力倍率（1240px幅相当）
const EXPORT_MULTIPLIER = 1240 / CANVAS_WIDTH;

// 写真添付欄のガイド領域（用紙比率。print.blade.php のレイアウトから算出）
const GUIDE_AREA = { left: 0.057, top: 0.18, right: 0.943, bottom: 0.85 };

// 選択枠・ハンドルのスタイル（背景のスキャン上でも見やすい赤）
const SELECTION_STYLE = {
    borderColor: '#ef4444',
    cornerColor: '#ef4444',
    cornerStrokeColor: '#ffffff',
    cornerSize: 12,
    transparentCorners: false,
    borderScaleFactor: 2,
};

const canvasElRef = ref(null);
const saving = ref(false);
const errorMessage = ref('');
const hasSelection = ref(false);

let fabricCanvas = null;
let objectUrls = [];

/**
 * S3署名URLの画像をBlob経由で読み込む。
 * <img>タグが同じURLをCORSなしで先に読み込むとブラウザキャッシュに
 * CORSヘッダーなしの応答が残り、crossOrigin付きの読み込みが失敗するため、
 * cache: 'no-store' でキャッシュを迂回してBlob URLに変換する
 * （Blob URLは同一オリジン扱いなのでcanvasも汚染されない）
 */
async function fetchAsObjectUrl(url) {
    const res = await fetch(url, { mode: 'cors', cache: 'no-store' });
    if (!res.ok) throw new Error(`画像の取得に失敗しました (${res.status})`);
    const objectUrl = URL.createObjectURL(await res.blob());
    objectUrls.push(objectUrl);
    return objectUrl;
}

// 配置候補: 顧客写真のうち画像のみ（PDF・アンケートスキャン自体は除外）
const palettePhotos = computed(() => {
    return (props.customer.photos || []).filter((p) => {
        if (!p.url || (p.file_path || '').toLowerCase().endsWith('.pdf')) return false;
        if ([props.questionnaire.page1_photo_id, props.questionnaire.page2_photo_id].includes(p.id)) return false;
        return true;
    });
});

watch(() => props.show, async (show) => {
    if (show) {
        await nextTick();
        await initCanvas();
    } else {
        disposeCanvas();
    }
});

async function initCanvas() {
    disposeCanvas();
    errorMessage.value = '';

    fabricCanvas = new Canvas(canvasElRef.value, {
        width: CANVAS_WIDTH,
        height: CANVAS_HEIGHT,
        preserveObjectStacking: true,
        selection: false,
    });

    fabricCanvas.on('selection:created', () => (hasSelection.value = true));
    fabricCanvas.on('selection:updated', () => (hasSelection.value = true));
    fabricCanvas.on('selection:cleared', () => (hasSelection.value = false));

    try {
        // 背景 = 2ページ目スキャン
        const bg = await FabricImage.fromURL(await fetchAsObjectUrl(props.questionnaire.page2_url));
        bg.scaleX = CANVAS_WIDTH / bg.width;
        bg.scaleY = CANVAS_HEIGHT / bg.height;
        fabricCanvas.backgroundImage = bg;

        // 保存済み配置の復元（写真・テキスト）
        // 既存データは数値が文字列で保存されている場合があるため必ず数値化して扱う
        for (const raw of (props.questionnaire.placements || [])) {
            const placement = normalizePlacement(raw);
            if ((placement.type ?? 'photo') === 'text') {
                addTextToCanvas(placement);
                continue;
            }
            const photo = (props.customer.photos || []).find((p) => p.id === placement.customer_photo_id);
            if (!photo?.url) continue;
            await addPhotoToCanvas(photo, placement);
        }

        fabricCanvas.requestRenderAll();
        // モーダルの開閉アニメーション（scale変形）中に計算した座標オフセットがずれるため再計算
        setTimeout(() => fabricCanvas && fabricCanvas.calcOffset(), 350);
    } catch (e) {
        errorMessage.value = '画像の読み込みに失敗しました。再度開き直してください。';
    }
}

async function addPhotoToCanvas(photo, placement = null) {
    let img;
    try {
        img = await FabricImage.fromURL(await fetchAsObjectUrl(photo.url));
    } catch (e) {
        errorMessage.value = '写真の読み込みに失敗しました。ページを再読み込みしてお試しください。';
        return;
    }
    img.customerPhotoId = photo.id;

    if (placement) {
        img.set({
            left: placement.left * CANVAS_WIDTH,
            top: placement.top * CANVAS_HEIGHT,
            angle: placement.angle,
        });
        img.scale(placement.scale * CANVAS_WIDTH / img.width);
    } else {
        // 初期配置: ガイド領域内に幅30%で置く
        const targetWidth = CANVAS_WIDTH * 0.3;
        img.scale(targetWidth / img.width);
        img.set({
            left: GUIDE_AREA.left * CANVAS_WIDTH + 12 + (fabricCanvas.getObjects().length * 16) % 80,
            top: GUIDE_AREA.top * CANVAS_HEIGHT + 12 + (fabricCanvas.getObjects().length * 16) % 80,
        });
    }
    img.set(SELECTION_STYLE);

    fabricCanvas.add(img);
    fabricCanvas.setActiveObject(img);
    fabricCanvas.requestRenderAll();
}

/**
 * テキストを追加（placement指定時は保存済みの復元）
 * ダブルクリック（ダブルタップ）で内容を編集できる
 */
function addTextToCanvas(placement = null) {
    const text = new Textbox(placement?.text ?? 'テキストを入力', {
        left: placement ? placement.left * CANVAS_WIDTH : GUIDE_AREA.left * CANVAS_WIDTH + 20,
        top: placement ? placement.top * CANVAS_HEIGHT : GUIDE_AREA.top * CANVAS_HEIGHT + 20,
        angle: placement?.angle ?? 0,
        fontSize: (placement?.font_size ?? 24 / CANVAS_WIDTH) * CANVAS_WIDTH,
        width: (placement?.width ?? 200 / CANVAS_WIDTH) * CANVAS_WIDTH,
        scaleX: placement?.scale_x ?? 1,
        scaleY: placement?.scale_y ?? 1,
        fill: '#111111',
        fontFamily: 'sans-serif',
        ...SELECTION_STYLE,
    });
    text.isPlacedText = true;
    fabricCanvas.add(text);
    if (!placement) {
        fabricCanvas.setActiveObject(text);
    }
    fabricCanvas.requestRenderAll();
}

// 保存済み配置の数値項目を数値化する（FormData経由で文字列化された既存データ対策）
function normalizePlacement(raw) {
    const p = { ...raw };
    for (const key of ['left', 'top', 'angle', 'scale', 'scale_x', 'scale_y', 'font_size', 'width']) {
        if (p[key] !== undefined && p[key] !== null) p[key] = Number(p[key]);
    }
    if (p.customer_photo_id !== undefined && p.customer_photo_id !== null) {
        p.customer_photo_id = Number(p.customer_photo_id);
    }
    return p;
}

function removeSelected() {
    const active = fabricCanvas?.getActiveObject();
    if (active && (active.customerPhotoId || active.isPlacedText)) {
        fabricCanvas.remove(active);
        fabricCanvas.discardActiveObject();
        fabricCanvas.requestRenderAll();
    }
}

// 配置をすべて消してまっさらな状態から編集し直す
function resetPlacements() {
    if (!fabricCanvas) return;
    if (!confirm('配置した写真・テキストをすべて消してやり直しますか？')) return;
    fabricCanvas.remove(...fabricCanvas.getObjects());
    fabricCanvas.discardActiveObject();
    fabricCanvas.requestRenderAll();
}

function collectPlacements() {
    return fabricCanvas.getObjects()
        .filter((o) => o.customerPhotoId || o.isPlacedText)
        .map((o) => {
            if (o.isPlacedText) {
                return {
                    type: 'text',
                    text: o.text,
                    left: o.left / CANVAS_WIDTH,
                    top: o.top / CANVAS_HEIGHT,
                    angle: o.angle || 0,
                    font_size: o.fontSize / CANVAS_WIDTH,
                    width: o.width / CANVAS_WIDTH,
                    scale_x: o.scaleX,
                    scale_y: o.scaleY,
                };
            }
            return {
                type: 'photo',
                customer_photo_id: o.customerPhotoId,
                left: o.left / CANVAS_WIDTH,
                top: o.top / CANVAS_HEIGHT,
                scale: (o.scaleX * o.width) / CANVAS_WIDTH, // 用紙幅に対する表示幅の比率
                angle: o.angle || 0,
            };
        });
}

async function save() {
    if (!fabricCanvas) return;
    saving.value = true;
    errorMessage.value = '';

    try {
        fabricCanvas.discardActiveObject();
        fabricCanvas.requestRenderAll();

        const dataUrl = fabricCanvas.toDataURL({
            format: 'jpeg',
            quality: 0.9,
            multiplier: EXPORT_MULTIPLIER,
        });
        const blob = await (await fetch(dataUrl)).blob();

        const fd = new FormData();
        fd.append('_method', 'PUT');
        const placements = collectPlacements()
            .filter((p) => p.type !== 'text' || p.text.trim() !== '');
        placements.forEach((p, i) => {
            for (const [key, value] of Object.entries(p)) {
                if (value !== undefined && value !== null) {
                    fd.append(`placements[${i}][${key}]`, value);
                }
            }
        });
        fd.append('composed_image', blob, 'composed_page2.jpg');

        await axios.post(
            route('admin.customers.questionnaire.placements.update', props.customer.id),
            fd
        );

        router.reload({ only: ['questionnaire'], preserveScroll: true });
        emit('close');
    } catch (e) {
        errorMessage.value = e?.response?.data?.message
            || '保存に失敗しました。画像の読み込み設定（CORS）が原因の場合があります。';
    } finally {
        saving.value = false;
    }
}

function disposeCanvas() {
    if (fabricCanvas) {
        fabricCanvas.dispose();
        fabricCanvas = null;
    }
    objectUrls.forEach((u) => URL.revokeObjectURL(u));
    objectUrls = [];
    hasSelection.value = false;
}

onBeforeUnmount(disposeCanvas);
</script>

<template>
    <Modal :show="show" max-width="4xl" @close="emit('close')">
        <div class="p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-brand-text flex items-center gap-2">
                    <ImageIcon :size="18" class="text-brand-primary" />
                    写真添付欄に写真を配置
                </h3>
                <button class="text-brand-text-muted hover:text-brand-text" @click="emit('close')">
                    <XIcon :size="20" />
                </button>
            </div>

            <div class="flex gap-4">
                <!-- キャンバス（touch-none: タブレットでドラッグがスクロールに奪われるのを防ぐ） -->
                <div class="flex-shrink-0 border border-brand-border rounded-lg overflow-hidden bg-brand-surface-2 touch-none">
                    <canvas ref="canvasElRef"></canvas>
                </div>

                <!-- パレット -->
                <div class="flex-1 min-w-0 flex flex-col">
                    <p class="text-xs text-brand-text-muted mb-2">
                        クリックで配置 → ドラッグで移動、四隅ハンドルで拡大縮小・回転。<br>
                        テキストはダブルクリック（ダブルタップ）で内容を編集できます。
                    </p>
                    <div class="flex-1 overflow-y-auto max-h-[60vh] grid grid-cols-3 gap-2 content-start">
                        <button
                            v-for="photo in palettePhotos"
                            :key="photo.id"
                            class="aspect-square rounded border border-brand-border overflow-hidden hover:ring-2 hover:ring-brand-primary"
                            @click="addPhotoToCanvas(photo)"
                        >
                            <img :src="photo.url" class="w-full h-full object-cover" :alt="photo.type?.name || '写真'">
                        </button>
                        <p v-if="palettePhotos.length === 0" class="col-span-3 text-sm text-brand-text-muted py-4 text-center">
                            配置できる顧客写真がありません。<br>先に「顧客写真」から写真を追加してください。
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <UiButton variant="ghost" size="sm" @click="addTextToCanvas()">
                            <TypeIcon :size="14" /> テキストを追加
                        </UiButton>
                        <UiButton variant="danger" size="sm" :disabled="!hasSelection" @click="removeSelected">
                            <Trash2 :size="14" /> 選択中を削除
                        </UiButton>
                        <UiButton variant="ghost" size="sm" @click="resetPlacements">
                            <RotateCcw :size="14" /> リセット
                        </UiButton>
                    </div>
                </div>
            </div>

            <p v-if="errorMessage" class="text-sm text-red-600 mt-3">{{ errorMessage }}</p>

            <div class="flex justify-end gap-3 mt-4">
                <UiButton variant="ghost" :disabled="saving" @click="emit('close')">キャンセル</UiButton>
                <UiButton variant="primary" :loading="saving" @click="save">
                    <Save :size="16" /> 保存する
                </UiButton>
            </div>
        </div>
    </Modal>
</template>
