<template>
    <Head :title="incident.title" />

    <AdminLayout :breadcrumb="[{ label: '業務マニュアル' }, { label: '共有ノート', href: route('admin.incidents.index', { group: incident.group_key }) }, { label: incident.title }]">
        <!-- 見出し -->
        <div class="mb-6">
            <div class="flex flex-wrap items-center gap-1.5 mb-2">
                <KindBadge :value="incident.category" type="category" />
                <KindBadge :value="incident.kind" />
                <UiBadge v-if="notYet" variant="danger">まだ対応していません</UiBadge>
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
            <!-- 基本情報（PC は右の欄、スマホは上に2列） -->
            <aside class="lg:order-2">
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

                <!-- 経過 -->
                <section>
                    <h2 class="text-base font-semibold text-brand-text mb-3">経過 <span class="text-xs font-normal text-brand-text-muted">{{ incident.comments.length }}件</span></h2>
                    <p v-if="!incident.comments.length" class="text-sm text-brand-text-muted mb-4">
                        その後どうなったか、話し合ったことなどを書き足せます。
                    </p>
                    <ul v-else class="space-y-3 mb-4">
                        <li v-for="c in incident.comments" :key="c.id" class="flex gap-2.5">
                            <span class="shrink-0 w-8 h-8 rounded-full bg-brand-surface-2 text-brand-text-muted grid place-items-center text-xs font-semibold">
                                {{ (c.user || '？').slice(0, 1) }}
                            </span>
                            <div class="min-w-0 max-w-[640px]">
                                <div class="text-xs text-brand-text-muted mb-0.5">{{ c.user || '（退職者）' }}・{{ dateTimeLabel(c.created_at) }}</div>
                                <div class="rounded-soft-lg rounded-tl-sm bg-brand-surface border border-brand-border px-3.5 py-2.5 text-sm text-brand-text whitespace-pre-wrap break-words">{{ c.body }}</div>
                            </div>
                        </li>
                    </ul>
                    <form class="space-y-2" @submit.prevent="addComment">
                        <UiTextarea v-model="comment.body" :rows="3" placeholder="例：店長と相談し、前日に小物をチェックすることにした。" :error="!!comment.errors.body" />
                        <p v-if="comment.errors.body" class="text-xs text-brand-danger">{{ comment.errors.body }}</p>
                        <div class="flex justify-end">
                            <UiButton type="submit" variant="primary" :loading="comment.processing" :disabled="!comment.body.trim()">経過を追加</UiButton>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiBadge, UiButton, UiCard, UiTextarea } from '@/Components/UI';
import KindBadge from '@/Components/Incident/KindBadge.vue';
import { Pencil } from 'lucide-vue-next';
import { flowFor, dateLabel, dateTimeLabel, isNotYet } from '@/composables/useIncidentFlow.js';

const props = defineProps({
    incident: Object,
    canEdit: Boolean,
    groupName: String,
});

const flow = computed(() => flowFor(props.incident.kind));
const isIdea = computed(() => !flow.value.action);
const notYet = computed(() => isNotYet(props.incident.kind, props.incident.action));

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

const comment = useForm({ body: '' });
const addComment = () => {
    comment.post(route('admin.incidents.comments.store', props.incident.id), {
        preserveScroll: true,
        onSuccess: () => comment.reset('body'),
    });
};
</script>
