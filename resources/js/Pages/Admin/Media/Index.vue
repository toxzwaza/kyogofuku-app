<template>
    <Head title="メディアライブラリ" />

    <AdminLayout :breadcrumb="[{ label: 'イベント・予約' }, { label: 'メディアライブラリ' }]">
        <UiPageHeader
            title="メディアライブラリ"
            description="画像を一元管理し、イベント／スライドショー等で再利用できます。"
        >
            <template #actions>
                <UiButton variant="subtle" @click="tagManagerOpen = true">
                    <template #leading><FolderTree :size="14" /></template>
                    タグ管理
                </UiButton>
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
                <UiFormField label="タグ" hint="セレクトボックスから選んで追加（複数可）">
                    <TagAssignField :options="tagOptions" :tag-by-id="tagById" v-model="uploadTagIds" />
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
            <div class="grid grid-cols-1 md:grid-cols-[1fr_200px_200px_auto] gap-3 items-end">
                <UiFormField label="ファイル名検索">
                    <UiInput v-model="searchQuery" placeholder="ファイル名で検索..." size="sm" @keyup.enter="applyFilters" />
                </UiFormField>
                <UiFormField label="タグ">
                    <UiSelect
                        v-model="filterRootId"
                        :options="[{ value: '', label: 'すべて' }, ...rootTagOptions]"
                        size="sm"
                        @update:modelValue="onChangeRoot"
                    />
                </UiFormField>
                <UiFormField label="配下タグ">
                    <UiSelect
                        v-model="filterSubId"
                        :options="[{ value: '', label: subOptions.length ? '指定なし' : '（配下タグなし）' }, ...subOptions]"
                        size="sm"
                        :disabled="!filterRootId || subOptions.length === 0"
                        @update:modelValue="applyFilters"
                    />
                </UiFormField>
                <div class="flex gap-2">
                    <UiButton variant="primary" size="sm" @click="applyFilters">
                        <template #leading><Search :size="13" /></template>
                        検索
                    </UiButton>
                    <UiButton v-if="searchQuery || filterRootId" variant="ghost" size="sm" @click="clearFilters">クリア</UiButton>
                </div>
            </div>
        </UiCard>

        <!-- グリッド -->
        <UiCard variant="default" padding="md">
            <div v-if="mediaFiles.data && mediaFiles.data.length > 0">
                <div class="flex flex-wrap items-center gap-3 mb-3">
                    <p class="text-xs text-brand-text-muted">{{ mediaFiles.total }} 件のメディア</p>
                    <label class="flex items-center gap-1 text-xs text-brand-text-muted cursor-pointer">
                        <input type="checkbox" :checked="allOnPageSelected" @change="toggleSelectAll" />
                        表示中を全選択
                    </label>
                </div>

                <!-- 一括操作ツールバー -->
                <div v-if="selectedIds.length > 0" class="mb-3 flex flex-wrap items-center gap-2 p-2 rounded-soft bg-brand-surface-2 border border-brand-border">
                    <span class="text-sm font-medium text-brand-text">{{ selectedIds.length }} 件選択中</span>
                    <div class="w-56">
                        <UiSelect
                            v-model="bulkTagSelect"
                            :options="[{ value: '', label: '選択中にタグを付与…' }, ...tagOptions]"
                            size="sm"
                            @update:modelValue="onBulkTag"
                        />
                    </div>
                    <UiButton variant="ghost" size="sm" class="text-brand-danger" @click="bulkDelete">
                        <template #leading><Trash2 :size="13" /></template>
                        選択を削除
                    </UiButton>
                    <UiButton variant="ghost" size="sm" @click="clearSelection">選択解除</UiButton>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <div
                        v-for="media in mediaFiles.data"
                        :key="media.id"
                        class="relative rounded-soft overflow-hidden border bg-brand-surface transition-all"
                        :class="isSelected(media.id) ? 'border-brand-primary ring-2 ring-brand-primary' : 'border-brand-border'"
                    >
                        <!-- 選択チェックボックス -->
                        <label
                            class="absolute top-1.5 left-1.5 z-10 flex items-center justify-center bg-white/90 rounded p-0.5 cursor-pointer shadow"
                            @click.stop
                        >
                            <input type="checkbox" :checked="isSelected(media.id)" @change="toggleSelect(media.id)" />
                        </label>
                        <!-- クリックで選択トグル -->
                        <button
                            type="button"
                            class="group block w-full text-left hover:opacity-95"
                            @click="toggleSelect(media.id)"
                        >
                            <img
                                :src="media.url"
                                :alt="media.alt || media.original_filename"
                                class="w-full h-32 object-cover"
                                loading="lazy"
                            />
                            <div class="p-2 pr-8">
                                <p class="text-xs text-brand-text truncate" :title="media.original_filename">
                                    {{ media.original_filename }}
                                </p>
                                <div class="text-[10px] text-brand-text-subtle flex items-center gap-1 mt-0.5">
                                    <span>{{ formatFileSize(media.file_size) }}</span>
                                    <span v-if="media.usage_count > 0" class="text-brand-primary">・{{ media.usage_count }}箇所</span>
                                </div>
                                <div v-if="media.tags && media.tags.length > 0" class="mt-1 flex flex-wrap gap-0.5">
                                    <UiBadge v-for="tag in media.tags.slice(0, 2)" :key="tag.id" size="sm" variant="neutral" :title="tag.full_path">{{ tag.name }}</UiBadge>
                                    <span v-if="media.tags.length > 2" class="text-[10px] text-brand-text-subtle">+{{ media.tags.length - 2 }}</span>
                                </div>
                            </div>
                        </button>
                        <!-- 詳細を開く（右下） -->
                        <button
                            type="button"
                            class="absolute bottom-1.5 right-1.5 z-10 flex items-center justify-center bg-white/90 hover:bg-white text-brand-text rounded p-1 shadow border border-brand-border"
                            title="詳細を表示"
                            @click.stop="openDetail(media)"
                        >
                            <Eye :size="14" />
                        </button>
                    </div>
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
                <UiFormField label="タグ" hint="セレクトボックスから選んで追加（複数可）">
                    <TagAssignField :options="tagOptions" :tag-by-id="tagById" v-model="detailTagIds" />
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

        <!-- タグ管理ダイアログ -->
        <UiDialog v-model:open="tagManagerOpen" size="lg">
            <template #header>
                <span class="flex items-center gap-2">
                    <FolderTree :size="16" class="text-brand-primary" />
                    タグ管理（階層）
                </span>
            </template>
            <div class="space-y-4">
                <p class="text-xs text-brand-text-muted">
                    タグは階層（フォルダ風）で管理できます。例: 2026 ＞ イベント ＞ イベント1
                </p>

                <!-- ルートタグ追加 -->
                <div class="flex gap-2 items-end">
                    <UiFormField label="ルートにタグを追加" class="flex-1">
                        <UiInput v-model="newRootName" size="sm" placeholder="例: 2026" @keyup.enter="createTag(null, newRootName, () => newRootName = '')" />
                    </UiFormField>
                    <UiButton variant="primary" size="sm" :disabled="!newRootName.trim()" @click="createTag(null, newRootName, () => newRootName = '')">追加</UiButton>
                </div>

                <!-- ツリー -->
                <div class="border border-brand-border rounded-soft divide-y divide-brand-border max-h-[50vh] overflow-y-auto">
                    <div v-if="orderedTree.length === 0" class="p-4 text-center text-sm text-brand-text-muted">タグがありません</div>
                    <div
                        v-for="node in orderedTree"
                        :key="node.id"
                        class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-brand-surface-2"
                        :style="{ paddingLeft: (12 + node.depth * 20) + 'px' }"
                    >
                        <FolderTree :size="13" class="text-brand-text-subtle shrink-0" />
                        <span class="text-brand-text">{{ node.name }}</span>
                        <span v-if="node.media_count > 0" class="text-[10px] text-brand-text-subtle">（{{ node.media_count }}）</span>

                        <!-- 移動セレクト -->
                        <div v-if="movingId === node.id" class="ml-auto flex items-center gap-2 min-w-0">
                            <UiSelect
                                v-model="moveTargetId"
                                :options="moveParentOptions(node)"
                                size="sm"
                                class="flex-1 min-w-0"
                            />
                            <UiButton variant="primary" size="sm" class="shrink-0 whitespace-nowrap" @click="confirmMove(node)">確定</UiButton>
                            <UiButton variant="ghost" size="sm" class="shrink-0 whitespace-nowrap" @click="movingId = null">取消</UiButton>
                        </div>

                        <div v-else class="ml-auto flex items-center gap-1">
                            <button type="button" class="text-xs text-brand-primary hover:underline" @click="addChild(node)">＋子</button>
                            <button type="button" class="text-xs text-brand-text-muted hover:underline" @click="renameTag(node)">名前変更</button>
                            <button type="button" class="text-xs text-brand-text-muted hover:underline" @click="startMove(node)">移動</button>
                            <button type="button" class="text-xs text-brand-danger hover:underline" @click="deleteTag(node)">削除</button>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <UiButton variant="ghost" @click="tagManagerOpen = false">閉じる</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, h } from 'vue';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    UiPageHeader, UiButton, UiBadge, UiCard, UiFormField,
    UiInput, UiSelect, UiDialog,
} from '@/Components/UI';
import { Upload, Search, RefreshCw, Trash2, Image as ImageIcon, FolderTree, Eye } from 'lucide-vue-next';

