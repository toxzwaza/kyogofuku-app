<script setup>
import { computed } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: Array, default: () => [] }, // [{value, label}] | ['a','b']
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // sm | md | lg
});

defineEmits(['update:modelValue']);

const normalizedOptions = computed(() =>
    props.options.map((o) => (typeof o === 'object' ? o : { value: o, label: o }))
);

const sizeClasses = {
    sm: 'text-xs h-8 pl-2.5 pr-7',
    md: 'text-sm h-9 pl-3 pr-8',
    lg: 'text-base h-11 pl-4 pr-10',
};

const classes = computed(() => [
    'appearance-none block w-full rounded-soft bg-brand-surface text-brand-text',
    'border transition-colors',
    'focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-offset-brand-bg',
    props.error
        ? 'border-brand-danger focus:ring-brand-danger'
        : 'border-brand-border-strong focus:ring-brand-primary focus:border-brand-primary',
    props.disabled ? 'opacity-60 bg-brand-surface-2 cursor-not-allowed' : '',
    sizeClasses[props.size] || sizeClasses.md,
]);
</script>

<template>
    <div class="relative inline-block w-full">
        <select
            :value="modelValue"
            :disabled="disabled"
            :class="classes"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option v-if="placeholder" value="" disabled hidden>{{ placeholder }}</option>
            <option v-for="opt in normalizedOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>
        <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-brand-text-muted" :size="16" />
    </div>
</template>
