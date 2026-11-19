<template>
    <Head :title="isEdit ? 'クレジット契約' : '契約を登録'" />

    <AdminLayout :breadcrumb="[{ label: 'クレジット', href: route('admin.credits.index') }, { label: isEdit ? '契約' : '契約を登録' }]">
        <UiPageHeader
            :title="isEdit ? `クレジット契約：${customer?.name ?? ''}` : '契約を登録'"
            :description="isEdit ? '契約の内容と月ごとの支払予定です。' : '回数と月額を入れると、毎月の支払予定が自動でできます。'"
        >
            <template #actions>
                <UiButton v-if="customer" variant="ghost" :href="route('admin.customers.show', customer.id) + '?tab=credit'">お客様の画面へ</UiButton>
                <UiButton variant="ghost" :href="route('admin.credits.index')">一覧へ</UiButton>
            </template>
        </UiPageHeader>

        <!-- 仮登録（担当の方に内容を確認中） -->
        <UiAlert v-if="contract?.is_provisional" variant="warning" title="仮登録です（内容を確認中）" class="mb-4">
            <p v-if="contract.provisional_note" class="whitespace-pre-line">確認していること：{{ contract.provisional_note }}</p>
            <p class="mt-1 text-xs">確認が済んだら内容を直し、下の「仮登録（確認中）」のチェックを外して保存してください。</p>
        </UiAlert>
        <p v-else-if="contract?.confirmed_at" class="mb-4 text-xs text-brand-text-muted">
            仮登録を外しました：{{ contract.confirmed_at }}<span v-if="contract.confirmer">（{{ contract.confirmer }}）</span>
        </p>

        <form class="grid lg:grid-cols-2 gap-6 items-start" @submit.prevent="submit">
            <UiCard>
                <template #header><h3 class="font-serif font-semibold">契約の内容</h3></template>
                <div class="space-y-4">
                    <!-- お客様 -->
                    <UiFormField v-if="!isEdit" label="お客様" required :error="form.errors.customer_id || form.errors.new_customer_name">
                        <div v-if="pickedCustomer" class="flex items-center gap-2">
                            <span class="font-medium">{{ pickedCustomer.name }}</span>
                            <UiButton size="sm" variant="link" @click="clearCustomer">変更</UiButton>
                        </div>
                        <template v-else>
                            <div class="flex gap-3 text-sm mb-2">
                                <label class="inline-flex items-center gap-1"><input v-model="customerMode" type="radio" value="existing" /> 登録済みのお客様</label>
                                <label class="inline-flex items-center gap-1"><input v-model="customerMode" type="radio" value="new" /> 新しいお客様</label>
                            </div>
                            <div v-if="customerMode === 'existing'">
                                <UiInput v-model="customerQuery" placeholder="お名前で検索" @input="searchCustomers" />
                                <ul v-if="customerResults.length" class="mt-1 border border-brand-border rounded-soft divide-y divide-brand-border max-h-56 overflow-auto bg-brand-surface">
                                    <li v-for="c in customerResults" :key="c.id">
                                        <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-brand-surface-2" @click="pickCustomer(c)">
                                            {{ c.name }} <span class="text-xs text-brand-text-muted">{{ c.phone_number }}</span>
                                        </button>
                                    </li>
                                </ul>
                                <p v-else-if="customerQuery.length >= 1 && searched" class="mt-1 text-xs text-brand-text-muted">見つかりません。「新しいお客様」で登録してください。</p>
                            </div>
                            <div v-else class="grid grid-cols-2 gap-2">
                                <UiInput v-model="form.new_customer_name" placeholder="お名前（例：山田　花子）" />
                                <UiInput v-model="form.new_customer_kana" placeholder="ふりがな" />
                                <p class="col-span-2 text-xs text-brand-text-muted">所属店舗は空のまま登録します（あとでお客様の画面から設定できます）。</p>
                            </div>
                        </template>
                    </UiFormField>

                    <div class="grid grid-cols-2 gap-3">
                        <UiFormField
                            label="支払方法（信販会社）"
                            :required="!form.is_provisional"
                            :error="form.errors.credit_company_id"
                            :hint="form.is_provisional ? '仮登録のあいだは「未定」のままでも保存できます' : ''"
                        >
                            <UiSelect v-model="form.credit_company_id" :options="companyOptions" placeholder="選んでください" :disabled="readonly" />
                        </UiFormField>
                        <UiFormField label="契約日" :error="form.errors.contract_date">
                            <UiInput v-model="form.contract_date" type="date" :disabled="readonly" />
                        </UiFormField>
                        <UiFormField label="商品" :error="form.errors.item_name" class="col-span-2">
                            <UiInput v-model="form.item_name" placeholder="例：振袖" :disabled="readonly" />
                        </UiFormField>
                        <UiFormField label="元金（円）" required :error="form.errors.principal">
                            <UiInput v-model.number="form.principal" type="number" min="0" :disabled="readonly" />
                        </UiFormField>
                        <UiFormField label="手数料（円）" :error="form.errors.fee">
                            <UiInput v-model.number="form.fee" type="number" min="0" :disabled="readonly" />
                        </UiFormField>
                        <UiFormField label="担当者" :error="form.errors.staff_user_id" :hint="contract?.staff_name && !form.staff_user_id ? `取り込み時の担当：${contract.staff_name}` : ''">
                            <UiSelect v-model="form.staff_user_id" :options="staffOptions" :placeholder="contract?.staff_name || '選んでください'" :disabled="readonly" />
                        </UiFormField>
                        <UiFormField v-if="isEdit" label="状態" :error="form.errors.status">
                            <UiSelect v-model="form.status" :options="statusOptions" :disabled="readonly" />
                        </UiFormField>
                        <UiFormField label="メモ" :error="form.errors.note" class="col-span-2">
                            <UiTextarea v-model="form.note" :rows="2" :disabled="readonly" />
                        </UiFormField>
                    </div>
                    <div class="rounded-soft border border-brand-border p-3 space-y-2" :class="form.is_provisional ? 'bg-natane-50 dark:bg-natane-900/30' : ''">
                        <label class="inline-flex items-center gap-2 text-sm font-medium">
                            <input v-model="form.is_provisional" type="checkbox" :disabled="readonly" />
                            仮登録（担当の方に内容を確認中）
                        </label>
                        <UiFormField v-if="form.is_provisional" label="確認していること" :error="form.errors.provisional_note">
                            <UiTextarea v-model="form.provisional_note" :rows="2" placeholder="例：信販会社がわからない" :disabled="readonly" />
                        </UiFormField>
                        <p v-else-if="contract?.is_provisional" class="text-xs text-brand-text-muted">保存すると仮登録を外します（確認済みになります）。</p>
                    </div>
                    <p v-if="contract?.source_file" class="text-xs text-brand-text-muted">取り込み元：{{ contract.source_file }}</p>
                </div>
            </UiCard>

            <!-- 新規：支払予定の作り方 -->
            <UiCard v-if="!isEdit">
                <template #header><h3 class="font-serif font-semibold">支払予定</h3></template>
                <div class="grid grid-cols-2 gap-3">
                    <UiFormField label="支払開始月" required :error="form.errors.start_month">
                        <UiInput v-model="form.start_month" type="month" />
                    </UiFormField>
                    <UiFormField label="回数" required :error="form.errors.count">
                        <UiInput v-model.number="form.count" type="number" min="1" max="120" />
                    </UiFormField>
                    <UiFormField label="月額（円）" required :error="form.errors.monthly_amount">
                        <UiInput v-model.number="form.monthly_amount" type="number" min="0" />
                    </UiFormField>
                    <UiFormField label="初回だけ別の金額（円）" hint="同じなら空のまま" :error="form.errors.first_amount">
                        <UiInput v-model.number="form.first_amount" type="number" min="0" />
                    </UiFormField>
                </div>
                <div class="mt-4 text-sm bg-brand-surface-2 rounded-soft p-3 space-y-1">
                    <div>支払総額：<span class="tabular-nums font-semibold">{{ yen(plannedTotal) }}</span></div>
                    <div v-if="plannedEnd">最終回：<span class="tabular-nums">{{ plannedEnd }}</span></div>
                    <div v-if="principalPlusFee && plannedTotal !== principalPlusFee" class="text-amber-700">
                        元金＋手数料（{{ yen(principalPlusFee) }}）と {{ yen(plannedTotal - principalPlusFee) }} 違います。
                    </div>
                </div>
                <div class="mt-4">
                    <UiButton type="submit" variant="primary" :loading="form.processing" block>登録する</UiButton>
                </div>
            </UiCard>

            <!-- 編集：月ごとの支払予定 -->
            <UiCard v-else padding="none">
                <template #header>
                    <div class="flex items-center justify-between">
                        <h3 class="font-serif font-semibold">月ごとの支払予定</h3>
                        <div class="text-sm text-brand-text-muted">
                            残額 <span class="tabular-nums font-semibold text-brand-text">{{ yen(unpaidTotal) }}</span>
                            ／ 合計 <span class="tabular-nums">{{ yen(scheduleTotal) }}</span>
                        </div>
                    </div>
                </template>
                <div class="max-h-[60vh] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-brand-surface-2 text-xs text-brand-text-muted sticky top-0">
                            <tr>
                                <th class="text-left px-3 py-2">年月</th>
                                <th class="text-right px-3 py-2">金額</th>
                                <th class="text-center px-3 py-2">支払済</th>
                                <th class="text-left px-3 py-2">確認</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in form.payments" :key="p.id" class="border-t border-brand-border" :class="isCurrent(p.due_month) ? 'bg-amber-50' : ''">
                                <td class="px-3 py-1.5 tabular-nums">{{ p.due_month }}</td>
                                <td class="px-3 py-1.5 text-right">
                                    <input v-if="!readonly" v-model.number="p.amount" type="number" min="0" class="w-28 text-right rounded-md border-brand-border text-sm py-1" />
                                    <span v-else class="tabular-nums">{{ yen(p.amount) }}</span>
                                </td>
                                <td class="px-3 py-1.5 text-center">
                                    <input v-model="p.is_paid" type="checkbox" :disabled="readonly" class="rounded border-brand-border text-brand-primary" />
                                </td>
                                <td class="px-3 py-1.5 text-xs text-brand-text-muted">{{ p.checker ? `${p.checker} ${p.paid_checked_at}` : '' }}</td>
                            </tr>
                            <tr v-if="form.payments.length === 0">
                                <td colspan="4" class="px-3 py-6 text-center text-brand-text-muted">支払予定がありません。</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!readonly" class="p-4 border-t border-brand-border space-y-3">
                    <UiButton type="submit" variant="primary" :loading="form.processing" block>保存する</UiButton>
                    <details class="text-sm">
                        <summary class="cursor-pointer text-brand-text-muted">支払予定を作り直す（回数・月額から）</summary>
                        <div class="grid grid-cols-2 gap-2 mt-3">
                            <UiInput v-model="regen.start_month" type="month" />
                            <UiInput v-model.number="regen.count" type="number" min="1" placeholder="回数" />
                            <UiInput v-model.number="regen.monthly_amount" type="number" min="0" placeholder="月額" />
                            <UiInput v-model.number="regen.first_amount" type="number" min="0" placeholder="初回だけ別額" />
                        </div>
                        <p class="text-xs text-brand-danger mt-2">今の予定（支払済のチェックも）は消えて作り直されます。</p>
                        <UiButton class="mt-2" variant="danger" size="sm" :loading="regen.processing" @click="doRegen">作り直す</UiButton>
                    </details>
                </div>
            </UiCard>
        </form>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { UiPageHeader, UiButton, UiCard, UiInput, UiSelect, UiTextarea, UiFormField, UiAlert } from '@/Components/UI';

