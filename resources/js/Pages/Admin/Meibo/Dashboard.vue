<template>
    <Head title="名簿ダッシュボード" />

    <AdminLayout :breadcrumb="[{ label: '名簿一覧', href: route('admin.meibo.index') }, { label: '名簿ダッシュボード' }]">
        <UiPageHeader title="名簿ダッシュボード" description="名簿の総数・DM状態・成人式年と都道府県の内訳・地域の分布を見ます。">
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.meibo.index')">
                    <template #leading><BookUser :size="14" /></template>
                    名簿一覧
                </UiButton>
            </template>
        </UiPageHeader>

        <DummyNotice :show="api.isDummy" />

        <!-- 数字 -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
            <Link v-for="c in cards" :key="c.key" :href="c.href" class="block">
                <UiCard variant="default" class="h-full hover:border-brand-primary transition-colors">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-xs text-brand-text-muted">{{ c.title }}</div>
                            <div class="mt-1.5 font-serif text-2xl sm:text-3xl leading-none tabular-nums" :class="c.tone">
                                <template v-if="stats">{{ num(c.value) }}</template>
                                <span v-else class="inline-block w-16 h-7 rounded-soft-sm bg-brand-surface-2 animate-pulse align-middle" />
                                <span class="text-sm text-brand-text-muted ml-1">人</span>
                            </div>
                            <div v-if="stats && c.share !== null" class="mt-1.5 text-[11px] text-brand-text-subtle tabular-nums">全体の {{ c.share }}%</div>
                        </div>
                        <div :class="['w-9 h-9 sm:w-10 sm:h-10 rounded-soft flex items-center justify-center shrink-0', c.iconBg]">
                            <component :is="c.icon" :size="18" :class="c.tone || 'text-brand-text-muted'" />
                        </div>
                    </div>
                </UiCard>
            </Link>
        </section>

        <!-- DM状態の割合 -->
        <UiCard v-if="stats && stats.total" padding="sm" class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-serif text-base">DM状態の割合</h2>
                <span class="text-xs text-brand-text-muted">全 {{ num(stats.total) }} 人</span>
            </div>
            <div class="flex h-3 w-full overflow-hidden rounded-full bg-brand-surface-2" role="img" :aria-label="statusAria">
                <div v-for="s in statusBars" :key="s.status" :class="s.bar" :style="{ width: `${s.pct}%` }" />
            </div>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                <span v-for="s in statusBars" :key="s.status" class="inline-flex items-center gap-1.5">
                    <span :class="['inline-block w-2.5 h-2.5 rounded-full', s.bar]" />
                    {{ s.status }} <b class="tabular-nums">{{ num(s.count) }}</b><span class="text-brand-text-muted">（{{ s.pct }}%）</span>
                </span>
            </div>
        </UiCard>

        <!-- 地図 -->
        <section class="mb-6">
            <MeiboMap :api="api" :options="opts" />
        </section>

        <!-- 成人式年 × 都道府県 -->
        <UiCard padding="none">
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-serif text-base">成人式年 × 都道府県</h2>
                    <span class="text-xs text-brand-text-muted">数字を押すと、その条件の名簿一覧を開きます</span>
                </div>
            </template>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-brand-surface-2 text-xs text-brand-text-muted">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium whitespace-nowrap sticky left-0 bg-brand-surface-2">成人式年</th>
                            <th v-for="p in pivot.prefs" :key="p" class="px-4 py-2 text-right font-medium whitespace-nowrap">{{ p }}</th>
                            <th class="px-4 py-2 text-right font-medium whitespace-nowrap">合計</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-for="row in pivot.rows" :key="row.year" class="hover:bg-brand-surface-2/60">
                            <th class="px-4 py-2 text-left font-normal whitespace-nowrap sticky left-0 bg-brand-surface">
                                {{ wareki(row.year) }}<span class="text-xs text-brand-text-muted ml-1">（{{ row.year }}）</span>
                            </th>
                            <td v-for="p in pivot.prefs" :key="p" class="px-4 py-2 text-right tabular-nums">
                                <Link v-if="row.cells[p]" :href="listHref({ pref: p, seijin_year: row.year })" class="hover:text-brand-primary hover:underline">{{ num(row.cells[p]) }}</Link>
                                <span v-else class="text-brand-text-subtle">—</span>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums font-medium">
                                <Link :href="listHref({ seijin_year: row.year })" class="hover:text-brand-primary hover:underline">{{ num(row.total) }}</Link>
                            </td>
                        </tr>
                        <tr v-if="!pivot.rows.length">
                            <td :colspan="pivot.prefs.length + 2" class="px-4 py-8 text-center text-brand-text-muted">{{ stats ? 'データがありません' : '読み込み中…' }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="pivot.rows.length" class="border-t-2 border-brand-border-strong font-medium">
                        <tr>
                            <th class="px-4 py-2 text-left whitespace-nowrap sticky left-0 bg-brand-surface">合計</th>
                            <td v-for="p in pivot.prefs" :key="p" class="px-4 py-2 text-right tabular-nums">
                                <Link :href="listHref({ pref: p })" class="hover:text-brand-primary hover:underline">{{ num(pivot.colTotals[p]) }}</Link>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ num(pivot.total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </UiCard>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiPageHeader, UiButton, UiCard } from '@/Components/UI';
import DummyNotice from '@/Components/Meibo/DummyNotice.vue';
import MeiboMap from '@/Components/Meibo/MeiboMap.vue';
import { BookUser, Users, MailCheck, MailX, Ban, Sparkles } from 'lucide-vue-next';
import { useMeiboApi, num, wareki, DM_STATUSES } from '@/services/meibo';
import { useToast } from '@/composables/useToast.js';

defineProps({
    dataSource: { type: String, default: 'dummy' },
});

const api = useMeiboApi();
const toast = useToast();

const stats = ref(null);
const opts = ref({ prefs: [], seijin_years: [], statuses: DM_STATUSES });

const listHref = (q = {}) => {
    const s = new URLSearchParams(Object.entries(q).filter(([, v]) => v !== '' && v != null).map(([k, v]) => [k, String(v)])).toString();
    return route('admin.meibo.index') + (s ? `?${s}` : '');
};

const STATUS_STYLE = {
    送付可: { icon: MailCheck, tone: 'text-brand-success', iconBg: 'bg-uguisu-50 dark:bg-uguisu-900', bar: 'bg-brand-success' },
    未達: { icon: MailX, tone: 'text-brand-warning', iconBg: 'bg-natane-50 dark:bg-natane-900', bar: 'bg-brand-warning' },
    停止: { icon: Ban, tone: 'text-brand-danger', iconBg: 'bg-enji-50 dark:bg-enji-900', bar: 'bg-brand-danger' },
};

const share = (n) => (stats.value?.total ? Math.round((n / stats.value.total) * 1000) / 10 : 0);

const cards = computed(() => {
    const s = stats.value;
    return [
        { key: 'total', title: '総登録数', value: s?.total ?? 0, share: null, icon: Users, tone: '', iconBg: 'bg-brand-surface-2', href: listHref() },
        ...DM_STATUSES.map((st) => ({
            key: st,
            title: st,
            value: s?.by_status?.[st] ?? 0,
            share: share(s?.by_status?.[st] ?? 0),
            icon: STATUS_STYLE[st].icon,
            tone: STATUS_STYLE[st].tone,
            iconBg: STATUS_STYLE[st].iconBg,
            href: listHref({ status: st }),
        })),
        { key: 'r11', title: 'R11 成人名簿', value: s?.r11 ?? 0, share: share(s?.r11 ?? 0), icon: Sparkles, tone: 'text-brand-accent', iconBg: 'bg-ai-50 dark:bg-ai-900', href: listHref({ source: 'r11' }) },
    ];
});

const statusBars = computed(() => DM_STATUSES.map((st) => {
    const count = stats.value?.by_status?.[st] ?? 0;
    return { status: st, count, pct: share(count), bar: STATUS_STYLE[st].bar };
}));
const statusAria = computed(() => statusBars.value.map((s) => `${s.status} ${s.count}人`).join('、'));

const pivot = computed(() => {
    const rowsIn = stats.value?.by_year ?? [];
    const prefs = [...new Set(rowsIn.map((r) => r.pref))].sort();
    const years = [...new Set(rowsIn.map((r) => r.seijin_year))].sort((a, b) => a - b);
    const colTotals = Object.fromEntries(prefs.map((p) => [p, 0]));
    const rows = years.map((year) => {
        const cells = {};
        let total = 0;
        rowsIn.filter((r) => r.seijin_year === year).forEach((r) => {
            cells[r.pref] = (cells[r.pref] || 0) + r.c;
            colTotals[r.pref] += r.c;
            total += r.c;
        });
        return { year, cells, total };
    });
    return { prefs, rows, colTotals, total: rows.reduce((a, r) => a + r.total, 0) };
});

onMounted(async () => {
    try {
        const [s, o] = await Promise.all([api.stats(), api.options()]);
        stats.value = s;
        opts.value = o;
    } catch (e) {
        toast.danger(`集計を読み込めませんでした：${e.message}`);
    }
});
</script>
