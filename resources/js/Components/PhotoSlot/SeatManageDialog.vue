<script setup>
// 前撮り：その時刻の枠を足す・定員を変える小さい画面（権限機能で「前撮りの管理」の人だけにする予定）
import { ref, watch } from 'vue';
import axios from 'axios';
import { UiDialog, UiButton } from '@/Components/UI';
import { formatDateJaWithWeekday } from '@/utils/dateFormat';

const props = defineProps({
    open: { type: Boolean, default: false },
    studio: { type: Object, default: null }, // { id, name }
    date: { type: String, default: '' },
    time: { type: String, default: '' },
    capacity: { type: Number, default: 0 }, // いまの定員（枠がなければ 0）
    booked: { type: Number, default: 0 },
    maxCapacity: { type: Number, default: 10 },
});
const emit = defineEmits(['update:open', 'changed']);

const next = ref(1);
const saving = ref(false);
const error = ref('');

watch(() => props.open, (open) => {
    if (!open) return;
    next.value = props.capacity > 0 ? props.capacity : 1;
    error.value = '';
});

const target = () => ({ photo_studio_id: props.studio.id, shoot_date: props.date, shoot_time: props.time });
const messageOf = (e, fallback) => {
    const errors = e.response?.data?.errors;
    return errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || fallback);
};

const close = () => emit('update:open', false);

const addOne = async () => {
    saving.value = true;
    error.value = '';
    try {
        const res = await axios.post(route('admin.photo-slots.seats.store'), target());
        emit('changed', res.data.message);
        close();
    } catch (e) {
        error.value = messageOf(e, '枠を足せませんでした。');
    } finally {
        saving.value = false;
    }
};

const changeCapacity = async () => {
    const n = Number(next.value);
    saving.value = true;
    error.value = '';
    try {
        let res;
        if (props.capacity === 0) {
            // 枠のない時刻：1枠作ってから定員をそろえる
            res = await axios.post(route('admin.photo-slots.seats.store'), target());
            if (n > 1) res = await axios.put(route('admin.photo-slots.capacity'), { ...target(), capacity: n });
        } else {
            res = await axios.put(route('admin.photo-slots.capacity'), { ...target(), capacity: n });
        }
        emit('changed', res.data.message);
        close();
    } catch (e) {
        error.value = messageOf(e, '定員を変えられませんでした。');
        if (props.capacity === 0) emit('changed', null); // 1枠目はできているかもしれないので、表を読み直す
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <UiDialog :open="open" title="枠の数を変える" size="sm" @update:open="emit('update:open', $event)">
        <div class="space-y-4 text-sm">
            <div class="rounded-soft bg-brand-surface-2 px-3 py-2">
                <div class="font-semibold">{{ studio?.name }}</div>
                <div class="tabular-nums">{{ date ? formatDateJaWithWeekday(date) : '' }} {{ time }}</div>
                <div class="mt-1 text-xs text-brand-text-muted">
                    <template v-if="capacity > 0">いま：予約 {{ booked }}／定員 {{ capacity }}</template>
                    <template v-else>この時刻には枠がありません</template>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-brand-text-muted mb-1">定員（1〜{{ maxCapacity }}人）</label>
                <div class="flex items-center gap-2">
                    <input v-model.number="next" type="number" :min="Math.max(1, booked)" :max="maxCapacity" class="w-24 rounded-md border-brand-border text-sm tabular-nums focus:border-brand-primary focus:ring-brand-primary" />
                    <UiButton size="sm" :loading="saving" :disabled="!next || next === capacity" @click="changeCapacity">
                        {{ capacity > 0 ? '定員を変える' : '枠を作る' }}
                    </UiButton>
                </div>
                <p class="mt-1 text-xs text-brand-text-muted">減らすときは、番号の大きい空き枠から消します。予約の入っている枠は消しません。</p>
            </div>

            <div v-if="capacity > 0 && capacity < maxCapacity" class="border-t border-brand-border pt-3">
                <UiButton variant="ghost" size="sm" :loading="saving" @click="addOne">この時刻に1枠足す</UiButton>
            </div>

            <p v-if="error" class="rounded-soft bg-red-50 border border-red-200 px-3 py-2 text-red-700">{{ error }}</p>
        </div>
    </UiDialog>
</template>
