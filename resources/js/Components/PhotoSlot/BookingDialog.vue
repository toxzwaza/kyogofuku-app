<script setup>
// 前撮り：空いている席に、お客様を選んで予約を入れる小さい画面（表の画面・一覧から使う）
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { UiDialog, UiButton } from '@/Components/UI';
import { formatDateJaWithWeekday } from '@/utils/dateFormat';
import { targetLabel as slotTargetLabel } from '@/utils/photoSlotDuplicate';

const props = defineProps({
    open: { type: Boolean, default: false },
    studio: { type: Object, default: null }, // { id, name, shop_group }
    date: { type: String, default: '' },
    time: { type: String, default: '' },
    shops: { type: Array, default: () => [] }, // { id, name, group_key }
    plans: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:open', 'booked']);

const keyword = ref('');
const results = ref([]);
const searching = ref(false);
const customer = ref(null);
const shopId = ref('');
const userId = ref('');
const planId = ref('');
const remarks = ref('');
const users = ref([]);
const saving = ref(false);
const error = ref('');
// 二重登録の確認（サーバーが 409 で返した今の登録）。OK なら replace_photo_slot_id を付けて送り直す
const duplicate = ref(null);

// スタジオと同じ店舗グループの店舗だけ（グループのないスタジオはどの店舗でもよい）
const shopOptions = computed(() =>
    props.shops.filter(s => !props.studio?.shop_group || s.group_key === props.studio.shop_group)
);

watch(() => props.open, (open) => {
    if (!open) return;
    keyword.value = '';
    results.value = [];
    customer.value = null;
    shopId.value = shopOptions.value.length === 1 ? shopOptions.value[0].id : '';
    userId.value = '';
    planId.value = '';
    remarks.value = '';
    error.value = '';
    duplicate.value = null;
    loadUsers();
});

let timer = null;
watch(keyword, (v) => {
    clearTimeout(timer);
    if (!v || v.trim().length < 1) {
        results.value = [];
        return;
    }
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

const loadUsers = async () => {
    users.value = [];
    if (!shopId.value) return;
    try {
        const res = await axios.get(route('admin.schedules.shop-users'), { params: { shop_id: shopId.value } });
        users.value = res.data || [];
        if (userId.value && !users.value.some(u => u.id === Number(userId.value))) userId.value = '';
    } catch (e) {
        users.value = [];
    }
};
watch(shopId, loadUsers);
// お客様を選び直したら確認は取り消す
watch(customer, () => { duplicate.value = null; });

const targetLabel = computed(() => `${slotTargetLabel(props.date, props.time)} ${props.studio?.name || ''}`.trim());

const close = () => emit('update:open', false);

const submit = async (replaceId = null) => {
    if (!customer.value) {
        error.value = 'お客様を選んでください。';
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        await axios.post(route('admin.photo-slots.book'), {
            customer_id: customer.value.id,
            photo_studio_id: props.studio.id,
            shoot_date: props.date,
            shoot_time: props.time,
            shop_id: shopId.value || null,
            user_id: userId.value || null,
            plan_id: planId.value || null,
            remarks: remarks.value || null,
            replace_photo_slot_id: replaceId,
        });
        const moved = replaceId ? `（元の ${duplicate.value?.existing?.[0]?.label} は空きに戻しました）` : '';
        emit('booked', `${customer.value.name} さんの予約を入れました（${props.date} ${props.time}）${moved}。`);
        duplicate.value = null;
        close();
    } catch (e) {
        const dup = e.response?.status === 409 ? e.response.data?.duplicate : null;
        if (dup?.reason === 'confirm') {
            // 確認を出す（ここではまだ何も変わっていない）
            duplicate.value = dup;
            return;
        }
        duplicate.value = null;
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || '予約を入れられませんでした。');
    } finally {
        saving.value = false;
    }
};

const confirmMove = () => submit(duplicate.value.existing[0].id);
const cancelMove = () => { duplicate.value = null; };
</script>

<template>
    <UiDialog :open="open" title="お客様を選んで予約" size="md" @update:open="emit('update:open', $event)">
        <div class="space-y-4 text-sm">
            <div class="rounded-soft bg-brand-surface-2 px-3 py-2">
                <div class="font-semibold">{{ studio?.name }}</div>
                <div class="tabular-nums">{{ date ? formatDateJaWithWeekday(date) : '' }} {{ time }}</div>
            </div>

            <!-- お客様 -->
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
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">担当者</label>
                    <select v-model="userId" :disabled="!shopId" class="w-full rounded-md border-brand-border text-sm focus:border-brand-primary focus:ring-brand-primary disabled:bg-brand-surface-2">
                        <option value="">選択してください</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">プラン</label>
                    <select v-model="planId" class="w-full rounded-md border-brand-border text-sm focus:border-brand-primary focus:ring-brand-primary">
                        <option value="">選択してください</option>
                        <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">備考</label>
                    <textarea v-model="remarks" rows="2" class="w-full rounded-md border-brand-border text-sm focus:border-brand-primary focus:ring-brand-primary"></textarea>
                </div>
            </div>

            <p v-if="error" class="rounded-soft bg-red-50 border border-red-200 px-3 py-2 text-red-700">{{ error }}</p>

            <!-- 二重登録の確認 -->
            <div v-if="duplicate" class="rounded-soft border border-amber-300 bg-amber-50 px-3 py-3 text-amber-900" role="alertdialog">
                <p class="font-semibold">この方は {{ duplicate.existing[0].label }} に登録済みです。</p>
                <p class="mt-1">{{ targetLabel }} に変更しますか？</p>
                <p class="mt-1 text-xs">変更すると、元の{{ duplicate.existing[0].undecided ? '日時未定の登録は消えます' : '枠は空きに戻ります' }}。</p>
            </div>
        </div>
        <template #footer>
            <template v-if="duplicate">
                <UiButton variant="ghost" :disabled="saving" @click="cancelMove">キャンセル</UiButton>
                <UiButton :loading="saving" @click="confirmMove">変更する</UiButton>
            </template>
            <template v-else>
                <UiButton variant="ghost" @click="close">キャンセル</UiButton>
                <UiButton :loading="saving" :disabled="!customer" @click="submit()">予約を入れる</UiButton>
            </template>
        </template>
    </UiDialog>
</template>
