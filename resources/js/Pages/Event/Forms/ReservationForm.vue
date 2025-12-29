<template>
    <form @submit.prevent="submit">
        <!-- イベント情報 -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold mb-4">{{ event.title }}</h1>
            <div v-if="event.description" class="text-gray-700 mb-6" v-html="event.description"></div>

            <!-- 開催会場 -->
            <div v-if="eventVenues && eventVenues.length > 0" class="mb-6">
                <h2 class="text-xl font-semibold mb-4">開催会場</h2>
                <div class="space-y-6">
                    <div v-for="venue in eventVenues" :key="venue.id" class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                        <!-- 画像とテキストのグリッドレイアウト -->
                        <div class="md:flex">
                            <!-- テキスト情報（左側または上側） -->
                            <div class="flex-1 p-6">
                                <h3 class="font-bold text-xl text-gray-900 mb-3">{{ venue.name }}</h3>
                                
                                <div v-if="venue.description" class="text-sm text-gray-700 mb-4 leading-relaxed" v-html="venue.description"></div>
                                
                                <div class="space-y-3">
                                    <!-- 住所 -->
                                    <div v-if="venue.address" class="flex items-start space-x-3">
                                        <div class="flex-shrink-0 mt-0.5">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </div>
                                        <p class="text-sm text-gray-700 flex-1">{{ venue.address }}</p>
                                    </div>
                                    
                                    <!-- 電話番号 -->
                                    <div v-if="venue.phone" class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                        </div>
                                        <a :href="`tel:${venue.phone}`" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                            {{ venue.phone }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 画像（右側または下側） -->
                            <div v-if="venue.image_url" class="md:w-1/2 lg:w-2/5 flex-shrink-0">
                                <img
                                    :src="venue.image_url"
                                    :alt="venue.name"
                                    class="w-full h-full object-cover"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- 会場選択 -->
        <div v-if="eventVenues && eventVenues.length > 0" class="mb-6">
            <h2 class="text-xl font-semibold mb-4">ご来店会場 <span class="text-red-500">*</span></h2>
            <select
                v-model="form.venue_id"
                required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="">選択してください</option>
                <option v-for="venue in eventVenues" :key="venue.id" :value="venue.id">
                    {{ venue.name }}
                </option>
            </select>
        </div>

        <!-- 予約可能な日時 -->
        <div class="mb-6">
            <h2 class="text-xl font-semibold mb-4">予約可能な日時</h2>
            <div v-if="!form.venue_id" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded">
                <p class="text-yellow-800">会場を選択してください。</p>
            </div>
            <div v-else-if="filteredTimeslots && filteredTimeslots.length === 0" class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded">
                <p class="text-gray-600">選択された会場には予約可能な日時がありません。</p>
            </div>
            <div v-else-if="filteredTimeslots && filteredTimeslots.length > 0">
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
                <label class="block text-sm font-medium text-gray-700 mb-1">担当者指名</label>
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
    fromAdmin: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['submitted', 'timeslot-selected', 'confirm']);

const internalSelectedTimeslot = ref(props.selectedTimeslot || null);

// URLパラメータから予約枠を取得して自動選択（timeslot_idのみ）
if (props.timeslots && props.timeslots.length > 0) {
    const urlParams = new URLSearchParams(window.location.search);
    const urlTimeslotId = urlParams.get('timeslot_id');
    
    if (urlTimeslotId) {
        // timeslot_idが指定されている場合、そのIDで直接検索
        const matchingTimeslot = props.timeslots.find(t => t.id == urlTimeslotId);
        if (matchingTimeslot) {
            internalSelectedTimeslot.value = matchingTimeslot;
        }
    }
}

// event_venueから会場を取得（event.venuesが利用可能な場合はそれを使用、そうでない場合はvenuesプロップを使用）
const eventVenues = computed(() => {
    if (props.event?.venues && props.event.venues.length > 0) {
        return props.event.venues;
    }
    return props.venues || [];
});

// 選択された会場でフィルタリングされた予約枠
const filteredTimeslots = computed(() => {
    if (!props.timeslots || props.timeslots.length === 0) return [];
    if (!form.venue_id) return [];
    
    return props.timeslots.filter(timeslot => {
        // venue_idがnullの場合は、すべての会場で利用可能とみなす（後方互換性のため）
        // venue_idが設定されている場合は、選択された会場と一致するもののみ
        return !timeslot.venue_id || timeslot.venue_id == form.venue_id;
    });
});

// 予約枠を日付ごとにグループ化
const groupedTimeslots = computed(() => {
    if (!filteredTimeslots.value || filteredTimeslots.value.length === 0) return {};
    const groups = {};
    filteredTimeslots.value.forEach(timeslot => {
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

// 生年月日から成人式予定年を計算する関数
const calculateSeijinYear = (birthDate) => {
    if (!birthDate) {
        return null;
    }
    
    try {
        const date = new Date(birthDate);
        if (isNaN(date.getTime())) {
            return null;
        }
        
        // 生年月日の年 + 20 = 成人式予定年
        const birthYear = date.getFullYear();
        const seijinYear = birthYear + 20;
        
        // 計算された年が選択肢に含まれているか確認
        if (seijinYears.includes(seijinYear)) {
            return seijinYear;
        }
        
        return null;
    } catch (error) {
        console.error('生年月日の計算エラー:', error);
        return null;
    }
};

// 生年月日の変更を監視して、成人式予定年を自動選択
watch(() => form.birth_date, (newBirthDate) => {
    if (newBirthDate) {
        const calculatedYear = calculateSeijinYear(newBirthDate);
        if (calculatedYear && !form.seijin_year) {
            // 既に値が設定されていない場合のみ自動選択
            form.seijin_year = calculatedYear;
        }
    }
});

// 会場が一つしかない場合はデフォルトで選択
watch(() => eventVenues.value, (newVenues) => {
    if (newVenues && newVenues.length === 1 && !form.venue_id) {
        // 会場が一つしかない場合は自動選択
        form.venue_id = newVenues[0].id;
    }
}, { immediate: true });

// 予約枠が選択された場合、会場を自動選択
watch(() => internalSelectedTimeslot.value, (newTimeslot) => {
    if (newTimeslot) {
        // 予約枠から会場IDを取得して自動選択
        if (newTimeslot.venue_id && !form.venue_id) {
            const venue = eventVenues.value.find(v => v.id === newTimeslot.venue_id);
            if (venue) {
                form.venue_id = venue.id;
            }
        }
        
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

// 会場が変更された場合、選択されている予約枠をクリア（予約枠の会場と一致しない場合のみ）
watch(() => form.venue_id, (newVenueId, oldVenueId) => {
    // 初期化時はスキップ
    if (oldVenueId === undefined) return;
    
    // 予約枠が選択されている場合、会場が一致しない場合は予約枠をクリア
    if (internalSelectedTimeslot.value) {
        const timeslotVenueId = internalSelectedTimeslot.value.venue_id;
        // 予約枠に会場IDが設定されている場合、選択された会場と一致しない場合はクリア
        if (timeslotVenueId && timeslotVenueId != newVenueId) {
            internalSelectedTimeslot.value = null;
            form.reservation_datetime = '';
        }
    }
});

const processing = ref(false);

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
        timeslot_id: internalSelectedTimeslot.value.id, // 予約枠IDを追加
        from_admin: props.fromAdmin,
    });
};
</script>

