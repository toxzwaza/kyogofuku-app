<template>
    <Head title="記録を直す" />

    <AdminLayout :breadcrumb="[
        { label: '業務マニュアル' },
        { label: '共有ノート', href: route('admin.incidents.index') },
        { label: incident.title, href: route('admin.incidents.show', incident.id) },
        { label: '直す' },
    ]">
        <UiPageHeader title="記録を直す" description="直したところだけ変えて「保存する」を押してください。" />

        <form class="max-w-3xl space-y-6" @submit.prevent="submit">
            <UiCard>
                <div class="space-y-6">
                    <UiFormField label="発生日" required :error="form.errors.occurred_on">
                        <input
                            v-model="form.occurred_on"
                            type="date"
                            :max="today"
                            name="occurred_on"
                            class="block w-full sm:w-56 h-11 md:h-9 px-3 text-base md:text-sm rounded-soft border bg-brand-surface text-brand-text focus:ring-2 focus:ring-brand-primary focus:border-brand-primary"
                            :class="form.errors.occurred_on ? 'border-brand-danger' : 'border-brand-border-strong'"
                        />
                    </UiFormField>
                    <UiFormField label="店舗" required :error="form.errors.shop_name">
                        <ChipPicker v-model="form.shop_name" :items="shops" :error="!!form.errors.shop_name" name="shop_name" />
                    </UiFormField>
                    <UiFormField label="区分" required :error="form.errors.category">
                        <ChipPicker v-model="form.category" v-model:other="form.category_other" :items="categories" with-other :error="!!form.errors.category" name="category" />
                    </UiFormField>
                    <UiFormField label="種類" required :error="form.errors.kind">
                        <ChipPicker v-model="form.kind" v-model:other="form.kind_other" :items="kinds" with-other :error="!!form.errors.kind" name="kind" />
                    </UiFormField>
                    <UiFormField label="件名" required :error="form.errors.title">
                        <UiInput v-model="form.title" :error="!!form.errors.title" />
                    </UiFormField>
                </div>
            </UiCard>

            <UiCard>
                <div class="space-y-6">
                    <UiFormField :label="flow.what.nm" required :error="form.errors.what">
                        <UiTextarea v-model="form.what" :rows="6" :placeholder="flow.what.ph" :error="!!form.errors.what" />
                    </UiFormField>
                    <UiFormField
                        v-if="flow.action"
                        :label="flow.action.nm"
                        :required="!flow.action.opt"
                        :hint="flow.action.opt ? '任意' : 'まだ何もしていなければ「未対応」と書いてください。'"
                        :error="form.errors.action"
                    >
                        <UiTextarea v-model="form.action" :rows="4" :placeholder="flow.action.ph" :error="!!form.errors.action" />
                    </UiFormField>
                    <UiFormField :label="flow.consult.nm" hint="任意" :error="form.errors.consult">
                        <UiTextarea v-model="form.consult" :rows="4" :placeholder="flow.consult.ph" :error="!!form.errors.consult" />
                    </UiFormField>
                    <p v-if="!flow.action && incident.action" class="text-xs text-brand-text-muted">
                        ※ 種類を「気づき・提案」にすると、「その場でした対応」は消えます。
                    </p>
                </div>
            </UiCard>

            <div class="flex justify-between gap-3">
                <UiButton variant="ghost" :href="route('admin.incidents.show', incident.id)">やめる</UiButton>
                <UiButton type="submit" variant="primary" :loading="form.processing">保存する</UiButton>
            </div>
        </form>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiPageHeader, UiButton, UiCard, UiFormField, UiInput, UiTextarea } from '@/Components/UI';
import ChipPicker from '@/Components/Incident/ChipPicker.vue';
import { flowFor, finalValue, splitOther } from '@/composables/useIncidentFlow.js';

const props = defineProps({
    incident: Object,
    shops: { type: Array, default: () => [] },
    today: String,
    categories: Array,
    kinds: Array,
});

const [category, categoryOther] = splitOther(props.incident.category, props.categories);
const [kind, kindOther] = splitOther(props.incident.kind, props.kinds);

const form = useForm({
    occurred_on: props.incident.occurred_on,
    shop_name: props.incident.shop_name,
    category,
    category_other: categoryOther,
    kind,
    kind_other: kindOther,
    title: props.incident.title,
    what: props.incident.what,
    action: props.incident.action || '',
    consult: props.incident.consult || '',
});

const flow = computed(() => flowFor(finalValue(form.kind, form.kind_other)));

const submit = () => {
    form
        .transform((d) => ({
            occurred_on: d.occurred_on,
            shop_name: d.shop_name,
            category: finalValue(d.category, d.category_other),
            kind: finalValue(d.kind, d.kind_other),
            title: d.title,
            what: d.what,
            action: flow.value.action ? d.action : '',
            consult: d.consult,
        }))
        .put(route('admin.incidents.update', props.incident.id));
};
</script>
