<template>
    <div class="space-y-4 max-w-4xl">
        <!-- サマリ -->
        <UiCard variant="default" padding="md">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <div class="text-xs text-brand-text-muted">ステージ</div>
                    <span :class="stageBadge(referral.stage)" class="inline-block mt-1 px-2 py-0.5 rounded text-sm">{{ stageLabel(referral.stage) }}</span>
                </div>
                <div>
                    <div class="text-xs text-brand-text-muted">成立紹介数</div>
                    <div class="text-lg font-semibold">{{ referral.matured_referrals_count ?? 0 }}</div>
                </div>
                <div>
                    <div class="text-xs text-brand-text-muted">紹介ポイント</div>
                    <div class="text-lg font-semibold text-brand-primary">{{ (referral.balance ?? 0).toLocaleString() }} pt</div>
                    <div class="text-xs text-brand-text-muted mt-1">平田ポイント</div>
                    <div class="text-base font-semibold text-emerald-700">{{ hirataBalance.toLocaleString() }} pt</div>
                </div>
                <div>
                    <div class="text-xs text-brand-text-muted">紹介コード</div>
                    <div class="text-sm font-mono">{{ referral.referral_code || '—' }}</div>
                </div>
            </div>
            <div v-if="referral.referrals_made" class="mt-3 text-xs text-brand-text-muted">
                紹介状況：成立待ち {{ referral.referrals_made.linked }} ／ 成約(仮) {{ referral.referrals_made.contracted }} ／ 確定 {{ referral.referrals_made.matured }} ／ 無効 {{ referral.referrals_made.rejected }}
            </div>
        </UiCard>

        <!-- ポイントを使用（ギフトカード引換 / 商品購入 / 譲渡 を選択） -->
        <UiCard variant="default" padding="md">
            <template #header><h3 class="font-serif text-base">ポイントを使用</h3></template>
            <p class="text-xs text-brand-text-muted mb-3">
                使用方法を選択してください。残高：紹介 <span class="font-semibold text-brand-primary">{{ (referral.balance ?? 0).toLocaleString() }}</span> pt ／ 平田 <span class="font-semibold text-emerald-700">{{ hirataBalance.toLocaleString() }}</span> pt（合計 {{ totalBalance.toLocaleString() }} pt）
            </p>
            <!-- 用途選択 -->
            <div class="flex flex-wrap gap-2 mb-4">
                <button
                    v-for="m in useModes"
                    :key="m.value"
                    type="button"
                    class="px-3 py-1.5 rounded-full text-xs border"
                    :class="useMode === m.value ? 'bg-brand-primary text-brand-on-primary border-brand-primary' : 'bg-brand-surface text-brand-text border-brand-border hover:bg-brand-surface-2'"
                    @click="useMode = m.value"
                >{{ m.label }}</button>
            </div>

            <!-- ① ギフトカード引換 -->
            <div v-if="useMode === 'gift'">
                <p class="text-xs text-brand-text-muted mb-3">
                    店舗でギフトカードを発行・お渡ししたら「発行済みにする」を押してください（{{ unit.toLocaleString() }}円単位・交換レート 1pt={{ rate }}円分）。<span class="text-amber-700">※ギフトカードは紹介ポイント（{{ (referral.balance ?? 0).toLocaleString() }}pt）のみ利用可。平田ポイントは使えません。</span>
                </p>
                <div class="flex items-end gap-2 flex-wrap">
                    <UiFormField label="引換額（円）" class="w-40">
                        <UiInput v-model.number="issueAmount" type="number" :min="unit" :step="unit" size="sm" />
                    </UiFormField>
                    <div class="text-sm text-brand-text-muted pb-2">必要 <span class="font-semibold text-brand-primary">{{ pointsNeeded.toLocaleString() }}</span> pt</div>
                    <UiButton variant="primary" size="sm" :loading="issuing" :disabled="!canIssue" @click="issue">発行済みにする</UiButton>
                    <span v-if="errors.amount" class="text-sm text-red-600">{{ errors.amount }}</span>
                </div>

                <div v-if="referral.gift_cards && referral.gift_cards.length" class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-brand-surface-2">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">金額</th>
                                <th class="px-3 py-2 text-left font-medium">状態</th>
                                <th class="px-3 py-2 text-left font-medium">発行日</th>
                                <th class="px-3 py-2 text-left font-medium">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr v-for="g in referral.gift_cards" :key="g.id">
                                <td class="px-3 py-2">{{ g.amount.toLocaleString() }} 円</td>
                                <td class="px-3 py-2">
                                    <span :class="g.status === 'issued' ? 'bg-green-100 text-green-900' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 rounded text-xs">
                                        {{ g.status === 'issued' ? '発行済' : '取消' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">{{ g.issued_at || '—' }}</td>
                                <td class="px-3 py-2">
                                    <button v-if="g.cancelable" type="button" class="text-xs text-brand-danger hover:underline" @click="cancel(g)">キャンセル</button>
                                    <span v-else class="text-xs text-brand-text-subtle">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ② 商品購入で使用（1pt=1円） -->
            <div v-else-if="useMode === 'purchase'">
                <p class="text-xs text-brand-text-muted mb-3">社内商品の購入にポイントを充当します（1pt=1円）。</p>
                <div class="flex items-end gap-2 flex-wrap">
                    <UiFormField label="使用ポイント（=円）" class="w-40">
                        <UiInput v-model.number="purchaseAmount" type="number" min="1" size="sm" />
                    </UiFormField>
                    <UiFormField label="メモ（任意）" class="w-56">
                        <UiInput v-model="purchaseNote" size="sm" placeholder="購入品名など" />
                    </UiFormField>
                    <UiButton variant="primary" size="sm" :disabled="!canPurchase" @click="purchase">使用する</UiButton>
                </div>
            </div>

            <!-- ③ ポイント譲渡 -->
            <div v-else-if="useMode === 'transfer'">
                <p class="text-xs text-brand-text-muted mb-3">他のお客様へポイントを譲渡します（等価・1pt単位）。</p>
                <div class="flex items-end gap-2 flex-wrap">
                    <UiFormField label="譲渡先を検索" class="w-64 relative">
                        <UiInput v-model="transferSearch" size="sm" placeholder="顧客名で検索" @input="onTransferSearch" />
                        <div v-if="transferResults.length" class="absolute z-10 mt-1 w-full bg-brand-surface border border-brand-border rounded-soft shadow max-h-56 overflow-y-auto">
                            <button
                                v-for="c in transferResults"
                                :key="c.id"
                                type="button"
                                class="block w-full text-left px-3 py-2 text-sm hover:bg-brand-surface-2"
                                @click="selectTransferTo(c)"
                            >{{ c.name }}<span v-if="c.phone_number" class="text-xs text-brand-text-subtle ml-2">{{ c.phone_number }}</span></button>
                        </div>
                    </UiFormField>
                    <UiFormField label="譲渡ポイント" class="w-32">
                        <UiInput v-model.number="transferPoints" type="number" min="1" size="sm" />
                    </UiFormField>
                    <UiFormField label="メモ（任意）" class="w-48">
                        <UiInput v-model="transferNote" size="sm" />
                    </UiFormField>
                    <UiButton variant="primary" size="sm" :disabled="!canTransfer" @click="transfer">譲渡する</UiButton>
                </div>
                <p v-if="transferTo" class="mt-2 text-xs text-brand-text-muted">譲渡先：<span class="font-semibold">{{ transferTo.name }}</span>（ID {{ transferTo.id }}）</p>
            </div>
        </UiCard>

        <!-- クーポン（任意配布） -->
        <UiCard variant="default" padding="md">
            <template #header><h3 class="font-serif text-base">クーポン</h3></template>
            <div class="flex items-end gap-2 flex-wrap mb-3">
                <UiFormField label="クーポンを配布" class="w-72">
                    <UiSelect v-model="distributeCouponId" :options="[{ value: '', label: '選択してください' }, ...couponOptions]" size="sm" />
                </UiFormField>
                <UiButton variant="primary" size="sm" :disabled="!distributeCouponId" @click="distribute">配布する</UiButton>
            </div>
            <div v-if="referral.coupons && referral.coupons.length" class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-brand-surface-2">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">クーポン</th>
                            <th class="px-3 py-2 text-left font-medium">併用</th>
                            <th class="px-3 py-2 text-left font-medium">有効期限</th>
                            <th class="px-3 py-2 text-left font-medium">状態</th>
                            <th class="px-3 py-2 text-left font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-for="c in referral.coupons" :key="c.id">
                            <td class="px-3 py-2">{{ c.name }}（{{ couponDiscount(c) }}）</td>
                            <td class="px-3 py-2">
                                <span :class="c.combinable ? 'bg-green-100 text-green-900' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 rounded text-xs">{{ c.combinable ? '併用可' : '併用不可' }}</span>
                            </td>
                            <td class="px-3 py-2">{{ c.valid_until || '—' }}</td>
                            <td class="px-3 py-2">
                                <span :class="couponStatusClass(c.status)" class="px-2 py-0.5 rounded text-xs">{{ couponStatusLabel(c.status) }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <button v-if="c.usable" type="button" class="text-xs text-brand-primary hover:underline" @click="useCoupon(c)">使用</button>
                                <span v-else class="text-xs text-brand-text-subtle">{{ c.used_at || '—' }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="py-4 text-center text-brand-text-muted text-sm">保有クーポンはありません</div>
        </UiCard>

        <!-- ポイント台帳 -->
        <UiCard variant="default" padding="md">
            <template #header><h3 class="font-serif text-base">ポイント履歴</h3></template>
            <div v-if="referral.ledger && referral.ledger.length" class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-brand-surface-2">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">日時</th>
                            <th class="px-3 py-2 text-left font-medium">ポイント</th>
                            <th class="px-3 py-2 text-left font-medium">種別</th>
                            <th class="px-3 py-2 text-right font-medium">増減</th>
                            <th class="px-3 py-2 text-left font-medium">メモ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-for="l in referral.ledger" :key="l.id">
                            <td class="px-3 py-2 whitespace-nowrap">{{ l.created_at }}</td>
                            <td class="px-3 py-2">
                                <span :class="l.point_type === 'hirata' ? 'bg-emerald-100 text-emerald-900' : 'bg-blue-100 text-blue-900'" class="px-2 py-0.5 rounded text-xs">{{ pointTypeLabel(l.point_type) }}</span>
                            </td>
                            <td class="px-3 py-2">{{ typeLabel(l.type) }}</td>
                            <td class="px-3 py-2 text-right" :class="l.amount >= 0 ? 'text-green-700' : 'text-red-700'">
                                {{ l.amount >= 0 ? '+' : '' }}{{ l.amount.toLocaleString() }}
                            </td>
                            <td class="px-3 py-2 text-brand-text-muted">{{ l.note }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="py-6 text-center text-brand-text-muted text-sm">履歴はありません</div>
        </UiCard>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { UiCard, UiFormField, UiInput, UiButton, UiSelect } from '@/Components/UI';

const props = defineProps({
    customerId: [Number, String],
    referral: { type: Object, default: () => ({}) },
    distributableCoupons: { type: Array, default: () => [] },
});

const distributeCouponId = ref('');
const couponOptions = computed(() => props.distributableCoupons.map((c) => ({
    value: c.id,
    label: `${c.name}（${couponDiscount(c)}${c.combinable ? '・併用可' : ''}）`,
})));
const couponDiscount = (c) => c.discount_type === 'rate' ? `${c.discount_value}%OFF` : `${Number(c.discount_value).toLocaleString()}円OFF`;
const couponStatusLabel = (s) => ({ held: '保有', used: '使用済', expired: '期限切れ' }[s] || s);
const couponStatusClass = (s) => ({ held: 'bg-blue-100 text-blue-900', used: 'bg-gray-200 text-gray-700', expired: 'bg-gray-100 text-gray-500' }[s]);

const distribute = () => {
    if (!distributeCouponId.value) return;
    router.post(route('admin.customers.coupons.distribute', props.customerId), { coupon_id: distributeCouponId.value }, {
        preserveScroll: true,
        onSuccess: () => { distributeCouponId.value = ''; },
    });
};
const useCoupon = (c) => {
    if (!confirm('このクーポンを使用済みにしますか？')) return;
    router.post(route('admin.customer-coupons.use', c.id), {}, { preserveScroll: true });
};

// ポイント使用の用途選択
const useMode = ref('gift');
const useModes = [
    { value: 'gift', label: 'ギフトカード引換' },
    { value: 'purchase', label: '商品購入で使用' },
    { value: 'transfer', label: 'ポイント譲渡' },
];

const hirataBalance = computed(() => Number(props.referral.hirata_balance ?? 0));
const totalBalance = computed(() => Number(props.referral.balance ?? 0) + hirataBalance.value);
const pointTypeLabel = (t) => (t === 'hirata' ? '平田' : '紹介');

const unit = computed(() => Number(props.referral.gift_card_unit || 500));
const rate = computed(() => Number(props.referral.gift_card_rate || 0.8) || 0.8);
const issueAmount = ref(unit.value);
const issuing = ref(false);

// 交換レート適用後の必要ポイント（円 ÷ レート・切上げ）
const pointsNeeded = computed(() => Math.ceil((Number(issueAmount.value) || 0) / rate.value));

const errors = computed(() => usePage().props.errors || {});
const canIssue = computed(() => issueAmount.value >= unit.value && issueAmount.value % unit.value === 0 && pointsNeeded.value <= (props.referral.balance ?? 0));

const stageLabel = (s) => ({ bronze: 'ブロンズ', silver: 'シルバー', gold: 'ゴールド', platinum: 'プラチナ' }[s] || s || '—');
const stageBadge = (s) => ({
    bronze: 'bg-orange-100 text-orange-900',
    silver: 'bg-gray-200 text-gray-800',
    gold: 'bg-yellow-100 text-yellow-900',
    platinum: 'bg-indigo-100 text-indigo-900',
}[s] || 'bg-gray-100');
const typeLabel = (t) => ({
    referrer_reward: '紹介報酬',
    referred_bonus: '被紹介特典',
    hirata_reward: '平田ポイント付与',
    gift_card_redeem: 'ギフト引換',
    gift_card_cancel_refund: 'ギフト取消返還',
    product_purchase: '商品購入',
    transfer_out: '譲渡（送出）',
    transfer_in: '譲渡（受取）',
    adjust: '調整',
}[t] || t);

const issue = () => {
    if (!canIssue.value) return;
    issuing.value = true;
    router.post(route('admin.customers.gift-cards.issue', props.customerId), { amount: issueAmount.value }, {
        preserveScroll: true,
        onFinish: () => { issuing.value = false; },
        onSuccess: () => { issueAmount.value = unit.value; },
    });
};

const cancel = (g) => {
    if (!confirm(`${g.amount.toLocaleString()}円のギフトカードをキャンセルし、ポイントを返還しますか？`)) return;
    router.post(route('admin.gift-cards.cancel', g.id), {}, { preserveScroll: true });
};

// 商品購入で使用（1pt=1円）
const purchaseAmount = ref(null);
const purchaseNote = ref('');
const canPurchase = computed(() => Number(purchaseAmount.value) >= 1 && Number(purchaseAmount.value) <= totalBalance.value);
const purchase = () => {
    if (!canPurchase.value) return;
    if (!confirm(`${Number(purchaseAmount.value).toLocaleString()}pt（${Number(purchaseAmount.value).toLocaleString()}円分）を商品購入に使用します。よろしいですか？`)) return;
    router.post(route('admin.customers.point-purchase', props.customerId), {
        amount: purchaseAmount.value,
        note: purchaseNote.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { purchaseAmount.value = null; purchaseNote.value = ''; },
    });
};

// ポイント譲渡
const transferSearch = ref('');
const transferResults = ref([]);
const transferTo = ref(null);
const transferPoints = ref(null);
const transferNote = ref('');
let transferTimer = null;
const onTransferSearch = () => {
    transferTo.value = null;
    clearTimeout(transferTimer);
    const q = transferSearch.value.trim();
    if (!q) { transferResults.value = []; return; }
    transferTimer = setTimeout(async () => {
        try {
            const { data } = await axios.get(route('admin.customers.search'), { params: { name: q } });
            transferResults.value = (data.customers || []).filter((c) => c.id !== Number(props.customerId));
        } catch (e) { transferResults.value = []; }
    }, 300);
};
const selectTransferTo = (c) => {
    transferTo.value = c;
    transferSearch.value = c.name;
    transferResults.value = [];
};
const canTransfer = computed(() => transferTo.value && Number(transferPoints.value) >= 1 && Number(transferPoints.value) <= totalBalance.value);
const transfer = () => {
    if (!canTransfer.value) return;
    if (!confirm(`「${transferTo.value.name}」様へ ${Number(transferPoints.value).toLocaleString()}pt を譲渡します。よろしいですか？`)) return;
    router.post(route('admin.customers.point-transfers', props.customerId), {
        to_customer_id: transferTo.value.id,
        points: transferPoints.value,
        note: transferNote.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { transferTo.value = null; transferSearch.value = ''; transferPoints.value = null; transferNote.value = ''; },
    });
};
</script>
