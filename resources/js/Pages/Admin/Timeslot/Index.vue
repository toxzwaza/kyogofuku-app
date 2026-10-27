<template>
    <Head title="予約枠一覧" />

    <AdminLayout :breadcrumb="[{ label: 'イベント・予約' }, { label: event.title, href: route('admin.events.show', event.id) }, { label: '予約枠' }]">
        <!-- 操作結果（画面右上に固定・枠増減・削除の結果） -->
        <div class="fixed top-4 right-4 z-50 space-y-2 max-w-sm w-full sm:max-w-md pointer-events-none" aria-live="polite">
            <div v-if="localSuccessMessage" class="rounded-lg bg-uguisu-50 p-3 border border-brand-success shadow-lg flex items-center justify-between gap-3 pointer-events-auto">
                <p class="text-sm font-medium text-brand-text flex-1 min-w-0">{{ localSuccessMessage }}</p>
                <button type="button" class="p-1 rounded hover:bg-brand-surface-2" aria-label="閉じる" @click="localSuccessMessage = ''"><X :size="16" /></button>
            </div>
            <div v-if="localErrorMessage" class="rounded-lg bg-akane-50 p-3 border border-brand-danger shadow-lg flex items-center justify-between gap-3 pointer-events-auto">
                <p class="text-sm font-medium text-brand-danger flex-1 min-w-0">{{ localErrorMessage }}</p>
                <button type="button" class="p-1 rounded hover:bg-brand-surface-2" aria-label="閉じる" @click="localErrorMessage = ''"><X :size="16" /></button>
            </div>
        </div>

        <UiPageHeader :title="`予約枠一覧 - ${event.title}`" description="行が日付、列が会場です。時刻をクリックすると定員の増減・編集・削除ができます。">
            <template #actions>
                <Link :href="route('admin.events.timeslots.bulk', event.id)">
                    <UiButton variant="primary"><template #leading><CalendarPlus :size="14" /></template>期間一括作成</UiButton>
                </Link>
                <Link :href="route('admin.events.timeslots.create', event.id)">
                    <UiButton variant="ghost"><template #leading><Plus :size="14" /></template>個別追加</UiButton>
                </Link>
                <Link :href="route('admin.events.show', event.id)">
                    <UiButton variant="ghost">イベント詳細へ戻る</UiButton>
                </Link>
            </template>
        </UiPageHeader>

        <div class="space-y-4">
            <div v-if="$page.props.flash?.success" class="rounded-md bg-uguisu-50 border border-brand-success p-3 text-sm text-brand-text">{{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.errorMessages?.length" class="rounded-md bg-amber-50 border border-amber-300 p-3">
                <p class="text-sm font-medium text-amber-800 mb-1">スキップされた予約枠の詳細</p>
                <ul class="list-disc list-inside space-y-0.5">
                    <li v-for="(error, index) in $page.props.flash.errorMessages" :key="index" class="text-sm text-amber-800">{{ error }}</li>
                </ul>
            </div>
            <div v-if="$page.props.errors?.error" class="rounded-md bg-akane-50 border border-brand-danger p-3 text-sm text-brand-danger">{{ $page.props.errors.error }}</div>

            <UiCard variant="default" padding="md">
                <EventNavigation :event="event" :show-url-button="false" />
            </UiCard>

            <UiCard variant="default" padding="md">
                <!-- ツールバー -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-3 mb-3">
                    <div class="inline-flex rounded-lg border border-brand-border overflow-hidden text-sm">
                        <button
                            v-for="opt in [{ key: 'board', label: '日付×会場' }, { key: 'calendar', label: 'カレンダー' }]"
                            :key="opt.key"
                            type="button"
                            class="px-4 py-1.5"
                            :class="viewMode === opt.key ? 'bg-brand-primary text-white' : 'bg-brand-surface text-brand-text hover:bg-brand-surface-2'"
                            @click="viewMode = opt.key"
                        >{{ opt.label }}</button>
                    </div>

                    <div v-if="venueOptions.length > 1" class="flex flex-wrap items-center gap-1.5">
                        <span class="text-xs text-brand-text-muted">会場</span>
                        <label v-for="v in venueOptions" :key="v.key" :class="chipClass(shownVenueKeys.includes(v.key))">
                            <input v-model="shownVenueKeys" type="checkbox" :value="v.key" class="sr-only" />
                            {{ v.name }}
                        </label>
                    </div>

                    <label class="inline-flex items-center gap-1.5 text-sm text-brand-text ml-auto">
                        <input type="checkbox" :checked="includePast" class="rounded border-brand-border text-brand-primary" @change="togglePast($event.target.checked)" />
                        終了した日も表示<span v-if="!includePast && pastCount" class="text-brand-text-muted">（{{ pastCount }}枠）</span>
                    </label>
                </div>

                <!-- 状態で絞り込み（色の凡例を兼ねる） -->
                <div class="flex flex-wrap items-center gap-1.5 mb-3 text-xs">
                    <span class="text-brand-text-muted mr-1">状態</span>
                    <button
                        v-for="st in STATUSES"
                        :key="st.key"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border transition-colors"
                        :class="statusFilter.includes(st.key) ? 'border-brand-primary bg-ai-50 text-brand-primary' : 'border-brand-border bg-brand-surface text-brand-text hover:bg-brand-surface-2'"
                        :aria-pressed="statusFilter.includes(st.key)"
                        @click="toggleStatus(st.key)"
                    >
                        <span class="w-3 h-3 rounded-sm border" :class="st.swatch"></span>
                        {{ st.label }}
                        <span class="tabular-nums text-brand-text-muted">{{ statusCounts[st.key] }}</span>
                    </button>
                    <button v-if="statusFilter.length" type="button" class="ml-1 text-brand-primary hover:underline" @click="statusFilter = []">状態の絞り込みを解除</button>
                    <span class="ml-auto text-brand-text-muted tabular-nums">
                        表示中 <strong class="text-brand-text">{{ totals.days }}</strong>日・<strong class="text-brand-text">{{ totals.slots }}</strong>枠／
                        予約 <strong class="text-brand-text">{{ totals.reserved }}</strong>・受付中の残り <strong class="text-brand-text">{{ totals.remaining }}</strong>
                    </span>
                </div>

                <!-- 日付へ移動 -->
                <div v-if="viewMode === 'board'" class="flex flex-wrap items-center gap-2 mb-3 text-sm">
                    <UiButton size="sm" variant="ghost" @click="jumpToToday">今日へ</UiButton>
                    <select
                        v-if="monthOptions.length > 1"
                        class="rounded-md border-brand-border text-sm py-1 pl-2 pr-8"
                        :value="''"
                        @change="jumpToMonth($event.target.value); $event.target.value = ''"
                    >
                        <option value="">月へ移動…</option>
                        <option v-for="m in monthOptions" :key="m.key" :value="m.key">{{ m.label }}</option>
                    </select>
                    <span v-if="jumpMessage" class="text-xs text-brand-text-muted">{{ jumpMessage }}</span>
                    <template v-if="selectedDate">
                        <span class="px-2 py-0.5 rounded bg-ai-50 border border-brand-primary text-brand-primary">{{ formatDay(selectedDate) }} のみ表示中</span>
                        <button type="button" class="text-brand-primary hover:underline" @click="selectedDate = ''">すべて表示</button>
                    </template>
                </div>

                <TimeslotMonthCalendar
                    v-if="viewMode === 'calendar'"
                    :timeslots="filteredSlots"
                    :selected-date="selectedDate"
                    @select="(d) => { selectedDate = d; viewMode = 'board'; }"
                />

                <template v-else-if="boardRows.length">
                    <div class="overflow-x-auto border border-brand-border rounded-lg">
                        <table class="min-w-full text-sm">
                            <thead class="bg-brand-surface-2">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium whitespace-nowrap w-36">日付</th>
                                    <th v-for="v in shownVenues" :key="v.key" class="px-3 py-2 text-left font-medium whitespace-nowrap">{{ v.name }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="row in boardRows" :key="row.date">
                                    <tr v-if="row.monthHeader" :id="`m-${row.date.slice(0, 7)}`" class="bg-brand-surface-2/60 scroll-mt-4">
                                        <td :colspan="shownVenues.length + 1" class="px-3 py-1 text-xs font-semibold text-brand-text-muted">{{ row.monthHeader }}</td>
                                    </tr>
                                    <tr
                                        :id="`d-${row.date}`"
                                        class="border-t border-brand-border align-top scroll-mt-4 transition-colors"
                                        :class="flashDate === row.date ? 'bg-ai-50' : row.date === today ? 'bg-amber-50/60' : row.past ? 'bg-brand-surface-2/30' : ''"
                                    >
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <div class="tabular-nums font-medium" :class="weekdayClass(row.date)">
                                                {{ formatDay(row.date) }}
                                                <span v-if="row.date === today" class="ml-1 text-[10px] px-1 rounded bg-amber-200 text-amber-900">今日</span>
                                                <AlertTriangle v-if="hasOverlapOn(row.date)" :size="12" class="inline ml-1 -mt-0.5 text-amber-600" aria-label="別イベントと重なる日" />
                                            </div>
                                            <div class="text-[11px] text-brand-text-muted tabular-nums">予約{{ row.reserved }}／残{{ row.remaining }}</div>
                                            <button
                                                type="button"
                                                class="mt-1 text-[11px] text-brand-primary hover:underline"
                                                @click="openBulk(`${formatDay(row.date)} の全会場`, row.allSlots)"
                                            >この日をまとめて変更</button>
                                        </td>
                                        <td v-for="v in shownVenues" :key="v.key" class="px-2 py-2">
                                            <div
                                                v-if="overlapsAt(row.date, v.key).length"
                                                class="mb-1 inline-flex items-center gap-1 max-w-[16rem] px-1.5 py-0.5 rounded bg-amber-50 border border-amber-300 text-[11px] text-amber-900"
                                                :title="overlapTitle(row.date, v.key)"
                                            >
                                                <AlertTriangle :size="12" class="shrink-0" />
                                                <span class="truncate">別イベント：{{ overlapsAt(row.date, v.key).map((o) => o.title).join('、') }}</span>
                                            </div>
                                            <div class="flex flex-wrap items-start gap-1">
                                                <button
                                                    v-for="t in row.cells[v.key] || []"
                                                    :key="t.id"
                                                    type="button"
                                                    class="inline-flex flex-col items-center min-w-[3.75rem] px-1.5 py-0.5 rounded border text-xs leading-tight transition-shadow"
                                                    :class="[slotClass(t), selectedId === t.id ? 'ring-2 ring-brand-primary ring-offset-1' : 'hover:shadow']"
                                                    :title="`${timeOf(t)} 定員${t.capacity}・予約${t.reserved_count}・残${t.remaining_capacity}${t.is_active ? '' : '（受付停止）'}`"
                                                    @click="selectedId = selectedId === t.id ? null : t.id"
                                                >
                                                    <span class="font-semibold tabular-nums">{{ timeOf(t) }}</span>
                                                    <span class="tabular-nums text-[10px] opacity-80">{{ t.reserved_count }}/{{ t.capacity }}</span>
                                                </button>
                                                <span v-if="!(row.cells[v.key] || []).length" class="text-xs text-brand-text-subtle px-1 py-1">—</span>
                                                <button
                                                    v-if="(row.allCells[v.key] || []).length"
                                                    type="button"
                                                    class="self-center p-1 rounded text-brand-text-muted hover:bg-brand-surface-2 hover:text-brand-text"
                                                    :title="`${formatDay(row.date)} ${v.name} の枠をまとめて変更`"
                                                    :aria-label="`${formatDay(row.date)} ${v.name} の枠をまとめて変更`"
                                                    @click="openBulk(`${formatDay(row.date)} ${v.name}`, row.allCells[v.key])"
                                                ><SlidersHorizontal :size="14" /></button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-brand-text-muted">時刻の下の数字は「予約数／定員」です。<SlidersHorizontal :size="12" class="inline -mt-0.5" /> でその日・会場の枠をまとめて受付停止／再開・定員変更できます。</p>
                </template>

                <div v-else class="text-center py-12 text-brand-text-muted">
                    <template v-if="localTimeslots.length || pastCount">表示する予約枠がありません<span v-if="!includePast && pastCount">（終了した日の枠は「終了した日も表示」で確認できます）</span></template>
                    <template v-else>予約枠が登録されていません</template>
                </div>
            </UiCard>
        </div>

        <!-- 選択中の枠の操作（画面下に固定） -->
        <div v-if="selectedSlot" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 w-[min(60rem,calc(100%-2rem))]">
            <div class="rounded-xl border border-brand-border bg-brand-surface shadow-xl px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="min-w-0">
                    <div class="font-semibold text-brand-text tabular-nums">
                        {{ formatDay(dateOf(selectedSlot)) }} {{ timeOf(selectedSlot) }}
                        <span class="ml-1 text-sm font-normal text-brand-text-muted">{{ selectedSlot.venue?.name || '会場未設定' }}</span>
                    </div>
                    <div class="text-xs text-brand-text-muted tabular-nums">
                        定員 {{ selectedSlot.capacity }}・予約 {{ selectedSlot.reserved_count }}・残り
                        <span :class="selectedSlot.remaining_capacity > 0 ? 'text-brand-success font-semibold' : 'text-brand-danger font-semibold'">{{ selectedSlot.remaining_capacity }}</span>
                        <span class="ml-2 px-1.5 py-0.5 rounded-full" :class="selectedSlot.is_active ? 'bg-uguisu-50 text-brand-success' : 'bg-brand-surface-2 text-brand-text-muted'">{{ selectedSlot.is_active ? '受付中' : '受付停止' }}</span>
                        <span class="ml-2 text-brand-text-subtle">ID {{ selectedSlot.id }}</span>
                    </div>
                    <div v-if="overlapsAt(dateOf(selectedSlot), venueKeyOf(selectedSlot)).length" class="text-[11px] text-amber-800 mt-0.5">
                        別イベントあり：{{ overlapTitle(dateOf(selectedSlot), venueKeyOf(selectedSlot)) }}
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 ml-auto">
                    <Link :href="reservationsUrl(selectedSlot)" class="text-sm text-brand-primary hover:underline mr-1" title="キャンセル済みの予約も一覧に含まれます">予約一覧で見る（{{ selectedSlot.reserved_count }}件）</Link>
                    <UiButton size="sm" variant="ghost" :disabled="selectedSlot.capacity <= Math.max(1, selectedSlot.reserved_count) || adjustingTimeslotId === selectedSlot.id" title="定員を1減らす" @click="adjustCapacity(selectedSlot.id, -1)"><Minus :size="14" /></UiButton>
                    <UiButton size="sm" variant="ghost" :disabled="adjustingTimeslotId === selectedSlot.id" title="定員を1増やす" @click="adjustCapacity(selectedSlot.id, 1)"><Plus :size="14" /></UiButton>
                    <UiButton size="sm" variant="ghost" :disabled="adjustingTimeslotId === selectedSlot.id" title="定員を5増やす" @click="adjustCapacity(selectedSlot.id, 5)">+5</UiButton>
                    <Link :href="`${route('admin.events.timeslots.create', event.id)}?duplicate=${selectedSlot.id}`">
                        <UiButton size="sm" variant="ghost" title="複製"><template #leading><Copy :size="14" /></template>複製</UiButton>
                    </Link>
                    <Link :href="editUrl(selectedSlot)">
                        <UiButton size="sm" variant="ghost" title="編集"><template #leading><Pencil :size="14" /></template>編集</UiButton>
                    </Link>
                    <UiButton
                        size="sm"
                        variant="danger"
                        :disabled="deletingTimeslotId === selectedSlot.id || selectedSlot.reserved_count > 0"
                        :title="selectedSlot.reserved_count > 0 ? '予約が存在するため削除できません' : '削除'"
                        @click="deleteTimeslot(selectedSlot.id)"
                    ><template #leading><Trash2 :size="14" /></template>削除</UiButton>
                    <button type="button" class="p-1.5 rounded hover:bg-brand-surface-2" aria-label="閉じる" @click="selectedId = null"><X :size="16" /></button>
                </div>
            </div>
        </div>

        <!-- まとめて変更（対象と変更前後を確認してから実行） -->
        <UiDialog v-model:open="bulk.open" :title="`まとめて変更：${bulk.title}`" size="lg">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <label v-for="a in BULK_ACTIONS" :key="a.key" class="inline-flex items-center gap-1.5">
                        <input v-model="bulk.action" type="radio" :value="a.key" class="text-brand-primary" />
                        {{ a.label }}
                    </label>
                    <span v-if="bulk.action === 'inc' || bulk.action === 'dec'" class="inline-flex items-center gap-1">
                        <input v-model.number="bulk.amount" type="number" min="1" max="999" class="w-20 rounded-md border-brand-border text-sm py-1" />
                        <span class="text-brand-text-muted">人</span>
                    </span>
                </div>
                <p v-if="bulk.action === 'deactivate'" class="text-xs text-brand-text-muted">受付停止は予約フォームに枠を出さなくするだけで、入っている予約はキャンセルされません。</p>
                <p v-if="bulk.action === 'activate'" class="text-xs text-amber-800">意図して停止していた枠も再開されます。対象のチェックを確認してください。</p>
                <p v-if="bulk.action === 'dec'" class="text-xs text-brand-text-muted">予約数を下回る枠・定員が1未満になる枠は変更しません。</p>

                <div class="max-h-[50vh] overflow-y-auto border border-brand-border rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-brand-surface-2 sticky top-0">
                            <tr>
                                <th class="px-2 py-1.5 w-8"><input type="checkbox" aria-label="すべて選択" :checked="bulk.ids.length === bulk.slots.length" @change="bulk.ids = $event.target.checked ? bulk.slots.map((s) => s.id) : []" /></th>
                                <th class="px-2 py-1.5 text-left font-medium">日時・会場</th>
                                <th class="px-2 py-1.5 text-left font-medium">予約</th>
                                <th class="px-2 py-1.5 text-left font-medium">変更前 → 変更後</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in bulkPreview" :key="p.slot.id" class="border-t border-brand-border" :class="p.checked && !p.change ? 'text-brand-text-muted' : ''">
                                <td class="px-2 py-1.5"><input v-model="bulk.ids" type="checkbox" :value="p.slot.id" /></td>
                                <td class="px-2 py-1.5 whitespace-nowrap tabular-nums">{{ formatDay(dateOf(p.slot)) }} {{ timeOf(p.slot) }} <span class="text-xs text-brand-text-muted">{{ p.slot.venue?.name || '会場未設定' }}</span></td>
                                <td class="px-2 py-1.5 tabular-nums">{{ p.slot.reserved_count }}</td>
                                <td class="px-2 py-1.5">
                                    <template v-if="!p.checked"><span class="text-brand-text-subtle">対象外</span></template>
                                    <template v-else-if="p.change"><span class="tabular-nums">{{ p.before }} → <strong>{{ p.after }}</strong></span></template>
                                    <template v-else><span class="text-xs">{{ p.reason }}</span></template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="bulk.error" class="text-sm text-brand-danger">{{ bulk.error }}</p>
            </div>
            <template #footer>
                <span class="mr-auto text-sm text-brand-text-muted">変更される枠：<strong class="text-brand-text">{{ bulkChangeIds.length }}</strong>件</span>
                <UiButton variant="ghost" @click="bulk.open = false">キャンセル</UiButton>
                <UiButton :variant="bulk.action === 'deactivate' ? 'danger' : 'primary'" :disabled="!bulkChangeIds.length || bulk.sending" @click="runBulk">
                    {{ bulk.sending ? '実行中…' : '実行する' }}
                </UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { UiPageHeader, UiCard, UiButton, UiDialog } from '@/Components/UI';
import { AlertTriangle, CalendarPlus, Copy, Minus, Pencil, Plus, SlidersHorizontal, Trash2, X } from 'lucide-vue-next';
import EventNavigation from '@/Components/EventNavigation.vue';
import TimeslotMonthCalendar from '@/Components/TimeslotMonthCalendar.vue';

const props = defineProps({
    event: Object,
    timeslots: Array,
    venues: { type: Array, default: () => [] },
    includePast: { type: Boolean, default: false },
    pastCount: { type: Number, default: 0 },
    // 同じ店舗の別イベントと重なる日: { 'YYYY-MM-DD': { 会場ID or 'none': [{ title, venue_names, reason }] } }
    overlaps: { type: [Object, Array], default: () => ({}) },
});

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];
// 状態は重ならないように分ける（受付停止を優先し、受付中の枠を予約状況で分類）
const STATUSES = [
    { key: 'empty', label: '予約なし', swatch: 'bg-brand-surface border-brand-border-strong' },
    { key: 'booked', label: '予約あり（空きあり）', swatch: 'bg-ai-50 border-brand-primary' },
    { key: 'full', label: '満席', swatch: 'bg-akane-50 border-brand-danger' },
    { key: 'stopped', label: '受付停止', swatch: 'bg-brand-surface-2 border-dashed border-brand-border' },
];
const BULK_ACTIONS = [
    { key: 'deactivate', label: '受付停止' },
    { key: 'activate', label: '受付再開' },
    { key: 'inc', label: '定員を増やす' },
    { key: 'dec', label: '定員を減らす' },
];

const localTimeslots = ref(props.timeslots ? [...props.timeslots] : []);
watch(() => props.timeslots, (next) => {
    localTimeslots.value = next && next.length ? [...next] : [];
});

const adjustingTimeslotId = ref(null);
const deletingTimeslotId = ref(null);
const localSuccessMessage = ref('');
const localErrorMessage = ref('');
const selectedId = ref(null);

// 日付・時刻は店舗の時刻（start_local）で扱う
const dateOf = (t) => t.start_local.slice(0, 10);
const timeOf = (t) => t.start_local.slice(11, 16);
const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Tokyo' }).format(new Date());
const venueKeyOf = (t) => (t.venue_id ? String(t.venue_id) : 'none');
const statusOf = (t) => {
    if (!t.is_active) return 'stopped';
    if (t.remaining_capacity <= 0) return 'full';
    return t.reserved_count > 0 ? 'booked' : 'empty';
};

// 表示条件はURLに残す（編集画面から戻っても・再読込しても同じ表示になるように）
const initial = new URLSearchParams(window.location.search);
const viewMode = ref(initial.get('view') === 'calendar' ? 'calendar' : 'board');
const selectedDate = ref(/^\d{4}-\d{2}-\d{2}$/.test(initial.get('d') || '') ? initial.get('d') : '');
const statusFilter = ref((initial.get('s') || '').split(',').filter((k) => STATUSES.some((s) => s.key === k)));

// 会場の列：イベントの会場＋枠にだけある会場（会場未設定を含む）
const venueOptions = computed(() => {
    const list = props.venues.map((v) => ({ key: String(v.id), name: v.name }));
    localTimeslots.value.forEach((t) => {
        const key = venueKeyOf(t);
        if (!list.some((v) => v.key === key)) list.push({ key, name: t.venue?.name || '会場未設定' });
    });
    return list;
});
const initialVenues = initial.get('v') ? initial.get('v').split(',') : null;
const shownVenueKeys = ref(venueOptions.value.map((v) => v.key).filter((k) => !initialVenues || initialVenues.includes(k)));
watch(venueOptions, (opts, old) => {
    // 「終了した日も表示」で新しく出てきた会場は表示対象に加える
    opts.forEach((v) => {
        if (!old.some((o) => o.key === v.key) && !shownVenueKeys.value.includes(v.key)) shownVenueKeys.value.push(v.key);
    });
});
const shownVenues = computed(() => venueOptions.value.filter((v) => shownVenueKeys.value.includes(v.key)));

const queryParams = computed(() => {
    const p = {};
    if (props.includePast) p.past = '1';
    if (shownVenueKeys.value.length !== venueOptions.value.length) p.v = shownVenueKeys.value.join(',');
    if (statusFilter.value.length) p.s = statusFilter.value.join(',');
    if (selectedDate.value) p.d = selectedDate.value;
    if (viewMode.value === 'calendar') p.view = 'calendar';
    return p;
});
const queryString = computed(() => new URLSearchParams(queryParams.value).toString());
watch(queryString, (qs) => {
    const url = `${window.location.pathname}${qs ? `?${qs}` : ''}`;
    // Inertiaの履歴情報は残したままURLだけ置き換える（戻る操作で履歴が増えないように）
    const state = window.history.state;
    window.history.replaceState(state?.page ? { ...state, page: { ...state.page, url } } : state, '', url);
});

const editUrl = (t) => `${route('admin.timeslots.edit', t.id)}${queryString.value ? `?return=${encodeURIComponent(queryString.value)}` : ''}`;
const reservationsUrl = (t) => `${route('admin.events.reservations.index', props.event.id)}?${new URLSearchParams({
    venue_id: t.venue_id ? String(t.venue_id) : 'none',
    reservation_datetime: t.start_local,
}).toString()}`;

const venueSlots = computed(() => localTimeslots.value.filter((t) => shownVenueKeys.value.includes(venueKeyOf(t))));
const statusCounts = computed(() => {
    const counts = Object.fromEntries(STATUSES.map((s) => [s.key, 0]));
    venueSlots.value.forEach((t) => {
        if (!selectedDate.value || dateOf(t) === selectedDate.value) counts[statusOf(t)]++;
    });
    return counts;
});
const filteredSlots = computed(() => (statusFilter.value.length
    ? venueSlots.value.filter((t) => statusFilter.value.includes(statusOf(t)))
    : venueSlots.value));
const toggleStatus = (key) => {
    statusFilter.value = statusFilter.value.includes(key) ? statusFilter.value.filter((k) => k !== key) : [...statusFilter.value, key];
};

// 行=日付、列=会場。cells は状態で絞った枠、allCells はまとめて変更の対象（状態の絞り込みに関係なくその日・会場の全枠）
const boardRows = computed(() => {
    const all = new Map();
    venueSlots.value.forEach((t) => {
        const date = dateOf(t);
        if (selectedDate.value && date !== selectedDate.value) return;
        if (!all.has(date)) all.set(date, { date, past: date < today, cells: {}, allCells: {}, allSlots: [], reserved: 0, remaining: 0, shown: 0 });
        const row = all.get(date);
        const key = venueKeyOf(t);
        (row.allCells[key] ??= []).push(t);
        row.allSlots.push(t);
        if (statusFilter.value.length && !statusFilter.value.includes(statusOf(t))) return;
        (row.cells[key] ??= []).push(t);
        row.shown++;
        row.reserved += t.reserved_count;
        if (t.is_active) row.remaining += t.remaining_capacity;
    });
    const rows = [...all.values()].filter((r) => r.shown > 0).sort((a, b) => a.date.localeCompare(b.date));
    let prevMonth = '';
    rows.forEach((row) => {
        [...Object.values(row.cells), ...Object.values(row.allCells), row.allSlots].forEach((list) => list.sort((a, b) => a.start_local.localeCompare(b.start_local)));
        const month = row.date.slice(0, 7);
        row.monthHeader = month !== prevMonth ? monthLabel(month) : '';
        prevMonth = month;
    });
    return rows;
});

const totals = computed(() => ({
    days: boardRows.value.length,
    slots: boardRows.value.reduce((n, r) => n + r.shown, 0),
    reserved: boardRows.value.reduce((n, r) => n + r.reserved, 0),
    remaining: boardRows.value.reduce((n, r) => n + r.remaining, 0),
}));

const selectedSlot = computed(() => localTimeslots.value.find((t) => t.id === selectedId.value) || null);

// 別イベントとの重なり
const overlapsAt = (date, venueKey) => props.overlaps?.[date]?.[venueKey] || [];
const hasOverlapOn = (date) => shownVenues.value.some((v) => overlapsAt(date, v.key).length);
const overlapTitle = (date, venueKey) => overlapsAt(date, venueKey)
    .map((o) => `${o.title}（${o.venue_names.join('・')}／${o.reason}）`).join('、');

// 日付へ移動
const monthLabel = (ym) => `${Number(ym.slice(0, 4))}年${Number(ym.slice(5, 7))}月`;
const monthOptions = computed(() => [...new Set(boardRows.value.map((r) => r.date.slice(0, 7)))].map((key) => ({ key, label: monthLabel(key) })));
const jumpMessage = ref('');
const flashDate = ref('');
const scrollToRow = async (id, date) => {
    await nextTick();
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (date) {
        flashDate.value = date;
        setTimeout(() => { if (flashDate.value === date) flashDate.value = ''; }, 1500);
    }
};
const jumpToToday = () => {
    jumpMessage.value = '';
    selectedDate.value = '';
    nextTick(() => {
        const row = boardRows.value.find((r) => r.date >= today);
        if (!row) {
            jumpMessage.value = statusFilter.value.length || shownVenueKeys.value.length !== venueOptions.value.length
                ? '今日以降で絞り込み条件に合う枠はありません'
                : '今日以降の枠はありません';
            return;
        }
        if (row.date !== today) jumpMessage.value = `今日の枠はありません。次の枠のある日（${formatDay(row.date)}）へ移動しました`;
        scrollToRow(`d-${row.date}`, row.date);
    });
};
const jumpToMonth = (ym) => {
    if (!ym) return;
    jumpMessage.value = '';
    scrollToRow(`m-${ym}`);
};

const togglePast = (on) => {
    selectedId.value = null;
    const params = { ...queryParams.value };
    if (on) params.past = '1'; else delete params.past;
    router.get(route('admin.events.timeslots.index', props.event.id), params, {
        preserveState: true,
        preserveScroll: true,
        only: ['timeslots', 'includePast', 'pastCount', 'overlaps'],
    });
};

const slotClass = (t) => ({
    stopped: 'bg-brand-surface-2 border-dashed border-brand-border text-brand-text-subtle',
    full: 'bg-akane-50 border-brand-danger text-brand-danger',
    booked: 'bg-ai-50 border-brand-primary text-brand-primary',
    empty: 'bg-brand-surface border-brand-border-strong text-brand-text',
}[statusOf(t)]);
const chipClass = (on) => [
    'inline-flex items-center px-3 py-1 rounded-lg border text-sm cursor-pointer select-none transition-colors',
    on ? 'bg-ai-50 border-brand-primary text-brand-primary' : 'bg-brand-surface border-brand-border text-brand-text-muted',
];

const formatDay = (d) => {
    const dt = new Date(`${d}T00:00:00`);
    return `${dt.getMonth() + 1}/${dt.getDate()}（${WEEKDAYS[dt.getDay()]}）`;
};
const weekdayClass = (d) => {
    const w = new Date(`${d}T00:00:00`).getDay();
    return w === 0 ? 'text-red-600' : w === 6 ? 'text-blue-600' : 'text-brand-text';
};

const mergeSlots = (rows) => {
    const byId = new Map(rows.map((r) => [r.id, r]));
    localTimeslots.value = localTimeslots.value.map((s) => byId.get(s.id) || s);
};

// まとめて変更
const bulk = reactive({ open: false, title: '', slots: [], ids: [], action: 'deactivate', amount: 1, sending: false, error: '' });
const openBulk = (title, slots) => {
    bulk.title = title;
    bulk.slots = [...slots];
    bulk.ids = slots.map((s) => s.id);
    bulk.action = slots.some((s) => s.is_active) ? 'deactivate' : 'activate';
    bulk.amount = 1;
    bulk.error = '';
    bulk.open = true;
};
const bulkPreview = computed(() => bulk.slots.map((slot) => {
    const checked = bulk.ids.includes(slot.id);
    const row = { slot, checked, change: false, before: '', after: '', reason: '' };
    if (bulk.action === 'deactivate' || bulk.action === 'activate') {
        const on = bulk.action === 'activate';
        if (slot.is_active === on) return { ...row, reason: on ? 'すでに受付中' : 'すでに受付停止' };
        return { ...row, change: true, before: slot.is_active ? '受付中' : '受付停止', after: on ? '受付中' : '受付停止' };
    }
    const amount = Math.max(1, Math.floor(Number(bulk.amount) || 0));
    const next = slot.capacity + (bulk.action === 'inc' ? amount : -amount);
    if (next < Math.max(1, slot.reserved_count)) {
        return { ...row, reason: slot.reserved_count > 0 ? `予約数（${slot.reserved_count}）を下回るため変更しません` : '定員は1以上です' };
    }
    return { ...row, change: true, before: `定員${slot.capacity}`, after: `定員${next}` };
}));
const bulkChangeIds = computed(() => bulkPreview.value.filter((p) => p.checked && p.change).map((p) => p.slot.id));
const runBulk = async () => {
    bulk.sending = true;
    bulk.error = '';
    const amount = Math.max(1, Math.floor(Number(bulk.amount) || 0));
    try {
        const { data } = await axios.post(route('admin.events.timeslots.bulk-update', props.event.id), {
            ids: bulkChangeIds.value,
            action: bulk.action === 'inc' || bulk.action === 'dec' ? 'capacity' : bulk.action,
            amount: bulk.action === 'dec' ? -amount : amount,
        }, { headers: { 'Accept': 'application/json' } });
        mergeSlots(data.timeslots || []);
        localErrorMessage.value = data.skipped?.length
            ? `変更しなかった枠：${data.skipped.map((s) => `${s.label}（${s.reason}）`).join('、')}`
            : '';
        localSuccessMessage.value = data.message;
        bulk.open = false;
    } catch (error) {
        const errors = error.response?.data?.errors;
        bulk.error = (errors && Object.values(errors)[0]?.[0]) || error.response?.data?.message || 'まとめて変更できませんでした。';
    } finally {
        bulk.sending = false;
    }
};

const deleteTimeslot = async (id) => {
    if (!confirm('本当に削除しますか？')) return;
    deletingTimeslotId.value = id;
    localErrorMessage.value = '';
    localSuccessMessage.value = '';
    try {
        const response = await axios.delete(route('admin.timeslots.destroy', id), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (response.data && response.data.success) {
            localTimeslots.value = localTimeslots.value.filter(t => t.id !== id);
            selectedId.value = null;
            localSuccessMessage.value = response.data.message || '予約枠を削除しました。';
        } else {
            localErrorMessage.value = (response.data && response.data.message) || '削除に失敗しました。';
        }
    } catch (error) {
        localErrorMessage.value = error.response?.data?.message || '削除に失敗しました。';
    } finally {
        deletingTimeslotId.value = null;
    }
};

const adjustCapacity = async (timeslotId, amount) => {
    if (adjustingTimeslotId.value === timeslotId) return;
    adjustingTimeslotId.value = timeslotId;
    localErrorMessage.value = '';
    localSuccessMessage.value = '';
    try {
        const response = await axios.post(route('admin.timeslots.adjust-capacity', timeslotId), {
            amount: amount,
        }, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (response.data && response.data.success) {
            localTimeslots.value = localTimeslots.value.map(s =>
                s.id === timeslotId
                    ? { ...s, capacity: response.data.capacity, reserved_count: response.data.reserved, remaining_capacity: response.data.remaining }
                    : s
            );
            localSuccessMessage.value = amount > 0 ? '定員を増やしました。' : '定員を減らしました。';
        } else {
            localErrorMessage.value = (response.data && response.data.message) || '枠数の変更に失敗しました。';
        }
    } catch (error) {
        localErrorMessage.value = error.response?.data?.message || '枠数の変更に失敗しました。';
    } finally {
        adjustingTimeslotId.value = null;
    }
};
</script>
