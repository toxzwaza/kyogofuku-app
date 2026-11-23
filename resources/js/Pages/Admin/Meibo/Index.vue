<template>
    <Head title="名簿一覧" />

    <AdminLayout :breadcrumb="[{ label: '名簿' }, { label: '名簿一覧' }]">
        <UiPageHeader title="名簿一覧" description="成人式の DM 名簿を探して、DM 状態を変えたり、ラベルを発行する人を選んだりします。">
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.meibo.dashboard')">
                    <template #leading><ChartPie :size="14" /></template>
                    ダッシュボード
                </UiButton>
                <UiButton variant="primary" :href="route('admin.meibo.labels')">
                    <template #leading><Printer :size="14" /></template>
                    ラベル発行（{{ num(selection.count.value) }}件）
                </UiButton>
            </template>
        </UiPageHeader>

        <DummyNotice :show="api.isDummy" />

        <!-- 絞り込み -->
        <UiCard padding="sm" class="mb-4">
            <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-[repeat(4,minmax(0,11rem))_1fr] gap-3">
                <UiFormField label="都道府県">
                    <UiSelect v-model="filters.pref" :options="prefOptions" />
                </UiFormField>
                <UiFormField label="成人式年">
                    <UiSelect v-model="filters.seijin_year" :options="yearOptions" />
                </UiFormField>
                <UiFormField label="DM状態">
                    <UiSelect v-model="filters.status" :options="statusOptions" />
                </UiFormField>
                <UiFormField label="出所">
                    <UiSelect v-model="filters.source" :options="sourceOptions" />
                </UiFormField>
                <UiFormField label="キーワード（氏名・フリガナ・住所・電話・保護者）" class="col-span-2 md:col-span-4 xl:col-span-1">
                    <UiInput v-model="filters.q" placeholder="例：山田 / 鯖江市 / 0776-">
                        <template #leading><Search :size="14" class="text-brand-text-muted" /></template>
                    </UiInput>
                </UiFormField>
            </div>
            <div v-if="activeChips.length" class="mt-3 flex flex-wrap items-center gap-1.5">
                <button
                    v-for="c in activeChips"
                    :key="c.key"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full bg-brand-surface-2 border border-brand-border px-2.5 py-1 text-xs hover:border-brand-border-strong"
                    @click="filters[c.key] = ''"
                >
                    {{ c.label }}<X :size="12" class="text-brand-text-muted" />
                </button>
                <button type="button" class="text-xs text-brand-primary hover:underline ml-1" @click="resetFilters">条件をすべて外す</button>
            </div>
        </UiCard>

        <!-- 選択のツールバー -->
        <div class="mb-3 flex flex-wrap items-center gap-2 rounded-soft-lg border border-brand-border bg-brand-surface px-3 py-2">
            <UiButton size="sm" variant="ghost" :loading="selectingAll" :disabled="!data.total" @click="selectAllMatching">
                <template #leading><ListChecks :size="13" /></template>
                該当{{ num(data.total) }}件をすべて選択
            </UiButton>
            <UiButton size="sm" variant="ghost" :disabled="!pageIds.length" @click="togglePage">
                {{ pageAllSelected ? 'このページを外す' : 'このページを選択' }}
            </UiButton>
            <UiButton size="sm" variant="subtle" :disabled="!selection.count.value" @click="selection.clear()">選択を解除</UiButton>
            <span class="text-sm text-brand-text-muted ml-auto">
                選択中 <b class="font-serif text-lg text-brand-accent tabular-nums">{{ num(selection.count.value) }}</b> 件
            </span>
        </div>

        <div class="mb-2 text-xs text-brand-text-muted" aria-live="polite">
            <template v-if="loading">検索中...</template>
            <template v-else>該当 <b class="text-brand-text tabular-nums">{{ num(data.total) }}</b> 件（{{ page }} / {{ pages }} ページ）</template>
        </div>

        <!-- 表（PC） -->
        <div class="hidden md:block rounded-soft-lg border border-brand-border overflow-hidden bg-brand-surface">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-brand-surface-2 text-brand-text-muted">
                        <tr class="text-[11px] font-semibold tracking-wider text-left">
                            <th class="w-10 p-3">
                                <input
                                    type="checkbox"
                                    :checked="pageAllSelected"
                                    :indeterminate.prop="pageSomeSelected && !pageAllSelected"
                                    class="rounded border-brand-border-strong focus:ring-brand-primary text-brand-primary"
                                    aria-label="このページをすべて選択"
                                    @change="togglePage"
                                />
                            </th>
                            <th class="px-3 py-2.5 whitespace-nowrap">県・成人式年</th>
                            <th class="px-3 py-2.5">氏名</th>
                            <th class="px-3 py-2.5">住所</th>
                            <th class="px-3 py-2.5 hidden lg:table-cell whitespace-nowrap">電話／携帯</th>
                            <th class="px-3 py-2.5 hidden lg:table-cell">保護者</th>
                            <th class="px-3 py-2.5">DM状態</th>
                            <th class="px-3 py-2.5">出所</th>
                            <th class="px-3 py-2.5 w-20"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-if="loading && !data.items.length">
                            <td colspan="9" class="p-8 text-center text-brand-text-muted">読み込み中...</td>
                        </tr>
                        <tr v-else-if="!data.items.length">
                            <td colspan="9" class="p-8 text-center">
                                <div class="flex flex-col items-center gap-2 text-brand-text-muted">
                                    <Inbox :size="28" class="text-brand-text-subtle" />
                                    <span class="text-sm">条件に合う人がいません。</span>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-for="r in data.items"
                            v-else
                            :key="r.id"
                            :class="['transition-colors', selection.has(r.id) ? 'bg-enji-50/60 dark:bg-enji-900/30' : 'hover:bg-brand-surface-2']"
                            :style="loading ? 'opacity:.6' : ''"
                        >
                            <td class="w-10 p-3">
                                <input
                                    type="checkbox"
                                    :checked="selection.has(r.id)"
                                    class="rounded border-brand-border-strong focus:ring-brand-primary text-brand-primary"
                                    :aria-label="`${r.name}を選択`"
                                    @change="selection.toggle(r.id)"
                                />
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                <div>{{ r.pref }}</div>
                                <div class="text-xs text-brand-text-muted">{{ wareki(r.seijin_year) }}</div>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="font-medium whitespace-nowrap">{{ r.name }}</div>
                                <div class="text-xs text-brand-text-muted whitespace-nowrap">{{ r.kana || '―' }}</div>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="text-xs text-brand-text-muted tabular-nums">〒{{ r.zip || '―' }}</div>
                                <div>{{ r.address || '―' }}</div>
                            </td>
                            <td class="px-3 py-2.5 hidden lg:table-cell text-xs tabular-nums whitespace-nowrap">{{ r.tel || r.mobile || '―' }}</td>
                            <td class="px-3 py-2.5 hidden lg:table-cell whitespace-nowrap">{{ r.guardian_name || '―' }}</td>
                            <td class="px-3 py-2.5">
                                <UiBadge :variant="DM_STATUS_VARIANT[r.dm_status]">{{ r.dm_status }}</UiBadge>
                                <div v-if="r.dm_note" class="mt-0.5 text-[11px] text-brand-text-muted max-w-[10rem] truncate" :title="r.dm_note">{{ r.dm_note }}</div>
                            </td>
                            <td class="px-3 py-2.5"><SourceBadge :source-versions="r.source_versions" :merge-count="r.merge_count" /></td>
                            <td class="px-3 py-2.5 text-right">
                                <UiButton size="sm" variant="ghost" class="whitespace-nowrap" @click="openEdit(r)">状態変更</UiButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- カード（スマホ） -->
        <div class="md:hidden space-y-2">
            <div v-if="!data.items.length" class="p-6 text-center rounded-soft-lg border border-brand-border bg-brand-surface text-sm text-brand-text-muted">
                {{ loading ? '読み込み中...' : '条件に合う人がいません。' }}
            </div>
            <div
                v-for="r in data.items"
                :key="r.id"
                :class="['rounded-soft-lg border bg-brand-surface p-3 flex gap-3', selection.has(r.id) ? 'border-brand-accent' : 'border-brand-border']"
            >
                <input
                    type="checkbox"
                    :checked="selection.has(r.id)"
                    class="mt-1 h-5 w-5 rounded border-brand-border-strong focus:ring-brand-primary text-brand-primary"
                    :aria-label="`${r.name}を選択`"
                    @change="selection.toggle(r.id)"
                />
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="font-medium">{{ r.name }}</span>
                        <UiBadge :variant="DM_STATUS_VARIANT[r.dm_status]" size="sm">{{ r.dm_status }}</UiBadge>
                        <SourceBadge :source-versions="r.source_versions" :merge-count="r.merge_count" />
                    </div>
                    <div class="text-xs text-brand-text-muted">{{ r.kana || '―' }}・{{ r.pref }}・{{ wareki(r.seijin_year) }}</div>
                    <div class="text-sm">〒{{ r.zip }} {{ r.address }}</div>
                    <div class="text-xs text-brand-text-muted">
                        {{ r.tel || r.mobile || '電話なし' }}<template v-if="r.guardian_name">・保護者 {{ r.guardian_name }}</template>
                    </div>
                    <div v-if="r.dm_note" class="text-xs text-brand-text-muted">メモ：{{ r.dm_note }}</div>
                </div>
                <UiButton size="sm" variant="ghost" class="self-start" @click="openEdit(r)">状態変更</UiButton>
            </div>
        </div>

        <!-- ページ送り -->
        <nav v-if="pages > 1" class="mt-4 flex items-center justify-center gap-2 text-sm" aria-label="ページ送り">
            <UiButton size="sm" variant="ghost" :disabled="page <= 1 || loading" @click="go(page - 1)">
                <ChevronLeft :size="14" />前へ
            </UiButton>
            <span class="tabular-nums text-brand-text-muted px-2">{{ page }} / {{ pages }}</span>
            <UiButton size="sm" variant="ghost" :disabled="page >= pages || loading" @click="go(page + 1)">
                次へ<ChevronRight :size="14" />
            </UiButton>
        </nav>

        <!-- スマホ：選択中の件数とラベル発行を下に固定 -->
        <div
            v-if="selection.count.value"
            class="md:hidden fixed inset-x-0 bottom-0 z-30 border-t border-brand-border bg-brand-surface/95 backdrop-blur pl-4 pr-20 py-2 flex items-center gap-3"
        >
            <span class="text-sm">選択中 <b class="text-brand-accent tabular-nums">{{ num(selection.count.value) }}</b> 件</span>
            <UiButton variant="primary" size="sm" class="ml-auto" :href="route('admin.meibo.labels')">
                <template #leading><Printer :size="13" /></template>
                ラベル発行
            </UiButton>
        </div>
        <div v-if="selection.count.value" class="md:hidden h-16" />

        <DmStatusDialog v-model:open="editOpen" :person="editing" :api="api" @saved="onSaved" />
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiPageHeader, UiButton, UiCard, UiSelect, UiInput, UiFormField, UiBadge } from '@/Components/UI';
import DmStatusDialog from '@/Components/Meibo/DmStatusDialog.vue';
import SourceBadge from '@/Components/Meibo/SourceBadge.vue';
import DummyNotice from '@/Components/Meibo/DummyNotice.vue';
import { Search, Printer, ChartPie, ListChecks, ChevronLeft, ChevronRight, Inbox, X } from 'lucide-vue-next';
import { useMeiboApi, DM_STATUS_VARIANT, PAGE_SIZE, wareki, yearLabel, num } from '@/services/meibo';
import { useMeiboSelection } from '@/composables/useMeiboSelection.js';
import { useDebounceFn } from '@/composables/useDebounceFn.js';
import { useToast } from '@/composables/useToast.js';

