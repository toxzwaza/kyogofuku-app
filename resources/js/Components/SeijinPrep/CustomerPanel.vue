<script setup>
// お客様の詳細：成人式お支度（お支度場所／他店お支度／未定、時刻の枠、希望時刻、備考）
// 福井の場所は時刻を持たない番号枠（日を選ぶと、その日の次の番号に入る）。メイク有無が必須で、希望時刻は入力しない
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { UiButton, UiBadge, UiDialog } from '@/Components/UI';
import { Sparkles, Pencil } from 'lucide-vue-next';
import { formatDateJaWithWeekday } from '@/utils/dateFormat';

const props = defineProps({
    customerId: { type: Number, required: true },
    customerShopId: { type: Number, default: null },
});

const loading = ref(true);
const loadError = ref('');
const prep = ref(null);
const hidden = ref(false);
const venues = ref([]);
const shops = ref([]);
const guides = ref({});
const notice = ref('');

const load = async () => {
    loading.value = true;
    loadError.value = '';
    try {
        const res = await axios.get(route('admin.customers.seijin-prep.show', props.customerId));
        prep.value = res.data.prep;
        hidden.value = res.data.hidden;
        venues.value = res.data.venues || [];
        shops.value = res.data.shops || [];
        guides.value = res.data.guides || {};
    } catch (e) {
        loadError.value = '成人式お支度を読み込めませんでした。';
    } finally {
        loading.value = false;
    }
};
onMounted(load);

// 編集
const editing = ref(false);
const form = reactive({ mode: 'venue', venue_id: '', prep_date: '', prep_time: '', has_makeup: null, preferred_time: '', remarks: '', shop_id: '' });
const errors = ref({});
const saving = ref(false);
const dates = ref([]);
const times = ref([]);

const venue = computed(() => venues.value.find(v => v.id === Number(form.venue_id)) || null);
const groupOf = computed(() => {
    if (form.mode === 'venue' && venue.value?.shop_group) return venue.value.shop_group;
    return shops.value.find(s => s.id === Number(form.shop_id))?.group_key || null;
});
const guide = computed(() => {
    const g = guides.value[groupOf.value];
    if (form.mode === 'other') {
        return g?.other_store || Object.values(guides.value).map(x => x.other_store).join(' ／ ');
    }
    return g?.default || '';
});
// 福井の場所（番号枠）か
const numbered = computed(() => form.mode === 'venue' && !!venue.value?.numbered);
// 福井の登録（場所が福井、または他店・未定で担当店舗が福井の店）では希望時刻を入力しない
const isFukui = computed(() => (form.mode === 'venue' && venue.value) ? !!venue.value.numbered : groupOf.value === 'fukui');
const dayInfo = computed(() => (numbered.value ? times.value[0] || null : null));
const shopOptions = computed(() => shops.value.filter(s => form.mode !== 'venue' || !venue.value?.shop_group || s.group_key === venue.value.shop_group));

const startEdit = () => {
    const p = prep.value;
    Object.assign(form, {
        mode: p?.mode || 'venue',
        venue_id: p?.venue_id || '',
        prep_date: p?.prep_date || '',
        prep_time: p?.prep_time || '',
        has_makeup: p?.has_makeup ?? null,
        preferred_time: p?.preferred_time || '',
        remarks: p?.remarks || '',
        shop_id: p?.shop_id || props.customerShopId || '',
    });
    errors.value = {};
    editing.value = true;
    loadDates();
    loadTimes();
};

const loadDates = async () => {
    dates.value = [];
    if (form.mode !== 'venue' || !form.venue_id) return;
    try {
        const res = await axios.get(route('admin.customers.seijin-prep.dates', props.customerId), { params: { venue_id: form.venue_id } });
        dates.value = res.data.dates || [];
        // 今入っている日（過去の日など）も選べるように残す
        if (form.prep_date && !dates.value.includes(form.prep_date)) dates.value.unshift(form.prep_date);
    } catch (e) { dates.value = []; }
};
const loadTimes = async () => {
    times.value = [];
    if (form.mode !== 'venue' || !form.venue_id || !form.prep_date) return;
    try {
        const res = await axios.get(route('admin.customers.seijin-prep.times', props.customerId), { params: { venue_id: form.venue_id, prep_date: form.prep_date } });
        times.value = res.data.times || [];
    } catch (e) { times.value = []; }
};
const onVenueChange = () => { form.prep_date = ''; form.prep_time = ''; loadDates(); times.value = []; };
const onDateChange = () => { form.prep_time = ''; loadTimes(); };
watch(() => form.mode, () => { if (editing.value) { loadDates(); loadTimes(); } });

