<script setup>
// 成人式お支度：空いている枠（レーン）に、お客様を選んで入れる小さい画面（月の表から使う）
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { UiDialog, UiButton } from '@/Components/UI';
import { formatDateJaWithWeekday } from '@/utils/dateFormat';

const props = defineProps({
    open: { type: Boolean, default: false },
    venue: { type: Object, default: null }, // { id, name, shop_group }
    date: { type: String, default: '' },
    time: { type: String, default: '' },
    shops: { type: Array, default: () => [] },
    guides: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:open', 'booked']);

const keyword = ref('');
const results = ref([]);
const searching = ref(false);
const customer = ref(null);
const shopId = ref('');
const preferredTime = ref('');
const remarks = ref('');
const saving = ref(false);
const error = ref('');

const shopOptions = computed(() =>
    props.shops.filter(s => !props.venue?.shop_group || s.group_key === props.venue.shop_group)
);
const guide = computed(() => props.guides[props.venue?.shop_group]?.default || '');

watch(() => props.open, (open) => {
    if (!open) return;
    keyword.value = '';
    results.value = [];
    customer.value = null;
    shopId.value = shopOptions.value.length === 1 ? shopOptions.value[0].id : '';
    preferredTime.value = '';
    remarks.value = '';
    error.value = '';
});

let timer = null;
watch(keyword, (v) => {
    clearTimeout(timer);
    if (!v || !v.trim()) { results.value = []; return; }
    timer = setTimeout(search, 300);
});
const search = async () => {
    searching.value = true;
    try {
        const res = await axios.get(route('admin.customers.search'), { params: { name: keyword.value.trim() } });
        results.value = res.data.customers || [];
    } catch (e) {
        results.value = [];
    } finally {
        searching.value = false;
    }
};

const close = () => emit('update:open', false);
const submit = async () => {
    if (!customer.value) { error.value = 'お客様を選んでください。'; return; }
    saving.value = true;
    error.value = '';
    try {
        await axios.post(route('admin.seijin-prep-slots.book'), {
            customer_id: customer.value.id,
            seijin_prep_venue_id: props.venue.id,
            prep_date: props.date,
            prep_time: props.time,
            shop_id: shopId.value || null,
            preferred_time: preferredTime.value || null,
            remarks: remarks.value || null,
        });
        emit('booked', `${customer.value.name} さんを ${props.venue.name} ${props.date} ${props.time} に入れました。`);
        close();
    } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || '登録できませんでした。');
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <UiDialog :open="open" title="お客様を選んでお支度の枠に入れる" size="md" @update:open="emit('update:open', $event)">
        <div class="space-y-4 text-sm">
            <div class="rounded-soft bg-brand-surface-2 px-3 py-2">
                <div class="font-semibold">{{ venue?.name }}</div>
                <div class="tabular-nums">{{ date ? formatDateJaWithWeekday(date) : '' }} {{ time }}</div>
            </div>
            <p class="text-xs text-brand-text-muted">すでにお支度の登録（場所だけ・ほかの時刻）があるお客様は、この枠に移ります。</p>

            <div>
                <label class="block text-xs font-medium text-brand-text-muted mb-1">お客様 <span class="text-red-500">*</span></label>
                <div v-if="customer" class="flex items-center justify-between rounded-soft border border-brand-primary px-3 py-2">
                    <div>
                        <div class="font-semibold">{{ customer.name }}</div>
                        <div class="text-xs text-brand-text-muted">{{ customer.phone_number || '' }}</div>
                    </div>
                    <button type="button" class="text-xs text-brand-primary hover:underline" @click="customer = null">選び直す</button>
                </div>
                <template v-else>
                    <input v-model="keyword" type="search" placeholder="名前で探す" class="w-full rounded-md border-brand-border text-sm focus:border-brand-primary focus:ring-brand-primary" />
                    <div v-if="searching" class="mt-1 text-xs text-brand-text-muted">探しています…</div>
                    <ul v-else-if="results.length" class="mt-1 max-h-48 overflow-y-auto rounded-soft border border-brand-border divide-y divide-brand-border">
                        <li v-for="c in results" :key="c.id">
                            <button type="button" class="w-full text-left px-3 py-2 hover:bg-brand-surface-2" @click="customer = c">
                                <span class="font-medium">{{ c.name }}</span>
                                <span class="ml-2 text-xs text-brand-text-muted">{{ c.phone_number || '' }} {{ c.ceremony_area?.name || '' }}</span>
                            </button>
                        </li>
                    </ul>
                    <div v-else-if="keyword" class="mt-1 text-xs text-brand-text-muted">見つかりません</div>
                </template>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">担当店舗</label>
                    <select v-model="shopId" class="w-full rounded-md border-brand-border text-sm focus:border-brand-primary focus:ring-brand-primary">
                        <option value="">選択してください</option>
                        <option v-for="s in shopOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">希望時刻</label>
                    <input v-model="preferredTime" type="text" maxlength="50" placeholder="例：7時台" class="w-full rounded-md border-brand-border text-sm focus:border-brand-primary focus:ring-brand-primary" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">備考</label>
                    <textarea v-model="remarks" rows="2" class="w-full rounded-md border-brand-border text-sm focus:border-brand-primary focus:ring-brand-primary"></textarea>
                    <p v-if="guide" class="mt-1 text-xs text-brand-text-muted">{{ guide }}</p>
                </div>
            </div>

            <p v-if="error" class="rounded-soft bg-red-50 border border-red-200 px-3 py-2 text-red-700">{{ error }}</p>
        </div>
        <template #footer>
            <UiButton variant="ghost" @click="close">キャンセル</UiButton>
            <UiButton :loading="saving" :disabled="!customer" @click="submit">枠に入れる</UiButton>
        </template>
    </UiDialog>
</template>
