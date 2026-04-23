<template>
    <Head title="スライドショー一覧" />

    <AdminLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-brand-text leading-tight">スライドショー一覧</h2>
                <Link
                    :href="route('admin.slideshows.create')"
                    class="bg-brand-primary text-white px-4 py-2 rounded-md hover:bg-brand-primary-hover"
                >
                    新規追加
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="slideshows && slideshows.length > 0" class="space-y-4">
                            <div
                                v-for="slideshow in slideshows"
                                :key="slideshow.id"
                                class="flex items-center justify-between p-4 border border-brand-border rounded-lg hover:bg-brand-surface-2"
                            >
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-brand-text">{{ slideshow.name }}</h3>
                                    <p class="text-sm text-brand-text-muted">画像数: {{ slideshow.images ? slideshow.images.length : 0 }}枚</p>
                                </div>
                                <div class="flex space-x-2">
                                    <Link
                                        :href="route('admin.slideshows.show', slideshow.id)"
                                        class="px-4 py-2 bg-brand-primary text-white rounded-md hover:bg-brand-primary-hover"
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
                        <div v-else class="text-center py-8 text-brand-text-muted">
                            スライドショーが登録されていません
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
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


