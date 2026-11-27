<script setup>
// 成人式のお支度場所のマスタの入力欄（登録・編集で共通）
import { computed } from 'vue';
import { UiCard, UiFormField, UiInput, UiTextarea, UiSelect } from '@/Components/UI';

const props = defineProps({
    form: { type: Object, required: true },
    groupOptions: { type: Object, default: () => ({}) },
    kindOptions: { type: Object, default: () => ({}) },
    intervals: { type: Array, default: () => [30] },
    maxCapacity: { type: Number, default: 20 },
    maxNumberedCapacity: { type: Number, default: 200 },
    numberedGroups: { type: Array, default: () => ['fukui'] },
});

// 福井は時刻で管理せず「日＋番号」の枠にする（刻み・時刻の欄は使わない）
const numbered = computed(() => props.numberedGroups.includes(props.form.shop_group));

const toOptions = (obj) => Object.entries(obj).map(([value, label]) => ({ value, label }));
const groupSelectOptions = computed(() => toOptions(props.groupOptions));
const kindSelectOptions = computed(() => toOptions(props.kindOptions));
const intervalOptions = computed(() => props.intervals.map((m) => ({ value: m, label: `${m}分` })));
const capacityOptions = computed(() => Array.from({ length: props.maxCapacity }, (_, i) => ({ value: i + 1, label: `${i + 1}レーン` })));

const pad = (n) => String(n).padStart(2, '0');
const toMinutes = (hm) => { const [h, m] = hm.split(':').map(Number); return h * 60 + m; };
// 成人式の朝は早いので 4:00〜20:00
const timeOptions = computed(() => {
    const step = Number(props.form.slot_interval_minutes) || 30;
    const list = [{ value: '', label: '未登録' }];
    for (let m = 4 * 60; m <= 20 * 60; m += step) list.push({ value: `${pad(Math.floor(m / 60))}:${pad(m % 60)}`, label: `${Math.floor(m / 60)}:${pad(m % 60)}` });
    return list;
});

const preview = computed(() => {
    const { default_start_time: s, default_end_time: e } = props.form;
    const step = Number(props.form.slot_interval_minutes) || 30;
    if (!s || !e || toMinutes(s) > toMinutes(e)) return null;
    const count = Math.floor((toMinutes(e) - toMinutes(s)) / step) + 1;
    return { count, seats: count * Number(props.form.default_capacity || 1) };
});
</script>

<template>
    <div class="space-y-5">
        <UiCard>
            <h3 class="text-sm font-semibold text-brand-text mb-4">基本情報</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <UiFormField label="お支度場所の名前" required :error="form.errors.name" class="sm:col-span-2">
                    <UiInput v-model="form.name" placeholder="例：岡山プラザホテル／〇〇美容室" :error="!!form.errors.name" />
                </UiFormField>
                <UiFormField label="種類" required :error="form.errors.kind" hint="自社会場＝京呉服が美容師・着付け師を集めて開く会場">
                    <UiSelect v-model="form.kind" :options="kindSelectOptions" :error="!!form.errors.kind" />
                </UiFormField>
                <UiFormField label="店舗グループ" required :error="form.errors.shop_group" hint="このグループのスタッフに、場所と枠が表示されます">
                    <UiSelect v-model="form.shop_group" :options="groupSelectOptions" placeholder="選んでください" :error="!!form.errors.shop_group" />
                </UiFormField>
                <UiFormField label="状態" :error="form.errors.is_active" hint="無効にすると、新しい登録の選択肢に出ません（今の枠と登録は残ります）">
                    <label class="inline-flex items-center gap-2 h-9 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-brand-border text-brand-primary focus:ring-brand-primary" />
                        有効
                    </label>
                </UiFormField>
                <UiFormField label="並び順" :error="form.errors.sort_order" hint="小さい順に並びます">
                    <UiInput v-model="form.sort_order" type="number" min="0" :error="!!form.errors.sort_order" />
                </UiFormField>
                <UiFormField label="住所" :error="form.errors.address">
                    <UiInput v-model="form.address" />
                </UiFormField>
                <UiFormField label="電話" :error="form.errors.phone">
                    <UiInput v-model="form.phone" />
                </UiFormField>
                <UiFormField label="メモ" :error="form.errors.remarks" class="sm:col-span-2" hint="美容室の担当者・連絡のしかた・受けられる人数の目安など">
                    <UiTextarea v-model="form.remarks" :rows="3" />
                </UiFormField>
            </div>
        </UiCard>

        <UiCard>
            <h3 class="text-sm font-semibold text-brand-text mb-1">枠の作り方（既定）</h3>
            <template v-if="numbered">
                <p class="text-xs text-brand-text-muted mb-4">
                    この店舗グループは時刻で管理しません。日ごとに枠数（人数）だけを作り、枠は「1, 2, 3…」の番号で並びます（早く入った人ほど若い番号。取り消しが出ると後ろの番号が詰まります）。
                </p>
                <UiFormField label="既定の枠数（1日）" required :error="form.errors.default_capacity" :hint="`1〜${maxNumberedCapacity}`" class="sm:w-48">
                    <UiInput v-model.number="form.default_capacity" type="number" min="1" :max="maxNumberedCapacity" :error="!!form.errors.default_capacity" />
                </UiFormField>
            </template>
            <template v-else>
            <p class="text-xs text-brand-text-muted mb-4">
                枠を1日分まとめて作るときの最初の値です（作るときに直せます）。終了時刻は「最後の枠の時刻」です。定員はレーン数（同じ時刻にお支度できる人数）です。
            </p>
            <div class="grid gap-4 sm:grid-cols-4">
                <UiFormField label="枠の刻み" required :error="form.errors.slot_interval_minutes">
                    <UiSelect v-model="form.slot_interval_minutes" :options="intervalOptions" :error="!!form.errors.slot_interval_minutes" />
                </UiFormField>
                <UiFormField label="開始時刻" :error="form.errors.default_start_time">
                    <UiSelect v-model="form.default_start_time" :options="timeOptions" :error="!!form.errors.default_start_time" />
                </UiFormField>
                <UiFormField label="最後の枠" :error="form.errors.default_end_time">
                    <UiSelect v-model="form.default_end_time" :options="timeOptions" :error="!!form.errors.default_end_time" />
                </UiFormField>
                <UiFormField label="定員（レーン）" required :error="form.errors.default_capacity">
                    <UiSelect v-model="form.default_capacity" :options="capacityOptions" :error="!!form.errors.default_capacity" />
                </UiFormField>
            </div>
            <p v-if="preview" class="mt-4 rounded-md bg-brand-surface-2 px-4 py-2 text-sm">
                1日に {{ preview.count }}時刻 × {{ form.default_capacity }}レーン ＝ <span class="font-semibold">{{ preview.seats }}人</span> まで
            </p>
            </template>
        </UiCard>
    </div>
</template>
