<template>
    <div class="p-4 border-2 border-dashed border-amber-300 rounded-lg bg-amber-50/80">
        <label class="flex items-center gap-2 cursor-pointer">
            <input
                type="checkbox"
                :checked="modelValue"
                class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                @change="$emit('update:modelValue', ($event.target).checked)"
            />
            <span class="text-sm font-medium text-gray-700">{{ label }}</span>
        </label>
    </div>
</template>

<script setup>
defineProps({
    position: { type: Number, required: true },
    label: { type: String, default: 'この位置にCTAボタンを表示' },
    modelValue: { type: Boolean, default: false },
});
defineEmits(['update:modelValue']);
</script>
