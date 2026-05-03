<script setup>
import { useUiVersion } from '@/Composables/useUiVersion';

defineProps({
    variant: { type: String, default: 'button' }, // 'button' | 'compact'
});

const { isLegacy, setVersion } = useUiVersion();

const switchTo = (target) => {
    setVersion(target);
    // Cookie 反映のためフルリロード（Inertia のSPA遷移ではVueファイル種別が切り替わらない）
    window.location.reload();
};
</script>

<template>
    <div :class="variant === 'compact' ? 'inline-flex shrink-0' : 'inline-flex items-center gap-2 shrink-0'">
        <span v-if="variant !== 'compact'" class="text-xs text-gray-500 whitespace-nowrap">UI:</span>
        <div class="inline-flex rounded-md border border-gray-300 overflow-hidden text-xs whitespace-nowrap">
            <button
                type="button"
                @click="switchTo('legacy')"
                :class="[
                    'px-2.5 py-1 transition whitespace-nowrap',
                    isLegacy ? 'bg-gray-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50',
                ]"
                title="旧UIへ切替（端末ごと・localStorage保持）"
            >旧UI</button>
            <button
                type="button"
                @click="switchTo('modern')"
                :class="[
                    'px-2.5 py-1 transition border-l border-gray-300 whitespace-nowrap',
                    !isLegacy ? 'bg-gray-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50',
                ]"
                title="新UIへ切替（端末ごと・localStorage保持）"
            >新UI</button>
        </div>
    </div>
</template>
