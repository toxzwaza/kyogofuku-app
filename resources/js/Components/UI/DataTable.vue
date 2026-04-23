<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowUp, ArrowDown, ArrowUpDown, Inbox } from 'lucide-vue-next';

const props = defineProps({
    /**
     * columns: [
     *   { key, label, sortable?: bool, class?: string, align?: 'left|right|center', hideOnMobile?: bool, width?: string }
     * ]
     */
    columns: { type: Array, required: true },
    /** 行データ配列 */
    rows: { type: Array, default: () => [] },
    /** 行キー取得関数。デフォルトは row.id */
    rowKey: { type: Function, default: (r) => r.id },
    /** 行クリック時のInertia href生成。nullなら非リンク */
    rowHref: { type: Function, default: null },
    /** 現在のソート。{ key, dir: 'asc'|'desc' } */
    sort: { type: Object, default: null },
    /** Inertia ページネーションオブジェクト（Laravel paginator JSON） */
    pagination: { type: Object, default: null },
    /** 空状態メッセージ */
    emptyMessage: { type: String, default: '該当するデータがありません' },
    /** 読み込み中 */
    loading: { type: Boolean, default: false },
    /** 一括選択を有効化 */
    selectable: { type: Boolean, default: false },
    /** v-model:selected */
    selected: { type: Array, default: () => [] },
});

const emit = defineEmits(['sort-change', 'update:selected']);

const allSelected = computed(() => {
    return props.rows.length > 0 && props.selected.length === props.rows.length;
});

const toggleAll = () => {
    if (allSelected.value) {
        emit('update:selected', []);
    } else {
        emit('update:selected', props.rows.map((r) => props.rowKey(r)));
    }
};

const toggleRow = (key) => {
    const s = [...props.selected];
    const i = s.indexOf(key);
    if (i >= 0) s.splice(i, 1);
    else s.push(key);
    emit('update:selected', s);
};

const onSort = (col) => {
    if (!col.sortable) return;
    const same = props.sort?.key === col.key;
    const nextDir = same && props.sort?.dir === 'asc' ? 'desc' : 'asc';
    emit('sort-change', { key: col.key, dir: nextDir });
};

const alignClass = (col) => {
    if (col.align === 'right') return 'text-right';
    if (col.align === 'center') return 'text-center';
    return 'text-left';
};
</script>

