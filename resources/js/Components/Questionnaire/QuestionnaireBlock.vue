<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { UiCard, UiButton, UiBadge } from '@/Components/UI';
import { ClipboardList, Printer, Camera, Image as ImageIcon, Trash2 } from 'lucide-vue-next';
import QuestionnaireScanModal from './QuestionnaireScanModal.vue';
import QuestionnairePlacerModal from './QuestionnairePlacerModal.vue';

const props = defineProps({
    customer: { type: Object, required: true },
    questionnaire: { type: Object, default: null },
});

const showScanModal = ref(false);
const scanInitialPage = ref(1);
const showPlacerModal = ref(false);
const previewUrl = ref(null);

const hasAnyScan = computed(() => !!(props.questionnaire?.page1_url || props.questionnaire?.page2_url));

function printUrl(params = {}) {
    const query = new URLSearchParams(params).toString();
    return route('admin.customers.questionnaire.print', props.customer.id) + (query ? `?${query}` : '');
}

function openPrint(params = {}) {
    window.open(printUrl(params), '_blank');
}

function openScan(page) {
    scanInitialPage.value = page;
    showScanModal.value = true;
}

function deleteScan(page) {
    if (!confirm(`アンケート${page}ページ目のスキャンを削除しますか？`)) return;
    router.delete(
        route('admin.customers.questionnaire.scans.destroy', [props.customer.id, page]),
        { preserveScroll: true }
    );
}
</script>

<template>
    <UiCard variant="default" padding="lg">
        <template #header>
            <h3 class="font-serif text-base font-semibold flex items-center gap-2 text-brand-text">
                <ClipboardList :size="15" class="text-brand-primary" />
                振袖アンケート
            </h3>
        </template>

        <!-- 操作ボタン -->
        <div class="flex flex-wrap gap-2 mb-4">
            <UiButton variant="ghost" size="sm" @click="openPrint()">
                <Printer :size="14" /> 用紙を印刷
            </UiButton>
            <UiButton variant="ghost" size="sm" @click="openPrint({ blank: 1 })">
                <Printer :size="14" /> 空欄で印刷
            </UiButton>
            <UiButton v-if="hasAnyScan" variant="ghost" size="sm" @click="openPrint({ mode: 'scan' })">
                <Printer :size="14" /> スキャン済みを印刷
            </UiButton>
        </div>

        <!-- 取り込み状況 -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div
                v-for="page in [1, 2]"
                :key="page"
                class="border border-brand-border rounded-lg p-3"
            >
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-brand-text">{{ page }}ページ目</span>
                    <UiBadge v-if="questionnaire?.[`page${page}_url`]" variant="success">取込済</UiBadge>
                    <UiBadge v-else variant="neutral">未取込</UiBadge>
                </div>
                <template v-if="questionnaire?.[`page${page}_url`]">
                    <img
                        :src="questionnaire[`page${page}_url`]"
                        class="w-full h-36 object-contain bg-brand-surface-2 rounded cursor-pointer"
                        :alt="`アンケート${page}ページ目`"
                        @click="previewUrl = questionnaire[`page${page}_url`]"
                    >
                    <div class="flex gap-2 mt-2">
                        <UiButton variant="ghost" size="sm" @click="openScan(page)">
                            <Camera :size="13" /> 差し替え
                        </UiButton>
                        <UiButton variant="ghost" size="sm" @click="deleteScan(page)">
                            <Trash2 :size="13" /> 削除
                        </UiButton>
                    </div>
                </template>
                <template v-else>
                    <button
                        class="w-full h-36 border-2 border-dashed border-brand-border rounded flex flex-col items-center justify-center text-brand-text-muted hover:border-brand-primary hover:text-brand-primary"
                        @click="openScan(page)"
                    >
                        <Camera :size="22" class="mb-1" />
                        <span class="text-xs">カメラ・ファイルから取り込む</span>
                    </button>
                </template>
            </div>
        </div>

        <!-- 写真添付欄 -->
        <div class="border-t border-brand-border pt-3">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-brand-text flex items-center gap-1.5">
                    <ImageIcon :size="14" class="text-brand-primary" /> 写真添付欄（2ページ目）
                </span>
                <UiButton
                    variant="primary"
                    size="sm"
                    :disabled="!questionnaire?.page2_url"
                    @click="showPlacerModal = true"
                >
                    <ImageIcon :size="13" /> 写真を配置する
                </UiButton>
            </div>
            <p v-if="!questionnaire?.page2_url" class="text-xs text-brand-text-muted">
                2ページ目のスキャンを取り込むと、登録済みの顧客写真を写真添付欄に配置できます。
            </p>
            <img
                v-else-if="questionnaire?.composed_page2_url"
                :src="questionnaire.composed_page2_url"
                class="w-full max-w-xs h-44 object-contain bg-brand-surface-2 rounded cursor-pointer"
                alt="写真配置済み2ページ目"
                @click="previewUrl = questionnaire.composed_page2_url"
            >
            <p v-else class="text-xs text-brand-text-muted">写真はまだ配置されていません。</p>
        </div>

        <!-- 拡大プレビュー -->
        <div
            v-if="previewUrl"
            class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-6 cursor-pointer"
            @click="previewUrl = null"
        >
            <img :src="previewUrl" class="max-w-full max-h-full rounded shadow-2xl" alt="プレビュー">
        </div>

        <QuestionnaireScanModal
            :show="showScanModal"
            :customer="customer"
            :initial-page="scanInitialPage"
            @close="showScanModal = false"
        />
        <QuestionnairePlacerModal
            v-if="questionnaire?.page2_url"
            :show="showPlacerModal"
            :customer="customer"
            :questionnaire="questionnaire"
            @close="showPlacerModal = false"
        />
    </UiCard>
</template>
