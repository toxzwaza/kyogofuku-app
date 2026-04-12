<template>
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
                v-if="show"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50"
                @click.self="close"
                role="dialog"
                aria-modal="true"
                aria-label="メディアライブラリ"
            >
                <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] flex flex-col" @click.stop>
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between p-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">メディアライブラリ</h3>
                        <div class="flex items-center gap-3">
                            <span v-if="selectedIds.size > 0" class="text-sm text-indigo-600 font-medium">
                                {{ selectedIds.size }}件選択中
                            </span>
                            <button @click="close" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- タブ -->
                    <div class="flex border-b">
                        <button
                            @click="activeTab = 'library'"
                            class="px-6 py-3 text-sm font-medium border-b-2 transition-colors"
                            :class="activeTab === 'library' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        >
                            ライブラリから選択
                        </button>
                        <button
                            @click="activeTab = 'upload'"
                            class="px-6 py-3 text-sm font-medium border-b-2 transition-colors"
                            :class="activeTab === 'upload' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        >
                            新規アップロード
                        </button>
                    </div>

                    <!-- コンテンツ -->
                    <div class="flex-1 overflow-y-auto p-4">
                        <!-- ライブラリタブ -->
                        <div v-if="activeTab === 'library'" class="space-y-4">
                            <!-- 検索・フィルタ -->
                            <div class="flex flex-wrap gap-3">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    class="flex-1 min-w-[200px] rounded-md border-gray-300 shadow-sm text-sm"
                                    placeholder="ファイル名で検索..."
                                    @keyup.enter="fetchMedia"
                                />
                                <select
                                    v-model="selectedTag"
                                    class="rounded-md border-gray-300 shadow-sm text-sm"
                                    @change="fetchMedia"
                                >
                                    <option value="">すべてのタグ</option>
                                    <option v-for="tag in allTags" :key="tag" :value="tag">{{ tag }}</option>
                                </select>
                                <button
                                    @click="fetchMedia"
                                    class="px-3 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm"
                                >
                                    検索
                                </button>
                            </div>

                            <!-- グリッド -->
                            <div v-if="isLoading" class="text-center py-12 text-gray-500">
                                読み込み中...
                            </div>
                            <div v-else-if="mediaItems.length > 0" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
                                <div
                                    v-for="media in mediaItems"
                                    :key="media.id"
                                    class="relative border-2 rounded-lg overflow-hidden cursor-pointer transition-all"
                                    :class="selectedIds.has(media.id) ? 'border-indigo-500 ring-2 ring-indigo-300' : 'border-gray-200 hover:border-gray-400'"
                                    @click="toggleSelect(media)"
                                >
                                    <img
                                        :src="media.url"
                                        :alt="media.alt || media.original_filename"
                                        class="w-full h-28 object-cover"
                                        loading="lazy"
                                    />
                                    <!-- 選択チェックマーク -->
                                    <div
                                        v-if="selectedIds.has(media.id)"
                                        class="absolute top-1 right-1 w-6 h-6 bg-indigo-600 rounded-full flex items-center justify-center"
                                    >
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <div class="p-1.5">
                                        <p class="text-[11px] text-gray-600 truncate">{{ media.original_filename }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-12 text-gray-500">
                                メディアが見つかりません
                            </div>

                            <!-- ページネーション -->
                            <div v-if="totalPages > 1" class="flex justify-center gap-2 pt-2">
                                <button
                                    v-for="page in totalPages"
                                    :key="page"
                                    @click="goToPage(page)"
                                    class="px-3 py-1 border rounded text-sm"
                                    :class="currentPage === page ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-700 hover:bg-gray-50'"
                                >
                                    {{ page }}
                                </button>
                            </div>
                        </div>

                        <!-- アップロードタブ -->
                        <div v-if="activeTab === 'upload'" class="space-y-4">
                            <div>
                                <input
                                    ref="uploadFileInput"
                                    type="file"
                                    multiple
                                    accept="image/*"
                                    @change="handleUploadFileChange"
                                    class="w-full rounded-md border-gray-300 shadow-sm"
                                />
                                <p class="mt-1 text-sm text-gray-500">複数の画像を選択できます（JPEG, PNG, GIF, WebP、最大10MB）</p>
                            </div>

                            <!-- アップロードプレビュー -->
                            <div v-if="uploadPreviews.length > 0" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
                                <div v-for="(preview, index) in uploadPreviews" :key="index" class="relative">
                                    <img :src="preview" alt="プレビュー" class="w-full h-24 object-cover rounded" />
                                    <button
                                        type="button"
                                        @click="removeUploadPreview(index)"
                                        class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"
                                    >
                                        &times;
                                    </button>
                                </div>
                            </div>

                            <!-- タグ -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">タグ（カンマ区切り）</label>
                                <input
                                    v-model="uploadTagInput"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                                    placeholder="例: 振袖, 会場, バナー"
                                />
                            </div>

                            <div class="flex justify-end">
                                <button
                                    @click="submitUpload"
                                    :disabled="isUploading || uploadFiles.length === 0"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                >
                                    {{ isUploading ? 'アップロード中...' : 'アップロードして選択' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- フッター -->
                    <div class="flex justify-end gap-3 p-4 border-t bg-gray-50 rounded-b-lg">
                        <button
                            @click="close"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                        >
                            キャンセル
                        </button>
                        <button
                            @click="confirm"
                            :disabled="selectedIds.size === 0"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                        >
                            {{ selectedIds.size > 0 ? `${selectedIds.size}件を追加` : '選択してください' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'select']);

const activeTab = ref('library');
const searchQuery = ref('');
const selectedTag = ref('');
const allTags = ref([]);
const mediaItems = ref([]);
const selectedIds = ref(new Set());
const selectedMediaMap = ref(new Map());
const isLoading = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);

// アップロード
const uploadFileInput = ref(null);
const uploadFiles = ref([]);
const uploadPreviews = ref([]);
const uploadTagInput = ref('');
const isUploading = ref(false);

watch(() => props.show, (val) => {
    if (val) {
        selectedIds.value = new Set();
        selectedMediaMap.value = new Map();
        activeTab.value = 'library';
        fetchMedia();
    }
});

const fetchMedia = async (page = 1) => {
    isLoading.value = true;
    currentPage.value = page;
    try {
        const params = { page };
        if (searchQuery.value) params.search = searchQuery.value;
        if (selectedTag.value) params.tag = selectedTag.value;

        const res = await axios.get(route('admin.media.list-json'), { params });
        mediaItems.value = res.data.mediaFiles.data;
        totalPages.value = res.data.mediaFiles.last_page;
        allTags.value = res.data.allTags;
    } catch (e) {
        console.error('メディア取得エラー:', e);
    } finally {
        isLoading.value = false;
    }
};

const goToPage = (page) => {
    fetchMedia(page);
};

const toggleSelect = (media) => {
    const next = new Set(selectedIds.value);
    const nextMap = new Map(selectedMediaMap.value);
    if (next.has(media.id)) {
        next.delete(media.id);
        nextMap.delete(media.id);
    } else {
        next.add(media.id);
        nextMap.set(media.id, media);
    }
    selectedIds.value = next;
    selectedMediaMap.value = nextMap;
};

const handleUploadFileChange = (event) => {
    const files = Array.from(event.target.files);
    uploadFiles.value = files;
    uploadPreviews.value = [];
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => uploadPreviews.value.push(e.target.result);
        reader.readAsDataURL(file);
    });
};

