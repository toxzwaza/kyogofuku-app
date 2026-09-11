<template>
    <form @submit.prevent="$emit('search')" class="space-y-3">
        <!-- フィルタタブ -->
        <div class="flex flex-wrap gap-1">
            <button
                v-for="tab in filterTabs"
                :key="tab.key"
                type="button"
                class="px-2.5 py-1.5 text-xs rounded-md border transition-colors"
                :class="activeFilterTab === tab.key
                    ? 'bg-brand-primary text-brand-on-primary border-brand-primary'
                    : 'bg-brand-surface text-brand-text-muted border-brand-border hover:bg-brand-surface-2'"
                @click="setActiveFilterTab(tab.key)"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- 基本情報 -->
        <div v-show="activeFilterTab === 'basic'" class="rounded-lg border border-brand-border bg-brand-surface overflow-hidden">
            <div class="px-3 pb-3 pt-3 space-y-3 bg-brand-surface-2/50">
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">顧客名</label>
                    <input v-model="form.name" type="text" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" placeholder="顧客名で検索" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">ふりがな</label>
                    <input v-model="form.kana" type="text" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" placeholder="ふりがなで検索" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">成人式エリア</label>
                    <div class="space-y-2">
                        <select
                            v-model="form.ceremony_prefecture"
                            class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                            @change="form.ceremony_area_id = []"
                        >
                            <option :value="null">全て</option>
                            <option v-for="pref in ceremonyPrefectures" :key="pref" :value="pref">{{ pref }}</option>
                        </select>
                        <div v-if="form.ceremony_prefecture">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-brand-text-muted">市町村（複数選択可）</span>
                                <div class="flex gap-2">
                                    <button type="button" class="text-xs text-brand-primary hover:underline" @click="selectAllCeremonyAreas">全選択</button>
                                    <button type="button" class="text-xs text-brand-text-muted hover:underline" @click="form.ceremony_area_id = []">全解除</button>
                                </div>
                            </div>
                            <div class="rounded-md border border-brand-border bg-brand-surface max-h-40 overflow-y-auto p-2 space-y-1">
                                <label v-for="area in ceremonyAreasFiltered" :key="area.id" class="flex items-center gap-2 text-sm text-brand-text cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="form.ceremony_area_id.includes(area.id)"
                                        @change="toggleCeremonyArea(area.id)"
                                        class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                                    />
                                    <span>{{ area.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">電話番号</label>
                    <input v-model="form.phone_number" type="text" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" placeholder="電話番号で検索" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">担当店舗（複数選択可）</label>
                    <div class="rounded-md border border-brand-border bg-brand-surface max-h-40 overflow-y-auto p-2 space-y-1">
                        <label class="flex items-center gap-2 text-sm text-brand-text cursor-pointer">
                            <input
                                type="checkbox"
                                :checked="isAllShopsSelected"
                                @change="selectAllShops"
                                class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                            />
                            <span>全て</span>
                        </label>
                        <label v-for="shop in shops" :key="shop.id" class="flex items-center gap-2 text-sm text-brand-text cursor-pointer">
                            <input
                                type="checkbox"
                                :checked="isShopSelected(shop.id)"
                                @change="toggleShopFilter(shop.id)"
                                class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                            />
                            <span>{{ shop.name }}</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">登録日（開始）</label>
                    <input v-model="form.created_at_from" type="date" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">登録日（終了）</label>
                    <input v-model="form.created_at_to" type="date" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">顧客写真（全身）</label>
                    <select v-model="form.full_body_photo_presence" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option value="写真なし">写真なし</option>
                        <option value="写真あり">写真あり</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 成人式情報 -->
        <div v-show="activeFilterTab === 'seijin'" class="rounded-lg border border-brand-border bg-brand-surface overflow-hidden">
            <div class="px-3 pb-3 pt-3 space-y-3 bg-brand-surface-2/50">
                <p v-if="seijinFilterOptionsLoading" class="text-xs text-brand-text-muted">候補を読み込み中…</p>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">仕度会場</label>
                    <input
                        v-model="form.seijin_preparation_venue"
                        type="text"
                        :list="datalistId('seijin-venue')"
                        autocomplete="off"
                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                        placeholder="部分一致"
                    />
                    <datalist :id="datalistId('seijin-venue')">
                        <option v-for="v in seijinVenueOptions" :key="'sv-' + v" :value="v" />
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">時間</label>
                    <input v-model="form.seijin_preparation_time" type="text" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" placeholder="部分一致" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">他店お支度</label>
                    <select v-model="form.other_store_preparation" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option :value="true">あり</option>
                        <option :value="false">なし</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">美容室名</label>
                    <input
                        v-model="form.other_store_salon_name"
                        type="text"
                        :list="datalistId('salon-name')"
                        autocomplete="off"
                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                        placeholder="部分一致"
                    />
                    <p class="mt-1 text-[11px] text-brand-text-muted leading-snug">
                        他店お支度が「あり」のとき、空欄のまま検索すると美容室名が未登録の顧客のみに絞り込みます。
                    </p>
                    <datalist :id="datalistId('salon-name')">
                        <option v-for="n in salonNameOptions" :key="'ss-' + n" :value="n" />
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">着物発送日</label>
                    <input v-model="form.kimono_ship_date" type="date" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" />
                </div>
            </div>
        </div>

        <!-- 成約情報 -->
        <div v-show="activeFilterTab === 'contract'" class="rounded-lg border border-brand-border bg-brand-surface overflow-hidden">
            <div class="px-3 pb-3 pt-3 space-y-3 bg-brand-surface-2/50">
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">成約ステータス</label>
                    <select v-model="form.contract_status" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option value="成約なし">成約なし</option>
                        <option value="確定">確定</option>
                        <option value="保留">保留</option>
                        <option value="キャンセル">キャンセル</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">成約日（開始）</label>
                    <input v-model="form.contract_date_from" type="date" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">成約日（終了）</label>
                    <input v-model="form.contract_date_to" type="date" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">店舗</label>
                    <select v-model="form.shop_id" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option v-for="shop in shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">プラン</label>
                    <select v-model="form.plan_id" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">着物種別</label>
                    <select v-model="form.kimono_type" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option value="振袖">振袖</option>
                        <option value="袴">袴</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">安心保証</label>
                    <select v-model="form.warranty_flag" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option :value="true">あり</option>
                        <option :value="false">なし</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">担当スタッフ</label>
                    <select v-model="form.user_id" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">お仕度会場</label>
                    <input v-model="form.preparation_venue" type="text" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" placeholder="お仕度会場で検索" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">お仕度日程</label>
                    <input v-model="form.preparation_date" type="date" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" />
                </div>
            </div>
        </div>

        <!-- 制約情報 -->
        <div v-show="activeFilterTab === 'constraint'" class="rounded-lg border border-brand-border bg-brand-surface overflow-hidden">
            <div class="px-3 pb-3 pt-3 space-y-3 bg-brand-surface-2/50">
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">制約の有無</label>
                    <select v-model="form.constraint_presence" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option value="制約なし">制約なし</option>
                        <option value="制約あり">制約あり</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">制約テンプレート</label>
                    <select v-model="form.constraint_template_id" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option v-for="t in constraintTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">署名日（開始）</label>
                    <input v-model="form.constraint_signed_at_from" type="date" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">署名日（終了）</label>
                    <input v-model="form.constraint_signed_at_to" type="date" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">説明担当スタッフ</label>
                    <select v-model="form.constraint_explainer_user_id" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 前撮り情報 -->
        <div v-show="activeFilterTab === 'photo'" class="rounded-lg border border-brand-border bg-brand-surface overflow-hidden">
            <div class="px-3 pb-3 pt-3 space-y-3 bg-brand-surface-2/50">
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">担当店舗</label>
                    <select v-model="form.photo_slot_shop_id" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option v-for="shop in shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">前撮り詳細未決定</label>
                    <select v-model="form.photo_slot_details_undecided" class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm">
                        <option :value="null">全て</option>
                        <option :value="true">あり（詳細未決定あり）</option>
                        <option :value="false">なし（詳細未決定なし）</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex gap-2">
            <UiButton variant="primary" size="sm" type="submit" class="flex-1">
                <template #leading><Search :size="13" /></template>
                検索
            </UiButton>
            <UiButton variant="ghost" size="sm" type="button" @click="$emit('reset')">リセット</UiButton>
        </div>
    </form>
</template>

<script setup>
/**
 * 顧客検索フィルタパネル（基本情報・成人式情報・成約情報・制約情報・前撮り情報の5タブ）。
 *
 * Admin/Customer/Index と同じ検索条件を LINE広告の送信先選択（顧客検索タブ）で使うための
 * 独立コンポーネント。form オブジェクト（reactive）を直接バインドし、検索の実行は
 * 親コンポーネントが 'search' イベントで行う。バックエンドは CustomerSearchQuery が対応。
 */
import { computed, ref } from 'vue';
import axios from 'axios';
import { UiButton } from '@/Components/UI';
import { Search } from 'lucide-vue-next';

const props = defineProps({
    form:                { type: Object, required: true },
    shops:               { type: Array, default: () => [] },
    plans:               { type: Array, default: () => [] },
    users:               { type: Array, default: () => [] },
    ceremonyAreas:       { type: Array, default: () => [] },
    constraintTemplates: { type: Array, default: () => [] },
    idPrefix:            { type: String, default: 'customer-filter' },
});

defineEmits(['search', 'reset']);

const filterTabs = [
    { key: 'basic', label: '基本情報' },
    { key: 'seijin', label: '成人式情報' },
    { key: 'contract', label: '成約情報' },
    { key: 'constraint', label: '制約情報' },
    { key: 'photo', label: '前撮り情報' },
];
const activeFilterTab = ref('basic');

const datalistId = (name) => `${props.idPrefix}-${name}-datalist`;

// ---- 成人式エリア ----
const ceremonyPrefectures = computed(() => {
    const prefs = [...new Set((props.ceremonyAreas || []).map((a) => a.prefecture).filter(Boolean))];
    return prefs.sort((a, b) => (a || '').localeCompare(b || ''));
});
const ceremonyAreasFiltered = computed(() => {
    const pref = props.form.ceremony_prefecture;
    if (!pref) return [];
    return (props.ceremonyAreas || []).filter((a) => a.prefecture === pref);
});
const toggleCeremonyArea = (id) => {
    const idx = props.form.ceremony_area_id.indexOf(id);
    if (idx >= 0) props.form.ceremony_area_id.splice(idx, 1);
    else props.form.ceremony_area_id.push(id);
};
const selectAllCeremonyAreas = () => {
    props.form.ceremony_area_id = ceremonyAreasFiltered.value.map((a) => a.id);
};

// ---- 担当店舗（複数選択・'all' = 全店舗） ----
const isAllShopsSelected = computed(() => (props.form.customer_shop_id || []).includes('all'));
const isShopSelected = (id) => !isAllShopsSelected.value && (props.form.customer_shop_id || []).includes(id);
const selectAllShops = () => {
    props.form.customer_shop_id = isAllShopsSelected.value ? [] : ['all'];
};
const toggleShopFilter = (id) => {
    let ids = (props.form.customer_shop_id || []).filter((v) => v !== 'all');
    const idx = ids.indexOf(id);
    if (idx >= 0) ids.splice(idx, 1);
    else ids.push(id);
    props.form.customer_shop_id = ids;
};

// ---- 成人式情報の datalist 候補（タブを開いたときに遅延取得） ----
const seijinVenueOptions = ref([]);
const salonNameOptions = ref([]);
const seijinFilterOptionsLoaded = ref(false);
const seijinFilterOptionsLoading = ref(false);

const setActiveFilterTab = (key) => {
    activeFilterTab.value = key;
    if (key === 'seijin') loadSeijinFilterOptionsIfNeeded();
};

const loadSeijinFilterOptionsIfNeeded = async () => {
    if (seijinFilterOptionsLoaded.value || seijinFilterOptionsLoading.value) return;
    seijinFilterOptionsLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.customers.filters.seijin-options'));
        seijinVenueOptions.value = Array.isArray(data.seijin_preparation_venues) ? data.seijin_preparation_venues : [];
        salonNameOptions.value = Array.isArray(data.other_store_salon_names) ? data.other_store_salon_names : [];
        seijinFilterOptionsLoaded.value = true;
    } catch (e) {
        console.error('成人式情報の候補取得に失敗しました', e);
    } finally {
        seijinFilterOptionsLoading.value = false;
    }
};
</script>
