<script setup>
import { computed } from 'vue';
import {
    CalendarCheck, CheckCircle2, XCircle, FileText, Camera, Clock, TrendingUp,
} from 'lucide-vue-next';

const props = defineProps({
    customer: { type: Object, required: true },
});

const reservations = computed(() => props.customer.event_reservations || []);
const contracts    = computed(() => props.customer.contracts || []);
const photoSlots   = computed(() => props.customer.photo_slots || []);

const totalRes  = computed(() => reservations.value.length);
const activeRes = computed(() => reservations.value.filter((r) => !r.cancel_flg).length);
const doneRes   = computed(() => reservations.value.filter((r) => r.status === '対応完了済み').length);
const cancelRes = computed(() => reservations.value.filter((r) => r.cancel_flg).length);

const firstReservation = computed(() => {
    const dates = reservations.value
        .map((r) => r.reservation_datetime)
        .filter(Boolean)
        .sort();
    return dates[0] || null;
});
const lastReservation = computed(() => {
    const dates = reservations.value
        .map((r) => r.reservation_datetime)
        .filter(Boolean)
        .sort();
    return dates.length ? dates[dates.length - 1] : null;
});

const totalContract = computed(() => contracts.value.length);
const totalAmount = computed(() => {
    return contracts.value
        .filter((c) => !c.deleted_at) // withTrashed されている場合
        .reduce((s, c) => s + (Number(c.amount) || 0), 0);
});

const totalPhotoSlots = computed(() => photoSlots.value.length);

const fmtDate = (v) => {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T'));
    if (isNaN(d.getTime())) return v;
    return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`;
};

const fmtYen = (n) => `¥${Number(n || 0).toLocaleString()}`;

const items = computed(() => [
    {
        key: 'total',
        label: '総予約数',
        value: totalRes.value,
        unit: '件',
        icon: CalendarCheck,
        color: 'text-brand-primary',
        bg: 'bg-ai-50 dark:bg-ai-900',
        sub: `有効 ${activeRes.value} / キャンセル ${cancelRes.value}`,
    },
    {
        key: 'done',
        label: '対応完了',
        value: doneRes.value,
        unit: '件',
        icon: CheckCircle2,
        color: 'text-brand-success',
        bg: 'bg-uguisu-50 dark:bg-uguisu-900',
    },
    {
        key: 'cancel',
        label: 'キャンセル率',
        value: totalRes.value > 0 ? Math.round((cancelRes.value / totalRes.value) * 100) : 0,
        unit: '%',
        icon: XCircle,
        color: 'text-brand-danger',
        bg: 'bg-akane-50 dark:bg-akane-900',
        sub: `${cancelRes.value} / ${totalRes.value}`,
    },
    {
        key: 'contracts',
        label: '契約',
        value: totalContract.value,
        unit: '件',
        icon: FileText,
        color: 'text-brand-accent',
        bg: 'bg-enji-50 dark:bg-enji-900',
        sub: totalAmount.value > 0 ? fmtYen(totalAmount.value) : '金額未登録',
    },
    {
        key: 'photos',
        label: '前撮り',
        value: totalPhotoSlots.value,
        unit: '件',
        icon: Camera,
        color: 'text-brand-warning',
        bg: 'bg-natane-50 dark:bg-natane-900',
    },
]);
</script>

<template>
    <div class="rounded-soft-lg border border-brand-border bg-brand-surface p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-serif text-base text-brand-text flex items-center gap-2">
                <TrendingUp :size="16" class="text-brand-primary" />
                顧客サマリー
            </h3>
            <div v-if="firstReservation || lastReservation" class="text-[11px] text-brand-text-muted flex items-center gap-3">
                <span v-if="firstReservation" class="flex items-center gap-1">
                    <Clock :size="11" />初回 {{ fmtDate(firstReservation) }}
                </span>
                <span v-if="lastReservation" class="flex items-center gap-1">
                    最終 {{ fmtDate(lastReservation) }}
                </span>
            </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div v-for="it in items" :key="it.key" class="rounded-soft border border-brand-border p-3 bg-brand-surface">
                <div class="flex items-start justify-between mb-1">
                    <div class="text-[11px] text-brand-text-muted">{{ it.label }}</div>
                    <div :class="['w-7 h-7 rounded-soft flex items-center justify-center', it.bg]">
                        <component :is="it.icon" :size="14" :class="it.color" />
                    </div>
                </div>
                <div class="font-serif text-xl leading-none">
                    {{ it.value }}<span class="text-xs text-brand-text-muted ml-1">{{ it.unit }}</span>
                </div>
                <div v-if="it.sub" class="mt-1 text-[10px] text-brand-text-subtle">{{ it.sub }}</div>
            </div>
        </div>
    </div>
</template>
