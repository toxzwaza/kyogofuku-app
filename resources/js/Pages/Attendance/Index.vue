<template>
    <Head title="打刻" />
    <AdminLayout :breadcrumb="[{ label: '勤怠' }, { label: '打刻' }]">

        <UiPageHeader
            title="打刻"
            description="出勤・休憩・退勤の打刻を行います。"
        />

        <PunchPanel
            :attendance-status="attendanceStatus"
            :user-shops="userShops"
            :current-user="currentUser"
            :attendance-manual-url="attendanceManualUrl"
            :attendance-manual-url-manager="attendanceManualUrlManager"
        />

    </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiPageHeader } from '@/Components/UI';
import PunchPanel from '@/Components/Attendance/PunchPanel.vue';

defineProps({
    attendanceStatus: { type: Object, default: () => ({}) },
    userShops: { type: Array, default: () => [] },
    currentUser: { type: Object, default: null },
    attendanceManualUrl: { type: String, default: '' },
    attendanceManualUrlManager: { type: String, default: '' },
});
</script>
