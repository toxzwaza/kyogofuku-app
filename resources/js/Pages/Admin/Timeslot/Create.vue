<template>
    <Head title="予約枠追加" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">予約枠追加</h2>
                <Link
                    :href="route('admin.events.timeslots.index', event.id)"
                    class="text-indigo-600 hover:text-indigo-900"
                >
                    ← 予約枠一覧に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- エラーメッセージ -->
                        <div v-if="$page.props.errors && Object.keys($page.props.errors).length > 0" class="mb-4 rounded-md bg-red-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">エラーが発生しました</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc list-inside space-y-1">
                                            <li v-for="(error, key) in $page.props.errors" :key="key">
                                                {{ Array.isArray(error) ? error[0] : error }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- テンプレートグループ作成へのリンク -->
                        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-sm font-medium text-blue-900">テンプレートグループから一括作成</h3>
                                    <p class="text-xs text-blue-700 mt-1">事前に作成したテンプレートを使用して予約枠を一括作成できます</p>
                                </div>
                                <Link
                                    :href="route('admin.timeslot-templates.create')"
                                    target="_blank"
                                    class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                >
                                    テンプレート作成
                                </Link>
                            </div>
                        </div>

                        <form @submit.prevent="submit">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">会場 <span class="text-red-500">*</span></label>
                                    <select
                                        v-model="form.venue_id"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option :value="null">選択してください</option>
                                        <option
                                            v-for="venue in venues"
                                            :key="venue.id"
                                            :value="venue.id"
                                        >
                                            {{ venue.name }}
                                        </option>
                                    </select>
                                    <p v-if="venues.length === 0" class="mt-1 text-sm text-orange-500">
                                        このイベントには会場が登録されていません。
                                    </p>
                                </div>

                                <!-- テンプレートグループ選択セクション -->
                                <div class="border-t pt-4">
                                    <h3 class="text-sm font-medium text-gray-700 mb-3">テンプレートグループから一括作成</h3>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">テンプレートグループ</label>
                                            <select
                                                v-model="selectedTemplateId"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option :value="null">選択してください</option>
                                                <option
                                                    v-for="template in templates"
                                                    :key="template.id"
                                                    :value="template.id"
                                                >
                                                    {{ template.name }}（{{ template.slots?.length || 0 }}件の時間枠）
                                                </option>
                                            </select>
                                        </div>
                                        <div v-if="selectedTemplateId">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">日付 <span class="text-red-500">*</span></label>
                                            <input
                                                v-model="templateDate"
                                                type="date"
                                                required
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                        </div>
                                        <div v-if="selectedTemplateId">
                                            <label class="flex items-center">
                                                <input
                                                    v-model="templateIsActive"
                                                    type="checkbox"
                                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                                <span class="ml-2 text-sm text-gray-700">有効</span>
                                            </label>
                                        </div>
                                        <div v-if="selectedTemplateId && templateDate && form.venue_id">
                                            <button
                                                type="button"
                                                @click="addFromTemplate"
                                                class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                                            >
                                                テンプレートから追加
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t pt-4">
                                    <h3 class="text-sm font-medium text-gray-700 mb-3">個別追加</h3>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">開始日時 <span class="text-red-500">*</span></label>
                                    <div class="grid grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">日付</label>
                                            <input
                                                v-model="form.start_date"
                                                type="date"
                                                required
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">時</label>
                                            <select
                                                v-model="form.start_hour"
                                                required
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option
                                                    v-for="hour in hours"
                                                    :key="hour"
                                                    :value="hour"
                                                >
                                                    {{ String(hour).padStart(2, '0') }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">分</label>
                                            <select
                                                v-model="form.start_minute"
                                                required
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option
                                                    v-for="minute in minutes"
                                                    :key="minute"
                                                    :value="minute"
                                                >
                                                    {{ String(minute).padStart(2, '0') }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">枠数 <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.capacity"
                                        type="number"
                                        min="1"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>

                                <div>
                                    <label class="flex items-center">
                                        <input
                                            v-model="form.is_active"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">有効</span>
                                    </label>
                                </div>

                                <div class="flex justify-end space-x-4 pt-4">
                                    <Link
                                        :href="route('admin.events.timeslots.index', event.id)"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                    >
                                        キャンセル
                                    </Link>
                                    <button
                                        type="button"
                                        @click="addToPendingList"
                                        :disabled="!form.venue_id"
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                                    >
                                        追加
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- 既存予約枠表示（会場選択時） -->
                        <div v-if="form.venue_id && existingTimeslots.length > 0" class="mt-8 border-t pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                既存予約枠（{{ existingTimeslots.length }}件）
                            </h3>
                            <div v-if="isLoadingExistingTimeslots" class="text-center py-4 text-gray-500">
                                読み込み中...
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">開始日時</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">枠数</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状態</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="timeslot in existingTimeslots" :key="timeslot.id">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ formatDateTimeForDisplay(timeslot.start_at) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ timeslot.capacity }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <span :class="timeslot.is_active ? 'text-green-600' : 'text-gray-400'">
                                                    {{ timeslot.is_active ? '有効' : '無効' }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 追加済み予約枠リスト -->
                        <div v-if="pendingTimeslots.length > 0" class="mt-8 border-t pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                追加済み予約枠（{{ pendingTimeslots.length }}件）
                            </h3>
                            <div class="space-y-3">
                                <div
                                    v-for="(timeslot, index) in pendingTimeslots"
                                    :key="index"
                                    class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200"
                                >
                                    <div class="flex-1 grid grid-cols-4 gap-4">
                                        <div>
                                            <span class="text-xs text-gray-500">会場</span>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ getVenueName(timeslot.venue_id) }}
                                            </p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500">開始日時</span>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ formatDateTimeForDisplay(timeslot.start_at) }}
                                            </p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500">枠数</span>
                                            <p class="text-sm font-medium text-gray-900">{{ timeslot.capacity }}</p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500">状態</span>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ timeslot.is_active ? '有効' : '無効' }}
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeFromPendingList(index)"
                                        class="ml-4 px-3 py-1 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-md"
                                    >
                                        削除
                                    </button>
                                </div>
                            </div>

                            <!-- 保存ボタン -->
                            <div class="flex justify-end space-x-4 mt-6 pt-4 border-t">
                                <Link
                                    :href="route('admin.events.timeslots.index', event.id)"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                >
                                    キャンセル
                                </Link>
                                <button
                                    type="button"
                                    @click="submit"
                                    :disabled="isSubmitting"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                >
                                    {{ isSubmitting ? '保存中...' : `保存（${pendingTimeslots.length}件）` }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    event: Object,
    venues: Array,
    duplicateTimeslot: Object,
    existingTimeslots: {
        type: Array,
        default: () => [],
    },
    templates: {
        type: Array,
        default: () => [],
    },
});