defineProps({
    dataSource: { type: String, default: 'dummy' },
});

const api = useMeiboApi();
const selection = useMeiboSelection();
const toast = useToast();

const FILTER_KEYS = ['pref', 'seijin_year', 'status', 'source', 'q'];
const SOURCE_LABELS = { r11: 'R11 成人名簿の人', other: 'R11 以外' };

// 絞り込みは URL の ?pref=… にも残す（戻る・共有で同じ条件になる）
const initial = (() => {
    const qs = new URLSearchParams(window.location.search);
    return Object.fromEntries(FILTER_KEYS.map((k) => [k, qs.get(k) || '']));
})();
const filters = reactive({ ...initial });

const opts = ref({ prefs: [], seijin_years: [], statuses: [] });
const data = ref({ items: [], total: 0 });
const page = ref(Number(new URLSearchParams(window.location.search).get('page')) || 1);
const loading = ref(false);
const selectingAll = ref(false);

const prefOptions = computed(() => [{ value: '', label: 'すべて' }, ...opts.value.prefs]);
const yearOptions = computed(() => [
    { value: '', label: 'すべて' },
    ...opts.value.seijin_years.map((y) => ({ value: String(y), label: yearLabel(y) })),
]);
const statusOptions = computed(() => [{ value: '', label: 'すべて' }, ...opts.value.statuses]);
const sourceOptions = [
    { value: '', label: 'すべて' },
    { value: 'r11', label: 'R11 成人名簿の人' },
    { value: 'other', label: 'R11 以外' },
];

