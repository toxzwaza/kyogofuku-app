<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | accent | ghost | subtle | danger | link
    size: { type: String, default: 'md' },          // sm | md | lg
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    href: { type: String, default: null },          // Inertia <Link>
    to: { type: String, default: null },            // external <a>
    block: { type: Boolean, default: false },
});

const variantClasses = {
    primary: 'bg-brand-primary text-brand-on-primary hover:bg-brand-primary-hover focus-visible:ring-brand-primary',
    accent:  'bg-brand-accent text-brand-on-accent hover:opacity-90 focus-visible:ring-brand-accent',
    ghost:   'bg-transparent text-brand-text border border-brand-border-strong hover:bg-brand-surface-2 focus-visible:ring-brand-primary',
    subtle:  'bg-brand-surface-2 text-brand-text hover:bg-brand-border focus-visible:ring-brand-primary',
    danger:  'bg-brand-danger text-white hover:opacity-90 focus-visible:ring-brand-danger',
    link:    'bg-transparent text-brand-primary hover:underline px-0 py-0 h-auto focus-visible:ring-brand-primary',
};

const sizeClasses = {
    sm: 'text-xs px-2.5 py-1.5 h-7 gap-1.5',
    md: 'text-sm px-3.5 py-2 h-9 gap-2',
    lg: 'text-base px-5 py-2.5 h-11 gap-2',
};

const classes = computed(() => [
    'inline-flex items-center justify-center rounded-soft font-medium',
    'transition-all duration-150 select-none',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-brand-bg',
    'disabled:opacity-50 disabled:cursor-not-allowed',
    variantClasses[props.variant] || variantClasses.primary,
    sizeClasses[props.size] || sizeClasses.md,
    props.block ? 'w-full' : '',
]);

const tag = computed(() => {
    if (props.href) return Link;
    if (props.to) return 'a';
    return 'button';
});
</script>

<template>
    <component
        :is="tag"
        :href="href || to"
        :type="tag === 'button' ? type : undefined"
        :disabled="tag === 'button' ? (disabled || loading) : undefined"
        :class="classes"
    >
        <Loader2 v-if="loading" class="animate-spin" :size="size === 'sm' ? 14 : 16" />
        <slot name="leading" />
        <slot />
        <slot name="trailing" />
    </component>
</template>
