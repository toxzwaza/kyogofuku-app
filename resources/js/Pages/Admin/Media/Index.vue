<template>
    <Head title="メディアライブラリ" />

    <AdminLayout :breadcrumb="[{ label: 'イベント・予約' }, { label: 'メディアライブラリ' }]">
        <UiPageHeader
            title="メディアライブラリ"
            description="画像を一元管理し、イベント／スライドショー等で再利用できます。"
        >
            <template #actions>
                <UiButton variant="subtle" :loading="isImporting" @click="importExisting">
                    <template #leading><RefreshCw :size="14" /></template>
                    既存画像を取り込む
                </UiButton>
            </template>
        </UiPageHeader>

        <!-- アップロード -->
        <UiCard variant="default" padding="md" class="mb-4">
            <template #header><h3 class="font-serif text-base flex items-center gap-2"><Upload :size="15" />画像をアップロード</h3></template>
            <form @submit.prevent="submitUpload" enctype="multipart/form-data" class="space-y-3">
                <UiFormField label="画像ファイル" hint="複数選択可。JPEG / PNG / GIF / WebP、最大10MB">
                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        accept="image/*"
                        class="block w-full text-sm text-brand-text file:mr-3 file:py-1.5 file:px-3 file:rounded-soft file:border-0 file:bg-brand-primary file:text-brand-on-primary file:text-xs file:font-medium hover:file:bg-brand-primary-hover"
                        @change="handleFileChange"
                    />
                </UiFormField>
                <div v-if="previewImages.length > 0" class="grid grid-cols-3 md:grid-cols-6 gap-3">
                    <div v-for="(preview, index) in previewImages" :key="index" class="relative">
                        <img :src="preview" alt="プレビュー" class="w-full h-24 object-cover rounded-soft border border-brand-border" />
                        <button
                            type="button"
                            class="absolute top-1 right-1 bg-brand-danger text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:opacity-90"
                            @click="removePreview(index)"
                        >×</button>
                    </div>
                </div>
                <UiFormField label="タグ" hint="カンマ区切り（例: 振袖, 会場, バナー）">
                    <UiInput v-model="tagInput" placeholder="例: 振袖, 会場, バナー" size="sm" />
                </UiFormField>
                <div class="flex justify-end">
                    <UiButton variant="primary" type="submit" :loading="uploadForm.processing" :disabled="selectedFiles.length === 0">
                        <template #leading><Upload :size="13" /></template>
                        アップロード
                    </UiButton>
                </div>
            </form>
        </UiCard>

        <!-- 検索 -->
        <UiCard variant="default" padding="md" class="mb-4">
            <div class="grid grid-cols-1 md:grid-cols-[1fr_200px_auto] gap-3 items-end">
                <UiFormField label="ファイル名検索">
                    <UiInput v-model="searchQuery" placeholder="ファイル名で検索..." size="sm" @keyup.enter="applyFilters" />
                </UiFormField>
                <UiFormField label="タグ">
                    <UiSelect
                        v-model="selectedTag"
                        :options="[{ value: '', label: 'すべて' }, ...allTags.map(t => ({ value: t, label: t }))]"
                        size="sm"
                        @update:modelValue="applyFilters"
                    />
                </UiFormField>
                <div class="flex gap-2">
                    <UiButton variant="primary" size="sm" @click="applyFilters">
                        <template #leading><Search :size="13" /></template>
                        検索
                    </UiButton>
                    <UiButton v-if="searchQuery || selectedTag" variant="ghost" size="sm" @click="clearFilters">クリア</UiButton>
                </div>
            </div>
        </UiCard>

        <!-- グリッド -->
        <UiCard variant="default" padding="md">
            <div v-if="mediaFiles.data && mediaFiles.data.length > 0">
                <p class="text-xs text-brand-text-muted mb-3">{{ mediaFiles.total }} 件のメディア</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <button
                        v-for="media in mediaFiles.data"
                        :key="media.id"
                        type="button"
                        class="group relative rounded-soft overflow-hidden border border-brand-border bg-brand-surface text-left hover:ring-2 hover:ring-brand-primary transition-all"
                        @click="openDetail(media)"
                    >
                        <img
                            :src="media.url"
                            :alt="media.alt || media.original_filename"
                            class="w-full h-32 object-cover"
                            loading="lazy"
                        />
                        <div class="p-2">
                            <p class="text-xs text-brand-text truncate" :title="media.original_filename">
                                {{ media.original_filename }}
                            </p>
                            <div class="text-[10px] text-brand-text-subtle flex items-center gap-1 mt-0.5">
                                <span>{{ formatFileSize(media.file_size) }}</span>
                                <span v-if="media.usage_count > 0" class="text-brand-primary">・{{ media.usage_count }}箇所</span>
                            </div>
                            <div v-if="media.tags && media.tags.length > 0" class="mt-1 flex flex-wrap gap-0.5">
                                <UiBadge v-for="tag in media.tags.slice(0, 2)" :key="tag" size="sm" variant="neutral">{{ tag }}</UiBadge>
                                <span v-if="media.tags.length > 2" class="text-[10px] text-brand-text-subtle">+{{ media.tags.length - 2 }}</span>
                            </div>
                        </div>
                    </button>
                </div>
                <div v-if="mediaFiles.last_page > 1" class="mt-4 flex justify-center gap-1">
                    <Link
                        v-for="link in mediaFiles.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-2.5 py-1 rounded-soft border text-xs"
                        :class="link.active
                            ? 'bg-brand-primary text-brand-on-primary border-brand-primary'
                            : 'bg-brand-surface text-brand-text hover:bg-brand-surface-2 border-brand-border'"
                        v-html="link.label"
                        preserve-scroll
                    />
                </div>
            </div>
            <div v-else class="py-10 text-center text-brand-text-muted">
                <ImageIcon :size="28" class="mx-auto mb-2 text-brand-text-subtle" />
                <div class="text-sm">メディアが登録されていません</div>
            </div>
        </UiCard>

        <!-- 詳細ダイアログ -->
        <UiDialog v-model:open="detailOpen" size="lg">
            <template #header>
                <span class="flex items-center gap-2">
                    <ImageIcon :size="16" class="text-brand-primary" />
                    メディア詳細
                </span>
            </template>
            <div v-if="detailMedia" class="space-y-4">
                <img :src="detailMedia.url" :alt="detailMedia.alt || ''" class="w-full max-h-[40vh] object-contain rounded-soft border border-brand-border bg-brand-surface-2" />
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-xs text-brand-text-muted">ファイル名</div>
                        <p class="text-brand-text break-all">{{ detailMedia.original_filename }}</p>
                    </div>
                    <div>
                        <div class="text-xs text-brand-text-muted">サイズ</div>
                        <p class="text-brand-text">{{ formatFileSize(detailMedia.file_size) }}</p>
                    </div>
                    <div>
                        <div class="text-xs text-brand-text-muted">解像度</div>
                        <p class="text-brand-text">{{ detailMedia.width }} x {{ detailMedia.height }} px</p>
                    </div>
                    <div>
                        <div class="text-xs text-brand-text-muted">使用箇所</div>
                        <p class="text-brand-text">{{ detailMedia.usage_count }} 箇所</p>
                    </div>
                    <div class="col-span-2">
                        <div class="text-xs text-brand-text-muted">登録日</div>
                        <p class="text-brand-text">{{ detailMedia.created_at }}</p>
                    </div>
                </div>
                <UiFormField label="Alt属性">
                    <UiInput v-model="detailForm.alt" size="sm" />
                </UiFormField>
                <UiFormField label="タグ（カンマ区切り）">
                    <UiInput v-model="detailTagInput" size="sm" />
                </UiFormField>
            </div>
            <template #footer>
                <UiButton
                    variant="ghost"
                    class="text-brand-danger mr-auto"
                    :disabled="detailMedia?.usage_count > 0"
                    :title="detailMedia?.usage_count > 0 ? '使用中のため削除できません' : ''"
                    @click="deleteMedia"
                >
                    <template #leading><Trash2 :size="13" /></template>
                    削除
                </UiButton>
                <UiButton variant="ghost" @click="closeDetail">キャンセル</UiButton>
                <UiButton variant="primary" :loading="isSavingDetail" @click="saveDetail">保存</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    UiPageHeader, UiButton, UiBadge, UiCard, UiFormField,
    UiInput, UiSelect, UiDialog,
} from '@/Components/UI';
import { Upload, Search, RefreshCw, Trash2, Image as ImageIcon } from 'lucide-vue-next';

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
const detailForm = ref({ alt: '' });
const detailTagInput = ref('');
const isSavingDetail = ref(false);
const isImporting = ref(false);

const detailOpen = computed({
    get: () => !!detailMedia.value,
    set: (v) => { if (!v) detailMedia.value = null; },
});

const uploadForm = useForm({
    images: [],
    tags: [],
});

const formatFileSize = (bytes) => {
    if (!bytes) return '—';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const handleFileChange = (event) => {
    const files = Array.from(event.target.files);
    selectedFiles.value = files;
    previewImages.value = [];
    files.forEach((file) => {
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
    selectedFiles.value.forEach((file) => dt.items.add(file));
    if (fileInput.value) fileInput.value.files = dt.files;
};

const submitUpload = () => {
    const tags = tagInput.value.split(',').map((t) => t.trim()).filter(Boolean);
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

const closeDetail = () => { detailMedia.value = null; };

const saveDetail = () => {
    if (!detailMedia.value) return;
    const tags = detailTagInput.value.split(',').map((t) => t.trim()).filter(Boolean);
    isSavingDetail.value = true;
    router.patch(route('admin.media.update', detailMedia.value.id), {
        alt: detailForm.value.alt,
        tags,
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
