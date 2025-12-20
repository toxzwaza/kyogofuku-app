<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps(['href', 'active']);

const classes = computed(() =>
    props.active
        ? 'theme-responsive-nav-link-active'
        : 'block w-full pl-3 pr-4 py-2 border-l-4 border-transparent text-left text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300 transition duration-150 ease-in-out'
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>

<style scoped>
.theme-responsive-nav-link-active {
    display: block;
    width: 100%;
    padding-left: 0.75rem;
    padding-right: 1rem;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
    border-left-width: 5px;
    border-color: var(--theme-color) !important;
    text-align: left;
    font-size: 1rem;
    font-weight: 600;
    color: rgb(55, 65, 81) !important;
    background-color: var(--theme-color-15) !important;
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.theme-responsive-nav-link-active:focus {
    outline: none;
    color: rgb(55, 65, 81) !important;
    background-color: var(--theme-color-20) !important;
    border-color: var(--theme-color) !important;
}
</style>
