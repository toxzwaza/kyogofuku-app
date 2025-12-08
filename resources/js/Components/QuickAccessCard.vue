<template>
    <Link
        :href="link"
        :class="[
            'block bg-white overflow-hidden shadow-sm rounded-lg p-6 hover:shadow-md transition-shadow',
            colorClasses[color]?.border || 'border-gray-200'
        ]"
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
                <!-- Clock Icon -->
                <svg v-else-if="iconComponent === 'clock'" :class="[
                    'h-6 w-6',
                    colorClasses[color]?.text || 'text-gray-600'
                ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
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
            </div>
            <div class="ml-4 flex-1">
                <h4 :class="[
                    'text-lg font-semibold mb-1',
                    colorClasses[color]?.title || 'text-gray-900'
                ]">
                    {{ title }}
                </h4>
                <p class="text-sm text-gray-500">{{ description }}</p>
            </div>
            <div :class="[
                'flex-shrink-0 ml-4',
                colorClasses[color]?.arrow || 'text-gray-400'
            ]">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </div>
    </Link>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    title: String,
    description: String,
    icon: String,
    color: {
        type: String,
        default: 'blue',
    },
    link: [String, Object],
});

const colorClasses = {
    blue: {
        bg: 'bg-blue-100',
        text: 'text-blue-600',
        title: 'text-blue-900',
        arrow: 'text-blue-500',
        border: 'border-l-4 border-blue-500',
    },
    green: {
        bg: 'bg-green-100',
        text: 'text-green-600',
        title: 'text-green-900',
        arrow: 'text-green-500',
        border: 'border-l-4 border-green-500',
    },
    purple: {
        bg: 'bg-purple-100',
        text: 'text-purple-600',
        title: 'text-purple-900',
        arrow: 'text-purple-500',
        border: 'border-l-4 border-purple-500',
    },
    indigo: {
        bg: 'bg-indigo-100',
        text: 'text-indigo-600',
        title: 'text-indigo-900',
        arrow: 'text-indigo-500',
        border: 'border-l-4 border-indigo-500',
    },
    orange: {
        bg: 'bg-orange-100',
        text: 'text-orange-600',
        title: 'text-orange-900',
        arrow: 'text-orange-500',
        border: 'border-l-4 border-orange-500',
    },
    red: {
        bg: 'bg-red-100',
        text: 'text-red-600',
        title: 'text-red-900',
        arrow: 'text-red-500',
        border: 'border-l-4 border-red-500',
    },
};

const iconComponent = computed(() => {
    return props.icon || 'calendar';
});
</script>

