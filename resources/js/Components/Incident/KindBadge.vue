<script setup>
import { UiBadge } from '@/Components/UI';
import { KIND_VARIANT, CATEGORY_VARIANT } from '@/composables/useIncidentFlow.js';

const props = defineProps({
    value: { type: String, default: '' },
    type: { type: String, default: 'kind' }, // kind | category
});
const variant = () => (props.type === 'category' ? CATEGORY_VARIANT : KIND_VARIANT)[props.value] || 'neutral';
</script>

<template>
    <UiBadge v-if="value" :variant="variant()">{{ value }}</UiBadge>
</template>
