<template>
    <Head title="LINE 連携一覧" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">LINE 連携一覧</h2>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto px-4 sm:px-6 lg:px-8 max-w-7xl">
                <!-- フィルタ -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4">
                    <form @submit.prevent="search" class="flex flex-wrap gap-3 items-end">
                        <div class="flex flex-col">
                            <label class="text-xs font-medium text-gray-700 mb-1">種別</label>
                            <select v-model="form.type" class="rounded-md border-gray-300 text-sm">
                                <option value="all">全て（{{ counts.all }}）</option>
                                <option value="customer">顧客紐付け（{{ counts.customer }}）</option>
                                <option value="reservation">予約紐付け（{{ counts.reservation }}）</option>
                                <option value="unbound">未紐付け（{{ counts.unbound }}）</option>
                            </select>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-xs font-medium text-gray-700 mb-1">店舗</label>
                            <select v-model="form.shop_id" class="rounded-md border-gray-300 text-sm">
                                <option :value="''">全て</option>
                                <option v-for="s in shops" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col flex-1 min-w-[200px]">
                            <label class="text-xs font-medium text-gray-700 mb-1">キーワード（名前・カナ・電話・LINE userId・ラベル）</label>
                            <input v-model="form.q" type="text" class="rounded-md border-gray-300 text-sm" placeholder="例) 村上 / ムラカミ / 09012345678" />
                        </div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">検索</button>
                        <button type="button" @click="reset" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">リセット</button>
                    </form>
                </div>

                <!-- 件数表示 -->
                <div class="text-sm text-gray-600 mb-2">
                    <span v-if="contacts.meta.total > 0">
                        {{ contacts.meta.from }}〜{{ contacts.meta.to }} / {{ contacts.meta.total }} 件
                    </span>
                    <span v-else>該当なし</span>
                </div>

                <!-- テーブル -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200 text-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left whitespace-nowrap">種別</th>
                                <th class="px-3 py-2 text-left whitespace-nowrap">名前</th>
                                <th class="px-3 py-2 text-left whitespace-nowrap">カナ</th>
                                <th class="px-3 py-2 text-left whitespace-nowrap">電話番号</th>
                                <th class="px-3 py-2 text-left whitespace-nowrap">店舗</th>
                                <th class="px-3 py-2 text-left whitespace-nowrap">追加情報</th>
                                <th class="px-3 py-2 text-left whitespace-nowrap">表示名</th>
                                <th class="px-3 py-2 text-left whitespace-nowrap">連携日時</th>
                                <th class="px-3 py-2 text-right whitespace-nowrap">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="c in contacts.data" :key="c.id" class="hover:bg-indigo-50/50 cursor-pointer" @click="goTo(c)">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span v-if="c.kind === 'customer'" class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">顧客</span>
                                    <span v-else-if="c.kind === 'reservation'" class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">予約</span>
                                    <span v-else class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">未紐付</span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap font-medium text-gray-900">
                                    {{ c.target?.name || '—' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    {{ c.target?.kana || '—' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    {{ c.target?.phone || '—' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    {{ c.shop?.name || '—' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    <template v-if="c.kind === 'reservation'">
                                        <span class="text-xs">{{ c.target?.event_title || '' }}</span>
                                    </template>
                                    <template v-else-if="c.kind === 'unbound'">
                                        <span class="text-xs text-gray-400">{{ shortLineId(c.line_user_id) }}</span>
                                    </template>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ c.label || '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500 text-xs">{{ formatDate(c.created_at) }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-right">
                                    <button type="button"
                                            @click.stop="unlink(c)"
                                            class="text-red-600 hover:text-red-800 text-xs font-medium">
                                        解除
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="contacts.data.length === 0">
                                <td colspan="9" class="px-3 py-8 text-center text-gray-500">該当する LINE 連携が見つかりません。</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ページネーション -->
                <div v-if="contacts.meta.last_page > 1" class="mt-4 flex flex-wrap gap-1 justify-center">
                    <Link v-for="(link, idx) in contacts.links"
                          :key="idx"
                          :href="link.url || '#'"
                          v-html="link.label"
                          class="px-3 py-1 text-sm rounded border"
                          :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : (link.url ? 'bg-white text-gray-700 hover:bg-gray-50 border-gray-200' : 'text-gray-300 border-gray-100 cursor-not-allowed')"
                          preserve-scroll />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    contacts: { type: Object, required: true },
    filters: { type: Object, required: true },
    shops: { type: Array, required: true },
    counts: { type: Object, required: true },
});

const form = reactive({
    type: props.filters.type || 'all',
    shop_id: props.filters.shop_id ?? '',
    q: props.filters.q || '',
});

function search() {
    router.get(route('admin.line-contacts.index'), {
        type: form.type,
        shop_id: form.shop_id === '' ? null : form.shop_id,
        q: form.q,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function reset() {
    form.type = 'all';
    form.shop_id = '';
    form.q = '';
    search();
}

function goTo(c) {
    if (c.target?.detail_url) {
        router.visit(c.target.detail_url);
    }
}

function unlink(c) {
    const targetLabel = c.target?.name ? `${c.target.name} さん` : `LINE userId ${shortLineId(c.line_user_id)}`;
    if (!confirm(`${targetLabel} の LINE 連携を解除します。よろしいですか？`)) return;
    router.delete(route('admin.line-contacts.destroy', c.id), {
        preserveScroll: true,
    });
}

function shortLineId(id) {
    if (!id) return '';
    return id.substring(0, 6) + '…' + id.substring(id.length - 4);
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    return `${y}/${m}/${day} ${hh}:${mm}`;
}
</script>