const props = defineProps({
    contract: { type: Object, default: null },
    customer: { type: Object, default: null },
    companies: Array,
    staffs: Array,
    defaultStaffId: { type: Number, default: null },
    canEdit: { type: Boolean, default: true },
});

const isEdit = computed(() => !!props.contract);
const readonly = computed(() => isEdit.value && !props.canEdit);

const thisMonth = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

const form = useForm({
    customer_id: props.customer?.id ?? null,
    new_customer_name: '',
    new_customer_kana: '',
    credit_company_id: props.contract?.credit_company_id ? String(props.contract.credit_company_id) : '',
    contract_date: props.contract?.contract_date ?? new Date().toISOString().slice(0, 10),
    item_name: props.contract?.item_name ?? '',
    principal: props.contract?.principal ?? null,
    fee: props.contract?.fee ?? null,
    staff_user_id: props.contract ? (props.contract.staff_user_id ? String(props.contract.staff_user_id) : '') : (props.defaultStaffId ? String(props.defaultStaffId) : ''),
    status: props.contract?.status ?? 'active',
    note: props.contract?.note ?? '',
    is_provisional: !!props.contract?.is_provisional,
    provisional_note: props.contract?.provisional_note ?? '',
    start_month: thisMonth(),
    count: 10,
    monthly_amount: null,
    first_amount: null,
    payments: (props.contract?.payments ?? []).map((p) => ({ ...p })),
});

