<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    UiCard, UiBadge, UiButton, UiPageHeader,
} from '@/Components/UI';
import {
    CalendarDays, Users, TrendingUp, TrendingDown, AlertCircle,
    ArrowRight, Store, Clock,
} from 'lucide-vue-next';

const props = defineProps({
    stats: { type: Object, required: true },
    recent_reservations: { type: Array, default: () => [] },
    shop_ranking: { type: Array, default: () => [] },
    week_range: { type: Object, default: () => ({}) },
});

const statusVariant = (status) => ({
    '確認中':       'primary',
    '返信待ち':     'warning',
    '対応完了済み': 'success',
    'キャンセル':   'danger',
    '未対応':       'neutral',
}[status] || 'neutral');

const fmtDateTime = (s) => {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    return `${mm}/${dd} ${hh}:${mi}`;
};

const weekDeltaAbs = computed(() =>
    props.stats.week_delta != null ? Math.abs(props.stats.week_delta) : null
);

const maxShopCnt = computed(() =>
    props.shop_ranking.length ? Math.max(...props.shop_ranking.map((s) => Number(s.cnt))) : 0
);
</script>

<template>
    <Head title="オーバービュー" />
    <AdminLayout :breadcrumb="[{ label: 'ホーム' }, { label: 'オーバービュー' }]">

        <UiPageHeader
            title="オーバービュー"
            description="主要KPIと最近の予約を一覧で把握できます。"
        >
            <template #actions>
                <UiButton variant="ghost" :href="route('dashboard')">
                    従来ダッシュボードへ
                    <template #trailing><ArrowRight :size="14" /></template>
                </UiButton>
            </template>
        </UiPageHeader>

        <!-- KPI カード -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <UiCard variant="default">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-xs text-brand-text-muted">本日の予約</div>
                        <div class="mt-1.5 font-serif text-3xl leading-none">{{ stats.today_count }}<span class="text-sm text-brand-text-muted ml-1">件</span></div>
                    </div>
                    <div class="w-10 h-10 rounded-soft bg-ai-50 dark:bg-ai-900 flex items-center justify-center">
                        <CalendarDays :size="20" class="text-brand-primary" />
                    </div>
                </div>
            </UiCard>

            <UiCard variant="default">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-xs text-brand-text-muted">今週の予約</div>
                        <div class="mt-1.5 font-serif text-3xl leading-none">{{ stats.week_count }}<span class="text-sm text-brand-text-muted ml-1">件</span></div>
                        <div v-if="stats.week_delta != null" class="mt-1 text-xs flex items-center gap-1" :class="stats.week_delta >= 0 ? 'text-uguisu-600 dark:text-uguisu-400' : 'text-akane-600 dark:text-akane-400'">
                            <component :is="stats.week_delta >= 0 ? TrendingUp : TrendingDown" :size="12" />
                            前週比 {{ stats.week_delta >= 0 ? '+' : '−' }}{{ weekDeltaAbs }}%
                        </div>
                        <div v-else class="mt-1 text-xs text-brand-text-subtle">前週データなし</div>
                    </div>
                    <div class="w-10 h-10 rounded-soft bg-uguisu-50 dark:bg-uguisu-900 flex items-center justify-center">
                        <Users :size="20" class="text-brand-success" />
                    </div>
                </div>
            </UiCard>

            <UiCard variant="default">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-xs text-brand-text-muted">要対応</div>
                        <div class="mt-1.5 font-serif text-3xl leading-none">{{ stats.pending_count }}<span class="text-sm text-brand-text-muted ml-1">件</span></div>
                        <div class="mt-1 text-xs text-brand-text-subtle">未対応・確認中・返信待ち</div>
                    </div>
                    <div class="w-10 h-10 rounded-soft bg-natane-50 dark:bg-natane-900 flex items-center justify-center">
                        <AlertCircle :size="20" class="text-brand-warning" />
                    </div>
                </div>
            </UiCard>

            <UiCard variant="default">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-xs text-brand-text-muted">キャンセル率（30日）</div>
                        <div class="mt-1.5 font-serif text-3xl leading-none">{{ stats.cancel_rate }}<span class="text-sm text-brand-text-muted ml-1">%</span></div>
                    </div>
                    <div class="w-10 h-10 rounded-soft bg-akane-50 dark:bg-akane-900 flex items-center justify-center">
                        <TrendingDown :size="20" class="text-brand-danger" />
                    </div>
                </div>
            </UiCard>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- 最近の予約 -->
            <UiCard variant="default" padding="none" class="lg:col-span-2">
                <template #header>
                    <div class="flex items-center justify-between">
                        <h2 class="font-serif text-base">最近の予約</h2>
                        <Clock :size="14" class="text-brand-text-muted" />
                    </div>
                </template>
                <div v-if="recent_reservations.length === 0" class="p-6 text-center text-sm text-brand-text-muted">
                    最近の予約はありません。
                </div>
                <div v-else class="divide-y divide-brand-border">
                    <Link
                        v-for="r in recent_reservations"
                        :key="r.id"
                        :href="route('admin.reservations.show', r.id)"
                        class="flex items-center gap-3 px-5 py-3 hover:bg-brand-surface-2 transition-colors"
                    >
                        <div class="flex-shrink-0 w-14 text-xs text-brand-text-muted font-medium text-center">
                            {{ fmtDateTime(r.reservation_datetime) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">{{ r.name || '(無記名)' }}</div>
                            <div class="text-xs text-brand-text-muted truncate">
                                {{ r.event?.title || '未設定' }}<span v-if="r.venue?.name"> ／ {{ r.venue.name }}</span>
                            </div>
                        </div>
                        <UiBadge :variant="statusVariant(r.status)" size="sm">{{ r.status }}</UiBadge>
                    </Link>
                </div>
            </UiCard>

            <!-- 今週の店舗ランキング -->
            <UiCard variant="default" padding="none">
                <template #header>
                    <div class="flex items-center justify-between">
                        <h2 class="font-serif text-base">今週の店舗別予約</h2>
                        <Store :size="14" class="text-brand-text-muted" />
                    </div>
                </template>
                <div v-if="shop_ranking.length === 0" class="p-6 text-center text-sm text-brand-text-muted">
                    今週の予約はまだありません。
                </div>
                <div v-else class="p-5 space-y-3">
                    <div v-for="s in shop_ranking" :key="s.id" class="space-y-1">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ s.name }}</span>
                            <span class="text-brand-text-muted tabular-nums">{{ s.cnt }}件</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-brand-surface-2 overflow-hidden">
                            <div
                                class="h-full bg-brand-primary transition-all"
                                :style="{ width: maxShopCnt > 0 ? `${(Number(s.cnt) / maxShopCnt) * 100}%` : '0%' }"
                            />
                        </div>
                    </div>
                    <div v-if="week_range.start" class="text-xs text-brand-text-subtle mt-3 pt-3 border-t border-brand-border">
                        期間: {{ week_range.start }} 〜 {{ week_range.end }}
                    </div>
                </div>
            </UiCard>

        </div>

    </AdminLayout>
</template>
