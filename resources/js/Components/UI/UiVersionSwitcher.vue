<script setup>
import { useUiVersion } from '@/Composables/useUiVersion';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'button' }, // 'button' | 'compact'
});

const { isLegacy, setVersion } = useUiVersion();

const switchTo = (target) => {
    setVersion(target);
    // Cookieが書き込まれた直後にリロードして、サーバ側でviewFor()が新しいCookieを参照する
    router.reload({ preserveScroll: false, preserveState: false });
    // Inertiaのreloadでは表示用Vueファイルが切り替わらないのでフルリロードする
    setTimeout(() => window.location.reload(), 50);
};
</script>

<template>
    <div :class="variant === 'compact' ? 'inline-flex' : 'inline-flex items-center gap-2'">
        <span v-if="variant !== 'compact'" class="text-xs text-gray-500">UI:</span>
        <div class="inline-flex rounded-md border border-gray-300 overflow-hidden text-xs">
            <button
                type="button"
                @click="switchTo('legacy')"
                :class="[
                    'px-2.5 py-1 transition',
                    isLegacy ? 'bg-gray-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50',
                ]"
                title="旧UIへ切替（端末ごと・localStorage保持）"
            >旧UI</button>
            <button
                type="button"
                @click="switchTo('modern')"
                :class="[
                    'px-2.5 py-1 transition border-l border-gray-300',
                    !isLegacy ? 'bg-gray-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50',
                ]"
                title="新UIへ切替（端末ごと・localStorage保持）"
            >新UI</button>
        </div>
    </div>
</template>
