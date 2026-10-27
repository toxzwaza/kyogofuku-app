<template>
    <!-- 予約枠の月カレンダー。日ごと・会場ごとに枠数と残りを表示し、日付クリックでその日の一覧へ絞り込む -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <button type="button" class="p-1.5 rounded hover:bg-brand-surface-2" aria-label="前の月" @click="shift(-1)">
                <ChevronLeft :size="18" />
            </button>
            <div class="font-semibold text-brand-text tabular-nums">{{ month.getFullYear() }}年{{ month.getMonth() + 1 }}月</div>
            <button type="button" class="p-1.5 rounded hover:bg-brand-surface-2" aria-label="次の月" @click="shift(1)">
                <ChevronRight :size="18" />
            </button>
        </div>

        <div class="grid grid-cols-7 text-xs text-center font-medium border-b border-brand-border">
            <div v-for="(w, i) in WEEKDAYS" :key="w" class="py-1" :class="i === 0 ? 'text-red-600' : i === 6 ? 'text-blue-600' : 'text-brand-text-muted'">{{ w }}</div>
        </div>
        <div class="grid grid-cols-7 border-l border-brand-border">
            <button
                v-for="day in days"
                :key="day.key"
                type="button"
                class="min-h-[84px] p-1.5 text-left border-r border-b border-brand-border align-top transition-colors"
                :class="[
                    day.inMonth ? 'bg-brand-surface' : 'bg-brand-surface-2/60 text-brand-text-subtle',
                    day.key === selectedDate ? 'ring-2 ring-inset ring-brand-primary' : '',
                    day.summary.length ? 'hover:bg-ai-50 cursor-pointer' : 'cursor-default',
                ]"
                :disabled="!day.summary.length"
                @click="$emit('select', day.key)"
            >
                <div class="text-xs tabular-nums" :class="day.weekday === 0 ? 'text-red-600' : day.weekday === 6 ? 'text-blue-600' : ''">{{ day.date }}</div>
                <div v-for="s in day.summary" :key="s.key" class="mt-0.5 text-[11px] leading-tight truncate" :title="`${s.venue}：${s.count}枠 残${s.remaining}`">
                    <span class="text-brand-text-muted">{{ shortName(s.venue) }}</span>
                    <span class="ml-1 tabular-nums" :class="s.remaining === 0 ? 'text-brand-danger font-semibold' : 'text-brand-text'">
                        {{ s.count }}枠<template v-if="s.remaining !== s.capacity">・残{{ s.remaining }}</template>
                    </span>
                    <span v-if="s.inactive" class="ml-0.5 text-brand-text-subtle">（無効{{ s.inactive }}）</span>
                </div>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    timeslots: { type: Array, default: () => [] },
    selectedDate: { type: String, default: '' },
});
defineEmits(['select']);

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
// start_at はUTCのISO文字列で来るので、ブラウザの日付に直してから日単位にする
const dateOf = (startAt) => ymd(new Date(startAt));

// 初期表示は「今日以降で最初に枠がある月」。無ければ今月
const initialMonth = () => {
    const today = ymd(new Date());
    const next = props.timeslots.map((t) => dateOf(t.start_at)).filter((d) => d >= today).sort()[0];
    const base = next ? new Date(`${next}T00:00:00`) : new Date();
    return new Date(base.getFullYear(), base.getMonth(), 1);
};
const month = ref(initialMonth());
const shift = (n) => { month.value = new Date(month.value.getFullYear(), month.value.getMonth() + n, 1); };

// date => 会場ID => 集計（残りは受付中の枠だけ数える）
const byDate = computed(() => {
    const map = {};
    props.timeslots.forEach((t) => {
        const date = dateOf(t.start_at);
        const venue = t.venue?.name || '会場未設定';
        const s = ((map[date] ??= {})[t.venue_id ?? 'none'] ??= { key: t.venue_id ?? 'none', venue, count: 0, capacity: 0, remaining: 0, inactive: 0 });
        s.count += 1;
        if (t.is_active) {
            s.capacity += t.capacity;
            s.remaining += t.remaining_capacity;
        } else {
            s.inactive += 1;
        }
    });
    return map;
});

const days = computed(() => {
    const first = month.value;
    const start = new Date(first.getFullYear(), first.getMonth(), 1 - first.getDay());
    const last = new Date(first.getFullYear(), first.getMonth() + 1, 0);
    const end = new Date(last.getFullYear(), last.getMonth(), last.getDate() + (6 - last.getDay()));
    const list = [];
    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        const key = ymd(d);
        list.push({
            key,
            date: d.getDate(),
            weekday: d.getDay(),
            inMonth: d.getMonth() === first.getMonth(),
            summary: Object.values(byDate.value[key] || {}),
        });
    }
    return list;
});

// 「京呉服好一 岡山店」→「岡山店」のように店名部分だけ出す
const shortName = (name) => name.split(/\s+/).pop();
</script>
