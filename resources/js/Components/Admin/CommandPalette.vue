<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    Dialog as HDialog, DialogPanel,
    Combobox, ComboboxInput, ComboboxOptions, ComboboxOption,
    TransitionRoot, TransitionChild,
} from '@headlessui/vue';
import {
    Search, Loader2, CalendarCheck, User as UserIcon, ArrowRight, ChevronRight, CornerDownLeft,
} from 'lucide-vue-next';
import { useAdminNav } from '@/composables/useAdminNav.js';
import { useCommandPalette } from '@/composables/useCommandPalette.js';

const { open, hide } = useCommandPalette();
const page = usePage();

const query = ref('');
const loading = ref(false);
const serverResults = ref({ reservations: [], customers: [] });
let abortController = null;

// ナビゲーションからフラット化
const nav = useAdminNav();
const user = computed(() => page.props.auth?.user || {});
const flatNav = computed(() =>
    nav
        .filter((g) => !g.permission || user.value[g.permission])
        .flatMap((g) =>
            g.items
                .filter((i) => !i.permission || user.value[i.permission])
                .map((i) => ({ ...i, group: g.group }))
        )
);

const filteredNav = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return flatNav.value.slice(0, 8);
    return flatNav.value.filter((i) =>
        i.label.toLowerCase().includes(q) || i.group.toLowerCase().includes(q)
    ).slice(0, 8);
});

// サーバサイド検索（予約＆顧客）
watch(query, async (q) => {
    if (abortController) abortController.abort();
    if (!q || q.trim().length < 1) {
        serverResults.value = { reservations: [], customers: [] };
        return;
    }
    loading.value = true;
    abortController = new AbortController();
    try {
        const res = await fetch(`${route('admin.search')}?q=${encodeURIComponent(q)}`, {
            headers: { 'Accept': 'application/json' },
            signal: abortController.signal,
        });
        if (res.ok) {
            serverResults.value = await res.json();
        }
    } catch (e) {
        if (e.name !== 'AbortError') {
            console.error('[CommandPalette] search error', e);
        }
    } finally {
        loading.value = false;
    }
});

watch(open, (v) => {
    if (!v) {
        query.value = '';
        serverResults.value = { reservations: [], customers: [] };
    }
});

