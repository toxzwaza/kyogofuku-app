<template>
    <form @submit.prevent="submit">
        <!-- イベント情報 -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold mb-4">{{ event.title }}</h1>
            <div v-if="event.description" class="text-gray-700 mb-6" v-html="event.description"></div>

            <!-- 開催店舗 -->
            <div v-if="shops && shops.length > 0" class="mb-6">
                <h2 class="text-xl font-semibold mb-4">開催店舗</h2>
                <div class="space-y-4">
                    <div v-for="shop in shops" :key="shop.id" class="bg-gray-50 p-4 rounded-lg flex items-start space-x-4">
                        <div v-if="shop.image_url" class="flex-shrink-0">
                            <img
                                :src="shop.image_url"
                                :alt="shop.name"
                                class="w-24 h-24 object-cover rounded"
                            />
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold">{{ shop.name }}</p>
                            <p v-if="shop.address" class="text-sm text-gray-600">{{ shop.address }}</p>
                            <p v-if="shop.phone" class="text-sm text-gray-600">{{ shop.phone }}</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- 予約可能な日時 -->
        <div v-if="timeslots && timeslots.length > 0" class="mb-6">
            <h2 class="text-xl font-semibold mb-4">予約可能な日時</h2>
            <div v-if="!internalSelectedTimeslot" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded">
                <p class="text-yellow-800">予約日時を選択してください。</p>
            </div>
            <div class="space-y-6">
                <div
                    v-for="(dateGroup, date) in groupedTimeslots"
                    :key="date"
                    class="border-b border-gray-200 pb-6 last:border-b-0 last:pb-0"
                >
                    <h3 class="text-lg font-semibold mb-3 text-gray-800">{{ formatDate(date) }}</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                            <button
                                type="button"
                                v-for="timeslot in dateGroup"
                                :key="timeslot.id"
                                @click="selectTimeslot(timeslot)"
                                :class="[
                                    'p-3 rounded-lg border-2 transition text-left',
                                    internalSelectedTimeslot?.id === timeslot.id
                                        ? 'border-blue-500 bg-blue-50'
                                        : 'border-gray-200 hover:border-blue-300 bg-white'
                                ]"
                            >
                            <p class="font-semibold text-sm mb-1">{{ formatTime(timeslot.start_at) }}</p>
                            <p class="text-xs text-gray-600">残り{{ getRemainingCapacity(timeslot) }}枠</p>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">予約日時 <span class="text-red-500">*</span></label>
                <input
                    type="text"
                    :value="internalSelectedTimeslot ? formatDateTime(internalSelectedTimeslot.start_at) : ''"
                    readonly
                    class="w-full rounded-md border-gray-300 shadow-sm bg-gray-100"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">お名前 <span class="text-red-500">*</span></label>
                <input
                    v-model="form.name"
                    type="text"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">フリガナ</label>
                <input
                    v-model="form.furigana"
                    type="text"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス <span class="text-red-500">*</span></label>
                <input
                    v-model="form.email"
                    type="email"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">電話番号 <span class="text-red-500">*</span></label>
                <input
                    v-model="form.phone"
                    type="tel"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ご来店会場 <span class="text-red-500">*</span></label>
                <select
                    v-model="form.venue_id"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">選択してください</option>
                    <option v-for="venue in venues" :key="venue.id" :value="venue.id">
                        {{ venue.name }}
                    </option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">過去当店のご来店はありますか</label>
                <div class="flex space-x-4">
                    <label class="flex items-center">
                        <input
                            v-model="form.has_visited_before"
                            type="radio"
                            :value="false"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <span class="ml-2 text-sm text-gray-700">なし</span>
                    </label>
                    <label class="flex items-center">
                        <input
                            v-model="form.has_visited_before"
                            type="radio"
                            :value="true"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <span class="ml-2 text-sm text-gray-700">あり</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">住所</label>
                <input
                    v-model="form.address"
                    type="text"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">生年月日</label>
                <input
                    v-model="form.birth_date"
                    type="date"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">成人式予定年月</label>
                <select
                    v-model="form.seijin_year"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">選択してください</option>
                    <option v-for="year in seijinYears" :key="year" :value="year">
                        {{ year }}年
                    </option>
                </select>
            </div>


            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">学校名</label>
                <input
                    v-model="form.school_name"
                    type="text"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">担当者指定</label>
                <input
                    v-model="form.staff_name"
                    type="text"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">来店動機</label>
                <div class="space-y-2">
                    <label
                        v-for="reason in visitReasonOptions"
                        :key="reason.value"
                        class="flex items-center"
                    >
                        <input
                            type="checkbox"
                            :value="reason.value"
                            v-model="form.visit_reasons"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <span class="ml-2 text-sm text-gray-700">{{ reason.label }}</span>
                    </label>
                    <div v-if="form.visit_reasons && form.visit_reasons.includes('その他')" class="ml-6 mt-2">
                        <input
                            v-model="form.visit_reason_other"
                            type="text"
                            placeholder="その他の内容を入力してください"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">駐車場利用</label>
                <div class="flex space-x-4">
                    <label class="flex items-center">
                        <input
                            v-model="form.parking_usage"
                            type="radio"
                            value="なし"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <span class="ml-2 text-sm text-gray-700">なし</span>
                    </label>
                    <label class="flex items-center">
                        <input
                            v-model="form.parking_usage"
                            type="radio"
                            value="あり"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <span class="ml-2 text-sm text-gray-700">あり</span>
                    </label>
                </div>
            </div>

            <div v-if="form.parking_usage === 'あり'">
                <label class="block text-sm font-medium text-gray-700 mb-1">駐車台数</label>
                <input
                    v-model="form.parking_car_count"
                    type="number"
                    min="1"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">検討中のプラン</label>
                <div class="space-y-2">
                    <label
                        v-for="plan in availablePlans"
                        :key="plan"
                        class="flex items-center"
                    >
                        <input
                            type="checkbox"
                            :value="plan"
                            v-model="form.considering_plans"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <span class="ml-2 text-sm text-gray-700">{{ plan }}</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ご紹介者様お名前</label>
                <input
                    v-model="form.referred_by_name"
                    type="text"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">お問い合わせ内容</label>
                <textarea
                    v-model="form.inquiry_message"
                    rows="4"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                ></textarea>
            </div>

            <div class="pt-4">
                <button
                    type="submit"
                    :disabled="!internalSelectedTimeslot || processing"
                    class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                >
                    {{ processing ? '確認中...' : '送信内容の確認へ' }}
                </button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    shops: Array,
    venues: Array,
    timeslots: Array,
    selectedTimeslot: Object,
});

