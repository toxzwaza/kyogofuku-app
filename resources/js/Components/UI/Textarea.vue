<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
    rows: { type: Number, default: 4 },
});

defineEmits(['update:modelValue']);

const classes = computed(() => [
    'block w-full px-3 py-2 text-sm rounded-soft bg-brand-surface text-brand-text',
    'border transition-colors placeholder:text-brand-text-subtle',
    'focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-offset-brand-bg',
    props.error
        ? 'border-brand-danger focus:ring-brand-danger'
        : 'border-brand-border-strong focus:ring-brand-primary focus:border-brand-primary',
    props.disabled ? 'opacity-60 bg-brand-surface-2 cursor-not-allowed' : '',
]);
</script>

<template>
    <textarea
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :rows="rows"
        :class="classes"
        @input="$emit('update:modelValue', $event.target.value)"
    />
</template>
