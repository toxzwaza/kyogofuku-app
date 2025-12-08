<template>
    <div
        :class="[
            'bg-white overflow-hidden shadow-sm rounded-lg p-6',
            link ? 'cursor-pointer hover:shadow-md transition-shadow' : ''
        ]"
        @click="handleClick"
    >
        <div class="flex items-center">
            <div :class="[
                'flex-shrink-0 p-3 rounded-md',
                colorClasses[color]?.bg || 'bg-gray-100'
            ]">
                <!-- Calendar Icon -->
                <svg v-if="iconComponent === 'calendar'" :class="[
                    'h-6 w-6',
                    colorClasses[color]?.text || 'text-gray-600'
                ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <!-- Clipboard Icon -->
                <svg v-else-if="iconComponent === 'clipboard'" :class="[
                    'h-6 w-6',
                    colorClasses[color]?.text || 'text-gray-600'
                ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <!-- Store Icon -->
                <svg v-else-if="iconComponent === 'store'" :class="[
                    'h-6 w-6',
                    colorClasses[color]?.text || 'text-gray-600'
                ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <!-- Users Icon -->
                <svg v-else-if="iconComponent === 'users'" :class="[
                    'h-6 w-6',
                    colorClasses[color]?.text || 'text-gray-600'
                ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <!-- Clock Icon -->
                <svg v-else-if="iconComponent === 'clock'" :class="[
                    'h-6 w-6',
                    colorClasses[color]?.text || 'text-gray-600'
                ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <!-- Chart Icon -->
                <svg v-else-if="iconComponent === 'chart'" :class="[
                    'h-6 w-6',
                    colorClasses[color]?.text || 'text-gray-600'
                ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <div class="ml-4 flex-1">
                <p class="text-sm font-medium text-gray-500">{{ title }}</p>
                <p :class="[
                    'text-2xl font-semibold',
                    colorClasses[color]?.value || 'text-gray-900'
                ]">
                    {{ value }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    title: String,
    value: [Number, String],
    icon: String,
    color: {
        type: String,
        default: 'blue',
    },
    link: [String, Object, null],
});

const colorClasses = {
    blue: {
        bg: 'bg-blue-100',
        text: 'text-blue-600',
        value: 'text-blue-600',
    },
    green: {
        bg: 'bg-green-100',
        text: 'text-green-600',
        value: 'text-green-600',
    },
    purple: {
        bg: 'bg-purple-100',
        text: 'text-purple-600',
        value: 'text-purple-600',
    },
    indigo: {
        bg: 'bg-indigo-100',
        text: 'text-indigo-600',
        value: 'text-indigo-600',
    },
    orange: {
        bg: 'bg-orange-100',
        text: 'text-orange-600',
        value: 'text-orange-600',
    },
    yellow: {
        bg: 'bg-yellow-100',
        text: 'text-yellow-600',
        value: 'text-yellow-600',
    },
    red: {
        bg: 'bg-red-100',
        text: 'text-red-600',
        value: 'text-red-600',
    },
};

const iconComponent = computed(() => {
    return props.icon || 'calendar';
});

const handleClick = () => {
    if (props.link) {
        router.visit(props.link);
    }
};
</script>

