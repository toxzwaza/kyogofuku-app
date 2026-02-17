<template>
    <Head title="イベント画像管理" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    イベント画像管理 - {{ event.title }}
                </h2>
                <div class="flex space-x-4">
                    <Link
                        :href="route('admin.events.images.create', event.id)"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700"
                    >
                        画像追加
                    </Link>
                    <ActionButton
                        variant="back"
                        label="イベント詳細へ戻る"
                        :href="route('admin.events.show', event.id)"
                    />
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- 操作ナビゲーション -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <EventNavigation :event="event" :show-url-button="false" />
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-50 p-4">
                            <p class="text-sm font-medium text-green-800">{{ $page.props.flash.success }}</p>
                        </div>
                        <div v-if="$page.props.flash?.error" class="mb-4 rounded-md bg-red-50 p-4">
                            <p class="text-sm font-medium text-red-800">{{ $page.props.flash.error }}</p>
                        </div>
                        <div v-if="images && images.length > 0" class="space-y-4">
                            <div class="mb-4 flex flex-wrap items-center gap-3">
                                <p class="text-sm text-gray-600">ハンドル（≡）をドラッグして並び替え／行の上端で放すと挿入、行の中央付近で放すと入れ替え。行クリックで複数選択できます</p>
                                <template v-if="selectedIds.size > 0">
                                    <span class="text-sm font-medium text-indigo-600">{{ selectedIds.size }}件選択中</span>
                                    <button
                                        type="button"
                                        @click="bulkDeleteSelected"
                                        :disabled="isBulkDeleting"
                                        class="px-3 py-1.5 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ isBulkDeleting ? '削除中...' : '選択した画像を削除' }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="clearSelection"
                                        class="text-sm text-gray-600 hover:text-gray-800 underline"
                                    >
                                        選択解除
                                    </button>
                                </template>
                            </div>
                            
                            <!-- 最初の画像の前にスライドショーを設定 -->
                            <SlideshowPositionManager
                                :position="0"
                                :slideshows="slideshows"
                                :slideshow-list="getSlideshowListForPosition(0)"
                                @add="addSlideshowToPosition(0, $event)"
                                @remove="removeSlideshowFromPosition(0, $event)"
                                @reorder="reorderSlideshowsAtPosition(0, $event)"
                                label="最初の画像の前にスライドショーを表示"
                            />
                            <CtaButtonPositionToggle
                                :position="0"
                                label="最初の画像の前にCTAボタンを表示"
                                :model-value="hasCtaAtPosition(0)"
                                @update:model-value="setCtaAtPosition(0, $event)"
                                class="mb-4"
                            />

                            <div
                                v-for="(image, index) in sortedImages"
                                :key="image.id"
                                class="relative"
                            >
                                <!-- 挿入ドロップインジケータ（行と行の間） -->
                                <div
                                    v-if="dropMode === 'insert' && dropTargetIndex === index"
                                    class="h-1 bg-indigo-500 rounded mb-2"
                                    aria-hidden="true"
                                />
                                <!-- 画像行 -->
                                <div
                                    class="flex items-center space-x-3 p-4 border rounded-lg mb-4 transition-colors cursor-pointer select-none"
                                    :class="[
                                        dropMode === 'swap' && dropTargetIndex === index ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-300 ring-offset-1' : 'border-gray-200 hover:bg-gray-50',
                                        selectedIds.has(image.id) && !(dropMode === 'swap' && dropTargetIndex === index) ? 'bg-sky-100 border-sky-400 ring-2 ring-sky-300 ring-offset-1' : ''
                                    ]"
                                    :data-drop-index="index"
                                    @dragover.prevent="handleDragOver(index, $event)"
                                    @drop="handleDrop(index, $event)"
                                    @dragleave="handleDragLeave($event)"
                                    @click="toggleSelection(image.id)"
                                >
                                    <!-- ドラッグハンドル（ここだけドラッグ開始） -->
                                    <div
                                        class="flex-shrink-0 cursor-grab active:cursor-grabbing p-1 rounded hover:bg-gray-200 flex items-center justify-center"
                                        draggable="true"
                                        @dragstart="handleDragStart(index, $event)"
                                        @dragend="handleDragEnd"
                                        @click.stop
                                        title="ドラッグして移動"
                                    >
                                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                        </svg>
                                    </div>
                                    <!-- 画像（クリックで拡大プレビュー） -->
                                    <button
                                        type="button"
                                        class="flex-shrink-0 rounded overflow-hidden border border-gray-200 hover:border-indigo-400 hover:ring-2 hover:ring-indigo-200 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition-shadow"
                                        @click.stop="openPreview(image)"
                                        title="クリックで拡大表示"
                                    >
                                        <img
                                            :src="getImageUrl(image)"
                                            :alt="image.alt || 'イベント画像'"
                                            class="w-32 h-32 object-cover block pointer-events-none"
                                        />
                                    </button>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-gray-600">順序: {{ index + 1 }}</p>
                                        <p class="text-sm text-gray-600">ファイル形式: {{ image.file_format || '-' }}</p>
                                        <p v-if="image.webp_path" class="text-sm text-green-600">WebP変換済み</p>
                                        <p v-if="image.alt" class="text-sm text-gray-600 truncate">Alt: {{ image.alt }}</p>
                                    </div>
                                    <div class="flex-shrink-0 flex flex-col gap-2 items-end" @click.stop>
                                        <button
                                            type="button"
                                            @click="openMarginModal(image)"
                                            class="text-indigo-600 hover:text-indigo-900 text-sm"
                                        >
                                            詳細
                                        </button>
                                        <button
                                            @click="deleteImage(image.id)"
                                            class="text-red-600 hover:text-red-900 text-sm"
                                        >
                                            削除
                                        </button>
                                    </div>
                                </div>

                                <!-- この画像の後にスライドショーを設定 -->
                                <SlideshowPositionManager
                                    v-if="index < sortedImages.length - 1 || index === sortedImages.length - 1"
                                    :position="index + 1"
                                    :slideshows="slideshows"
                                    :slideshow-list="getSlideshowListForPosition(index + 1)"
                                    @add="addSlideshowToPosition(index + 1, $event)"
                                    @remove="removeSlideshowFromPosition(index + 1, $event)"
                                    @reorder="reorderSlideshowsAtPosition(index + 1, $event)"
                                    label="この画像の後にスライドショーを表示"
                                    class="mb-4"
                                />
                                <CtaButtonPositionToggle
                                    v-if="index < sortedImages.length - 1 || index === sortedImages.length - 1"
                                    :position="index + 1"
                                    :label="`${index + 1}枚目の画像の後にCTAボタンを表示`"
                                    :model-value="hasCtaAtPosition(index + 1)"
                                    @update:model-value="setCtaAtPosition(index + 1, $event)"
                                    class="mb-4"
                                />
                            </div>

                            <div class="pt-4 flex flex-wrap gap-4 items-center">
                                <button
                                    @click="saveSlideshowPositions"
                                    :disabled="isSavingSlideshows"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:bg-gray-400"
                                >
                                    {{ isSavingSlideshows ? '保存中...' : 'スライドショー位置を保存' }}
                                </button>
                                <button
                                    @click="saveCtaButtonPositions"
                                    :disabled="isSavingCtaButtons"
                                    class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 disabled:bg-gray-400"
                                >
                                    {{ isSavingCtaButtons ? '保存中...' : 'CTAボタン位置を保存' }}
                                </button>
                                <Link
                                    :href="route('admin.slideshows.index')"
                                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
                                >
                                    スライドショー管理
                                </Link>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            画像が登録されていません
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 画像拡大プレビューモーダル -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="previewImage"
                    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/70"
                    @click.self="closePreview"
                    @keydown.esc="closePreview"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="previewImage?.alt || '画像プレビュー'"
                >
                    <div class="relative max-w-[90vw] max-h-[90vh] flex items-center justify-center">
                        <img
                            :src="getImageUrl(previewImage)"
                            :alt="previewImage.alt || 'イベント画像'"
                            class="max-w-full max-h-[90vh] object-contain rounded shadow-2xl"
                            @click.stop
                        />
                        <button
                            type="button"
                            class="absolute -top-10 right-0 p-2 text-white hover:text-gray-300 rounded-full hover:bg-white/10 transition-colors"
                            @click="closePreview"
                            aria-label="閉じる"
                        >
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- 画像詳細（マージン）モーダル -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="marginModalImage"
                    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50"
                    @click.self="closeMarginModal"
                    role="dialog"
                    aria-modal="true"
                    aria-label="画像詳細・マージン設定"
                >
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6" @click.stop>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">画像詳細 - 余白（マージン）</h3>
                        <p class="text-sm text-gray-500 mb-4">公開ページでこの画像の上・下に余白（px）を付けます。未入力の場合は余白なしです。</p>
                        <form @submit.prevent="submitMargin" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">上マージン（px）</label>
                                <input
                                    v-model.number="marginForm.margin_top_px"
                                    type="number"
                                    min="0"
                                    max="500"
                                    class="w-full rounded-md border-gray-300 shadow-sm"
                                    placeholder="未設定"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">下マージン（px）</label>
                                <input
                                    v-model.number="marginForm.margin_bottom_px"
                                    type="number"
                                    min="0"
                                    max="500"
                                    class="w-full rounded-md border-gray-300 shadow-sm"
                                    placeholder="未設定"
                                />
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    @click="closeMarginModal"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                >
                                    キャンセル
                                </button>
                                <button
                                    type="submit"
                                    :disabled="isSavingMargin"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ isSavingMargin ? '保存中...' : '保存' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import ActionButton from '@/Components/ActionButton.vue';
