<template>
    <Head title="スタッフ一覧" />

    <AdminLayout :breadcrumb="[{ label: 'マスタ' }, { label: 'スタッフ' }]">
        <UiPageHeader
            title="スタッフ一覧"
            description="所属スタッフの検索・管理。所属店舗や勤務属性で絞り込めます。"
        >
            <template #actions>
                <UiButton variant="primary" :href="route('admin.users.create')">
                    <template #leading><Plus :size="14" /></template>
                    新規追加
                </UiButton>
            </template>
        </UiPageHeader>

        <!-- 絞り込み -->
        <UiCard variant="default" padding="md" class="mb-4">
            <form @submit.prevent="search" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <UiFormField label="店舗">
                    <UiSelect
                        v-model="searchForm.shop_id"
                        :options="[{ value: '', label: 'すべての店舗' }, ...shops.map(s => ({ value: s.id, label: s.name }))]"
                        size="sm"
                    />
                </UiFormField>
                <UiFormField label="名前">
                    <UiInput v-model="searchForm.name" placeholder="名前で検索" size="sm" />
                </UiFormField>
                <UiFormField label="メール">
                    <UiInput v-model="searchForm.email" placeholder="メールで検索" size="sm" />
                </UiFormField>
                <div class="flex items-end gap-2">
                    <UiButton variant="primary" size="sm" type="submit">
                        <template #leading><Search :size="13" /></template>
                        検索
                    </UiButton>
                    <UiButton variant="ghost" size="sm" type="button" @click="resetFilters">リセット</UiButton>
                </div>
            </form>
        </UiCard>

        <UiDataTable
            :columns="columns"
            :rows="users.data"
            :pagination="users"
            empty-message="スタッフが見つかりませんでした。"
        >
            <template #cell-id="{ value }">
                <span class="text-brand-text-muted tabular-nums">#{{ value }}</span>
            </template>
            <template #cell-name="{ row }">
                <div class="flex items-center gap-2">
                    <span
                        v-if="row.theme_color"
                        class="w-2 h-6 rounded-full flex-shrink-0"
                        :style="{ background: row.theme_color }"
                    />
                    <span class="font-medium truncate">{{ row.name }}</span>
                </div>
            </template>
            <template #cell-email="{ value }">
                <span class="text-brand-text-muted text-xs">{{ value }}</span>
            </template>
            <template #cell-work_attribute="{ row }">
                <span v-if="row.work_attribute?.name" class="text-brand-text-muted">{{ row.work_attribute.name }}</span>
                <span v-else class="text-brand-text-subtle">—</span>
            </template>
            <template #cell-shops="{ row }">
                <div v-if="row.shops?.length" class="flex flex-wrap gap-1">
                    <UiBadge v-for="s in row.shops" :key="s.id" variant="neutral" size="sm">{{ s.name }}</UiBadge>
                </div>
                <span v-else class="text-brand-text-subtle">未所属</span>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <UiButton size="sm" variant="ghost" :href="route('admin.users.edit', row.id)">
                        <Pencil :size="13" />
                    </UiButton>
                    <UiButton size="sm" variant="ghost" class="text-brand-danger" @click="askDelete(row)">
                        <Trash2 :size="13" />
                    </UiButton>
                </div>
            </template>
        </UiDataTable>

        <UiDialog v-model:open="confirmOpen" title="スタッフを削除">
            <p class="text-sm text-brand-text-muted">
                <span class="font-medium text-brand-text">{{ target?.name }}</span> を削除します。元に戻せません。
            </p>
            <template #footer>
                <UiButton variant="ghost" @click="confirmOpen = false">キャンセル</UiButton>
                <UiButton variant="danger" :loading="deleting" @click="confirmDelete">削除する</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    UiPageHeader, UiButton, UiBadge, UiDataTable, UiDialog,
    UiCard, UiFormField, UiInput, UiSelect,
} from '@/Components/UI';
import { Plus, Pencil, Trash2, Search } from 'lucide-vue-next';

const props = defineProps({
    users: Object,
    shops: Array,
    filters: Object,
});

const columns = [
    { key: 'id',             label: 'ID',         width: '70px' },
    { key: 'name',           label: '名前',       width: '200px' },
    { key: 'email',          label: 'メール',     hideOnMobile: true },
    { key: 'work_attribute', label: '勤務属性',   hideOnMobile: true, width: '120px' },
    { key: 'shops',          label: '所属店舗' },
    { key: 'actions',        label: '',           align: 'right', width: '100px', noLink: true },
];

const searchForm = reactive({
    shop_id: props.filters?.shop_id || '',
    name:    props.filters?.name || '',
    email:   props.filters?.email || '',
});

const search = () => {
    router.get(route('admin.users.index'), {
        shop_id: searchForm.shop_id || undefined,
        name:    searchForm.name || undefined,
        email:   searchForm.email || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
    searchForm.shop_id = '';
    searchForm.name = '';
    searchForm.email = '';
    router.get(route('admin.users.index'), {}, { preserveState: true, preserveScroll: true });
};

const confirmOpen = ref(false);
const target = ref(null);
const deleting = ref(false);

const askDelete = (user) => {
    target.value = user;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!target.value) return;
    deleting.value = true;
    router.delete(route('admin.users.destroy', target.value.id), {
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
            target.value = null;
        },
    });
};
</script>
