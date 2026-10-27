<template>
    <Head title="GTMタグ管理" />
    <AdminLayout :breadcrumb="[{ label: 'イベント・予約' }, { label: 'GTMタグ' }]">
        <UiPageHeader
            title="GTMタグ管理"
            description="イベントLPに埋め込むGoogleタグマネージャーのIDを管理します。イベント編集では開催店舗に紐付くタグから選択します。"
        >
            <template #actions>
                <UiButton variant="primary" @click="openCreate">
                    <template #leading><Plus :size="14" /></template>
                    GTMタグ追加
                </UiButton>
            </template>
        </UiPageHeader>

        <UiCard variant="default" padding="md">
            <div v-if="gtmTags.length" class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-brand-surface-2">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium whitespace-nowrap">管理会社</th>
                            <th class="px-4 py-2 text-left font-medium whitespace-nowrap">GTM ID</th>
                            <th class="px-4 py-2 text-left font-medium whitespace-nowrap">紐付け店舗</th>
                            <th class="px-4 py-2 text-left font-medium">メモ</th>
                            <th class="px-4 py-2 text-left font-medium whitespace-nowrap">使用イベント</th>
                            <th class="px-4 py-2 text-left font-medium whitespace-nowrap">状態</th>
                            <th class="px-4 py-2 text-left font-medium w-24">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-for="t in gtmTags" :key="t.id" class="hover:bg-brand-surface-2">
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span v-if="t.company_name">{{ t.company_name }}</span>
                                <span v-else class="text-brand-text-subtle">未設定</span>
                            </td>
                            <td class="px-4 py-2 font-mono whitespace-nowrap">{{ t.gtm_id }}</td>
                            <td class="px-4 py-2">
                                <div v-if="t.shop_names.length" class="flex flex-wrap gap-1">
                                    <UiBadge v-for="name in t.shop_names" :key="name" variant="neutral" size="sm">{{ name }}</UiBadge>
                                </div>
                                <span v-else class="text-brand-text-subtle text-xs">全店舗で選択可</span>
                            </td>
                            <td class="px-4 py-2 text-xs text-brand-text-muted whitespace-pre-line">{{ t.memo }}</td>
                            <td class="px-4 py-2 tabular-nums">{{ t.events_count }}件</td>
                            <td class="px-4 py-2">
                                <UiBadge :variant="t.is_active ? 'success' : 'neutral'" dot>{{ t.is_active ? '有効' : '無効' }}</UiBadge>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex gap-2 whitespace-nowrap">
                                    <button type="button" class="text-brand-primary hover:underline text-sm" @click="openEdit(t)">編集</button>
                                    <button
                                        v-if="!t.events_count"
                                        type="button"
                                        class="text-brand-danger hover:underline text-sm"
                                        @click="remove(t)"
                                    >削除</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="py-10 text-center text-brand-text-muted text-sm">
                GTMタグがありません。「GTMタグ追加」から登録してください。
            </div>
        </UiCard>

        <UiDialog v-model:open="modalOpen" size="lg">
            <template #header>{{ editing ? 'GTMタグを編集' : 'GTMタグを追加' }}</template>
            <div class="space-y-3">
                <UiFormField label="管理会社" :error="form.errors.company_name" hint="GTMコンテナを管理している会社（広告代理店など）">
                    <UiInput v-model="form.company_name" size="sm" placeholder="例：BE FANG" />
                </UiFormField>
                <UiFormField label="GTM ID" required :error="form.errors.gtm_id">
                    <UiInput v-model="form.gtm_id" size="sm" placeholder="例: GTM-5NP4QCSB" class="font-mono" />
                </UiFormField>
                <UiFormField
                    label="紐付け店舗"
                    :error="form.errors.shop_ids"
                    hint="イベントの開催店舗に含まれるときだけ選択肢に出ます。未選択なら全店舗で選択できます。"
                >
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="shop in shops"
                            :key="shop.id"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-brand-border bg-brand-surface-2 text-sm cursor-pointer"
                        >
                            <input
                                v-model="form.shop_ids"
                                type="checkbox"
                                :value="shop.id"
                                class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                            />
                            {{ shop.name }}
                        </label>
                    </div>
                </UiFormField>
                <UiFormField label="メモ" :error="form.errors.memo">
                    <UiTextarea v-model="form.memo" rows="3" placeholder="用途・計測内容・担当者など" />
                </UiFormField>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-brand-border text-brand-primary focus:ring-brand-primary" />
                    有効（無効にするとイベント編集の選択肢に出ません。設定済みのイベントはそのまま）
                </label>
                <p v-if="editing && editing.events_count" class="text-xs text-brand-text-muted">
                    このタグは{{ editing.events_count }}件のイベントで使用中です。GTM IDを変更すると、それらのイベントのLPにも反映されます。
                </p>
            </div>
            <template #footer>
                <UiButton variant="ghost" @click="modalOpen = false">キャンセル</UiButton>
                <UiButton variant="primary" :loading="form.processing" @click="submit">{{ editing ? '更新' : '追加' }}</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { UiPageHeader, UiCard, UiButton, UiBadge, UiDialog, UiFormField, UiInput, UiTextarea } from '@/Components/UI';
import { Plus } from 'lucide-vue-next';

defineProps({
    gtmTags: Array,
    shops: Array,
});

const modalOpen = ref(false);
const editing = ref(null);

const form = useForm({
    company_name: '',
    gtm_id: '',
    memo: '',
    is_active: true,
    shop_ids: [],
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
};

const openEdit = (t) => {
    editing.value = t;
    form.clearErrors();
    form.company_name = t.company_name || '';
    form.gtm_id = t.gtm_id;
    form.memo = t.memo || '';
    form.is_active = t.is_active;
    form.shop_ids = [...t.shop_ids];
    modalOpen.value = true;
};

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { modalOpen.value = false; } };
    if (editing.value) {
        form.put(route('admin.gtm-tags.update', editing.value.id), opts);
    } else {
        form.post(route('admin.gtm-tags.store'), opts);
    }
};

const remove = (t) => {
    if (!confirm(`GTMタグ「${t.gtm_id}」を削除しますか？`)) return;
    router.delete(route('admin.gtm-tags.destroy', t.id), { preserveScroll: true });
};
</script>
