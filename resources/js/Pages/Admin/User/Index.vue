<template>
    <Head title="スタッフ一覧" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">スタッフ一覧</h2>
                <ActionButton variant="create" label="新規追加" :href="route('admin.users.create')" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- 検索ブロック -->
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">店舗</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">名前</label>
                                <input
                                    v-model="searchForm.name"
                                    type="text"
                                    placeholder="名前で検索"
                                    @keyup.enter="search"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス</label>
                                <input
                                    v-model="searchForm.email"
                                    type="text"
                                    placeholder="メールで検索"
                                    @keyup.enter="search"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">名前</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">メールアドレス</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">所属店舗</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="user in users.data" :key="user.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.email }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span v-if="user.shops && user.shops.length > 0">
                                                <span v-for="(shop, index) in user.shops" :key="shop.id">
                                                    {{ shop.name }}<span v-if="index < user.shops.length - 1">, </span>
                                                </span>
                                            </span>
                                            <span v-else class="text-gray-400">未所属</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <ActionButton variant="edit" label="編集" size="sm" :href="route('admin.users.edit', user.id)" />
                                                <ActionButton variant="delete" label="削除" size="sm" @click="deleteUser(user.id)" />
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="users.data.length === 0">
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                            スタッフが見つかりませんでした
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ページネーション -->
                        <div v-if="users.links && users.links.length > 3" class="mt-4">
                            <div class="flex justify-center">
                                <template v-for="link in users.links" :key="link.label">
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
    users: Object,
    shops: Array,
    filters: Object,
});

const searchForm = reactive({
    shop_id: props.filters?.shop_id || '',
    name: props.filters?.name || '',
    email: props.filters?.email || '',
});

const search = () => {
    router.get(route('admin.users.index'), {
        shop_id: searchForm.shop_id || undefined,
        name: searchForm.name || undefined,
        email: searchForm.email || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    searchForm.shop_id = '';
    searchForm.name = '';
    searchForm.email = '';
    router.get(route('admin.users.index'), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const deleteUser = (id) => {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('admin.users.destroy', id));
    }
};
</script>
