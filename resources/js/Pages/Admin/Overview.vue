<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    UiCard, UiBadge, UiPageHeader,
} from '@/Components/UI';
import UtmSourceChart from '@/Components/Admin/UtmSourceChart.vue';
import {
    AlertCircle, ArrowRight, Clock, BarChart3,
    ClipboardList, ImageOff, FileX, ShieldAlert, HeartPulse,
} from 'lucide-vue-next';

const props = defineProps({
    input_alerts: { type: Object, default: () => ({}) },
    care_list: { type: Array, default: () => [] },
    care_summary: { type: Object, default: () => ({}) },
    care_list_retention: { type: Array, default: () => [] },
    care_summary_retention: { type: Object, default: () => ({}) },
    care_generated_at: { type: String, default: null },
    user_shop_ids: { type: Array, default: () => [] },
    recent_reservations: { type: Array, default: () => [] },
    utm_dist: { type: Array, default: () => [] },
});

// 入力もれチェックカード。クリックで顧客一覧の該当フィルタへ遷移する。
// customer_shop_id を明示的に渡し、顧客一覧のデフォルト「メイン店舗のみ」適用を
// 回避してカードの数字と一覧件数を一致させる。所属店舗がない場合はリンクなし
// （デフォルト店舗フォールバックで全顧客が表示される事故を防ぐ）。
const alertCards = computed(() => {
    const hasShops = props.user_shop_ids.length > 0;
    const link = (extra) => hasShops
        ? route('admin.customers.index', { customer_shop_id: props.user_shop_ids, ...extra })
        : null;
    return [
        { key: 'pending_contracts',       title: '成約ステータス（保留）', value: props.input_alerts.pending_contracts ?? 0,
          icon: Clock,        link: link({ contract_status: '保留' }) },
        { key: 'undecided_photo_slots',   title: '前撮り詳細未決定',       value: props.input_alerts.undecided_photo_slots ?? 0,
          icon: ClipboardList, link: link({ photo_slot_details_undecided: '1' }) },
        { key: 'missing_full_body_photo', title: '全身写真 未登録',        value: props.input_alerts.missing_full_body_photo ?? 0,
          icon: ImageOff,     link: link({ full_body_photo_presence: '写真なし' }) },
        { key: 'missing_contract',        title: '成約情報 未登録',        value: props.input_alerts.missing_contract ?? 0,
          icon: FileX,        link: link({ contract_status: '成約なし' }) },
        { key: 'missing_constraint',      title: '制約情報 未登録',        value: props.input_alerts.missing_constraint ?? 0,
          icon: ShieldAlert,  link: link({ constraint_presence: '制約なし' }) },
    ];
});

// 今日のケアリスト（A9）：優先度バンド → バッジ配色
const careBandVariant = (band) => ({
    '緊急': 'danger', '高': 'warning', '中': 'accent', '低': 'neutral',
}[band] || 'neutral');

// ケアリスト：獲得（acquisition）／維持（retention）の切替タブ
const careTab = ref('acquisition');
const careList = computed(() => careTab.value === 'retention' ? props.care_list_retention : props.care_list);
const careSummary = computed(() => careTab.value === 'retention' ? props.care_summary_retention : props.care_summary);
const careTabTotal = (summary) => Object.values(summary || {}).reduce((a, b) => a + Number(b || 0), 0);
// 維持ケアは「ステータス」ではなく前撮りフェーズを表示するためラベルを出し分ける
const careStatusLabel = computed(() => careTab.value === 'retention' ? '前撮り' : 'ステータス');
const careDaysLabel = computed(() => careTab.value === 'retention' ? '最終接触' : '経過日数');
const careEmptyText = computed(() => careTab.value === 'retention'
    ? '維持フォローが必要なご成約者はいません。'
    : 'いま優先的に対応すべき案件はありません。');

// 直近の予約：初期分はサーバから受け取り、「もっと見る」で過去へ遡って追加取得する
const RECENT_PAGE = 8;
const recentList = ref([...props.recent_reservations]);
const recentHasMore = ref(props.recent_reservations.length >= RECENT_PAGE);
const recentLoading = ref(false);
const loadMoreRecent = async () => {
    if (recentLoading.value || !recentHasMore.value) return;
    recentLoading.value = true;
    try {
        const res = await window.axios.get(route('admin.recent-reservations'), {
            params: { offset: recentList.value.length, limit: RECENT_PAGE },
        });
        recentList.value.push(...(res.data.items || []));
        recentHasMore.value = !!res.data.has_more;
    } catch (e) {
        // 取得失敗時は現状維持
    } finally {
        recentLoading.value = false;
    }
};

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

