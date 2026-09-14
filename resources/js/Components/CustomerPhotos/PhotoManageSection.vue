<script setup>
/**
 * 写真セクション（アップロード・メディアライブラリ選択・一覧・削除）。
 *
 * 予約詳細「写真・アンケート」タブ用。routeBase でエンドポイントを切り替える:
 *   予約: routeBase='admin.reservations.photos', ownerId=予約ID
 *   （.store / .from-media / .destroy が続く）
 * メディアライブラリの画像一覧は admin.customers.media-library（共通JSON）を使う。
 */
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { UiCard, UiButton } from '@/Components/UI';
import { Camera as CameraIcon, Image as ImageIcon, FolderOpen, X as XIcon } from 'lucide-vue-next';

const props = defineProps({
    routeBase: { type: String, required: true },
    ownerId: { type: [Number, String], required: true },
    photos: { type: Array, default: () => [] },
    photoTypes: { type: Array, default: () => [] },
});

// ===== アップロードフォーム =====
const photoForm = useForm({
    photo_type_id: '',
    photo: null,
    remarks: '',
});
const photoPreview = ref(null);
const isPdfSelected = ref(false);
const pdfFileName = ref('');
const photoFileInputCamera = ref(null);
const photoFileInputFile = ref(null);

const onPhotoFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        selectedMediaFile.value = null;
        photoForm.photo = file;
        const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
        if (isPdf) {
            isPdfSelected.value = true;
            pdfFileName.value = file.name;
            photoPreview.value = null;
        } else {
            isPdfSelected.value = false;
            pdfFileName.value = '';
            const reader = new FileReader();
            reader.onload = (e) => {
                photoPreview.value = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    } else {
        photoPreview.value = null;
        isPdfSelected.value = false;
        pdfFileName.value = '';
    }
};

const resetPhotoForm = () => {
    photoForm.reset();
    photoPreview.value = null;
    isPdfSelected.value = false;
    pdfFileName.value = '';
    selectedMediaFile.value = null;
    if (photoFileInputCamera.value) photoFileInputCamera.value.value = '';
    if (photoFileInputFile.value) photoFileInputFile.value.value = '';
};

const mediaPhotoSubmitting = ref(false);

const submitPhoto = () => {
    // メディアライブラリ選択時は from-media、ファイル選択時は通常アップロード
    if (selectedMediaFile.value) {
        mediaPhotoSubmitting.value = true;
        router.post(route(`${props.routeBase}.from-media`, props.ownerId), {
            media_file_id: selectedMediaFile.value.id,
            photo_type_id: photoForm.photo_type_id,
            remarks: photoForm.remarks,
        }, {
            preserveScroll: true,
            onSuccess: resetPhotoForm,
            onFinish: () => { mediaPhotoSubmitting.value = false; },
        });
        return;
    }
    photoForm.post(route(`${props.routeBase}.store`, props.ownerId), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: resetPhotoForm,
    });
};

// ===== メディアライブラリ =====
const showMediaLibraryModal = ref(false);
const mediaLibraryLoading = ref(false);
const mediaLibraryTagId = ref('');
const mediaLibrary = ref({ parentTag: 'タブレット画像', prefix: '', deviceTags: [], mediaFiles: { data: [], current_page: 1, last_page: 1 } });
const selectedMediaFile = ref(null);

