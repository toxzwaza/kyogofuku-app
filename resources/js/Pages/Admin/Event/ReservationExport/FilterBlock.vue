<template>
    <div class="bg-white shadow-sm rounded-lg">
        <button
            type="button"
            @click="collapsed = !collapsed"
            class="w-full flex items-center justify-between px-4 py-3 text-left"
        >
            <div class="flex items-center gap-3">
                <svg
                    class="w-4 h-4 text-gray-400 transition-transform"
                    :class="{ 'rotate-[-90deg]': collapsed }"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
                <span class="text-sm font-semibold text-gray-800">{{ title }}</span>
                <span class="text-xs text-gray-500 tabular-nums">
                    {{ countSelected }} / {{ countTotal }}
                </span>
            </div>
            <div class="flex items-center gap-1.5" @click.stop>
                <slot name="extra" />
                <button
                    type="button"
                    @click="$emit('select-all')"
                    class="text-xs px-2 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
                >全選択</button>
                <button
                    type="button"
                    @click="$emit('clear-all')"
                    class="text-xs px-2 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
                >全解除</button>
            </div>
        </button>
        <div v-show="!collapsed" class="px-4 pb-4 pt-1 border-t border-gray-100">
            <slot />
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    title: { type: String, required: true },
    countSelected: { type: Number, default: 0 },
    countTotal: { type: Number, default: 0 },
});

defineEmits(['select-all', 'clear-all']);

const collapsed = ref(false);
</script>
