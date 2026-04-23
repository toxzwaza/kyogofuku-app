<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number, null], default: null },
    icon: { type: [Object, Function], default: null },
    /** 1 (default) | 2 | 3 */
    span: { type: Number, default: 1 },
    /** 値を大きく強調表示 */
    highlight: { type: Boolean, default: false },
    /** 値がコピーしやすい等幅フォントに */
    mono: { type: Boolean, default: false },
});

const wrapperCls = computed(() => [
    'rounded-soft border border-brand-border bg-brand-surface p-3 min-w-0',
    props.span === 2 ? 'md:col-span-2' : '',
    props.span === 3 ? 'md:col-span-3' : '',
]);

const valueCls = computed(() => [
    'text-brand-text break-words',
    props.highlight ? 'text-lg font-semibold font-serif' : 'text-sm font-medium',
    props.mono ? 'font-mono text-xs' : '',
]);
</script>

<template>
    <div :class="wrapperCls">
        <div class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-brand-text-muted mb-1">
            <component v-if="icon" :is="icon" :size="11" class="text-brand-primary/70 flex-shrink-0" />
            <span class="truncate">{{ label }}</span>
        </div>
        <div :class="valueCls">
            <slot>{{ value || '—' }}</slot>
        </div>
    </div>
</template>
