<template>
    <UiDialog :open="open" title="DM状態の変更" size="md" @update:open="$emit('update:open', $event)">
        <div v-if="person" class="space-y-4">
            <dl class="grid grid-cols-[4.5rem_1fr] gap-x-3 gap-y-1.5 text-sm">
                <dt class="text-brand-text-muted text-xs pt-0.5">氏名</dt>
                <dd>{{ person.name }}<span v-if="person.kana" class="text-xs text-brand-text-muted ml-1">（{{ person.kana }}）</span></dd>
                <dt class="text-brand-text-muted text-xs pt-0.5">住所</dt>
                <dd>〒{{ person.zip }} {{ person.pref }}{{ person.address }}</dd>
                <dt class="text-brand-text-muted text-xs pt-0.5">保護者</dt>
                <dd>{{ person.guardian_name || '―' }}</dd>
                <dt class="text-brand-text-muted text-xs pt-0.5">出所</dt>
                <dd><SourceBadge :source-versions="person.source_versions" :merge-count="person.merge_count" /></dd>
            </dl>

            <fieldset>
                <legend class="text-xs font-medium text-brand-text-muted mb-1.5">DM状態</legend>
                <div class="grid grid-cols-3 gap-2">
                    <label
                        v-for="s in DM_STATUSES"
                        :key="s"
                        :class="[
                            'flex flex-col items-center gap-0.5 rounded-soft border px-2 py-2 cursor-pointer transition-colors text-center',
                            status === s ? 'border-brand-primary bg-brand-surface-2' : 'border-brand-border hover:bg-brand-surface-2',
                        ]"
                    >
                        <input v-model="status" type="radio" :value="s" class="sr-only" />
                        <UiBadge :variant="DM_STATUS_VARIANT[s]">{{ s }}</UiBadge>
                        <span class="text-[11px] text-brand-text-muted leading-tight">{{ DM_STATUS_HINT[s] }}</span>
                    </label>
                </div>
            </fieldset>

            <UiFormField label="メモ（返送理由・停止依頼日など）">
                <UiInput v-model="note" placeholder="例：2026/1 宛先不明で返送" />
            </UiFormField>

            <div>
                <div class="text-xs font-medium text-brand-text-muted mb-1">変更履歴</div>
                <div v-if="loadingLogs" class="text-xs text-brand-text-subtle">読み込み中...</div>
                <ul v-else-if="logs.length" class="max-h-36 overflow-y-auto divide-y divide-brand-border rounded-soft border border-brand-border text-xs">
                    <li v-for="l in logs" :key="l.id" class="px-3 py-1.5 flex flex-wrap items-center gap-1.5">
                        <span class="tabular-nums text-brand-text-muted">{{ fmt(l.changed_at) }}</span>
                        <UiBadge size="sm" :variant="DM_STATUS_VARIANT[l.old_status]">{{ l.old_status }}</UiBadge>
                        <span class="text-brand-text-subtle">→</span>
                        <UiBadge size="sm" :variant="DM_STATUS_VARIANT[l.new_status]">{{ l.new_status }}</UiBadge>
                        <span v-if="l.note" class="text-brand-text-muted">{{ l.note }}</span>
                    </li>
                </ul>
                <div v-else class="text-xs text-brand-text-subtle">まだ変更はありません。</div>
            </div>
        </div>

        <template #footer>
            <UiButton variant="ghost" @click="$emit('update:open', false)">キャンセル</UiButton>
            <UiButton variant="primary" :loading="saving" @click="save">保存</UiButton>
        </template>
    </UiDialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import { UiDialog, UiButton, UiBadge, UiInput, UiFormField } from '@/Components/UI';
import SourceBadge from '@/Components/Meibo/SourceBadge.vue';
import { useToast } from '@/composables/useToast.js';
import { DM_STATUSES, DM_STATUS_VARIANT, DM_STATUS_HINT } from '@/services/meibo/constants.js';

const props = defineProps({
    open: { type: Boolean, default: false },
    person: { type: Object, default: null },
    api: { type: Object, required: true },
});
const emit = defineEmits(['update:open', 'saved']);
const toast = useToast();

const status = ref('送付可');
const note = ref('');
const logs = ref([]);
const loadingLogs = ref(false);
const saving = ref(false);

watch(() => [props.open, props.person?.id], async ([isOpen]) => {
    if (!isOpen || !props.person) return;
    status.value = props.person.dm_status;
    note.value = props.person.dm_note || '';
    logs.value = [];
    loadingLogs.value = true;
    try {
        logs.value = (await props.api.person(props.person.id)).logs || [];
    } catch { /* 履歴が読めなくても変更はできる */ } finally {
        loadingLogs.value = false;
    }
}, { immediate: true });

const fmt = (s) => String(s || '').replace('T', ' ').slice(0, 16);

const save = async () => {
    saving.value = true;
    try {
        const updated = await props.api.updateDM(props.person.id, status.value, note.value);
        toast.success(`DM状態を「${status.value}」にしました。`);
        emit('saved', updated);
        emit('update:open', false);
    } catch (e) {
        toast.danger(`保存できませんでした：${e.message}`);
    } finally {
        saving.value = false;
    }
};
</script>
