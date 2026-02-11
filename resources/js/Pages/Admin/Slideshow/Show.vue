<template>
    <Head title="スライドショー詳細" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">スライドショー詳細 - {{ slideshow.name }}</h2>
                <div class="flex space-x-4">
                    <Link
                        :href="route('admin.slideshows.index')"
                        class="text-indigo-600 hover:text-indigo-900"
                    >
                        ← スライドショー一覧に戻る
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- スライドショー名編集 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="updateName">
                            <div class="flex items-end space-x-4">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">スライドショー名</label>
                                    <input
                                        v-model="nameForm.name"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    :disabled="nameForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                >
                                    {{ nameForm.processing ? '更新中...' : '名前を更新' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- スライドショー設定 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">スライドショー設定</h3>
                        <form @submit.prevent="updateSettings">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">表示タイプ</label>
                                    <select
                                        v-model="settingsForm.type"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="fade">フェード</option>
                                        <option value="slide">スライド</option>
                                        <option value="cube">キューブ</option>
                                        <option value="coverflow">カバーフロー</option>
                                    </select>
                                    <p class="mt-1 text-sm text-gray-500">スライドショーの切り替えエフェクトを選択</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">自動再生</label>
                                    <label class="flex items-center">
                                        <input
                                            v-model="settingsForm.autoplay_enabled"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">自動でスライドを切り替える</span>
                                    </label>
                                </div>

                                <div v-if="settingsForm.autoplay_enabled">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">自動遷移時間（ミリ秒）</label>
                                    <input
                                        v-model.number="settingsForm.autoplay_interval"
                                        type="number"
                                        min="1000"
                                        max="60000"
                                        step="1000"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p class="mt-1 text-sm text-gray-500">1000〜60000ミリ秒（1秒〜60秒）の範囲で設定</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">全画面表示</label>
                                    <label class="flex items-center">
                                        <input
                                            v-model="settingsForm.fullscreen"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">全画面でスライドショーを表示する</span>
                                    </label>
                                </div>

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        :disabled="settingsForm.processing"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                    >
                                        {{ settingsForm.processing ? '更新中...' : '設定を更新' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 画像追加（スライドショー設定の下に配置） -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">画像を追加</h3>
                        <form @submit.prevent="submitImage" enctype="multipart/form-data">
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
                                    <div v-if="imageForm.errors.images" class="mt-1 text-sm text-red-600">{{ imageForm.errors.images }}</div>

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
                                        v-model="imageForm.alt"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="画像の説明（オプション）"
                                    />
                                    <div v-if="imageForm.errors.alt" class="mt-1 text-sm text-red-600">{{ imageForm.errors.alt }}</div>
                                </div>

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        :disabled="imageForm.processing || selectedFiles.length === 0"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                    >
                                        {{ imageForm.processing ? 'アップロード中...' : '画像を追加' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 画像一覧（複数選択・ドラッグで並び替え） -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-50 p-4">
                            <p class="text-sm font-medium text-green-800">{{ $page.props.flash.success }}</p>
                        </div>
                        <div v-if="$page.props.flash?.error" class="mb-4 rounded-md bg-red-50 p-4">
                            <p class="text-sm font-medium text-red-800">{{ $page.props.flash.error }}</p>
                        </div>
                        <div v-if="sortedImages.length > 0" class="space-y-4">
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
                                            :src="getImageUrl(image.path)"
                                            :alt="image.alt || 'スライドショー画像'"
                                            class="w-32 h-32 object-cover block pointer-events-none"
                                        />
                                    </button>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-gray-600">順序: {{ index + 1 }}</p>
                                        <p v-if="image.alt" class="text-sm text-gray-600 truncate">Alt: {{ image.alt }}</p>
                                    </div>
                                    <div class="flex-shrink-0 flex flex-col gap-2 items-end" @click.stop>
                                        <button
                                            @click="deleteImage(image.id)"
                                            class="text-red-600 hover:text-red-900 text-sm"
                                        >
                                            削除
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4">
                                <button
                                    @click="saveSortOrder"
                                    :disabled="isSaving"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                >
                                    {{ isSaving ? '保存中...' : 'ソート順を保存' }}
                                </button>
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
                            :src="getImageUrl(previewImage.path)"
                            :alt="previewImage.alt || 'スライドショー画像'"
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
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    slideshow: Object,
});

const sortedImages = ref([...(props.slideshow?.images || [])]);
const draggedIndices = ref([]);
const dropTargetIndex = ref(null);
const dropMode = ref('insert');
const INSERT_ZONE_RATIO = 0.25;
const isSaving = ref(false);
const fileInput = ref(null);
const selectedFiles = ref([]);
const previewImages = ref([]);
const selectedIds = ref(new Set());
const previewImage = ref(null);
const isBulkDeleting = ref(false);

const AUTO_SCROLL_ZONE = 80;
const SCROLL_STEP = 14;
const lastScrollDirection = ref(null);
let scrollIntervalId = null;

watch(() => props.slideshow?.images, (newVal) => {
    if (newVal && Array.isArray(newVal) && newVal.length >= 0) {
        sortedImages.value = [...newVal];
    }
}, { deep: true });

const nameForm = useForm({
    name: props.slideshow.name,
});

const settingsForm = useForm({
    name: props.slideshow.name,
    type: props.slideshow.type || 'fade',
    autoplay_enabled: props.slideshow.autoplay_enabled !== undefined ? props.slideshow.autoplay_enabled : true,
    autoplay_interval: props.slideshow.autoplay_interval || 5000,
    fullscreen: props.slideshow.fullscreen !== undefined ? props.slideshow.fullscreen : true,
});

const imageForm = useForm({
    images: [],
    alt: '',
});

const getImageUrl = (path) => {
    if (path.startsWith('http')) {
        return path;
    }
    return `/storage/${path}`;
};

const toggleSelection = (id) => {
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
};

const clearSelection = () => {
    selectedIds.value = new Set();
};

const openPreview = (image) => {
    previewImage.value = image;
};

const closePreview = () => {
    previewImage.value = null;
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
    saveSortOrder();
};

const handleDragEnd = () => {
    stopAutoScroll();
    dropTargetIndex.value = null;
    dropMode.value = 'insert';
    draggedIndices.value = [];
};

const saveSortOrder = () => {
    isSaving.value = true;
    const imageIds = sortedImages.value.map(img => img.id);
    router.post(route('admin.slideshows.images.sort', props.slideshow.id), {
        image_ids: imageIds,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false;
        },
    });
};

const deleteImage = (id) => {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('admin.slideshow-images.destroy', id), {
            onSuccess: () => {
                sortedImages.value = sortedImages.value.filter(img => img.id !== id);
            },
        });
    }
};

const bulkDeleteSelected = () => {
    if (selectedIds.value.size === 0) return;
    const count = selectedIds.value.size;
    if (!confirm(`選択した${count}件の画像を削除しますか？この操作は取り消せません。`)) return;
    isBulkDeleting.value = true;
    router.post(route('admin.slideshows.images.bulk-destroy', props.slideshow.id), {
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

    imageForm.images = files;
};

const removePreview = (index) => {
    previewImages.value.splice(index, 1);
    selectedFiles.value.splice(index, 1);
    imageForm.images = selectedFiles.value;

    const dt = new DataTransfer();
    selectedFiles.value.forEach(file => dt.items.add(file));
    fileInput.value.files = dt.files;
};

const updateName = () => {
    nameForm.put(route('admin.slideshows.update', props.slideshow.id), {
        onSuccess: () => {
            settingsForm.name = nameForm.name;
        },
    });
};

const updateSettings = () => {
    settingsForm.put(route('admin.slideshows.update', props.slideshow.id), {
        onSuccess: () => {
            nameForm.name = settingsForm.name;
        },
    });
};

const submitImage = () => {
    imageForm.post(route('admin.slideshows.images.store', props.slideshow.id), {
        forceFormData: true,
        onSuccess: () => {
            imageForm.reset();
            selectedFiles.value = [];
            previewImages.value = [];
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};
</script>
