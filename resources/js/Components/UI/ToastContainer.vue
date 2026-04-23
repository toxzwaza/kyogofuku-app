<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, Info, AlertTriangle, XCircle, X } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast.js';

const toast = useToast();

const variantConfig = {
    info:    { icon: Info,           ring: 'ring-brand-primary', iconCls: 'text-brand-primary' },
    success: { icon: CheckCircle2,   ring: 'ring-brand-success', iconCls: 'text-brand-success' },
    warning: { icon: AlertTriangle,  ring: 'ring-brand-warning', iconCls: 'text-brand-warning' },
    danger:  { icon: XCircle,        ring: 'ring-brand-danger',  iconCls: 'text-brand-danger' },
};

// Inertia の flash shared-props を自動で Toast へ流し込む
const page = usePage();
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        if (flash.success) toast.success(flash.success);
        if (flash.error)   toast.danger(flash.error);
    },
    { deep: true, immediate: true }
);
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed top-4 right-4 z-[60] flex flex-col gap-2 max-w-sm w-[calc(100vw-2rem)] sm:w-96">
            <transition-group
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 translate-x-4"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0 translate-x-4"
                move-class="transition duration-200"
            >
                <div
                    v-for="t in toast.state.toasts"
                    :key="t.id"
                    class="pointer-events-auto flex items-start gap-3 p-3.5 rounded-soft bg-brand-surface border border-brand-border shadow-soft-lg ring-1 ring-inset"
                    :class="variantConfig[t.variant]?.ring || variantConfig.info.ring"
                >
                    <component :is="variantConfig[t.variant]?.icon || variantConfig.info.icon" :size="18" class="flex-shrink-0 mt-0.5" :class="variantConfig[t.variant]?.iconCls || variantConfig.info.iconCls" />
                    <div class="flex-1 min-w-0 text-sm">
                        <div v-if="t.title" class="font-semibold text-brand-text mb-0.5">{{ t.title }}</div>
                        <div class="text-brand-text">{{ t.message }}</div>
                    </div>
                    <button
                        type="button"
                        class="flex-shrink-0 text-brand-text-muted hover:text-brand-text rounded p-1 -m-1 hover:bg-brand-surface-2 transition-colors"
                        @click="toast.remove(t.id)"
                    >
                        <X :size="14" />
                    </button>
                </div>
            </transition-group>
        </div>
    </Teleport>
</template>
