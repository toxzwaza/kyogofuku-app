<template>
    <Head title="共有する" />

    <AdminLayout :breadcrumb="[{ label: '業務マニュアル' }, { label: '共有ノート', href: route('admin.incidents.index') }, { label: '共有する' }]">
        <UiPageHeader title="共有する" :description="`${groupLabel}の共有ノートに記録します。1つずつ答えていけば完了です。`">
            <template v-if="groups.length" #actions>
                <div class="w-40">
                    <UiSelect :model-value="group" :options="groups" @update:model-value="changeGroup" />
                </div>
            </template>
        </UiPageHeader>

        <!-- スマホ・タブレット：進み具合 -->
        <div class="lg:hidden mb-4">
            <div class="flex items-center justify-between text-xs text-brand-text-muted mb-1.5">
                <span>{{ idx + 1 }} / {{ keys.length }}　{{ stepLabel(kindValue, current) }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-brand-surface-2 overflow-hidden">
                <div class="h-full bg-brand-primary transition-all" :style="{ width: `${((idx + 1) / keys.length) * 100}%` }" />
            </div>
        </div>

        <div class="grid lg:grid-cols-[260px_1fr] gap-6 pb-24 lg:pb-0">
            <!-- PC：工程の一覧 -->
            <nav class="hidden lg:block" aria-label="入力の工程">
                <ol class="space-y-1">
                    <li v-for="(k, i) in keys" :key="k">
                        <button
                            type="button"
                            class="w-full text-left rounded-soft px-3 py-2.5 flex gap-3 transition-colors"
                            :class="[
                                i === idx ? 'bg-brand-surface border border-brand-primary shadow-soft-sm' : 'border border-transparent',
                                i < idx || i <= maxReached ? 'hover:bg-brand-surface-2 cursor-pointer' : 'opacity-60 cursor-default',
                            ]"
                            :disabled="i > maxReached"
                            @click="goTo(i)"
                        >
                            <span
                                class="shrink-0 w-6 h-6 rounded-full grid place-items-center text-xs font-semibold"
                                :class="i < idx ? 'bg-brand-primary text-brand-on-primary' : i === idx ? 'border-2 border-brand-primary text-brand-primary' : 'bg-brand-surface-2 text-brand-text-muted'"
                            >
                                <Check v-if="i < idx" :size="13" />
                                <template v-else>{{ i + 1 }}</template>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium" :class="i === idx ? 'text-brand-primary' : 'text-brand-text'">{{ stepLabel(kindValue, k) }}</span>
                                <span v-if="i < idx && summary(k)" class="block text-xs text-brand-text-muted truncate">{{ summary(k) }}</span>
                            </span>
                        </button>
                    </li>
                </ol>
            </nav>

            <!-- 入力欄 -->
            <UiCard>
                <!-- 基本情報 -->
                <div v-if="current === 'base'" class="space-y-6">
                    <h2 class="text-lg font-semibold text-brand-text">いつ・どこで・何の話？</h2>
                    <UiFormField label="発生日" required :error="errs.occurred_on">
                        <input
                            v-model="form.occurred_on"
                            type="date"
                            :max="today"
                            name="occurred_on"
                            class="block w-full sm:w-56 h-11 md:h-9 px-3 text-base md:text-sm rounded-soft border bg-brand-surface text-brand-text focus:ring-2 focus:ring-brand-primary focus:border-brand-primary"
                            :class="errs.occurred_on ? 'border-brand-danger' : 'border-brand-border-strong'"
                        />
                    </UiFormField>
                    <UiFormField label="店舗" required :error="errs.shop_name">
                        <ChipPicker v-model="form.shop_name" :items="shops" :error="!!errs.shop_name" name="shop_name" />
                    </UiFormField>
                    <UiFormField label="区分" required :error="errs.category">
                        <ChipPicker
                            v-model="form.category"
                            v-model:other="form.category_other"
                            :items="categories"
                            :error="!!errs.category"
                            with-other
                            other-placeholder="例：小物、仕立て直し"
                            name="category"
                        />
                    </UiFormField>
                    <UiFormField label="種類" required :error="errs.kind">
                        <ChipPicker
                            v-model="form.kind"
                            v-model:other="form.kind_other"
                            :items="kinds"
                            :error="!!errs.kind"
                            with-other
                            other-placeholder="例：お礼の言葉"
                            name="kind"
                        />
                    </UiFormField>
                </div>

                <!-- 件名 -->
                <div v-else-if="current === 'title'" class="space-y-3">
                    <h2 class="text-lg font-semibold text-brand-text">件名をつけてください</h2>
                    <p class="text-sm text-brand-text-muted">一覧で見て、何の話か分かる短い言葉にします（100字まで）。</p>
                    <input
                        ref="focusEl"
                        v-model="form.title"
                        type="text"
                        maxlength="100"
                        name="title"
                        placeholder="例：前撮りで重ね衿が入っていなかった"
                        class="block w-full h-11 px-3 text-base rounded-soft border bg-brand-surface text-brand-text focus:ring-2 focus:ring-brand-primary focus:border-brand-primary"
                        :class="errs.title ? 'border-brand-danger' : 'border-brand-border-strong'"
                        @keydown.enter.prevent="next"
                    />
                    <p v-if="errs.title" class="text-xs text-brand-danger">{{ errs.title }}</p>
                </div>

                <!-- 本文（何があったか・対応・相談） -->
                <div v-else-if="['what', 'action', 'consult'].includes(current)" class="space-y-3">
                    <h2 class="text-lg font-semibold text-brand-text">
                        {{ flow[current].q }}
                        <span v-if="current !== 'what' && (current === 'consult' || flow.action.opt)" class="text-xs font-normal text-brand-text-muted ml-1">任意</span>
                    </h2>
                    <p class="text-sm text-brand-text-muted">{{ flow[current].sub }}</p>
                    <textarea
                        ref="focusEl"
                        v-model="form[current]"
                        rows="7"
                        maxlength="5000"
                        :name="current"
                        :placeholder="flow[current].ph"
                        class="block w-full px-3 py-2 text-base md:text-sm rounded-soft border bg-brand-surface text-brand-text placeholder:text-brand-text-subtle focus:ring-2 focus:ring-brand-primary focus:border-brand-primary"
                        :class="errs[current] ? 'border-brand-danger' : 'border-brand-border-strong'"
                    />
                    <p v-if="errs[current]" class="text-xs text-brand-danger">{{ errs[current] }}</p>
                    <div v-if="current === 'action' && !flow.action.opt">
                        <UiButton variant="ghost" size="sm" @click="markNotYet">その場では対応していない</UiButton>
                    </div>
                </div>

                <!-- 確認 -->
                <div v-else-if="current === 'confirm'" class="space-y-4">
                    <h2 class="text-lg font-semibold text-brand-text">この内容で共有します</h2>
                    <p class="text-sm text-brand-text-muted">直したいところは「直す」を押してください。</p>
                    <dl class="divide-y divide-brand-border border border-brand-border rounded-soft">
                        <div v-for="row in confirmRows" :key="row.key" class="p-3 sm:flex gap-4">
                            <dt class="sm:w-40 shrink-0 text-xs font-medium text-brand-text-muted pt-0.5">{{ row.label }}</dt>
                            <dd class="flex-1 min-w-0 text-sm text-brand-text whitespace-pre-wrap break-words">
                                <span v-if="row.value">{{ row.value }}</span>
                                <span v-else class="text-brand-text-subtle">（なし）</span>
                            </dd>
                            <div class="mt-1 sm:mt-0 shrink-0">
                                <UiButton variant="link" size="sm" @click="goTo(keys.indexOf(row.step))">直す</UiButton>
                            </div>
                        </div>
                    </dl>
                    <p v-if="Object.keys(form.errors).length" class="text-sm text-brand-danger">
                        入力に足りないところがあります。赤字のところを直してください。
                    </p>
                </div>

                <!-- PC：戻る／次へ -->
                <div class="hidden lg:flex justify-between gap-3 mt-8 pt-5 border-t border-brand-border">
                    <UiButton v-if="idx > 0" variant="ghost" @click="back">
                        <template #leading><ChevronLeft :size="16" /></template>戻る
                    </UiButton>
                    <UiButton v-else variant="ghost" :href="route('admin.incidents.index', groups.length ? { group } : {})">やめる</UiButton>
                    <UiButton v-if="current !== 'confirm'" variant="primary" @click="next">
                        次へ<template #trailing><ChevronRight :size="16" /></template>
                    </UiButton>
                    <UiButton v-else variant="primary" :loading="form.processing" @click="submit">共有する</UiButton>
                </div>
            </UiCard>
        </div>

        <!-- スマホ・タブレット：下に固定の戻る／次へ（右下のチャットボタンと重ならないよう右を空ける） -->
        <div class="lg:hidden fixed inset-x-0 bottom-0 z-30 bg-brand-surface border-t border-brand-border pl-4 pr-[88px] py-3 flex gap-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
            <UiButton v-if="idx > 0" variant="ghost" class="flex-1" @click="back">戻る</UiButton>
            <UiButton v-else variant="ghost" class="flex-1" :href="route('admin.incidents.index', groups.length ? { group } : {})">やめる</UiButton>
            <UiButton v-if="current !== 'confirm'" variant="primary" class="flex-[2]" @click="next">次へ</UiButton>
            <UiButton v-else variant="primary" class="flex-[2]" :loading="form.processing" @click="submit">共有する</UiButton>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiPageHeader, UiButton, UiCard, UiFormField, UiSelect } from '@/Components/UI';
