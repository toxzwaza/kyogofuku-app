<template>
    <Head title="ラベル発行" />

    <AdminLayout :breadcrumb="[{ label: '名簿' }, { label: 'ラベル発行' }]">
        <UiPageHeader title="ラベル発行" description="名簿一覧で選んだ人の宛名ラベル（A4・1枚10面／A-One 66732 相当）を作ります。">
            <template #actions>
                <UiButton variant="ghost" :href="route('admin.meibo.index')">
                    <template #leading><ArrowLeft :size="14" /></template>
                    名簿一覧で選び直す
                </UiButton>
            </template>
        </UiPageHeader>

        <DummyNotice :show="api.isDummy" />

        <!-- 対象 -->
        <UiCard padding="sm" class="mb-4">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-xs text-brand-text-muted">対象</span>
                    <span class="font-serif text-3xl leading-none text-brand-accent tabular-nums">{{ num(count) }}</span>
                    <span class="text-sm text-brand-text-muted">件</span>
                </div>
                <div v-if="count" class="text-xs text-brand-text-muted">
                    用紙 {{ num(sheets) }} 枚<template v-if="r11Count">・うち R11 成人名簿の人 {{ num(r11Count) }} 件</template>
                </div>
                <UiButton v-if="count" size="sm" variant="subtle" class="ml-auto" @click="selection.clear()">選択を解除</UiButton>
            </div>
        </UiCard>

        <div v-if="!count" class="rounded-soft-lg border border-dashed border-brand-border-strong bg-brand-surface p-10 text-center">
            <Printer :size="32" class="mx-auto text-brand-text-subtle" />
            <p class="mt-3 text-sm text-brand-text-muted">ラベルを作る人がまだ選ばれていません。名簿一覧でチェックを付けてください。</p>
            <UiButton class="mt-4" variant="primary" :href="route('admin.meibo.index')">名簿一覧へ</UiButton>
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-[minmax(0,26rem)_1fr] gap-6 items-start">
            <!-- 設定 -->
            <div class="space-y-4">
                <UiCard padding="sm">
                    <div class="flex items-end gap-2">
                        <UiFormField label="テンプレート" class="flex-1">
                            <UiSelect v-model="templateId" :options="templateOptions" placeholder="選んでください" />
                        </UiFormField>
                        <UiButton variant="ghost" @click="openSave">
                            <template #leading><Save :size="14" /></template>
                            保存
                        </UiButton>
                        <UiButton v-if="templateId" variant="ghost" aria-label="このテンプレートを削除" title="このテンプレートを削除" @click="removeTemplate">
                            <Trash2 :size="14" />
                        </UiButton>
                    </div>
                    <p class="mt-2 text-xs text-brand-text-subtle">福井発送・岡山発送など、差出人と宛名の決まった組み合わせを呼び出せます。</p>
                </UiCard>

                <UiCard padding="sm">
                    <h2 class="font-serif text-base mb-3">差出人</h2>
                    <div class="grid grid-cols-[7.5rem_1fr] gap-3">
                        <UiFormField label="郵便番号">
                            <UiInput v-model="form.sender_zip" placeholder="918-8015" />
                        </UiFormField>
                        <UiFormField label="会社名">
                            <UiInput v-model="form.sender_company" />
                        </UiFormField>
                        <UiFormField label="住所" class="col-span-2">
                            <UiInput v-model="form.sender_address" placeholder="福井県福井市花堂南1-2-1" />
                        </UiFormField>
                    </div>
                    <p v-if="!form.sender_zip || !form.sender_address" class="mt-2 text-xs text-brand-warning">差出人の郵便番号と住所を入れてください。</p>
                </UiCard>

                <UiCard padding="sm">
                    <h2 class="font-serif text-base mb-3">宛名</h2>
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <label
                            v-for="o in ADDRESSEE"
                            :key="o.value"
                            :class="[
                                'rounded-soft border px-3 py-2 cursor-pointer transition-colors',
                                form.addressee === o.value ? 'border-brand-primary bg-brand-surface-2' : 'border-brand-border hover:bg-brand-surface-2',
                            ]"
                        >
                            <input v-model="form.addressee" type="radio" :value="o.value" class="sr-only" />
                            <div class="text-sm font-medium">{{ o.label }}</div>
                            <div class="text-[11px] text-brand-text-muted">{{ o.hint }}</div>
                        </label>
                    </div>
                    <label class="flex items-center gap-2 text-sm mb-3 cursor-pointer">
                        <input v-model="form.with_family" type="checkbox" class="rounded border-brand-border-strong text-brand-primary focus:ring-brand-primary" />
                        氏名の下に「ご家族様」を印字する
                    </label>
                    <UiFormField label="担当名（任意・右下に印字）">
                        <UiInput v-model="form.tantou" placeholder="例：村上" />
                    </UiFormField>
                    <p v-if="form.addressee === 'guardian' && noGuardianCount" class="mt-2 text-xs text-brand-text-muted">
                        見本の {{ noGuardianCount }} 件は保護者名がないため、本人宛で印字します。
                    </p>
                </UiCard>

                <UiButton variant="primary" size="lg" block :loading="generating" :disabled="!canGenerate" @click="generate">
                    <template #leading><FileDown :size="16" /></template>
                    ラベルPDFを作る（{{ num(count) }}件）
                </UiButton>
            </div>

            <!-- 見本 -->
            <UiCard padding="sm">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <h2 class="font-serif text-base">見本（1枚目）</h2>
                    <span class="text-xs text-brand-text-muted">選んだ順の先頭 {{ previewRows.length }} 件。印刷の位置は PDF で確かめてください。</span>
                </div>
                <div class="mx-auto max-w-[560px] rounded-soft border border-brand-border bg-sumi-100 dark:bg-sumi-800 p-2 sm:p-4">
                    <div class="bg-white shadow-soft" :style="{ aspectRatio: '210 / 297', padding: `${(21.2 / 210) * 100}% ${(18.6 / 210) * 100}% 0` }">
                        <div class="grid grid-cols-2">
                            <div v-for="i in 10" :key="i" class="outline outline-1 outline-dashed outline-sumi-200">
                                <LabelFace
                                    :person="previewRows[i - 1] || null"
                                    :sender="{ zip: form.sender_zip, address: form.sender_address, company: form.sender_company }"
                                    :addressee="form.addressee"
                                    :with-family="form.with_family"
                                    :tantou="form.tantou"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </UiCard>
        </div>

        <!-- テンプレート保存 -->
        <UiDialog v-model:open="saveOpen" title="テンプレートを保存" size="sm">
            <UiFormField label="テンプレート名" hint="同じ名前があれば上書きします。">
                <UiInput v-model="saveName" placeholder="例：福井発送" />
            </UiFormField>
            <template #footer>
                <UiButton variant="ghost" @click="saveOpen = false">キャンセル</UiButton>
                <UiButton variant="primary" :loading="saving" :disabled="!saveName.trim()" @click="saveTemplate">保存</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiPageHeader, UiButton, UiCard, UiSelect, UiInput, UiFormField, UiDialog } from '@/Components/UI';
