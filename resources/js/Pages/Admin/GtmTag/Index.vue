<template>
    <Head title="GTMタグ管理" />
    <AdminLayout :breadcrumb="[{ label: 'イベント・予約' }, { label: 'GTMタグ' }]">
        <UiPageHeader
            title="GTMタグ管理"
            description="Googleタグマネージャーの台帳です。どの会社が管理しているかを記録し、イベント編集では種類が「イベント」で開催店舗に紐付くタグから選択します。"
        >
            <template #actions>
                <UiButton variant="primary" @click="openCreate">
                    <template #leading><Plus :size="14" /></template>
                    GTMタグ追加
                </UiButton>
            </template>
        </UiPageHeader>

        <UiAlert v-if="unconfirmedCount" variant="warning" class="mb-4">
            未確認 {{ unconfirmedCount }}件：管理会社が空か、確度が「確認済」でないタグがあります（色付きの行）。担当者に確かめたら編集で「確認済」にしてください。
        </UiAlert>
        <UiAlert v-if="companyGtmMismatch" variant="warning" class="mb-4">
            社内共通のタグ（{{ companyTagIds.join('・') }}）が、LPに出している社内GTM（{{ companyGtmId }}）と違います。
        </UiAlert>

        <UiCard variant="default" padding="md">
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <label class="text-xs text-brand-text-muted">
                    種類
                    <UiSelect v-model="filters.kind" size="sm" :options="kindFilterOptions" class="mt-1 min-w-[8rem]" />
                </label>
                <label class="text-xs text-brand-text-muted">
                    管理会社
                    <UiSelect v-model="filters.company" size="sm" :options="companyFilterOptions" class="mt-1 min-w-[10rem]" />
                </label>
                <label class="inline-flex items-center gap-2 text-sm pb-1.5">
                    <input v-model="filters.activeOnly" type="checkbox" class="rounded border-brand-border text-brand-primary focus:ring-brand-primary" />
                    使用中のみ
                </label>
                <span class="text-xs text-brand-text-muted pb-2 ml-auto">{{ filtered.length }} / {{ gtmTags.length }}件</span>
            </div>

            <div v-if="filtered.length" class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-brand-surface-2">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium whitespace-nowrap">GTM ID</th>
                            <th class="px-3 py-2 text-left font-medium whitespace-nowrap">管理会社・担当</th>
                            <th class="px-3 py-2 text-left font-medium whitespace-nowrap">種類</th>
                            <th class="px-3 py-2 text-left font-medium">用途・使用先</th>
                            <th class="px-3 py-2 text-left font-medium whitespace-nowrap">紐付け店舗</th>
                            <th class="px-3 py-2 text-left font-medium whitespace-nowrap">確度</th>
                            <th class="px-3 py-2 text-left font-medium whitespace-nowrap">状態</th>
                            <th class="px-3 py-2 text-left font-medium w-20">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr
                            v-for="t in filtered"
                            :key="t.id"
                            :class="t.is_unconfirmed ? 'bg-natane-50 hover:bg-natane-100 dark:bg-natane-900/30 dark:hover:bg-natane-900/50' : 'hover:bg-brand-surface-2'"
                        >
                            <td class="px-3 py-2 font-mono whitespace-nowrap align-top">{{ t.gtm_id }}</td>
                            <td class="px-3 py-2 align-top">
                                <div class="whitespace-nowrap">
                                    <span v-if="t.company_name" class="font-medium">{{ t.company_name }}</span>
                                    <span v-else class="text-natane-700 dark:text-natane-200">会社未登録</span>
                                </div>
                                <div v-if="t.contact_person || t.contact_masked" class="text-xs text-brand-text-muted whitespace-nowrap">
                                    {{ t.contact_person }}<span v-if="t.contact_person && t.contact_masked">・</span><span class="font-mono">{{ t.contact_masked }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2 align-top whitespace-nowrap">
                                <UiBadge :variant="kindVariant[t.kind] || 'neutral'" size="sm">{{ kinds[t.kind] || t.kind }}</UiBadge>
                            </td>
                            <td class="px-3 py-2 text-xs align-top">
                                <div v-if="t.purpose" class="whitespace-pre-line">{{ t.purpose }}</div>
                                <div class="text-brand-text-muted">
                                    <span v-if="t.kind === 'event' || t.events_count" class="tabular-nums">イベント {{ t.events_count }}件</span>
                                    <span v-if="(t.kind === 'event' || t.events_count) && t.used_on">・</span>
                                    <span v-if="t.used_on" class="whitespace-pre-line">{{ t.used_on }}</span>
                                </div>
                                <div v-if="t.memo" class="text-brand-text-subtle whitespace-pre-line mt-0.5">{{ t.memo }}</div>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <template v-if="t.kind === 'event'">
                                    <div v-if="t.shop_names.length" class="flex flex-wrap gap-1">
                                        <UiBadge v-for="name in t.shop_names" :key="name" variant="neutral" size="sm">{{ name }}</UiBadge>
                                    </div>
                                    <span v-else class="text-brand-text-subtle text-xs">全店舗で選択可</span>
                                </template>
                                <span v-else class="text-brand-text-subtle text-xs">—</span>
                            </td>
                            <td class="px-3 py-2 align-top whitespace-nowrap">
                                <UiBadge :variant="confidenceVariant(t.confidence)" size="sm">{{ t.confidence || '未設定' }}</UiBadge>
                                <div v-if="t.confirmed_at" class="text-[11px] text-brand-text-muted mt-0.5 tabular-nums">{{ t.confirmed_at }}</div>
                            </td>
                            <td class="px-3 py-2 align-top whitespace-nowrap">
                                <UiBadge :variant="t.is_active ? 'success' : 'neutral'" dot>{{ t.is_active ? '使用中' : '停止' }}</UiBadge>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="flex gap-2 whitespace-nowrap">
                                    <button type="button" class="text-brand-primary hover:underline text-sm" @click="openEdit(t)">編集</button>
                                    <button
                                        v-if="!t.events_count"
                                        type="button"
                                        class="text-brand-danger hover:underline text-sm"
                                        @click="remove(t)"
                                    >削除</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else-if="gtmTags.length" class="py-10 text-center text-brand-text-muted text-sm">
                条件に合うGTMタグがありません。
            </div>
            <div v-else class="py-10 text-center text-brand-text-muted text-sm">
                GTMタグがありません。「GTMタグ追加」から登録してください。
            </div>
        </UiCard>

        <UiDialog v-model:open="modalOpen" size="lg">
            <template #header>{{ editing ? 'GTMタグを編集' : 'GTMタグを追加' }}</template>
            <div class="space-y-3">
                <UiFormField label="GTM ID" required :error="form.errors.gtm_id">
                    <UiInput v-model="form.gtm_id" size="sm" placeholder="例: GTM-5NP4QCSB" class="font-mono" />
                </UiFormField>
                <UiFormField label="種類" :error="form.errors.kind" hint="イベントの編集で選べるのは「イベント」だけです。社内共通・HPは台帳として記録します。">
                    <UiSelect v-model="form.kind" size="sm" :options="kindOptions" />
                </UiFormField>
                <UiFormField label="管理会社" :error="form.errors.company_name" hint="GTMコンテナを管理している会社（広告代理店・自社など）">
                    <UiInput v-model="form.company_name" size="sm" placeholder="例：BE FANG" />
                </UiFormField>
                <div class="grid gap-3 sm:grid-cols-2">
                    <UiFormField label="担当者" :error="form.errors.contact_person">
                        <UiInput v-model="form.contact_person" size="sm" />
                    </UiFormField>
                    <UiFormField label="連絡先" :error="form.errors.contact" hint="一覧では一部を隠して表示します">
                        <UiInput v-model="form.contact" size="sm" placeholder="メール・電話など" />
                    </UiFormField>
                </div>
                <UiFormField label="用途" :error="form.errors.purpose">
                    <UiTextarea v-model="form.purpose" rows="2" placeholder="例：好一の広告（Google広告・Meta など）" />
                </UiFormField>
                <UiFormField label="使用先（イベント以外）" :error="form.errors.used_on" hint="HPのURLなど。イベントの件数は自動で数えます。">
                    <UiTextarea v-model="form.used_on" rows="2" />
                </UiFormField>
                <div class="grid gap-3 sm:grid-cols-2">
                    <UiFormField label="確度" :error="form.errors.confidence" hint="担当者に確かめたら「確認済」">
                        <UiSelect v-model="form.confidence" size="sm" :options="confidenceOptions" />
                    </UiFormField>
                    <UiFormField label="確認日" :error="form.errors.confirmed_at">
                        <UiInput v-model="form.confirmed_at" type="date" size="sm" />
                    </UiFormField>
                </div>
                <UiFormField
                    v-if="form.kind === 'event'"
                    label="紐付け店舗"
                    :error="form.errors.shop_ids"
                    hint="イベントの開催店舗に含まれるときだけ選択肢に出ます。未選択なら全店舗で選択できます。"
                >
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="shop in shops"
                            :key="shop.id"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-brand-border bg-brand-surface-2 text-sm cursor-pointer"
                        >
                            <input
                                v-model="form.shop_ids"
                                type="checkbox"
                                :value="shop.id"
                                class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                            />
                            {{ shop.name }}
                        </label>
                    </div>
                </UiFormField>
                <UiFormField label="メモ" :error="form.errors.memo">
                    <UiTextarea v-model="form.memo" rows="2" />
                </UiFormField>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-brand-border text-brand-primary focus:ring-brand-primary" />
                    使用中（外すとイベント編集の選択肢に出ません。設定済みのイベントはそのまま）
                </label>
                <p v-if="editing && editing.events_count" class="text-xs text-brand-text-muted">
                    このタグは{{ editing.events_count }}件のイベントで使用中です。GTM IDを変更すると、それらのイベントのLPにも反映されます。
                </p>
                <p v-if="editing && editing.events_count && form.kind !== 'event'" class="text-xs text-amber-700">
                    種類を「イベント」以外にすると、新しいイベントでは選べなくなります（設定済みのイベントはそのまま）。
                </p>
            </div>
            <template #footer>
                <UiButton variant="ghost" @click="modalOpen = false">キャンセル</UiButton>
                <UiButton variant="primary" :loading="form.processing" @click="submit">{{ editing ? '更新' : '追加' }}</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { UiPageHeader, UiCard, UiButton, UiBadge, UiDialog, UiFormField, UiInput, UiTextarea, UiSelect, UiAlert } from '@/Components/UI';
import { Plus } from 'lucide-vue-next';

const props = defineProps({
    gtmTags: Array,
    shops: Array,
    kinds: { type: Object, default: () => ({ event: 'イベント', company: '社内共通', site: 'HP' }) },
    confidences: { type: Array, default: () => ['確認済', '高', '中', '低'] },
    companyGtmId: { type: String, default: '' },
});

const NO_COMPANY = '__none__';

const kindVariant = { event: 'primary', company: 'accent', site: 'neutral' };
const confidenceVariant = (c) => ({ 確認済: 'success', 高: 'primary', 中: 'warning', 低: 'danger' }[c] || 'neutral');

const kindOptions = computed(() => Object.entries(props.kinds).map(([value, label]) => ({ value, label })));
const kindFilterOptions = computed(() => [{ value: '', label: 'すべて' }, ...kindOptions.value]);
const confidenceOptions = computed(() => [{ value: '', label: '未設定' }, ...props.confidences.map((c) => ({ value: c, label: c }))]);
const companyFilterOptions = computed(() => {
    const names = [...new Set(props.gtmTags.map((t) => t.company_name).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'ja'));
    const opts = [{ value: '', label: 'すべて' }, ...names.map((n) => ({ value: n, label: n }))];
    if (props.gtmTags.some((t) => !t.company_name)) opts.push({ value: NO_COMPANY, label: '会社未登録' });
    return opts;
});

const filters = reactive({ kind: '', company: '', activeOnly: false });

const filtered = computed(() =>
    props.gtmTags.filter((t) => {
        if (filters.kind && t.kind !== filters.kind) return false;
        if (filters.company === NO_COMPANY && t.company_name) return false;
        if (filters.company && filters.company !== NO_COMPANY && t.company_name !== filters.company) return false;
        if (filters.activeOnly && !t.is_active) return false;
        return true;
    })
);

const unconfirmedCount = computed(() => props.gtmTags.filter((t) => t.is_unconfirmed).length);

// 社内共通のタグがマスタにあり、LPに出している社内GTM（config）と違えば注意
const companyTagIds = computed(() => props.gtmTags.filter((t) => t.kind === 'company' && t.is_active).map((t) => t.gtm_id));
const companyGtmMismatch = computed(
    () => props.companyGtmId && companyTagIds.value.length && !companyTagIds.value.includes(props.companyGtmId)
);

const modalOpen = ref(false);
const editing = ref(null);

const form = useForm({
    company_name: '',
    contact_person: '',
    contact: '',
    gtm_id: '',
    kind: 'event',
    purpose: '',
    used_on: '',
    confidence: '',
    confirmed_at: '',
    memo: '',
    is_active: true,
    shop_ids: [],
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
};

const openEdit = (t) => {
    editing.value = t;
    form.clearErrors();
    form.company_name = t.company_name || '';
    form.contact_person = t.contact_person || '';
    form.contact = t.contact || '';
    form.gtm_id = t.gtm_id;
    form.kind = t.kind || 'event';
    form.purpose = t.purpose || '';
    form.used_on = t.used_on || '';
    form.confidence = t.confidence || '';
    form.confirmed_at = t.confirmed_at || '';
    form.memo = t.memo || '';
    form.is_active = t.is_active;
    form.shop_ids = [...t.shop_ids];
    modalOpen.value = true;
};

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { modalOpen.value = false; } };
    // 空文字は未設定として送る
    const payload = (data) => ({
        ...data,
        confidence: data.confidence || null,
        confirmed_at: data.confirmed_at || null,
    });
    if (editing.value) {
        form.transform(payload).put(route('admin.gtm-tags.update', editing.value.id), opts);
    } else {
        form.transform(payload).post(route('admin.gtm-tags.store'), opts);
    }
};

const remove = (t) => {
    if (!confirm(`GTMタグ「${t.gtm_id}」を削除しますか？`)) return;
    router.delete(route('admin.gtm-tags.destroy', t.id), { preserveScroll: true });
};
</script>