// 日付のみ（MM/DD）。予約があった日（フォーム送信日）の表示用
const fmtDate = (s) => {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${mm}/${dd}`;
};

// 予約があった日からの経過日数（当日は0）
const daysSince = (s) => {
    if (!s) return null;
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d.getTime())) return null;
    return Math.floor((Date.now() - d.getTime()) / 86400000);
};

// 流入導線（utm_source）を読みやすい短いラベルに
const UTM_LABELS = {
    HP_TOP: 'HPトップ', HP_TOP_PICKUP: 'HP注目', AJ: 'AJ', DM: 'DM',
    BF_facebook: 'FB広告', BF_google: 'Google広告',
};
const utmLabel = (s) => {
    if (!s || s === 'NONE') return '直接・不明';
    if (String(s).startsWith('cf_utm_source')) return '広告';
    return UTM_LABELS[s] || s;
};

</script>

<template>
    <Head title="オーバービュー" />
    <AdminLayout :breadcrumb="[{ label: 'ホーム' }, { label: 'オーバービュー' }]">

        <UiPageHeader
            title="オーバービュー"
            description="今日ケアすべきお客様と、対応もれ・入力もれを一覧で把握できます。"
        />

        <!-- 入力もれチェック（最優先で確認する項目のため最上部に配置） -->
        <section class="mb-8">
            <h2 class="font-serif text-base mb-3 flex items-center gap-2">
                <AlertCircle :size="16" class="text-brand-warning" />
                入力もれチェック
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <component
                    :is="c.link ? Link : 'div'"
                    v-for="c in alertCards"
                    :key="c.key"
                    :href="c.link ?? undefined"
                    class="block"
                >
                    <UiCard variant="default" class="h-full" :class="c.link ? 'hover:border-brand-primary transition-colors cursor-pointer' : ''">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-xs text-brand-text-muted">{{ c.title }}</div>
                                <div
                                    class="mt-1.5 font-serif text-3xl leading-none"
                                    :class="c.value > 0 ? 'text-brand-warning' : ''"
                                >{{ c.value }}<span class="text-sm text-brand-text-muted ml-1">人</span></div>
                            </div>
                            <div class="w-10 h-10 rounded-soft bg-natane-50 dark:bg-natane-900 flex items-center justify-center shrink-0">
                                <component :is="c.icon" :size="20" class="text-brand-warning" />
                            </div>
                        </div>
                    </UiCard>
                </component>
            </div>
        </section>

        <!-- 直近の予約（左）＋ 今日のケアリスト（右）：左右2カラム -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 items-start">

            <!-- 直近の予約（過去へ遡って読み込み可） -->
            <UiCard variant="default" padding="none">
                <template #header>
                    <div class="flex items-center justify-between">
                        <h2 class="font-serif text-base">直近の予約</h2>
                        <Clock :size="14" class="text-brand-text-muted" />
                    </div>
                </template>
                <div v-if="recentList.length === 0" class="p-6 text-center text-sm text-brand-text-muted">
                    予約はありません。
                </div>
                <div v-else class="max-h-[28rem] overflow-y-auto">
                    <div class="divide-y divide-brand-border">
                        <Link
                            v-for="r in recentList"
                            :key="r.id"
                            :href="route('admin.reservations.show', r.id)"
                            class="flex items-center gap-3 px-5 py-3 hover:bg-brand-surface-2 transition-colors"
                        >
                            <!-- 予約があった日（フォーム送信日）＋その日からの経過 -->
                            <div class="flex-shrink-0 w-16 text-center">
                                <div class="text-xs text-brand-text-muted font-medium">{{ fmtDate(r.created_at) }}</div>
                                <div v-if="daysSince(r.created_at) != null" class="text-[10px] text-brand-text-subtle mt-0.5">
                                    {{ daysSince(r.created_at) === 0 ? '本日' : daysSince(r.created_at) + '日前' }}
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium truncate">{{ r.name || '(無記名)' }}</div>
                                <div class="text-xs text-brand-text-muted truncate">
                                    {{ r.event?.title || '未設定' }}<span v-if="r.venue?.name"> ／ {{ r.venue.name }}</span>
                                </div>
                                <!-- 予約日時（来店予定） -->
                                <div v-if="r.reservation_datetime" class="text-[11px] text-brand-text-subtle truncate mt-0.5">
                                    予約日時: {{ fmtDateTime(r.reservation_datetime) }}
                                </div>
                                <!-- LINE連携有無・流入導線 -->
                                <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                    <UiBadge :variant="r.line_linked ? 'success' : 'neutral'" size="sm">
                                        {{ r.line_linked ? 'LINE連携済' : 'LINE未連携' }}
                                    </UiBadge>
                                    <UiBadge variant="neutral" size="sm">導線: {{ utmLabel(r.utm_source) }}</UiBadge>
                                </div>
                            </div>
                            <UiBadge :variant="statusVariant(r.status)" size="sm">{{ r.status }}</UiBadge>
                        </Link>
                    </div>
                    <!-- もっと見る（過去の予約を追加取得） -->
                    <div v-if="recentHasMore" class="p-3 text-center border-t border-brand-border">
                        <button
                            type="button"
                            class="text-xs text-brand-primary hover:underline disabled:opacity-50"
                            :disabled="recentLoading"
                            @click="loadMoreRecent"
                        >
                            {{ recentLoading ? '読み込み中…' : 'もっと見る' }}
                        </button>
                    </div>
                </div>
            </UiCard>

            <!-- 今日のケアリスト（A9：成約見込み×放置度×緊急度で今ケアすべき顧客を提案） -->
            <UiCard variant="elevated" padding="none">
                <template #header>
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <h2 class="font-serif text-base flex items-center gap-2">
                            <HeartPulse :size="18" class="text-brand-primary" />
                            今日のケアリスト
                        </h2>
                        <div class="flex items-center gap-2 text-xs">
                            <UiBadge v-if="careSummary['緊急']" variant="danger" size="sm">緊急 {{ careSummary['緊急'] }}</UiBadge>
                            <UiBadge v-if="careSummary['高']" variant="warning" size="sm">高 {{ careSummary['高'] }}</UiBadge>
                            <UiBadge v-if="careSummary['中']" variant="accent" size="sm">中 {{ careSummary['中'] }}</UiBadge>
                            <span v-if="care_generated_at" class="text-brand-text-muted">更新 {{ care_generated_at }}</span>
                        </div>
                    </div>
                    <!-- イベント予約者ケア（成約前リード）／顧客ケア（成約後キャンセル防止）の切替 -->
                    <div class="mt-3 flex gap-1 border-b border-brand-border -mb-px">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
                            :class="careTab === 'acquisition' ? 'border-brand-primary text-brand-primary' : 'border-transparent text-brand-text-muted hover:text-brand-text'"
                            @click="careTab = 'acquisition'"
                        >
                            イベント予約者ケア
                            <span class="ml-1 text-xs text-brand-text-muted">{{ careTabTotal(props.care_summary) }}</span>
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
                            :class="careTab === 'retention' ? 'border-brand-primary text-brand-primary' : 'border-transparent text-brand-text-muted hover:text-brand-text'"
                            @click="careTab = 'retention'"
                        >
                            顧客ケア
                            <span class="ml-1 text-xs text-brand-text-muted">{{ careTabTotal(props.care_summary_retention) }}</span>
                        </button>
                    </div>
                </template>

                <div v-if="!careList.length" class="p-6 text-center text-sm text-brand-text-muted">
                    {{ careEmptyText }}
                </div>
                <ul v-else class="divide-y divide-brand-border max-h-[28rem] overflow-y-auto">
                    <li v-for="c in careList" :key="c.id" class="flex items-start gap-3 px-5 py-3 hover:bg-brand-surface-2 transition-colors">
                        <UiBadge :variant="careBandVariant(c.band)" size="sm" class="shrink-0 mt-0.5">{{ c.band }}</UiBadge>
                        <div class="flex-1 min-w-0">
                            <!-- 氏名＋見込み・成人式年度 -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-sm">{{ c.name || '（お名前未登録）' }}</span>
                                <UiBadge v-if="c.prospect" variant="neutral" size="sm">{{ c.prospect }}</UiBadge>
                                <span v-if="c.seijin_year" class="text-xs text-brand-text-muted">{{ c.seijin_year }}年成人式</span>
                            </div>
                            <!-- ラベル分割表示：ステータス／経過日数／推奨対応 -->
                            <dl class="mt-1 grid grid-cols-[auto,1fr] gap-x-2 gap-y-0.5 text-sm">
                                <dt class="text-brand-text-muted">{{ careStatusLabel }}:</dt>
                                <dd>{{ c.status || '—' }}</dd>
                                <dt class="text-brand-text-muted">{{ careDaysLabel }}:</dt>
                                <dd>{{ c.days != null ? c.days + '日' : '—' }}</dd>
                                <dt class="text-brand-text-muted">推奨対応:</dt>
                                <dd class="font-medium">{{ c.next_action }}</dd>
                            </dl>
                        </div>
                        <Link
                            v-if="c.reservation_id"
                            :href="route('admin.reservations.show', c.reservation_id)"
                            class="shrink-0 mt-1 text-brand-primary text-xs inline-flex items-center gap-1 hover:underline"
                        >
                            詳細<ArrowRight :size="14" />
                        </Link>
                        <Link
                            v-else-if="c.customer_id"
                            :href="route('admin.customers.show', c.customer_id)"
                            class="shrink-0 mt-1 text-brand-primary text-xs inline-flex items-center gap-1 hover:underline"
                        >
                            詳細<ArrowRight :size="14" />
                        </Link>
                    </li>
                </ul>
            </UiCard>
        </section>

        <!-- 流入経路別の予約者数 -->
        <section class="mb-6">
            <UiCard variant="default">
                <template #header>
                    <div class="flex items-center justify-between">
                        <h2 class="font-serif text-base">流入経路別の予約者数</h2>
                        <span class="text-[10px] text-brand-text-muted flex items-center gap-1">
                            <BarChart3 :size="12" />直近30日・キャンセル除く
                        </span>
                    </div>
                </template>
                <UtmSourceChart v-if="utm_dist.length" :data="utm_dist" />
                <div v-else class="h-32 flex items-center justify-center text-brand-text-muted text-sm">
                    データがありません
                </div>
            </UiCard>
        </section>

    </AdminLayout>
</template>
