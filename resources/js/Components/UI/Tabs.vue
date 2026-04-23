<script setup>
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue';
import { computed } from 'vue';

const props = defineProps({
    tabs: { type: Array, required: true }, // [{id, label}]
    modelValue: { type: [String, Number], default: null },
});
const emit = defineEmits(['update:modelValue']);

const selectedIndex = computed(() => {
    if (props.modelValue == null) return 0;
    const idx = props.tabs.findIndex((t) => t.id === props.modelValue);
    return idx >= 0 ? idx : 0;
});

const onChange = (idx) => {
    emit('update:modelValue', props.tabs[idx]?.id);
};
</script>

<template>
    <TabGroup :selected-index="selectedIndex" @change="onChange">
        <TabList class="flex items-center gap-1 border-b border-brand-border overflow-x-auto">
            <Tab v-for="t in tabs" :key="t.id" v-slot="{ selected }" as="template">
                <button
                    type="button"
                    :class="[
                        'px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors border-b-2 -mb-px focus-visible:outline-none',
                        selected
                            ? 'border-brand-primary text-brand-primary'
                            : 'border-transparent text-brand-text-muted hover:text-brand-text hover:border-brand-border-strong',
                    ]"
                >
                    {{ t.label }}
                </button>
            </Tab>
        </TabList>
        <TabPanels class="pt-4">
            <TabPanel v-for="t in tabs" :key="t.id" :unmount="false">
                <slot :name="t.id" />
            </TabPanel>
        </TabPanels>
    </TabGroup>
</template>