const companyOptions = computed(() => [
    // 仮登録のあいだだけ「未定」を選べる
    ...(form.is_provisional ? [{ value: '', label: '（未定）' }] : []),
    ...props.companies.map((c) => ({ value: String(c.id), label: c.name })),
]);
const staffOptions = computed(() => [{ value: '', label: '（なし）' }, ...props.staffs.map((s) => ({ value: String(s.id), label: s.name }))]);
const statusOptions = [
    { value: 'active', label: '支払中' },
    { value: 'completed', label: '完済' },
    { value: 'cancelled', label: '解約・組換' },
];

const yen = (v) => '¥' + Number(v || 0).toLocaleString();

// --- お客様の選択（新規登録のとき） ---
const pickedCustomer = ref(props.customer);
const customerMode = ref('existing');
const customerQuery = ref('');
const customerResults = ref([]);
const searched = ref(false);
let timer = null;
const searchCustomers = () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
        if (!customerQuery.value.trim()) { customerResults.value = []; return; }
        const { data } = await axios.get(route('admin.customers.search'), { params: { name: customerQuery.value } });
        customerResults.value = data.customers || [];
        searched.value = true;
    }, 300);
};
const pickCustomer = (c) => { pickedCustomer.value = c; form.customer_id = c.id; customerResults.value = []; };
const clearCustomer = () => { pickedCustomer.value = null; form.customer_id = null; };

