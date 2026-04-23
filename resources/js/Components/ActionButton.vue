<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    Plus, Pencil, Trash2, Eye, ArrowLeft, Search, Save, Loader2,
} from 'lucide-vue-next';

/**
 * 旧 ActionButton の互換ラッパ。
 * 既存呼び出し（variant="create|edit|delete|detail|back|search|save|primary|secondary"）
 * をそのまま受け取り、ブランドトークンで統一したスタイルで描画する。
 * 新規コードでは `Components/UI/Button.vue (UiButton)` の利用を推奨。
 */
const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['create', 'edit', 'delete', 'detail', 'back', 'search', 'save', 'primary', 'secondary'].includes(v),
    },
    label: { type: String, default: '' },
    href:  { type: String, default: '' },
    type:  { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    loading:  { type: Boolean, default: false },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
});

const emit = defineEmits(['click']);

const onClick = (event) => {
    if (!props.href) emit('click', event);
};

const variantMap = {
    create:    { cls: 'bg-brand-primary text-brand-on-primary hover:bg-brand-primary-hover focus-visible:ring-brand-primary',    icon: Plus },
    edit:      { cls: 'bg-brand-primary text-brand-on-primary hover:bg-brand-primary-hover focus-visible:ring-brand-primary',    icon: Pencil },
    delete:    { cls: 'bg-brand-danger text-white hover:opacity-90 focus-visible:ring-brand-danger',                              icon: Trash2 },
    detail:    { cls: 'bg-brand-accent text-brand-on-accent hover:opacity-90 focus-visible:ring-brand-accent',                    icon: Eye },
    back:      { cls: 'bg-brand-surface-2 text-brand-text border border-brand-border-strong hover:bg-brand-border focus-visible:ring-brand-primary', icon: ArrowLeft },
    secondary: { cls: 'bg-brand-surface-2 text-brand-text border border-brand-border-strong hover:bg-brand-border focus-visible:ring-brand-primary', icon: null },
    search:    { cls: 'bg-brand-primary text-brand-on-primary hover:bg-brand-primary-hover focus-visible:ring-brand-primary',    icon: Search },
    save:      { cls: 'bg-brand-primary text-brand-on-primary hover:bg-brand-primary-hover focus-visible:ring-brand-primary',    icon: Save },
    primary:   { cls: 'bg-brand-primary text-brand-on-primary hover:bg-brand-primary-hover focus-visible:ring-brand-primary',    icon: null },
};

const cfg = computed(() => variantMap[props.variant] || variantMap.primary);

const sizeClasses = computed(() => ({
    sm: 'text-xs px-2.5 py-1.5 gap-1.5',
    md: 'text-sm px-3.5 py-2 gap-2',
    lg: 'text-base px-5 py-2.5 gap-2',
}[props.size] || 'text-sm px-3.5 py-2 gap-2'));

const iconSize = computed(() => (props.size === 'lg' ? 18 : 14));

const buttonClasses = computed(() => [
    'inline-flex items-center justify-center rounded-soft font-medium select-none',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-brand-bg',
    'disabled:opacity-50 disabled:cursor-not-allowed',
    'transition-all duration-150',
    cfg.value.cls,
    sizeClasses.value,
].join(' '));
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        :type="href ? undefined : type"
        :disabled="disabled || loading"
        :class="buttonClasses"
        @click="onClick"
    >
        <Loader2 v-if="loading" class="animate-spin" :size="iconSize" />
        <component v-else-if="cfg.icon" :is="cfg.icon" :size="iconSize" />
        <span v-if="label">{{ label }}</span>
        <slot v-else />
    </component>
</template>
