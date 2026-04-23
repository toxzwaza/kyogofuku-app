<script setup>
import { computed, useSlots } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // sm | md | lg
});

defineEmits(['update:modelValue']);

const slots = useSlots();

const sizeClasses = {
    sm: 'text-xs h-8',
    md: 'text-sm h-9',
    lg: 'text-base h-11',
};

const wrapperClasses = computed(() => [
    'flex items-center gap-2 rounded-soft border bg-brand-surface text-brand-text',
    'transition-colors',
    'focus-within:ring-2 focus-within:ring-offset-1 focus-within:ring-offset-brand-bg',
    props.error
        ? 'border-brand-danger focus-within:ring-brand-danger'
        : 'border-brand-border-strong focus-within:ring-brand-primary focus-within:border-brand-primary',
    props.disabled ? 'opacity-60 bg-brand-surface-2 cursor-not-allowed' : '',
    sizeClasses[props.size] || sizeClasses.md,
    slots.leading || slots.trailing ? 'px-3' : '',
]);

const inputClasses = computed(() => [
    'flex-1 min-w-0 bg-transparent outline-none placeholder:text-brand-text-subtle',
    slots.leading || slots.trailing ? '' : 'px-3',
    'border-0 focus:ring-0 py-0',
]);
</script>

<template>
    <div :class="wrapperClasses">
        <span v-if="$slots.leading" class="text-brand-text-muted flex-shrink-0">
            <slot name="leading" />
        </span>
        <input
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :readonly="readonly"
            :class="inputClasses"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <span v-if="$slots.trailing" class="text-brand-text-muted flex-shrink-0">
            <slot name="trailing" />
        </span>
    </div>
</template>
