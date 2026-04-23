<script setup>
import { computed } from 'vue';

const props = defineProps({
    text: { type: String, required: true },
    placement: { type: String, default: 'top' }, // top | bottom | left | right
});

const placementClasses = {
    top:    'bottom-full left-1/2 -translate-x-1/2 mb-1.5',
    bottom: 'top-full left-1/2 -translate-x-1/2 mt-1.5',
    left:   'right-full top-1/2 -translate-y-1/2 mr-1.5',
    right:  'left-full top-1/2 -translate-y-1/2 ml-1.5',
};

const arrowClasses = {
    top:    'top-full left-1/2 -translate-x-1/2 border-t-sumi-900 border-x-transparent border-b-0',
    bottom: 'bottom-full left-1/2 -translate-x-1/2 border-b-sumi-900 border-x-transparent border-t-0',
    left:   'left-full top-1/2 -translate-y-1/2 border-l-sumi-900 border-y-transparent border-r-0',
    right:  'right-full top-1/2 -translate-y-1/2 border-r-sumi-900 border-y-transparent border-l-0',
};

const tipCls = computed(() => [
    'pointer-events-none absolute z-30 px-2 py-1 text-[11px] font-medium whitespace-nowrap',
    'bg-sumi-900 text-sumi-50 rounded shadow-soft-sm',
    'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity duration-100',
    placementClasses[props.placement] || placementClasses.top,
]);

const arrCls = computed(() => [
    'absolute w-0 h-0 border-4',
    arrowClasses[props.placement] || arrowClasses.top,
    'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity duration-100',
]);
</script>

<template>
    <span class="group relative inline-flex">
        <slot />
        <span :class="tipCls">{{ text }}</span>
        <span :class="arrCls" />
    </span>
</template>
