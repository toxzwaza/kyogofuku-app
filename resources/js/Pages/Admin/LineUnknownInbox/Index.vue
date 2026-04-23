<script setup>
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    groups: Array,
    shops: Array,
    filters: Object,
});

function initialShopFilter() {
    if (props.filters.unassigned) {
        return '__unassigned__';
    }
    if (props.filters.shop_id != null) {
        return String(props.filters.shop_id);
    }
    return '';
}

const shopFilter = ref(initialShopFilter());

watch(
    () => [props.filters.shop_id, props.filters.unassigned],
    () => {
        shopFilter.value = initialShopFilter();
    },
);

function applyFilter() {
    const params = {};
    if (shopFilter.value === '__unassigned__') {
        params.unassigned = 1;
    } else if (shopFilter.value !== '') {
        params.shop_id = Number(shopFilter.value);
    }
    router.get(route('admin.line-unknown-inbox.index'), params, { preserveState: true });
}

function showHref(g) {
    const q = { line_user_id: g.line_user_id };
    if (g.shop_id != null) {
        q.shop_id = g.shop_id;
    }
    return route('admin.line-unknown-inbox.show', q);
}
</script>

<template>
    <Head title="LINE 不明メッセージ" />

    <AdminLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-brand-text leading-tight">LINE 不明メッセージ</h2>
                <ActionButton variant="back" label="顧客一覧" :href="route('admin.customers.index')" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-brand-surface shadow-sm sm:rounded-lg p-6 mb-6">
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-sm font-medium text-brand-text mb-1">店舗</label>
                            <select
                                v-model="shopFilter"
                                class="rounded-md border-brand-border shadow-sm text-sm"
                                @change="applyFilter"
                            >
                                <option value="">すべて</option>
                                <option value="__unassigned__">店舗未分類</option>
                                <option v-for="s in shops" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                            </select>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-brand-text-muted">
                        友だち未登録の LINE ユーザーからの受信がここに溜まります。詳細から顧客へ紐づけできます。
                    </p>
                </div>

                <div class="bg-brand-surface shadow-sm sm:rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-brand-border">
                        <thead class="bg-brand-surface-2">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-brand-text-muted uppercase">店舗</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-brand-text-muted uppercase">LINEユーザー</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-brand-text-muted uppercase">件数</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-brand-text-muted uppercase">最新</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-brand-text-muted uppercase">プレビュー</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr v-for="(g, idx) in groups" :key="idx" class="hover:bg-brand-surface-2">
                                <td class="px-4 py-3 text-sm text-brand-text">{{ g.shop_name }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-brand-text">{{ g.line_user_id_masked }}</td>
                                <td class="px-4 py-3 text-sm text-brand-text-muted">{{ g.message_count }}</td>
                                <td class="px-4 py-3 text-sm text-brand-text-muted">
                                    {{ g.last_at ? new Date(g.last_at).toLocaleString('ja-JP') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-brand-text max-w-xs truncate">{{ g.last_text }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="showHref(g)" class="text-brand-primary hover:text-brand-primary-hover text-sm font-medium">
                                        詳細・紐づけ
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!groups.length">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-brand-text-muted">不明メッセージはありません。</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
