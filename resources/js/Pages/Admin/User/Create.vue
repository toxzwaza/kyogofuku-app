<template>
    <Head title="スタッフ追加" />

    <AdminLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-brand-text leading-tight">スタッフ追加</h2>
                <Link
                    :href="route('admin.users.index')"
                    class="text-brand-primary hover:text-brand-primary-hover"
                >
                    ← スタッフ一覧に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-brand-text mb-1">名前 <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                                    />
                                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-brand-text mb-1">メールアドレス <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        required
                                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                                    />
                                    <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-brand-text mb-1">ログインID</label>
                                    <input
                                        v-model="form.login_id"
                                        type="text"
                                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                                        placeholder="メールアドレスが使えない場合のログインID（任意）"
                                    />
                                    <p class="mt-1 text-sm text-brand-text-muted">メールアドレスまたはログインIDでログインできます</p>
                                    <div v-if="form.errors.login_id" class="mt-1 text-sm text-red-600">{{ form.errors.login_id }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-brand-text mb-1">テーマカラー</label>
                                    <div class="flex items-center space-x-3">
                                        <input
                                            v-model="form.theme_color"
                                            type="color"
                                            class="h-10 w-20 rounded-md border-brand-border shadow-sm cursor-pointer"
                                        />
                                        <input
                                            v-model="form.theme_color"
                                            type="text"
                                            pattern="^#[0-9A-Fa-f]{6}$"
                                            placeholder="#000000"
                                            class="flex-1 rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                                        />
                                    </div>
                                    <p class="mt-1 text-sm text-brand-text-muted">スタッフごとのテーマカラーを設定できます（任意）</p>
                                    <div v-if="form.errors.theme_color" class="mt-1 text-sm text-red-600">{{ form.errors.theme_color }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-brand-text mb-1">勤務属性</label>
                                    <select
                                        v-model="form.work_attribute_id"
                                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                                    >
                                        <option value="">未設定</option>
                                        <option v-for="wa in workAttributes" :key="wa.id" :value="wa.id">{{ wa.name }}</option>
                                    </select>
                                    <p class="mt-1 text-sm text-brand-text-muted">給与用のベース勤務時間に使用します</p>
                                    <div v-if="form.errors.work_attribute_id" class="mt-1 text-sm text-red-600">{{ form.errors.work_attribute_id }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-brand-text mb-1">パスワード <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.password"
                                        type="password"
                                        required
                                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                                    />
                                    <div v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-brand-text mb-1">パスワード（確認） <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.password_confirmation"
                                        type="password"
                                        required
                                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-brand-text mb-2">所属店舗</label>
                                    <div v-if="shops && shops.length > 0" class="space-y-2">
                                        <label
                                            v-for="shop in shops"
                                            :key="shop.id"
                                            class="flex items-center"
                                        >
                                            <input
                                                v-model="form.shop_ids"
                                                type="checkbox"
                                                :value="shop.id"
                                                @change="onShopToggle(shop.id, $event.target.checked)"
                                                class="rounded border-brand-border text-brand-primary shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                                            />
                                            <span class="ml-2 text-sm text-brand-text">{{ shop.name }}</span>
                                        </label>
                                    </div>
                                    <p v-else class="text-sm text-brand-text-muted">店舗が登録されていません</p>
                                </div>

                                <div v-if="form.shop_ids && form.shop_ids.length > 0">
                                    <label class="block text-sm font-medium text-brand-text mb-2">デフォルト店舗</label>
                                    <select
                                        v-model="form.main_shop_id"
                                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                                    >
                                        <option value="">デフォルト店舗を選択してください</option>
                                        <option
                                            v-for="shopId in form.shop_ids"
                                            :key="shopId"
                                            :value="shopId"
                                        >
                                            {{ getShopName(shopId) }}
                                        </option>
                                    </select>
                                    <p class="mt-1 text-sm text-brand-text-muted">ダッシュボードのカレンダーで最初に表示される店舗を設定します</p>
                                </div>

                                <div class="flex justify-end space-x-4 pt-4">
                                    <Link
                                        :href="route('admin.users.index')"
                                        class="px-4 py-2 border border-brand-border rounded-md text-brand-text hover:bg-brand-surface-2"
                                    >
                                        キャンセル
                                    </Link>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="px-4 py-2 bg-brand-primary text-white rounded-md hover:bg-brand-primary-hover disabled:bg-gray-400"
                                    >
                                        {{ form.processing ? '保存中...' : '保存' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    shops: Array,
    workAttributes: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    email: '',
    login_id: '',
    theme_color: '',
    work_attribute_id: '',
    password: '',
    password_confirmation: '',
    shop_ids: [],
    main_shop_id: null,
});

const getShopName = (shopId) => {
    const shop = props.shops.find(s => s.id === shopId);
    return shop ? shop.name : '';
};

const onShopToggle = (shopId, checked) => {
    // チェックが外された場合、その店舗がデフォルト店舗ならクリア
    if (!checked && form.main_shop_id === shopId) {
        form.main_shop_id = null;
    }
};

const submit = () => {
    form.post(route('admin.users.store'));
};
</script>

