<template>
    <Head title="イベント予約者一覧" />

    <AdminLayout :breadcrumb="[{ label: 'イベント・予約' }, { label: 'イベント予約者一覧' }]">
        <UiPageHeader
            title="イベント予約者一覧"
            description="担当店舗・イベントで予約者を絞り込んで確認できます。"
        />

        <UiCard variant="default" padding="md" class="mb-3">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <UiFormField label="担当店舗">
                    <UiSelect
                        v-model="resForm.shop_id"
                        :options="[{ value: 'all', label: 'すべての店舗' }, ...shops.map(s => ({ value: s.id, label: s.name }))]"
                        size="sm"
                        @change="onResScopeChange"
                    />
                </UiFormField>
                <UiFormField label="フォーム種別">
                    <UiSelect
                        v-model="resForm.form_type"
                        :options="[
                            { value: '',                   label: 'すべて' },
                            { value: 'reservation',        label: '振袖予約' },
                            { value: 'reservation_hakama', label: '袴予約（岡山）' },
                            { value: 'document',           label: '資料請求' },
                            { value: 'contact',            label: '問い合わせ' },
                        ]"
                        size="sm"
                        @change="onResScopeChange"
                    />
                </UiFormField>
                <UiFormField label="公開状態">
                    <UiSelect
                        v-model="resForm.public_status"
                        :options="[
                            { value: 'active',  label: '公開中' },
                            { value: 'ended',   label: '受付終了' },
                            { value: 'private', label: '非公開' },
                            { value: 'all',     label: 'すべて' },
                        ]"
                        size="sm"
                        @change="onResScopeChange"
                    />
                </UiFormField>
                <div class="flex items-end">
                    <UiButton variant="ghost" size="sm" type="button" @click="resetReservationFilters">
                        <template #leading><RotateCcw :size="13" /></template>
                        リセット
                    </UiButton>
                </div>
            </div>

            <!-- イベント複数選択 -->
            <div class="mt-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium text-brand-text">イベント（複数選択可・未選択で対象すべて）</span>
                    <div class="flex gap-2">
                        <button type="button" class="text-xs text-brand-primary hover:underline" @click="selectAllResEvents">全選択</button>
                        <button type="button" class="text-xs text-brand-text-muted hover:underline" @click="clearResEvents">全解除</button>
                    </div>
                </div>
                <div v-if="eventOptions.length" class="rounded-md border border-brand-border bg-brand-surface max-h-44 overflow-y-auto p-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                    <label
                        v-for="ev in eventOptions"
                        :key="ev.id"
                        class="flex items-center gap-2 text-sm text-brand-text cursor-pointer min-w-0"
                    >
                        <input
                            type="checkbox"
                            :checked="resForm.event_ids.includes(ev.id)"
                            class="rounded border-brand-border text-brand-primary focus:ring-brand-primary shrink-0"
                            @change="toggleResEvent(ev.id)"
                        />
                        <span class="truncate">{{ ev.title }}</span>
                    </label>
                </div>
                <p v-else class="text-xs text-brand-text-muted py-2">該当するイベントがありません。</p>
            </div>
        </UiCard>

        <UiCard variant="default" padding="none">
            <div class="px-4 py-3 border-b border-brand-border flex flex-wrap items-baseline justify-between gap-2 bg-brand-surface-2/60">
                <h3 class="text-sm font-semibold text-brand-text">予約者</h3>
                <p class="text-sm text-brand-text-muted">
                    全 <span class="font-medium text-brand-text tabular-nums">{{ resMeta.total }}</span> 件
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-brand-border">
                    <thead class="bg-brand-surface-2">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">ID</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">受付日時</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">イベント</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">種別</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">担当店舗</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">氏名</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">電話番号</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">予約日時</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">会場</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">ステータス</th>
                        </tr>
                    </thead>
                    <tbody class="bg-brand-surface divide-y divide-brand-border">
                        <tr v-if="resLoading">
                            <td colspan="10" class="px-4 py-10 text-center text-sm text-brand-text-muted">読み込み中…</td>
                        </tr>
                        <tr v-else-if="!resRows.length">
                            <td colspan="10" class="px-4 py-10 text-center text-sm text-brand-text-muted">該当する予約者がいません。</td>
                        </tr>
                        <template v-else>
                            <tr
                                v-for="r in resRows"
                                :key="r.id"
                                class="cursor-pointer hover:bg-brand-surface-2 transition-colors"
                                :class="{ 'opacity-60': r.cancel_flg }"
                                @click="goToReservation(r.id)"
                            >
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-brand-text-muted tabular-nums">#{{ r.id }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-brand-text-muted">{{ formatDateTime(r.created_at) }}</td>
                                <td class="px-4 py-3 text-sm text-brand-text max-w-[16rem] truncate">{{ r.event_title || '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <UiBadge :variant="formTypeVariant(r.form_type)" size="sm">{{ getFormTypeLabel(r.form_type) }}</UiBadge>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        <UiBadge v-for="(s, i) in r.shop_names" :key="i" variant="neutral" size="sm">{{ s }}</UiBadge>
                                        <span v-if="!r.shop_names || !r.shop_names.length" class="text-xs text-brand-text-muted">—</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-brand-text">
                                    {{ r.name || '—' }}
                                    <span v-if="r.furigana" class="block text-xs text-brand-text-muted">{{ r.furigana }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-brand-text tabular-nums">{{ r.phone || '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-brand-text">{{ r.reservation_datetime || '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-brand-text">{{ r.venue_name || '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <span v-if="r.cancel_flg" class="text-red-600 font-medium">キャンセル</span>
                                    <span v-else class="text-brand-text">{{ r.status || '—' }}</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <!-- ページャ -->
            <div v-if="resMeta.last_page > 1" class="flex items-center justify-center gap-3 px-4 py-3 border-t border-brand-border">
                <UiButton size="sm" variant="ghost" :disabled="resMeta.current_page <= 1" @click="changeResPage(resMeta.current_page - 1)">前へ</UiButton>
                <span class="text-xs text-brand-text-muted tabular-nums">{{ resMeta.current_page }} / {{ resMeta.last_page }}</span>
                <UiButton size="sm" variant="ghost" :disabled="resMeta.current_page >= resMeta.last_page" @click="changeResPage(resMeta.current_page + 1)">次へ</UiButton>
            </div>
        </UiCard>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { UiPageHeader, UiButton, UiBadge, UiCard, UiFormField, UiSelect } from '@/Components/UI';
import { RotateCcw } from 'lucide-vue-next';

const props = defineProps({
    shops: { type: Array, default: () => [] },
});

const resForm = reactive({
    shop_id: 'all',          // 初回はサーバー側でログインユーザーの担当店舗をデフォルト適用
    form_type: '',
    public_status: 'active',
    event_ids: [],
});
const eventOptions = ref([]);
const resRows = ref([]);
const resMeta = ref({ total: 0, current_page: 1, last_page: 1 });
const resLoading = ref(false);
const resInitialized = ref(false);

const fetchReservations = async (page = 1) => {
    resLoading.value = true;
    try {
        const params = {
            form_type: resForm.form_type || undefined,
            public_status: resForm.public_status || undefined,
            event_ids: resForm.event_ids.length ? resForm.event_ids : undefined,
            page,
        };
        // 初回はshop_idを送らずサーバーのデフォルト店舗に委ねる
        if (resInitialized.value) {
            params.shop_id = resForm.shop_id;
        }
        const { data } = await axios.get(route('admin.event-reservations.search'), { params });
        resRows.value = data.reservations.data;
        resMeta.value = {
            total: data.reservations.total,
            current_page: data.reservations.current_page,
            last_page: data.reservations.last_page,
        };
        eventOptions.value = data.event_options;
        resForm.shop_id = data.applied.shop_id;
        resForm.form_type = data.applied.form_type;
        resForm.public_status = data.applied.public_status;
        resInitialized.value = true;
    } catch (e) {
        console.error('予約者一覧の取得に失敗しました', e);
    } finally {
        resLoading.value = false;
    }
};

// 担当店舗・フォーム種別・公開状態の変更時はイベント選択をリセットして再取得
const onResScopeChange = () => {
    resForm.event_ids = [];
    fetchReservations(1);
};
const toggleResEvent = (id) => {
    const i = resForm.event_ids.indexOf(id);
    if (i === -1) resForm.event_ids.push(id);
    else resForm.event_ids.splice(i, 1);
    fetchReservations(1);
};
const selectAllResEvents = () => {
    resForm.event_ids = eventOptions.value.map((e) => e.id);
    fetchReservations(1);
};
const clearResEvents = () => {
    resForm.event_ids = [];
    fetchReservations(1);
};
const resetReservationFilters = () => {
    resForm.form_type = '';
    resForm.public_status = 'active';
    resForm.event_ids = [];
    resInitialized.value = false; // shop_id を送らずデフォルト店舗に戻す
    fetchReservations(1);
};
const changeResPage = (page) => {
    if (page < 1 || page > resMeta.value.last_page) return;
    fetchReservations(page);
};
const goToReservation = (id) => {
    router.visit(route('admin.reservations.show', id));
};

const getFormTypeLabel = (t) => ({
    reservation: '振袖予約',
    reservation_hakama: '袴予約（岡山）',
    document: '資料請求',
    contact: '問い合わせ',
}[t] || t || '—');

const formTypeVariant = (t) => ({
    reservation: 'primary',
    reservation_hakama: 'accent',
    document: 'success',
    contact: 'warning',
}[t] || 'neutral');

const formatDateTime = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '—';
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}/${p(d.getMonth() + 1)}/${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
};

onMounted(() => {
    fetchReservations(1);
});
</script>
