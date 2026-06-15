<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    UiCard, UiBadge, UiButton, UiPageHeader,
} from '@/Components/UI';
import DailyTrendChart from '@/Components/Admin/DailyTrendChart.vue';
import StatusDistChart from '@/Components/Admin/StatusDistChart.vue';
import ReservationHeatmap from '@/Components/Admin/ReservationHeatmap.vue';
import {
    CalendarDays, Users, TrendingUp, TrendingDown, AlertCircle,
    ArrowRight, Store, Clock, BarChart3, PieChart, Grid3x3,
    MessageCircle, ChevronDown, Image as ImageIcon,
} from 'lucide-vue-next';

const props = defineProps({
    stats: { type: Object, required: true },
    recent_reservations: { type: Array, default: () => [] },
    shop_ranking: { type: Array, default: () => [] },
    week_range: { type: Object, default: () => ({}) },
    daily_trend: { type: Array, default: () => [] },
    status_dist: { type: Array, default: () => [] },
    heatmap: { type: Object, default: () => ({ cells: {}, max: 0 }) },
    line_inbound: { type: Object, default: () => ({ groups: [], unread_total: 0, total: 0 }) },
});

// LINE受信ブロック：お客様グループの展開状態
const expandedGroups = ref({});
const toggleGroup = (id) => { expandedGroups.value[id] = !expandedGroups.value[id]; };
const isExpanded = (id) => !!expandedGroups.value[id];

// LINE受信ブロック：表示フィルタ（未読のみ / 既読も表示）
const lineUnreadOnly = ref(true);

