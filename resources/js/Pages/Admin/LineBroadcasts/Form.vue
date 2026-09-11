<template>
    <Head :title="isEdit ? 'LINE広告を編集' : 'LINE広告を作成'" />

    <AdminLayout :breadcrumb="[
        { label: '顧客' },
        { label: 'LINE広告', href: route('admin.line-broadcasts.index') },
        { label: isEdit ? '編集' : '新規作成' },
    ]">
        <UiPageHeader
            :title="isEdit ? 'LINE広告を編集' : 'LINE広告を作成'"
            description="配信するメッセージ内容を作成します。右側のプレビューで実際の見え方を確認できます。"
        >
            <template #actions>
                <UiButton v-if="isEdit" variant="primary" size="sm" :href="route('admin.line-broadcasts.recipients', broadcast.id)">
                    <template #leading><Send :size="14" /></template>
                    送信先を選んで送る
                </UiButton>
            </template>
        </UiPageHeader>

        <div v-if="isEdit && broadcast.sent_count > 0" class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            この広告は既に {{ broadcast.sent_count }} 件送信済みです。内容を変更すると、以後の送信には変更後の内容が使われます（送信済みの方への再送はされません）。
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            <!-- 左：入力フォーム -->
            <UiCard variant="default" padding="md">
                <form @submit.prevent="submit" class="space-y-4">
                    <UiFormField label="タイトル" hint="管理用の名前です。お客様には表示されません。" required>
                        <UiInput v-model="form.title" placeholder="例: 秋の前撮りキャンペーン" />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-brand-danger">{{ form.errors.title }}</p>
                    </UiFormField>

                    <UiFormField label="バナー画像" hint="JPEG / PNG、5MBまで。画像だけの配信も可能です。">
                        <div class="space-y-2">
                            <input
                                ref="fileInput"
                                type="file"
                                accept="image/jpeg,image/png"
                                class="block w-full text-sm text-brand-text-muted file:mr-3 file:rounded-md file:border-0 file:bg-brand-primary file:px-3 file:py-1.5 file:text-sm file:text-brand-on-primary file:cursor-pointer"
                                @change="onFileChange"
                            />
                            <div v-if="previewImageUrl" class="flex items-center gap-3">
                                <img :src="previewImageUrl" class="h-20 rounded-md border border-brand-border object-contain" alt="バナー画像" />
                                <UiButton size="sm" variant="ghost" class="text-brand-danger" type="button" @click="clearImage">
                                    画像を外す
                                </UiButton>
                            </div>
                        </div>
                        <p v-if="form.errors.image_file" class="mt-1 text-xs text-brand-danger">{{ form.errors.image_file }}</p>
                    </UiFormField>

                    <UiFormField label="メッセージ本文" hint="4500文字まで。空欄にして画像のみ送ることもできます。">
                        <textarea
                            v-model="form.text"
                            rows="8"
                            class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                            placeholder="キャンペーンのご案内文を入力してください"
                        ></textarea>
                        <p v-if="form.errors.text" class="mt-1 text-xs text-brand-danger">{{ form.errors.text }}</p>
                    </UiFormField>

                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-brand-border">
                        <UiButton variant="primary" type="submit" :loading="form.processing">
                            {{ isEdit ? '保存' : '下書きとして保存' }}
                        </UiButton>
                        <UiButton v-if="isEdit" variant="subtle" type="button" @click="openTestSend">
                            <template #leading><FlaskConical :size="14" /></template>
                            テスト送信
                        </UiButton>
                        <UiButton variant="ghost" type="button" :href="route('admin.line-broadcasts.index')">一覧へ戻る</UiButton>
                    </div>
                    <p v-if="!isEdit" class="text-xs text-brand-text-muted">
                        保存するとテスト送信・送信先の選択ができるようになります。
                    </p>
                </form>
            </UiCard>

            <!-- 右：LINE風プレビュー -->
            <UiCard variant="default" padding="md">
                <h3 class="text-sm font-semibold text-brand-text mb-3">プレビュー</h3>
                <div class="rounded-xl p-4 min-h-[320px]" style="background-color: #8cabd8;">
                    <div class="flex items-start gap-2">
                        <div class="h-8 w-8 rounded-full bg-white/80 flex items-center justify-center text-xs font-bold text-[#8cabd8] shrink-0">呉</div>
                        <div class="space-y-2 max-w-[75%]">
                            <div v-if="previewImageUrl" class="rounded-2xl overflow-hidden bg-white shadow">
                                <img :src="previewImageUrl" class="w-full object-contain" alt="バナー画像プレビュー" />
                            </div>
                            <div v-if="form.text" class="rounded-2xl bg-white px-3 py-2 shadow text-sm text-gray-800 whitespace-pre-wrap break-words">{{ form.text }}</div>
                            <p v-if="!previewImageUrl && !form.text" class="text-sm text-white/80">
                                本文や画像を入力するとここに表示されます
                            </p>
                        </div>
                    </div>
                </div>
                <p class="mt-2 text-xs text-brand-text-muted">
                    ※ 実際の表示はお客様の端末・LINEのバージョンにより多少異なります。
                </p>
            </UiCard>
        </div>

        <!-- テスト送信ダイアログ -->
        <UiDialog v-model:open="testDialogOpen" title="テスト送信">
            <div class="space-y-3">
                <p class="text-sm text-brand-text-muted">
                    LINE連携済みのアカウントを検索して、この広告を1通だけテスト送信します（配信履歴には記録されません）。
                </p>
                <UiFormField label="送信先を検索">
                    <UiInput v-model="testQuery" placeholder="名前・カナで検索" size="sm" @input="searchTestContacts" />
                </UiFormField>
                <div v-if="testContacts.length" class="rounded-md border border-brand-border max-h-48 overflow-y-auto divide-y divide-brand-border">
                    <button
                        v-for="c in testContacts"
                        :key="c.id"
                        type="button"
                        class="w-full px-3 py-2 text-left text-sm flex items-center justify-between hover:bg-brand-surface-2"
                        :class="testTarget?.id === c.id ? 'bg-brand-surface-2' : ''"
                        @click="testTarget = c"
                    >
                        <span>
                            {{ c.name }}
                            <span class="ml-1 text-xs text-brand-text-muted">{{ c.shop_name || '' }}</span>
                        </span>
                        <UiBadge :variant="c.kind === 'customer' ? 'primary' : c.kind === 'reservation' ? 'success' : 'neutral'" size="sm">
                            {{ c.kind === 'customer' ? '顧客' : c.kind === 'reservation' ? '予約' : '未紐付' }}
                        </UiBadge>
                    </button>
                </div>
                <p v-else-if="testQuery && !testSearching" class="text-xs text-brand-text-muted">該当する連携アカウントが見つかりません。</p>
                <p v-if="testResult" class="text-sm" :class="testResultOk ? 'text-brand-success' : 'text-brand-danger'">{{ testResult }}</p>
            </div>
            <template #footer>
                <UiButton variant="ghost" @click="testDialogOpen = false">閉じる</UiButton>
                <UiButton variant="primary" :disabled="!testTarget" :loading="testSending" @click="doTestSend">
                    {{ testTarget ? `${testTarget.name} に送信` : '送信先を選択してください' }}
                </UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    UiPageHeader, UiButton, UiBadge, UiCard, UiDialog, UiFormField, UiInput,
} from '@/Components/UI';
import { Send, FlaskConical } from 'lucide-vue-next';

