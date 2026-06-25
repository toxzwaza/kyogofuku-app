<template>
    <Head title="ステージ設定（友達紹介）" />
    <AdminLayout :breadcrumb="[{ label: '顧客' }, { label: 'ステージ設定' }]">
        <UiPageHeader title="ステージ設定（友達紹介）" description="紹介人数の閾値・還元率と、特典の各設定値を管理します。" />

        <UiCard variant="default" padding="md" class="mb-4">
            <template #header><h3 class="font-serif text-base">ステージ（人数閾値・還元率）</h3></template>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-brand-surface-2">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">ステージ</th>
                            <th class="px-4 py-2 text-left font-medium">最小成立人数</th>
                            <th class="px-4 py-2 text-left font-medium">還元率（%）</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-for="(row, i) in form.stages" :key="row.stage">
                            <td class="px-4 py-2">
                                <span :class="badgeClass(row.stage)" class="px-2 py-0.5 rounded text-xs">{{ stageLabel(row.stage) }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <UiInput v-model.number="form.stages[i].min_referrals" type="number" min="0" size="sm" class="w-28" />
                            </td>
                            <td class="px-4 py-2">
                                <UiInput v-model.number="form.stages[i].reward_rate_percent" type="number" min="0" max="100" step="0.5" size="sm" class="w-28" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-brand-text-muted mt-2">判定：成立した紹介数に対し「最小成立人数 ≤ 人数」を満たす最上位ステージを採用します。</p>
        </UiCard>

        <UiCard variant="default" padding="md" class="mb-4">
            <template #header><h3 class="font-serif text-base">特典・確定の設定値</h3></template>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <UiFormField label="被紹介者特典ポイント（pt）" hint="被紹介者が成約したとき付与">
                    <UiInput v-model.number="form.settings.referred_bonus_points" type="number" min="0" size="sm" />
                </UiFormField>
                <UiFormField label="ギフトカード引換単位（円）">
                    <UiInput v-model.number="form.settings.gift_card_unit" type="number" min="1" size="sm" />
                </UiFormField>
                <UiFormField label="確定までの月数（クーリングオフ）" hint="成約からこの月数後に特典を確定">
                    <UiInput v-model.number="form.settings.maturation_months" type="number" min="0" max="12" size="sm" />
                </UiFormField>
                <UiFormField label="紹介の有効期限（月）" hint="友達追加からこの月数で失効">
                    <UiInput v-model.number="form.settings.referral_expire_months" type="number" min="1" max="60" size="sm" />
                </UiFormField>
            </div>
        </UiCard>

        <div class="flex justify-end">
            <UiButton variant="primary" :loading="form.processing" @click="submit">保存</UiButton>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { UiPageHeader, UiCard, UiFormField, UiInput, UiButton } from '@/Components/UI';

const props = defineProps({
    stageSettings: Array,
    settings: Object,
});

const order = ['bronze', 'silver', 'gold', 'platinum'];
const sorted = [...props.stageSettings].sort((a, b) => order.indexOf(a.stage) - order.indexOf(b.stage));

const form = useForm({
    stages: sorted.map((s) => ({
        stage: s.stage,
        min_referrals: Number(s.min_referrals),
        reward_rate_percent: Number(s.reward_rate_percent),
    })),
    settings: {
        referred_bonus_points: Number(props.settings.referred_bonus_points),
        gift_card_unit: Number(props.settings.gift_card_unit),
        maturation_months: Number(props.settings.maturation_months),
        referral_expire_months: Number(props.settings.referral_expire_months),
    },
});

const stageLabel = (s) => ({ bronze: 'ブロンズ', silver: 'シルバー', gold: 'ゴールド', platinum: 'プラチナ' }[s] || s);
const badgeClass = (s) => ({
    bronze: 'bg-orange-100 text-orange-900',
    silver: 'bg-gray-200 text-gray-800',
    gold: 'bg-yellow-100 text-yellow-900',
    platinum: 'bg-indigo-100 text-indigo-900',
}[s] || 'bg-gray-100');

const submit = () => {
    form.put(route('admin.referral.stage-settings.update'), { preserveScroll: true });
};
</script>
