<script setup>
import { reactive, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Plus, Trash2, AlertCircle } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    UiCard, UiButton, UiPageHeader,
    UiInput, UiSelect, UiFormField, UiAlert,
} from '@/Components/UI';
import { formatDateInputValueJa } from '@/utils/dateFormat';

const props = defineProps({
    record: Object,
    userShops: Array,
});

const isEdit = computed(() => !!props.record);
const pageTitle = computed(() => (isEdit.value ? '仮登録の編集' : '勤怠の仮登録'));

function isoToTime(iso) {
    if (!iso) return '';
    const s = String(iso);
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(s)) return s.slice(11, 16);
    if (/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}/.test(s)) return s.slice(11, 16);
    return s.length >= 5 ? s.slice(0, 5) : '';
}

const form = reactive({
    date: props.record?.date != null && props.record.date !== ''
        ? formatDateInputValueJa(props.record.date)
        : formatDateInputValueJa(new Date().toISOString()),
    shop_id: props.record?.shop_id ?? (props.userShops?.[0]?.id ?? ''),
    clock_in_time: isoToTime(props.record?.clock_in_at),
    clock_out_time: isoToTime(props.record?.clock_out_at),
    is_manual: false,
    breaks: props.record?.breaks?.length
        ? props.record.breaks.map((b) => ({
            start_time: isoToTime(b.start_at),
            end_time: isoToTime(b.end_at),
        }))
        : [],
    processing: false,
    errors: {},
});

const shopOptions = computed(() => (props.userShops || []).map((s) => ({ value: s.id, label: s.name })));

function addBreak() {
    form.breaks.push({ start_time: '', end_time: '' });
}

function removeBreak(i) {
    form.breaks.splice(i, 1);
}

function buildDatetime(dateStr, timeStr) {
    if (!dateStr || !timeStr) return null;
    return `${dateStr}T${timeStr}:00`;
}

function submit() {
    const clockInAt = buildDatetime(form.date, form.clock_in_time);
    const clockOutAt = buildDatetime(form.date, form.clock_out_time);
    const breaks = form.breaks
        .filter((b) => b.start_time)
        .map((b) => ({
            start_at: buildDatetime(form.date, b.start_time),
            end_at: b.end_time ? buildDatetime(form.date, b.end_time) : null,
        }));
    const payload = {
        date: form.date,
        shop_id: form.shop_id,
        clock_in_at: clockInAt,
        clock_out_at: clockOutAt,
        is_manual: form.is_manual,
        breaks,
    };
    form.processing = true;
    form.errors = {};
    if (props.record) {
        router.put(route('attendance.provisional.update', props.record.id), {
            clock_in_at: payload.clock_in_at,
            clock_out_at: payload.clock_out_at,
            breaks: payload.breaks,
        }, {
            onError: (errors) => { form.errors = errors; },
            onFinish: () => { form.processing = false; },
        });
    } else {
        router.post(route('attendance.provisional.store'), payload, {
            preserveScroll: false,
            preserveState: false,
            onError: (errors) => { form.errors = errors; },
            onFinish: () => { form.processing = false; },
        });
    }
}
</script>

<template>
    <Head :title="pageTitle" />
    <AdminLayout :breadcrumb="[{ label: '勤怠' }, { label: '勤怠履歴', href: route('attendance.history') }, { label: pageTitle }]">

        <UiPageHeader
            :title="pageTitle"
            :description="isEdit ? '保存済みの仮登録を編集します。' : '日付を指定して勤怠を仮登録します。'"
        >
            <template #actions>
                <UiButton variant="ghost" :href="route('attendance.history')">勤怠履歴へ</UiButton>
            </template>
        </UiPageHeader>

        <UiAlert v-if="Object.keys(form.errors).length" variant="danger" title="入力内容を確認してください" class="mb-4">
            <ul class="list-disc list-inside text-sm space-y-0.5">
                <li v-for="(msg, k) in form.errors" :key="k">{{ msg }}</li>
            </ul>
        </UiAlert>

        <UiCard variant="default" padding="none" class="max-w-3xl">
            <template #header>
                <h2 class="font-serif text-base text-brand-text">基本情報</h2>
            </template>

            <form @submit.prevent="submit" class="p-5 space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <UiFormField label="日付" required :error="form.errors.date">
                        <UiInput
                            v-model="form.date"
                            type="date"
                            :disabled="isEdit"
                            :error="!!form.errors.date"
                        />
                    </UiFormField>
                    <UiFormField label="店舗" required :error="form.errors.shop_id">
                        <UiSelect
                            v-model="form.shop_id"
                            :options="shopOptions"
                            placeholder="選択してください"
                            :disabled="isEdit"
                            :error="!!form.errors.shop_id"
                        />
                    </UiFormField>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <UiFormField
                        label="出勤時刻"
                        :error="form.errors.clock_in_at"
                        hint="日付は上で選択した日付になります"
                    >
                        <UiInput v-model="form.clock_in_time" type="time" :error="!!form.errors.clock_in_at" />
                    </UiFormField>
                    <UiFormField
                        label="退勤時刻"
                        :error="form.errors.clock_out_at"
                        hint="日付は上で選択した日付になります"
                    >
                        <UiInput v-model="form.clock_out_time" type="time" :error="!!form.errors.clock_out_at" />
                    </UiFormField>
                </div>

                <div v-if="!isEdit">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input
                            v-model="form.is_manual"
                            type="checkbox"
                            class="mt-0.5 rounded border-brand-border-strong text-brand-primary focus:ring-brand-primary"
                        />
                        <span class="text-sm text-brand-text">
                            <span class="font-medium">確定申請として登録</span>
                            <span class="block text-xs text-brand-text-muted mt-0.5">
                                申請済となり、管理者の承認が必要です。
                            </span>
                        </span>
                    </label>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-xs font-medium text-brand-text-muted">休憩（複数可）</label>
                        <UiButton type="button" variant="link" size="sm" @click="addBreak">
                            <template #leading><Plus :size="14" /></template>
                            休憩を追加
                        </UiButton>
                    </div>
                    <div v-if="form.breaks.length === 0" class="text-xs text-brand-text-subtle py-2">
                        休憩はありません。
                    </div>
                    <div v-for="(b, i) in form.breaks" :key="i" class="flex gap-2 items-center mb-2">
                        <UiInput v-model="b.start_time" type="time" placeholder="開始" class="flex-1" />
                        <span class="text-brand-text-subtle text-sm">〜</span>
                        <UiInput v-model="b.end_time" type="time" placeholder="終了" class="flex-1" />
                        <button
                            type="button"
                            @click="removeBreak(i)"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-soft text-akane-600 hover:text-akane-800 hover:bg-akane-50 transition-colors"
                            title="この休憩を削除"
                        >
                            <Trash2 :size="16" />
                        </button>
                    </div>
                </div>
            </form>

            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <UiButton variant="ghost" :href="route('attendance.history')">キャンセル</UiButton>
                    <UiButton
                        type="button"
                        variant="primary"
                        :loading="form.processing"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        {{ isEdit ? '更新する' : '登録する' }}
                    </UiButton>
                </div>
            </template>
        </UiCard>

    </AdminLayout>
</template>