const removeUploadPreview = (index) => {
    uploadPreviews.value.splice(index, 1);
    uploadFiles.value.splice(index, 1);
    if (uploadFileInput.value) {
        const dt = new DataTransfer();
        uploadFiles.value.forEach(file => dt.items.add(file));
        uploadFileInput.value.files = dt.files;
    }
};

const submitUpload = async () => {
    if (uploadFiles.value.length === 0) return;
    isUploading.value = true;

    const formData = new FormData();
    uploadFiles.value.forEach(file => formData.append('images[]', file));
    const tags = uploadTagInput.value.split(',').map(t => t.trim()).filter(t => t.length > 0);
    tags.forEach(tag => formData.append('tags[]', tag));

    try {
        const res = await axios.post(route('admin.media.store-json'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        // アップロードしたものを自動選択
        const uploaded = res.data.media || [];
        const next = new Set(selectedIds.value);
        const nextMap = new Map(selectedMediaMap.value);
        uploaded.forEach(m => {
            next.add(m.id);
            nextMap.set(m.id, m);
        });
        selectedIds.value = next;
        selectedMediaMap.value = nextMap;

        // リセット
        uploadFiles.value = [];
        uploadPreviews.value = [];
        uploadTagInput.value = '';
        if (uploadFileInput.value) uploadFileInput.value.value = '';

        // ライブラリタブに切り替え・再読み込み
        activeTab.value = 'library';
        fetchMedia();
    } catch (e) {
        console.error('アップロードエラー:', e);
        alert('アップロードに失敗しました。');
    } finally {
        isUploading.value = false;
    }
};

const close = () => {
    emit('close');
};

const confirm = () => {
    const selectedMedia = Array.from(selectedMediaMap.value.values());
    emit('select', selectedMedia);
    emit('close');
};
</script>
