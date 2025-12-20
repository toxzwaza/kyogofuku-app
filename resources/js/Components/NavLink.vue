<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps(['href', 'active']);

const classes = computed(() =>
    props.active
        ? 'theme-nav-link-active'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out'
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>

<style scoped>
.theme-nav-link-active {
    display: inline-flex;
    align-items: center;
    padding-left: 0.25rem;
    padding-top: 0.25rem;
    border-bottom-width: 3px;
    border-color: var(--theme-color) !important;
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.25rem;
    color: rgb(17, 24, 39) !important;
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.theme-nav-link-active:focus {
    outline: none;
    border-color: var(--theme-color) !important;
}
</style>