const activeChips = computed(() => FILTER_KEYS
    .filter((k) => filters[k])
    .map((k) => ({
        key: k,
        label: k === 'seijin_year' ? yearLabel(filters[k])
            : k === 'source' ? SOURCE_LABELS[filters[k]]
            : k === 'q' ? `「${filters[k]}」` : filters[k],
    })));

const pages = computed(() => Math.max(1, Math.ceil(data.value.total / PAGE_SIZE)));
const pageIds = computed(() => data.value.items.map((r) => r.id));
const pageAllSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selection.has(id)));
const pageSomeSelected = computed(() => pageIds.value.some((id) => selection.has(id)));

const syncUrl = () => {
    const qs = new URLSearchParams();
    FILTER_KEYS.forEach((k) => { if (filters[k]) qs.set(k, filters[k]); });
    if (page.value > 1) qs.set('page', String(page.value));
    const s = qs.toString();
    window.history.replaceState(window.history.state, '', window.location.pathname + (s ? `?${s}` : ''));
};

let seq = 0;
const load = async () => {
    const mine = ++seq;
    loading.value = true;
    try {
        const d = await api.persons({ ...filters, page: page.value, page_size: PAGE_SIZE });
        if (mine !== seq) return; // 古い検索の結果は捨てる
        data.value = d;
        syncUrl();
    } catch (e) {
        toast.danger(`名簿を読み込めませんでした：${e.message}`);
    } finally {
        if (mine === seq) loading.value = false;
    }
};

