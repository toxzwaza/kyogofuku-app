<template>
    <Head :title="`送信先の選択: ${broadcast.title}`" />

    <AdminLayout :breadcrumb="[
        { label: '顧客' },
        { label: 'LINE広告', href: route('admin.line-broadcasts.index') },
        { label: broadcast.title, href: route('admin.line-broadcasts.show', broadcast.id) },
        { label: '送信先の選択' },
    ]">
        <UiPageHeader title="送信先の選択" :description="`広告「${broadcast.title}」の送信先を選択します。LINE連携済みの方のみ選択できます。`">
            <template #actions>
                <UiButton variant="ghost" size="sm" type="button" @click="previewOpen = !previewOpen">
                    {{ previewOpen ? 'プレビューを隠す' : 'プレビューを表示' }}
                </UiButton>
            </template>
        </UiPageHeader>

        <!-- 配信内容プレビュー（折りたたみ） -->
        <UiCard v-if="previewOpen" variant="default" padding="md" class="mb-4">
            <div class="rounded-xl p-4" style="background-color: #8cabd8;">
                <div class="flex items-start gap-2">
                    <div class="h-8 w-8 rounded-full bg-white/80 flex items-center justify-center text-xs font-bold text-[#8cabd8] shrink-0">呉</div>
                    <div class="space-y-2 max-w-[60%]">
                        <div v-if="broadcast.image_url" class="rounded-2xl overflow-hidden bg-white shadow">
                            <img :src="broadcast.image_url" class="w-full max-h-48 object-contain" alt="バナー画像" />
                        </div>
                        <div v-if="broadcast.text" class="rounded-2xl bg-white px-3 py-2 shadow text-sm text-gray-800 whitespace-pre-wrap break-words">{{ broadcast.text }}</div>
                    </div>
                </div>
            </div>
        </UiCard>

        <!-- タブ切り替え -->
        <div class="flex gap-1 mb-4">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="px-4 py-2 text-sm rounded-t-md border border-b-0 transition-colors"
                :class="activeTab === tab.key
                    ? 'bg-brand-surface text-brand-text font-medium border-brand-border'
                    : 'bg-brand-surface-2 text-brand-text-muted border-transparent hover:text-brand-text'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- ============ 顧客から選ぶ ============ -->
        <div v-show="activeTab === 'customers'" class="flex flex-col lg:flex-row-reverse gap-6 lg:items-start">
            <!-- 右：検索条件（顧客一覧と同配置） -->
            <aside class="w-full lg:w-[22rem] shrink-0 lg:sticky lg:top-4 lg:max-h-[calc(100vh-5rem)] lg:overflow-y-auto">
                <UiCard variant="default" padding="md">
                    <CustomerSearchFilterPanel
                        :form="customerFilter"
                        :shops="shops"
                        :plans="plans"
                        :users="users"
                        :ceremony-areas="ceremonyAreas"
                        :constraint-templates="constraintTemplates"
                        :tab-counts="customerFilterTabCounts"
                        id-prefix="broadcast-recipients"
                        @search="searchCustomers(1)"
                        @reset="resetCustomerFilter"
                    />
                </UiCard>
            </aside>

            <!-- 左：検索結果 -->
            <div class="flex-1 min-w-0 w-full">
                <UiCard variant="default" padding="none">
                    <div class="px-4 py-3 border-b border-brand-border flex flex-wrap items-center justify-between gap-2 bg-brand-surface-2/60">
                        <div class="flex items-center gap-3">
                            <h3 class="text-base font-semibold text-brand-text">検索結果</h3>
                            <p class="text-sm text-brand-text-muted">
                                全 <span class="font-medium text-brand-text tabular-nums">{{ customerResult.total }}</span> 件
                            </p>
                        </div>
                        <UiButton size="sm" variant="subtle" type="button" :disabled="!customerSendableRows.length" @click="selectAllCustomersOnPage">
                            このページの送信可能な顧客を全て選択
                        </UiButton>
                    </div>
                    <!-- 適用中の絞り込み条件（顧客一覧と同表示） -->
                    <div
                        v-if="customerFilterChips.length"
                        class="px-4 py-2.5 border-b border-brand-border bg-brand-surface flex flex-wrap items-center gap-1.5"
                    >
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-brand-text-muted mr-1">
                            <Filter :size="13" />
                            絞り込み中
                        </span>
                        <span
                            v-for="chip in customerFilterChips"
                            :key="chip.key"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-brand-primary/10 text-brand-primary text-xs font-medium"
                        >
                            <span class="text-brand-text-muted">{{ chip.label }}:</span>{{ chip.value }}
                        </span>
                    </div>

                    <div v-if="customerLoading" class="p-8 text-center text-sm text-brand-text-muted">検索中…</div>
                    <div v-else-if="!customerResult.data.length" class="p-8 text-center text-sm text-brand-text-muted">該当する顧客が見つかりません。</div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-brand-border text-left text-xs text-brand-text-muted">
                                <th class="px-4 py-2 w-10"></th>
                                <th class="px-4 py-2">顧客名</th>
                                <th class="px-4 py-2 hidden md:table-cell">ふりがな</th>
                                <th class="px-4 py-2 hidden md:table-cell">電話番号</th>
                                <th class="px-4 py-2 hidden md:table-cell">担当店舗</th>
                                <th class="px-4 py-2">LINE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in customerResult.data"
                                :key="row.id"
                                class="border-b border-brand-border last:border-b-0 transition-colors"
                                :class="rowClasses(row, 'c')"
                                @click="toggleRow(row, 'c')"
                            >
                                <td class="px-4 py-2.5">
                                    <input
                                        type="checkbox"
                                        class="rounded border-brand-border text-brand-primary focus:ring-brand-primary disabled:opacity-40"
                                        :checked="isSelected(row, 'c')"
                                        :disabled="!isSendable(row)"
                                        @click.stop="toggleRow(row, 'c')"
                                    />
                                </td>
                                <td class="px-4 py-2.5 font-medium">{{ row.name }}</td>
                                <td class="px-4 py-2.5 hidden md:table-cell text-brand-text-muted">{{ row.kana || '—' }}</td>
                                <td class="px-4 py-2.5 hidden md:table-cell tabular-nums text-brand-text-muted">{{ row.phone || '—' }}</td>
                                <td class="px-4 py-2.5 hidden md:table-cell text-brand-text-muted">{{ row.shop_name || '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <LineStatusBadge :row="row" />
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <SimplePagination
                        :current-page="customerResult.current_page"
                        :last-page="customerResult.last_page"
                        @change="searchCustomers"
                    />
                </UiCard>
            </div>
        </div>

        <!-- ============ イベント予約者から選ぶ ============ -->
        <div v-show="activeTab === 'reservations'" class="flex flex-col lg:flex-row-reverse gap-6 lg:items-start">
            <!-- 右：検索条件（顧客から選ぶと同配置） -->
            <aside class="w-full lg:w-[22rem] shrink-0 lg:sticky lg:top-4 lg:max-h-[calc(100vh-5rem)] lg:overflow-y-auto">
            <UiCard variant="default" padding="md">
                <form @submit.prevent="searchReservations(1)" class="space-y-3">
                    <UiFormField label="キーワード" hint="名前・ふりがな・電話番号で検索">
                        <UiInput v-model="reservationFilter.q" placeholder="例: 山田 / ヤマダ / 090…" size="sm" />
                    </UiFormField>
                    <UiFormField label="顧客登録">
                        <UiSelect
                            v-model="reservationFilter.customer_linked"
                            :options="[
                                { value: 'all',    label: '全て' },
                                { value: 'none',   label: '顧客登録なしのみ' },
                                { value: 'linked', label: '顧客登録ありのみ' },
                            ]"
                            size="sm"
                        />
                    </UiFormField>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-brand-text">
                                イベントで絞り込み（複数選択可）
                                <span v-if="reservationFilter.event_ids.length" class="ml-1 font-semibold text-brand-primary">{{ reservationFilter.event_ids.length }}件選択中</span>
                            </span>
                            <button type="button" class="text-xs text-brand-text-muted hover:underline" @click="reservationFilter.event_ids = []">全解除</button>
                        </div>
                        <!-- イベント一覧の絞り込み（開催店舗・状態） -->
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <div>
                                <label class="block text-xs font-medium text-brand-text mb-1">開催店舗</label>
                                <select v-model="eventShopFilter" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                                    <option :value="null">全て</option>
                                    <option v-for="shop in shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-brand-text mb-1">状態</label>
                                <select v-model="eventStatusFilter" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                                    <option :value="null">全て</option>
                                    <option value="upcoming">開催前</option>
                                    <option value="ongoing">開催中</option>
                                    <option value="ended">終了</option>
                                    <option value="unscheduled">日程未設定</option>
                                </select>
                            </div>
                        </div>
                        <!-- 開催日の新しい順のイベント選択テーブル -->
                        <div class="rounded-lg border border-brand-border max-h-80 overflow-y-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-brand-surface-2 sticky top-0 z-[1]">
                                    <tr class="text-left text-xs text-brand-text-muted">
                                        <th class="px-3 py-2 w-8"></th>
                                        <th class="px-3 py-2">イベント</th>
                                        <th class="px-3 py-2 text-right whitespace-nowrap">状態</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-brand-surface divide-y divide-brand-border">
                                    <tr v-if="!filteredEvents.length">
                                        <td colspan="3" class="px-3 py-6 text-center text-xs text-brand-text-muted">条件に合うイベントがありません</td>
                                    </tr>
                                    <tr
                                        v-for="ev in filteredEvents"
                                        :key="ev.id"
                                        class="cursor-pointer transition-colors align-top"
                                        :class="reservationFilter.event_ids.includes(ev.id) ? 'bg-brand-primary/5' : 'hover:bg-brand-surface-2'"
                                        @click="toggleEventFilter(ev.id)"
                                    >
                                        <td class="px-3 py-2">
                                            <input
                                                type="checkbox"
                                                :value="ev.id"
                                                v-model="reservationFilter.event_ids"
                                                class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                                                @click.stop
                                            />
                                        </td>
                                        <td class="px-3 py-2 min-w-0">
                                            <div class="font-medium text-brand-text">{{ ev.title }}</div>
                                            <div class="text-xs tabular-nums text-brand-text-muted">{{ formatEventPeriod(ev) }}</div>
                                            <div v-if="eventShopNames(ev)" class="text-xs text-brand-text-muted">{{ eventShopNames(ev) }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap">
                                            <UiBadge :variant="eventStatus(ev).variant" size="sm">{{ eventStatus(ev).label }}</UiBadge>
                                            <div class="mt-0.5 text-xs tabular-nums text-brand-text-muted">予約{{ ev.reservations_count ?? 0 }}件</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <UiButton variant="primary" size="sm" type="submit" class="flex-1">
                            <template #leading><Search :size="13" /></template>
                            検索
                        </UiButton>
                        <UiButton variant="ghost" size="sm" type="button" @click="resetReservationFilter">リセット</UiButton>
                    </div>
                </form>
            </UiCard>
            </aside>

            <!-- 左：検索結果 -->
            <div class="flex-1 min-w-0 w-full">
            <UiCard variant="default" padding="none">
                <div class="px-4 py-3 border-b border-brand-border flex flex-wrap items-center justify-between gap-2 bg-brand-surface-2/60">
                    <div class="flex items-center gap-3">
                        <h3 class="text-base font-semibold text-brand-text">検索結果</h3>
                        <p class="text-sm text-brand-text-muted">
                            全 <span class="font-medium text-brand-text tabular-nums">{{ reservationResult.total }}</span> 件
                        </p>
                    </div>
                    <UiButton size="sm" variant="subtle" type="button" :disabled="!reservationSendableRows.length" @click="selectAllReservationsOnPage">
                        このページの送信可能な予約者を全て選択
                    </UiButton>
                </div>

                <div v-if="reservationLoading" class="p-8 text-center text-sm text-brand-text-muted">検索中…</div>
                <div v-else-if="!reservationResult.data.length" class="p-8 text-center text-sm text-brand-text-muted">該当する予約者が見つかりません。</div>
                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-brand-border text-left text-xs text-brand-text-muted">
                            <th class="px-4 py-2 w-10"></th>
                            <th class="px-4 py-2">氏名</th>
                            <th class="px-4 py-2 hidden md:table-cell">ふりがな</th>
                            <th class="px-4 py-2 hidden lg:table-cell">電話番号</th>
                            <th class="px-4 py-2">イベント</th>
                            <th class="px-4 py-2 hidden md:table-cell">顧客登録</th>
                            <th class="px-4 py-2">LINE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in reservationResult.data"
                            :key="row.id"
                            class="border-b border-brand-border last:border-b-0 transition-colors"
                            :class="rowClasses(row, 'r')"
                            @click="toggleRow(row, 'r')"
                        >
                            <td class="px-4 py-2.5">
                                <input
                                    type="checkbox"
                                    class="rounded border-brand-border text-brand-primary focus:ring-brand-primary disabled:opacity-40"
                                    :checked="isSelected(row, 'r')"
                                    :disabled="!isSendable(row)"
                                    @click.stop="toggleRow(row, 'r')"
                                />
                            </td>
                            <td class="px-4 py-2.5 font-medium">{{ row.name || '—' }}</td>
                            <td class="px-4 py-2.5 hidden md:table-cell text-brand-text-muted">{{ row.furigana || '—' }}</td>
                            <td class="px-4 py-2.5 hidden lg:table-cell tabular-nums text-brand-text-muted">{{ row.phone || '—' }}</td>
                            <td class="px-4 py-2.5 text-xs text-brand-text-muted">{{ row.event_title || '—' }}</td>
                            <td class="px-4 py-2.5 hidden md:table-cell">
                                <UiBadge :variant="row.has_customer ? 'primary' : 'neutral'" size="sm">
                                    {{ row.has_customer ? '登録済' : 'なし' }}
                                </UiBadge>
                            </td>
                            <td class="px-4 py-2.5">
                                <LineStatusBadge :row="row" />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <SimplePagination
                    :current-page="reservationResult.current_page"
                    :last-page="reservationResult.last_page"
                    @change="searchReservations"
                />
            </UiCard>
            </div>
        </div>

        <!-- スペーサー（固定トレイと重ならないように） -->
        <div class="h-28"></div>

        <!-- ============ 選択トレイ（画面下部固定） ============ -->
        <Teleport to="body">
            <div class="fixed bottom-0 inset-x-0 z-40 border-t border-brand-border bg-brand-surface/95 backdrop-blur shadow-[0_-4px_12px_rgba(0,0,0,0.06)]">
                <div class="mx-auto max-w-6xl px-4 py-3 space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-sm">
                            <span class="font-semibold text-brand-text">選択中 {{ selectedEntries.length }}人</span>
                            <span class="ml-2 text-xs text-brand-text-muted">LINE未連携・送信済みの方は選択できません（送信されません）</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <UiButton size="sm" variant="ghost" type="button" :disabled="!selectedEntries.length" @click="clearSelection">すべて解除</UiButton>
                            <UiButton size="sm" variant="primary" type="button" :disabled="!selectedEntries.length" @click="confirmOpen = true">
                                <template #leading><Send :size="13" /></template>
                                確認して送信（{{ selectedEntries.length }}人）
                            </UiButton>
                        </div>
                    </div>
                    <div v-if="selectedEntries.length" class="flex flex-wrap gap-1.5 max-h-16 overflow-y-auto">
                        <span
                            v-for="entry in selectedEntries"
                            :key="entry.key"
                            class="inline-flex items-center gap-1 rounded-full bg-brand-surface-2 border border-brand-border px-2.5 py-0.5 text-xs text-brand-text"
                        >
                            {{ entry.label }}
                            <button type="button" class="text-brand-text-muted hover:text-brand-danger" @click="removeSelection(entry.key)">×</button>
                        </span>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ============ 送信確認ダイアログ ============ -->
        <UiDialog v-model:open="confirmOpen" title="一斉送信の確認">
            <div class="space-y-3 text-sm">
                <p>
                    広告「<span class="font-medium">{{ broadcast.title }}</span>」を
                    <span class="font-semibold text-brand-text">{{ selectedEntries.length }}人</span>
                    に送信します。
                </p>
                <ul class="list-disc pl-5 space-y-1 text-brand-text-muted text-xs">
                    <li>同一のLINEアカウントが重複して選択されている場合は自動で1通にまとめられます。</li>
                    <li>この広告を過去に受け取った方は自動でスキップされます（二重送信されません）。</li>
                    <li>LINE公式アカウントの月間メッセージ通数を消費します。</li>
                </ul>
                <p v-if="sendError" class="text-brand-danger">{{ sendError }}</p>
            </div>
            <template #footer>
                <UiButton variant="ghost" @click="confirmOpen = false">キャンセル</UiButton>
                <UiButton variant="primary" :loading="sending" @click="executeSend">送信する</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    UiPageHeader, UiButton, UiBadge, UiCard, UiDialog, UiFormField, UiInput, UiSelect,
} from '@/Components/UI';
import CustomerSearchFilterPanel from '@/Components/Admin/CustomerSearchFilterPanel.vue';
import { Filter, Search, Send } from 'lucide-vue-next';
import { buildCustomerFilterChips, countChipsByTab } from '@/composables/useCustomerFilterChips.js';
import { formatDateJa } from '@/utils/dateFormat.js';

const props = defineProps({
    broadcast:           { type: Object, required: true },
    shops:               { type: Array, default: () => [] },
    plans:               { type: Array, default: () => [] },
    users:               { type: Array, default: () => [] },
    ceremonyAreas:       { type: Array, default: () => [] },
    constraintTemplates: { type: Array, default: () => [] },
    events:              { type: Array, default: () => [] },
});

const tabs = [
    { key: 'customers', label: '顧客から選ぶ' },
    { key: 'reservations', label: 'イベント予約者から選ぶ' },
];
const activeTab = ref('customers');
const previewOpen = ref(false);

// ---------------------------------------------------------------
// LINE連携状態バッジ（行内表示用の小さなローカルコンポーネント）
// ---------------------------------------------------------------
const LineStatusBadge = defineComponent({
    props: { row: { type: Object, required: true } },
    setup(p) {
        return () => {
            if (!p.row.line_linked) {
                return h('span', { class: 'inline-flex items-center rounded-full bg-gray-100 border border-gray-200 px-2 py-0.5 text-xs text-gray-500' }, '未連携');
            }
            if (!p.row.sendable_contact_ids.length) {
                return h('span', { class: 'inline-flex items-center rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-xs text-blue-600' }, '送信済');
            }
            return h('span', { class: 'inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-xs text-emerald-700' }, '連携済 ✓');
        };
    },
});

// ---------------------------------------------------------------
// ページネーション（AJAX検索用の簡易版）
// ---------------------------------------------------------------
const SimplePagination = defineComponent({
    props: {
        currentPage: { type: Number, default: 1 },
        lastPage: { type: Number, default: 1 },
    },
    emits: ['change'],
    setup(p, { emit }) {
        return () => {
            if (p.lastPage <= 1) return null;
            return h('div', { class: 'flex items-center justify-center gap-2 px-4 py-3 border-t border-brand-border' }, [
                h('button', {
                    type: 'button',
                    class: 'px-3 py-1 text-sm rounded-soft border border-brand-border disabled:opacity-40',
                    disabled: p.currentPage <= 1,
                    onClick: () => emit('change', p.currentPage - 1),
                }, '前へ'),
                h('span', { class: 'text-sm text-brand-text-muted tabular-nums' }, `${p.currentPage} / ${p.lastPage}`),
                h('button', {
                    type: 'button',
                    class: 'px-3 py-1 text-sm rounded-soft border border-brand-border disabled:opacity-40',
                    disabled: p.currentPage >= p.lastPage,
                    onClick: () => emit('change', p.currentPage + 1),
                }, '次へ'),
            ]);
        };
    },
});

// ---------------------------------------------------------------
// 選択状態（タブやページを切り替えても保持する）
// key: 'c-{customerId}' / 'r-{reservationId}'
// ---------------------------------------------------------------
const selection = reactive(new Map());

const selectedEntries = computed(() => [...selection.values()]);

function selectionKey(row, kind) {
    return `${kind}-${row.id}`;
}

function isSendable(row) {
    return row.sendable_contact_ids?.length > 0;
}

function isSelected(row, kind) {
    return selection.has(selectionKey(row, kind));
}

function toggleRow(row, kind) {
    if (!isSendable(row)) return;
    const key = selectionKey(row, kind);
    if (selection.has(key)) {
        selection.delete(key);
    } else {
        selection.set(key, {
            key,
            label: row.name || '（名前未設定）',
            contactIds: [...row.sendable_contact_ids],
        });
    }
}

function removeSelection(key) {
    selection.delete(key);
}

function clearSelection() {
    selection.clear();
}

function rowClasses(row, kind) {
    if (!isSendable(row)) {
        return 'opacity-45 cursor-not-allowed bg-brand-surface-2/40';
    }
    return isSelected(row, kind)
        ? 'bg-brand-primary/5 cursor-pointer'
        : 'hover:bg-brand-surface-2/60 cursor-pointer';
}

// ---------------------------------------------------------------
// 顧客検索
// ---------------------------------------------------------------
const defaultCustomerFilter = () => ({
    name: '', kana: '', ceremony_prefecture: null, ceremony_area_id: [], phone_number: '',
    customer_shop_id: ['all'],
    created_at_from: '', created_at_to: '', full_body_photo_presence: null,
    seijin_preparation_venue: '', seijin_preparation_time: '', other_store_preparation: null,
    other_store_salon_name: '', kimono_ship_date: '',
    contract_status: null, contract_date_from: '', contract_date_to: '',
    contract_amount_min: '', contract_amount_max: '', shop_id: null,
    plan_id: null, kimono_type: null, warranty_flag: null, user_id: null,
    preparation_venue: '', preparation_date: '',
    constraint_presence: null, constraint_template_id: null,
    constraint_signed_at_from: '', constraint_signed_at_to: '', constraint_explainer_user_id: null,
    photo_slot_shop_id: null, photo_slot_details_undecided: null,
});

const customerFilter = reactive(defaultCustomerFilter());
const customerResult = reactive({ data: [], current_page: 1, last_page: 1, total: 0 });
const customerLoading = ref(false);
let customerInitialized = false;

// 実際に結果へ反映されている検索条件（検索レスポンスの applied）。チップ・タブバッジ表示用
const customerAppliedFilters = ref({});
const customerFilterChips = computed(() => buildCustomerFilterChips(customerAppliedFilters.value, {
    shops: props.shops,
    plans: props.plans,
    users: props.users,
    ceremonyAreas: props.ceremonyAreas,
    constraintTemplates: props.constraintTemplates,
}));
const customerFilterTabCounts = computed(() => countChipsByTab(customerFilterChips.value));

function customerSearchParams(page) {
    const params = { page };
    Object.entries(customerFilter).forEach(([key, value]) => {
        if (key === 'ceremony_prefecture') return; // UI専用
        if (value === null || value === '' || (Array.isArray(value) && !value.length)) return;
        params[key] = value;
    });
    return params;
}

async function searchCustomers(page = 1) {
    customerLoading.value = true;
    try {
        const params = customerSearchParams(page);
        // 初回のみ担当店舗フィルタ未指定でリクエストし、サーバー側のデフォルト店舗を適用させる
        if (!customerInitialized) delete params.customer_shop_id;
        const { data } = await axios.get(route('admin.line-broadcasts.search.customers', props.broadcast.id), { params });
        customerResult.data = data.customers.data;
        customerResult.current_page = data.customers.current_page;
        customerResult.last_page = data.customers.last_page;
        customerResult.total = data.customers.total;
        customerAppliedFilters.value = data.applied || {};
        if (!customerInitialized) {
            const applied = data.applied?.customer_shop_id;
            customerFilter.customer_shop_id = applied === 'all' ? ['all'] : (Array.isArray(applied) ? applied : [applied]).filter(Boolean);
            customerInitialized = true;
        }
    } catch (e) {
        console.error('顧客検索に失敗しました', e);
    } finally {
        customerLoading.value = false;
    }
}

function resetCustomerFilter() {
    Object.assign(customerFilter, defaultCustomerFilter());
    searchCustomers(1);
}

const customerSendableRows = computed(() => customerResult.data.filter(isSendable));

function selectAllCustomersOnPage() {
    customerSendableRows.value.forEach((row) => {
        const key = selectionKey(row, 'c');
        if (!selection.has(key)) toggleRow(row, 'c');
    });
}

// ---------------------------------------------------------------
// イベント予約者検索
// ---------------------------------------------------------------
const defaultReservationFilter = () => ({ q: '', customer_linked: 'all', event_ids: [] });
const reservationFilter = reactive(defaultReservationFilter());

// ---- イベント選択テーブル ----
function toggleEventFilter(id) {
    const idx = reservationFilter.event_ids.indexOf(id);
    if (idx >= 0) reservationFilter.event_ids.splice(idx, 1);
    else reservationFilter.event_ids.push(id);
}

function formatEventPeriod(ev) {
    const from = ev.start_at ? formatDateJa(ev.start_at) : null;
    const to = ev.end_at ? formatDateJa(ev.end_at) : null;
    if (from && to) return from === to ? from : `${from} 〜 ${to}`;
    return from || to || '—';
}

function eventStatusKey(ev) {
    if (!ev.start_at) return 'unscheduled';
    const now = new Date();
    if (new Date(ev.start_at) > now) return 'upcoming';
    if (ev.end_at && new Date(ev.end_at) < now) return 'ended';
    return 'ongoing';
}

const EVENT_STATUS_DISPLAY = {
    unscheduled: { label: '日程未設定', variant: 'neutral' },
    upcoming: { label: '開催前', variant: 'primary' },
    ongoing: { label: '開催中', variant: 'success' },
    ended: { label: '終了', variant: 'neutral' },
};

function eventStatus(ev) {
    return EVENT_STATUS_DISPLAY[eventStatusKey(ev)];
}

const eventShopNames = (ev) => (ev.shops || []).map((s) => s.name).join('・');

// イベント一覧の絞り込み（開催店舗・状態）
const eventShopFilter = ref(null);
const eventStatusFilter = ref(null);
const filteredEvents = computed(() => (props.events || []).filter((ev) => {
    if (eventShopFilter.value !== null && !(ev.shops || []).some((s) => s.id === eventShopFilter.value)) return false;
    if (eventStatusFilter.value !== null && eventStatusKey(ev) !== eventStatusFilter.value) return false;
    return true;
}));
const reservationResult = reactive({ data: [], current_page: 1, last_page: 1, total: 0 });
const reservationLoading = ref(false);

async function searchReservations(page = 1) {
    reservationLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.line-broadcasts.search.reservations', props.broadcast.id), {
            params: {
                page,
                q: reservationFilter.q || undefined,
                customer_linked: reservationFilter.customer_linked,
                event_ids: reservationFilter.event_ids.length ? reservationFilter.event_ids : undefined,
            },
        });
        reservationResult.data = data.reservations.data;
        reservationResult.current_page = data.reservations.current_page;
        reservationResult.last_page = data.reservations.last_page;
        reservationResult.total = data.reservations.total;
    } catch (e) {
        console.error('予約者検索に失敗しました', e);
    } finally {
        reservationLoading.value = false;
    }
}

function resetReservationFilter() {
    Object.assign(reservationFilter, defaultReservationFilter());
    eventShopFilter.value = null;
    eventStatusFilter.value = null;
    searchReservations(1);
}

const reservationSendableRows = computed(() => reservationResult.data.filter(isSendable));

function selectAllReservationsOnPage() {
    reservationSendableRows.value.forEach((row) => {
        const key = selectionKey(row, 'r');
        if (!selection.has(key)) toggleRow(row, 'r');
    });
}

// ---------------------------------------------------------------
// 送信実行
// ---------------------------------------------------------------
const confirmOpen = ref(false);
const sending = ref(false);
const sendError = ref('');

function executeSend() {
    const contactIds = [...new Set(selectedEntries.value.flatMap((e) => e.contactIds))];
    if (!contactIds.length) return;
    sending.value = true;
    sendError.value = '';
    router.post(route('admin.line-broadcasts.send', props.broadcast.id), {
        contact_ids: contactIds,
    }, {
        onError: (errors) => {
            sendError.value = errors.send || errors.contact_ids || '送信に失敗しました。';
        },
        onFinish: () => {
            sending.value = false;
        },
    });
}

onMounted(() => {
    searchCustomers(1);
    searchReservations(1);
});
</script>
