<template>
    <div class="constraint-body-with-checks">
        <template v-for="(seg, i) in segments" :key="i">
            <div v-if="seg.type === 'markdown'" class="markdown-segment" v-html="seg.html"></div>
            <label v-else-if="seg.type === 'checkbox'" class="flex items-center gap-2 py-1 checkbox-segment" :class="{ 'cursor-pointer': !readonly }">
                <input
                    type="checkbox"
                    :checked="!!(checkValues && checkValues[seg.label])"
                    @change="!readonly && onCheckChange(seg.label, $event.target.checked)"
                    :disabled="readonly"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-70"
                />
                <span>{{ seg.label }}</span>
            </label>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { marked } from 'marked';

const props = defineProps({
    body: { type: String, default: '' },
    checkValues: { type: Object, default: () => ({}) },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:checkValues']);

const onCheckChange = (label, checked) => {
    emit('update:checkValues', { ...(props.checkValues || {}), [label]: checked });
};

// - [ ] ラベル または - [x] ラベル のパターン（行頭、オプションのインデント対応）
const CHECKBOX_RE = /^\s*-\s*\[([ x])\]\s+(.+)$/gm;

const segments = computed(() => {
    const text = props.body || '';
    const result = [];
    let lastIndex = 0;
    const regex = new RegExp(CHECKBOX_RE.source, 'gm');

    let m;
    while ((m = regex.exec(text)) !== null) {
        const checked = m[1] === 'x';
        const label = m[2].trim();

        const before = text.slice(lastIndex, m.index);
        if (before.trim()) {
            try {
                const html = marked.parse(before);
                result.push({ type: 'markdown', html: typeof html === 'string' ? html : String(html) });
            } catch {
                result.push({ type: 'markdown', html: escapeHtml(before) });
            }
        }
        result.push({ type: 'checkbox', label, defaultChecked: checked });
        lastIndex = regex.lastIndex;
    }
    const after = text.slice(lastIndex);
    if (after.trim()) {
        try {
            const html = marked.parse(after);
            result.push({ type: 'markdown', html: typeof html === 'string' ? html : String(html) });
        } catch {
            result.push({ type: 'markdown', html: escapeHtml(after) });
        }
    }

    return result;
});

function escapeHtml(s) {
    return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\n/g, '<br>');
}
</script>

<style scoped>
.markdown-segment :deep(p) {
    margin: 0.5em 0;
}
.markdown-segment :deep(ul) {
    list-style: disc;
    padding-left: 1.5em;
}
.markdown-segment :deep(ol) {
    list-style: decimal;
    padding-left: 1.5em;
}
.markdown-segment :deep(table) {
    border-collapse: collapse;
    width: 100%;
}
.markdown-segment :deep(th),
.markdown-segment :deep(td) {
    border: 1px solid #e5e7eb;
    padding: 0.25em 0.5em;
}
.markdown-segment :deep(strong) {
    font-weight: 600;
}
.markdown-segment :deep(hr) {
    border: none;
    border-top: 1px solid #e5e7eb;
    margin: 1em 0;
}
</style>
