<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Search, ChevronDown, LogOut, User as UserIcon, Menu, ALargeSmall, Check,
} from 'lucide-vue-next';
import { UiDropdown, UiDropdownItem } from '@/Components/UI';
import { useFontScale } from '@/composables/useFontScale.js';

defineProps({
    mobileMenuOpen: { type: Boolean, default: false },
});
defineEmits(['toggle-mobile-menu', 'open-command-palette']);

const page = usePage();
const user = computed(() => page.props.auth?.user || {});

// 文字サイズ切替（標準／大きめ／特大）
const { scale, setScale, levels: fontLevels } = useFontScale();

const safeRoute = (name) => {
    try { return route(name); } catch { return '#'; }
};

const logout = () => {
    router.post(safeRoute('logout'));
};
</script>

<template>
    <header class="h-14 flex-shrink-0 bg-brand-surface border-b border-brand-border flex items-center gap-3 px-4 sm:px-6">
        <!-- Mobile: hamburger -->
        <button
            type="button"
            class="lg:hidden -ml-1 p-2 rounded hover:bg-brand-surface-2 text-brand-text-muted"
            @click="$emit('toggle-mobile-menu')"
            aria-label="メニューを開く"
        >
            <Menu :size="18" />
        </button>

        <!-- Search -->
        <button
            type="button"
            class="flex items-center gap-2 flex-1 max-w-3xl px-3 h-9 rounded-soft bg-brand-surface-2 border border-brand-border hover:bg-brand-border/60 text-brand-text-muted text-sm transition-colors"
            @click="$emit('open-command-palette')"
        >
            <Search :size="15" class="flex-shrink-0" />
            <span class="flex-1 text-left">予約・顧客・画面を検索…</span>
            <kbd class="hidden sm:inline text-[10px] px-1.5 py-0.5 rounded border border-brand-border-strong bg-brand-surface text-brand-text-subtle">⌘K</kbd>
        </button>

        <div class="flex-1 hidden lg:block" />

        <!-- Actions -->
        <div class="flex items-center gap-1">
            <!-- 文字サイズ切替（アクセシビリティ） -->
            <UiDropdown align="right">
                <template #trigger>
                    <button
                        type="button"
                        class="flex items-center gap-1 px-2 py-1.5 rounded hover:bg-brand-surface-2 text-brand-text-muted hover:text-brand-text transition-colors"
                        aria-label="文字サイズ"
                        title="文字サイズ"
                    >
                        <ALargeSmall :size="18" />
                        <span class="hidden sm:inline text-sm">文字サイズ</span>
                    </button>
                </template>
                <UiDropdownItem
                    v-for="lv in fontLevels"
                    :key="lv.key"
                    @click="setScale(lv.key)"
                >
                    <Check :size="14" :class="scale === lv.key ? 'opacity-100' : 'opacity-0'" />
                    <span>{{ lv.label }}</span>
                </UiDropdownItem>
            </UiDropdown>

            <UiDropdown align="right">
                <template #trigger>
                    <button
                        type="button"
                        class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-brand-surface-2 text-brand-text transition-colors"
                    >
                        <span class="w-7 h-7 rounded-full bg-ai-600 text-white text-xs flex items-center justify-center font-semibold">
                            {{ (user.name || '?').charAt(0) }}
                        </span>
                        <span class="hidden md:inline text-sm">{{ user.name || 'ゲスト' }}</span>
                        <ChevronDown :size="13" class="text-brand-text-muted hidden md:inline" />
                    </button>
                </template>
                <UiDropdownItem :href="safeRoute('profile.edit')">
                    <UserIcon :size="14" /><span>プロフィール</span>
                </UiDropdownItem>
                <UiDropdownItem @click="logout">
                    <LogOut :size="14" /><span>ログアウト</span>
                </UiDropdownItem>
            </UiDropdown>
        </div>
    </header>
</template>