import DummyNotice from '@/Components/Meibo/DummyNotice.vue';
import LabelFace from '@/Components/Meibo/LabelFace.vue';
import { ArrowLeft, Printer, Save, FileDown, Trash2 } from 'lucide-vue-next';
import { useMeiboApi, num, hasR11, DEFAULT_LABEL_FORM } from '@/services/meibo';
import { useMeiboSelection } from '@/composables/useMeiboSelection.js';
import { useToast } from '@/composables/useToast.js';

defineProps({
    dataSource: { type: String, default: 'dummy' },
});

const api = useMeiboApi();
const selection = useMeiboSelection();
const toast = useToast();

const ADDRESSEE = [
    { value: 'self', label: '本人宛', hint: '本人氏名＋様' },
    { value: 'guardian', label: '保護者宛', hint: '保護者氏名＋様' },
];
const TEMPLATE_FIELDS = Object.keys(DEFAULT_LABEL_FORM);

const form = reactive({ ...DEFAULT_LABEL_FORM });
const templates = ref([]);
const templateId = ref('');
const previewRows = ref([]);
const r11Count = ref(0);
const generating = ref(false);

const count = computed(() => selection.count.value);
const sheets = computed(() => Math.ceil(count.value / 10));
const templateOptions = computed(() => templates.value.map((t) => ({ value: String(t.id), label: t.name })));
const noGuardianCount = computed(() => previewRows.value.filter((r) => !r.guardian_name).length);
const canGenerate = computed(() => count.value > 0 && form.sender_zip && form.sender_address && form.sender_company);

