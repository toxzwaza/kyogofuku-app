<template>
    <Head :title="record ? '仮登録編集' : '仮登録'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ record ? '仮登録編集' : '仮登録' }}</h2>
                <Link :href="route('attendance.history')" class="text-gray-600 hover:text-gray-900">勤怠履歴へ</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <form @submit.prevent="submit">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">日付 <span class="text-red-500">*</span></label>
                                <input
                                    v-model="form.date"
                                    type="date"
                                    required
                                    :disabled="!!record"
                                    class="w-full rounded-md border-gray-300 text-sm disabled:bg-gray-100"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">店舗 <span class="text-red-500">*</span></label>
                                <select
                                    v-model="form.shop_id"
                                    required
                                    :disabled="!!record"
                                    class="w-full rounded-md border-gray-300 text-sm disabled:bg-gray-100"
                                >
                                    <option value="">選択してください</option>
                                    <option v-for="s in userShops" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">出勤時刻</label>
                                    <input v-model="form.clock_in_time" type="time" class="w-full rounded-md border-gray-300 text-sm" />
                                    <p class="mt-0.5 text-xs text-gray-500">日付は上で選択した日付になります</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">退勤時刻</label>
                                    <input v-model="form.clock_out_time" type="time" class="w-full rounded-md border-gray-300 text-sm" />
                                    <p class="mt-0.5 text-xs text-gray-500">日付は上で選択した日付になります</p>
                                </div>
                            </div>
                            <div v-if="!record">
                                <label class="flex items-center gap-2">
                                    <input v-model="form.is_manual" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                    <span class="text-sm text-gray-700">確定申請（申請済となり、管理者の承認が必要です）</span>
                                </label>
                            </div>
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm font-medium text-gray-700">休憩（複数可）</label>
                                    <button type="button" @click="addBreak" class="text-sm text-indigo-600 hover:text-indigo-800">+ 休憩を追加</button>
                                </div>
                                <div v-for="(b, i) in form.breaks" :key="i" class="flex gap-2 items-center mb-2">
                                    <input v-model="b.start_time" type="time" class="flex-1 rounded-md border-gray-300 text-sm" placeholder="開始" />
                                    <input v-model="b.end_time" type="time" class="flex-1 rounded-md border-gray-300 text-sm" placeholder="終了" />
                                    <button type="button" @click="form.breaks.splice(i, 1)" class="text-red-600 hover:text-red-800">削除</button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex gap-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ form.processing ? '保存中...' : (record ? '更新' : '登録') }}
                            </button>
                            <Link :href="route('attendance.history')" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                キャンセル
                            </Link>
                        </div>
                        <div v-if="Object.keys(form.errors).length" class="mt-4 p-3 bg-red-50 text-red-700 rounded-md text-sm">
                            <ul>
                                <li v-for="(msg, k) in form.errors" :key="k">{{ msg }}</li>
                            </ul>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    record: Object,
    userShops: Array,
});

function isoToTime(iso) {
    if (!iso) return '';
    const s = String(iso);
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(s)) return s.slice(11, 16);
    if (/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}/.test(s)) return s.slice(11, 16);
    return s.length >= 5 ? s.slice(0, 5) : '';
}

const form = reactive({
    date: props.record?.date ?? new Date().toISOString().slice(0, 10),
    shop_id: props.record?.shop_id ?? (props.userShops?.[0]?.id ?? ''),
    clock_in_time: isoToTime(props.record?.clock_in_at),
    clock_out_time: isoToTime(props.record?.clock_out_at),
    is_manual: false,
    breaks: props.record?.breaks?.length
        ? props.record.breaks.map(b => ({
            start_time: isoToTime(b.start_at),
            end_time: isoToTime(b.end_at),
        }))
        : [],
    processing: false,
    errors: {},
});

function addBreak() {
    form.breaks.push({ start_time: '', end_time: '' });
}

function buildDatetime(dateStr, timeStr) {
    if (!dateStr || !timeStr) return null;
    return `${dateStr}T${timeStr}:00`;
}

function submit() {
    const clockInAt = buildDatetime(form.date, form.clock_in_time);
    const clockOutAt = buildDatetime(form.date, form.clock_out_time);
    const breaks = form.breaks
        .filter(b => b.start_time)
        .map(b => ({
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
