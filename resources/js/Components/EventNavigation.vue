<template>
    <div class=" border-gray-200 pb-6">
        <h3 class="text-lg font-semibold mb-4 text-gray-800">操作</h3>
        <div class="flex flex-wrap gap-3">
            <Link
                :href="route('event.show', event.slug)"
                target="_blank"
                class="group relative inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-sm hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                公開ページを表示
            </Link>
            <Link
                :href="route('admin.events.images.index', event.id)"
                :class="[
                    'group relative inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-emerald-600 to-emerald-700 rounded-lg shadow-sm hover:from-emerald-700 hover:to-emerald-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-all duration-200',
                    isCurrentPage('admin.events.images.index', { event: event.id }) ? 'opacity-50 cursor-default pointer-events-none' : ''
                ]"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                画像管理
            </Link>
            <Link
                :href="route('admin.events.reservations.index', event.id)"
                :class="[
                    'group relative inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200',
                    isCurrentPage('admin.events.reservations.index', { event: event.id }) ? 'opacity-50 cursor-default pointer-events-none' : ''
                ]"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                予約一覧
            </Link>
            <Link
                :href="route('admin.events.timeslots.index', event.id)"
                :class="[
                    'group relative inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-purple-600 to-purple-700 rounded-lg shadow-sm hover:from-purple-700 hover:to-purple-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-all duration-200',
                    isCurrentPage('admin.events.timeslots.index', { event: event.id }) ? 'opacity-50 cursor-default pointer-events-none' : ''
                ]"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                枠管理
            </Link>
            <button
                v-if="showUrlButton"
                @click="$emit('url-modal-open')"
                class="group relative inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-amber-600 to-amber-700 rounded-lg shadow-sm hover:from-amber-700 hover:to-amber-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-all duration-200"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                URL発行
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    showUrlButton: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['url-modal-open']);

const page = usePage();

// 現在のページを判定
const isCurrentPage = (routeName, params = {}) => {
    const currentUrl = page.url.split('?')[0]; // クエリパラメータを除く
    
    // URLパターンで判定
    const patterns = {
        'admin.events.show': `/admin/events/${params.event}`,
        'admin.events.images.index': `/admin/events/${params.event}/images`,
        'admin.events.reservations.index': `/admin/events/${params.event}/reservations`,
        'admin.events.timeslots.index': `/admin/events/${params.event}/timeslots`,
    };
    
    const expectedPath = patterns[routeName];
    return expectedPath && currentUrl === expectedPath;
};
</script>