const fmtDateTime = (s) => {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    return `${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
};

const safeRoute = (name, params) => {
    try { return route(name, params); } catch { return '#'; }
};

// onSelect: Combobox の選択時に ナビゲート
const onSelect = (item) => {
    if (!item) return;
    hide();
    if (item.__type === 'nav') {
        router.visit(safeRoute(item.route));
    } else if (item.__type === 'reservation') {
        router.visit(safeRoute('admin.reservations.show', item.id));
    } else if (item.__type === 'customer') {
        router.visit(safeRoute('admin.customers.show', item.id));
    }
};

// 統合リスト（Combobox に渡す）
const combinedItems = computed(() => {
    const list = [];
    for (const n of filteredNav.value) list.push({ ...n, __type: 'nav' });
    for (const r of serverResults.value.reservations) list.push({ ...r, __type: 'reservation' });
    for (const c of serverResults.value.customers)   list.push({ ...c, __type: 'customer' });
    return list;
});

const noResults = computed(() =>
    query.value.trim().length > 0 &&
    !loading.value &&
    combinedItems.value.length === 0
);
</script>

<template>
    <TransitionRoot :show="open" as="template">
        <HDialog as="div" class="relative z-50" @close="hide">
            <TransitionChild
                as="template"
                enter="duration-150 ease-out" enter-from="opacity-0" enter-to="opacity-100"
                leave="duration-100 ease-in" leave-from="opacity-100" leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-sumi-950/60 backdrop-blur-sm" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-start justify-center pt-[10vh] p-4">
                    <TransitionChild
                        as="template"
                        enter="duration-200 ease-out"
                        enter-from="opacity-0 scale-95"
                        enter-to="opacity-100 scale-100"
                        leave="duration-100 ease-in"
                        leave-from="opacity-100 scale-100"
                        leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel class="w-full max-w-xl rounded-soft-lg bg-brand-surface text-brand-text shadow-soft-lg border border-brand-border overflow-hidden">
                            <Combobox @update:modelValue="onSelect">
                                <div class="flex items-center gap-2 px-4 h-12 border-b border-brand-border">
                                    <Search :size="16" class="text-brand-text-muted flex-shrink-0" />
                                    <ComboboxInput
                                        class="flex-1 bg-transparent border-0 focus:ring-0 outline-none text-sm placeholder:text-brand-text-subtle"
                                        placeholder="予約 / 顧客 / 画面 を検索..."
                                        autocomplete="off"
                                        :display-value="() => query"
                                        @change="query = $event.target.value"
                                    />
                                    <Loader2 v-if="loading" :size="14" class="animate-spin text-brand-text-muted" />
                                    <kbd class="text-[10px] text-brand-text-subtle border border-brand-border-strong px-1.5 py-0.5 rounded">ESC</kbd>
                                </div>

                                <ComboboxOptions static class="max-h-[60vh] overflow-y-auto py-2">
                                    <!-- ナビゲーション -->
                                    <div v-if="filteredNav.length" class="px-2">
                                        <div class="px-2 pb-1 text-[10px] uppercase tracking-widest text-brand-text-subtle font-semibold">画面</div>
                                        <ComboboxOption
                                            v-for="n in filteredNav"
                                            :key="'nav-' + n.route"
                                            :value="{ ...n, __type: 'nav' }"
                                            v-slot="{ active }"
                                        >
                                            <div :class="['flex items-center gap-2.5 px-2 py-2 rounded cursor-pointer', active ? 'bg-brand-surface-2' : '']">
                                                <component :is="n.icon" :size="15" class="text-brand-text-muted flex-shrink-0" />
                                                <span class="text-sm flex-1">{{ n.label }}</span>
                                                <span class="text-[10px] text-brand-text-subtle">{{ n.group }}</span>
                                                <CornerDownLeft v-if="active" :size="12" class="text-brand-text-subtle" />
                                            </div>
                                        </ComboboxOption>
                                    </div>

                                    <!-- 予約 -->
                                    <div v-if="serverResults.reservations.length" class="px-2 mt-2 pt-2 border-t border-brand-border">
                                        <div class="px-2 pb-1 text-[10px] uppercase tracking-widest text-brand-text-subtle font-semibold">予約</div>
                                        <ComboboxOption
                                            v-for="r in serverResults.reservations"
                                            :key="'r-' + r.id"
                                            :value="{ ...r, __type: 'reservation' }"
                                            v-slot="{ active }"
                                        >
                                            <div :class="['flex items-center gap-2.5 px-2 py-2 rounded cursor-pointer', active ? 'bg-brand-surface-2' : '']">
                                                <CalendarCheck :size="15" class="text-brand-text-muted flex-shrink-0" />
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm truncate">{{ r.name || '(無記名)' }} <span class="text-brand-text-muted text-xs">#{{ r.id }}</span></div>
                                                    <div class="text-[11px] text-brand-text-muted truncate">
                                                        {{ fmtDateTime(r.reservation_datetime) }} ／ {{ r.event?.title || '' }}
                                                    </div>
                                                </div>
                                                <CornerDownLeft v-if="active" :size="12" class="text-brand-text-subtle" />
                                            </div>
                                        </ComboboxOption>
                                    </div>

                                    <!-- 顧客 -->
                                    <div v-if="serverResults.customers.length" class="px-2 mt-2 pt-2 border-t border-brand-border">
                                        <div class="px-2 pb-1 text-[10px] uppercase tracking-widest text-brand-text-subtle font-semibold">顧客</div>
                                        <ComboboxOption
                                            v-for="c in serverResults.customers"
                                            :key="'c-' + c.id"
                                            :value="{ ...c, __type: 'customer' }"
                                            v-slot="{ active }"
                                        >
                                            <div :class="['flex items-center gap-2.5 px-2 py-2 rounded cursor-pointer', active ? 'bg-brand-surface-2' : '']">
                                                <UserIcon :size="15" class="text-brand-text-muted flex-shrink-0" />
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm truncate">{{ c.name || '(無記名)' }} <span class="text-brand-text-muted text-xs">#{{ c.id }}</span></div>
                                                    <div class="text-[11px] text-brand-text-muted truncate">{{ c.phone_number || c.email || '' }}</div>
                                                </div>
                                                <CornerDownLeft v-if="active" :size="12" class="text-brand-text-subtle" />
                                            </div>
                                        </ComboboxOption>
                                    </div>

                                    <div v-if="noResults" class="px-4 py-6 text-center text-sm text-brand-text-muted">
                                        該当する結果がありません
                                    </div>

                                    <div v-if="!query" class="px-4 py-3 text-[11px] text-brand-text-subtle border-t border-brand-border">
                                        <span class="font-semibold">Tips:</span> 画面名・予約者名・予約ID・電話番号 などで検索できます。
                                    </div>
                                </ComboboxOptions>
                            </Combobox>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </HDialog>
    </TransitionRoot>
</template>
