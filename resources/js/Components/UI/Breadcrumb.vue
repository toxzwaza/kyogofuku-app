<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

defineProps({
    /**
     * [{ label: '顧客', href: '/admin/customers' }, { label: '詳細' }]
     * href 未指定の最後の項目は現在ページ。
     */
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <nav v-if="items.length" aria-label="Breadcrumb" class="text-xs text-brand-text-muted">
        <ol class="flex items-center gap-1 flex-wrap">
            <li v-for="(it, idx) in items" :key="idx" class="flex items-center gap-1">
                <Link
                    v-if="it.href && idx !== items.length - 1"
                    :href="it.href"
                    class="hover:text-brand-primary transition-colors"
                >
                    {{ it.label }}
                </Link>
                <span v-else class="text-brand-text">{{ it.label }}</span>
                <ChevronRight v-if="idx !== items.length - 1" :size="12" class="text-brand-text-subtle" />
            </li>
        </ol>
    </nav>
</template>
