<template>
    <UiCard padding="sm">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <h2 class="font-serif text-base mr-auto">テンプレート</h2>
            <UiButton size="sm" variant="accent" @click="openCreate">
                <template #leading><Plus :size="14" /></template>
                新しいテンプレートを追加
            </UiButton>
        </div>

        <ul v-if="templates.length" class="space-y-2">
            <li
                v-for="t in templates"
                :key="t.id"
                :class="[
                    'rounded-soft border transition-colors',
                    isSelected(t) ? 'border-brand-primary bg-brand-surface-2' : 'border-brand-border hover:bg-brand-surface-2/60',
                ]"
            >
                <div class="flex items-start gap-2 p-2.5">
                    <button
                        type="button"
                        class="flex-1 min-w-0 text-left"
                        :aria-pressed="isSelected(t)"
                        :title="`「${t.name}」を使う`"
                        @click="select(t)"
                    >
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span :class="['inline-block w-3.5 h-3.5 rounded-full border-2 shrink-0', isSelected(t) ? 'border-brand-primary bg-brand-primary shadow-[inset_0_0_0_2px_white]' : 'border-brand-border-strong']" />
                            <span class="text-sm font-medium">{{ t.name }}</span>
                            <UiBadge v-if="isSelected(t)" size="sm" variant="primary">使用中</UiBadge>
                        </div>
                        <div class="mt-1 pl-5 text-xs text-brand-text-muted break-all">
                            <template v-if="t.sender_zip || t.sender_address">〒{{ t.sender_zip || '—' }} {{ t.sender_address || '（住所なし）' }}</template>
                            <span v-else class="text-brand-warning">差出人の住所が未入力</span>
                        </div>
                        <div class="mt-0.5 pl-5 text-[11px] text-brand-text-subtle">
                            {{ t.sender_company || '会社名なし' }}・{{ t.addressee === 'guardian' ? '保護者宛' : '本人宛' }}<template v-if="t.with_family">・ご家族様</template><template v-if="t.tantou">・担当 {{ t.tantou }}</template>
                        </div>
                    </button>
                    <div class="flex shrink-0 gap-1">
                        <UiButton size="sm" variant="ghost" :title="`「${t.name}」を編集`" @click="openEdit(t)">
                            <template #leading><Pencil :size="13" /></template>
                            編集
                        </UiButton>
                        <UiButton size="sm" variant="ghost" class="!text-brand-danger" :aria-label="`「${t.name}」を削除`" :title="`「${t.name}」を削除`" @click="askRemove(t)">
                            <Trash2 :size="13" />
                        </UiButton>
                    </div>
                </div>
            </li>
        </ul>
        <p v-else class="rounded-soft border border-dashed border-brand-border-strong px-3 py-4 text-center text-xs text-brand-text-muted">
            テンプレートはまだありません。「新しいテンプレートを追加」から作れます。
        </p>

        <!-- 選んだテンプレートから入力を変えたとき -->
        <div v-if="current && dirty" class="mt-3 flex flex-wrap items-center gap-2 rounded-soft bg-natane-50 dark:bg-natane-900 px-3 py-2 text-xs">
            <span class="mr-auto">「{{ current.name }}」から入力を変えています。</span>
            <UiButton size="sm" variant="ghost" @click="emit('apply', current)">元に戻す</UiButton>
            <UiButton size="sm" variant="subtle" :loading="saving" @click="overwriteCurrent">
                <template #leading><Save :size="13" /></template>
                「{{ current.name }}」に上書き保存
            </UiButton>
        </div>
        <p class="mt-2 text-xs text-brand-text-subtle">押すと、差出人・宛名・担当・ご家族様の設定を呼び出します。</p>

        <!-- 追加・編集 -->
        <UiDialog v-model:open="editOpen" :title="editing ? `テンプレート「${editing.name}」を編集` : '新しいテンプレートを追加'" size="md">
            <div class="space-y-4">
                <p v-if="!editing" class="text-xs text-brand-text-muted">いま画面に入っている差出人・宛名を下書きにしています。</p>
                <UiFormField label="テンプレート名" required :error="nameError">
                    <UiInput v-model="draft.name" placeholder="例：岡山発送" />
                </UiFormField>

                <UiAlert v-if="conflict" variant="warning" :title="`「${conflict.name}」はすでにあります`">
                    <template v-if="editing">保存すると「{{ conflict.name }}」をこの内容で上書きし、「{{ editing.name }}」は消えます。</template>
                    <template v-else>保存すると、いまの「{{ conflict.name }}」をこの内容で上書きします。別のテンプレートとして残すときは名前を変えてください。</template>
                </UiAlert>

                <fieldset>
                    <legend class="text-xs font-medium text-brand-text-muted mb-1.5">差出人</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-[8rem_1fr] gap-3">
                        <UiFormField label="郵便番号">
                            <UiInput v-model="draft.sender_zip" placeholder="700-0000" inputmode="numeric" />
                        </UiFormField>
                        <UiFormField label="会社名">
                            <UiInput v-model="draft.sender_company" placeholder="(株)京呉服　平田" />
                        </UiFormField>
                        <UiFormField label="住所" class="sm:col-span-2">
                            <UiInput v-model="draft.sender_address" placeholder="岡山県岡山市北区〇〇1-2-3" />
                        </UiFormField>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="text-xs font-medium text-brand-text-muted mb-1.5">宛名</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label
                            v-for="o in ADDRESSEE"
                            :key="o.value"
                            :class="['rounded-soft border px-3 py-2 cursor-pointer transition-colors', draft.addressee === o.value ? 'border-brand-primary bg-brand-surface-2' : 'border-brand-border hover:bg-brand-surface-2']"
                        >
                            <input v-model="draft.addressee" type="radio" :value="o.value" class="sr-only" />
                            <div class="text-sm font-medium">{{ o.label }}</div>
                            <div class="text-[11px] text-brand-text-muted">{{ o.hint }}</div>
                        </label>
                    </div>
                    <label class="mt-3 flex items-center gap-2 text-sm cursor-pointer">
                        <input v-model="draft.with_family" type="checkbox" class="rounded border-brand-border-strong text-brand-primary focus:ring-brand-primary" />
                        氏名の下に「ご家族様」を印字する
                    </label>
                </fieldset>

                <UiFormField label="担当名（任意）">
                    <UiInput v-model="draft.tantou" placeholder="例：村上" />
                </UiFormField>
            </div>
            <template #footer>
                <UiButton variant="ghost" @click="editOpen = false">キャンセル</UiButton>
                <UiButton :variant="conflict ? 'danger' : 'primary'" :loading="saving" :disabled="!draft.name.trim()" @click="submit">
                    {{ conflict ? '上書きして保存' : editing ? '保存' : '追加' }}
                </UiButton>
            </template>
        </UiDialog>

        <!-- 削除の確認 -->
        <UiDialog v-model:open="removeOpen" title="テンプレートを削除" size="sm">
            <p class="text-sm">テンプレート「{{ removing?.name }}」を削除しますか？ 元に戻せません。</p>
            <template #footer>
                <UiButton variant="ghost" @click="removeOpen = false">キャンセル</UiButton>
                <UiButton variant="danger" :loading="saving" @click="remove">削除する</UiButton>
            </template>
        </UiDialog>
    </UiCard>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { UiCard, UiButton, UiBadge, UiInput, UiFormField, UiDialog, UiAlert } from '@/Components/UI';
