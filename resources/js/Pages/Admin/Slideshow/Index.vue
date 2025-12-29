<template>
    <Head title="スライドショー一覧" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">スライドショー一覧</h2>
                <Link
                    :href="route('admin.slideshows.create')"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700"
                >
                    新規追加
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="slideshows && slideshows.length > 0" class="space-y-4">
                            <div
                                v-for="slideshow in slideshows"
                                :key="slideshow.id"
                                class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
                            >
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ slideshow.name }}</h3>
                                    <p class="text-sm text-gray-600">画像数: {{ slideshow.images ? slideshow.images.length : 0 }}枚</p>
                                </div>
                                <div class="flex space-x-2">
                                    <Link
                                        :href="route('admin.slideshows.show', slideshow.id)"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                                    >
                                        詳細・編集
                                    </Link>
                                    <button
                                        @click="deleteSlideshow(slideshow.id)"
                                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                                    >
                                        削除
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            スライドショーが登録されていません
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    slideshows: Array,
});

const deleteSlideshow = (id) => {
    if (confirm('本当に削除しますか？関連する画像もすべて削除されます。')) {
        router.delete(route('admin.slideshows.destroy', id));
    }
};
</script>


