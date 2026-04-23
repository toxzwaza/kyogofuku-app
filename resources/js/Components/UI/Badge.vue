<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'neutral' }, // primary | accent | success | warning | danger | neutral
    size: { type: String, default: 'md' },          // sm | md
    dot: { type: Boolean, default: false },
});

const variantClasses = {
    primary: 'bg-ai-100 text-ai-700 dark:bg-ai-900 dark:text-ai-200',
    accent:  'bg-enji-100 text-enji-700 dark:bg-enji-900 dark:text-enji-200',
    success: 'bg-uguisu-100 text-uguisu-700 dark:bg-uguisu-900 dark:text-uguisu-200',
    warning: 'bg-natane-100 text-natane-700 dark:bg-natane-900 dark:text-natane-200',
    danger:  'bg-akane-100 text-akane-700 dark:bg-akane-900 dark:text-akane-200',
    neutral: 'bg-sumi-100 text-sumi-700 dark:bg-sumi-800 dark:text-sumi-200',
};

const dotClasses = {
    primary: 'bg-ai-500',
    accent:  'bg-enji-500',
    success: 'bg-uguisu-500',
    warning: 'bg-natane-500',
    danger:  'bg-akane-500',
    neutral: 'bg-sumi-500',
};

const sizeClasses = {
    sm: 'text-[10px] px-1.5 py-0.5 gap-1',
    md: 'text-xs px-2 py-0.5 gap-1.5',
};

const classes = computed(() => [
    'inline-flex items-center rounded-full font-semibold',
    variantClasses[props.variant] || variantClasses.neutral,
    sizeClasses[props.size] || sizeClasses.md,
]);
</script>

<template>
    <span :class="classes">
        <span v-if="dot" class="w-1.5 h-1.5 rounded-full" :class="dotClasses[variant] || dotClasses.neutral" />
        <slot />
    </span>
</template>