import { Plus, Pencil, Trash2, Save } from 'lucide-vue-next';
import { DEFAULT_LABEL_FORM } from '@/services/meibo';
import { useToast } from '@/composables/useToast.js';

/**
 * ラベル発行のテンプレート（福井発送・岡山発送など）の一覧・追加・編集・削除。
 * 押したテンプレートの中身は apply で親に渡し、親のフォームに入れてもらう。
 */
const props = defineProps({
    api: { type: Object, required: true },
    form: { type: Object, required: true },   // 親のいまの入力（下書き・変更の検出に使う）
});
const emit = defineEmits(['apply']);
const toast = useToast();

const ADDRESSEE = [
    { value: 'self', label: '本人宛', hint: '本人氏名＋様' },
    { value: 'guardian', label: '保護者宛', hint: '保護者氏名＋様' },
];
const KEYS = Object.keys(DEFAULT_LABEL_FORM);
const pick = (src) => Object.fromEntries(KEYS.map((k) => [k, k === 'with_family' ? !!src[k] : String(src[k] ?? '')]));

const templates = ref([]);
const selectedId = ref(null);
const saving = ref(false);

const current = computed(() => templates.value.find((t) => t.id === selectedId.value) || null);
const isSelected = (t) => t.id === selectedId.value;
const dirty = computed(() => {
    if (!current.value) return false;
    const a = pick(current.value);
    const b = pick(props.form);
    return KEYS.some((k) => a[k] !== b[k]);
});