<template>
    <div>
        <!-- Desktop table -->
        <div class="hidden md:block rounded-soft-lg border border-brand-border overflow-hidden bg-brand-surface">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-brand-surface-2 text-brand-text-muted">
                        <tr>
                            <th v-if="selectable" class="w-10 p-3 text-left">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    class="rounded border-brand-border-strong focus:ring-brand-primary text-brand-primary"
                                    @change="toggleAll"
                                />
                            </th>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                :class="[
                                    'px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider',
                                    alignClass(col),
                                    col.sortable ? 'cursor-pointer select-none hover:text-brand-text' : '',
                                    col.hideOnMobile ? 'hidden lg:table-cell' : '',
                                ]"
                                :style="col.width ? { width: col.width } : {}"
                                @click="onSort(col)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    {{ col.label }}
                                    <template v-if="col.sortable">
                                        <ArrowUp   v-if="sort?.key === col.key && sort.dir === 'asc'"  :size="12" />
                                        <ArrowDown v-else-if="sort?.key === col.key && sort.dir === 'desc'" :size="12" />
                                        <ArrowUpDown v-else :size="12" class="opacity-40" />
                                    </template>
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-if="loading">
                            <td :colspan="columns.length + (selectable ? 1 : 0)" class="p-8 text-center text-brand-text-muted">
                                読み込み中...
                            </td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td :colspan="columns.length + (selectable ? 1 : 0)" class="p-8 text-center">
                                <div class="flex flex-col items-center gap-2 text-brand-text-muted">
                                    <Inbox :size="28" class="text-brand-text-subtle" />
                                    <span class="text-sm">{{ emptyMessage }}</span>
                                </div>
                            </td>
                        </tr>
                        <template v-else>
                            <tr
                                v-for="row in rows"
                                :key="rowKey(row)"
                                :class="[
                                    'transition-colors',
                                    rowHref ? 'hover:bg-brand-surface-2 cursor-pointer' : 'hover:bg-brand-surface-2',
                                ]"
                            >
                                <td v-if="selectable" class="w-10 p-3" @click.stop>
                                    <input
                                        type="checkbox"
                                        :checked="selected.includes(rowKey(row))"
                                        class="rounded border-brand-border-strong focus:ring-brand-primary text-brand-primary"
                                        @change="toggleRow(rowKey(row))"
                                    />
                                </td>
                                <td
                                    v-for="col in columns"
                                    :key="col.key"
                                    :class="[
                                        'px-3 py-2.5',
                                        alignClass(col),
                                        col.hideOnMobile ? 'hidden lg:table-cell' : '',
                                        col.class || '',
                                    ]"
                                >
                                    <!-- rowHref があれば Link で包んでクリック領域を広げる -->
                                    <Link v-if="rowHref && !col.noLink" :href="rowHref(row)" class="block -my-2.5 py-2.5">
                                        <slot :name="`cell-${col.key}`" :row="row" :col="col" :value="row[col.key]">
                                            {{ row[col.key] }}
                                        </slot>
                                    </Link>
                                    <slot v-else :name="`cell-${col.key}`" :row="row" :col="col" :value="row[col.key]">
                                        {{ row[col.key] }}
                                    </slot>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile card list -->
        <div class="md:hidden space-y-2">
            <div v-if="loading" class="p-6 text-center text-brand-text-muted text-sm rounded-soft-lg border border-brand-border bg-brand-surface">
                読み込み中...
            </div>
            <div v-else-if="rows.length === 0" class="p-6 text-center rounded-soft-lg border border-brand-border bg-brand-surface">
                <div class="flex flex-col items-center gap-2 text-brand-text-muted">
                    <Inbox :size="24" class="text-brand-text-subtle" />
                    <span class="text-sm">{{ emptyMessage }}</span>
                </div>
            </div>
            <template v-else>
                <component
                    :is="rowHref ? Link : 'div'"
                    v-for="row in rows"
                    :key="rowKey(row)"
                    :href="rowHref ? rowHref(row) : undefined"
                    class="block rounded-soft-lg border border-brand-border bg-brand-surface p-3 hover:bg-brand-surface-2 transition-colors"
                >
                    <slot name="mobile-card" :row="row">
                        <!-- default mobile rendering: list each column as key/value -->
                        <dl class="space-y-1 text-sm">
                            <template v-for="col in columns" :key="col.key">
                                <div class="flex items-start justify-between gap-2">
                                    <dt class="text-[11px] text-brand-text-muted uppercase tracking-wide flex-shrink-0">{{ col.label }}</dt>
                                    <dd class="text-right min-w-0 flex-1">
                                        <slot :name="`cell-${col.key}`" :row="row" :col="col" :value="row[col.key]">
                                            {{ row[col.key] }}
                                        </slot>
                                    </dd>
                                </div>
                            </template>
                        </dl>
                    </slot>
                </component>
            </template>
        </div>

        <!-- Pagination (Laravel paginator) -->
        <div
            v-if="pagination && pagination.last_page > 1"
            class="mt-4 flex items-center justify-between flex-wrap gap-2 text-sm"
        >
            <div class="text-brand-text-muted text-xs">
                {{ pagination.from ?? 0 }} 〜 {{ pagination.to ?? 0 }} / 全 {{ pagination.total }} 件
            </div>
            <nav class="flex items-center gap-1">
                <template v-for="(link, idx) in pagination.links" :key="idx">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        :class="[
                            'px-2.5 py-1 rounded border transition-colors text-xs',
                            link.active
                                ? 'bg-brand-primary text-brand-on-primary border-brand-primary'
                                : 'bg-brand-surface border-brand-border text-brand-text hover:bg-brand-surface-2',
                        ]"
                        preserve-scroll
                    />
                    <span
                        v-else
                        v-html="link.label"
                        class="px-2.5 py-1 rounded border border-brand-border text-xs text-brand-text-subtle"
                    />
                </template>
            </nav>
        </div>
    </div>
</template>
