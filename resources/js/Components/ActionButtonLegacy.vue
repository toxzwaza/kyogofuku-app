<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        :type="href ? undefined : type"
        :disabled="disabled"
        :class="buttonClasses"
        @click="onClick"
    >
        <!-- 追加アイコン -->
        <svg v-if="variant === 'create'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        <!-- 編集アイコン -->
        <svg v-else-if="variant === 'edit'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
        <!-- 削除アイコン -->
        <svg v-else-if="variant === 'delete'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        <!-- 詳細アイコン -->
        <svg v-else-if="variant === 'detail'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
        <!-- 戻るアイコン -->
        <svg v-else-if="variant === 'back'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        <!-- 検索アイコン -->
        <svg v-else-if="variant === 'search'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <!-- 保存アイコン -->
        <svg v-else-if="variant === 'save'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span v-if="label">{{ label }}</span>
        <slot v-else></slot>
    </component>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['create', 'edit', 'delete', 'detail', 'back', 'search', 'save', 'primary', 'secondary'].includes(value)
    },
    label: {
        type: String,
        default: ''
    },
    href: {
        type: String,
        default: ''
    },
    type: {
        type: String,
        default: 'button'
    },
    disabled: {
        type: Boolean,
        default: false
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg'].includes(value)
    }
});

const emit = defineEmits(['click']);

const onClick = (event) => {
    if (!props.href) {
        emit('click', event);
    }
};

const colorClasses = computed(() => {
    switch (props.variant) {
        case 'delete':
            return 'bg-red-600 hover:bg-red-700 focus:ring-red-500';
        case 'back':
        case 'secondary':
            return 'bg-gray-600 hover:bg-gray-700 focus:ring-gray-500';
        case 'detail':
            return 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500';
        case 'edit':
            return 'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500';
        case 'create':
        case 'save':
        case 'search':
        case 'primary':
        default:
            return 'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500';
    }
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'px-3 py-1.5 text-xs';
        case 'lg':
            return 'px-6 py-3 text-base';
        case 'md':
        default:
            return 'px-4 py-2 text-sm';
    }
});

const buttonClasses = computed(() => {
    return [
        'inline-flex items-center gap-2 font-medium text-white rounded-md shadow-sm',
        'focus:outline-none focus:ring-2 focus:ring-offset-2',
        'transition-colors duration-200',
        'disabled:opacity-50 disabled:cursor-not-allowed',
        colorClasses.value,
        sizeClasses.value,
    ].join(' ');
});
</script>

