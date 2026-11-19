<template>
    <Head :title="incident.title" />

    <AdminLayout :breadcrumb="[{ label: '業務マニュアル' }, { label: '共有ノート', href: route('admin.incidents.index', { group: incident.group_key }) }, { label: incident.title }]">
        <!-- 見出し -->
        <div class="mb-6">
            <div class="flex flex-wrap items-center gap-1.5 mb-2">
                <StatusBadge :value="incident.status" />
                <KindBadge :value="incident.category" type="category" />
                <KindBadge :value="incident.kind" />
            </div>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h1 class="text-xl md:text-2xl font-semibold text-brand-text break-words min-w-0">{{ incident.title }}</h1>
                <div class="flex gap-2 shrink-0">
                    <UiButton v-if="canEdit" variant="ghost" :href="route('admin.incidents.edit', incident.id)">
                        <template #leading><Pencil :size="14" /></template>直す
                    </UiButton>
                    <UiButton variant="subtle" :href="route('admin.incidents.index', { group: incident.group_key })">一覧へ</UiButton>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-[1fr_280px] gap-6">
            <!-- 右の欄（PC）／上（スマホ）：ステータスと基本情報 -->
            <aside class="lg:order-2 space-y-4">
                <!-- ステータス -->
                <UiCard padding="sm">
                    <h2 class="text-xs text-brand-text-muted mb-2">ステータス</h2>
                    <ol class="flex items-center gap-1 mb-3" aria-label="ステータスの流れ">
                        <template v-for="(key, i) in statusKeys" :key="key">
                            <li
                                class="flex-1 text-center rounded-soft px-1 py-1.5 text-xs font-semibold border"
                                :class="key === incident.status
                                    ? CURRENT_CLASS[key]
                                    : (rank(key) < rank(incident.status) ? 'border-brand-border text-brand-text-muted bg-brand-surface-2' : 'border-dashed border-brand-border text-brand-text-subtle')"
                                :aria-current="key === incident.status ? 'step' : undefined"
                            >{{ STATUS[key].label }}</li>
                            <ChevronRight v-if="i < statusKeys.length - 1" :size="14" class="shrink-0 text-brand-text-subtle" />
                        </template>
                    </ol>
                    <p class="text-xs text-brand-text-muted mb-3">
                        {{ STATUS[incident.status]?.hint }}
                        <template v-if="incident.status_changed_at">
                            <br>{{ incident.status_changed_by || '（退職者）' }}・{{ dateTimeLabel(incident.status_changed_at) }}
                        </template>
                    </p>
                    <div class="flex flex-col gap-2">
                        <UiButton v-if="incident.status === 'open'" variant="subtle" :loading="statusForm.processing" @click="toAgenda">
                            <template #leading><MessagesSquare :size="14" /></template>議題にする
                        </UiButton>
                        <UiButton v-if="incident.status !== 'resolved'" variant="primary" @click="startDecision">
                            <template #leading><CircleCheck :size="14" /></template>決定事項を登録する
                        </UiButton>
                        <UiButton v-if="incident.status === 'resolved'" variant="ghost" size="sm" @click="openBack('agenda')">議題に戻す</UiButton>
                        <UiButton v-if="incident.status !== 'open'" variant="ghost" size="sm" @click="openBack('open')">未対応に戻す</UiButton>
                    </div>
                </UiCard>

                <UiCard padding="sm">
                    <dl class="grid grid-cols-2 lg:grid-cols-1 gap-x-4 gap-y-3 text-sm">
                        <div v-for="f in facts" :key="f.label">
                            <dt class="text-xs text-brand-text-muted">{{ f.label }}</dt>
                            <dd class="text-brand-text break-words">{{ f.value }}</dd>
                        </div>
                    </dl>
                </UiCard>
            </aside>

            <div class="lg:order-1 space-y-6 min-w-0">
                <!-- 番号つきの流れ -->
                <UiCard>
                    <ol class="space-y-6">
                        <li v-for="(s, i) in sections" :key="s.key" class="flex gap-3">
                            <span
                                class="shrink-0 w-7 h-7 rounded-full grid place-items-center text-sm font-semibold"
                                :class="isIdea ? 'bg-uguisu-100 text-uguisu-700 dark:bg-uguisu-900/40 dark:text-uguisu-200' : 'bg-brand-primary/10 text-brand-primary'"
                            >{{ i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-sm font-semibold text-brand-text mb-1">{{ s.label }}</h2>
                                <p v-if="s.value" class="text-sm text-brand-text whitespace-pre-wrap break-words leading-relaxed">{{ s.value }}</p>
                                <p v-else class="text-sm text-brand-text-subtle">（なし）</p>
                            </div>
                        </li>
                    </ol>
                </UiCard>

                <!-- 決定事項（あれば上にまとめて見せる） -->
                <UiCard v-if="decisions.length" class="border-uguisu-300 dark:border-uguisu-700">
                    <h2 class="flex items-center gap-1.5 text-sm font-semibold text-uguisu-700 dark:text-uguisu-200 mb-3">
                        <CircleCheck :size="16" />{{ incident.status === 'resolved' ? '決定事項' : 'これまでの決定事項' }}
                    </h2>
                    <ul class="space-y-3">
                        <li v-for="d in decisions" :key="d.id">
                            <p class="text-sm text-brand-text whitespace-pre-wrap break-words leading-relaxed">{{ d.body }}</p>
                            <p class="text-xs text-brand-text-muted mt-1">{{ d.user || '（退職者）' }}・{{ dateTimeLabel(d.created_at) }}</p>
                        </li>
                    </ul>
                </UiCard>

                <!-- 対応の記録 -->
                <section>
                    <h2 class="text-base font-semibold text-brand-text mb-3">対応の記録 <span class="text-xs font-normal text-brand-text-muted">{{ incident.comments.length }}件</span></h2>
                    <p v-if="!incident.comments.length" class="text-sm text-brand-text-muted mb-4">
                        その後どうなったか、会議で話し合ったことなどを書き足せます。書いた人と日時は自動で残ります。
                    </p>
                    <ul v-else class="space-y-3 mb-5">
                        <li v-for="c in incident.comments" :key="c.id">
                            <!-- 状態の変更（自動で残る記録） -->
                            <div v-if="c.type === 'status'" class="flex items-start gap-2 text-xs text-brand-text-muted pl-1">
                                <ArrowRightLeft :size="14" class="shrink-0 mt-0.5" />
                                <div class="min-w-0">
                                    <span>{{ c.user || '（退職者）' }}が</span>
                                    <StatusBadge :value="c.to_status" size="sm" class="mx-1" />
                                    <span>にしました・{{ dateTimeLabel(c.created_at) }}</span>
                                    <p v-if="c.body" class="mt-0.5 text-brand-text whitespace-pre-wrap break-words">理由：{{ c.body }}</p>
                                </div>
                            </div>

                            <!-- 対応記録・決定事項 -->
                            <div v-else class="flex gap-2.5">
                                <span class="shrink-0 w-8 h-8 rounded-full bg-brand-surface-2 text-brand-text-muted grid place-items-center text-xs font-semibold">
                                    {{ (c.user || '？').slice(0, 1) }}
                                </span>
                                <div class="min-w-0 flex-1 max-w-[640px]">
                                    <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-brand-text-muted mb-0.5">
                                        <UiBadge :variant="RECORD_TYPE[c.type]?.variant || 'neutral'" size="sm">{{ RECORD_TYPE[c.type]?.label || '対応記録' }}</UiBadge>
                                        <span>{{ c.user || '（退職者）' }}・{{ dateTimeLabel(c.created_at) }}</span>
                                        <span v-if="c.edited_at">（{{ dateTimeLabel(c.edited_at) }}に直しました）</span>
                                        <button v-if="c.editable && editingId !== c.id" type="button" class="text-brand-primary hover:underline ml-1" @click="startEdit(c)">直す</button>
                                    </div>

                                    <form v-if="editingId === c.id" class="space-y-2" @submit.prevent="saveEdit(c)">
                                        <UiTextarea v-model="editForm.body" :rows="3" :error="!!editForm.errors.body" />
                                        <p v-if="editForm.errors.body" class="text-xs text-brand-danger">{{ editForm.errors.body }}</p>
                                        <p class="text-xs text-brand-text-muted">直す前の文も記録に残ります。直せるのは今日のうちだけです。</p>
                                        <div class="flex justify-end gap-2">
                                            <UiButton variant="ghost" size="sm" @click="editingId = null">やめる</UiButton>
                                            <UiButton type="submit" variant="primary" size="sm" :loading="editForm.processing" :disabled="!editForm.body.trim()">保存する</UiButton>
                                        </div>
                                    </form>
                                    <div
                                        v-else
                                        class="rounded-soft-lg rounded-tl-sm border px-3.5 py-2.5 text-sm text-brand-text whitespace-pre-wrap break-words"
                                        :class="c.type === 'decision'
                                            ? 'bg-uguisu-50 border-uguisu-300 dark:bg-uguisu-900/30 dark:border-uguisu-700'
                                            : 'bg-brand-surface border-brand-border'"
                                    >{{ c.body }}</div>

                                    <details v-if="c.revisions.length" class="mt-1 text-xs text-brand-text-muted">
                                        <summary class="cursor-pointer select-none">直す前の文を見る（{{ c.revisions.length }}件）</summary>
                                        <ul class="mt-1 space-y-1.5 border-l-2 border-brand-border pl-3">
                                            <li v-for="(r, i) in c.revisions" :key="i">
                                                <div>{{ dateTimeLabel(r.created_at) }}に{{ r.editor || '（退職者）' }}が直す前：</div>
                                                <div class="text-brand-text whitespace-pre-wrap break-words line-through decoration-brand-text-subtle">{{ r.body }}</div>
                                            </li>
                                        </ul>
                                    </details>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <!-- 書き足す -->
                    <div ref="composer" class="rounded-soft-lg border border-brand-border bg-brand-surface p-3 space-y-2">
                        <div class="flex gap-1 p-0.5 rounded-soft bg-brand-surface-2 w-full sm:w-auto sm:inline-flex" role="tablist">
                            <button
                                v-for="m in modes"
                                :key="m.id"
                                type="button"
                                role="tab"
                                :aria-selected="mode === m.id"
                                class="flex-1 sm:flex-none px-3 min-h-[40px] md:min-h-[34px] rounded-soft text-sm font-medium transition-colors"
                                :class="mode === m.id ? 'bg-brand-surface text-brand-text shadow-sm' : 'text-brand-text-muted hover:text-brand-text'"
                                @click="mode = m.id"
                            >{{ m.label }}</button>
                        </div>
                        <p class="text-xs text-brand-text-muted">
                            <template v-if="mode === 'record'">ステータスは「{{ STATUS[incident.status]?.label }}」のまま変わりません。</template>
                            <template v-else-if="incident.status === 'resolved'">決定事項を追加します（すでに対応済みです）。</template>
                            <template v-else>登録すると、ステータスが「対応済み」になります。</template>
                            書いた人と日時は自動で残ります。
                        </p>
                        <UiTextarea
                            
                            v-model="comment.body"
                            :rows="3"
                            :placeholder="mode === 'record' ? '例：店長と相談し、前日に小物をチェックすることにした。' : '例：前撮りの前日に、担当者が小物セットを契約書と照らし合わせて確認する。'"
                            :error="!!comment.errors.body"
                        />
                        <p v-if="comment.errors.body" class="text-xs text-brand-danger">{{ comment.errors.body }}</p>
                        <div class="flex justify-end">
                            <UiButton v-if="mode === 'record'" type="button" variant="primary" :loading="comment.processing" :disabled="!comment.body.trim()" @click="addRecord">対応記録を追加</UiButton>
                            <UiButton v-else type="button" variant="primary" :loading="comment.processing" :disabled="!comment.body.trim()" @click="confirmDecision = true">
                                {{ incident.status === 'resolved' ? '決定事項を追加' : '決定事項を登録して対応済みにする' }}
                            </UiButton>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- 決定事項の確認 -->
        <UiDialog v-model:open="confirmDecision" :title="incident.status === 'resolved' ? '決定事項を追加しますか？' : '決定事項を登録して、対応済みにしますか？'" size="md">
            <p class="text-sm text-brand-text-muted mb-2">登録した人（あなた）と日時が記録に残ります。</p>
            <div class="rounded-soft border border-uguisu-300 bg-uguisu-50 dark:bg-uguisu-900/30 dark:border-uguisu-700 px-3 py-2 text-sm text-brand-text whitespace-pre-wrap break-words">{{ comment.body }}</div>
            <template #footer>
                <UiButton variant="ghost" @click="confirmDecision = false">戻る</UiButton>
                <UiButton variant="primary" :loading="comment.processing" @click="addDecision">登録する</UiButton>
            </template>
        </UiDialog>

        <!-- 前の状態に戻す（理由が要る） -->
        <UiDialog v-model:open="backDialog" :title="`「${STATUS[backTo]?.label}」に戻しますか？`" size="md">
            <p class="text-sm text-brand-text-muted mb-2">戻す理由を書いてください。理由・あなたの名前・日時が記録に残ります。</p>
            <UiTextarea v-model="statusForm.reason" :rows="3" placeholder="例：同じことがまた起きたので、もう一度話し合う。" :error="!!statusForm.errors.reason" />
            <p v-if="statusForm.errors.reason" class="text-xs text-brand-danger mt-1">{{ statusForm.errors.reason }}</p>
            <template #footer>
                <UiButton variant="ghost" @click="backDialog = false">やめる</UiButton>
                <UiButton variant="primary" :loading="statusForm.processing" :disabled="!statusForm.reason.trim()" @click="submitBack">戻す</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiBadge, UiButton, UiCard, UiDialog, UiTextarea } from '@/Components/UI';
import KindBadge from '@/Components/Incident/KindBadge.vue';
import StatusBadge from '@/Components/Incident/StatusBadge.vue';
import { ArrowRightLeft, ChevronRight, CircleCheck, MessagesSquare, Pencil } from 'lucide-vue-next';
import { flowFor, dateLabel, dateTimeLabel, STATUS, RECORD_TYPE } from '@/composables/useIncidentFlow.js';

const props = defineProps({
    incident: Object,
    statuses: Object,
    canEdit: Boolean,
    groupName: String,
});

const flow = computed(() => flowFor(props.incident.kind));
const isIdea = computed(() => !flow.value.action);

const statusKeys = Object.keys(STATUS);
const rank = (k) => statusKeys.indexOf(k);
const CURRENT_CLASS = {
    open:     'border-akane-300 bg-akane-50 text-akane-700 dark:bg-akane-900/40 dark:text-akane-200 dark:border-akane-700',
    agenda:   'border-natane-300 bg-natane-50 text-natane-700 dark:bg-natane-900/40 dark:text-natane-200 dark:border-natane-700',
    resolved: 'border-uguisu-300 bg-uguisu-50 text-uguisu-700 dark:bg-uguisu-900/40 dark:text-uguisu-200 dark:border-uguisu-700',
};

const sections = computed(() => {
    const f = flow.value;
    const list = [{ key: 'what', label: f.what.nm, value: props.incident.what }];
    if (f.action) list.push({ key: 'action', label: f.action.nm, value: props.incident.action });
    list.push({ key: 'consult', label: f.consult.nm, value: props.incident.consult });
    return list;
});

const facts = computed(() => [
    { label: '発生日', value: dateLabel(props.incident.occurred_on) },
    { label: '店舗', value: props.incident.shop_name },
    { label: '記録した人', value: props.incident.creator || '（退職者）' },
    { label: '記録した日時', value: dateTimeLabel(props.incident.created_at) },
    { label: 'グループ', value: props.groupName },
]);

const decisions = computed(() => props.incident.comments.filter((c) => c.type === 'decision'));

/* 書き足す（対応記録／決定事項） */
const modes = [
    { id: 'record', label: '対応記録' },
    { id: 'decision', label: '決定事項' },
];
const mode = ref('record');
const composer = ref(null);
const comment = useForm({ body: '' });
const confirmDecision = ref(false);

const startDecision = async () => {
    mode.value = 'decision';
    await nextTick();
    composer.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    composer.value?.querySelector('textarea')?.focus();
};

const addRecord = () => {
    comment.post(route('admin.incidents.comments.store', props.incident.id), {
        preserveScroll: true,
        onSuccess: () => comment.reset('body'),
    });
};

const addDecision = () => {
    comment.post(route('admin.incidents.decisions.store', props.incident.id), {
        preserveScroll: true,
        onSuccess: () => {
            comment.reset('body');
            mode.value = 'record';
        },
        onFinish: () => { confirmDecision.value = false; },
    });
};

/* ステータス */
const statusForm = useForm({ status: '', reason: '' });
const backDialog = ref(false);
const backTo = ref('open');

const toAgenda = () => {
    statusForm.status = 'agenda';
    statusForm.reason = '';
    statusForm.put(route('admin.incidents.status.update', props.incident.id), { preserveScroll: true });
};

const openBack = (to) => {
    backTo.value = to;
    statusForm.clearErrors();
    statusForm.reason = '';
    backDialog.value = true;
};

const submitBack = () => {
    statusForm.status = backTo.value;
    statusForm.put(route('admin.incidents.status.update', props.incident.id), {
        preserveScroll: true,
        onSuccess: () => { backDialog.value = false; },
    });
};

/* 記録を直す（本人が当日だけ） */
const editingId = ref(null);
const editForm = useForm({ body: '' });
const startEdit = (c) => {
    editingId.value = c.id;
    editForm.clearErrors();
    editForm.body = c.body;
};
const saveEdit = (c) => {
    editForm.put(route('admin.incidents.comments.update', [props.incident.id, c.id]), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
};
</script>
