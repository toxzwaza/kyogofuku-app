<template>
    <Head title="勤怠管理" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">勤怠管理</h2>
                <Link :href="route('dashboard')" class="text-gray-600 hover:text-gray-900">ダッシュボードへ</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-md text-sm">
                            {{ $page.props.flash.success }}
                        </div>
                        <form @submit.prevent="applyFilters" class="mb-4 flex flex-wrap gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">店舗</label>
                                <select v-model="filters.shop_id" class="rounded-md border-gray-300 text-sm">
                                    <option value="">すべて</option>
                                    <option v-for="s in shops" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">ユーザー</label>
                                <select v-model="filters.user_id" class="rounded-md border-gray-300 text-sm">
                                    <option value="">すべて</option>
                                    <option v-for="u in filteredUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">開始日</label>
                                <input v-model="filters.from" type="date" class="rounded-md border-gray-300 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">終了日</label>
                                <input v-model="filters.to" type="date" class="rounded-md border-gray-300 text-sm" />
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">絞り込み</button>
                            </div>
                        </form>

                        <div v-if="records.data?.length > 0" class="mb-4 flex flex-wrap items-center gap-3">
                            <span class="text-sm font-medium text-gray-700">CSVエクスポート</span>
                            <button type="button" @click="selectAllUsers" class="px-2 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">全選択</button>
                            <button type="button" @click="deselectAllUsers" class="px-2 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">全解除</button>
                            <button
                                type="button"
                                :disabled="selectedUserIds.length === 0"
                                @click="exportCsv"
                                class="px-3 py-1.5 text-sm bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                CSVダウンロード
                            </button>
                            <span class="text-xs text-gray-500">選択中: {{ selectedUserIds.length }}件</span>
                        </div>

                        <div class="mb-4 flex items-center gap-4">
                            <span class="text-sm font-medium text-gray-700">表示方法</span>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="displayMode"
                                    type="radio"
                                    value="table"
                                    class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-sm text-gray-700">テーブル表示</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="displayMode"
                                    type="radio"
                                    value="byUser"
                                    class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-sm text-gray-700">ユーザー毎</span>
                            </label>
                        </div>

                        <!-- テーブル表示 -->
                        <div v-if="displayMode === 'table'" class="space-y-4">
                            <div v-if="uniqueUsersInData.length > 0" class="flex flex-wrap items-center gap-2 p-3 bg-gray-50 rounded-md text-sm">
                                <span class="font-medium text-gray-700">エクスポート対象ユーザー:</span>
                                <label v-for="u in uniqueUsersInData" :key="u.id" class="flex items-center gap-1.5 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="selectedUserIds.includes(u.id)"
                                        @change="toggleUserSelection(u.id)"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span>{{ u.name }}</span>
                                </label>
                            </div>
                            <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">日付</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">ユーザー</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">店舗</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">ステータス</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">出勤</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">退勤</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">ベース開始</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">ベース終了</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">業務開始(給与)</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">残業(丸め)</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700">休憩</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700 w-20">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="r in records.data" :key="r.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-2">{{ formatDateJaWithWeekday(r.date) }}</td>
                                        <td class="px-4 py-2">{{ r.user?.name ?? '-' }}</td>
                                        <td class="px-4 py-2">{{ r.shop?.name ?? '-' }}</td>
                                        <td class="px-4 py-2">
                                            <span :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span>
                                        </td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.clock_in_at) }}</td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.clock_out_at) }}</td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.payroll?.base_start_at) }}</td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.payroll?.base_end_at) }}</td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.payroll?.payroll_clock_in_at) }}</td>
                                        <td class="px-4 py-2">{{ formatOvertimeRounded(r.payroll?.overtime_minutes_rounded) }}</td>
                                        <td class="px-4 py-2">{{ formatBreaks(r.breaks) }}</td>
                                        <td class="px-4 py-2">
                                            <button type="button" @click="openEditModal(r)" class="text-indigo-600 hover:text-indigo-800 text-sm">編集</button>
                                        </td>
                                    </tr>
                                    <tr v-if="!records.data || records.data.length === 0">
                                        <td colspan="12" class="px-4 py-8 text-center text-gray-500">データがありません</td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>

                        <!-- ユーザー毎表示 -->
                        <div v-else class="space-y-6">
                            <template v-if="groupedByUser.length > 0">
                                <section
                                    v-for="group in groupedByUser"
                                    :key="group.userId"
                                    class="rounded-lg border border-gray-200 overflow-hidden"
                                >
                                    <div class="px-4 py-2 bg-gray-100 border-b border-gray-200 font-medium text-gray-800 flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            :checked="selectedUserIds.includes(group.userId)"
                                            @change="toggleUserSelection(group.userId)"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span>{{ group.userName }}（{{ group.records.length }}件）</span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">日付</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">店舗</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">ステータス</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">出勤</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">退勤</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">ベース開始</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">ベース終了</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">業務開始(給与)</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">残業(丸め)</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">休憩</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700 w-20">操作</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr v-for="r in group.records" :key="r.id" class="hover:bg-gray-50">
                                                    <td class="px-4 py-2">{{ formatDateJaWithWeekday(r.date) }}</td>
                                                    <td class="px-4 py-2">{{ r.shop?.name ?? '-' }}</td>
                                                    <td class="px-4 py-2">
                                                        <span :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span>
                                                    </td>
                                                    <td class="px-4 py-2">{{ formatTimeJa(r.clock_in_at) }}</td>
                                                    <td class="px-4 py-2">{{ formatTimeJa(r.clock_out_at) }}</td>
                                                    <td class="px-4 py-2">{{ formatTimeJa(r.payroll?.base_start_at) }}</td>
                                                    <td class="px-4 py-2">{{ formatTimeJa(r.payroll?.base_end_at) }}</td>
                                                    <td class="px-4 py-2">{{ formatTimeJa(r.payroll?.payroll_clock_in_at) }}</td>
                                                    <td class="px-4 py-2">{{ formatOvertimeRounded(r.payroll?.overtime_minutes_rounded) }}</td>
                                                    <td class="px-4 py-2">{{ formatBreaks(r.breaks) }}</td>
                                                    <td class="px-4 py-2">
                                                        <button type="button" @click="openEditModal(r)" class="text-indigo-600 hover:text-indigo-800 text-sm">編集</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            </template>
                            <p v-else class="py-8 text-center text-gray-500 text-sm">データがありません</p>
                        </div>

                        <!-- 編集モーダル -->
                        <div v-if="editRecord" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="editRecord = null">
                            <div class="bg-white rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
                                <div class="p-4 border-b font-medium text-gray-800">勤怠を編集</div>
                                <form @submit.prevent="submitEdit" class="p-4 space-y-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">日付</label>
                                        <input :value="formatDateJaWithWeekday(editRecord.date)" type="text" readonly class="w-full rounded-md border-gray-300 bg-gray-100 text-sm" />
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">出勤時刻</label>
                                            <input v-model="editForm.clock_in_time" type="time" class="w-full rounded-md border-gray-300 text-sm" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">退勤時刻</label>
                                            <input v-model="editForm.clock_out_time" type="time" class="w-full rounded-md border-gray-300 text-sm" />
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center mb-2">
                                            <label class="block text-xs font-medium text-gray-600">休憩</label>
                                            <button type="button" @click="addEditBreak" class="text-sm text-indigo-600 hover:text-indigo-800">+ 休憩を追加</button>
                                        </div>
                                        <div v-for="(b, i) in editForm.breaks" :key="i" class="flex gap-2 items-center mb-2">
                                            <input v-model="b.start_time" type="time" class="flex-1 rounded-md border-gray-300 text-sm" />
                                            <input v-model="b.end_time" type="time" class="flex-1 rounded-md border-gray-300 text-sm" />
                                            <button type="button" @click="editForm.breaks.splice(i, 1)" class="text-red-600 hover:text-red-800 text-sm">削除</button>
                                        </div>
                                    </div>
                                    <div v-if="Object.keys(editForm.errors).length" class="p-2 bg-red-50 text-red-700 rounded text-sm">
                                        <ul>
                                            <li v-for="(msg, k) in editForm.errors" :key="k">{{ msg }}</li>
                                        </ul>
                                    </div>
                                    <div class="flex gap-2 pt-2">
                                        <button type="submit" :disabled="editForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50">
                                            {{ editForm.processing ? '保存中...' : '保存' }}
                                        </button>
                                        <button type="button" @click="editRecord = null" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">キャンセル</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatTimeJa, formatDateJa, formatDateJaWithWeekday, formatDateInputValueJa } from '@/utils/dateFormat';