const props = defineProps({
    broadcast: { type: Object, default: null },
});

const isEdit = computed(() => !!props.broadcast);

const form = useForm({
    title: props.broadcast?.title || '',
    text: props.broadcast?.text || '',
    image_file: null,
    remove_image: false,
});

const fileInput = ref(null);
const localPreviewUrl = ref(null);

const previewImageUrl = computed(() => {
    if (localPreviewUrl.value) return localPreviewUrl.value;
    if (form.remove_image) return null;
    return props.broadcast?.image_url || null;
});

function onFileChange(e) {
    const file = e.target.files?.[0] || null;
    form.image_file = file;
    form.remove_image = false;
    if (localPreviewUrl.value) URL.revokeObjectURL(localPreviewUrl.value);
    localPreviewUrl.value = file ? URL.createObjectURL(file) : null;
}

function clearImage() {
    form.image_file = null;
    form.remove_image = true;
    if (localPreviewUrl.value) URL.revokeObjectURL(localPreviewUrl.value);
    localPreviewUrl.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

function submit() {
    if (isEdit.value) {
        // ファイルアップロードを含むため POST + _method=put で送信
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(route('admin.line-broadcasts.update', props.broadcast.id), { preserveScroll: true });
    } else {
        form.post(route('admin.line-broadcasts.store'), { preserveScroll: true });
    }
}

// ---- テスト送信 ----
const testDialogOpen = ref(false);
const testQuery = ref('');
const testContacts = ref([]);
const testTarget = ref(null);
const testSearching = ref(false);
const testSending = ref(false);
const testResult = ref('');
const testResultOk = ref(false);

let searchTimer = null;

function openTestSend() {
    testDialogOpen.value = true;
    testResult.value = '';
    testTarget.value = null;
}

function searchTestContacts() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        if (!testQuery.value.trim()) {
            testContacts.value = [];
            return;
        }
        testSearching.value = true;
        try {
            const { data } = await axios.get(route('admin.line-broadcasts.contact-search'), {
                params: { q: testQuery.value },
            });
            testContacts.value = data.contacts || [];
        } catch {
            testContacts.value = [];
        } finally {
            testSearching.value = false;
        }
    }, 300);
}

async function doTestSend() {
    if (!testTarget.value || !props.broadcast) return;
    testSending.value = true;
    testResult.value = '';
    try {
        await axios.post(route('admin.line-broadcasts.test-send', props.broadcast.id), {
            contact_id: testTarget.value.id,
        });
        testResultOk.value = true;
        testResult.value = `${testTarget.value.name} さんにテスト送信しました。LINEでご確認ください。`;
    } catch (e) {
        testResultOk.value = false;
        testResult.value = e.response?.data?.message || 'テスト送信に失敗しました。';
    } finally {
        testSending.value = false;
    }
}
</script>