const timeFull = (t) => !t.mine && t.booked >= t.capacity;

const save = async () => {
    saving.value = true;
    errors.value = {};
    try {
        const res = await axios.put(route('admin.customers.seijin-prep.save', props.customerId), {
            mode: form.mode,
            venue_id: form.mode === 'venue' ? form.venue_id || null : null,
            prep_date: form.mode === 'venue' && (numbered.value || form.prep_time) ? form.prep_date || null : null,
            prep_time: form.mode === 'venue' && !numbered.value && form.prep_time ? form.prep_time : null,
            has_makeup: numbered.value ? form.has_makeup : null,
            // 福井では送らない（サーバーが今の値を残す）
            preferred_time: isFukui.value ? null : form.preferred_time || null,
            remarks: form.remarks || null,
            shop_id: form.shop_id || null,
        });
        notice.value = res.data?.message || '保存しました。';
        editing.value = false;
        await load();
    } catch (e) {
        const errs = e.response?.data?.errors;
        errors.value = errs ? Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v.join(' ')])) : { error: e.response?.data?.message || '保存できませんでした。' };
    } finally {
        saving.value = false;
    }
};

const confirmCancel = ref(false);
const cancelling = ref(false);
const cancelPrep = async () => {
    cancelling.value = true;
    try {
        const res = await axios.delete(route('admin.customers.seijin-prep.destroy', props.customerId));
        notice.value = res.data?.message || '取り消しました。';
        confirmCancel.value = false;
        editing.value = false;
        await load();
    } catch (e) {
        const errs = e.response?.data?.errors;
        errors.value = { error: errs ? Object.values(errs).flat().join(' ') : '取り消せませんでした。' };
        confirmCancel.value = false;
    } finally {
        cancelling.value = false;
    }
};

const modeLabel = { venue: 'お支度場所', other: '他店お支度', undecided: '未定' };
const inputCls = 'w-full rounded-md border-brand-border text-sm focus:border-brand-primary focus:ring-brand-primary disabled:bg-brand-surface-2';
</script>

