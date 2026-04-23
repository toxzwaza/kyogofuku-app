<script setup>
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';

defineProps({
    align: { type: String, default: 'right' }, // left | right
    width: { type: String, default: '192px' },
});
</script>

<template>
    <Menu as="div" class="relative inline-block text-left">
        <MenuButton as="template">
            <slot name="trigger" />
        </MenuButton>
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <MenuItems
                :class="[
                    'absolute z-20 mt-1 origin-top rounded-soft border border-brand-border bg-brand-surface shadow-soft-lg py-1 focus:outline-none',
                    align === 'right' ? 'right-0' : 'left-0',
                ]"
                :style="{ minWidth: width }"
            >
                <slot />
            </MenuItems>
        </transition>
    </Menu>
</template>
