<template>
    <Head title="クレジット一覧" />

    <AdminLayout :breadcrumb="[{ label: 'クレジット' }, { label: 'クレジット一覧' }]">
        <UiPageHeader
            title="クレジット一覧"
            description="お客様のクレジット契約と残額です（旧：Dropbox の支払一覧表）。"
        >
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.credits.monthly')">
                    今月の支払確認
                </UiButton>
                <UiButton v-if="canEdit" variant="primary" :href="route('admin.credits.create')">
                    <template #leading><Plus :size="14" /></template>
                    契約を登録
                </UiButton>
            </template>
        </UiPageHeader>

        <form class="mb-4 grid grid-cols-2 md:grid-cols-6 gap-2 items-end" @submit.prevent="search">
            <div class="col-span-2">
                <label class="block text-xs text-brand-text-muted mb-1">お客様名・かな</label>
                <UiInput v-model="form.q" placeholder="例：山田" />
            </div>
            <div>
                <label class="block text-xs text-brand-text-muted mb-1">信販会社</label>
                <UiSelect v-model="form.company" :options="companyOptions" />
            </div>
            <div>
                <label class="block text-xs text-brand-text-muted mb-1">状態</label>
                <UiSelect v-model="form.status" :options="statusOptions" />
            </div>
            <div>
                <label class="block text-xs text-brand-text-muted mb-1">担当者</label>
                <UiInput v-model="form.staff" placeholder="例：平田" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="inline-flex items-center gap-1.5 text-xs">
                    <input v-model="form.provisional" type="checkbox" true-value="1" false-value="" />
                    仮登録だけ<span v-if="provisionalCount" class="text-brand-text-muted">（{{ provisionalCount }}件）</span>
                </label>
                <UiButton type="submit" variant="primary" block>
                    <template #leading><Search :size="14" /></template>
                    検索
                </UiButton>
            </div>
        </form>

        <!-- 表示の切り替え -->
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <div class="inline-flex rounded-soft border border-brand-border overflow-hidden text-sm">
                <button
                    v-for="v in views"
                    :key="v.value"
                    type="button"
                    class="px-3 py-1.5 inline-flex items-center gap-1.5"
                    :class="filters.view === v.value ? 'bg-brand-primary text-brand-on-primary' : 'bg-brand-surface hover:bg-brand-surface-2'"
                    @click="switchView(v.value)"
                >
                    <component :is="v.icon" :size="14" />{{ v.label }}
                </button>
            </div>
            <template v-if="filters.view === 'sheet'">
                <div class="flex items-center gap-1 ml-2">
                    <UiButton variant="ghost" size="sm" @click="shift(-6)"><ChevronLeft :size="14" /></UiButton>
                    <span class="text-sm tabular-nums w-44 text-center">{{ rangeLabel }}</span>
                    <UiButton variant="ghost" size="sm" @click="shift(6)"><ChevronRight :size="14" /></UiButton>
                    <UiButton variant="link" size="sm" @click="shift(null)">今月を中心に</UiButton>
                </div>
            </template>
            <div class="ml-auto flex items-center gap-3">
                <UiButton v-if="customers.data.length" variant="link" size="sm" @click="toggleAll">
                    {{ allOpen ? 'すべて閉じる' : 'すべての明細を開く' }}
                </UiButton>
                <span v-if="customers.total" class="text-xs text-brand-text-muted">お客様 {{ customers.total }} 人（契約 {{ contractTotal }} 件）</span>
            </div>
        </div>

        <CreditSheet
            v-if="filters.view === 'sheet'"
            :groups="customers.data"
            :months="months"
            :this-month="thisMonth"
            :is-open="isOpen"
            :toggle="toggle"
        />
        <CreditCustomerList v-else :groups="customers.data" :can-edit="canEdit" :is-open="isOpen" :toggle="toggle" />

        <nav v-if="customers.last_page > 1" class="mt-3 flex flex-wrap items-center gap-1">
            <template v-for="(link, idx) in customers.links" :key="idx">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    v-html="link.label"
                    preserve-scroll
                    class="px-2.5 py-1 rounded border text-xs"
                    :class="link.active ? 'bg-brand-primary text-brand-on-primary border-brand-primary' : 'bg-brand-surface border-brand-border hover:bg-brand-surface-2'"
                />
                <span v-else v-html="link.label" class="px-2.5 py-1 rounded border border-brand-border text-xs text-brand-text-subtle" />
            </template>
        </nav>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref, computed, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import CreditSheet from '@/Components/Admin/CreditSheet.vue';
import CreditCustomerList from '@/Components/Admin/CreditCustomerList.vue';
import { UiPageHeader, UiButton, UiInput, UiSelect } from '@/Components/UI';
import { Plus, Search, Table2, List, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    customers: Object,
    contractTotal: { type: Number, default: 0 },
    months: { type: Array, default: () => [] },
    thisMonth: String,
    filters: Object,
    companies: Array,
    shops: Array,
    provisionalCount: { type: Number, default: 0 },
    canEdit: Boolean,
});

const form = reactive({ ...props.filters });
// 仮登録だけを見るときは状態を「すべて」にする（完済になった仮登録も見落とさない）
watch(() => form.provisional, (v) => { if (v === '1') form.status = 'all'; });

const companyOptions = computed(() => [
    { value: '', label: 'すべて' },
    ...props.companies.map((c) => ({ value: String(c.id), label: c.name })),
]);
const statusOptions = [
    { value: 'active', label: '支払中' },
    { value: 'completed', label: '完済' },
    { value: 'cancelled', label: '解約・組換' },
    { value: 'all', label: 'すべて' },
];

// 明細を開いているお客様（ページを移っても、表示を切り替えても覚えておく）
const opened = ref(new Set());
const isOpen = (id) => opened.value.has(id);
const toggle = (id) => {
    const next = new Set(opened.value);
    next.has(id) ? next.delete(id) : next.add(id);
    opened.value = next;
};
const allOpen = computed(() => props.customers.data.length > 0 && props.customers.data.every((g) => opened.value.has(g.id)));
const toggleAll = () => {
    const next = new Set(opened.value);
    const open = !allOpen.value;
    props.customers.data.forEach((g) => (open ? next.add(g.id) : next.delete(g.id)));
    opened.value = next;
};

const views = [
    { value: 'sheet', label: '支払状況（表）', icon: Table2 },
    { value: 'list', label: '契約リスト', icon: List },
];

// 検索条件はそのままに、表示や期間だけ変える
const go = (extra) => {
    const params = { ...form, view: props.filters.view, from: props.filters.from, ...extra };
    if (params.view === 'list') delete params.from;
    Object.keys(params).forEach((k) => (params[k] === undefined || params[k] === '') && delete params[k]);
    router.get(route('admin.credits.index'), params, { preserveState: true, preserveScroll: true });
};
const search = () => go({});
const switchView = (v) => go({ view: v, from: undefined });
const shift = (delta) => {
    if (delta === null) return go({ from: undefined });
    const [y, m] = props.filters.from.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    go({ from: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` });
};
const rangeLabel = computed(() => {
    if (!props.months.length) return '';
    const f = (s) => `${s.slice(0, 4)}年${Number(s.slice(5))}月`;
    return `${f(props.months[0])}〜${f(props.months[props.months.length - 1])}`;
});
</script>