function formatOvertimeRounded(val) {
    if (val === null || val === undefined) return '-';
    return `${val}分`;
}

const props = defineProps({
    records: Object,
    shops: Array,
    users: Array,
    usersByShop: Object,
    filters: Object,
});

const displayMode = ref('byUser');

const editRecord = ref(null);
const editForm = ref({
    clock_in_time: '',
    clock_out_time: '',
    breaks: [],
    processing: false,
    errors: {},
});

function isoToTime(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (isNaN(date.getTime())) return '';
    const h = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    return `${h}:${min}`;
}

function openEditModal(r) {
    const dateStr = r.date ? formatDateInputValueJa(r.date) : '';
    editRecord.value = r;
    editForm.value = {
        clock_in_time: isoToTime(r.clock_in_at),
        clock_out_time: isoToTime(r.clock_out_at),
        breaks: (r.breaks || []).map((b) => ({ start_time: isoToTime(b.start_at), end_time: isoToTime(b.end_at) })),
        processing: false,
        errors: {},
    };
}

function addEditBreak() {
    editForm.value.breaks.push({ start_time: '', end_time: '' });
}

function buildDatetime(dateStr, timeStr) {
    if (!dateStr || !timeStr) return null;
    return `${dateStr}T${timeStr}:00`;
}

