<template>
    <Head title="スタジオ編集" />

    <AdminLayout :breadcrumb="[{ label: '前撮り' }, { label: 'スタジオ', href: route('admin.photo-studios.index') }, { label: photoStudio.name }]">
        <UiPageHeader title="スタジオ編集" description="営業時間を変えても、もう作った枠は変わりません。これから作る枠に使われます。">
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.photo-studios.index')">一覧に戻る</UiButton>
            </template>
        </UiPageHeader>

        <form class="max-w-3xl space-y-5" @submit.prevent="submit">
            <StudioForm :form="form" :group-options="groupOptions" />
            <div class="flex justify-end">
                <UiButton type="submit" variant="primary" :loading="form.processing">更新する</UiButton>
            </div>
        </form>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { UiPageHeader, UiButton } from '@/Components/UI';
import StudioForm from '@/Components/PhotoSlot/StudioForm.vue';

const props = defineProps({
    photoStudio: { type: Object, required: true },
    groupOptions: { type: Object, default: () => ({}) },
});

const s = props.photoStudio;
const form = useForm({
    name: s.name || '',
    address: s.address || '',
    remarks: s.remarks || '',
    shop_group: s.shop_group || '',
    is_active: s.is_active,
    default_start_time: s.default_start_time || '',
    default_end_time: s.default_end_time || '',
    default_capacity: s.default_capacity || 1,
    weekday_hours: (s.weekday_hours || []).map((r) => ({ ...r })),
});

const submit = () => {
    form.transform((d) => ({
        ...d,
        default_start_time: d.default_start_time || null,
        default_end_time: d.default_end_time || null,
    })).put(route('admin.photo-studios.update', s.id));
};
</script>
