<template>
    <Head title="お支度場所の編集" />

    <AdminLayout :breadcrumb="[{ label: '成人式' }, { label: 'お支度場所', href: route('admin.seijin-prep-venues.index') }, { label: '編集' }]">
        <UiPageHeader :title="`お支度場所の編集：${venue.name}`">
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.seijin-prep-venues.index')">一覧に戻る</UiButton>
            </template>
        </UiPageHeader>

        <form class="max-w-3xl space-y-5" @submit.prevent="submit">
            <VenueForm :form="form" :group-options="groupOptions" :kind-options="kindOptions" :intervals="intervals" :max-capacity="maxCapacity" />
            <div class="flex justify-end">
                <UiButton type="submit" variant="primary" :loading="form.processing">保存する</UiButton>
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
    venue: { type: Object, required: true },
    groupOptions: { type: Object, default: () => ({}) },
    kindOptions: { type: Object, default: () => ({}) },
    intervals: { type: Array, default: () => [30] },
    maxCapacity: { type: Number, default: 20 },
});

const v = props.venue;
const form = useForm({
    name: v.name,
    kind: v.kind,
    shop_group: v.shop_group || '',
    is_active: v.is_active,
    sort_order: v.sort_order,
    address: v.address || '',
    phone: v.phone || '',
    remarks: v.remarks || '',
    slot_interval_minutes: v.slot_interval_minutes,
    default_start_time: v.default_start_time || '',
    default_end_time: v.default_end_time || '',
    default_capacity: v.default_capacity,
});

const submit = () => {
    form.transform((d) => ({ ...d, default_start_time: d.default_start_time || null, default_end_time: d.default_end_time || null }))
        .put(route('admin.seijin-prep-venues.update', v.id));
};
</script>
