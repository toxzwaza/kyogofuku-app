<script setup>
import { computed } from 'vue';
import { UiBadge } from '@/Components/UI';
import { STATUS } from '@/composables/useIncidentFlow.js';

const props = defineProps({
    value: { type: String, default: 'open' },
    size: { type: String, default: 'md' },
});
const s = computed(() => STATUS[props.value] || STATUS.open);
</script>

<template>
    <UiBadge :variant="s.variant" :size="size" dot>{{ s.label }}</UiBadge>
</template>
