<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useAdminNav, isItemActive } from '@/composables/useAdminNav.js';
import { PanelLeftClose, PanelLeft } from 'lucide-vue-next';

const props = defineProps({
    collapsed: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-collapsed']);

const page = usePage();
const nav = useAdminNav();

const user = computed(() => page.props.auth?.user || {});

const filteredGroups = computed(() => {
    return nav
        .filter((g) => !g.permission || user.value[g.permission])
        .map((g) => ({
            ...g,
            items: g.items.filter((i) => !i.permission || user.value[i.permission]),
        }))
        .filter((g) => g.items.length > 0);
});

const safeRoute = (name, params) => {
    try {
        return route(name, params || {});
    } catch {
        return '#';
    }
};
</script>

<template>
    <aside
        :class="[
            'flex flex-col bg-sumi-900 dark:bg-sumi-950 text-sumi-100 transition-all duration-200',
            'border-r border-sumi-800 dark:border-sumi-900',
            collapsed ? 'w-16' : 'w-60',
        ]"
    >
        <!-- Brand -->
        <div class="h-14 flex items-center justify-between px-4 border-b border-sumi-800 dark:border-sumi-900 flex-shrink-0">
            <div v-if="!collapsed" class="font-serif text-base tracking-widest text-unohana-100">
                京呉服平田
            </div>
            <div v-else class="font-serif text-lg text-unohana-100 w-full text-center">京</div>
            <button
                type="button"
                class="p-1 rounded hover:bg-sumi-800 text-sumi-300 hover:text-white transition-colors"
                :class="collapsed ? 'hidden' : ''"
                @click="emit('toggle-collapsed')"
                :aria-label="collapsed ? 'サイドバーを開く' : 'サイドバーを閉じる'"
            >
                <PanelLeftClose :size="16" />
            </button>
        </div>

        <!-- Nav -->
        <nav class="flex-1 overflow-y-auto py-3 space-y-5">
            <div v-for="g in filteredGroups" :key="g.group">
                <div v-if="!collapsed" class="px-4 mb-1 text-[10px] uppercase tracking-widest text-sumi-400 font-semibold">
                    {{ g.group }}
                </div>
                <ul class="space-y-0.5">
                    <li v-for="item in g.items" :key="item.route">
                        <Link
                            :href="safeRoute(item.route, item.routeParams)"
                            :class="[
                                'relative flex items-center gap-2.5 rounded mx-2 text-sm transition-colors group',
                                collapsed ? 'justify-center px-2 py-2' : 'px-2.5 py-2',
                                isItemActive(item)
                                    ? 'bg-sumi-800 text-white'
                                    : 'text-sumi-200 hover:bg-sumi-800/70 hover:text-white',
                            ]"
                            :title="collapsed ? item.label : undefined"
                        >
                            <span
                                v-if="isItemActive(item)"
                                class="absolute -left-2 top-1 bottom-1 w-0.5 rounded-r-full bg-unohana-300"
                                aria-hidden="true"
                            />
                            <component :is="item.icon" :size="16" class="flex-shrink-0" :class="isItemActive(item) ? 'text-unohana-200' : ''" />
                            <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Collapsed toggle (bottom) -->
        <div v-if="collapsed" class="border-t border-sumi-800 dark:border-sumi-900 p-2">
            <button
                type="button"
                class="w-full p-2 rounded hover:bg-sumi-800 text-sumi-300 hover:text-white transition-colors flex items-center justify-center"
                @click="emit('toggle-collapsed')"
                aria-label="サイドバーを開く"
            >
                <PanelLeft :size="16" />
            </button>
        </div>
    </aside>
</template>