const emit = defineEmits(['submitted', 'timeslot-selected', 'confirm']);

const internalSelectedTimeslot = ref(props.selectedTimeslot || null);

// 予約枠を日付ごとにグループ化
const groupedTimeslots = computed(() => {
    if (!props.timeslots || props.timeslots.length === 0) return {};
    const groups = {};
    props.timeslots.forEach(timeslot => {
        const date = new Date(timeslot.start_at);
        const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        if (!groups[dateKey]) {
            groups[dateKey] = [];
        }
        groups[dateKey].push(timeslot);
    });
    // 各日付の枠を時間順にソート
    Object.keys(groups).forEach(dateKey => {
        groups[dateKey].sort((a, b) => {
            return new Date(a.start_at) - new Date(b.start_at);
        });
    });
    return groups;
});

const selectTimeslot = (timeslot) => {
    internalSelectedTimeslot.value = timeslot;
    emit('timeslot-selected', timeslot);
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'short',
    });
};

const formatTime = (datetime) => {
    const date = new Date(datetime);
    return date.toLocaleTimeString('ja-JP', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getRemainingCapacity = (timeslot) => {
    return timeslot.remaining_capacity || 0;
};

const form = useForm({
    name: '',
    email: '',
    phone: '',
    reservation_datetime: '',
    venue_id: null,
    has_visited_before: false,
    address: '',
    birth_date: '',
    seijin_year: null,
    referred_by_name: '',
    furigana: '',
    school_name: '',
    staff_name: '',
    visit_reasons: [],
    visit_reason_other: '',
    parking_usage: '',
    parking_car_count: null,
    considering_plans: [],
    heard_from: '',
    inquiry_message: '',
});

// 検討中のプランの選択肢
const availablePlans = [
    '振袖レンタルプラン',
    '振袖購入プラン',
    'ママ振りフォトプラン',
    'フォトレンタルプラン',
];

// 来店動機の選択肢
const visitReasonOptions = [
    { value: '紹介', label: '紹介' },
    { value: 'DM・カタログ', label: 'DM・カタログ' },
    { value: 'SNS広告(Instaなど)', label: 'SNS広告(Instaなど)' },
    { value: 'WEB広告', label: 'WEB広告' },
    { value: 'その他', label: 'その他(テキスト入力)' },
];

// 成人式予定年の選択肢（現在年から10年後まで）
const currentYear = new Date().getFullYear();
const seijinYears = Array.from({ length: 11 }, (_, i) => currentYear + i);

// 会場が一つしかない場合はデフォルトで選択
watch(() => props.venues, (newVenues) => {
    if (newVenues && newVenues.length === 1 && !form.venue_id) {
        form.venue_id = newVenues[0].id;
    }
}, { immediate: true });

const processing = ref(false);

watch(() => internalSelectedTimeslot.value, (newTimeslot) => {
    if (newTimeslot) {
        // start_atをY-m-d H:i:s形式の文字列に変換
        const date = new Date(newTimeslot.start_at);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        form.reservation_datetime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }
}, { immediate: true });

const formatDateTime = (datetime) => {
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const submit = () => {
    if (!internalSelectedTimeslot.value) {
        alert('予約日時を選択してください。');
        return;
    }

    // 確認ページに遷移
    emit('confirm', {
        ...form.data(),
        reservation_datetime: form.reservation_datetime,
    });
};
</script>