const fetchMediaLibrary = async (page = 1) => {
    mediaLibraryLoading.value = true;
    try {
        const params = new URLSearchParams();
        if (mediaLibraryTagId.value) params.set('tag_id', mediaLibraryTagId.value);
        params.set('page', String(page));
        const res = await fetch(route('admin.customers.media-library') + '?' + params.toString(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        mediaLibrary.value = await res.json();
    } catch (e) {
        mediaLibrary.value = { parentTag: 'タブレット画像', prefix: '', deviceTags: [], mediaFiles: { data: [], current_page: 1, last_page: 1 } };
    } finally {
        mediaLibraryLoading.value = false;
    }
};

const openMediaLibrary = () => {
    showMediaLibraryModal.value = true;
    fetchMediaLibrary(1);
};

const selectMediaFromLibrary = (m) => {
    selectedMediaFile.value = m;
    photoForm.photo = null;
    isPdfSelected.value = false;
    pdfFileName.value = '';
    photoPreview.value = m.url;
    showMediaLibraryModal.value = false;
    if (photoFileInputCamera.value) photoFileInputCamera.value.value = '';
    if (photoFileInputFile.value) photoFileInputFile.value.value = '';
};

// ===== 一覧・プレビュー・削除 =====
const previewPhoto = ref(null);

const getPhotoUrl = (photo) => {
    if (!photo) return '';
    if (photo.url) return photo.url;
    return `/storage/${photo.file_path || ''}`;
};

const isPdfPhoto = (photo) => {
    if (!photo) return false;
    return /\.pdf$/i.test(photo.file_path || '');
};

const openPhoto = (photo) => {
    if (isPdfPhoto(photo)) {
        window.open(getPhotoUrl(photo), '_blank');
        return;
    }
    previewPhoto.value = photo;
};

const deletePhoto = (photo) => {
    if (!confirm('この写真を削除しますか？')) return;
    router.delete(route(`${props.routeBase}.destroy`, [props.ownerId, photo.id]), {
        preserveScroll: true,
    });
};

const canSubmit = computed(() => !!photoForm.photo_type_id && (!!photoForm.photo || !!selectedMediaFile.value));
</script>

<template>
    <UiCard variant="default" padding="lg">
        <template #header>
            <h3 class="font-serif text-base font-semibold flex items-center gap-2 text-brand-text">
                <ImageIcon :size="15" class="text-brand-primary" />
                写真
            </h3>
        </template>

        <!-- アップロードフォーム -->
        <form @submit.prevent="submitPhoto" class="mb-6 border border-brand-border rounded-lg p-4 bg-brand-surface-2/40">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">写真の種類 <span class="text-brand-danger">*</span></label>
                    <select
                        v-model="photoForm.photo_type_id"
                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                    >
                        <option value="">選択してください</option>
                        <option v-for="t in photoTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">ファイル</label>
                    <div class="flex flex-wrap gap-2">
                        <input ref="photoFileInputCamera" type="file" accept="image/*" capture="environment" class="hidden" @change="onPhotoFileChange" />
                        <input ref="photoFileInputFile" type="file" accept="image/jpeg,image/png,image/gif,application/pdf" class="hidden" @change="onPhotoFileChange" />
                        <UiButton variant="ghost" size="sm" type="button" @click="photoFileInputCamera.click()">
                            <CameraIcon :size="14" /> カメラで撮影
                        </UiButton>
                        <UiButton variant="ghost" size="sm" type="button" @click="photoFileInputFile.click()">
                            <FolderOpen :size="14" /> ファイルを選択
                        </UiButton>
                        <UiButton variant="ghost" size="sm" type="button" @click="openMediaLibrary">
                            <ImageIcon :size="14" /> メディアライブラリ
                        </UiButton>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-xs font-medium text-brand-text mb-1">備考</label>
                <textarea
                    v-model="photoForm.remarks"
                    rows="2"
                    class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                    placeholder="写真に関する備考を入力"
                ></textarea>
            </div>
            <!-- プレビュー -->
            <div v-if="photoPreview" class="mt-3">
                <div class="inline-block w-28 h-28 rounded-lg overflow-hidden border border-brand-border bg-brand-surface-2">
                    <img :src="photoPreview" alt="プレビュー" class="w-full h-full object-cover" />
                </div>
                <p class="mt-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2 inline-block">
                    「写真を追加」ボタンを押して保存してください。
                </p>
            </div>
            <div v-else-if="isPdfSelected" class="mt-3">
                <div class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-brand-border bg-brand-surface-2 text-sm text-brand-text">
                    <span class="text-red-600 font-semibold text-xs">PDF</span>
                    <span class="break-all">{{ pdfFileName }}</span>
                </div>
            </div>
            <div class="mt-3 flex justify-end">
                <UiButton
                    variant="primary"
                    size="sm"
                    type="submit"
                    :disabled="photoForm.processing || mediaPhotoSubmitting || !canSubmit"
                    :loading="photoForm.processing || mediaPhotoSubmitting"
                >
                    写真を追加
                </UiButton>
            </div>
            <p v-if="photoForm.errors.photo" class="mt-1 text-xs text-brand-danger">{{ photoForm.errors.photo }}</p>
            <p v-if="photoForm.errors.photo_type_id" class="mt-1 text-xs text-brand-danger">{{ photoForm.errors.photo_type_id }}</p>
        </form>

        <!-- 写真一覧 -->
        <div v-if="photos.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <div v-for="photo in photos" :key="photo.id" class="relative group cursor-pointer" @click="openPhoto(photo)">
                <div class="aspect-square rounded-lg overflow-hidden border border-brand-border bg-brand-surface-2 hover:border-brand-primary transition-colors relative">
                    <div
                        v-if="isPdfPhoto(photo)"
                        class="w-full h-full flex flex-col items-center justify-center gap-2 bg-red-50 text-red-600"
                    >
                        <span class="text-lg font-bold">PDF</span>
                        <span class="text-xs">クリックで開く</span>
                    </div>
                    <img
                        v-else
                        :src="getPhotoUrl(photo)"
                        :alt="photo.type?.name || '写真'"
                        class="w-full h-full object-cover"
                    />
                    <div class="absolute top-1 right-1 z-10">
                        <button
                            type="button"
                            class="w-7 h-7 flex items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600 shadow-md"
                            title="写真を削除"
                            @click.stop="deletePhoto(photo)"
                        >
                            <XIcon :size="14" />
                        </button>
                    </div>
                </div>
                <div class="mt-2 text-sm">
                    <div class="font-medium text-brand-text">{{ photo.type?.name || '-' }}</div>
                    <div v-if="photo.remarks" class="text-brand-text-muted text-xs mt-1 line-clamp-2">{{ photo.remarks }}</div>
                </div>
            </div>
        </div>
        <div v-else class="text-center py-8 text-brand-text-muted">
            写真がありません
        </div>

        <!-- 拡大プレビュー -->
        <div
            v-if="previewPhoto"
            class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-6 cursor-pointer"
            @click="previewPhoto = null"
        >
            <img :src="getPhotoUrl(previewPhoto)" class="max-w-full max-h-full rounded shadow-2xl" alt="プレビュー">
        </div>

        <!-- メディアライブラリ選択モーダル -->
        <div
            v-if="showMediaLibraryModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showMediaLibraryModal = false"
        >
            <div class="bg-brand-surface rounded-lg shadow-xl w-full max-w-4xl max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between px-5 py-4 border-b border-brand-border">
                    <h3 class="text-base font-semibold text-brand-text">メディアライブラリから選択</h3>
                    <button type="button" class="text-brand-text-muted hover:text-brand-text" @click="showMediaLibraryModal = false">
                        <XIcon :size="18" />
                    </button>
                </div>
                <div class="px-5 py-3 border-b border-brand-border flex flex-wrap items-center gap-4">
                    <div class="text-sm text-brand-text">
                        タグ：<span class="font-semibold">{{ mediaLibrary.parentTag || 'タブレット画像' }}</span>（固定）
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-brand-text-muted">配下タグ</label>
                        <select
                            v-model="mediaLibraryTagId"
                            class="rounded-md border-brand-border text-sm focus:border-brand-primary focus:ring-brand-primary"
                            @change="fetchMediaLibrary(1)"
                        >
                            <option value="">すべて</option>
                            <option v-for="t in mediaLibrary.deviceTags" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-5">
                    <p v-if="mediaLibraryLoading" class="text-center text-sm text-brand-text-muted py-10">読み込み中…</p>
                    <template v-else>
                        <p v-if="!mediaLibrary.deviceTags.length" class="text-center text-sm text-brand-text-muted py-10">
                            「{{ mediaLibrary.parentTag }}」配下に {{ mediaLibrary.prefix }} から始まるタグがありません。
                        </p>
                        <p v-else-if="!mediaLibrary.mediaFiles?.data?.length" class="text-center text-sm text-brand-text-muted py-10">
                            該当する画像がありません。
                        </p>
                        <div v-else class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                            <button
                                v-for="m in mediaLibrary.mediaFiles.data"
                                :key="m.id"
                                type="button"
                                class="group relative rounded-lg overflow-hidden border border-brand-border hover:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary"
                                @click="selectMediaFromLibrary(m)"
                            >
                                <img :src="m.url" :alt="m.original_filename" loading="lazy" class="w-full h-28 object-cover" />
                                <div class="absolute inset-x-0 bottom-0 bg-black/55 text-white text-[10px] px-1.5 py-1 truncate text-left">
                                    {{ m.created_at }}
                                </div>
                            </button>
                        </div>
                    </template>
                </div>
                <div v-if="(mediaLibrary.mediaFiles?.last_page || 1) > 1" class="px-5 py-3 border-t border-brand-border flex items-center justify-center gap-3">
                    <button
                        type="button"
                        :disabled="mediaLibrary.mediaFiles.current_page <= 1 || mediaLibraryLoading"
                        class="px-3 py-1.5 rounded-md border border-brand-border text-sm disabled:opacity-40"
                        @click="fetchMediaLibrary(mediaLibrary.mediaFiles.current_page - 1)"
                    >
                        前へ
                    </button>
                    <span class="text-xs text-brand-text-muted">{{ mediaLibrary.mediaFiles.current_page }} / {{ mediaLibrary.mediaFiles.last_page }}</span>
                    <button
                        type="button"
                        :disabled="mediaLibrary.mediaFiles.current_page >= mediaLibrary.mediaFiles.last_page || mediaLibraryLoading"
                        class="px-3 py-1.5 rounded-md border border-brand-border text-sm disabled:opacity-40"
                        @click="fetchMediaLibrary(mediaLibrary.mediaFiles.current_page + 1)"
                    >
                        次へ
                    </button>
                </div>
            </div>
        </div>
    </UiCard>
</template>
