<script setup>
import { computed } from 'vue';
import {
    Dialog as HDialog,
    DialogPanel,
    DialogTitle,
    TransitionRoot,
    TransitionChild,
} from '@headlessui/vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm | md | lg | xl | full
    closeOnOverlay: { type: Boolean, default: true },
});

const emit = defineEmits(['update:open', 'close']);

const close = () => {
    emit('update:open', false);
    emit('close');
};

const sizeClasses = {
    sm:  'max-w-sm',
    md:  'max-w-md',
    lg:  'max-w-2xl',
    xl:  'max-w-4xl',
    full: 'max-w-[95vw] h-[90vh]',
};

const panelClasses = computed(() => [
    'w-full rounded-soft-lg bg-brand-surface text-brand-text shadow-soft-lg',
    'border border-brand-border',
    sizeClasses[props.size] || sizeClasses.md,
]);
</script>

<template>
    <TransitionRoot :show="open" as="template">
        <HDialog as="div" class="relative z-50" @close="closeOnOverlay ? close() : null">
            <TransitionChild
                as="template"
                enter="duration-200 ease-out" enter-from="opacity-0" enter-to="opacity-100"
                leave="duration-150 ease-in" leave-from="opacity-100" leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-sumi-950/50 backdrop-blur-sm" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <TransitionChild
                        as="template"
                        enter="duration-200 ease-out"
                        enter-from="opacity-0 scale-95"
                        enter-to="opacity-100 scale-100"
                        leave="duration-150 ease-in"
                        leave-from="opacity-100 scale-100"
                        leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel :class="panelClasses">
                            <div v-if="title || $slots.header" class="flex items-center justify-between px-5 py-4 border-b border-brand-border">
                                <DialogTitle as="h3" class="text-base font-semibold text-brand-text">
                                    <slot name="header">{{ title }}</slot>
                                </DialogTitle>
                                <button
                                    type="button"
                                    class="text-brand-text-muted hover:text-brand-text rounded p-1 -m-1 hover:bg-brand-surface-2 transition-colors"
                                    @click="close"
                                >
                                    <X :size="18" />
                                </button>
                            </div>
                            <div class="p-5">
                                <slot />
                            </div>
                            <div v-if="$slots.footer" class="px-5 py-3 border-t border-brand-border bg-brand-surface-2 rounded-b-soft-lg flex items-center justify-end gap-2">
                                <slot name="footer" />
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </HDialog>
    </TransitionRoot>
</template>