import EventNavigation from '@/Components/EventNavigation.vue';
import SlideshowPositionManager from './Components/SlideshowPositionManager.vue';
import CtaButtonPositionToggle from './Components/CtaButtonPositionToggle.vue';

const props = defineProps({
    event: Object,
    images: Array,
    slideshows: Array,
    slideshowPositions: Object,
    ctaButtonPositions: {
        type: Array,
        default: () => [],
    },
});

const sortedImages = ref([...(props.images || [])]);
const draggedIndices = ref([]); // ドラッグ中のインデックス（単体 or 複数選択）
const dropTargetIndex = ref(null);
const dropMode = ref('insert'); // 'insert' = 行の間へ挿入, 'swap' = 行の上で入れ替え
const INSERT_ZONE_RATIO = 0.25; // 行の上からこの割合までを「挿入」ゾーンとする
const isSavingSlideshows = ref(false);
const isSavingCtaButtons = ref(false);
const slideshowPositionsLocal = ref({});
const ctaButtonPositionsLocal = ref([...(props.ctaButtonPositions || [])].map(Number).filter(n => !Number.isNaN(n)));
const selectedIds = ref(new Set());
const previewImage = ref(null);
const isBulkDeleting = ref(false);
const marginModalImage = ref(null);
const marginForm = ref({ margin_top_px: null, margin_bottom_px: null });
const isSavingMargin = ref(false);

