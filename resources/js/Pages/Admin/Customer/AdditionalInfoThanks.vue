<template>
    <Head title="追加情報のご記入ありがとうございました" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">追加情報のご記入ありがとうございました</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div v-if="$page.props.success" class="mb-4 p-3 rounded bg-green-100 text-green-800 border border-green-200">
                    {{ $page.props.success }}
                </div>
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-8 text-center">
                    <p class="text-lg text-gray-800 mb-8">
                        <span class="font-semibold">{{ customer.name }}</span> 様の追加情報を保存しました。
                    </p>
                    <Link
                        :href="route('admin.customers.show', customer.id)"
                        class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        顧客詳細へ戻る
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
    customer: { type: Object, required: true },
});
</script>