const load = async () => {
    try { templates.value = await props.api.templates(); } catch { templates.value = []; }
};
const select = (t) => {
    selectedId.value = t?.id ?? null;
    if (t) emit('apply', t);
};
const byName = (name) => templates.value.find((t) => t.name === name.trim());

// ---- 追加・編集 ----
const editOpen = ref(false);
const editing = ref(null); // null なら追加
const draft = reactive({ name: '', ...pick(DEFAULT_LABEL_FORM) });

const conflict = computed(() => {
    const hit = draft.name.trim() ? byName(draft.name) : null;
    return hit && hit.id !== editing.value?.id ? hit : null;
});
const nameError = computed(() => (editOpen.value && draft.name !== '' && !draft.name.trim() ? 'テンプレート名を入れてください' : ''));

const openCreate = () => {
    editing.value = null;
    Object.assign(draft, { name: '', ...pick(props.form) });
    editOpen.value = true;
};
const openEdit = (t) => {
    editing.value = t;
    Object.assign(draft, { name: t.name, ...pick(t) });
    editOpen.value = true;
};

const submit = async () => {
    saving.value = true;
    const body = { name: draft.name.trim(), ...pick(draft) };
    try {
        let saved;
        if (conflict.value) {
            // 同じ名前のテンプレートを上書きする（編集で名前を合わせたときは元のほうを消す）
            saved = await props.api.updateTemplate(conflict.value.id, body);
            if (editing.value) await props.api.deleteTemplate(editing.value.id);
            toast.success(`テンプレート「${body.name}」を上書きしました。`);
        } else if (editing.value) {
            saved = await props.api.updateTemplate(editing.value.id, body);
            toast.success(`テンプレート「${body.name}」を保存しました。`);
        } else {
            saved = await props.api.createTemplate(body);
            toast.success(`テンプレート「${body.name}」を追加しました。`);
        }
        await load();
        editOpen.value = false;
        select(templates.value.find((t) => t.id === saved.id));
    } catch (e) {
        if (e.code === 'name_taken') await load(); // ほかの人が同じ名前で先に作った → 一覧を読み直すと上書きの確認が出る
        toast.danger(`保存できませんでした：${e.message}`);
    } finally {
        saving.value = false;
    }
};

const overwriteCurrent = async () => {
    const t = current.value;
    if (!t || !window.confirm(`テンプレート「${t.name}」を、いまの入力で上書きしますか？`)) return;
    saving.value = true;
    try {
        await props.api.updateTemplate(t.id, { name: t.name, ...pick(props.form) });
        await load();
        toast.success(`テンプレート「${t.name}」を上書きしました。`);
    } catch (e) {
        toast.danger(`保存できませんでした：${e.message}`);
    } finally {
        saving.value = false;
    }
};

// ---- 削除 ----
const removeOpen = ref(false);
const removing = ref(null);
const askRemove = (t) => { removing.value = t; removeOpen.value = true; };
const remove = async () => {
    const t = removing.value;
    saving.value = true;
    try {
        await props.api.deleteTemplate(t.id);
        if (selectedId.value === t.id) selectedId.value = null;
        await load();
        removeOpen.value = false;
        toast.success(`テンプレート「${t.name}」を削除しました。`);
    } catch (e) {
        toast.danger(`削除できませんでした：${e.message}`);
    } finally {
        saving.value = false;
    }
};

onMounted(async () => {
    await load();
    if (templates.value.length) select(templates.value[0]);
});
</script>
