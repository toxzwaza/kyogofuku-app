<template>
    <svg
        class="seven-segment"
        viewBox="0 0 30 44"
        xmlns="http://www.w3.org/2000/svg"
    >
        <!-- Segment A - top horizontal -->
        <rect :class="{ on: segments.a }" x="5" y="0" width="20" height="4" rx="0.8" />
        <!-- Segment B - top-right vertical -->
        <rect :class="{ on: segments.b }" x="26" y="4" width="4" height="16" rx="0.8" />
        <!-- Segment C - bottom-right vertical -->
        <rect :class="{ on: segments.c }" x="26" y="24" width="4" height="16" rx="0.8" />
        <!-- Segment D - bottom horizontal -->
        <rect :class="{ on: segments.d }" x="5" y="40" width="20" height="4" rx="0.8" />
        <!-- Segment E - bottom-left vertical -->
        <rect :class="{ on: segments.e }" x="0" y="24" width="4" height="16" rx="0.8" />
        <!-- Segment F - top-left vertical -->
        <rect :class="{ on: segments.f }" x="0" y="4" width="4" height="16" rx="0.8" />
        <!-- Segment G - middle horizontal -->
        <rect :class="{ on: segments.g }" x="5" y="20" width="20" height="4" rx="0.8" />
    </svg>
</template>

<script setup>
import { computed } from 'vue';

// 7-segment mapping: a=top, b=top-right, c=bottom-right, d=bottom, e=bottom-left, f=top-left, g=middle
const SEGMENT_MAP = {
    '0': { a: 1, b: 1, c: 1, d: 1, e: 1, f: 1, g: 0 },
    '1': { a: 0, b: 1, c: 1, d: 0, e: 0, f: 0, g: 0 },
    '2': { a: 1, b: 1, c: 0, d: 1, e: 1, f: 0, g: 1 },
    '3': { a: 1, b: 1, c: 1, d: 1, e: 0, f: 0, g: 1 },
    '4': { a: 0, b: 1, c: 1, d: 0, e: 0, f: 1, g: 1 },
    '5': { a: 1, b: 0, c: 1, d: 1, e: 0, f: 1, g: 1 },
    '6': { a: 1, b: 0, c: 1, d: 1, e: 1, f: 1, g: 1 },
    '7': { a: 1, b: 1, c: 1, d: 0, e: 0, f: 0, g: 0 },
    '8': { a: 1, b: 1, c: 1, d: 1, e: 1, f: 1, g: 1 },
    '9': { a: 1, b: 1, c: 1, d: 1, e: 0, f: 1, g: 1 },
};

const props = defineProps({
    digit: { type: String, default: '0' },
});

const segments = computed(() => {
    const map = SEGMENT_MAP[props.digit] || SEGMENT_MAP['0'];
    return { a: !!map.a, b: !!map.b, c: !!map.c, d: !!map.d, e: !!map.e, f: !!map.f, g: !!map.g };
});
</script>

<style scoped>
.seven-segment {
    width: 100%;
    height: 100%;
    display: block;
}

.seven-segment rect {
    fill: rgba(5, 150, 105, 0.1);
    transition: fill 0.05s ease;
}

.seven-segment rect.on {
    fill: #059669;
}
</style>
