<template>
    <Head title="イベント追加" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">イベント追加</h2>
                <Link
                    :href="route('admin.events.index')"
                    class="text-indigo-600 hover:text-indigo-900"
                >
                    ← イベント一覧に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">タイトル <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.title"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <div v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                                    <textarea
                                        v-model="form.description"
                                        rows="4"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    ></textarea>
                                    <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">フォーム種別 <span class="text-red-500">*</span></label>
                                    <select
                                        v-model="form.form_type"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="reservation">予約</option>
                                        <option value="document">資料請求</option>
                                        <option value="contact">問い合わせ</option>
                                    </select>
                                    <div v-if="form.errors.form_type" class="mt-1 text-sm text-red-600">{{ form.errors.form_type }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">受付開始日</label>
                                    <input
                                        v-model="form.start_at"
                                        type="date"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p class="mt-1 text-sm text-gray-500">指定した日より予約受付が可能になります</p>
                                    <div v-if="form.errors.start_at" class="mt-1 text-sm text-red-600">{{ form.errors.start_at }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">受付終了日</label>
                                    <input
                                        v-model="form.end_at"
                                        type="date"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p class="mt-1 text-sm text-gray-500">指定した日を過ぎると予約ボタンが非表示になります</p>
                                    <div v-if="form.errors.end_at" class="mt-1 text-sm text-red-600">{{ form.errors.end_at }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">開催店舗</label>
                                    <div class="space-y-2">
                                        <label
                                            v-for="shop in shops"
                                            :key="shop.id"
                                            class="flex items-center"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="shop.id"
                                                v-model="form.shop_ids"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                            <span class="ml-2 text-sm text-gray-700">{{ shop.name }}</span>
                                        </label>
                                    </div>
                                    <div v-if="form.errors.shop_ids" class="mt-1 text-sm text-red-600">{{ form.errors.shop_ids }}</div>
                                </div>

                                <!-- 会場（予約フォームの場合のみ） -->
                                <div v-if="form.form_type === 'reservation'">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">会場</label>
                                    <div class="space-y-4">
                                        <!-- 既存会場の選択 -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-600 mb-2">既存の会場を選択</label>
                                            <div class="space-y-2">
                                                <label
                                                    v-for="venue in venues"
                                                    :key="venue.id"
                                                    class="flex items-center"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :value="venue.id"
                                                        v-model="form.venue_ids"
                                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    />
                                                    <span class="ml-2 text-sm text-gray-700">{{ venue.name }}</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- 新規会場の追加 -->
                                        <div class="border-t border-gray-200 pt-4">
                                            <label class="block text-sm font-medium text-gray-600 mb-2">新規会場を追加</label>
                                            <div class="space-y-3">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">会場名</label>
                                                    <input
                                                        v-model="form.new_venue_name"
                                                        type="text"
                                                        list="venue-list"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        placeholder="既存の会場を選択するか、新規会場名を入力"
                                                    />
                                                    <datalist id="venue-list">
                                                        <option v-for="venue in venues" :key="venue.id" :value="venue.name">
                                                            {{ venue.name }}
                                                        </option>
                                                    </datalist>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                                                    <textarea
                                                        v-model="form.new_venue_description"
                                                        rows="2"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    ></textarea>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">住所</label>
                                                    <input
                                                        v-model="form.new_venue_address"
                                                        type="text"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    />
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">電話番号</label>
                                                    <input
                                                        v-model="form.new_venue_phone"
                                                        type="tel"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="form.errors.venue_ids" class="mt-1 text-sm text-red-600">{{ form.errors.venue_ids }}</div>
                                </div>

                                <div>
                                    <label class="flex items-center">
                                        <input
                                            v-model="form.is_public"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">公開</span>
                                    </label>
                                </div>

                                <div class="flex justify-end space-x-4 pt-4">
                                    <Link
                                        :href="route('admin.events.index')"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                    >
                                        キャンセル
                                    </Link>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
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
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    shops: Array,
    venues: Array,
});

const form = useForm({
    title: '',
    description: '',
    form_type: 'reservation',
    start_at: '',
    end_at: '',
    shop_ids: [],
    venue_ids: [],
    new_venue_name: '',
    new_venue_description: '',
    new_venue_address: '',
    new_venue_phone: '',
    is_public: true,
});

const submit = () => {
    // datalistから選択された既存会場かチェック
    if (form.new_venue_name) {
        const existingVenue = props.venues.find(v => v.name === form.new_venue_name);
        if (existingVenue) {
            // 既存会場をvenue_idsに追加
            if (!form.venue_ids.includes(existingVenue.id)) {
                form.venue_ids.push(existingVenue.id);
            }
            // 新規会場情報をクリア
            form.new_venue_name = '';
            form.new_venue_description = '';
            form.new_venue_address = '';
            form.new_venue_phone = '';
        }
    }
    
    form.post(route('admin.events.store'));
};
</script>