function submitEdit() {
    if (!editRecord.value) return;
    const r = editRecord.value;
    const dateStr = r.date ? formatDateInputValueJa(r.date) : '';
    const clockInAt = buildDatetime(dateStr, editForm.value.clock_in_time);
    const clockOutAt = buildDatetime(dateStr, editForm.value.clock_out_time);
    const breaks = editForm.value.breaks
        .filter((b) => b.start_time)
        .map((b) => ({
            start_at: buildDatetime(dateStr, b.start_time),
            end_at: b.end_time ? buildDatetime(dateStr, b.end_time) : null,
        }));
    editForm.value.processing = true;
    editForm.value.errors = {};
    router.put(route('admin.attendance.update', r.id), {
        clock_in_at: clockInAt,
        clock_out_at: clockOutAt,
        breaks,
        ...Object.fromEntries(
            Object.entries({
                shop_id: filters.value.shop_id,
                user_id: filters.value.user_id,
                from: filters.value.from,
                to: filters.value.to,
            }).filter(([, v]) => v)
        ),
    }, {
        preserveScroll: true,
        onError: (errors) => {
            editForm.value.errors = errors;
            editForm.value.processing = false;
        },
        onFinish: () => {
            editForm.value.processing = false;
        },
        onSuccess: () => {
            editRecord.value = null;
        },
    });
}

const filters = ref({
    shop_id: props.filters?.shop_id ?? '',
    user_id: props.filters?.user_id ?? '',
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

const filteredUsers = computed(() => {
    if (filters.value.shop_id && props.usersByShop) {
        const list = props.usersByShop[filters.value.shop_id];
        return Array.isArray(list) ? list : props.users ?? [];
    }
    return props.users ?? [];
});

watch(() => filters.value.shop_id, () => {
    const ids = filteredUsers.value.map((u) => u.id);
    if (filters.value.user_id && !ids.includes(Number(filters.value.user_id))) {
        filters.value.user_id = '';
    }
});

const groupedByUser = computed(() => {
    const data = props.records?.data ?? [];
    const map = new Map();
    for (const r of data) {
        const uid = r.user_id ?? r.user?.id ?? 0;
        const name = r.user?.name ?? '-';
        if (!map.has(uid)) {
            map.set(uid, { userId: uid, userName: name, records: [] });
        }
        map.get(uid).records.push(r);
    }
    return Array.from(map.values()).sort((a, b) => String(a.userName).localeCompare(String(b.userName)));
});

const selectedUserIds = ref([]);

const uniqueUsersInData = computed(() => {
    const data = props.records?.data ?? [];
    const map = new Map();
    for (const r of data) {
        const uid = r.user_id ?? r.user?.id;
        if (uid && !map.has(uid)) {
            map.set(uid, { id: uid, name: r.user?.name ?? '-' });
        }
    }
    return Array.from(map.values()).sort((a, b) => String(a.name).localeCompare(String(b.name)));
});

function toggleUserSelection(userId) {
    const id = Number(userId);
    const idx = selectedUserIds.value.indexOf(id);
    if (idx >= 0) {
        selectedUserIds.value = selectedUserIds.value.filter((x) => x !== id);
    } else {
        selectedUserIds.value = [...selectedUserIds.value, id];
    }
}

function selectAllUsers() {
    selectedUserIds.value = uniqueUsersInData.value.map((u) => u.id);
}

function deselectAllUsers() {
    selectedUserIds.value = [];
}

function exportCsv() {
    if (selectedUserIds.value.length === 0) {
        return;
    }
    const params = new URLSearchParams();
    selectedUserIds.value.forEach((id) => params.append('user_ids[]', id));
    if (filters.value.from) params.set('from', filters.value.from);
    if (filters.value.to) params.set('to', filters.value.to);
    if (filters.value.shop_id) params.set('shop_id', filters.value.shop_id);
    const url = route('admin.attendance.export-csv') + '?' + params.toString();
    window.location.href = url;
}

function applyFilters() {
    router.get(route('admin.attendance.index'), {
        shop_id: filters.value.shop_id || undefined,
        user_id: filters.value.user_id || undefined,
        from: filters.value.from || undefined,
        to: filters.value.to || undefined,
    }, { preserveState: true });
}

function statusLabel(s) {
    const labels = { draft: '未申請', applied: '申請済', approved: '承認済' };
    return labels[s] ?? s;
}

function statusClass(s) {
    const classes = {
        draft: 'px-2 py-0.5 bg-gray-100 text-gray-800 rounded text-xs',
        applied: 'px-2 py-0.5 bg-amber-100 text-amber-800 rounded text-xs',
        approved: 'px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs',
    };
    return classes[s] ?? '';
}

function formatBreaks(breaks) {
    if (!breaks || breaks.length === 0) return '-';
    return breaks.map(b => {
        const s = formatTimeJa(b.start_at);
        const e = b.end_at ? formatTimeJa(b.end_at) : '〜';
        return `${s}-${e}`;
    }).join(', ');
}
</script>
