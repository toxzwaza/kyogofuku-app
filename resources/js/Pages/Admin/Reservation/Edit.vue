<template>
    <Head title="予約編集" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">予約編集</h2>
                <Link
                    :href="route('admin.events.reservations.index', reservation.event_id)"
                    class="text-indigo-600 hover:text-indigo-900"
                >
                    ← 予約一覧に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <div class="space-y-4">
                                <!-- 共通フィールド -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">お名前 <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">電話番号 <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.phone"
                                        type="tel"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <div v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</div>
                                </div>

                                <!-- 予約フォーム (reservation) -->
                                <template v-if="event.form_type === 'reservation'">
                                    <!-- ご来店会場（会場を先に選択） -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">ご来店会場 <span class="text-red-500">*</span></label>
                                        <select
                                            v-model="form.venue_id"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">選択してください</option>
                                            <option v-for="venue in venues" :key="venue.id" :value="venue.id">
                                                {{ venue.name }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.venue_id" class="mt-1 text-sm text-red-600">{{ form.errors.venue_id }}</div>
                                    </div>

                                    <!-- 予約可能な日時（選択会場の枠のみ・日付昇順・満枠グレー・バッジ） -->
                                    <div v-if="timeslots && timeslots.length > 0" class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">予約日時 <span class="text-red-500">*</span></label>
                                        <div v-if="!form.venue_id" class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-md">
                                            <p class="text-gray-600 text-sm">会場を選択してください。</p>
                                        </div>
                                        <div v-else-if="filteredTimeslots.length === 0" class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-md">
                                            <p class="text-gray-600 text-sm">選択された会場には開催日時がありません。</p>
                                        </div>
                                        <template v-else>
                                            <div v-if="!selectedTimeslot" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                                                <p class="text-yellow-800 text-sm">予約日時を選択してください。</p>
                                            </div>
                                            <div class="space-y-6">
                                                <div
                                                    v-for="date in sortedGroupedTimeslotDates"
                                                    :key="date"
                                                    class="border-b border-gray-200 pb-6 last:border-b-0 last:pb-0"
                                                >
                                                    <h3 class="text-lg font-semibold mb-3 text-gray-800">{{ formatDate(date) }}</h3>
                                                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                                        <button
                                                            type="button"
                                                            v-for="timeslot in groupedTimeslots[date]"
                                                            :key="timeslot.id"
                                                            :disabled="getRemainingCapacity(timeslot) === 0"
                                                            @click="getRemainingCapacity(timeslot) > 0 && selectTimeslot(timeslot)"
                                                            :class="[
                                                                'p-3 rounded-lg border-2 transition text-left',
                                                                getRemainingCapacity(timeslot) === 0
                                                                    ? 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed opacity-75'
                                                                    : [
                                                                        selectedTimeslot?.id === timeslot.id
                                                                            ? 'border-indigo-500 bg-indigo-50'
                                                                            : 'border-gray-200 hover:border-indigo-300 bg-white'
                                                                    ]
                                                            ]"
                                                        >
                                                            <!-- バッジ（受付終了 / 残りわずか / ねらい目） -->
                                                            <div v-if="getRemainingCapacity(timeslot) === 0 || getTimeslotBadge(timeslot)" class="mb-2 flex flex-wrap gap-1">
                                                                <span
                                                                    v-if="getRemainingCapacity(timeslot) === 0"
                                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold shadow-sm bg-gradient-to-r from-gray-400 to-gray-500 text-white ring-1 ring-gray-400/50"
                                                                >
                                                                    <span class="mr-1">🔒</span>受付終了
                                                                </span>
                                                                <span
                                                                    v-else-if="getTimeslotBadge(timeslot) === 'nokori_wazuka'"
                                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold shadow-sm bg-gradient-to-r from-rose-400 to-pink-500 text-white ring-1 ring-rose-300/50"
                                                                >
                                                                    <span class="mr-1">✨</span>残りわずか
                                                                </span>
                                                                <span
                                                                    v-else-if="getTimeslotBadge(timeslot) === 'nerai_me'"
                                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold shadow-sm bg-gradient-to-r from-amber-300 to-yellow-400 text-amber-900 ring-1 ring-amber-400/50"
                                                                >
                                                                    <span class="mr-1">★</span>ねらい目
                                                                </span>
                                                            </div>
                                                            <p class="font-semibold text-sm mb-1" :class="getRemainingCapacity(timeslot) === 0 ? 'text-gray-500' : 'text-gray-800'">{{ formatTime(timeslot.start_at) }}</p>
                                                            <p class="text-xs" :class="getRemainingCapacity(timeslot) === 0 ? 'text-gray-400' : 'text-gray-600'">
                                                                <template v-if="getRemainingCapacity(timeslot) === 0">満枠</template>
                                                                <template v-else>残り{{ getRemainingCapacity(timeslot) }}枠</template>
                                                            </p>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">選択中の予約日時</label>
                                                <input
                                                    type="text"
                                                    :value="selectedTimeslot ? formatDateTime(selectedTimeslot.start_at) : (reservation.reservation_datetime ? formatDateTime(reservation.reservation_datetime) : '')"
                                                    readonly
                                                    class="w-full rounded-md border-gray-300 shadow-sm bg-gray-100"
                                                />
                                            </div>
                                        </template>
                                        <div v-if="form.errors.reservation_datetime" class="mt-1 text-sm text-red-600">{{ form.errors.reservation_datetime }}</div>
                                    </div>
                                    <div v-else class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">予約日時</label>
                                        <input
                                            v-model="form.reservation_datetime"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="例: 2024-12-25 14:00:00"
                                        />
                                        <div v-if="form.errors.reservation_datetime" class="mt-1 text-sm text-red-600">{{ form.errors.reservation_datetime }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">フリガナ</label>
                                        <input
                                            v-model="form.furigana"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div v-if="form.errors.furigana" class="mt-1 text-sm text-red-600">{{ form.errors.furigana }}</div>
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
                                        <div v-if="form.errors.has_visited_before" class="mt-1 text-sm text-red-600">{{ form.errors.has_visited_before }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">郵便番号</label>
                                        <input
                                            v-model="form.postal_code"
                                            type="text"
                                            placeholder="例: 700-0012"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div v-if="form.errors.postal_code" class="mt-1 text-sm text-red-600">{{ form.errors.postal_code }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">住所</label>
                                        <input
                                            v-model="form.address"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">生年月日</label>
                                        <input
                                            v-model="form.birth_date"
                                            type="date"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div v-if="form.errors.birth_date" class="mt-1 text-sm text-red-600">{{ form.errors.birth_date }}</div>
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
                                        <div v-if="form.errors.seijin_year" class="mt-1 text-sm text-red-600">{{ form.errors.seijin_year }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">学校名</label>
                                        <input
                                            v-model="form.school_name"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div v-if="form.errors.school_name" class="mt-1 text-sm text-red-600">{{ form.errors.school_name }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">担当者指名</label>
                                        <input
                                            v-model="form.staff_name"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div v-if="form.errors.staff_name" class="mt-1 text-sm text-red-600">{{ form.errors.staff_name }}</div>
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
                                        <div v-if="form.errors.visit_reasons" class="mt-1 text-sm text-red-600">{{ form.errors.visit_reasons }}</div>
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
                                        <div v-if="form.errors.parking_usage" class="mt-1 text-sm text-red-600">{{ form.errors.parking_usage }}</div>
                                    </div>

                                    <div v-if="form.parking_usage === 'あり'">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">駐車台数</label>
                                        <input
                                            v-model="form.parking_car_count"
                                            type="number"
                                            min="1"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div v-if="form.errors.parking_car_count" class="mt-1 text-sm text-red-600">{{ form.errors.parking_car_count }}</div>
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
                                        <div v-if="form.errors.considering_plans" class="mt-1 text-sm text-red-600">{{ form.errors.considering_plans }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">ご紹介者様お名前</label>
                                        <input
                                            v-model="form.referred_by_name"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div v-if="form.errors.referred_by_name" class="mt-1 text-sm text-red-600">{{ form.errors.referred_by_name }}</div>
                                    </div>
                                </template>

                                <!-- 資料請求フォーム (document) -->
                                <template v-if="event.form_type === 'document'">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">請求方法 <span class="text-red-500">*</span></label>
                                        <select
                                            v-model="form.request_method"
                                            required
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">選択してください</option>
                                            <option value="郵送">郵送</option>
                                            <option value="デジタルカタログ">デジタルカタログ</option>
                                        </select>
                                        <div v-if="form.errors.request_method" class="mt-1 text-sm text-red-600">{{ form.errors.request_method }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">フリガナ</label>
                                        <input
                                            v-model="form.furigana"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div v-if="form.errors.furigana" class="mt-1 text-sm text-red-600">{{ form.errors.furigana }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">生年月日</label>
                                        <input
                                            v-model="form.birth_date"
                                            type="date"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div v-if="form.errors.birth_date" class="mt-1 text-sm text-red-600">{{ form.errors.birth_date }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">郵便番号</label>
                                        <input
                                            v-model="form.postal_code"
                                            type="text"
                                            placeholder="例: 700-0012"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div v-if="form.errors.postal_code" class="mt-1 text-sm text-red-600">{{ form.errors.postal_code }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">住所</label>
                                        <input
                                            v-model="form.address"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address }}</div>
                                    </div>

                                    <div>
                                        <label class="flex items-start">
                                            <input
                                                v-model="form.privacy_agreed"
                                                type="checkbox"
                                                class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                            <span class="ml-2 text-sm text-gray-700">個人情報の取扱いについて同意する</span>
                                        </label>
                                        <div v-if="form.errors.privacy_agreed" class="mt-1 text-sm text-red-600">{{ form.errors.privacy_agreed }}</div>
                                    </div>
                                </template>

                                <!-- お問い合わせフォーム (contact) -->
                                <template v-if="event.form_type === 'contact'">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">問い合わせ回答方法</label>
                                        <select
                                            v-model="form.heard_from"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">選択してください</option>
                                            <option value="メール">メール</option>
                                            <option value="電話">電話</option>
                                        </select>
                                        <div v-if="form.errors.heard_from" class="mt-1 text-sm text-red-600">{{ form.errors.heard_from }}</div>
                                    </div>
                                </template>

                                <!-- 共通: お問い合わせ内容 -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">お問い合わせ内容</label>
                                    <textarea
                                        v-model="form.inquiry_message"
                                        rows="4"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    ></textarea>
                                    <div v-if="form.errors.inquiry_message" class="mt-1 text-sm text-red-600">{{ form.errors.inquiry_message }}</div>
                                </div>

                                <div class="flex justify-end space-x-4 pt-4">
                                    <Link
                                        :href="route('admin.events.reservations.index', reservation.event_id)"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                    >
                                        キャンセル
                                    </Link>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                                    >
                                        {{ form.processing ? '更新中...' : '更新' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    reservation: Object,
    event: Object,
    venues: Array,
    timeslots: Array,
});

// 選択された予約枠
const selectedTimeslot = ref(null);

// 選択会場でフィルタした予約枠（公開フォームと同様）
const filteredTimeslots = computed(() => {
    if (!props.timeslots || props.timeslots.length === 0) return [];
    if (!form.venue_id) return [];
    return props.timeslots.filter(t => !t.venue_id || t.venue_id == form.venue_id);
});

// 予約枠を日付ごとにグループ化（日付昇順）
const groupedTimeslots = computed(() => {
    if (!filteredTimeslots.value || filteredTimeslots.value.length === 0) return {};
    const sorted = [...filteredTimeslots.value].sort(
        (a, b) => new Date(a.start_at) - new Date(b.start_at)
    );
    const groups = {};
    sorted.forEach(timeslot => {
        const date = new Date(timeslot.start_at);
        const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        if (!groups[dateKey]) groups[dateKey] = [];
        groups[dateKey].push(timeslot);
    });
    Object.keys(groups).forEach(dateKey => {
        groups[dateKey].sort((a, b) => new Date(a.start_at) - new Date(b.start_at));
    });
    return groups;
});

// 日付キーを昇順で並べた配列
const sortedGroupedTimeslotDates = computed(() => Object.keys(groupedTimeslots.value).sort());

// 既存の予約日時に対応する予約枠を探す
if (props.reservation.reservation_datetime && props.timeslots && props.timeslots.length > 0) {
    const reservationDateTime = new Date(props.reservation.reservation_datetime);
    const matchingTimeslot = props.timeslots.find(timeslot => {
        const timeslotDate = new Date(timeslot.start_at);
        return timeslotDate.getTime() === reservationDateTime.getTime();
    });
    if (matchingTimeslot) {
        selectedTimeslot.value = matchingTimeslot;
    }
}

const selectTimeslot = (timeslot) => {
    selectedTimeslot.value = timeslot;
    // start_atをY-m-d H:i:s形式の文字列に変換
    const date = new Date(timeslot.start_at);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    form.reservation_datetime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
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

const formatDateTime = (datetime) => {
    if (!datetime) return '';
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getRemainingCapacity = (timeslot) => timeslot.remaining_capacity ?? 0;

/** 残りわずか: 残り1〜2枠 */
const isNokoriWazuka = (timeslot) => {
    const remaining = getRemainingCapacity(timeslot);
    return remaining >= 1 && remaining <= 2;
};

/** ねらい目: 残り3枠以上 かつ 予約率40%以上70%未満 */
const isNeraiMe = (timeslot) => {
    const remaining = getRemainingCapacity(timeslot);
    const capacity = timeslot.capacity ?? 0;
    if (remaining < 3 || capacity <= 0) return false;
    const rate = (capacity - remaining) / capacity;
    return rate >= 0.4 && rate < 0.7;
};

/** 表示するバッジ種別（残りわずか優先） */
const getTimeslotBadge = (timeslot) => {
    if (isNokoriWazuka(timeslot)) return 'nokori_wazuka';
    if (isNeraiMe(timeslot)) return 'nerai_me';
    return null;
};

// 来店動機の選択肢
const visitReasonOptions = [
    { value: '紹介', label: '紹介' },
    { value: 'DM・カタログ', label: 'DM・カタログ' },
    { value: 'SNS広告(Instaなど)', label: 'SNS広告(Instaなど)' },
    { value: 'WEB広告', label: 'WEB広告' },
    { value: 'その他', label: 'その他(テキスト入力)' },
];

// 来店動機を処理（「その他」の場合はテキスト入力も含める）
const processVisitReasons = (visitReasons, visitReasonOther) => {
    if (!visitReasons || !Array.isArray(visitReasons) || visitReasons.length === 0) {
        return null;
    }

    const reasons = [];
    visitReasons.forEach(reason => {
        if (reason === 'その他' && visitReasonOther) {
            reasons.push('その他(' + visitReasonOther + ')');
        } else {
            reasons.push(reason);
        }
    });

    return reasons.length > 0 ? reasons : null;
};

// 既存の来店動機から「その他」のテキストを抽出
const extractVisitReasonOther = (visitReasons) => {
    if (!visitReasons || !Array.isArray(visitReasons)) {
        return '';
    }
    const otherReason = visitReasons.find(r => r && typeof r === 'string' && r.startsWith('その他('));
    if (otherReason) {
        // 「その他(テキスト)」からテキスト部分を抽出
        const match = otherReason.match(/^その他\((.+)\)$/);
        if (match && match[1]) {
            return match[1];
        }
    }
    return '';
};

// 既存の来店動機から「その他」を除いた配列を取得
const getVisitReasonsWithoutOther = (visitReasons) => {
    if (!visitReasons || !Array.isArray(visitReasons)) {
        return [];
    }
    const reasons = [];
    let hasOther = false;
    
    visitReasons.forEach(r => {
        if (!r || typeof r !== 'string') {
            return;
        }
        // 「その他(テキスト)」形式の場合は「その他」として追加
        if (r.startsWith('その他(')) {
            if (!hasOther) {
                reasons.push('その他');
                hasOther = true;
            }
        } else {
            // その他の理由はそのまま追加
            reasons.push(r);
        }
    });
    
    return reasons;
};

// 生年月日を HTML5 date 入力用に YYYY-MM-DD に正規化
const normalizeBirthDate = (value) => {
    if (value == null || value === '') return '';
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
    try {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    } catch {
        return '';
    }
};

// 既存データを処理
const initialBirthDate = normalizeBirthDate(props.reservation.birth_date);
const initialStaffName = props.reservation.staff_name || '';
const initialVisitReasons = getVisitReasonsWithoutOther(props.reservation.visit_reasons);
const initialVisitReasonOther = extractVisitReasonOther(props.reservation.visit_reasons);

// デバッグ用（開発時のみ）
if (process.env.NODE_ENV === 'development') {
    console.log('予約データ:', {
        staff_name: props.reservation.staff_name,
        visit_reasons: props.reservation.visit_reasons,
        processed_visit_reasons: initialVisitReasons,
        visit_reason_other: initialVisitReasonOther,
    });
}

// フォーム初期化（全フィールドを含む）
const form = useForm({
    name: props.reservation.name || '',
    email: props.reservation.email || '',
    phone: props.reservation.phone || '',
    // 予約フォーム用
    reservation_datetime: props.reservation.reservation_datetime || '',
    venue_id: props.reservation.venue_id || null,
    has_visited_before: props.reservation.has_visited_before || false,
    furigana: props.reservation.furigana || '',
    address: props.reservation.address || '',
    birth_date: initialBirthDate,
    seijin_year: props.reservation.seijin_year || null,
    school_name: props.reservation.school_name || '',
    staff_name: initialStaffName,
    visit_reasons: initialVisitReasons,
    visit_reason_other: initialVisitReasonOther,
    parking_usage: props.reservation.parking_usage || '',
    parking_car_count: props.reservation.parking_car_count || null,
    considering_plans: props.reservation.considering_plans || [],
    referred_by_name: props.reservation.referred_by_name || '',
    // 資料請求フォーム用
    request_method: props.reservation.request_method || '',
    postal_code: props.reservation.postal_code || '',
    privacy_agreed: props.reservation.privacy_agreed || false,
    // お問い合わせフォーム用
    heard_from: props.reservation.heard_from || '',
    // 共通
    inquiry_message: props.reservation.inquiry_message || '',
});

// 検討中のプランの選択肢（予約フォーム用）
const availablePlans = [
    '振袖レンタルプラン',
    '振袖購入プラン',
    'ママ振りフォトプラン',
    'フォトレンタルプラン',
];

// 成人式予定年の選択肢（現在年から10年後まで）
const currentYear = new Date().getFullYear();
const seijinYears = Array.from({ length: 11 }, (_, i) => currentYear + i);

// 会場変更時: 選択枠がその会場のものでなければクリア
watch(() => form.venue_id, (newVenueId) => {
    if (!selectedTimeslot.value) return;
    const slotVenueId = selectedTimeslot.value.venue_id;
    if (slotVenueId != null && String(slotVenueId) !== String(newVenueId)) {
        selectedTimeslot.value = null;
        form.reservation_datetime = '';
    }
});

const submit = () => {
    // 来店動機を処理
    const processedVisitReasons = processVisitReasons(form.visit_reasons, form.visit_reason_other);
    
    form.transform((data) => ({
        ...data,
        visit_reasons: processedVisitReasons,
    })).put(route('admin.reservations.update', props.reservation.id));
};
</script>
