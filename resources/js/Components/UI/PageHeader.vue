<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between mb-6">
        <div class="min-w-0">
            <slot name="breadcrumb" />
            <h1 class="font-serif text-2xl font-semibold tracking-wide text-brand-text">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-brand-text-muted">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
