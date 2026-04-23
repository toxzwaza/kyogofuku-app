<script setup>
import { MenuItem } from '@headlessui/vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    href: { type: String, default: null },
    as: { type: String, default: null }, // 'button' | 'a' など
    disabled: { type: Boolean, default: false },
    danger: { type: Boolean, default: false },
});

const baseCls = computed(() => [
    'w-full text-left flex items-center gap-2 px-3 py-2 text-sm transition-colors',
    props.danger ? 'text-brand-danger' : 'text-brand-text',
]);

const activeCls = 'bg-brand-surface-2';
const disabledCls = 'opacity-50 cursor-not-allowed';
</script>

<template>
    <MenuItem v-slot="{ active, disabled: di }" :disabled="disabled">
        <Link
            v-if="href"
            :href="href"
            :class="[...baseCls, active && !di ? activeCls : '', disabled || di ? disabledCls : 'hover:bg-brand-surface-2']"
        >
            <slot />
        </Link>
        <button
            v-else
            type="button"
            :disabled="disabled"
            :class="[...baseCls, active && !di ? activeCls : '', disabled || di ? disabledCls : 'hover:bg-brand-surface-2']"
        >
            <slot />
        </button>
    </MenuItem>
</template>