// 時（0-23）の選択肢を生成
const hours = Array.from({ length: 24 }, (_, i) => i);

// 分（10分単位：00, 10, 20, 30, 40, 50）の選択肢を生成
const minutes = [0, 10, 20, 30, 40, 50];

// 日付をYYYY-MM-DD形式に変換
const formatDateForInput = (datetime) => {
    if (!datetime) return '';
    const date = new Date(datetime);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// 日時をフォーマットして表示
const formatDateTimeForDisplay = (datetime) => {
    if (!datetime) return '';
    const date = new Date(datetime);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}`;
};

// 日付と時分を組み合わせてローカル時刻のISO形式に変換
const combineDateTimeToISO = (date, hour, minute) => {
    if (!date || hour === null || hour === undefined || minute === null || minute === undefined) {
        return null;
    }
    // ローカル時刻として扱い、UTC変換を避ける
    const hourStr = String(parseInt(hour)).padStart(2, '0');
    const minuteStr = String(parseInt(minute)).padStart(2, '0');
    const secondStr = '00';
    // YYYY-MM-DD HH:mm:ss 形式で返す（Laravelがタイムゾーン設定に基づいて処理）
    return `${date} ${hourStr}:${minuteStr}:${secondStr}`;
};

// 複製元の予約枠がある場合は、その値を初期値として使用
const getInitialValues = () => {
    if (props.duplicateTimeslot) {
        const duplicateDate = new Date(props.duplicateTimeslot.start_at);
        return {
            venue_id: props.duplicateTimeslot.venue_id || (props.venues.length === 1 ? props.venues[0].id : null),
            start_date: formatDateForInput(props.duplicateTimeslot.start_at),
            start_hour: duplicateDate.getHours(),
            start_minute: Math.floor(duplicateDate.getMinutes() / 10) * 10, // 10分単位に丸める
            capacity: props.duplicateTimeslot.capacity,
            is_active: props.duplicateTimeslot.is_active,
        };
    }
    return {
        venue_id: props.venues.length === 1 ? props.venues[0].id : null,
        start_date: '',
        start_hour: 0,
        start_minute: 0,
        capacity: 1,
        is_active: true,
    };
};

const form = useForm(getInitialValues());

// 追加待ちの予約枠リスト
const pendingTimeslots = ref([]);

// 送信中の状態
const isSubmitting = ref(false);

// テンプレート関連
const selectedTemplateId = ref(null);
const templateDate = ref('');
const templateIsActive = ref(true);

// 既存の予約枠リスト（会場選択時に表示）
const existingTimeslots = ref(props.existingTimeslots || []);

// 既存予約枠の取得中状態
const isLoadingExistingTimeslots = ref(false);

// 会場名を取得
const getVenueName = (venueId) => {
    if (!venueId) return '選択なし';
    const venue = props.venues.find(v => v.id === venueId);
    return venue ? venue.name : '不明';
};

// 会場選択時に既存予約枠を取得
const fetchExistingTimeslots = (venueId) => {
    if (!venueId) {
        existingTimeslots.value = [];
        return;
    }

    isLoadingExistingTimeslots.value = true;
    router.get(
        route('admin.events.timeslots.create', props.event.id),
        { venue_id: venueId },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['existingTimeslots'],
            onSuccess: (page) => {
                if (page.props.existingTimeslots) {
                    existingTimeslots.value = page.props.existingTimeslots;
                }
                isLoadingExistingTimeslots.value = false;
            },
            onError: () => {
                isLoadingExistingTimeslots.value = false;
            },
        }
    );
};

// 会場選択の変更を監視
watch(() => form.venue_id, (newVenueId, oldVenueId) => {
    // 会場が変更された場合のみ取得（初期化時はpropsから取得済み）
    if (newVenueId !== oldVenueId) {
        fetchExistingTimeslots(newVenueId);
    }
});

// 初期表示時に既存予約枠を設定（propsから）
if (props.existingTimeslots && props.existingTimeslots.length > 0) {
    existingTimeslots.value = props.existingTimeslots;
}

// フォーム値をリストに追加
const addToPendingList = () => {
    // バリデーション
    if (!form.venue_id) {
        alert('会場を選択してください。');
        return;
    }
    if (!form.start_date) {
        alert('開始日を入力してください。');
        return;
    }
    if (form.start_hour === null || form.start_hour === undefined) {
        alert('開始時を選択してください。');
        return;
    }
    if (form.start_minute === null || form.start_minute === undefined) {
        alert('開始分を選択してください。');
        return;
    }
    if (!form.capacity || form.capacity < 1) {
        alert('枠数は1以上を入力してください。');
        return;
    }

    // 日付と時分を組み合わせてISO形式に変換
    const startAtISO = combineDateTimeToISO(form.start_date, form.start_hour, form.start_minute);
    if (!startAtISO) {
        alert('開始日時が正しく入力されていません。');
        return;
    }

    // リストに追加
    pendingTimeslots.value.push({
        venue_id: form.venue_id,
        start_at: startAtISO,
        capacity: parseInt(form.capacity),
        is_active: form.is_active,
    });

    // フォームはリセットしない（連続登録用にデータを保持）
};

// リストから削除
const removeFromPendingList = (index) => {
    pendingTimeslots.value.splice(index, 1);
};

// テンプレートから一括追加
const addFromTemplate = () => {
    if (!selectedTemplateId.value) {
        alert('テンプレートグループを選択してください。');
        return;
    }
    if (!templateDate.value) {
        alert('日付を選択してください。');
        return;
    }
    if (!form.venue_id) {
        alert('会場を選択してください。');
        return;
    }

    const template = props.templates.find(t => t.id === selectedTemplateId.value);
    if (!template || !template.slots || template.slots.length === 0) {
        alert('選択したテンプレートに時間枠が登録されていません。');
        return;
    }

    template.slots.forEach(slot => {
        const hourStr = String(slot.hour).padStart(2, '0');
        const minuteStr = String(slot.minute).padStart(2, '0');
        const startAtISO = `${templateDate.value} ${hourStr}:${minuteStr}:00`;

        pendingTimeslots.value.push({
            venue_id: form.venue_id,
            start_at: startAtISO,
            capacity: parseInt(slot.capacity),
            is_active: templateIsActive.value,
        });
    });

    // テンプレート選択をリセット
    selectedTemplateId.value = null;
    templateDate.value = '';
};

// 一括登録
const submit = () => {
    if (pendingTimeslots.value.length === 0) {
        alert('追加された予約枠がありません。先に予約枠を追加してください。');
        return;
    }

    isSubmitting.value = true;

    // 一括登録用の新しいフォームインスタンスを作成
    const bulkForm = useForm({
        timeslots: pendingTimeslots.value,
    });

    bulkForm.post(route('admin.events.timeslots.store', props.event.id), {
        preserveState: false,
        preserveScroll: false,
        onSuccess: () => {
            // 成功時はリダイレクトされる
            isSubmitting.value = false;
        },
        onError: (errors) => {
            console.error('登録エラー:', errors);
            isSubmitting.value = false;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