// 表示用グループ：トグルに応じてメッセージを絞り込み、プレビュー・最新時刻を再計算
const lineGroupsView = computed(() => {
    let groups = (props.line_inbound.groups || []).map((g) => {
        const msgs = lineUnreadOnly.value
            ? g.messages.filter((m) => m.is_unread)
            : g.messages;
        const latest = msgs.length ? msgs[msgs.length - 1] : null; // messages は古い順
        return {
            ...g,
            view_messages: msgs,
            latest_at: latest?.created_at ?? null,
            latest_text: latest ? (latest.is_image ? '' : latest.text) : '',
            latest_is_image: latest?.is_image ?? false,
        };
    });
    // 未読のみ表示時は、未読を含まないグループを除外
    if (lineUnreadOnly.value) {
        groups = groups.filter((g) => g.view_messages.length > 0);
    }
    // 並び順：未読を含むお客様を上 → 最新受信時刻の降順
    return groups.sort((a, b) => {
        const ua = a.unread_count > 0 ? 1 : 0;
        const ub = b.unread_count > 0 ? 1 : 0;
        if (ua !== ub) return ub - ua;
        return new Date(b.latest_at || 0) - new Date(a.latest_at || 0);
    });
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

const fmtRelative = (s) => {
    if (!s) return '';
    const d = new Date(s);
    if (isNaN(d.getTime())) return '';
    const diff = Date.now() - d.getTime();
    const min = Math.floor(diff / 60000);
    if (min < 1) return 'たった今';
    if (min < 60) return `${min}分前`;
    const hr = Math.floor(min / 60);
    if (hr < 24) return `${hr}時間前`;
    const day = Math.floor(hr / 24);
    if (day < 7) return `${day}日前`;
    return fmtDateTime(s);
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

        <!-- チャート -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <UiCard variant="default" class="lg:col-span-2">
                <template #header>
                    <div class="flex items-center justify-between">
                        <h2 class="font-serif text-base">日別の予約数トレンド</h2>
                        <span class="text-[10px] text-brand-text-muted flex items-center gap-1">
                            <BarChart3 :size="12" />過去14日〜先14日
                        </span>
                    </div>
                </template>
                <DailyTrendChart v-if="daily_trend.length" :data="daily_trend" />
                <div v-else class="h-40 flex items-center justify-center text-brand-text-muted text-sm">
                    データがありません
                </div>
            </UiCard>

            <UiCard variant="default">
                <template #header>
                    <div class="flex items-center justify-between">
                        <h2 class="font-serif text-base">ステータス分布</h2>
                        <span class="text-[10px] text-brand-text-muted flex items-center gap-1">
                            <PieChart :size="12" />直近30日
                        </span>
                    </div>
                </template>
                <StatusDistChart v-if="status_dist.length" :data="status_dist" />
                <div v-else class="h-32 flex items-center justify-center text-brand-text-muted text-sm">
                    データがありません
                </div>
            </UiCard>
        </section>

        <!-- LINE受信（未読のみ）＋ 最近の予約：左右2カラム -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <!-- LINE受信（お客様単位でまとめて展開・未読/既読をトグル切替） -->
            <UiCard variant="default" padding="none">
                <template #header>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-serif text-base flex items-center gap-2">
                            LINE受信
                            <UiBadge v-if="line_inbound.unread_total > 0" variant="success" size="sm">
                                未読 {{ line_inbound.unread_total }}
                            </UiBadge>
                        </h2>
                        <div class="flex items-center gap-2">
                            <!-- 未読のみトグル -->
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="lineUnreadOnly"
                                @click="lineUnreadOnly = !lineUnreadOnly"
                                class="flex items-center gap-1.5 text-[11px] text-brand-text-muted hover:text-brand-text transition-colors"
                            >
                                <span>未読のみ</span>
                                <span
                                    class="relative inline-flex h-4 w-7 flex-shrink-0 items-center rounded-full transition-colors"
                                    :class="lineUnreadOnly ? 'bg-uguisu-500' : 'bg-brand-border'"
                                >
                                    <span
                                        class="inline-block h-3 w-3 transform rounded-full bg-white shadow transition-transform"
                                        :class="lineUnreadOnly ? 'translate-x-3.5' : 'translate-x-0.5'"
                                    />
                                </span>
                            </button>
                            <MessageCircle :size="14" class="text-brand-text-muted" />
                        </div>
                    </div>
                </template>

                <div v-if="lineGroupsView.length === 0" class="p-6 text-center text-sm text-brand-text-muted">
                    {{ lineUnreadOnly ? '未読のLINEメッセージはありません。' : 'LINEメッセージはありません。' }}
                    <button
                        v-if="lineUnreadOnly && line_inbound.total > 0"
                        type="button"
                        @click="lineUnreadOnly = false"
                        class="block mx-auto mt-2 text-xs text-brand-primary hover:underline"
                    >
                        既読も表示する
                    </button>
                </div>
                <div v-else class="divide-y divide-brand-border">
                    <div v-for="g in lineGroupsView" :key="g.contact_id">
                        <!-- お客様の見出し行（クリックで展開） -->
                        <button
                            type="button"
                            class="w-full flex items-center gap-3 px-5 py-3 text-left hover:bg-brand-surface-2 transition-colors"
                            @click="toggleGroup(g.contact_id)"
                        >
                            <span
                                class="flex-shrink-0 w-1 self-stretch rounded-full"
                                :class="g.unread_count > 0 ? 'bg-uguisu-500' : 'bg-brand-border'"
                            ></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span
                                        class="text-sm font-medium truncate"
                                        :class="g.unread_count > 0 ? '' : 'text-brand-text-muted'"
                                    >{{ g.name }}</span>
                                    <UiBadge :variant="g.link_kind === 'reservation' ? 'primary' : 'neutral'" size="sm">
                                        {{ g.link_kind === 'reservation' ? '予約' : '顧客' }}
                                    </UiBadge>
                                    <UiBadge v-if="g.unread_count > 0" variant="success" size="sm">未読 {{ g.unread_count }}</UiBadge>
                                    <UiBadge v-else variant="neutral" size="sm">既読</UiBadge>
                                </div>
                                <div class="text-xs text-brand-text-muted truncate mt-0.5">
                                    <span v-if="g.latest_is_image" class="inline-flex items-center gap-1">
                                        <ImageIcon :size="12" /> 画像
                                    </span>
                                    <span v-else>{{ (g.latest_text && g.latest_text.trim()) ? g.latest_text : '（テキスト以外）' }}</span>
                                </div>
                            </div>
                            <div class="flex-shrink-0 flex flex-col items-end gap-1">
                                <span class="text-[11px] text-brand-text-muted whitespace-nowrap">{{ fmtRelative(g.latest_at) }}</span>
                                <ChevronDown
                                    :size="16"
                                    class="text-brand-text-muted transition-transform"
                                    :class="{ 'rotate-180': isExpanded(g.contact_id) }"
                                />
                            </div>
                        </button>

                        <!-- 展開部：そのお客様のメッセージ一覧（時系列・既読は淡色） -->
                        <div v-if="isExpanded(g.contact_id)" class="bg-brand-surface-2 px-5 py-3 space-y-2">
                            <div
                                v-for="msg in g.view_messages"
                                :key="msg.id"
                                class="rounded-md border px-3 py-2"
                                :class="msg.is_unread
                                    ? 'border-brand-border bg-brand-surface'
                                    : 'border-brand-border/60 bg-brand-surface/60'"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <p v-if="msg.is_image" class="flex items-center gap-1 text-sm text-brand-text-muted">
                                        <ImageIcon :size="14" /> 画像メッセージ
                                    </p>
                                    <p
                                        v-else
                                        class="text-sm whitespace-pre-wrap break-words flex-1"
                                        :class="msg.is_unread ? '' : 'text-brand-text-muted'"
                                    >
                                        {{ msg.text?.trim() ? msg.text : '（テキスト以外）' }}
                                    </p>
                                    <span class="flex-shrink-0 flex items-center gap-1 whitespace-nowrap">
                                        <span v-if="msg.is_unread" class="inline-block w-1.5 h-1.5 rounded-full bg-uguisu-500" title="未読"></span>
                                        <span class="text-[10px] text-brand-text-subtle">{{ fmtDateTime(msg.created_at) }}</span>
                                    </span>
                                </div>
                            </div>
                            <Link
                                v-if="(g.link_kind === 'reservation' && g.reservation_id) || g.customer_id"
                                :href="g.link_kind === 'reservation' && g.reservation_id
                                    ? route('admin.reservations.show', g.reservation_id)
                                    : route('admin.customers.show', g.customer_id)"
                                class="inline-flex items-center gap-1 text-xs text-brand-primary hover:underline mt-1"
                            >
                                {{ g.link_kind === 'reservation' ? '予約詳細を開く' : '顧客詳細を開く' }}
                                <ArrowRight :size="12" />
                            </Link>
                        </div>
                    </div>
                </div>
            </UiCard>

            <!-- 最近の予約 -->
            <UiCard variant="default" padding="none">
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

        </section>

        <!-- ヒートマップ ＋ 店舗別予約 -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ヒートマップ -->
            <UiCard variant="default" class="lg:col-span-2">
                <template #header>
                    <div class="flex items-center justify-between">
                        <h2 class="font-serif text-base">予約ヒートマップ</h2>
                        <span class="text-[10px] text-brand-text-muted flex items-center gap-1">
                            <Grid3x3 :size="12" />
                            {{ heatmap.period?.start }} 〜 {{ heatmap.period?.end }}
                        </span>
                    </div>
                </template>
                <ReservationHeatmap v-if="heatmap.max > 0" :cells="heatmap.cells" :max="heatmap.max" />
                <div v-else class="py-8 text-center text-sm text-brand-text-muted">
                    直近4週間に予約実績がありません。
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
