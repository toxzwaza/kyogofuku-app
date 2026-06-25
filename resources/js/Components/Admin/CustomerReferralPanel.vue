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
                    <div class="text-xs text-brand-text-muted">ポイント残高</div>
                    <div class="text-lg font-semibold text-brand-primary">{{ (referral.balance ?? 0).toLocaleString() }} pt</div>
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

        <!-- ギフトカード引換（発行） -->
        <UiCard variant="default" padding="md">
            <template #header><h3 class="font-serif text-base">ギフトカード引換</h3></template>
            <p class="text-xs text-brand-text-muted mb-3">
                店舗でギフトカードを発行・お渡ししたら「発行済みにする」を押してください（ポイントを{{ unit.toLocaleString() }}円単位で減算します）。
            </p>
            <div class="flex items-end gap-2 flex-wrap">
                <UiFormField label="引換額（円）" class="w-40">
                    <UiInput v-model.number="issueAmount" type="number" :min="unit" :step="unit" size="sm" />
                </UiFormField>
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
                            <th class="px-3 py-2 text-left font-medium">種別</th>
                            <th class="px-3 py-2 text-right font-medium">増減</th>
                            <th class="px-3 py-2 text-left font-medium">メモ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-for="l in referral.ledger" :key="l.id">
                            <td class="px-3 py-2 whitespace-nowrap">{{ l.created_at }}</td>
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

const unit = computed(() => Number(props.referral.gift_card_unit || 500));
const issueAmount = ref(unit.value);
const issuing = ref(false);

const errors = computed(() => usePage().props.errors || {});
const canIssue = computed(() => issueAmount.value >= unit.value && issueAmount.value % unit.value === 0 && issueAmount.value <= (props.referral.balance ?? 0));

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
    gift_card_redeem: 'ギフト引換',
    gift_card_cancel_refund: 'ギフト取消返還',
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
</script>
