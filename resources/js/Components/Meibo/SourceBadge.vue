<template>
    <span class="inline-flex flex-wrap items-center gap-1">
        <UiBadge v-if="r11" variant="accent" size="sm" title="R11 成人名簿（G_R11）から取り込んだ人">R11</UiBadge>
        <span v-if="others" class="text-[11px] text-brand-text-subtle tabular-nums" :title="sourceVersions">
            {{ others }}<template v-if="mergeCount > 1">×{{ mergeCount }}</template>
        </span>
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { UiBadge } from '@/Components/UI';
import { parseSources, R11_SOURCE_CODE } from '@/services/meibo/constants.js';

/** 統合元版（出所）。R11 成人名簿を含む人は「R11」のバッジを出す */
const props = defineProps({
    sourceVersions: { type: String, default: '' },
    mergeCount: { type: Number, default: 1 },
});

const sources = computed(() => parseSources(props.sourceVersions));
const r11 = computed(() => sources.value.includes(R11_SOURCE_CODE));
// 残りは系統の頭文字だけ（G_R07 → G）。元の名簿管理システムの「版」と同じ見せ方
const others = computed(() =>
    [...new Set(sources.value.filter((s) => s !== R11_SOURCE_CODE).map((s) => s.split('_')[0]))].join('/'),
);
</script>
