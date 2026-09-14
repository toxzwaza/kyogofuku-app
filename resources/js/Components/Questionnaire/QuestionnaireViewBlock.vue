<script setup>
import { ref, computed } from 'vue';
import { UiCard, UiButton, UiBadge } from '@/Components/UI';
import { ClipboardList, Printer } from 'lucide-vue-next';

/**
 * 振袖アンケートの閲覧専用ブロック（顧客詳細用）。
 * 登録・編集は予約詳細の「写真・アンケート」タブで行う。
 */
const props = defineProps({
    questionnaire: { type: Object, default: null },
    /** スキャン済み印刷のURL（admin.customers.questionnaire.print?mode=scan） */
    printScanUrl: { type: String, default: null },
});

const previewUrl = ref(null);

const hasAnyScan = computed(() => !!(props.questionnaire?.page1_url || props.questionnaire?.page2_url));

// 表示する画像URL（写真合成済みを優先）
function pageDisplayUrl(page) {
    const composed = props.questionnaire?.[`composed_page${page}_url`];
    return composed || props.questionnaire?.[`page${page}_url`];
}

function openPrintScan() {
    if (props.printScanUrl) window.open(props.printScanUrl, '_blank');
}
</script>

<template>
    <UiCard variant="default" padding="lg">
        <template #header>
            <div class="flex items-center justify-between">
                <h3 class="font-serif text-base font-semibold flex items-center gap-2 text-brand-text">
                    <ClipboardList :size="15" class="text-brand-primary" />
                    振袖アンケート
                </h3>
                <UiButton
                    v-if="hasAnyScan && printScanUrl"
                    variant="ghost"
                    size="sm"
                    @click="openPrintScan"
                >
                    <Printer :size="14" /> スキャン済みを印刷
                </UiButton>
            </div>
        </template>

        <p class="text-xs text-brand-text-muted mb-3">
            アンケートの登録・編集は、予約詳細の「写真・アンケート」タブから行えます。
        </p>

        <div class="grid grid-cols-2 gap-4">
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
                <img
                    v-if="questionnaire?.[`page${page}_url`]"
                    :src="pageDisplayUrl(page)"
                    class="w-full h-36 object-contain bg-brand-surface-2 rounded cursor-pointer"
                    :alt="`アンケート${page}ページ目`"
                    @click="previewUrl = pageDisplayUrl(page)"
                >
                <div
                    v-else
                    class="w-full h-36 border-2 border-dashed border-brand-border rounded flex items-center justify-center text-xs text-brand-text-muted"
                >
                    未取込
                </div>
            </div>
        </div>

        <!-- 拡大プレビュー -->
        <div
            v-if="previewUrl"
            class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-6 cursor-pointer"
            @click="previewUrl = null"
        >
            <img :src="previewUrl" class="max-w-full max-h-full rounded shadow-2xl" alt="プレビュー">
        </div>
    </UiCard>
</template>