const reloadFromFirst = () => { page.value = 1; load(); };
const debouncedReload = useDebounceFn(reloadFromFirst, 300);
watch(() => FILTER_KEYS.filter((k) => k !== 'q').map((k) => filters[k]), reloadFromFirst);
watch(() => filters.q, debouncedReload);

const go = (p) => { page.value = p; load(); window.scrollTo({ top: 0, behavior: 'smooth' }); };
const resetFilters = () => { FILTER_KEYS.forEach((k) => { filters[k] = ''; }); };

const togglePage = () => {
    if (pageAllSelected.value) selection.removeMany(pageIds.value);
    else selection.addMany(pageIds.value);
};

const selectAllMatching = async () => {
    selectingAll.value = true;
    try {
        const { ids } = await api.personIds({ ...filters });
        selection.set(ids);
        toast.success(`${num(ids.length)}件を選びました。`);
    } catch (e) {
        toast.danger(`選べませんでした：${e.message}`);
    } finally {
        selectingAll.value = false;
    }
};

const editOpen = ref(false);
const editing = ref(null);
const openEdit = (r) => { editing.value = r; editOpen.value = true; };
const onSaved = (updated) => {
    const i = data.value.items.findIndex((r) => r.id === updated.id);
    if (i >= 0) data.value.items.splice(i, 1, { ...data.value.items[i], ...updated });
};

onMounted(async () => {
    try { opts.value = await api.options(); } catch { /* 選択肢が無くても検索はできる */ }
    load();
});
</script>
