<template>
    <div class="p-4 border-2 border-dashed border-indigo-300 rounded-lg bg-indigo-50">
        <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ label }}
        </label>
        
        <!-- スライドショーリスト -->
        <div v-if="slideshowList.length > 0" class="space-y-2 mb-3">
            <div
                v-for="(item, index) in slideshowList"
                :key="`${item.slideshow_id}-${index}`"
                class="flex items-center justify-between p-2 bg-white rounded border border-gray-200"
            >
                <div class="flex items-center space-x-2 flex-1">
                    <div class="cursor-move text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                        </svg>
                    </div>
                    <span class="text-sm text-gray-700">
                        {{ getSlideshowName(item.slideshow_id) }}
                    </span>
                </div>
                <button
                    @click="$emit('remove', index)"
                    class="text-red-600 hover:text-red-900 text-sm px-2 py-1"
                >
                    削除
                </button>
            </div>
        </div>
        
        <!-- スライドショー追加セレクト -->
        <select
            v-model="selectedSlideshow"
            @change="handleAdd"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        >
            <option :value="null">スライドショーを追加</option>
            <option
                v-for="slideshow in availableSlideshows"
                :key="slideshow.id"
                :value="slideshow.id"
            >
                {{ slideshow.name }}
            </option>
        </select>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    position: {
        type: Number,
        required: true,
    },
    slideshows: {
        type: Array,
        required: true,
    },
    slideshowList: {
        type: Array,
        default: () => [],
    },
    label: {
        type: String,
        default: 'スライドショーを表示',
    },
});

const emit = defineEmits(['add', 'remove', 'reorder']);

const selectedSlideshow = ref(null);

// 既に追加されていないスライドショーのみを表示
const availableSlideshows = computed(() => {
    const addedIds = props.slideshowList.map(item => item.slideshow_id);
    return props.slideshows.filter(s => !addedIds.includes(s.id));
});

const getSlideshowName = (slideshowId) => {
    const slideshow = props.slideshows.find(s => s.id === slideshowId);
    return slideshow ? slideshow.name : '不明なスライドショー';
};

const handleAdd = () => {
    if (selectedSlideshow.value) {
        emit('add', selectedSlideshow.value);
        selectedSlideshow.value = null;
    }
};
</script>

