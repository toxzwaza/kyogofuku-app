<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminSidebar from '@/Components/Admin/AdminSidebar.vue';
import AdminTopbar from '@/Components/Admin/AdminTopbar.vue';
import CommandPalette from '@/Components/Admin/CommandPalette.vue';
import UiToastContainer from '@/Components/UI/ToastContainer.vue';
import UiBreadcrumb from '@/Components/UI/Breadcrumb.vue';
import { useDarkMode } from '@/composables/useDarkMode.js';
import { useCommandPalette } from '@/composables/useCommandPalette.js';

defineProps({
    breadcrumb: { type: Array, default: () => [] },
});

const STORAGE_KEY = 'kyogofuku-sidebar-collapsed';

const collapsed = ref(false);
const mobileOpen = ref(false);

onMounted(() => {
    try {
        collapsed.value = localStorage.getItem(STORAGE_KEY) === '1';
    } catch { /* ignore */ }
});

const toggleCollapsed = () => {
    collapsed.value = !collapsed.value;
    try { localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0'); } catch {}
};

// Initialize dark mode on layout mount
useDarkMode();

// Command palette (Cmd+K)
const { show: openPalette } = useCommandPalette();
</script>

<template>
    <div class="min-h-screen flex bg-brand-bg text-brand-text">
        <!-- Desktop sidebar -->
        <div class="hidden lg:block flex-shrink-0 sticky top-0 h-screen">
            <AdminSidebar :collapsed="collapsed" @toggle-collapsed="toggleCollapsed" />
        </div>

        <!-- Mobile drawer sidebar -->
        <Teleport to="body">
            <transition
                enter-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-100"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="mobileOpen"
                    class="lg:hidden fixed inset-0 bg-sumi-950/50 backdrop-blur-sm z-40"
                    @click="mobileOpen = false"
                />
            </transition>
            <transition
                enter-active-class="transition-transform duration-200 ease-out"
                enter-from-class="-translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-150 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="-translate-x-full"
            >
                <div v-if="mobileOpen" class="lg:hidden fixed inset-y-0 left-0 z-50">
                    <AdminSidebar :collapsed="false" @toggle-collapsed="mobileOpen = false" />
                </div>
            </transition>
        </Teleport>

        <!-- Main area -->
        <div class="flex-1 min-w-0 flex flex-col">
            <AdminTopbar
                @toggle-mobile-menu="mobileOpen = !mobileOpen"
                @open-command-palette="openPalette"
            />

            <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6">
                <UiBreadcrumb v-if="breadcrumb.length" :items="breadcrumb" class="mb-4" />
                <!-- 互換用: 旧AuthenticatedLayoutの #header スロットを受け取る -->
                <div v-if="$slots.header" class="mb-4">
                    <slot name="header" />
                </div>
                <slot />
            </main>
        </div>

        <UiToastContainer />
        <CommandPalette />
    </div>
</template>
