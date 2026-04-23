<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'default' }, // default | elevated | outlined
    padding: { type: String, default: 'md' },       // none | sm | md | lg
});

const variantClasses = {
    default:  'bg-brand-surface border border-brand-border shadow-soft-sm',
    elevated: 'bg-brand-surface shadow-soft',
    outlined: 'bg-brand-surface border border-brand-border',
};

const paddingClasses = {
    none: '',
    sm: 'p-3',
    md: 'p-5',
    lg: 'p-7',
};

const classes = computed(() => [
    'rounded-soft-lg text-brand-text',
    variantClasses[props.variant] || variantClasses.default,
]);

const innerClasses = computed(() => paddingClasses[props.padding] || paddingClasses.md);
</script>

<template>
    <div :class="classes">
        <div v-if="$slots.header" class="px-5 pt-4 pb-3 border-b border-brand-border">
            <slot name="header" />
        </div>
        <div :class="innerClasses">
            <slot />
        </div>
        <div v-if="$slots.footer" class="px-5 py-3 border-t border-brand-border bg-brand-surface-2 rounded-b-soft-lg">
            <slot name="footer" />
        </div>
    </div>
</template>
