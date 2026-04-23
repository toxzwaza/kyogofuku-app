<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ActionButton from '@/Components/ActionButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    shop: Object,
    line_user_id: String,
    line_user_id_masked: String,
    messages: Array,
});

const form = useForm({
    line_user_id: props.line_user_id,
    unknown_shop_id: props.shop?.id ?? null,
    customer_id: '',
    label: 'お客様',
});

function submitLink() {
    form.post(route('admin.line-unknown-inbox.link'), { preserveScroll: true });
}
</script>

<template>
    <Head title="LINE 不明メッセージ詳細" />

    <AdminLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-brand-text leading-tight">LINE 不明メッセージ</h2>
                <ActionButton variant="back" label="一覧へ" :href="route('admin.line-unknown-inbox.index')" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-brand-surface shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-brand-text-muted">
                        店舗:
                        <span class="font-medium text-brand-text">{{ shop?.name ?? '（店舗未分類・共通チャネル）' }}</span>
                    </p>
                    <p class="text-sm text-brand-text-muted mt-1">LINEユーザー: <span class="font-mono">{{ line_user_id_masked }}</span></p>
                </div>

                <div class="bg-brand-surface shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-brand-text mb-3">受信メッセージ</h3>
                    <ul class="space-y-3 max-h-96 overflow-y-auto">
                        <li
                            v-for="m in messages"
                            :key="m.id"
                            class="text-sm border border-brand-border rounded p-3 bg-brand-surface-2"
                        >
                            <p class="whitespace-pre-wrap text-brand-text">{{ m.text || '（テキスト以外）' }}</p>
                            <p class="text-xs text-brand-text-subtle mt-1">
                                {{ m.created_at ? new Date(m.created_at).toLocaleString('ja-JP') : '' }}
                            </p>
                        </li>
                    </ul>
                    <p v-if="!messages.length" class="text-sm text-brand-text-muted">メッセージがありません。</p>
                </div>

                <div class="bg-brand-surface shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-brand-text mb-3">顧客へ紐づけ</h3>
                    <p class="text-xs text-brand-text-muted mb-4">
                        紐づけ先顧客の <strong>顧客ID</strong> を入力してください（顧客詳細画面の ID）。連携後はその顧客の担当店舗（<code class="bg-brand-surface-2 px-1 rounded text-xs">customers.shop_id</code>）が LINE 連絡先に保存されます。
                    </p>
                    <form class="space-y-4" @submit.prevent="submitLink">
                        <div>
                            <label class="block text-sm font-medium text-brand-text mb-1">顧客ID</label>
                            <input
                                v-model="form.customer_id"
                                type="number"
                                min="1"
                                required
                                class="w-full rounded-md border-brand-border shadow-sm text-sm"
                            />
                            <p v-if="form.errors.customer_id" class="mt-1 text-sm text-red-600">{{ form.errors.customer_id }}</p>
                            <p v-if="form.errors.line" class="mt-1 text-sm text-red-600">{{ form.errors.line }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-brand-text mb-1">表示ラベル（本人・母 など）</label>
                            <input
                                v-model="form.label"
                                type="text"
                                maxlength="50"
                                class="w-full rounded-md border-brand-border shadow-sm text-sm"
                            />
                        </div>
                        <div class="flex gap-3">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 bg-brand-primary text-white text-sm rounded-md hover:bg-brand-primary-hover disabled:opacity-50"
                            >
                                紐づけて取り込む
                            </button>
                            <Link
                                :href="route('admin.line-unknown-inbox.index')"
                                class="px-4 py-2 border border-brand-border text-sm rounded-md text-brand-text hover:bg-brand-surface-2"
                            >
                                キャンセル
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
