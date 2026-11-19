<script setup>
/**
 * ボタンで1つ選ぶ（共有ノート用）。
 * other-* を渡すと「その他」を選んだときだけ入力欄を出す。
 */
import { nextTick, ref } from 'vue';
import { OTHER } from '@/composables/useIncidentFlow.js';

const props = defineProps({
    modelValue: { type: String, default: '' },
    items: { type: Array, required: true },
    error: { type: Boolean, default: false },
    withOther: { type: Boolean, default: false },
    other: { type: String, default: '' },
    otherPlaceholder: { type: String, default: '具体的に' },
    name: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'update:other']);
const otherInput = ref(null);

const choose = async (v) => {
    emit('update:modelValue', v);
    if (props.withOther && v === OTHER) {
        await nextTick();
        otherInput.value?.focus();
    }
};
</script>

<template>
    <div>
        <div
            class="flex flex-wrap gap-2 rounded-soft"
            :class="error ? 'ring-2 ring-brand-danger ring-offset-2 ring-offset-brand-surface' : ''"
            role="radiogroup"
            :data-chips="name || undefined"
        >
            <button
                v-for="item in items"
                :key="item"
                type="button"
                role="radio"
                :aria-checked="modelValue === item"
                class="min-h-[44px] md:min-h-[36px] px-3.5 rounded-full border text-sm font-medium transition-colors"
                :class="modelValue === item
                    ? 'bg-brand-primary border-brand-primary text-brand-on-primary'
                    : 'bg-brand-surface border-brand-border-strong text-brand-text hover:bg-brand-surface-2'"
                @click="choose(item)"
            >{{ item }}</button>
        </div>
        <div v-if="withOther && modelValue === OTHER" class="mt-2 space-y-1">
            <input
                ref="otherInput"
                :value="other"
                type="text"
                maxlength="50"
                :name="name ? `${name}_other` : undefined"
                :placeholder="otherPlaceholder"
                autocomplete="off"
                class="block w-full h-11 md:h-9 px-3 text-base md:text-sm rounded-soft border border-brand-border-strong bg-brand-surface text-brand-text focus:ring-2 focus:ring-brand-primary focus:border-brand-primary"
                @input="emit('update:other', $event.target.value)"
            />
            <p class="text-xs text-brand-text-muted">空欄のときは「その他」で記録します。</p>
        </div>
    </div>
</template>