const props = defineProps({
    mediaFiles: Object,
    tagTree: Array,
    filters: Object,
});

// ---- タグの派生データ ----
const tagById = computed(() => {
    const map = {};
    (props.tagTree || []).forEach((t) => { map[t.id] = t; });
    return map;
});

// セレクト用オプション（パス順）
const tagOptions = computed(() =>
    [...(props.tagTree || [])]
        .sort((a, b) => a.full_path.localeCompare(b.full_path, 'ja'))
        .map((t) => ({ value: t.id, label: t.full_path }))
);

// 管理ツリー（DFSで深さ付き並び）
const orderedTree = computed(() => {
    const byParent = {};
    (props.tagTree || []).forEach((t) => {
        const key = t.parent_id == null ? 'root' : t.parent_id;
        (byParent[key] ||= []).push(t);
    });
    Object.values(byParent).forEach((arr) => arr.sort((a, b) => a.name.localeCompare(b.name, 'ja')));
    const out = [];
    const walk = (key, depth) => {
        (byParent[key] || []).forEach((t) => {
            out.push({ ...t, depth });
            walk(t.id, depth + 1);
        });
    };
    walk('root', 0);
    return out;
});

const descendantIds = (id) => {
    const ids = [id];
    const stack = [id];
    while (stack.length) {
        const cur = stack.pop();
        (props.tagTree || []).filter((t) => t.parent_id === cur).forEach((c) => {
            ids.push(c.id);
            stack.push(c.id);
        });
    }
    return ids;
};

