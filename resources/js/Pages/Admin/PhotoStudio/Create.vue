<template>
    <Head title="スタジオ追加" />

    <AdminLayout :breadcrumb="[{ label: '前撮り' }, { label: 'スタジオ', href: route('admin.photo-studios.index') }, { label: '追加' }]">
        <UiPageHeader title="スタジオ追加" description="前撮りのスタジオと、枠を作るときに使う営業時間・定員を登録します。">
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.photo-studios.index')">一覧に戻る</UiButton>
            </template>
        </UiPageHeader>

        <form class="max-w-3xl space-y-5" @submit.prevent="submit">
            <StudioForm :form="form" :group-options="groupOptions" />
            <div class="flex justify-end">
                <UiButton type="submit" variant="primary" :loading="form.processing">登録する</UiButton>
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
    groupOptions: { type: Object, default: () => ({}) },
});

const groupKeys = Object.keys(props.groupOptions);

const form = useForm({
    name: '',
    address: '',
    remarks: '',
    shop_group: groupKeys.length === 1 ? groupKeys[0] : '',
    is_active: true,
    default_start_time: '',
    default_end_time: '',
    default_capacity: 1,
    weekday_hours: [],
});

const submit = () => {
    form.transform((d) => ({
        ...d,
        default_start_time: d.default_start_time || null,
        default_end_time: d.default_end_time || null,
    })).post(route('admin.photo-studios.store'));
};
</script>
