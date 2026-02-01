<template>
    <Head :title="`制約編集 - ${constraintTemplate.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">制約編集</h2>
                <ActionButton variant="back" label="制約一覧に戻る" :href="route('admin.constraint-templates.index')" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        制約名 <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="例：京呉服好一 ご利用規約（レンタル・お持込）"
                                    />
                                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.name }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        対象店舗
                                    </label>
                                    <p class="text-xs text-gray-500 mb-2">規約説明者の選択元となる店舗を選択（複数可）</p>
                                    <div class="flex flex-wrap gap-3">
                                        <label
                                            v-for="shop in shops"
                                            :key="shop.id"
                                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm cursor-pointer hover:bg-gray-100"
                                        >
                                            <input
                                                v-model="form.shop_ids"
                                                type="checkbox"
                                                :value="shop.id"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            {{ shop.name }}
                                        </label>
                                    </div>
                                    <div v-if="form.errors.shop_ids" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.shop_ids }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        制約本文（マークダウン） <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-500 mb-2">
                                        マークダウン形式で記入。チェック項目は <code class="bg-gray-100 px-1 rounded">- [ ] ラベル</code> で記述してください。
                                    </p>
                                    <textarea
                                        v-model="form.body"
                                        rows="20"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                                        placeholder="# タイトル&#10;&#10;## セクション&#10;- 項目1&#10;- [ ] チェック項目1"
                                    ></textarea>
                                    <div v-if="form.errors.body" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.body }}
                                    </div>
                                </div>

                                <div>
                                    <label class="flex items-center">
                                        <input
                                            v-model="form.is_active"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">有効</span>
                                    </label>
                                    <p class="mt-1 text-xs text-gray-500">無効にすると、顧客への制約追加で選択できなくなります。</p>
                                    <div v-if="form.errors.is_active" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.is_active }}
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <ActionButton
                                        variant="save"
                                        type="submit"
                                        :disabled="form.processing"
                                    >
                                        {{ form.processing ? '更新中...' : '更新' }}
                                    </ActionButton>
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
import ActionButton from '@/Components/ActionButton.vue';
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    constraintTemplate: Object,
    shops: Array,
});

const form = useForm({
    name: props.constraintTemplate.name,
    body: props.constraintTemplate.body,
    is_active: props.constraintTemplate.is_active ?? true,
    shop_ids: props.constraintTemplate.shops?.map(s => s.id) ?? [],
});

const submit = () => {
    form.put(route('admin.constraint-templates.update', props.constraintTemplate.id));
};
</script>