// ドラッグ時の自動スクロール用
const AUTO_SCROLL_ZONE = 80;
const SCROLL_STEP = 14;
const lastScrollDirection = ref(null);
let scrollIntervalId = null;

// props.imagesが更新されたらsortedImagesを同期
watch(() => props.images, (newVal) => {
    if (newVal && Array.isArray(newVal) && newVal.length > 0) {
        sortedImages.value = [...newVal];
    }
}, { deep: true });

// propsからslideshowPositionsを初期化（複数スライドショー対応）
if (props.slideshowPositions) {
    Object.keys(props.slideshowPositions).forEach(position => {
        const pos = parseInt(position);
        if (Array.isArray(props.slideshowPositions[position])) {
            slideshowPositionsLocal.value[pos] = [...props.slideshowPositions[position]];
        } else {
            // 旧形式（単一スライドショー）の場合は配列に変換
            slideshowPositionsLocal.value[pos] = props.slideshowPositions[position] 
                ? [{ slideshow_id: props.slideshowPositions[position], sort_order: 0 }]
                : [];
        }
    });
}

// 最初の画像の前の位置を初期化（まだ設定されていない場合）
if (!slideshowPositionsLocal.value.hasOwnProperty(0)) {
    slideshowPositionsLocal.value[0] = [];
}