<template>
    <section class="mb-6">
        <header class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-2">
                <Sparkles :size="16" class="text-brand-primary" />
                <h3 class="font-serif text-base font-semibold text-brand-text">成人式お支度（新）</h3>
            </div>
            <UiButton v-if="!loading && !hidden && !editing" size="sm" variant="ghost" @click="startEdit">
                <template #leading><Pencil :size="13" /></template>
                {{ prep ? '変更' : '登録' }}
            </UiButton>
        </header>

        <p v-if="notice" class="mb-2 rounded-soft bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">{{ notice }}</p>

        <div v-if="loading" class="text-sm text-brand-text-muted">読み込み中…</div>
        <p v-else-if="loadError" class="text-sm text-brand-danger">{{ loadError }}</p>
        <p v-else-if="hidden" class="text-sm text-brand-text-muted">ほかの店舗グループでお支度の登録があります（このグループからは見られません）。</p>

        <!-- 表示 -->
        <div v-else-if="!editing" class="rounded-md border border-brand-border p-3 text-sm">
            <template v-if="prep">
                <div class="flex flex-wrap items-center gap-2">
                    <UiBadge :variant="prep.mode === 'venue' ? 'primary' : prep.mode === 'other' ? 'warning' : 'neutral'">{{ modeLabel[prep.mode] }}</UiBadge>
                    <span v-if="prep.venue_name" class="font-semibold">{{ prep.venue_name }}</span>
                    <template v-if="prep.numbered">
                        <span v-if="prep.prep_date" class="tabular-nums">{{ formatDateJaWithWeekday(prep.prep_date) }}・番号 {{ prep.seat_no }}</span>
                        <UiBadge v-else variant="warning">日未定</UiBadge>
                    </template>
                    <span v-else-if="prep.prep_date && prep.prep_time" class="tabular-nums">{{ formatDateJaWithWeekday(prep.prep_date) }} {{ prep.prep_time }}（レーン{{ prep.seat_no }}）</span>
                    <UiBadge v-else-if="prep.mode === 'venue'" variant="warning">時刻未定</UiBadge>
                </div>
                <dl class="mt-2 grid gap-1 sm:grid-cols-2 text-xs">
                    <div v-if="prep.numbered"><dt class="inline text-brand-text-muted">メイク有無：</dt><dd class="inline">{{ prep.has_makeup === null ? '未選択' : prep.has_makeup ? 'あり' : 'なし' }}</dd></div>
                    <div v-if="!prep.numbered || prep.preferred_time"><dt class="inline text-brand-text-muted">希望時刻：</dt><dd class="inline">{{ prep.preferred_time || '—' }}</dd></div>
                    <div v-if="prep.original_prep_time"><dt class="inline text-brand-text-muted">元の時刻：</dt><dd class="inline tabular-nums">{{ prep.original_prep_time }}</dd></div>
                    <div><dt class="inline text-brand-text-muted">担当店舗：</dt><dd class="inline">{{ shops.find(s => s.id === prep.shop_id)?.name || '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="inline text-brand-text-muted">備考：</dt><dd class="inline whitespace-pre-wrap">{{ prep.remarks || '—' }}</dd></div>
                </dl>
            </template>
            <p v-else class="text-brand-text-muted">まだ登録がありません。上の「成人式情報」は前の項目です（新しい登録に移したあと外します）。</p>
        </div>

        <!-- 編集 -->
        <div v-else class="rounded-md border border-brand-primary p-3 space-y-3 text-sm">
            <div class="flex flex-wrap gap-4">
                <label v-for="(label, key) in modeLabel" :key="key" class="inline-flex items-center gap-1.5">
                    <input v-model="form.mode" type="radio" :value="key" class="text-brand-primary focus:ring-brand-primary" />
                    {{ label }}
                </label>
            </div>

            <div v-if="form.mode === 'venue'" class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">お支度場所 <span class="text-red-500">*</span></label>
                    <select v-model="form.venue_id" :class="inputCls" @change="onVenueChange">
                        <option value="">選択してください</option>
                        <option v-for="v in venues" :key="v.id" :value="v.id">{{ v.name }}（{{ v.kind_label }}）{{ v.is_active ? '' : '［無効］' }}</option>
                    </select>
                    <p v-if="errors.venue_id" class="mt-1 text-xs text-brand-danger">{{ errors.venue_id }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">日</label>
                    <select v-model="form.prep_date" :class="inputCls" :disabled="!form.venue_id" @change="onDateChange">
                        <option value="">未定（場所だけ）</option>
                        <option v-for="d in dates" :key="d" :value="d">{{ formatDateJaWithWeekday(d) }}</option>
                    </select>
                    <p v-if="form.venue_id && !dates.length" class="mt-1 text-xs text-brand-text-muted">この場所にはまだ枠がありません（場所だけで登録できます）。</p>
                    <p v-if="errors.prep_date" class="mt-1 text-xs text-brand-danger">{{ errors.prep_date }}</p>
                </div>
                <!-- 福井：時刻の代わりに、その日の枠の埋まり具合 -->
                <div v-if="numbered" data-test="numbered-day">
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">番号</label>
                    <p v-if="!form.prep_date" class="py-2 text-xs text-brand-text-muted">日を選ぶと、その日の次の番号に入ります。</p>
                    <p v-else-if="dayInfo" class="py-2 tabular-nums" :class="!dayInfo.mine && dayInfo.booked >= dayInfo.capacity ? 'text-brand-danger' : ''">
                        {{ dayInfo.mine ? `今の番号 ${prep?.seat_no ?? ''}` : dayInfo.booked >= dayInfo.capacity ? '満員' : `番号 ${dayInfo.booked + 1} に入ります` }}
                        <span class="text-xs text-brand-text-muted">（{{ dayInfo.booked }}/{{ dayInfo.capacity }}枠）</span>
                    </p>
                    <p v-if="errors.prep_time" class="mt-1 text-xs text-brand-danger">{{ errors.prep_time }}</p>
                </div>
                <div v-else>
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">時刻</label>
                    <select v-model="form.prep_time" :class="inputCls" :disabled="!form.prep_date">
                        <option value="">未定</option>
                        <option v-for="t in times" :key="t.time" :value="t.time" :disabled="timeFull(t)">
                            {{ t.time }}（{{ t.booked }}/{{ t.capacity }}{{ t.mine ? '・今の枠' : timeFull(t) ? '・満員' : '' }}）
                        </option>
                    </select>
                    <p v-if="errors.prep_time" class="mt-1 text-xs text-brand-danger">{{ errors.prep_time }}</p>
                </div>
            </div>

            <fieldset v-if="numbered" data-test="makeup">
                <legend class="block text-xs font-medium text-brand-text-muted mb-1">メイク有無 <span class="text-red-500">*</span></legend>
                <div class="flex gap-4">
                    <label class="inline-flex items-center gap-1.5">
                        <input v-model="form.has_makeup" type="radio" name="has_makeup" :value="true" class="text-brand-primary focus:ring-brand-primary" />
                        あり
                    </label>
                    <label class="inline-flex items-center gap-1.5">
                        <input v-model="form.has_makeup" type="radio" name="has_makeup" :value="false" class="text-brand-primary focus:ring-brand-primary" />
                        なし
                    </label>
                </div>
                <p v-if="errors.has_makeup" class="mt-1 text-xs text-brand-danger">{{ errors.has_makeup }}</p>
            </fieldset>

            <div class="grid gap-3 sm:grid-cols-2">
                <div v-if="!isFukui">
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">希望時刻</label>
                    <input v-model="form.preferred_time" type="text" maxlength="50" placeholder="例：7時台・8:00より前" :class="inputCls" />
                    <p v-if="errors.preferred_time" class="mt-1 text-xs text-brand-danger">{{ errors.preferred_time }}</p>
                </div>
                <div v-else-if="form.preferred_time">
                    <span class="block text-xs font-medium text-brand-text-muted mb-1">希望時刻（前の登録の値・福井では入力しません）</span>
                    <p class="py-2">{{ form.preferred_time }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">担当店舗</label>
                    <select v-model="form.shop_id" :class="inputCls">
                        <option value="">選択してください</option>
                        <option v-for="s in shopOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <p v-if="errors.shop_id" class="mt-1 text-xs text-brand-danger">{{ errors.shop_id }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-brand-text-muted mb-1">備考</label>
                    <textarea v-model="form.remarks" rows="3" :class="inputCls"></textarea>
                    <p v-if="guide" class="mt-1 text-xs text-amber-700">{{ guide }}</p>
                    <p v-if="errors.remarks" class="mt-1 text-xs text-brand-danger">{{ errors.remarks }}</p>
                </div>
            </div>

            <p v-if="errors.error || errors.mode || errors.customer_id" class="rounded-soft bg-red-50 border border-red-200 px-3 py-2 text-red-700">
                {{ errors.error || errors.mode || errors.customer_id }}
            </p>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <UiButton v-if="prep" size="sm" variant="ghost" class="text-brand-danger" @click="confirmCancel = true">登録を取り消す</UiButton>
                <span v-else />
                <div class="flex gap-2">
                    <UiButton size="sm" variant="ghost" @click="editing = false">キャンセル</UiButton>
                    <UiButton size="sm" variant="primary" :loading="saving" @click="save">保存する</UiButton>
                </div>
            </div>
        </div>

        <UiDialog v-model:open="confirmCancel" title="成人式お支度の登録を取り消す" size="sm">
            <p class="text-sm text-brand-text-muted">このお客様のお支度の登録を取り消します。時刻の枠に入っていれば、その枠は空きに戻ります。</p>
            <template #footer>
                <UiButton variant="ghost" @click="confirmCancel = false">やめる</UiButton>
                <UiButton variant="danger" :loading="cancelling" @click="cancelPrep">取り消す</UiButton>
            </template>
        </UiDialog>
    </section>
</template>