// --- 新規の予定プレビュー ---
const plannedTotal = computed(() => {
    const n = Number(form.count) || 0;
    const m = Number(form.monthly_amount) || 0;
    if (n <= 0) return 0;
    const first = form.first_amount === null || form.first_amount === '' ? m : Number(form.first_amount);
    return first + m * (n - 1);
});
const plannedEnd = computed(() => {
    if (!form.start_month || !form.count) return '';
    const [y, mo] = form.start_month.split('-').map(Number);
    const d = new Date(y, mo - 1 + Number(form.count) - 1, 1);
    return `${d.getFullYear()}年${d.getMonth() + 1}月`;
});
const principalPlusFee = computed(() => (Number(form.principal) || 0) + (Number(form.fee) || 0));

// --- 編集 ---
const scheduleTotal = computed(() => form.payments.reduce((s, p) => s + (Number(p.amount) || 0), 0));
const unpaidTotal = computed(() => form.payments.filter((p) => !p.is_paid).reduce((s, p) => s + (Number(p.amount) || 0), 0));
const isCurrent = (ym) => ym === thisMonth();

const submit = () => {
    if (isEdit.value) {
        form.transform((d) => ({
            ...d,
            payments: d.payments.map((p) => ({ id: p.id, amount: Number(p.amount) || 0, is_paid: !!p.is_paid })),
        })).put(route('admin.credits.update', props.contract.id), { preserveScroll: true });
    } else {
        if (customerMode.value === 'new') form.customer_id = null;
        // 登録後は同じ画面（Form.vue）のまま編集画面に移るため、入力欄が登録前の空のまま残る。読み込み直して予定を出す
        form.post(route('admin.credits.store'), { onSuccess: () => { window.location.reload(); } });
    }
};

const regen = useForm({
    start_month: props.contract?.payments?.[0]?.due_month ?? thisMonth(),
    count: props.contract?.payments?.length || 10,
    monthly_amount: props.contract?.payments?.[1]?.amount ?? props.contract?.payments?.[0]?.amount ?? null,
    first_amount: null,
});
const doRegen = () => {
    if (!confirm('今の支払予定を消して作り直します。よろしいですか？')) return;
    regen.post(route('admin.credits.regenerate', props.contract.id), {
        preserveScroll: true,
        onSuccess: () => { window.location.reload(); },
    });
};
</script>
