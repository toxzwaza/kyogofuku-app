<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    icon: { type: [Object, Function], default: null },
    /** 1 | 2 (default) | 3 | 4 */
    cols: { type: Number, default: 2 },
    /** タイトル右側に置くアクセサリ（optional slot） */
    tight: { type: Boolean, default: false },
});

const gridCls = computed(() => {
    const base = 'grid gap-3';
    if (props.cols === 1) return `${base} grid-cols-1`;
    if (props.cols === 3) return `${base} grid-cols-1 md:grid-cols-2 lg:grid-cols-3`;
    if (props.cols === 4) return `${base} grid-cols-1 md:grid-cols-2 lg:grid-cols-4`;
    return `${base} grid-cols-1 md:grid-cols-2`;
});
</script>

<template>
    <section :class="tight ? '' : 'mb-6'">
        <header class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-2 min-w-0">
                <component v-if="icon" :is="icon" :size="16" class="text-brand-primary flex-shrink-0" />
                <h3 class="font-serif text-base font-semibold text-brand-text truncate">{{ title }}</h3>
            </div>
            <div v-if="$slots.actions" class="flex-shrink-0">
                <slot name="actions" />
            </div>
        </header>
        <div :class="gridCls">
            <slot />
        </div>
    </section>
</template>