// 各画像の後にスライドショー位置を初期化
sortedImages.value.forEach((image, index) => {
    if (!slideshowPositionsLocal.value.hasOwnProperty(index + 1)) {
        slideshowPositionsLocal.value[index + 1] = [];
    }
});

// 指定位置のスライドショーリストを取得
const getSlideshowListForPosition = (position) => {
    return slideshowPositionsLocal.value[position] || [];
};

// 位置にスライドショーを追加
const addSlideshowToPosition = (position, slideshowId) => {
    if (!slideshowPositionsLocal.value[position]) {
        slideshowPositionsLocal.value[position] = [];
    }
    const maxSortOrder = slideshowPositionsLocal.value[position].length > 0
        ? Math.max(...slideshowPositionsLocal.value[position].map(s => s.sort_order))
        : -1;
    slideshowPositionsLocal.value[position].push({
        slideshow_id: slideshowId,
        sort_order: maxSortOrder + 1,
    });
};

// 位置からスライドショーを削除
const removeSlideshowFromPosition = (position, index) => {
    if (slideshowPositionsLocal.value[position]) {
        slideshowPositionsLocal.value[position].splice(index, 1);
        // sort_orderを再計算
        slideshowPositionsLocal.value[position].forEach((item, idx) => {
            item.sort_order = idx;
        });
    }
};

// 位置のスライドショーを並び替え
const reorderSlideshowsAtPosition = (position, newOrder) => {
    if (slideshowPositionsLocal.value[position]) {
        slideshowPositionsLocal.value[position] = newOrder.map((item, idx) => ({
            ...item,
            sort_order: idx,
        }));
    }
};

// CTAボタン表示位置
const hasCtaAtPosition = (position) => ctaButtonPositionsLocal.value.includes(position);
const setCtaAtPosition = (position, checked) => {
    const set = new Set(ctaButtonPositionsLocal.value);
    if (checked) set.add(position);
    else set.delete(position);
    ctaButtonPositionsLocal.value = Array.from(set).sort((a, b) => a - b);
};
const saveCtaButtonPositions = () => {
    isSavingCtaButtons.value = true;
    router.post(route('admin.events.cta-button-positions.update', props.event.id), {
        positions: ctaButtonPositionsLocal.value,
    }, {
        onFinish: () => { isSavingCtaButtons.value = false; },
    });
};

const getImageUrl = (image) => {
    if (image.url) return image.url;
    const path = image.path ?? image;
    if (typeof path === 'string' && path.startsWith('http')) return path;
    return `/storage/${path}`;
};

// 複数選択
const toggleSelection = (id) => {
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
};

const clearSelection = () => {
    selectedIds.value = new Set();
};

// プレビューモーダル
const openPreview = (image) => {
    previewImage.value = image;
};

const closePreview = () => {
    previewImage.value = null;
};

