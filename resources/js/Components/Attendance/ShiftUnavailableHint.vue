<template>
    <span
        class="relative inline-flex items-center justify-center group cursor-help align-middle"
        tabindex="0"
        aria-label="シフト時刻を取得できません"
    >
        <span class="sr-only">シフト時刻未取得</span>
        <svg
            class="h-5 w-5 text-amber-500 shrink-0"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"
            />
        </svg>
        <span
            class="pointer-events-none absolute left-1/2 bottom-full z-20 mb-1 w-72 -translate-x-1/2 rounded-md border border-gray-200 bg-white px-3 py-2 text-left text-xs text-gray-700 shadow-lg opacity-0 invisible transition-opacity duration-150 group-hover:opacity-100 group-hover:visible group-focus-visible:opacity-100 group-focus-visible:visible"
            role="tooltip"
        >
            <span class="block font-semibold text-gray-900 mb-1">シフト時刻を表示できません</span>
            <span class="block text-gray-600 mb-1.5">次をご確認ください。</span>
            <ul v-if="reasons.length" class="list-disc pl-4 space-y-1 text-gray-700">
                <li v-for="(msg, i) in reasons" :key="i">{{ msg }}</li>
            </ul>
            <p v-else class="text-gray-600">勤怠管理者にシフト設定の確認を依頼してください。</p>
        </span>
    </span>
</template>

<script setup>
defineProps({
    reasons: {
        type: Array,
        default: () => [],
    },
});
</script>
