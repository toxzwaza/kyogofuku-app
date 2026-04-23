<script setup>
defineProps({
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    for: { type: String, default: null },
});
</script>

<template>
    <div class="space-y-1.5">
        <label v-if="label" :for="for" class="block text-xs font-medium text-brand-text-muted">
            {{ label }}
            <span v-if="required" class="text-brand-danger ml-0.5">*</span>
        </label>
        <slot />
        <p v-if="error" class="text-xs text-brand-danger">{{ error }}</p>
        <p v-else-if="hint" class="text-xs text-brand-text-subtle">{{ hint }}</p>
    </div>
</template>