// マージン（詳細）モーダル
const openMarginModal = (image) => {
    marginModalImage.value = image;
    marginForm.value = {
        margin_top_px: image.margin_top_px ?? '',
        margin_bottom_px: image.margin_bottom_px ?? '',
    };
};
const closeMarginModal = () => {
    marginModalImage.value = null;
};
const submitMargin = () => {
    if (!marginModalImage.value || !props.event?.id) return;
    const payload = {
        margin_top_px: marginForm.value.margin_top_px === '' || marginForm.value.margin_top_px == null ? null : Number(marginForm.value.margin_top_px),
        margin_bottom_px: marginForm.value.margin_bottom_px === '' || marginForm.value.margin_bottom_px == null ? null : Number(marginForm.value.margin_bottom_px),
    };
    isSavingMargin.value = true;
    router.patch(route('admin.events.images.margin', [props.event.id, marginModalImage.value.id]), payload, {
        preserveScroll: true,
        onSuccess: () => { closeMarginModal(); },
        onFinish: () => { isSavingMargin.value = false; },
    });
};

const handlePreviewEsc = (e) => {
    if (e.key === 'Escape') closePreview();
};
watch(previewImage, (val) => {
    if (val) {
        document.addEventListener('keydown', handlePreviewEsc);
        document.body.style.overflow = 'hidden';
    } else {
        document.removeEventListener('keydown', handlePreviewEsc);
        document.body.style.overflow = '';
    }
});
onUnmounted(() => {
    document.removeEventListener('keydown', handlePreviewEsc);
    document.body.style.overflow = '';
});

// ドラッグ時の自動スクロール開始
const startAutoScroll = () => {
    if (scrollIntervalId) return;
    scrollIntervalId = setInterval(() => {
        const d = lastScrollDirection.value;
        if (d === 'up') window.scrollBy(0, -SCROLL_STEP);
        else if (d === 'down') window.scrollBy(0, SCROLL_STEP);
    }, 16);
};

const stopAutoScroll = () => {
    if (scrollIntervalId) {
        clearInterval(scrollIntervalId);
        scrollIntervalId = null;
    }
    lastScrollDirection.value = null;
};

const handleDragStart = (index, event) => {
    const id = sortedImages.value[index]?.id;
    if (selectedIds.value.has(id) && selectedIds.value.size > 1) {
        const indices = sortedImages.value
            .map((img, i) => (selectedIds.value.has(img.id) ? i : -1))
            .filter(i => i >= 0)
            .sort((a, b) => a - b);
        draggedIndices.value = indices;
    } else {
        draggedIndices.value = [index];
    }
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', JSON.stringify({ indices: draggedIndices.value }));
    event.dataTransfer.dropEffect = 'move';
    startAutoScroll();
};

const handleDragOver = (index, event) => {
    event.dataTransfer.dropEffect = 'move';
    dropTargetIndex.value = index;
    const rect = event.currentTarget.getBoundingClientRect();
    const relativeY = event.clientY - rect.top;
    if (relativeY < rect.height * INSERT_ZONE_RATIO) {
        dropMode.value = 'insert';
    } else {
        dropMode.value = 'swap';
    }
    const y = event.clientY;
    if (y < AUTO_SCROLL_ZONE) lastScrollDirection.value = 'up';
    else if (y > window.innerHeight - AUTO_SCROLL_ZONE) lastScrollDirection.value = 'down';
    else lastScrollDirection.value = null;
};

const handleDragLeave = (event) => {
    // 子要素に移っただけのときは何もしない（relatedTarget が行の内側なら flicker 防止）
    if (event.relatedTarget && event.currentTarget.contains(event.relatedTarget)) {
        return;
    }
    dropTargetIndex.value = null;
    dropMode.value = 'insert';
};