// 移動先（親）候補：自分自身・子孫を除外＋ルート
const moveParentOptions = (node) => {
    const exclude = new Set(descendantIds(node.id));
    const opts = [{ value: '', label: '（ルート）' }];
    [...(props.tagTree || [])]
        .filter((t) => !exclude.has(t.id))
        .sort((a, b) => a.full_path.localeCompare(b.full_path, 'ja'))
        .forEach((t) => opts.push({ value: t.id, label: t.full_path }));
    return opts;
};

// ---- タグ絞り込み（2段：直下タグ＋配下タグ）----
const rootTagOptions = computed(() =>
    [...(props.tagTree || [])]
        .filter((t) => t.parent_id == null)
        .sort((a, b) => a.name.localeCompare(b.name, 'ja'))
        .map((t) => ({ value: t.id, label: t.name }))
);

const rootAncestorId = (id) => {
    let cur = tagById.value[id];
    while (cur && cur.parent_id != null) cur = tagById.value[cur.parent_id];
    return cur ? cur.id : '';
};

const initialTagId = props.filters?.tag_id ? Number(props.filters.tag_id) : '';
const initialRootId = initialTagId ? rootAncestorId(initialTagId) : '';

const filterRootId = ref(initialRootId || '');
const filterSubId = ref(initialTagId && initialRootId !== initialTagId ? initialTagId : '');

