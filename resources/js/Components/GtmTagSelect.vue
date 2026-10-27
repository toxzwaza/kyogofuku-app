<template>
    <!-- GTMタグをマスタから選ぶ。選択肢はイベントの開催店舗に紐付くタグ（店舗未設定のタグは常に表示） -->
    <div>
        <label class="block text-sm font-medium text-brand-text mb-1">GTM ID</label>
        <select
            :value="modelValue ?? ''"
            class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
            @change="$emit('update:modelValue', $event.target.value ? Number($event.target.value) : null)"
        >
            <option value="">設定しない</option>
            <option v-for="t in options" :key="t.id" :value="t.id">{{ label(t) }}</option>
        </select>
        <p v-if="selected?.memo" class="mt-1 text-xs text-brand-text-muted whitespace-pre-line">{{ selected.memo }}</p>
        <p v-if="selected && !selected.is_active" class="mt-1 text-xs text-amber-700">
            選択中のタグは無効化されています。LPには引き続き出力されます。
        </p>
        <p v-if="selectedOutsideShops" class="mt-1 text-xs text-amber-700">
            選択中のタグは開催店舗に紐付いていません。開催店舗を確認してください。
        </p>
        <p class="mt-1 text-xs text-brand-text-muted">
            開催店舗に紐付くGTMタグから選択します（任意）。
            <Link v-if="$page.props.auth?.user?.isManager" :href="route('admin.gtm-tags.index')" class="text-brand-primary hover:underline">GTMタグ管理</Link>
        </p>
        <div v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    gtmTags: { type: Array, default: () => [] },
    shopIds: { type: Array, default: () => [] },
    error: { type: String, default: '' },
});
defineEmits(['update:modelValue']);

const matchesShops = (t) => !t.shop_ids.length || t.shop_ids.some((id) => props.shopIds.includes(id));

const selected = computed(() => props.gtmTags.find((t) => t.id === Number(props.modelValue)) || null);

// 開催店舗外・無効化済みでも、選択中のタグは選択肢に残す（保存済みの値が消えないように）
const options = computed(() => props.gtmTags.filter((t) => (t.is_active && matchesShops(t)) || t.id === selected.value?.id));

const selectedOutsideShops = computed(() => selected.value && !matchesShops(selected.value));

const label = (t) => {
    const parts = [t.gtm_id];
    if (t.company_name) parts.push(t.company_name);
    if (t.shop_names.length) parts.push(t.shop_names.join('・'));
    return parts.join(' ／ ') + (t.is_active ? '' : '（無効）');
};
</script>
