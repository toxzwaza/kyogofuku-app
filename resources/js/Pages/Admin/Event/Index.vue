<template>
    <Head title="イベント一覧" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">イベント一覧</h2>
                <ActionButton variant="create" label="新規追加" :href="route('admin.events.create')" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- 絞り込みブロック -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">絞り込み</h3>
                            <button @click="resetFilters" class="text-sm text-gray-600 hover:text-gray-800">
                                リセット
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">フォーム種別</label>
                                <select
                                    v-model="searchForm.form_type"
                                    @change="search"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">すべて</option>
                                    <option value="reservation">予約</option>
                                    <option value="document">資料請求</option>
                                    <option value="contact">問い合わせ</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">担当店舗</label>
                                <select
                                    v-model="searchForm.shop_id"
                                    @change="search"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">すべての店舗</option>
                                    <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                                        {{ shop.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">公開状態</label>
                                <select
                                    v-model="searchForm.is_public"
                                    @change="search"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option :value="true">公開中</option>
                                    <option :value="false">非公開</option>
                                    <option value="">すべて</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button
                                @click="search"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm"
                            >
                                検索
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">タイトル</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">フォーム種別</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">受付開始日</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">受付終了日</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">店舗</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">公開状態</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="event in events.data" :key="event.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ event.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ event.title }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="px-2 py-1 text-xs rounded-full" :class="{
                                                'bg-blue-100 text-blue-800': event.form_type === 'reservation',
                                                'bg-green-100 text-green-800': event.form_type === 'document',
                                                'bg-purple-100 text-purple-800': event.form_type === 'contact',
                                            }">
                                                {{ getFormTypeLabel(event.form_type) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatDate(event.start_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatDate(event.end_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span v-for="(shop, index) in event.shops" :key="shop.id">
                                                {{ shop.name }}<span v-if="index < event.shops.length - 1">, </span>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 text-xs rounded-full" :class="event.is_public ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                                                {{ event.is_public ? '公開' : '非公開' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <ActionButton variant="detail" label="詳細" size="sm" :href="route('admin.events.show', event.id)" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ページネーション -->
                        <div v-if="events.links && events.links.length > 3" class="mt-4">
                            <div class="flex justify-center">
                                <template v-for="link in events.links" :key="link.label">
                                    <Link
                                        v-if="link.url"
                                        :href="link.url"
                                        :class="[
                                            'px-4 py-2 mx-1 rounded-md',
                                            link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                        ]"
                                    >
                                        <span v-html="link.label"></span>
                                    </Link>
                                    <span
                                        v-else
                                        :class="[
                                            'px-4 py-2 mx-1 rounded-md opacity-50 cursor-not-allowed',
                                            link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700',
                                        ]"
                                        v-html="link.label"
                                    ></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    events: Object,
    shops: Array,
    filters: Object,
});

const searchForm = reactive({
    form_type: props.filters?.form_type || '',
    shop_id: props.filters?.shop_id || '',
    is_public: props.filters?.is_public !== undefined ? props.filters.is_public : true,
});

const search = () => {
    router.get(route('admin.events.index'), {
        form_type: searchForm.form_type || undefined,
        shop_id: searchForm.shop_id || undefined,
        is_public: searchForm.is_public !== '' ? searchForm.is_public : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    searchForm.form_type = '';
    searchForm.shop_id = '';
    searchForm.is_public = true;
    router.get(route('admin.events.index'), {
        is_public: true,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const getFormTypeLabel = (formType) => {
    const labels = {
        reservation: '予約',
        document: '資料請求',
        contact: '問い合わせ',
    };
    return labels[formType] || formType;
};

const formatDate = (date) => {
    if (!date) {
        return '常時受付中';
    }
    const d = new Date(date);
    return d.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>