// 選択中の直下タグの配下（子孫・自身を除く）
const subOptions = computed(() => {
    if (!filterRootId.value) return [];
    const ids = descendantIds(Number(filterRootId.value)).filter((id) => id !== Number(filterRootId.value));
    return ids
        .map((id) => tagById.value[id])
        .filter(Boolean)
        .sort((a, b) => a.full_path.localeCompare(b.full_path, 'ja'))
        .map((t) => ({ value: t.id, label: t.full_path }));
});

// 実際に適用されるタグID（配下タグ優先、無ければ直下タグ）
const appliedTagId = computed(() => filterSubId.value || filterRootId.value || '');

const onChangeRoot = () => {
    filterSubId.value = '';
    applyFilters();
};

// ---- 状態 ----
const searchQuery = ref(props.filters?.search || '');
const uploadTagIds = ref([]);
const fileInput = ref(null);
const selectedFiles = ref([]);
const previewImages = ref([]);
const detailMedia = ref(null);
const detailForm = ref({ alt: '' });
const detailTagIds = ref([]);
const isSavingDetail = ref(false);
const isImporting = ref(false);

const tagManagerOpen = ref(false);
const newRootName = ref('');
const movingId = ref(null);
const moveTargetId = ref('');

// ---- 一括選択 ----
const selectedIds = ref([]);
const bulkTagSelect = ref('');

const isSelected = (id) => selectedIds.value.includes(id);

const toggleSelect = (id) => {
    if (isSelected(id)) {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    } else {
        selectedIds.value = [...selectedIds.value, id];
    }
};

const pageIds = computed(() => (props.mediaFiles?.data || []).map((m) => m.id));
const allOnPageSelected = computed(() =>
    pageIds.value.length > 0 && pageIds.value.every((id) => selectedIds.value.includes(id))
);

const toggleSelectAll = () => {
    if (allOnPageSelected.value) {
        const onPage = new Set(pageIds.value);
        selectedIds.value = selectedIds.value.filter((id) => !onPage.has(id));
    } else {
        const set = new Set([...selectedIds.value, ...pageIds.value]);
        selectedIds.value = [...set];
    }
};

const clearSelection = () => { selectedIds.value = []; };

const onBulkTag = (tagId) => {
    if (!tagId) return;
    router.post(route('admin.media.bulk-tag'), {
        media_ids: selectedIds.value,
        tag_ids: [tagId],
        ...(searchQuery.value ? { search: searchQuery.value } : {}),
        ...(appliedTagId.value ? { tag_id: appliedTagId.value } : {}),
    }, {
        preserveScroll: true,
        onFinish: () => { bulkTagSelect.value = ''; },
    });
};

const bulkDelete = () => {
    if (selectedIds.value.length === 0) return;
    if (!confirm(`選択した ${selectedIds.value.length} 件のメディアを削除しますか？\n（使用中のメディアはスキップされます）`)) return;
    router.post(route('admin.media.bulk-delete'), {
        media_ids: selectedIds.value,
        ...(searchQuery.value ? { search: searchQuery.value } : {}),
        ...(appliedTagId.value ? { tag_id: appliedTagId.value } : {}),
    }, {
        preserveScroll: true,
        onSuccess: () => clearSelection(),
    });
};

const detailOpen = computed({
    get: () => !!detailMedia.value,
    set: (v) => { if (!v) detailMedia.value = null; },
});