import ChipPicker from '@/Components/Incident/ChipPicker.vue';
import { Check, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { OTHER, flowFor, stepLabel, finalValue, dateLabel } from '@/composables/useIncidentFlow.js';

const props = defineProps({
    group: String,
    groups: { type: Array, default: () => [] },
    shops: { type: Array, default: () => [] },
    myShop: { type: String, default: '' },
    today: String,
    categories: Array,
    kinds: Array,
});

const groupLabel = computed(() => props.groups.find((g) => g.value === props.group)?.label
    || (props.group === 'fukui' ? '福井グループ' : '岡山グループ'));

const form = useForm({
    occurred_on: props.today,
    shop_name: props.myShop || (props.shops.length === 1 ? props.shops[0] : ''),
    category: '',
    category_other: '',
    kind: '',
    kind_other: '',
    title: '',
    what: '',
    action: '',
    consult: '',
});

const kindValue = computed(() => finalValue(form.kind, form.kind_other));
const flow = computed(() => flowFor(kindValue.value));
const keys = computed(() => flow.value.keys);

const idx = ref(0);
const maxReached = ref(0);
const current = computed(() => keys.value[idx.value]);
const focusEl = ref(null);

// 種類を変えて手順の数が変わったら、今の位置をはみ出さないようにする
watch(keys, (k) => {
    if (idx.value > k.length - 1) idx.value = k.length - 1;
    if (maxReached.value > k.length - 1) maxReached.value = k.length - 1;
});

/** 手元のチェック（サーバ側でも同じ決まりで確かめる） */
const localErrs = reactive({});
const errs = computed(() => ({ ...form.errors, ...localErrs }));

const checkStep = (key) => {
    Object.keys(localErrs).forEach((k) => delete localErrs[k]);
    if (key === 'base') {
        if (!form.occurred_on) localErrs.occurred_on = '発生日を選んでください。';
        else if (form.occurred_on > props.today) localErrs.occurred_on = '発生日に未来の日付は選べません。';
        if (!form.shop_name) localErrs.shop_name = '店舗を選んでください。';
        if (!form.category) localErrs.category = '区分を選んでください。';
        if (!form.kind) localErrs.kind = '種類を選んでください。';
    }
    if (key === 'title' && !form.title.trim()) localErrs.title = '件名を入れてください。';
    if (key === 'what' && !form.what.trim()) localErrs.what = `${flow.value.what.nm}を書いてください。`;
    if (key === 'action' && flow.value.action && !flow.value.action.opt && !form.action.trim()) {
        localErrs.action = 'その場でした対応を書くか、「その場では対応していない」を押してください。';
    }
    return Object.keys(localErrs).length === 0;
};

const focusInput = async () => {
    await nextTick();
    focusEl.value?.focus?.();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const next = () => {
    if (!checkStep(current.value)) return;
    idx.value = Math.min(idx.value + 1, keys.value.length - 1);
    maxReached.value = Math.max(maxReached.value, idx.value);
    focusInput();
};
const back = () => {
    Object.keys(localErrs).forEach((k) => delete localErrs[k]);
    idx.value = Math.max(idx.value - 1, 0);
    focusInput();
};
const goTo = (i) => {
    if (i < 0 || i > maxReached.value) return;
    Object.keys(localErrs).forEach((k) => delete localErrs[k]);
    idx.value = i;
    focusInput();
};

const markNotYet = () => {
    form.action = 'その場では対応していない';
    next();
};

const clip = (s, n = 24) => {
    const t = String(s || '').replace(/\s+/g, ' ').trim();
    return t.length > n ? `${t.slice(0, n)}…` : t;
};
const summary = (k) => {
    if (k === 'base') {
        return [dateLabel(form.occurred_on), form.shop_name, finalValue(form.category, form.category_other), kindValue.value]
            .filter(Boolean).join('・');
    }
    if (k === 'title') return clip(form.title);
    if (['what', 'action', 'consult'].includes(k)) return clip(form[k]) || '（なし）';
    return '';
};

const confirmRows = computed(() => {
    const f = flow.value;
    const rows = [
        { key: 'occurred_on', step: 'base', label: '発生日', value: dateLabel(form.occurred_on) },
        { key: 'shop_name', step: 'base', label: '店舗', value: form.shop_name },
        { key: 'category', step: 'base', label: '区分', value: finalValue(form.category, form.category_other) },
        { key: 'kind', step: 'base', label: '種類', value: kindValue.value },
        { key: 'title', step: 'title', label: '件名', value: form.title.trim() },
        { key: 'what', step: 'what', label: f.what.nm, value: form.what.trim() },
    ];
    if (f.action) rows.push({ key: 'action', step: 'action', label: f.action.nm, value: form.action.trim() });
    rows.push({ key: 'consult', step: 'consult', label: f.consult.nm, value: form.consult.trim() });
    return rows;
});

// サーバのエラーが返ったら、その項目のある手順へ戻す
const stepOfField = { occurred_on: 'base', shop_name: 'base', category: 'base', kind: 'base', title: 'title', what: 'what', action: 'action', consult: 'consult' };

const submit = () => {
    form
        .transform((d) => ({
            occurred_on: d.occurred_on,
            shop_name: d.shop_name,
            category: finalValue(d.category, d.category_other),
            kind: kindValue.value,
            title: d.title,
            what: d.what,
            action: flow.value.action ? d.action : '',
            consult: d.consult,
            group: props.group,
        }))
        .post(route('admin.incidents.store'), {
            onError: (e) => {
                const first = Object.keys(stepOfField).find((f) => e[f]);
                const i = first ? keys.value.indexOf(stepOfField[first]) : -1;
                if (i >= 0) goTo(i);
            },
        });
};

const changeGroup = (g) => {
    router.get(route('admin.incidents.create'), { group: g });
};
</script>
