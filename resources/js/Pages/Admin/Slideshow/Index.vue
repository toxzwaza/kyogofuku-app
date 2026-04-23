<template>
    <Head title="スライドショー一覧" />

    <AdminLayout :breadcrumb="[{ label: 'イベント・予約' }, { label: 'スライドショー' }]">
        <UiPageHeader
            title="スライドショー一覧"
            description="公開ページで利用するスライドショーを管理します。"
        >
            <template #actions>
                <UiButton variant="primary" :href="route('admin.slideshows.create')">
                    <template #leading><Plus :size="14" /></template>
                    新規追加
                </UiButton>
            </template>
        </UiPageHeader>

        <div v-if="slideshows && slideshows.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <UiCard
                v-for="s in slideshows"
                :key="s.id"
                variant="default"
                padding="md"
                class="hover:shadow-soft transition-shadow"
            >
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-soft bg-ai-50 dark:bg-ai-900 flex items-center justify-center flex-shrink-0">
                        <Film :size="18" class="text-brand-primary" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-medium text-brand-text truncate">{{ s.name }}</h3>
                        <p class="text-xs text-brand-text-muted mt-0.5">
                            画像 {{ s.images ? s.images.length : 0 }}枚
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-end gap-2 pt-3 border-t border-brand-border">
                    <UiButton size="sm" variant="ghost" class="text-brand-danger" @click="askDelete(s)">
                        <Trash2 :size="13" />
                    </UiButton>
                    <UiButton size="sm" variant="primary" :href="route('admin.slideshows.show', s.id)">
                        詳細・編集
                    </UiButton>
                </div>
            </UiCard>
        </div>
        <UiCard v-else variant="default" padding="lg">
            <div class="py-10 text-center text-brand-text-muted">
                <Film :size="32" class="mx-auto mb-2 text-brand-text-subtle" />
                <div class="text-sm">スライドショーが登録されていません</div>
            </div>
        </UiCard>

        <UiDialog v-model:open="confirmOpen" title="スライドショーを削除">
            <p class="text-sm text-brand-text-muted">
                <span class="font-medium text-brand-text">{{ target?.name }}</span> を削除します。
                関連する画像もすべて削除されます。
            </p>
            <template #footer>
                <UiButton variant="ghost" @click="confirmOpen = false">キャンセル</UiButton>
                <UiButton variant="danger" :loading="deleting" @click="confirmDelete">削除する</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { UiPageHeader, UiButton, UiCard, UiDialog } from '@/Components/UI';
import { Plus, Trash2, Film } from 'lucide-vue-next';

defineProps({
    slideshows: Array,
});

const confirmOpen = ref(false);
const target = ref(null);
const deleting = ref(false);

const askDelete = (s) => {
    target.value = s;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!target.value) return;
    deleting.value = true;
    router.delete(route('admin.slideshows.destroy', target.value.id), {
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
            target.value = null;
        },
    });
};
</script>