const uploadForm = useForm({
    images: [],
    tag_ids: [],
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
    uploadForm.tag_ids = uploadTagIds.value;
    uploadForm.post(route('admin.media.store'), {
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset();
            selectedFiles.value = [];
            previewImages.value = [];
            uploadTagIds.value = [];
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const applyFilters = () => {
    router.get(route('admin.media.index'), {
        search: searchQuery.value || undefined,
        tag_id: appliedTagId.value || undefined,
    }, { preserveState: true });
};

const clearFilters = () => {
    searchQuery.value = '';
    filterRootId.value = '';
    filterSubId.value = '';
    router.get(route('admin.media.index'));
};

const openDetail = (media) => {
    detailMedia.value = media;
    detailForm.value = { alt: media.alt || '' };
    detailTagIds.value = (media.tags || []).map((t) => t.id);
};

const closeDetail = () => { detailMedia.value = null; };

const saveDetail = () => {
    if (!detailMedia.value) return;
    isSavingDetail.value = true;
    router.patch(route('admin.media.update', detailMedia.value.id), {
        alt: detailForm.value.alt,
        tag_ids: detailTagIds.value,
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

// ---- タグ管理操作 ----
const reloadTags = () => router.reload({ only: ['tagTree', 'mediaFiles'], preserveScroll: true, preserveState: true });

const tagError = (e) => {
    const msg = e?.response?.data?.errors
        ? Object.values(e.response.data.errors).flat().join('\n')
        : (e?.response?.data?.message || 'タグの操作に失敗しました。');
    alert(msg);
};

const createTag = async (parentId, name, onDone) => {
    const trimmed = (name || '').trim();
    if (!trimmed) return;
    try {
        await axios.post(route('admin.media.tags.store'), { name: trimmed, parent_id: parentId });
        onDone && onDone();
        reloadTags();
    } catch (e) { tagError(e); }
};

const addChild = (node) => {
    const name = window.prompt(`「${node.name}」の子タグ名を入力してください`, '');
    if (name === null) return;
    createTag(node.id, name);
};

const renameTag = async (node) => {
    const name = window.prompt('新しいタグ名を入力してください', node.name);
    if (name === null) return;
    const trimmed = name.trim();
    if (!trimmed || trimmed === node.name) return;
    try {
        await axios.patch(route('admin.media.tags.update', node.id), { name: trimmed, parent_id: node.parent_id });
        reloadTags();
    } catch (e) { tagError(e); }
};

const startMove = (node) => {
    movingId.value = node.id;
    moveTargetId.value = node.parent_id ?? '';
};

const confirmMove = async (node) => {
    try {
        await axios.patch(route('admin.media.tags.update', node.id), {
            name: node.name,
            parent_id: moveTargetId.value === '' ? null : moveTargetId.value,
        });
        movingId.value = null;
        reloadTags();
    } catch (e) { tagError(e); }
};

const deleteTag = async (node) => {
    if (!confirm(`タグ「${node.name}」を削除しますか？\n配下の子タグもすべて削除され、メディアからの紐付けも解除されます。`)) return;
    try {
        await axios.delete(route('admin.media.tags.destroy', node.id));
        reloadTags();
    } catch (e) { tagError(e); }
};

// ---- タグ付与フィールド（セレクト＋チップ）インラインコンポーネント ----
const TagAssignField = {
    props: {
        options: { type: Array, default: () => [] },
        tagById: { type: Object, default: () => ({}) },
        modelValue: { type: Array, default: () => [] },
    },
    emits: ['update:modelValue'],
    setup(p, { emit }) {
        const addSel = ref('');
        const available = computed(() => p.options.filter((o) => !p.modelValue.includes(o.value)));
        const onAdd = (val) => {
            if (val === '' || val == null) return;
            if (!p.modelValue.includes(val)) emit('update:modelValue', [...p.modelValue, val]);
            addSel.value = '';
        };
        const removeTag = (id) => emit('update:modelValue', p.modelValue.filter((x) => x !== id));
        return () => h('div', { class: 'space-y-2' }, [
            h(UiSelect, {
                modelValue: addSel.value,
                'onUpdate:modelValue': onAdd,
                size: 'sm',
                options: [{ value: '', label: 'タグを選択して追加…' }, ...available.value],
            }),
            p.modelValue.length > 0
                ? h('div', { class: 'flex flex-wrap gap-1' }, p.modelValue.map((id) =>
                    h('span', {
                        key: id,
                        class: 'inline-flex items-center gap-1 rounded bg-brand-surface-2 border border-brand-border px-2 py-0.5 text-xs text-brand-text',
                    }, [
                        p.tagById[id]?.full_path || ('#' + id),
                        h('button', {
                            type: 'button',
                            class: 'text-brand-text-subtle hover:text-brand-danger',
                            onClick: () => removeTag(id),
                        }, '×'),
                    ])
                ))
                : h('p', { class: 'text-xs text-brand-text-subtle' }, '未設定'),
        ]);
    },
};
</script>