const handleDrop = (dropIndex, event) => {
    event.preventDefault();
    const mode = dropMode.value;
    dropTargetIndex.value = null;
    dropMode.value = 'insert';

    const indices = [...draggedIndices.value].sort((a, b) => a - b);
    if (!indices.length) return;

    if (mode === 'insert') {
        // 行と行の間へ挿入
        if (indices.includes(dropIndex) && indices.length === 1) return;
        const minI = Math.min(...indices);
        const maxI = Math.max(...indices);
        if (indices.length > 1 && dropIndex >= minI && dropIndex <= maxI) return;

        const items = indices.map(i => sortedImages.value[i]);
        const remaining = sortedImages.value.filter((_, i) => !indices.includes(i));
        const insertAt = Math.min(
            dropIndex - indices.filter(i => i < dropIndex).length,
            remaining.length
        );
        const before = remaining.slice(0, insertAt);
        const after = remaining.slice(insertAt);
        sortedImages.value = [...before, ...items, ...after];
    } else {
        // 行の上で放した → 入れ替え
        const j = dropIndex;
        if (indices.includes(j) && indices.length === 1) return;
        const minI = Math.min(...indices);
        const maxI = Math.max(...indices);
        if (indices.length > 1 && j >= minI && j <= maxI) return;

        if (indices.length === 1) {
            const i = indices[0];
            const arr = [...sortedImages.value];
            [arr[i], arr[j]] = [arr[j], arr[i]];
            sortedImages.value = arr;
        } else {
            const arr = sortedImages.value.slice();
            const block = indices.map((i) => arr[i]);
            const single = arr[j];
            indices.forEach((i) => (arr[i] = undefined));
            arr[j] = undefined;
            const compact = arr.filter((x) => x !== undefined);
            const posBlock = j - indices.filter((i) => i < j).length;
            const posSingle = minI - (j < minI ? 1 : 0);
            if (posSingle <= posBlock) {
                compact.splice(posSingle, 0, single);
                compact.splice(posBlock + 1, 0, ...block);
            } else {
                compact.splice(posBlock, 0, ...block);
                compact.splice(posSingle + block.length, 0, single);
            }
            sortedImages.value = compact;
        }
    }

    sortedImages.value.forEach((image, i) => {
        image.sort_order = i + 1;
    });
    draggedIndices.value = [];
    // 移動時に自動保存
    saveSortOrder();
};

const handleDragEnd = () => {
    stopAutoScroll();
    dropTargetIndex.value = null;
    dropMode.value = 'insert';
    draggedIndices.value = [];
};

const saveSortOrder = () => {
    const imageIds = sortedImages.value.map(img => img.id);
    router.post(route('admin.events.images.sort', props.event.id), {
        image_ids: imageIds,
    }, {
        preserveScroll: true,
    });
};

const deleteImage = (id) => {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('admin.images.destroy', id), {
            onSuccess: () => {
                sortedImages.value = sortedImages.value.filter(img => img.id !== id);
            },
        });
    }
};

// 選択した画像をまとめて削除
const bulkDeleteSelected = () => {
    if (selectedIds.value.size === 0) return;
    const count = selectedIds.value.size;
    if (!confirm(`選択した${count}件の画像を削除しますか？この操作は取り消せません。`)) return;
    isBulkDeleting.value = true;
    router.post(route('admin.events.images.bulk-destroy', props.event.id), {
        image_ids: Array.from(selectedIds.value),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = new Set();
        },
        onFinish: () => {
            isBulkDeleting.value = false;
        },
    });
};

const saveSlideshowPositions = () => {
    isSavingSlideshows.value = true;
    
    // positionとslideshowsの配列を作成（複数スライドショー対応）
    const positions = [];
    Object.keys(slideshowPositionsLocal.value).forEach(position => {
        const slideshowList = slideshowPositionsLocal.value[position];
        if (Array.isArray(slideshowList) && slideshowList.length > 0) {
            positions.push({
                position: parseInt(position),
                slideshows: slideshowList.filter(item => item.slideshow_id !== null),
            });
        }
    });

    router.post(route('admin.events.slideshow-positions.update', props.event.id), {
        positions: positions,
    }, {
        onFinish: () => {
            isSavingSlideshows.value = false;
        },
    });
};
</script>

