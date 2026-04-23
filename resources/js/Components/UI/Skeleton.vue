<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'rect' }, // rect | text | circle
    width: { type: String, default: '100%' },
    height: { type: String, default: '16px' },
    rounded: { type: String, default: 'soft' }, // soft | full | none
});

const shape = computed(() => {
    if (props.variant === 'circle') return 'rounded-full';
    if (props.rounded === 'full') return 'rounded-full';
    if (props.rounded === 'none') return '';
    return 'rounded-soft';
});

const style = computed(() => ({
    width: props.variant === 'circle' ? props.height : props.width,
    height: props.height,
}));
</script>

<template>
    <span :class="['inline-block bg-brand-surface-2 animate-pulse', shape]" :style="style" />
</template>
