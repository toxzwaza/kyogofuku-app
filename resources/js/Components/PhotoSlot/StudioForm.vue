<script setup>
// 前撮りスタジオのマスタの入力欄（登録・編集で共通）
import { computed } from 'vue';
import { UiCard, UiFormField, UiInput, UiTextarea, UiSelect } from '@/Components/UI';
import { WEEKDAY_NAMES, WEEKDAY_ORDER, MAX_CAPACITY, timeOptions, hoursSummary, shortTime, toMinutes } from '@/utils/photoSlotTime';

const props = defineProps({
    form: { type: Object, required: true },
    groupOptions: { type: Object, default: () => ({}) },
});

const groupSelectOptions = computed(() =>
    Object.entries(props.groupOptions).map(([value, label]) => ({ value, label }))
);
const times = timeOptions();
const timesWithEmpty = [{ value: '', label: '未登録' }, ...times];
const capacityOptions = Array.from({ length: MAX_CAPACITY }, (_, i) => ({ value: i + 1, label: `${i + 1}人` }));

const weekdayRow = (wd) => props.form.weekday_hours.find((r) => r.weekday === wd);
const toggleWeekday = (wd, on) => {
    if (on) {
        if (!weekdayRow(wd)) {
            props.form.weekday_hours.push({
                weekday: wd,
                start_time: props.form.default_start_time || '09:00',
                end_time: props.form.default_end_time || '17:00',
            });
            props.form.weekday_hours.sort((a, b) => WEEKDAY_ORDER.indexOf(a.weekday) - WEEKDAY_ORDER.indexOf(b.weekday));
        }
    } else {
        props.form.weekday_hours = props.form.weekday_hours.filter((r) => r.weekday !== wd);
    }
};
const weekdayError = (wd, field) => {
    const i = props.form.weekday_hours.findIndex((r) => r.weekday === wd);
    return i < 0 ? '' : props.form.errors[`weekday_hours.${i}.${field}`] || '';
};

const summary = computed(() =>
    hoursSummary(props.form.default_start_time, props.form.default_end_time, props.form.weekday_hours)
);
const defaultInvalid = computed(() =>
    props.form.default_start_time && props.form.default_end_time
    && toMinutes(props.form.default_start_time) >= toMinutes(props.form.default_end_time)
);
</script>

<template>
    <div class="space-y-5">
        <UiCard>
            <h3 class="text-sm font-semibold text-brand-text mb-4">基本情報</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <UiFormField label="スタジオ名" required :error="form.errors.name" class="sm:col-span-2">
                    <UiInput v-model="form.name" placeholder="例：岡山本店スタジオ" :error="!!form.errors.name" />
                </UiFormField>
                <UiFormField label="店舗グループ" required :error="form.errors.shop_group"
                    hint="このグループのスタッフに、スタジオと枠が表示されます">
                    <UiSelect v-model="form.shop_group" :options="groupSelectOptions" placeholder="選んでください" :error="!!form.errors.shop_group" />
                </UiFormField>
                <UiFormField label="状態" :error="form.errors.is_active" hint="無効にすると、枠の作成と予約の選択肢に出ません（今の枠と予約は残ります）">
                    <label class="inline-flex items-center gap-2 h-9 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-brand-border text-brand-primary focus:ring-brand-primary" />
                        有効
                    </label>
                </UiFormField>
                <UiFormField label="住所" :error="form.errors.address" class="sm:col-span-2">
                    <UiInput v-model="form.address" />
                </UiFormField>
                <UiFormField label="備考" :error="form.errors.remarks" class="sm:col-span-2">
                    <UiTextarea v-model="form.remarks" :rows="3" />
                </UiFormField>
            </div>
        </UiCard>

        <UiCard>
            <h3 class="text-sm font-semibold text-brand-text mb-1">営業時間と定員</h3>
            <p class="text-xs text-brand-text-muted mb-4">
                枠をまとめて作るときに、この営業時間から30分ごとの枠を作ります。終了時刻は「最後の枠が終わる時刻」です（例：9:00〜17:00 なら 9:00〜16:30 の16枠）。
            </p>
            <div class="grid gap-4 sm:grid-cols-3">
                <UiFormField label="開始時刻（既定）" :error="form.errors.default_start_time">
                    <UiSelect v-model="form.default_start_time" :options="timesWithEmpty" :error="!!form.errors.default_start_time" />
                </UiFormField>
                <UiFormField label="終了時刻（既定）" :error="form.errors.default_end_time || (defaultInvalid ? '終了時刻は開始時刻より後にしてください' : '')">
                    <UiSelect v-model="form.default_end_time" :options="timesWithEmpty" :error="!!form.errors.default_end_time || defaultInvalid" />
                </UiFormField>
                <UiFormField label="既定の定員" required :error="form.errors.default_capacity" hint="1つの時刻に入れる人数">
                    <UiSelect v-model="form.default_capacity" :options="capacityOptions" :error="!!form.errors.default_capacity" />
                </UiFormField>
            </div>

            <div class="mt-5">
                <p class="text-xs font-medium text-brand-text-muted mb-2">曜日ごとの営業時間（既定と違う曜日だけチェック）</p>
                <div class="divide-y divide-brand-border rounded-md border border-brand-border">
                    <div v-for="wd in WEEKDAY_ORDER" :key="wd" class="flex flex-wrap items-center gap-3 px-3 py-2">
                        <label class="inline-flex items-center gap-2 w-24 text-sm">
                            <input type="checkbox" :checked="!!weekdayRow(wd)"
                                class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                                @change="toggleWeekday(wd, $event.target.checked)" />
                            <span :class="wd === 0 ? 'text-red-600' : wd === 6 ? 'text-blue-600' : ''">{{ WEEKDAY_NAMES[wd] }}曜日</span>
                        </label>
                        <template v-if="weekdayRow(wd)">
                            <div class="w-28"><UiSelect v-model="weekdayRow(wd).start_time" :options="times" size="sm" :error="!!weekdayError(wd, 'start_time')" /></div>
                            <span class="text-brand-text-muted text-sm">〜</span>
                            <div class="w-28"><UiSelect v-model="weekdayRow(wd).end_time" :options="times" size="sm" :error="!!weekdayError(wd, 'end_time')" /></div>
                            <span v-if="weekdayError(wd, 'end_time') || weekdayError(wd, 'start_time')" class="text-xs text-brand-danger">
                                {{ weekdayError(wd, 'end_time') || weekdayError(wd, 'start_time') }}
                            </span>
                        </template>
                        <span v-else class="text-xs text-brand-text-subtle">既定の営業時間</span>
                    </div>
                </div>
            </div>

            <div class="mt-5 rounded-md bg-brand-surface-2 px-4 py-3 text-sm">
                <p class="text-xs font-medium text-brand-text-muted mb-1">この営業時間でできる枠</p>
                <ul v-if="summary.length" class="space-y-0.5">
                    <li v-for="g in summary" :key="g.days">
                        <span class="font-medium">{{ g.days }}</span>
                        ：{{ shortTime(g.start) }}〜{{ shortTime(g.last) }} の {{ g.count }}枠
                        <span class="text-brand-text-muted">（定員 {{ form.default_capacity }}人）</span>
                    </li>
                </ul>
                <p v-else class="text-brand-text-muted">営業時間が未登録です。登録するまで、このスタジオの枠はまとめて作れません。</p>
            </div>
        </UiCard>
    </div>
</template>
