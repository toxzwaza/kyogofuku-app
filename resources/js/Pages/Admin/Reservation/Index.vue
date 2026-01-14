<template>
    <Head title="予約一覧" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    予約一覧 - {{ event.title }}
                </h2>
                <ActionButton
                    variant="back"
                    label="イベント詳細へ戻る"
                    :href="route('admin.events.show', event.id)"
                />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- 成功メッセージ -->
                <div v-if="$page.props.success" class="rounded-md bg-green-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ $page.props.success }}</p>
                        </div>
                    </div>
                </div>

                <!-- 操作ナビゲーション -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <EventNavigation :event="event" :show-url-button="false" />
                    </div>
                </div>

                <!-- 予約枠統計情報（予約フォームの場合のみ） -->
                <div v-if="event.form_type === 'reservation' && timeslotStats" class="mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">予約枠状況</h3>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                                    <div class="text-sm font-medium text-blue-700 mb-1">総枠数</div>
                                    <div class="text-2xl font-bold text-blue-900">{{ timeslotStats.total_capacity }}</div>
                                    <div class="text-xs text-blue-600 mt-1">枠</div>
                                </div>
                                <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-4 rounded-lg border border-orange-200">
                                    <div class="text-sm font-medium text-orange-700 mb-1">予約済み</div>
                                    <div class="text-2xl font-bold text-orange-900">{{ timeslotStats.total_reserved }}</div>
                                    <div class="text-xs text-orange-600 mt-1">枠</div>
                                </div>
                                <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                                    <div class="text-sm font-medium text-green-700 mb-1">残り枠数</div>
                                    <div class="text-2xl font-bold text-green-900">{{ timeslotStats.remaining }}</div>
                                    <div class="text-xs text-green-600 mt-1">枠</div>
                                </div>
                                <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
                                    <div class="text-sm font-medium text-purple-700 mb-1">埋まり率</div>
                                    <div class="text-2xl font-bold text-purple-900">{{ timeslotStats.occupancy_rate }}%</div>
                                    <div class="w-full bg-purple-200 rounded-full h-2 mt-2">
                                        <div 
                                            class="bg-purple-600 h-2 rounded-full transition-all duration-300"
                                            :style="{ width: timeslotStats.occupancy_rate + '%' }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- タブ（予約フォームの場合は日付表示とテーブル、それ以外はカードとテーブル） -->
                        <div v-if="event.form_type !== 'reservation'" class="border-b border-gray-200 mb-6">
                            <nav class="-mb-px flex space-x-8">
                                <button
                                    @click="activeTab = 'cards'"
                                    :class="[
                                        'py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200',
                                        activeTab === 'cards'
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    ]"
                                >
                                    <span class="inline-flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                        </svg>
                                        カード表示
                                    </span>
                                </button>
                                <button
                                    @click="activeTab = 'table'"
                                    :class="[
                                        'py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200',
                                        activeTab === 'table'
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    ]"
                                >
                                    <span class="inline-flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        テーブル表示
                                    </span>
                                </button>
                            </nav>
                        </div>

                        <div v-else class="border-b border-gray-200 mb-6">
                            <nav class="-mb-px flex space-x-8">
                                <button
                                    @click="activeTab = 'schedule'"
                                    :class="[
                                        'py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200',
                                        activeTab === 'schedule'
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    ]"
                                >
                                    <span class="inline-flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        日付表示
                                    </span>
                                </button>
                                <button
                                    @click="activeTab = 'table'"
                                    :class="[
                                        'py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200',
                                        activeTab === 'table'
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    ]"
                                >
                                    <span class="inline-flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        テーブル表示
                                    </span>
                                </button>
                            </nav>
                        </div>

                        <!-- 日付表示（予約フォームの場合のみ） -->
                        <div v-if="event.form_type === 'reservation' && activeTab === 'schedule'" class="space-y-6">
                            <!-- 絞り込みUI -->
                            <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="flex flex-col md:flex-row md:items-end gap-4">
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">会場</label>
                                        <select
                                            v-model="filterVenueId"
                                            @change="onVenueChange"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">すべて</option>
                                            <option v-for="venue in venues" :key="venue.id" :value="venue.id">
                                                {{ venue.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">時間</label>
                                        <select
                                            v-model="filterReservationDatetime"
                                            :disabled="!filterVenueId"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                        >
                                            <option value="">すべて</option>
                                            <option v-for="timeslot in availableTimeslots" :key="timeslot.id" :value="timeslot.start_at">
                                                {{ formatDateTime(timeslot.start_at) }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            @click="applyFilters"
                                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        >
                                            絞り込み
                                        </button>
                                        <button
                                            @click="resetFilters"
                                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                                        >
                                            リセット
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="groupedByVenue && Object.keys(groupedByVenue).length > 0">
                                <div
                                    v-for="(venueGroup, venueKey) in groupedByVenue"
                                    :key="venueKey"
                                    class="border-b border-gray-300 pb-8 last:border-b-0 last:pb-0"
                                >
                                    <h2 class="text-2xl font-bold mb-6 text-indigo-700">{{ getVenueName(venueKey) }}</h2>
                                    <div
                                        v-for="(dateGroup, date) in venueGroup"
                                        :key="date"
                                        class="border-b border-gray-200 pb-6 last:border-b-0 last:pb-0 mb-6"
                                    >
                                        <h3 class="text-xl font-semibold my-4 text-gray-800">{{ formatDateHeader(date) }}</h3>
                                        <div class="space-y-4">
                                        <div
                                            v-for="timeslot in dateGroup"
                                            :key="timeslot.id"
                                            :class="getTimeslotBorderClass(timeslot)"
                                            class="border-2 rounded-lg overflow-hidden"
                                        >
                                            <!-- 時間枠ヘッダー -->
                                            <div :class="getTimeslotHeaderClass(timeslot)" class="px-4 py-3 border-b border-gray-200">
                                                <div class="flex justify-between items-center">
                                                    <div class="flex items-center space-x-4">
                                                        <span class="text-lg font-semibold text-gray-900">{{ formatTime(timeslot.start_at) }}</span>
                                                        <span class="text-sm text-gray-600">
                                                            定員: {{ timeslot.capacity }}枠
                                                            <span class="mx-2">|</span>
                                                            予約済み: {{ timeslot.reservations.length }}枠
                                                            <span class="mx-2">|</span>
                                                            残り: <span :class="timeslot.remaining_capacity > 0 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'">{{ timeslot.remaining_capacity }}枠</span>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center space-x-2">
                                                        <button
                                                            v-if="timeslot.remaining_capacity > 0"
                                                            @click="openTextReservationModal(timeslot)"
                                                            class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-purple-600 to-purple-700 rounded-lg shadow-sm hover:from-purple-700 hover:to-purple-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-all duration-200"
                                                            title="テキストから予約登録"
                                                        >
                                                            テキストから予約登録
                                                        </button>
                                                        <Link
                                                            v-if="timeslot.remaining_capacity > 0"
                                                            :href="getReservationUrl(timeslot)"
                                                            class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-sm hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200"
                                                            title="予約登録"
                                                        >
                                                            予約登録
                                                        </Link>
                                                        <span
                                                            v-else
                                                            class="px-3 py-1.5 text-sm font-medium text-gray-400 bg-gray-200 rounded-lg cursor-not-allowed"
                                                            title="枠なし"
                                                        >
                                                            枠なし
                                                        </span>
                                                        <button
                                                            @click="adjustCapacity(timeslot.id, -1)"
                                                            :disabled="timeslot.capacity <= timeslot.reservations.length || adjustingTimeslotId === timeslot.id"
                                                            class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-orange-600 to-orange-700 rounded-lg shadow-sm hover:from-orange-700 hover:to-orange-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                                                            title="枠を1つ減らす"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                                            </svg>
                                                        </button>
                                                        <button
                                                            @click="adjustCapacity(timeslot.id, 1)"
                                                            :disabled="adjustingTimeslotId === timeslot.id"
                                                            class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-green-600 to-green-700 rounded-lg shadow-sm hover:from-green-700 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200"
                                                            title="枠を1つ増やす"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                            </svg>
                                                        </button>
                                                        <button
                                                            @click="adjustCapacity(timeslot.id, 5)"
                                                            :disabled="adjustingTimeslotId === timeslot.id"
                                                            class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                                            title="枠を5つ増やす"
                                                        >
                                                            +5
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 予約一覧 -->
                                            <div v-if="timeslot.reservations && timeslot.reservations.length > 0" class="divide-y divide-gray-200">
                                                <div
                                                    v-for="reservation in timeslot.reservations"
                                                    :key="reservation.id"
                                                    class="p-4 hover:bg-gray-50 transition-colors duration-150"
                                                >
                                                    <div class="flex justify-between items-start">
                                                        <div class="flex-1">
                                                            <div class="flex items-center space-x-3 mb-2">
                                                                <span class="text-lg font-semibold text-gray-900">{{ reservation.name }}</span>
                                                                <span class="text-xs text-gray-500">ID: {{ reservation.id }}</span>
                                                                <span v-if="reservation.status" :class="getStatusBadgeClass(reservation.status)" class="px-2 py-0.5 text-xs font-medium rounded-full">
                                                                    {{ reservation.status }}<span v-if="reservation.status_updated_by">:{{ reservation.status_updated_by.name }}</span>
                                                                </span>

                                                                <span v-if="reservation.venue" class="px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded-full">
                                                                    {{ reservation.venue.name }}
                                                                </span>
                                                            </div>
                                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600">
                                                                <div class="flex items-center">
                                                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                                    </svg>
                                                                    {{ reservation.email }}
                                                                </div>
                                                                <div class="flex items-center">
                                                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                                    </svg>
                                                                    {{ reservation.phone }}
                                                                </div>
                                                                <div v-if="reservation.furigana" class="flex items-center">
                                                                    <span class="text-gray-500 mr-2">フリガナ:</span>
                                                                    {{ reservation.furigana }}
                                                                </div>
                                                                <div v-if="reservation.has_visited_before !== null" class="flex items-center">
                                                                    <span class="text-gray-500 mr-2">過去来店:</span>
                                                                    <span :class="reservation.has_visited_before ? 'text-green-600' : 'text-gray-600'">
                                                                        {{ reservation.has_visited_before ? 'あり' : 'なし' }}
                                                                    </span>
                                                                </div>
                                                                <div v-if="reservation.considering_plans && reservation.considering_plans.length > 0" class="md:col-span-2">
                                                                    <span class="text-gray-500 mr-2">検討プラン:</span>
                                                                    <span
                                                                        v-for="plan in reservation.considering_plans"
                                                                        :key="plan"
                                                                        class="inline-block px-2 py-0.5 text-xs bg-indigo-100 text-indigo-800 rounded-full mr-1"
                                                                    >
                                                                        {{ plan }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="text-xs text-gray-500 mt-2">
                                                                登録日時: {{ formatDateTime(reservation.created_at) }}
                                                            </div>
                                                        </div>
                                                        <div class="flex space-x-2 ml-4">
                                                            <Link
                                                                :href="route('admin.reservations.show', reservation.id)"
                                                                class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                                            >
                                                                詳細
                                                            </Link>
                                                            <button
                                                                @click="deleteReservation(reservation.id)"
                                                                class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 rounded-lg shadow-sm hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                                                            >
                                                                削除
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="p-4 text-center text-gray-500 text-sm">
                                                この時間枠には予約がありません
                                            </div>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-12 text-gray-500">
                                予約枠が登録されていません
                            </div>
                        </div>

                        <!-- カード表示（資料請求・お問い合わせの場合のみ） -->
                        <div v-if="event.form_type !== 'reservation' && activeTab === 'cards'" class="space-y-4">
                            <div v-if="reservations.data && reservations.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div
                                    v-for="reservation in reservations.data"
                                    :key="reservation.id"
                                    class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200 overflow-hidden"
                                >
                                    <div class="p-5">
                                        <!-- ヘッダー -->
                                        <div class="flex justify-between items-start mb-4">
                                            <div>
                                                <div class="text-lg font-semibold text-gray-900 mb-1">
                                                    {{ reservation.name }}
                                                </div>
                                                <div class="text-xs text-gray-500">ID: {{ reservation.id }}</div>
                                            </div>
                                            <div class="flex flex-col items-end space-y-1">
                                                <span
                                                    v-if="reservation.status"
                                                    :class="getStatusBadgeClass(reservation.status)"
                                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                                >
                                                    {{ reservation.status }}<span v-if="reservation.status_updated_by">:{{ reservation.status_updated_by.name }}</span>
                                                </span>
                                                <span
                                                    v-if="reservation.status_updated_by"
                                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                                    :class="getStatusBadgeClass(reservation.status)"
                                                >
                                                    {{ reservation.status_updated_by.name }}
                                                </span>
                                                <span
                                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                                    :class="getFormTypeBadgeClass(event.form_type)"
                                                >
                                                    {{ getFormTypeLabel(event.form_type) }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- 基本情報 -->
                                        <div class="space-y-2 mb-4">
                                            <div class="flex items-start text-sm">
                                                <svg class="w-4 h-4 mr-2 mt-0.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                </svg>
                                                <span class="text-gray-700 break-all">{{ reservation.email }}</span>
                                            </div>
                                            <div class="flex items-start text-sm">
                                                <svg class="w-4 h-4 mr-2 mt-0.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                </svg>
                                                <span class="text-gray-700">{{ reservation.phone || '-' }}</span>
                                            </div>
                                        </div>

                                        <div v-if="event.form_type === 'document'" class="space-y-2 mb-4 pb-4 border-b border-gray-100">
                                            <div v-if="reservation.request_method" class="flex items-start text-sm">
                                                <span class="text-gray-500 mr-2">請求方法:</span>
                                                <span class="text-gray-700 font-medium">{{ reservation.request_method }}</span>
                                            </div>
                                            <div v-if="reservation.postal_code" class="flex items-start text-sm">
                                                <span class="text-gray-500 mr-2">郵便番号:</span>
                                                <span class="text-gray-700">{{ reservation.postal_code }}</span>
                                            </div>
                                        </div>

                                        <div v-if="event.form_type === 'contact'" class="space-y-2 mb-4 pb-4 border-b border-gray-100">
                                            <div v-if="reservation.heard_from" class="flex items-start text-sm">
                                                <span class="text-gray-500 mr-2">回答方法:</span>
                                                <span class="text-gray-700 font-medium">{{ reservation.heard_from }}</span>
                                            </div>
                                        </div>

                                        <!-- 登録日時 -->
                                        <div class="text-xs text-gray-500 mb-4">
                                            登録: {{ formatDateTime(reservation.created_at) }}
                                        </div>

                                        <!-- 操作ボタン -->
                                        <div class="flex space-x-2">
                                            <Link
                                                :href="route('admin.reservations.show', reservation.id)"
                                                class="flex-1 inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                            >
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                詳細
                                            </Link>
                                            <button
                                                @click="deleteReservation(reservation.id)"
                                                class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 rounded-lg shadow-sm hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-12 text-gray-500">
                                予約データがありません
                            </div>
                        </div>

                        <!-- テーブル表示 -->
                        <div v-if="activeTab === 'table'">
                            <!-- 絞り込みUI（予約フォームの場合のみ） -->
                            <div v-if="event.form_type === 'reservation'" class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="flex flex-col md:flex-row md:items-end gap-4">
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">会場</label>
                                        <select
                                            v-model="filterVenueId"
                                            @change="onVenueChange"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">すべて</option>
                                            <option v-for="venue in venues" :key="venue.id" :value="venue.id">
                                                {{ venue.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">時間</label>
                                        <select
                                            v-model="filterReservationDatetime"
                                            :disabled="!filterVenueId"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                        >
                                            <option value="">すべて</option>
                                            <option v-for="timeslot in availableTimeslots" :key="timeslot.id" :value="timeslot.start_at">
                                                {{ formatDateTime(timeslot.start_at) }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            @click="applyFilters"
                                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        >
                                            絞り込み
                                        </button>
                                        <button
                                            @click="resetFilters"
                                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                                        >
                                            リセット
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ステータス</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ステータス更新者</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">お名前</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">メール</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">電話番号</th>
                                        
                                        <!-- 予約フォームの場合 -->
                                        <template v-if="event.form_type === 'reservation'">
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ご来店会場</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">予約日時</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">フリガナ</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">過去来店</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">住所</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">生年月日</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">成人式予定年</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">学校名</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">駐車場利用</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">検討中のプラン</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ご紹介者様</th>
                                        </template>
                                        
                                        <!-- 資料請求フォームの場合 -->
                                        <template v-if="event.form_type === 'document'">
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">請求方法</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">フリガナ</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">生年月日</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">郵便番号</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">住所</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">個人情報同意</th>
                                        </template>
                                        
                                        <!-- お問い合わせフォームの場合 -->
                                        <template v-if="event.form_type === 'contact'">
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">問い合わせ回答方法</th>
                                        </template>
                                        
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">登録日時</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="reservation in reservations.data" :key="reservation.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span v-if="reservation.status" :class="getStatusBadgeClass(reservation.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                                                {{ reservation.status }}<span v-if="reservation.status_updated_by">:{{ reservation.status_updated_by.name }}</span>
                                            </span>
                                            <span v-else class="text-sm text-gray-500">-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.status_updated_by ? reservation.status_updated_by.name : '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.email }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.phone }}</td>
                                        
                                        <!-- 予約フォームの場合 -->
                                        <template v-if="event.form_type === 'reservation'">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ reservation.venue ? reservation.venue.name : '-' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ reservation.reservation_datetime ? formatDateTime(reservation.reservation_datetime) : '-' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.furigana || '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.has_visited_before ? 'あり' : 'なし' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.address || '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.birth_date ? formatDate(reservation.birth_date) : '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.seijin_year ? reservation.seijin_year + '年' : '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.school_name || '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.parking_usage || '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ reservation.considering_plans && reservation.considering_plans.length > 0 ? reservation.considering_plans.join(', ') : '-' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.referred_by_name || '-' }}</td>
                                        </template>
                                        
                                        <!-- 資料請求フォームの場合 -->
                                        <template v-if="event.form_type === 'document'">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.request_method || '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.furigana || '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.birth_date ? formatDate(reservation.birth_date) : '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.postal_code || '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.address || '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.privacy_agreed ? '同意' : '-' }}</td>
                                        </template>
                                        
                                        <!-- お問い合わせフォームの場合 -->
                                        <template v-if="event.form_type === 'contact'">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.heard_from || '-' }}</td>
                                        </template>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatDateTime(reservation.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link
                                                :href="route('admin.reservations.show', reservation.id)"
                                                class="text-indigo-600 hover:text-indigo-900 mr-4"
                                            >
                                                詳細
                                            </Link>
                                            <button
                                                @click="deleteReservation(reservation.id)"
                                                class="text-red-600 hover:text-red-900"
                                            >
                                                削除
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>

                        <!-- ページネーション -->
                        <div v-if="reservations.links && reservations.links.length > 3" class="mt-6">
                            <div class="flex justify-center">
                                <Link
                                    v-for="link in reservations.links"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="[
                                        'px-4 py-2 mx-1 rounded-md text-sm',
                                        link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300',
                                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                                    ]"
                                >
                                    <span v-html="link.label"></span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- テキストから予約登録モーダル -->
        <div
            v-if="showTextReservationModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background-color: rgba(0, 0, 0, 0.5);"
            @click.self="closeTextReservationModal"
        >
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center z-10">
                    <h2 class="text-2xl font-bold">テキストから予約登録</h2>
                    <button
                        @click="closeTextReservationModal"
                        class="text-gray-500 hover:text-gray-700"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            テキストを貼り付けてください
                        </label>
                        <textarea
                            v-model="textReservationInput"
                            rows="15"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                            placeholder="【 イベント名 】 FURISODE EXPO in 岡山&#10;&#10;【 ご来店会場 】 岡山プラザホテル&#10;&#10;【 ご予約希望日時 】 2026/1/25(日) 12:30&#10;&#10;【 過去当店にご来店 】 ない&#10;&#10;【 お嬢様名 】 村本莉愛&#10;&#10;【 お嬢様名(ふりがな) 】 むらもりあ&#10;&#10;【 住所 】 岡山県倉敷市水島青葉町2-1-14&#10;&#10;【 Email 】 haruria.2255@docomo.ne.jp&#10;&#10;【 電話番号 】 09052670049&#10;&#10;【 生年月日 】 2007年 5月 5日&#10;&#10;【 ご検討中のプラン 】 振袖レンタルプラン&#10;&#10;【 お問い合わせ内容 】&#10;&#10;【 ご紹介者様お名前 】"
                        ></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button
                            @click="closeTextReservationModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                        >
                            キャンセル
                        </button>
                        <button
                            @click="proceedToReservationFromText"
                            class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                        >
                            予約登録へ進む
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import ActionButton from '@/Components/ActionButton.vue';
import EventNavigation from '@/Components/EventNavigation.vue';

const props = defineProps({
    event: Object,
    reservations: Object,
    timeslotStats: Object,
    timeslotsWithReservations: Array,
    venues: Array,
    filterTimeslots: Array,
    filters: Object,
    success: String,
});

// 予約フォームの場合は日付表示をデフォルト、それ以外はカード表示をデフォルト
const activeTab = ref(props.event.form_type === 'reservation' ? 'schedule' : 'cards');
const adjustingTimeslotId = ref(null);

// テキストから予約登録モーダルの状態
const showTextReservationModal = ref(false);
const textReservationInput = ref('');
const selectedTimeslotForTextReservation = ref(null);

// 絞り込み用のstate
const filterVenueId = ref(props.filters?.venue_id || '');
const filterReservationDatetime = ref(props.filters?.reservation_datetime || '');

// 選択可能な時間リスト（会場が選択されている場合、その会場の時間のみ表示）
const availableTimeslots = computed(() => {
    if (!filterVenueId.value) {
        // 会場が選択されていない場合、すべての時間を返す
        return props.filterTimeslots || [];
    }
    // 会場が選択されている場合、その会場の時間のみ返す
    return (props.filterTimeslots || []).filter(timeslot => {
        return timeslot.venue_id == filterVenueId.value;
    });
});

// 会場変更時の処理
const onVenueChange = () => {
    // 会場が変更されたら時間をリセット
    filterReservationDatetime.value = '';
};

// フィルターを適用
const applyFilters = () => {
    const params = {};
    if (filterVenueId.value) {
        params.venue_id = filterVenueId.value;
    }
    if (filterReservationDatetime.value) {
        params.reservation_datetime = filterReservationDatetime.value;
    }
    
    router.get(route('admin.events.reservations.index', props.event.id), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

// フィルターをリセット
const resetFilters = () => {
    filterVenueId.value = '';
    filterReservationDatetime.value = '';
    router.get(route('admin.events.reservations.index', props.event.id), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

// 予約枠を会場ごと、その中で日付ごとにグループ化
const groupedByVenue = computed(() => {
    if (!props.timeslotsWithReservations || props.timeslotsWithReservations.length === 0) return {};
    
    // まず会場ごとにグループ化
    const venueGroups = {};
    props.timeslotsWithReservations.forEach(timeslot => {
        // venue_idがnullの場合は「会場未設定」として扱う
        const venueKey = timeslot.venue_id || 'no_venue';
        if (!venueGroups[venueKey]) {
            venueGroups[venueKey] = {};
        }
        
        // 日付ごとにグループ化
        const date = new Date(timeslot.start_at);
        const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        if (!venueGroups[venueKey][dateKey]) {
            venueGroups[venueKey][dateKey] = [];
        }
        venueGroups[venueKey][dateKey].push(timeslot);
    });
    
    // 各会場の各日付の枠を時間順にソート
    Object.keys(venueGroups).forEach(venueKey => {
        Object.keys(venueGroups[venueKey]).forEach(dateKey => {
            venueGroups[venueKey][dateKey].sort((a, b) => {
                return new Date(a.start_at) - new Date(b.start_at);
            });
        });
    });
    
    return venueGroups;
});

// 会場名を取得
const getVenueName = (venueKey) => {
    if (venueKey === 'no_venue') {
        return '会場未設定';
    }
    // venueKeyはvenue_idなので、timeslotsWithReservationsから該当するvenueを探す
    const timeslot = props.timeslotsWithReservations.find(t => t.venue_id == venueKey);
    return timeslot?.venue?.name || `会場ID: ${venueKey}`;
};

// 予約登録URLを生成（予約枠IDのみ）
const getReservationUrl = (timeslot) => {
    const baseUrl = route('event.show', props.event.slug);
    const params = new URLSearchParams({
        from_admin: '1',
    });
    
    // 予約枠IDのみを追加
    if (timeslot.id) {
        params.append('timeslot_id', timeslot.id);
    }
    
    return `${baseUrl}?${params.toString()}`;
};

const formatDateTime = (datetime) => {
    if (!datetime) return '-';
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP');
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP');
};

const formatDateHeader = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'long',
    });
};

const formatTime = (datetime) => {
    const date = new Date(datetime);
    return date.toLocaleTimeString('ja-JP', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getFormTypeLabel = (formType) => {
    const labels = {
        reservation: '予約',
        document: '資料請求',
        contact: '問い合わせ',
    };
    return labels[formType] || formType;
};

const getFormTypeBadgeClass = (formType) => {
    const classes = {
        reservation: 'bg-blue-100 text-blue-800',
        document: 'bg-green-100 text-green-800',
        contact: 'bg-purple-100 text-purple-800',
    };
    return classes[formType] || 'bg-gray-100 text-gray-800';
};

const getStatusBadgeClass = (status) => {
    const classes = {
        '未対応': 'bg-gray-100 text-gray-800',
        '確認中': 'bg-yellow-100 text-yellow-800',
        '返信待ち': 'bg-blue-100 text-blue-800',
        '対応完了済み': 'bg-green-100 text-green-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

// 色分けロジックの修正版
const getTimeslotStatus = (timeslot) => {
    if (!timeslot.reservations || timeslot.reservations.length === 0) return null;
    // 優先度順
    const pri = ['未対応', '確認中', '返信待ち', '対応完了済み'];
    for (const st of pri) {
        if (timeslot.reservations.some(r => r.status === st)) return st;
    }
    return null;
};
// 時間枠全体の色
const getTimeslotAllDone = (timeslot) => {
  if (!timeslot.reservations || timeslot.reservations.length === 0) return false;
  return timeslot.reservations.every(r => r.status === '対応完了済み');
};
const getTimeslotHeaderClass = (timeslot) => getTimeslotAllDone(timeslot) ? 'bg-green-100' : 'bg-gray-100';
const getTimeslotBorderClass = (timeslot) => getTimeslotAllDone(timeslot) ? 'border-green-400' : 'border-gray-300';

const deleteReservation = (id) => {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('admin.reservations.destroy', id));
    }
};

const adjustCapacity = async (timeslotId, amount) => {
    if (adjustingTimeslotId.value === timeslotId) return;
    
    adjustingTimeslotId.value = timeslotId;
    
    try {
        const response = await axios.post(route('admin.timeslots.adjust-capacity', timeslotId), {
            amount: amount,
        });
        
        if (response.data.success) {
            // ページをリロードして最新の状態を取得
            router.visit(route('admin.events.reservations.index', props.event.id), {
                preserveScroll: true,
                only: ['timeslotsWithReservations', 'timeslotStats'],
            });
        } else {
            alert(response.data.message || '枠数の変更に失敗しました。');
            adjustingTimeslotId.value = null;
        }
    } catch (error) {
        if (error.response && error.response.data && error.response.data.message) {
            alert(error.response.data.message);
        } else {
            alert('枠数の変更に失敗しました。');
        }
        adjustingTimeslotId.value = null;
    }
};

// テキストから予約登録モーダルを開く
const openTextReservationModal = (timeslot) => {
    selectedTimeslotForTextReservation.value = timeslot;
    textReservationInput.value = '';
    showTextReservationModal.value = true;
};

// テキストから予約登録モーダルを閉じる
const closeTextReservationModal = () => {
    showTextReservationModal.value = false;
    textReservationInput.value = '';
    selectedTimeslotForTextReservation.value = null;
};

// テキストから情報を抽出する関数
const parseTextReservation = (text) => {
    const data = {};
    
    // 【 過去当店にご来店 】 ない/あり
    const hasVisitedMatch = text.match(/【\s*過去当店にご来店\s*】\s*([^\n\r]+)/);
    if (hasVisitedMatch) {
        const value = hasVisitedMatch[1].trim();
        data.has_visited_before = value === 'あり' || value === 'ある';
    }
    
    // 【 お嬢様名 】 村本莉愛
    const nameMatch = text.match(/【\s*お嬢様名\s*】\s*([^\n\r]+)/);
    if (nameMatch) {
        data.name = nameMatch[1].trim();
    }
    
    // 【 お嬢様名(ふりがな) 】 むらもりあ
    const furiganaMatch = text.match(/【\s*お嬢様名\s*\(ふりがな\)\s*】\s*([^\n\r]+)/);
    if (furiganaMatch) {
        data.furigana = furiganaMatch[1].trim();
    }
    
    // 【 住所 】 岡山県倉敷市水島青葉町2-1-14
    const addressMatch = text.match(/【\s*住所\s*】\s*([^\n\r]+)/);
    if (addressMatch) {
        data.address = addressMatch[1].trim();
    }
    
    // 【 Email 】 haruria.2255@docomo.ne.jp
    const emailMatch = text.match(/【\s*Email\s*】\s*([^\n\r]+)/);
    if (emailMatch) {
        data.email = emailMatch[1].trim();
    }
    
    // 【 電話番号 】 09052670049
    const phoneMatch = text.match(/【\s*電話番号\s*】\s*([^\n\r]+)/);
    if (phoneMatch) {
        data.phone = phoneMatch[1].trim();
    }
    
    // 【 生年月日 】 2007年 5月 5日
    const birthDateMatch = text.match(/【\s*生年月日\s*】\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日/);
    if (birthDateMatch) {
        const year = birthDateMatch[1];
        const month = String(birthDateMatch[2]).padStart(2, '0');
        const day = String(birthDateMatch[3]).padStart(2, '0');
        data.birth_date = `${year}-${month}-${day}`;
    }
    
    // 【 ご検討中のプラン 】 振袖レンタルプラン
    const planMatch = text.match(/【\s*ご検討中のプラン\s*】\s*([^\n\r]+)/);
    if (planMatch) {
        const planText = planMatch[1].trim();
        // プラン名を配列に変換（カンマ区切りの可能性を考慮）
        const plans = planText.split(/[,、]/).map(p => p.trim()).filter(p => p);
        data.considering_plans = plans;
    }
    
    // 【 お問い合わせ内容 】
    const inquiryMatch = text.match(/【\s*お問い合わせ内容\s*】\s*([\s\S]*?)(?=\n\s*【|$)/);
    if (inquiryMatch) {
        data.inquiry_message = inquiryMatch[1].trim();
    }
    
    // 【 ご紹介者様お名前 】
    const referredByMatch = text.match(/【\s*ご紹介者様お名前\s*】\s*([^\n\r]+)/);
    if (referredByMatch) {
        data.referred_by_name = referredByMatch[1].trim();
    }
    
    return data;
};

// テキストから予約登録画面に遷移
const proceedToReservationFromText = () => {
    if (!selectedTimeslotForTextReservation.value) {
        alert('予約枠が選択されていません。');
        return;
    }
    
    if (!textReservationInput.value.trim()) {
        alert('テキストを入力してください。');
        return;
    }
    
    // テキストから情報を抽出
    const extractedData = parseTextReservation(textReservationInput.value);
    
    // 予約登録URLを生成
    const baseUrl = route('event.show', props.event.slug);
    const params = new URLSearchParams({
        from_admin: '1',
        timeslot_id: selectedTimeslotForTextReservation.value.id,
    });
    
    // 抽出したデータをURLパラメータに追加
    Object.keys(extractedData).forEach(key => {
        const value = extractedData[key];
        if (value !== null && value !== undefined && value !== '') {
            if (Array.isArray(value)) {
                // 配列の場合はカンマ区切りで追加
                params.append(key, value.join(','));
            } else {
                params.append(key, value);
            }
        }
    });
    
    // モーダルを閉じて遷移
    closeTextReservationModal();
    window.location.href = `${baseUrl}?${params.toString()}`;
};

onMounted(() => {
    if (props.success) {
        alert(props.success);
    }
});
</script>
