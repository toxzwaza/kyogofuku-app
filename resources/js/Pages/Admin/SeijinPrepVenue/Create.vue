<template>
    <Head title="お支度場所の追加" />

    <AdminLayout :breadcrumb="[{ label: '成人式' }, { label: 'お支度場所', href: route('admin.seijin-prep-venues.index') }, { label: '追加' }]">
        <UiPageHeader title="お支度場所の追加" description="成人式のお支度をする会場・提携美容室を登録します。">
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.seijin-prep-venues.index')">一覧に戻る</UiButton>
            </template>
        </UiPageHeader>

        <form class="max-w-3xl space-y-5" @submit.prevent="submit">
            <VenueForm :form="form" :group-options="groupOptions" :kind-options="kindOptions" :intervals="intervals" :max-capacity="maxCapacity" />
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
import VenueForm from '@/Components/SeijinPrep/VenueForm.vue';

const props = defineProps({
    groupOptions: { type: Object, default: () => ({}) },
    kindOptions: { type: Object, default: () => ({}) },
    intervals: { type: Array, default: () => [30] },
    maxCapacity: { type: Number, default: 20 },
});

const groupKeys = Object.keys(props.groupOptions);
const form = useForm({
    name: '',
    kind: 'own',
    shop_group: groupKeys.length === 1 ? groupKeys[0] : '',
    is_active: true,
    sort_order: 0,
    address: '',
    phone: '',
    remarks: '',
    slot_interval_minutes: 30,
    default_start_time: '',
    default_end_time: '',
    default_capacity: 1,
});

const submit = () => {
    form.transform((d) => ({ ...d, default_start_time: d.default_start_time || null, default_end_time: d.default_end_time || null }))
        .post(route('admin.seijin-prep-venues.store'));
};
</script>
