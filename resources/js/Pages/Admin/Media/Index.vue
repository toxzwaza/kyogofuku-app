<template>
    <Head title="メディアライブラリ" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    メディアライブラリ
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 p-4">
                    <p class="text-sm font-medium text-green-800">{{ $page.props.flash.success }}</p>
                </div>
                <div v-if="$page.props.flash?.info" class="rounded-md bg-blue-50 p-4">
                    <p class="text-sm font-medium text-blue-800">{{ $page.props.flash.info }}</p>
                </div>
                <div v-if="$page.props.flash?.error" class="rounded-md bg-red-50 p-4">
                    <p class="text-sm font-medium text-red-800">{{ $page.props.flash.error }}</p>
                </div>

                <!-- 既存画像の取り込み -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold">既存画像の取り込み</h3>
                            <p class="text-sm text-gray-500 mt-1">
                                イベント画像・スライドショー画像として既にアップロード済みの画像を、メディアライブラリに一括登録します。
                                ファイルのコピーは行わず、既存パスをそのまま参照します。
                            </p>
                        </div>
                        <button
                            @click="importExisting"
                            :disabled="isImporting"
                            class="flex-shrink-0 px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 disabled:bg-gray-400"
                        >
                            {{ isImporting ? '取り込み中...' : '既存画像を取り込む' }}
                        </button>
                    </div>
                </div>

                <!-- アップロードセクション -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">画像をアップロード</h3>
                        <form @submit.prevent="submitUpload" enctype="multipart/form-data">
                            <div class="space-y-4">
                                <div>
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        multiple
                                        accept="image/*"
                                        @change="handleFileChange"
                                        class="w-full rounded-md border-gray-300 shadow-sm"
                                    />
                                    <p class="mt-1 text-sm text-gray-500">複数の画像を選択できます（JPEG, PNG, GIF, WebP、最大10MB）</p>
                                </div>

                                <!-- プレビュー -->
                                <div v-if="previewImages.length > 0" class="grid grid-cols-3 md:grid-cols-6 gap-3">
                                    <div v-for="(preview, index) in previewImages" :key="index" class="relative">
                                        <img :src="preview" alt="プレビュー" class="w-full h-24 object-cover rounded" />
                                        <button
                                            type="button"
                                            @click="removePreview(index)"
                                            class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                </div>

                                <!-- タグ入力 -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">タグ（カンマ区切り）</label>
                                    <input
                                        v-model="tagInput"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm"
                                        placeholder="例: 振袖, 会場, バナー"
                                    />
                                </div>

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        :disabled="uploadForm.processing || selectedFiles.length === 0"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                    >
                                        {{ uploadForm.processing ? 'アップロード中...' : 'アップロード' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- フィルタ・検索 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex flex-wrap gap-4 items-end">
                            <div class="flex-1 min-w-[200px]">
                                <label class="block text-sm font-medium text-gray-700 mb-1">ファイル名検索</label>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 shadow-sm"
                                    placeholder="ファイル名で検索..."
                                    @keyup.enter="applyFilters"
                                />
                            </div>
                            <div class="min-w-[150px]">
                                <label class="block text-sm font-medium text-gray-700 mb-1">タグ</label>
                                <select
                                    v-model="selectedTag"
                                    class="w-full rounded-md border-gray-300 shadow-sm"
                                    @change="applyFilters"
                                >
                                    <option value="">すべて</option>
                                    <option v-for="tag in allTags" :key="tag" :value="tag">{{ tag }}</option>
                                </select>
                            </div>
                            <button
                                @click="applyFilters"
                                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
                            >
                                検索
                            </button>
                            <button
                                v-if="searchQuery || selectedTag"
                                @click="clearFilters"
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50"
                            >
                                クリア
                            </button>
                        </div>
                    </div>
                </div>

                <!-- メディアグリッド -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="mediaFiles.data && mediaFiles.data.length > 0">
                            <p class="text-sm text-gray-600 mb-4">{{ mediaFiles.total }}件のメディア</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                                <div
                                    v-for="media in mediaFiles.data"
                                    :key="media.id"
                                    class="group relative border rounded-lg overflow-hidden hover:ring-2 hover:ring-indigo-400 transition-shadow cursor-pointer"
                                    @click="openDetail(media)"
                                >
                                    <img
                                        :src="media.url"
                                        :alt="media.alt || media.original_filename"
                                        class="w-full h-32 object-cover"
                                        loading="lazy"
                                    />
                                    <div class="p-2">
                                        <p class="text-xs text-gray-700 truncate" :title="media.original_filename">
                                            {{ media.original_filename }}
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            {{ formatFileSize(media.file_size) }}
                                            <span v-if="media.usage_count > 0" class="text-indigo-600">
                                                | {{ media.usage_count }}箇所で使用
                                            </span>
                                        </p>
                                        <div v-if="media.tags && media.tags.length > 0" class="mt-1 flex flex-wrap gap-1">
                                            <span
                                                v-for="tag in media.tags.slice(0, 2)"
                                                :key="tag"
                                                class="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded"
                                            >
                                                {{ tag }}
                                            </span>
                                            <span v-if="media.tags.length > 2" class="text-[10px] text-gray-400">
                                                +{{ media.tags.length - 2 }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ページネーション -->
                            <div v-if="mediaFiles.last_page > 1" class="mt-6 flex justify-center gap-2">
                                <Link
                                    v-for="link in mediaFiles.links"
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    class="px-3 py-1 border rounded text-sm"
                                    :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-700 hover:bg-gray-50'"
                                    v-html="link.label"
                                    :preserve-scroll="true"
                                />
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            メディアが登録されていません
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 詳細モーダル -->
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
                    v-if="detailMedia"
                    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50"
                    @click.self="closeDetail"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto" @click.stop>
                        <div class="p-6 space-y-4">
                            <div class="flex justify-between items-start">
                                <h3 class="text-lg font-semibold text-gray-800">メディア詳細</h3>
                                <button @click="closeDetail" class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <img
                                :src="detailMedia.url"
                                :alt="detailMedia.alt || ''"
                                class="w-full max-h-[40vh] object-contain rounded border"
                            />

                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500">ファイル名:</span>
                                    <p class="text-gray-800">{{ detailMedia.original_filename }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">サイズ:</span>
                                    <p class="text-gray-800">{{ formatFileSize(detailMedia.file_size) }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">解像度:</span>
                                    <p class="text-gray-800">{{ detailMedia.width }} x {{ detailMedia.height }}px</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">使用箇所:</span>
                                    <p class="text-gray-800">{{ detailMedia.usage_count }}箇所</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">登録日:</span>
                                    <p class="text-gray-800">{{ detailMedia.created_at }}</p>
                                </div>
                            </div>

                            <!-- Alt編集 -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alt属性</label>
                                <input
                                    v-model="detailForm.alt"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 shadow-sm"
                                />
                            </div>

                            <!-- タグ編集 -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">タグ（カンマ区切り）</label>
                                <input
                                    v-model="detailTagInput"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 shadow-sm"
                                />
                            </div>

                            <div class="flex justify-between pt-2">
                                <button
                                    @click="deleteMedia"
                                    :disabled="detailMedia.usage_count > 0"
                                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :title="detailMedia.usage_count > 0 ? '使用中のため削除できません' : ''"
                                >
                                    削除
                                </button>
                                <div class="flex gap-2">
                                    <button
                                        @click="closeDetail"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                    >
                                        キャンセル
                                    </button>
                                    <button
                                        @click="saveDetail"
                                        :disabled="isSavingDetail"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        {{ isSavingDetail ? '保存中...' : '保存' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mediaFiles: Object,
    allTags: Array,
    filters: Object,
});

const searchQuery = ref(props.filters?.search || '');
const selectedTag = ref(props.filters?.tag || '');
const tagInput = ref('');
const fileInput = ref(null);
const selectedFiles = ref([]);
const previewImages = ref([]);
const detailMedia = ref(null);
const detailForm = ref({ alt: '', tags: [] });
const detailTagInput = ref('');
const isSavingDetail = ref(false);
const isImporting = ref(false);

const uploadForm = useForm({
    images: [],
    tags: [],
});

const formatFileSize = (bytes) => {
    if (!bytes) return '-';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const handleFileChange = (event) => {
    const files = Array.from(event.target.files);
    selectedFiles.value = files;
    previewImages.value = [];
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => previewImages.value.push(e.target.result);
        reader.readAsDataURL(file);
    });
    uploadForm.images = files;
};

const removePreview = (index) => {
    previewImages.value.splice(index, 1);
    selectedFiles.value.splice(index, 1);
    uploadForm.images = selectedFiles.value;
    const dt = new DataTransfer();
    selectedFiles.value.forEach(file => dt.items.add(file));
    fileInput.value.files = dt.files;
};

const submitUpload = () => {
    const tags = tagInput.value
        .split(',')
        .map(t => t.trim())
        .filter(t => t.length > 0);
    uploadForm.tags = tags;
    uploadForm.post(route('admin.media.store'), {
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset();
            selectedFiles.value = [];
            previewImages.value = [];
            tagInput.value = '';
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const applyFilters = () => {
    router.get(route('admin.media.index'), {
        search: searchQuery.value || undefined,
        tag: selectedTag.value || undefined,
    }, { preserveState: true });
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedTag.value = '';
    router.get(route('admin.media.index'));
};

const openDetail = (media) => {
    detailMedia.value = media;
    detailForm.value = { alt: media.alt || '' };
    detailTagInput.value = (media.tags || []).join(', ');
};

const closeDetail = () => {
    detailMedia.value = null;
};

const saveDetail = () => {
    if (!detailMedia.value) return;
    const tags = detailTagInput.value
        .split(',')
        .map(t => t.trim())
        .filter(t => t.length > 0);
    isSavingDetail.value = true;
    router.patch(route('admin.media.update', detailMedia.value.id), {
        alt: detailForm.value.alt,
        tags: tags,
    }, {
        preserveScroll: true,
        onSuccess: () => closeDetail(),
        onFinish: () => { isSavingDetail.value = false; },
    });
};

const importExisting = () => {
    if (!confirm('既存のイベント画像・スライドショー画像をメディアライブラリに取り込みますか？\n（ファイルのコピーは行いません）')) return;
    isImporting.value = true;
    router.post(route('admin.media.import-existing'), {}, {
        onFinish: () => { isImporting.value = false; },
    });
};

const deleteMedia = () => {
    if (!detailMedia.value) return;
    if (!confirm('このメディアを削除しますか？')) return;
    router.delete(route('admin.media.destroy', detailMedia.value.id), {
        onSuccess: () => closeDetail(),
    });
};
</script>
