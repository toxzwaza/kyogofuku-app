<script setup>
import { computed } from 'vue';
import { Info, CheckCircle2, AlertTriangle, XCircle, X } from 'lucide-vue-next';

const props = defineProps({
    variant: { type: String, default: 'info' }, // info | success | warning | danger
    title: { type: String, default: '' },
    dismissible: { type: Boolean, default: false },
});

defineEmits(['dismiss']);

const variantConfig = {
    info: {
        container: 'bg-ai-50 border-brand-primary text-ai-900 dark:bg-ai-900 dark:text-ai-100',
        icon: Info,
        iconClass: 'text-brand-primary',
    },
    success: {
        container: 'bg-uguisu-50 border-brand-success text-uguisu-900 dark:bg-uguisu-900 dark:text-uguisu-100',
        icon: CheckCircle2,
        iconClass: 'text-brand-success',
    },
    warning: {
        container: 'bg-natane-50 border-brand-warning text-natane-900 dark:bg-natane-900 dark:text-natane-100',
        icon: AlertTriangle,
        iconClass: 'text-brand-warning',
    },
    danger: {
        container: 'bg-akane-50 border-brand-danger text-akane-900 dark:bg-akane-900 dark:text-akane-100',
        icon: XCircle,
        iconClass: 'text-brand-danger',
    },
};

const cfg = computed(() => variantConfig[props.variant] || variantConfig.info);
</script>

<template>
    <div class="flex items-start gap-3 p-3.5 border-l-4 rounded-soft text-sm" :class="cfg.container">
        <component :is="cfg.icon" :size="18" class="flex-shrink-0 mt-0.5" :class="cfg.iconClass" />
        <div class="flex-1 min-w-0">
            <div v-if="title" class="font-semibold mb-0.5">{{ title }}</div>
            <div><slot /></div>
        </div>
        <button
            v-if="dismissible"
            type="button"
            class="flex-shrink-0 -m-1 p-1 rounded hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
            @click="$emit('dismiss')"
        >
            <X :size="16" />
        </button>
    </div>
</template>