watch(templateId, (id) => {
    const t = templates.value.find((x) => String(x.id) === String(id));
    if (!t) return;
    TEMPLATE_FIELDS.forEach((k) => { form[k] = k === 'with_family' ? !!t[k] : (t[k] ?? ''); });
});

const loadTemplates = async () => {
    try { templates.value = await api.templates(); } catch { templates.value = []; }
};

const loadPreview = async () => {
    const ids = selection.list.value;
    if (!ids.length) { previewRows.value = []; r11Count.value = 0; return; }
    try {
        previewRows.value = await api.personsByIds(ids, 10);
    } catch (e) {
        toast.danger(`見本を読み込めませんでした：${e.message}`);
    }
    // R11 の件数は見本ではなく対象全体で数える（ダミーは全員を引ける。本物では API で数を返す予定）
    try {
        const all = await api.personsByIds(ids, ids.length);
        r11Count.value = all.filter((r) => hasR11(r.source_versions)).length;
    } catch { r11Count.value = 0; }
};

watch(() => selection.count.value, loadPreview);

const saveOpen = ref(false);
const saveName = ref('');
const saving = ref(false);
const openSave = () => {
    saveName.value = templates.value.find((t) => String(t.id) === String(templateId.value))?.name || '';
    saveOpen.value = true;
};
const saveTemplate = async () => {
    saving.value = true;
    try {
        const name = saveName.value.trim();
        const payload = Object.fromEntries(TEMPLATE_FIELDS.map((k) => [k, form[k]]));
        const saved = await api.saveTemplate({ name, ...payload });
        await loadTemplates();
        templateId.value = String(saved.id);
        saveOpen.value = false;
        toast.success(`テンプレート「${name}」を保存しました。`);
    } catch (e) {
        toast.danger(`保存できませんでした：${e.message}`);
    } finally {
        saving.value = false;
    }
};

const removeTemplate = async () => {
    const t = templates.value.find((x) => String(x.id) === String(templateId.value));
    if (!t || !window.confirm(`テンプレート「${t.name}」を削除しますか？`)) return;
    try {
        await api.deleteTemplate(t.id);
        templateId.value = '';
        await loadTemplates();
        toast.success(`テンプレート「${t.name}」を削除しました。`);
    } catch (e) {
        toast.danger(`削除できませんでした：${e.message}`);
    }
};

const generate = async () => {
    generating.value = true;
    try {
        const { blob, count: made } = await api.labels({ ids: selection.list.value, ...form });
        if (!blob) {
            toast.info(`ダミーのため PDF は作りません（${num(made)}件分の指定は受け付けました）。本物の名簿につないだあとに作れます。`, { duration: 6000 });
            return;
        }
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `labels_${made}件.pdf`;
        a.click();
        URL.revokeObjectURL(url);
        toast.success(`${num(made)}件のラベルPDFを作りました。`);
    } catch (e) {
        toast.danger(`ラベルを作れませんでした：${e.message}`);
    } finally {
        generating.value = false;
    }
};

onMounted(async () => {
    await loadTemplates();
    if (templates.value.length) templateId.value = String(templates.value[0].id);
    loadPreview();
});
</script>
