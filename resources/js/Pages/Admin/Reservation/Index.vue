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
              <svg
                class="h-5 w-5 text-green-400"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-green-800">
                {{ $page.props.success }}
              </p>
            </div>
          </div>
        </div>

        <!-- エラーメッセージ（キャンセル解除失敗時など） -->
        <div
          v-if="$page.props.flash?.error"
          class="rounded-md bg-red-50 p-4 border border-red-200"
        >
          <p class="text-sm font-medium text-red-800">
            {{ $page.props.flash.error }}
          </p>
        </div>

        <!-- 枠増減の成功メッセージ -->
        <div
          v-if="adjustCapacitySuccessMessage"
          class="rounded-md bg-green-50 p-4 border border-green-200"
        >
          <p class="text-sm font-medium text-green-800">
            {{ adjustCapacitySuccessMessage }}
          </p>
        </div>

        <!-- 操作ナビゲーション -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <EventNavigation :event="event" :show-url-button="false" />
          </div>
        </div>

        <!-- 予約枠統計情報（予約フォームの場合のみ） -->
        <div
          v-if="event.form_type === 'reservation' && timeslotStats"
          class="mb-6"
        >
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-semibold mb-4 text-gray-800">
                予約枠状況
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div
                  class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200"
                >
                  <div class="text-sm font-medium text-blue-700 mb-1">
                    総枠数
                  </div>
                  <div class="text-2xl font-bold text-blue-900">
                    {{ timeslotStats.total_capacity }}
                  </div>
                  <div class="text-xs text-blue-600 mt-1">枠</div>
                </div>
                <div
                  class="bg-gradient-to-br from-orange-50 to-orange-100 p-4 rounded-lg border border-orange-200"
                >
                  <div class="text-sm font-medium text-orange-700 mb-1">
                    予約済み
                  </div>
                  <div class="text-2xl font-bold text-orange-900">
                    {{ timeslotStats.total_reserved }}
                  </div>
                  <div class="text-xs text-orange-600 mt-1">枠</div>
                </div>
                <div
                  class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200"
                >
                  <div class="text-sm font-medium text-green-700 mb-1">
                    残り枠数
                  </div>
                  <div class="text-2xl font-bold text-green-900">
                    {{ timeslotStats.remaining }}
                  </div>
                  <div class="text-xs text-green-600 mt-1">枠</div>
                </div>
                <div
                  class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200"
                >
                  <div class="text-sm font-medium text-purple-700 mb-1">
                    埋まり率
                  </div>
                  <div class="text-2xl font-bold text-purple-900">
                    {{ timeslotStats.occupancy_rate }}%
                  </div>
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
            <div
              v-if="event.form_type !== 'reservation'"
              class="border-b border-gray-200 mb-6"
            >
              <nav class="-mb-px flex space-x-8">
                <button
                  @click="activeTab = 'cards'"
                  :class="[
                    'py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200',
                    activeTab === 'cards'
                      ? 'border-indigo-500 text-indigo-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                  ]"
                >
                  <span class="inline-flex items-center">
                    <svg
                      class="w-5 h-5 mr-2"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                      />
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
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                  ]"
                >
                  <span class="inline-flex items-center">
                    <svg
                      class="w-5 h-5 mr-2"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
                      />
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
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                  ]"
                >
                  <span class="inline-flex items-center">
                    <svg
                      class="w-5 h-5 mr-2"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                      />
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
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                  ]"
                >
                  <span class="inline-flex items-center">
                    <svg
                      class="w-5 h-5 mr-2"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
                      />
                    </svg>
                    テーブル表示
                  </span>
                </button>
              </nav>
            </div>

            <!-- 日付表示（予約フォームの場合のみ） -->
            <div
              v-if="
                event.form_type === 'reservation' && activeTab === 'schedule'
              "
              class="space-y-6"
            >
              <!-- 絞り込みUI -->
              <div
                class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200"
              >
                <div class="flex flex-col md:flex-row md:items-end gap-4">
                  <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2"
                      >会場</label
                    >
                    <select
                      v-model="filterVenueId"
                      @change="onVenueChangeAndApply"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                      <option value="">すべて</option>
                      <option
                        v-for="venue in venues"
                        :key="venue.id"
                        :value="venue.id"
                      >
                        {{ venue.name }}
                      </option>
                    </select>
                  </div>
                  <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2"
                      >日付</label
                    >
                    <select
                      v-model="filterReservationDate"
                      @change="onDateChange"
                      :disabled="!filterVenueId"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                    >
                      <option value="">すべて</option>
                      <option
                        v-for="date in availableDates"
                        :key="date"
                        :value="date"
                      >
                        {{ formatDateForOption(date) }}
                      </option>
                    </select>
                  </div>
                  <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2"
                      >時間</label
                    >
                    <select
                      v-model="filterReservationTime"
                      @change="applyFilters"
                      :disabled="!filterVenueId || !filterReservationDate"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                    >
                      <option value="">すべて</option>
                      <option
                        v-for="time in availableTimes"
                        :key="time"
                        :value="time"
                      >
                        {{ time }}
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

              <div
                v-if="groupedByVenue && Object.keys(groupedByVenue).length > 0"
              >
                <div
                  v-for="(venueGroup, venueKey) in groupedByVenue"
                  :key="venueKey"
                  class="border-b border-gray-300 pb-8 last:border-b-0 last:pb-0"
                >
                  <h2 class="text-2xl font-bold mb-6 text-indigo-700">
                    {{ getVenueName(venueKey) }}
                  </h2>
                  <div
                    v-for="(dateGroup, date) in venueGroup"
                    :key="date"
                    class="border-b border-gray-200 pb-6 last:border-b-0 last:pb-0 mb-6"
                  >
                    <h3 class="text-xl font-semibold my-4 text-gray-800">
                      {{ formatDateHeader(date) }}
                    </h3>
                    <div class="space-y-4">
                      <div
                        v-for="timeslot in dateGroup"
                        :key="timeslot.id"
                        :class="getTimeslotBorderClass(timeslot)"
                        class="border-2 rounded-lg overflow-hidden"
                      >
                        <!-- 時間枠ヘッダー -->
                        <div
                          :class="getTimeslotHeaderClass(timeslot)"
                          class="px-4 py-3 border-b border-gray-200"
                        >
                          <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                              <span
                                class="text-lg font-semibold text-gray-900"
                                >{{ formatTime(timeslot.start_at) }}</span
                              >
                              <span class="text-sm text-gray-600">
                                定員: {{ timeslot.capacity }}枠
                                <span class="mx-2">|</span>
                                予約済み: {{ timeslot.reservations.length }}枠
                                <span class="mx-2">|</span>
                                残り:
                                <span
                                  :class="
                                    timeslot.remaining_capacity > 0
                                      ? 'text-green-600 font-semibold'
                                      : 'text-red-600 font-semibold'
                                  "
                                  >{{ timeslot.remaining_capacity }}枠</span
                                >
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
                                :disabled="
                                  timeslot.remaining_capacity <= 0 ||
                                  adjustingTimeslotId === timeslot.id
                                "
                                class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-orange-600 to-orange-700 rounded-lg shadow-sm hover:from-orange-700 hover:to-orange-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                                title="枠を1つ減らす"
                              >
                                <svg
                                  class="w-4 h-4"
                                  fill="none"
                                  stroke="currentColor"
                                  viewBox="0 0 24 24"
                                >
                                  <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M20 12H4"
                                  />
                                </svg>
                              </button>
                              <button
                                @click="adjustCapacity(timeslot.id, 1)"
                                :disabled="adjustingTimeslotId === timeslot.id"
                                class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-green-600 to-green-700 rounded-lg shadow-sm hover:from-green-700 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200"
                                title="枠を1つ増やす"
                              >
                                <svg
                                  class="w-4 h-4"
                                  fill="none"
                                  stroke="currentColor"
                                  viewBox="0 0 24 24"
                                >
                                  <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 4v16m8-8H4"
                                  />
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
                        <div
                          v-if="
                            timeslot.reservations &&
                            timeslot.reservations.length > 0
                          "
                          class="divide-y divide-gray-200"
                        >
                          <div
                            v-for="reservation in timeslot.reservations"
                            :key="reservation.id"
                            :class="[
                              'p-4 transition-colors duration-150',
                              reservation.cancel_flg ? 'bg-gray-200' : 'hover:bg-gray-50',
                            ]"
                          >
                            <div class="flex justify-between items-start">
                              <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                  <span
                                    class="text-lg font-semibold text-gray-900"
                                    >{{ reservation.name }}</span
                                  >
                                  <span class="text-xs text-gray-500"
                                    >ID: {{ reservation.id }}</span
                                  >
                                  <span
                                    v-if="reservation.status"
                                    :class="
                                      getStatusBadgeClass(reservation.status)
                                    "
                                    class="px-2 py-0.5 text-xs font-medium rounded-full"
                                  >
                                    {{ reservation.status
                                    }}<span v-if="reservation.status_updated_by"
                                      >:{{
                                        reservation.status_updated_by.name
                                      }}</span
                                    >
                                  </span>

                                  <span
                                    v-if="reservation.venue"
                                    class="px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded-full"
                                  >
                                    {{ reservation.venue.name }}
                                  </span>
                                </div>
                                <div
                                  class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600"
                                >
                                  <div class="flex items-center">
                                    <svg
                                      class="w-4 h-4 mr-2 text-gray-400"
                                      fill="none"
                                      stroke="currentColor"
                                      viewBox="0 0 24 24"
                                    >
                                      <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                      />
                                    </svg>
                                    {{ reservation.email }}
                                  </div>
                                  <div class="flex items-center">
                                    <svg
                                      class="w-4 h-4 mr-2 text-gray-400"
                                      fill="none"
                                      stroke="currentColor"
                                      viewBox="0 0 24 24"
                                    >
                                      <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                                      />
                                    </svg>
                                    {{ reservation.phone }}
                                  </div>
                                  <div
                                    v-if="reservation.furigana"
                                    class="flex items-center"
                                  >
                                    <span class="text-gray-500 mr-2"
                                      >フリガナ:</span
                                    >
                                    {{ reservation.furigana }}
                                  </div>
                                  <div
                                    v-if="
                                      reservation.has_visited_before !== null
                                    "
                                    class="flex items-center"
                                  >
                                    <span class="text-gray-500 mr-2"
                                      >過去来店:</span
                                    >
                                    <span
                                      :class="
                                        reservation.has_visited_before
                                          ? 'text-green-600'
                                          : 'text-gray-600'
                                      "
                                    >
                                      {{
                                        reservation.has_visited_before
                                          ? "あり"
                                          : "なし"
                                      }}
                                    </span>
                                  </div>
                                  <div
                                    v-if="
                                      reservation.considering_plans &&
                                      reservation.considering_plans.length > 0
                                    "
                                    class="md:col-span-2"
                                  >
                                    <span class="text-gray-500 mr-2"
                                      >検討プラン:</span
                                    >
                                    <span
                                      v-for="plan in reservation.considering_plans"
                                      :key="plan"
                                      class="inline-block px-2 py-0.5 text-xs bg-indigo-100 text-indigo-800 rounded-full mr-1"
                                    >
                                      {{ plan }}
                                    </span>
                                  </div>
                                  <div
                                    v-if="
                                      reservation.schedule &&
                                      reservation.schedule.participantUsers &&
                                      reservation.schedule.participantUsers.length > 0
                                    "
                                    class="md:col-span-2"
                                  >
                                    <span class="text-gray-500 mr-2"
                                      >担当者:</span
                                    >
                                    <span
                                      v-for="participant in reservation.schedule.participantUsers"
                                      :key="participant.id"
                                      class="inline-block px-2 py-0.5 text-xs bg-purple-100 text-purple-800 rounded-full mr-1"
                                    >
                                      {{ participant.name }}
                                    </span>
                                  </div>
                                </div>
                                <div class="text-xs text-gray-500 mt-2">
                                  登録日時:
                                  <span v-html="formatDateTime(reservation.created_at)"></span>
                                </div>
                              </div>
                              <div class="flex space-x-2 ml-4">
                                <Link
                                  :href="
                                    route(
                                      'admin.reservations.show',
                                      reservation.id
                                    )
                                  "
                                  class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                >
                                  詳細
                                </Link>
                                <button
                                  v-if="reservation.cancel_flg"
                                  @click="restoreReservation(reservation.id)"
                                  class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-orange-600 to-orange-700 rounded-lg shadow-sm hover:from-orange-700 hover:to-orange-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-all duration-200"
                                >
                                  キャンセル解除
                                </button>
                                <button
                                  v-else
                                  @click="deleteReservation(reservation.id)"
                                  class="px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 rounded-lg shadow-sm hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                                >
                                  キャンセル
                                </button>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div
                          v-else
                          class="p-4 text-center text-gray-500 text-sm"
                        >
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
            <div
              v-if="event.form_type !== 'reservation' && activeTab === 'cards'"
              class="space-y-4"
            >
              <div
                v-if="sortedReservations && sortedReservations.length > 0"
                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
              >
                <div
                  v-for="reservation in sortedReservations"
                  :key="reservation.id"
                  :class="[
                    'border rounded-lg shadow-sm overflow-hidden',
                    reservation.cancel_flg
                      ? 'bg-gray-200 border-gray-300'
                      : 'bg-white border-gray-200 hover:shadow-md transition-shadow duration-200',
                  ]"
                >
                  <div class="p-5">
                    <!-- ヘッダー -->
                    <div class="flex justify-between items-start mb-4">
                      <div>
                        <div class="text-lg font-semibold text-gray-900 mb-1">
                          {{ reservation.name }}
                        </div>
                        <div class="text-xs text-gray-500">
                          ID: {{ reservation.id }}
                        </div>
                      </div>
                      <div class="flex flex-col items-end space-y-1">
                        <span
                          v-if="reservation.status"
                          :class="getStatusBadgeClass(reservation.status)"
                          class="px-2 py-1 text-xs font-medium rounded-full"
                        >
                          {{ reservation.status
                          }}<span v-if="reservation.status_updated_by"
                            >:{{ reservation.status_updated_by.name }}</span
                          >
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
                        <svg
                          class="w-4 h-4 mr-2 mt-0.5 text-gray-400 flex-shrink-0"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                          />
                        </svg>
                        <span class="text-gray-700 break-all">{{
                          reservation.email
                        }}</span>
                      </div>
                      <div class="flex items-start text-sm">
                        <svg
                          class="w-4 h-4 mr-2 mt-0.5 text-gray-400 flex-shrink-0"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                          />
                        </svg>
                        <span class="text-gray-700">{{
                          reservation.phone || "-"
                        }}</span>
                      </div>
                    </div>

                    <div
                      v-if="event.form_type === 'document'"
                      class="space-y-2 mb-4 pb-4 border-b border-gray-100"
                    >
                      <div
                        v-if="reservation.request_method"
                        class="flex items-start text-sm"
                      >
                        <span class="text-gray-500 mr-2">請求方法:</span>
                        <span class="text-gray-700 font-medium">{{
                          reservation.request_method
                        }}</span>
                      </div>
                      <div
                        v-if="reservation.postal_code"
                        class="flex items-start text-sm"
                      >
                        <span class="text-gray-500 mr-2">郵便番号:</span>
                        <span class="text-gray-700">{{
                          reservation.postal_code
                        }}</span>
                      </div>
                    </div>

                    <div
                      v-if="event.form_type === 'contact'"
                      class="space-y-2 mb-4 pb-4 border-b border-gray-100"
                    >
                      <div
                        v-if="reservation.heard_from"
                        class="flex items-start text-sm"
                      >
                        <span class="text-gray-500 mr-2">回答方法:</span>
                        <span class="text-gray-700 font-medium">{{
                          reservation.heard_from
                        }}</span>
                      </div>
                    </div>

                    <!-- 登録日時 -->
                    <div class="text-xs text-gray-500 mb-4">
                      登録: <span v-html="formatDateTime(reservation.created_at)"></span>
                    </div>

                    <!-- 操作ボタン -->
                    <div class="flex space-x-2">
                      <Link
                        :href="route('admin.reservations.show', reservation.id)"
                        class="flex-1 inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                      >
                        <svg
                          class="w-4 h-4 mr-1.5"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                          />
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                          />
                        </svg>
                        詳細
                      </Link>
                      <button
                        v-if="reservation.cancel_flg"
                        @click="restoreReservation(reservation.id)"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-orange-600 to-orange-700 rounded-lg shadow-sm hover:from-orange-700 hover:to-orange-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-all duration-200"
                      >
                        キャンセル解除
                      </button>
                      <button
                        v-else
                        @click="deleteReservation(reservation.id)"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 rounded-lg shadow-sm hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                      >
                        <svg
                          class="w-4 h-4"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                          />
                        </svg>
                        キャンセル
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
              <!-- 各種操作 -->
              <div class="mb-6 p-4 bg-indigo-50 rounded-lg border border-indigo-200">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">
                  各種操作
                </h3>
                
                <!-- 印刷ボタン -->
                <div class="mb-4">
                  <button
                    @click="openPrintModal"
                    class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200 inline-flex items-center"
                  >
                    <svg
                      class="w-5 h-5 mr-2"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
                      />
                    </svg>
                    印刷
                  </button>
                </div>

                <!-- 並べ替えと絞り込み（予約フォームの場合のみ） -->
                <div
                  v-if="event.form_type === 'reservation'"
                  class="grid grid-cols-1 md:grid-cols-2 gap-4"
                >
                  <!-- 並べ替えUI -->
                  <div class="bg-blue-50 rounded-lg border border-blue-200 overflow-hidden">
                    <button
                      @click="isSortOpen = !isSortOpen"
                      class="w-full p-4 flex justify-between items-center hover:bg-blue-100 transition-colors duration-200"
                    >
                      <h3 class="text-sm font-semibold text-gray-700">
                        並べ替え
                      </h3>
                      <svg
                        class="w-5 h-5 text-gray-600 transition-transform duration-200"
                        :class="{ 'rotate-180': isSortOpen }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M19 9l-7 7-7-7"
                        />
                      </svg>
                    </button>
                    <div v-show="isSortOpen" class="px-4 pb-4">
                      <div class="flex flex-col gap-4">
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-2"
                            >予約日付</label
                          >
                          <select
                            v-model="sortDateOrder"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          >
                            <option value="asc">新しい順</option>
                            <option value="desc">古い順</option>
                          </select>
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-2"
                            >予約時間</label
                          >
                          <select
                            v-model="sortTimeOrder"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          >
                            <option value="asc">新しい順</option>
                            <option value="desc">古い順</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- 絞り込みUI -->
                  <div class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden">
                    <button
                      @click="isFilterOpen = !isFilterOpen"
                      class="w-full p-4 flex justify-between items-center hover:bg-gray-100 transition-colors duration-200"
                    >
                      <h3 class="text-sm font-semibold text-gray-700">
                        絞り込み
                      </h3>
                      <svg
                        class="w-5 h-5 text-gray-600 transition-transform duration-200"
                        :class="{ 'rotate-180': isFilterOpen }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M19 9l-7 7-7-7"
                        />
                      </svg>
                    </button>
                    <div v-show="isFilterOpen" class="px-4 pb-4">
                      <div class="flex flex-col gap-4">
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-2"
                            >会場</label
                          >
                          <select
                            v-model="filterVenueId"
                            @change="onVenueChangeAndApply"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          >
                            <option value="">すべて</option>
                            <option
                              v-for="venue in venues"
                              :key="venue.id"
                              :value="venue.id"
                            >
                              {{ venue.name }}
                            </option>
                          </select>
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-2"
                            >日付</label
                          >
                          <select
                            v-model="filterReservationDate"
                            @change="onDateChange"
                            :disabled="!filterVenueId"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                          >
                            <option value="">すべて</option>
                            <option
                              v-for="date in availableDates"
                              :key="date"
                              :value="date"
                            >
                              {{ formatDateForOption(date) }}
                            </option>
                          </select>
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-2"
                            >時間</label
                          >
                          <select
                            v-model="filterReservationTime"
                            @change="applyFilters"
                            :disabled="!filterVenueId || !filterReservationDate"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                          >
                            <option value="">すべて</option>
                            <option
                              v-for="time in availableTimes"
                              :key="time"
                              :value="time"
                            >
                              {{ time }}
                            </option>
                          </select>
                        </div>
                        <div class="flex gap-2">
                          <button
                            @click="applyFilters"
                            class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                          >
                            絞り込み
                          </button>
                          <button
                            @click="resetFilters"
                            class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                          >
                            リセット
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 区切り線 -->
              <div class="mb-6 border-t border-gray-300"></div>

              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th
                        class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                      >
                        ID
                      </th>

                      <!-- 予約フォームの場合 -->
                      <template v-if="event.form_type === 'reservation'">
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          予約日時
                        </th>
                      </template>

                      <th
                        class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                      >
                        お名前
                      </th>
                      <th
                        class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                      >
                        メール
                      </th>
                      <th
                        class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                      >
                        電話番号
                      </th>

                      <!-- 予約フォームの場合 -->
                      <template v-if="event.form_type === 'reservation'">
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          ご来店会場
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          フリガナ
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          過去来店
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          住所
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          生年月日
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          成人式予定年
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          学校名
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          駐車場利用
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          検討中のプラン
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          ご紹介者様
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          ステータス
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          ステータス更新者
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          担当者
                        </th>
                      </template>

                      <!-- 資料請求フォームの場合 -->
                      <template v-if="event.form_type === 'document'">
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          請求方法
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          フリガナ
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          生年月日
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          郵便番号
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          住所
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          個人情報同意
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          ステータス
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          ステータス更新者
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          担当者
                        </th>
                      </template>

                      <!-- お問い合わせフォームの場合 -->
                      <template v-if="event.form_type === 'contact'">
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          問い合わせ回答方法
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          ステータス
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          ステータス更新者
                        </th>
                        <th
                          class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          担当者
                        </th>
                      </template>

                      <th
                        class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                      >
                        登録日時
                      </th>
                      <th
                        class="whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                      >
                        操作
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <!-- 予約フォームの場合：日付ごとにテーブルを分ける -->
                    <template v-if="event.form_type === 'reservation'">
                      <template v-for="group in groupedReservationsByDate" :key="group.date || 'no-date'">
                        <!-- 日付ヘッダー行 -->
                        <tr class="bg-gray-100">
                          <td :colspan="19" class="px-6 py-3 text-sm font-semibold text-gray-700">
                            {{ group.dateDisplay }}
                          </td>
                        </tr>
                        <!-- 予約データ行 -->
                        <tr
                          v-for="reservation in group.reservations"
                          :key="reservation.id"
                          :class="{ 'bg-gray-200': reservation.cancel_flg }"
                        >
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{ reservation.id }}
                          </td>
                          <td
                            class="px-6 py-4 text-sm text-gray-900"
                          >
                            <span
                              v-if="reservation.reservation_datetime"
                            >
                              {{ formatTimeOnly(reservation.reservation_datetime) }}
                            </span>
                            <span v-else>-</span>
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{ reservation.name }}
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{ reservation.email }}
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{ reservation.phone }}
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{ reservation.venue ? reservation.venue.name : "-" }}
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{ reservation.furigana || "-" }}
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{ reservation.has_visited_before ? "あり" : "なし" }}
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{ reservation.address || "-" }}
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{
                              reservation.birth_date
                                ? formatDate(reservation.birth_date)
                                : "-"
                            }}
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{
                              reservation.seijin_year
                                ? reservation.seijin_year + "年"
                                : "-"
                            }}
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{ reservation.school_name || "-" }}
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{ reservation.parking_usage || "-" }}
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{
                              reservation.considering_plans &&
                              reservation.considering_plans.length > 0
                                ? reservation.considering_plans.join(", ")
                                : "-"
                            }}
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{ reservation.referred_by_name || "-" }}
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap">
                            <span
                              v-if="reservation.status"
                              :class="getStatusBadgeClass(reservation.status)"
                              class="px-2 py-1 text-xs font-medium rounded-full"
                            >
                              {{ reservation.status
                              }}<span v-if="reservation.status_updated_by"
                                >:{{ reservation.status_updated_by.name }}</span
                              >
                            </span>
                            <span v-else class="text-sm text-gray-500">-</span>
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                          >
                            {{
                              reservation.status_updated_by
                                ? reservation.status_updated_by.name
                                : "-"
                            }}
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap">
                            <span
                              v-if="
                                reservation.schedule &&
                                reservation.schedule.participantUsers &&
                                reservation.schedule.participantUsers.length > 0
                              "
                            >
                              <span
                                v-for="participant in reservation.schedule.participantUsers"
                                :key="participant.id"
                                class="inline-block px-2 py-0.5 text-xs bg-purple-100 text-purple-800 rounded-full mr-1"
                              >
                                {{ participant.name }}
                              </span>
                            </span>
                            <span v-else class="text-sm text-gray-500">-</span>
                          </td>
                          <td
                            class="px-6 py-4 text-sm text-gray-900"
                          >
                            <span v-html="formatDateTime(reservation.created_at)"></span>
                          </td>
                          <td
                            class="px-6 py-4 whitespace-nowrap text-sm font-medium"
                          >
                            <Link
                              :href="
                                route('admin.reservations.show', reservation.id)
                              "
                              class="text-indigo-600 hover:text-indigo-900 mr-4"
                            >
                              詳細
                            </Link>
                            <button
                              v-if="reservation.cancel_flg"
                              @click="restoreReservation(reservation.id)"
                              class="text-orange-600 hover:text-orange-900 mr-4"
                            >
                              キャンセル解除
                            </button>
                            <button
                              v-else
                              @click="deleteReservation(reservation.id)"
                              class="text-red-600 hover:text-red-900"
                            >
                              キャンセル
                            </button>
                          </td>
                        </tr>
                      </template>
                    </template>
                    
                    <!-- 予約フォーム以外の場合：通常の表示 -->
                    <template v-else>
                      <tr
                        v-for="reservation in sortedReservations"
                        :key="reservation.id"
                        :class="{ 'bg-gray-200': reservation.cancel_flg }"
                      >
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{ reservation.id }}
                        </td>
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{ reservation.name }}
                        </td>
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{ reservation.email }}
                        </td>
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{ reservation.phone }}
                        </td>

                      <!-- 資料請求フォームの場合 -->
                      <template v-if="event.form_type === 'document'">
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{ reservation.request_method || "-" }}
                        </td>
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{ reservation.furigana || "-" }}
                        </td>
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{
                            reservation.birth_date
                              ? formatDate(reservation.birth_date)
                              : "-"
                          }}
                        </td>
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{ reservation.postal_code || "-" }}
                        </td>
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{ reservation.address || "-" }}
                        </td>
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{ reservation.privacy_agreed ? "同意" : "-" }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span
                            v-if="reservation.status"
                            :class="getStatusBadgeClass(reservation.status)"
                            class="px-2 py-1 text-xs font-medium rounded-full"
                          >
                            {{ reservation.status
                            }}<span v-if="reservation.status_updated_by"
                              >:{{ reservation.status_updated_by.name }}</span
                            >
                          </span>
                          <span v-else class="text-sm text-gray-500">-</span>
                        </td>
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{
                            reservation.status_updated_by
                              ? reservation.status_updated_by.name
                              : "-"
                          }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span
                            v-if="
                              reservation.schedule &&
                              reservation.schedule.participantUsers &&
                              reservation.schedule.participantUsers.length > 0
                            "
                          >
                            <span
                              v-for="participant in reservation.schedule.participantUsers"
                              :key="participant.id"
                              class="inline-block px-2 py-0.5 text-xs bg-purple-100 text-purple-800 rounded-full mr-1"
                            >
                              {{ participant.name }}
                            </span>
                          </span>
                          <span v-else class="text-sm text-gray-500">-</span>
                        </td>
                      </template>

                      <!-- お問い合わせフォームの場合 -->
                      <template v-if="event.form_type === 'contact'">
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{ reservation.heard_from || "-" }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span
                            v-if="reservation.status"
                            :class="getStatusBadgeClass(reservation.status)"
                            class="px-2 py-1 text-xs font-medium rounded-full"
                          >
                            {{ reservation.status
                            }}<span v-if="reservation.status_updated_by"
                              >:{{ reservation.status_updated_by.name }}</span
                            >
                          </span>
                          <span v-else class="text-sm text-gray-500">-</span>
                        </td>
                        <td
                          class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                          {{
                            reservation.status_updated_by
                              ? reservation.status_updated_by.name
                              : "-"
                          }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span
                            v-if="
                              reservation.schedule &&
                              reservation.schedule.participantUsers &&
                              reservation.schedule.participantUsers.length > 0
                            "
                          >
                            <span
                              v-for="participant in reservation.schedule.participantUsers"
                              :key="participant.id"
                              class="inline-block px-2 py-0.5 text-xs bg-purple-100 text-purple-800 rounded-full mr-1"
                            >
                              {{ participant.name }}
                            </span>
                          </span>
                          <span v-else class="text-sm text-gray-500">-</span>
                        </td>
                      </template>

                      <td
                        class="px-6 py-4 text-sm text-gray-900"
                      >
                        <span v-html="formatDateTime(reservation.created_at)"></span>
                      </td>
                      <td
                        class="px-6 py-4 whitespace-nowrap text-sm font-medium"
                      >
                        <Link
                          :href="
                            route('admin.reservations.show', reservation.id)
                          "
                          class="text-indigo-600 hover:text-indigo-900 mr-4"
                        >
                          詳細
                        </Link>
                        <button
                          v-if="reservation.cancel_flg"
                          @click="restoreReservation(reservation.id)"
                          class="text-orange-600 hover:text-orange-900 mr-4"
                        >
                          キャンセル解除
                        </button>
                        <button
                          v-else
                          @click="deleteReservation(reservation.id)"
                          class="text-red-600 hover:text-red-900"
                        >
                          キャンセル
                        </button>
                      </td>
                    </tr>
                    </template>
                  </tbody>
                </table>
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
      style="background-color: rgba(0, 0, 0, 0.5)"
      @click.self="closeTextReservationModal"
    >
      <div
        class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto"
      >
        <div
          class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center z-10"
        >
          <h2 class="text-2xl font-bold">テキストから予約登録</h2>
          <button
            @click="closeTextReservationModal"
            class="text-gray-500 hover:text-gray-700"
          >
            <svg
              class="w-6 h-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
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

    <!-- 印刷プレビューモーダル -->
    <div
      v-if="showPrintModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      style="background-color: rgba(0, 0, 0, 0.5);"
      @click.self="closePrintModal"
    >
      <div
        class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto"
      >
        <div
          class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center z-10"
        >
          <h2 class="text-2xl font-bold">印刷設定</h2>
          <button
            @click="closePrintModal"
            class="text-gray-500 hover:text-gray-700"
          >
            <svg
              class="w-6 h-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>
        <div class="p-6">
            <!-- 設定エリア -->
            <div class="w-full">
              <!-- 向き選択 -->
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  向き
                </label>
                <div class="flex gap-4">
                  <label class="flex items-center">
                    <input
                      type="radio"
                      v-model="printOrientation"
                      value="portrait"
                      class="mr-2"
                    />
                    <span>縦向き</span>
                  </label>
                  <label class="flex items-center">
                    <input
                      type="radio"
                      v-model="printOrientation"
                      value="landscape"
                      class="mr-2"
                    />
                    <span>横向き</span>
                  </label>
                </div>
              </div>

              <!-- フォントサイズ選択 -->
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  フォントサイズ (px)
                </label>
                <input
                  type="number"
                  v-model.number="printFontSize"
                  min="8"
                  max="24"
                  step="1"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
              </div>

              <!-- メモ欄設定 -->
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  メモ欄
                </label>
                <div class="flex gap-4 mb-3">
                  <label class="flex items-center">
                    <input
                      type="radio"
                      v-model="printMemoEnabled"
                      :value="true"
                      class="mr-2"
                    />
                    <span>あり</span>
                  </label>
                  <label class="flex items-center">
                    <input
                      type="radio"
                      v-model="printMemoEnabled"
                      :value="false"
                      class="mr-2"
                    />
                    <span>なし</span>
                  </label>
                </div>
                <div v-if="printMemoEnabled" class="mt-3">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    メモ欄のサイズ (px)
                  </label>
                  <input
                    type="number"
                    v-model.number="printMemoSize"
                    min="50"
                    max="300"
                    step="10"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>
              </div>

              <!-- カラム選択 -->
              <div class="mb-6">
                <div class="flex justify-between items-center mb-3">
                  <label class="block text-sm font-medium text-gray-700">
                    印刷に含めるカラム
                  </label>
                  <div class="flex gap-2">
                    <button
                      @click="selectAllColumns"
                      class="px-3 py-1 text-xs font-medium text-indigo-600 hover:text-indigo-800"
                    >
                      すべて選択
                    </button>
                    <button
                      @click="deselectAllColumns"
                      class="px-3 py-1 text-xs font-medium text-gray-600 hover:text-gray-800"
                    >
                      すべて解除
                    </button>
                  </div>
                </div>
                <div
                  class="border border-gray-200 rounded-lg p-4 max-h-96 overflow-y-auto"
                >
                  <div
                    v-for="column in printColumns"
                    :key="column.key"
                    class="mb-2"
                  >
                    <label class="flex items-center">
                      <input
                        type="checkbox"
                        v-model="selectedPrintColumns"
                        :value="column.key"
                        class="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                      />
                      <span class="text-sm text-gray-700">{{ column.label }}</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

          <!-- ボタン -->
          <div class="flex justify-end space-x-3 mt-6">
            <button
              @click="closePrintModal"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
            >
              キャンセル
            </button>
            <button
              @click="printTable"
              class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
            >
              印刷
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import axios from "axios";
import ActionButton from "@/Components/ActionButton.vue";
import EventNavigation from "@/Components/EventNavigation.vue";

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
const activeTab = ref(
  props.event.form_type === "reservation" ? "schedule" : "cards"
);
const adjustingTimeslotId = ref(null);
const adjustCapacitySuccessMessage = ref("");

// アコーディオンの開閉状態
const isSortOpen = ref(false);
const isFilterOpen = ref(false);

// テキストから予約登録モーダルの状態
const showTextReservationModal = ref(false);
const textReservationInput = ref("");
const selectedTimeslotForTextReservation = ref(null);

// 印刷設定の読み込み
const loadPrintSettings = () => {
  const savedSettings = localStorage.getItem('printSettings');
  if (savedSettings) {
    try {
      const settings = JSON.parse(savedSettings);
      return {
        paperSize: settings.paperSize || 'A4',
        orientation: settings.orientation || 'portrait',
        fontSize: settings.fontSize || '12',
        selectedColumns: settings.selectedColumns || [],
        memoEnabled: settings.memoEnabled !== undefined ? settings.memoEnabled : false,
        memoSize: settings.memoSize || '100'
      };
    } catch (e) {
      console.error('Failed to load print settings:', e);
    }
  }
  return {
    paperSize: 'A4',
    orientation: 'portrait',
    fontSize: '12',
    selectedColumns: [],
    memoEnabled: false,
    memoSize: '100'
  };
};

// 印刷設定の保存
const savePrintSettings = () => {
  const settings = {
    paperSize: printPaperSize.value,
    orientation: printOrientation.value,
    fontSize: printFontSize.value,
    selectedColumns: selectedPrintColumns.value,
    memoEnabled: printMemoEnabled.value,
    memoSize: printMemoSize.value
  };
  localStorage.setItem('printSettings', JSON.stringify(settings));
};

// 初期設定を読み込む
const initialSettings = loadPrintSettings();

// 印刷モーダルの状態（用紙サイズはA4に固定）
const showPrintModal = ref(false);
const printPaperSize = ref("A4");
const printOrientation = ref(initialSettings.orientation);
const printFontSize = ref(Number(initialSettings.fontSize));
const selectedPrintColumns = ref(initialSettings.selectedColumns);
const printMemoEnabled = ref(initialSettings.memoEnabled);
const printMemoSize = ref(Number(initialSettings.memoSize));

// 行の高さは固定値（30px）
const printRowHeight = 30;

// 絞り込み用のstate
const filterVenueId = ref(props.filters?.venue_id || "");
const filterReservationDatetime = ref(
  props.filters?.reservation_datetime || ""
);

// 日付と時間を分離したフィルター
// 既存のfilterReservationDatetimeから初期値を設定
const initialDatetime = props.filters?.reservation_datetime || "";
let initialDate = "";
let initialTime = "";
if (initialDatetime) {
  const date = new Date(initialDatetime);
  initialDate = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  initialTime = `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}
const filterReservationDate = ref(initialDate);
const filterReservationTime = ref(initialTime);

// 並べ替え用のstate（デフォルトは新しい順）
const sortDateOrder = ref("asc");
const sortTimeOrder = ref("asc");

// 選択可能な時間リスト（会場が選択されている場合、その会場の時間のみ表示）
const availableTimeslots = computed(() => {
  if (!filterVenueId.value) {
    // 会場が選択されていない場合、すべての時間を返す
    return props.filterTimeslots || [];
  }
  // 会場が選択されている場合、その会場の時間のみ返す
  return (props.filterTimeslots || []).filter((timeslot) => {
    return timeslot.venue_id == filterVenueId.value;
  });
});

// 選択可能な日付リスト（重複を除去し、ソート）
const availableDates = computed(() => {
  const dates = new Set();
  availableTimeslots.value.forEach((timeslot) => {
    const date = new Date(timeslot.start_at);
    const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    dates.add(dateKey);
  });
  return Array.from(dates).sort();
});

// 選択可能な時間リスト（選択された日付に対応する時間のみ）
const availableTimes = computed(() => {
  if (!filterReservationDate.value) {
    return [];
  }
  const times = new Set();
  availableTimeslots.value.forEach((timeslot) => {
    const date = new Date(timeslot.start_at);
    const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    if (dateKey === filterReservationDate.value) {
      const timeKey = `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
      times.add(timeKey);
    }
  });
  return Array.from(times).sort();
});

// 並べ替えられた予約データ
const sortedReservations = computed(() => {
  if (!props.reservations || !Array.isArray(props.reservations) || props.reservations.length === 0) {
    return [];
  }

  // 予約フォームの場合のみ並べ替えを適用
  if (props.event.form_type === "reservation") {
    const data = [...props.reservations];

    return data.sort((a, b) => {
      // 予約日時がない場合は最後に配置
      if (!a.reservation_datetime && !b.reservation_datetime) return 0;
      if (!a.reservation_datetime) return 1;
      if (!b.reservation_datetime) return -1;

      const dateA = new Date(a.reservation_datetime);
      const dateB = new Date(b.reservation_datetime);

      // 日付で比較（日付のみ、時間は無視）
      const dateOnlyA = new Date(
        dateA.getFullYear(),
        dateA.getMonth(),
        dateA.getDate()
      );
      const dateOnlyB = new Date(
        dateB.getFullYear(),
        dateB.getMonth(),
        dateB.getDate()
      );

      let dateCompare = 0;
      if (dateOnlyA < dateOnlyB) {
        dateCompare = -1;
      } else if (dateOnlyA > dateOnlyB) {
        dateCompare = 1;
      }

      // 日付が同じ場合は時間で比較
      if (dateCompare === 0) {
        const timeA = dateA.getHours() * 60 + dateA.getMinutes();
        const timeB = dateB.getHours() * 60 + dateB.getMinutes();

        let timeCompare = 0;
        if (timeA < timeB) {
          timeCompare = -1;
        } else if (timeA > timeB) {
          timeCompare = 1;
        }

        // 時間の並べ替え順を適用
        if (sortTimeOrder.value === "desc") {
          timeCompare = -timeCompare;
        }

        return timeCompare;
      }

      // 日付の並べ替え順を適用
      if (sortDateOrder.value === "desc") {
        dateCompare = -dateCompare;
      }

      return dateCompare;
    });
  }

  // 予約フォーム以外の場合は元のデータをそのまま返す
  return props.reservations;
});

// 予約データを日付ごとにグループ化（予約フォームの場合のみ）
const groupedReservationsByDate = computed(() => {
  if (props.event.form_type !== "reservation") {
    return [];
  }

  const groups = {};
  const reservationsWithoutDate = [];

  sortedReservations.value.forEach((reservation) => {
    if (!reservation.reservation_datetime) {
      reservationsWithoutDate.push(reservation);
      return;
    }

    const date = new Date(reservation.reservation_datetime);
    const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    
    if (!groups[dateKey]) {
      groups[dateKey] = {
        date: dateKey,
        dateDisplay: formatDateHeader(reservation.reservation_datetime),
        reservations: []
      };
    }
    
    groups[dateKey].reservations.push(reservation);
  });

  // 日付ごとにソート（日付の昇順）
  const sortedGroups = Object.values(groups).sort((a, b) => {
    return new Date(a.date) - new Date(b.date);
  });

  // 予約日時がないものは最後に追加
  if (reservationsWithoutDate.length > 0) {
    sortedGroups.push({
      date: null,
      dateDisplay: "予約日時なし",
      reservations: reservationsWithoutDate
    });
  }

  return sortedGroups;
});

// 印刷用カラムリスト（表示テーブルの順序と一致）
const printColumns = computed(() => {
  const columns = [];

  // IDは常に最初
  columns.push({ key: "id", label: "ID" });

  if (props.event.form_type === "reservation") {
    // 予約フォームの場合
    columns.push(
      { key: "reservation_datetime", label: "予約日時" },
      { key: "name", label: "お名前" },
      { key: "email", label: "メール" },
      { key: "phone", label: "電話番号" },
      { key: "venue", label: "ご来店会場" },
      { key: "furigana", label: "フリガナ" },
      { key: "has_visited_before", label: "過去来店" },
      { key: "address", label: "住所" },
      { key: "birth_date", label: "生年月日" },
      { key: "seijin_year", label: "成人式予定年" },
      { key: "school_name", label: "学校名" },
      { key: "parking_usage", label: "駐車場利用" },
      { key: "considering_plans", label: "検討中のプラン" },
      { key: "referred_by_name", label: "ご紹介者様" },
      { key: "status", label: "ステータス" },
      { key: "status_updated_by", label: "ステータス更新者" },
      { key: "participants", label: "担当者" },
      { key: "created_at", label: "登録日時" }
    );
  } else if (props.event.form_type === "document") {
    // 資料請求フォームの場合
    columns.push(
      { key: "name", label: "お名前" },
      { key: "email", label: "メール" },
      { key: "phone", label: "電話番号" },
      { key: "request_method", label: "請求方法" },
      { key: "furigana", label: "フリガナ" },
      { key: "birth_date", label: "生年月日" },
      { key: "postal_code", label: "郵便番号" },
      { key: "address", label: "住所" },
      { key: "privacy_agreed", label: "個人情報同意" },
      { key: "status", label: "ステータス" },
      { key: "status_updated_by", label: "ステータス更新者" },
      { key: "participants", label: "担当者" },
      { key: "created_at", label: "登録日時" }
    );
  } else if (props.event.form_type === "contact") {
    // お問い合わせフォームの場合
    columns.push(
      { key: "name", label: "お名前" },
      { key: "email", label: "メール" },
      { key: "phone", label: "電話番号" },
      { key: "heard_from", label: "問い合わせ回答方法" },
      { key: "status", label: "ステータス" },
      { key: "status_updated_by", label: "ステータス更新者" },
      { key: "participants", label: "担当者" },
      { key: "created_at", label: "登録日時" }
    );
  }

  return columns;
});

// 選択されたカラムのみを順序通りに返す
const selectedPrintColumnsList = computed(() => {
  return printColumns.value.filter((col) =>
    selectedPrintColumns.value.includes(col.key)
  );
});

// ページ分割の計算
const calculatePages = computed(() => {
  // A4サイズの高さ（px単位、96dpiを想定）
  // A4縦向き: 297mm ≈ 1122px, A4横向き: 210mm ≈ 794px
  const mmToPx = 3.779527559; // 1mm = 3.779527559px (96dpi)
  const pageHeightMm = printOrientation.value === 'portrait' ? 297 : 210;
  const pageHeightPx = pageHeightMm * mmToPx;
  
  // マージンとパディングを考慮
  const marginPx = 10 * mmToPx * 2; // 上下マージン（1cm = 10mm）
  const paddingPx = 20 * 2; // 上下パディング
  const headerHeightPx = parseFloat(printFontSize.value) + 2 + 15 + 15; // タイトル + マージン
  const tableHeaderHeightPx = printRowHeight - 2;
  const dateHeaderHeightPx = parseFloat(printFontSize.value) + 1 + 8 + 12 + 5; // 日付ヘッダー + マージン
  
  // 利用可能な高さ
  const availableHeightPx = pageHeightPx - marginPx - paddingPx - headerHeightPx - tableHeaderHeightPx;
  
  // 1ページに収まる行数
  const rowsPerPage = Math.floor(availableHeightPx / printRowHeight);
  
  // 予約フォームの場合：日付ごとにグループ化
  if (props.event.form_type === "reservation") {
    const pages = [];
    const groups = groupedReservationsByDate.value;
    let currentPage = {
      pageNumber: 1,
      dateGroups: [],
      currentHeight: headerHeightPx
    };
    
    groups.forEach((group) => {
      const groupHeight = dateHeaderHeightPx + (group.reservations.length * printRowHeight) + tableHeaderHeightPx + 15;
      
      // 現在のページに収まるか確認
      if (currentPage.currentHeight + groupHeight > availableHeightPx && currentPage.dateGroups.length > 0) {
        // 新しいページを作成
        pages.push(currentPage);
        currentPage = {
          pageNumber: pages.length + 1,
          dateGroups: [],
          currentHeight: headerHeightPx
        };
      }
      
      // グループが1ページに収まらない場合は分割
      if (groupHeight > availableHeightPx) {
        // グループを分割
        const rowsPerPageForGroup = Math.floor((availableHeightPx - dateHeaderHeightPx - tableHeaderHeightPx - 15) / printRowHeight);
        
        for (let i = 0; i < group.reservations.length; i += rowsPerPageForGroup) {
          if (currentPage.dateGroups.length > 0) {
            pages.push(currentPage);
            currentPage = {
              pageNumber: pages.length + 1,
              dateGroups: [],
              currentHeight: headerHeightPx
            };
          }
          
          currentPage.dateGroups.push({
            date: group.date,
            dateDisplay: i === 0 ? group.dateDisplay : null, // 最初の部分のみ日付ヘッダーを表示
            reservations: group.reservations.slice(i, i + rowsPerPageForGroup)
          });
          currentPage.currentHeight = headerHeightPx + dateHeaderHeightPx + (currentPage.dateGroups[0].reservations.length * printRowHeight) + tableHeaderHeightPx + 15;
        }
      } else {
        // グループをそのまま追加
        currentPage.dateGroups.push(group);
        currentPage.currentHeight += groupHeight;
      }
    });
    
    // 最後のページを追加
    if (currentPage.dateGroups.length > 0) {
      pages.push(currentPage);
    }
    
    return {
      rowsPerPage,
      pages,
      totalPages: pages.length
    };
  }
  
  // 予約フォーム以外の場合：通常のページ分割
  const pages = [];
  const reservations = sortedReservations.value;
  
  for (let i = 0; i < reservations.length; i += rowsPerPage) {
    pages.push({
      pageNumber: Math.floor(i / rowsPerPage) + 1,
      reservations: reservations.slice(i, i + rowsPerPage)
    });
  }
  
  return {
    rowsPerPage,
    pages,
    totalPages: pages.length
  };
});

// プレビュー用のスタイル
const previewStyle = computed(() => {
  // A4のアスペクト比を計算（縦向き: 210/297, 横向き: 297/210）
  const aspectRatio = printOrientation.value === 'portrait' ? 210 / 297 : 297 / 210;

  return {
    width: "100%",
    aspectRatio: aspectRatio,
    fontSize: `${printFontSize.value}px`,
    fontFamily:
      "'Hiragino Sans', 'Hiragino Kaku Gothic ProN', 'Yu Gothic', 'Meiryo', sans-serif",
    margin: "0 auto 20px auto",
    padding: "20px",
    backgroundColor: "#ffffff",
    boxSizing: "border-box",
    display: "block",
    boxShadow: "0 2px 8px rgba(0, 0, 0, 0.1)",
  };
});

// 会場変更時の処理
const onVenueChange = () => {
  // 会場が変更されたら日付と時間をリセット
  filterReservationDate.value = "";
  filterReservationTime.value = "";
};

// 会場変更時に日付と時間をリセットして自動的に絞り込みを適用
const onVenueChangeAndApply = () => {
  // 会場が変更されたら日付と時間をリセット
  filterReservationDate.value = "";
  filterReservationTime.value = "";
  // 自動的に絞り込みを適用
  applyFilters();
};

// 日付変更時に時間をリセットしてフィルターを適用
const onDateChange = () => {
  filterReservationTime.value = "";
  applyFilters();
};

// フィルターを適用
const applyFilters = () => {
  const params = {};
  if (filterVenueId.value) {
    params.venue_id = filterVenueId.value;
  }
  // 日付と時間を組み合わせてreservation_datetimeとして送信
  if (filterReservationDate.value && filterReservationTime.value) {
    params.reservation_datetime = `${filterReservationDate.value} ${filterReservationTime.value}:00`;
  } else if (filterReservationDate.value) {
    // 日付のみの場合、その日の最初の時間を設定（後方互換性のため）
    params.reservation_datetime = filterReservationDate.value;
  }

  router.get(route("admin.events.reservations.index", props.event.id), params, {
    preserveState: true,
    preserveScroll: true,
  });
};

// フィルターをリセット
const resetFilters = () => {
  filterVenueId.value = "";
  filterReservationDate.value = "";
  filterReservationTime.value = "";
  router.get(
    route("admin.events.reservations.index", props.event.id),
    {},
    {
      preserveState: true,
      preserveScroll: true,
    }
  );
};

// 予約枠を会場ごと、その中で日付ごとにグループ化
const groupedByVenue = computed(() => {
  if (
    !props.timeslotsWithReservations ||
    props.timeslotsWithReservations.length === 0
  )
    return {};

  // まず会場ごとにグループ化
  const venueGroups = {};
  props.timeslotsWithReservations.forEach((timeslot) => {
    // venue_idがnullの場合は「会場未設定」として扱う
    const venueKey = timeslot.venue_id || "no_venue";
    if (!venueGroups[venueKey]) {
      venueGroups[venueKey] = {};
    }

    // 日付ごとにグループ化
    const date = new Date(timeslot.start_at);
    const dateKey = `${date.getFullYear()}-${String(
      date.getMonth() + 1
    ).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
    if (!venueGroups[venueKey][dateKey]) {
      venueGroups[venueKey][dateKey] = [];
    }
    venueGroups[venueKey][dateKey].push(timeslot);
  });

  // 各会場の各日付の枠を時間順にソート
  Object.keys(venueGroups).forEach((venueKey) => {
    Object.keys(venueGroups[venueKey]).forEach((dateKey) => {
      venueGroups[venueKey][dateKey].sort((a, b) => {
        return new Date(a.start_at) - new Date(b.start_at);
      });
    });
  });

  return venueGroups;
});

// 会場名を取得
const getVenueName = (venueKey) => {
  if (venueKey === "no_venue") {
    return "会場未設定";
  }
  // venueKeyはvenue_idなので、timeslotsWithReservationsから該当するvenueを探す
  const timeslot = props.timeslotsWithReservations.find(
    (t) => t.venue_id == venueKey
  );
  return timeslot?.venue?.name || `会場ID: ${venueKey}`;
};

// 予約登録URLを生成（予約枠IDのみ）
const getReservationUrl = (timeslot) => {
  const baseUrl = route("event.show", props.event.slug);
  const params = new URLSearchParams({
    from_admin: "1",
  });

  // 予約枠IDのみを追加
  if (timeslot.id) {
    params.append("timeslot_id", timeslot.id);
  }

  return `${baseUrl}?${params.toString()}`;
};

const formatDateTime = (datetime) => {
  if (!datetime) return "-";
  const date = new Date(datetime);
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${month}/${day} ${hours}:${minutes}`;
};

// 時間のみを表示（hh:mm）
const formatTimeOnly = (datetime) => {
  if (!datetime) return "-";
  const date = new Date(datetime);
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${hours}:${minutes}`;
};

// 選択肢などで使用する日時フォーマット（改行なし）
const formatDateTimeForOption = (datetime) => {
  if (!datetime) return "-";
  const date = new Date(datetime);
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${month}/${day} ${hours}:${minutes}`;
};

const formatDate = (dateString) => {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return date.toLocaleDateString("ja-JP");
};

// 日付選択肢用のフォーマット（YYYY-MM-DD形式から表示用に変換）
const formatDateForOption = (dateString) => {
  if (!dateString) return "-";
  const date = new Date(dateString + "T00:00:00");
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${date.getFullYear()}/${month}/${day}`;
};

const formatDateHeader = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString("ja-JP", {
    year: "numeric",
    month: "long",
    day: "numeric",
    weekday: "long",
  });
};

const formatTime = (datetime) => {
  const date = new Date(datetime);
  return date.toLocaleTimeString("ja-JP", {
    hour: "2-digit",
    minute: "2-digit",
  });
};

const getFormTypeLabel = (formType) => {
  const labels = {
    reservation: "予約",
    document: "資料請求",
    contact: "問い合わせ",
  };
  return labels[formType] || formType;
};

const getFormTypeBadgeClass = (formType) => {
  const classes = {
    reservation: "bg-blue-100 text-blue-800",
    document: "bg-green-100 text-green-800",
    contact: "bg-purple-100 text-purple-800",
  };
  return classes[formType] || "bg-gray-100 text-gray-800";
};

const getStatusBadgeClass = (status) => {
  const classes = {
    未対応: "bg-gray-100 text-gray-800",
    確認中: "bg-yellow-100 text-yellow-800",
    返信待ち: "bg-blue-100 text-blue-800",
    対応完了済み: "bg-green-100 text-green-800",
  };
  return classes[status] || "bg-gray-100 text-gray-800";
};

// 色分けロジックの修正版
const getTimeslotStatus = (timeslot) => {
  if (!timeslot.reservations || timeslot.reservations.length === 0) return null;
  // 優先度順
  const pri = ["未対応", "確認中", "返信待ち", "対応完了済み"];
  for (const st of pri) {
    if (timeslot.reservations.some((r) => r.status === st)) return st;
  }
  return null;
};
// 時間枠全体の色
const getTimeslotAllDone = (timeslot) => {
  if (!timeslot.reservations || timeslot.reservations.length === 0)
    return false;
  return timeslot.reservations.every((r) => r.status === "対応完了済み");
};
const getTimeslotHeaderClass = (timeslot) =>
  getTimeslotAllDone(timeslot) ? "bg-green-100" : "bg-gray-100";
const getTimeslotBorderClass = (timeslot) =>
  getTimeslotAllDone(timeslot) ? "border-green-400" : "border-gray-300";

const deleteReservation = (id) => {
  if (confirm("本当にキャンセルしますか？")) {
    router.delete(route("admin.reservations.destroy", id));
  }
};

// キャンセル解除（詳細画面と同じ restore 処理）
const restoreReservation = (id) => {
  if (!confirm("キャンセルを解除しますか？")) return;
  router.patch(route("admin.reservations.restore", id), {}, {
    preserveScroll: true,
  });
};

const adjustCapacity = async (timeslotId, amount) => {
  if (adjustingTimeslotId.value === timeslotId) return;

  adjustingTimeslotId.value = timeslotId;

  try {
    const response = await axios.post(
      route("admin.timeslots.adjust-capacity", timeslotId),
      {
        amount: amount,
      }
    );

    if (response.data.success) {
      adjustCapacitySuccessMessage.value =
        amount > 0 ? "枠を追加しました" : "枠を削除しました";
      // ページをリロードして最新の状態を取得
      router.visit(route("admin.events.reservations.index", props.event.id), {
        preserveScroll: true,
        only: ["timeslotsWithReservations", "timeslotStats"],
      });
      setTimeout(() => {
        adjustCapacitySuccessMessage.value = "";
      }, 5000);
    } else {
      alert(response.data.message || "枠数の変更に失敗しました。");
      adjustingTimeslotId.value = null;
    }
  } catch (error) {
    if (error.response && error.response.data && error.response.data.message) {
      alert(error.response.data.message);
    } else {
      alert("枠数の変更に失敗しました。");
    }
    adjustingTimeslotId.value = null;
  }
};

// テキストから予約登録モーダルを開く
const openTextReservationModal = (timeslot) => {
  selectedTimeslotForTextReservation.value = timeslot;
  textReservationInput.value = "";
  showTextReservationModal.value = true;
};

// テキストから予約登録モーダルを閉じる
const closeTextReservationModal = () => {
  showTextReservationModal.value = false;
  textReservationInput.value = "";
  selectedTimeslotForTextReservation.value = null;
};

// テキストから情報を抽出する関数
const parseTextReservation = (text) => {
  const data = {};

  // 【 過去当店にご来店 】 ない/あり
  const hasVisitedMatch = text.match(/【\s*過去当店にご来店\s*】\s*([^\n\r]+)/);
  if (hasVisitedMatch) {
    const value = hasVisitedMatch[1].trim();
    data.has_visited_before = value === "あり" || value === "ある";
  }

  // 【 お嬢様名 】 村本莉愛
  const nameMatch = text.match(/【\s*お嬢様名\s*】\s*([^\n\r]+)/);
  if (nameMatch) {
    data.name = nameMatch[1].trim();
  }

  // 【 お嬢様名(ふりがな) 】 むらもりあ
  const furiganaMatch = text.match(
    /【\s*お嬢様名\s*\(ふりがな\)\s*】\s*([^\n\r]+)/
  );
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
  const birthDateMatch = text.match(
    /【\s*生年月日\s*】\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日/
  );
  if (birthDateMatch) {
    const year = birthDateMatch[1];
    const month = String(birthDateMatch[2]).padStart(2, "0");
    const day = String(birthDateMatch[3]).padStart(2, "0");
    data.birth_date = `${year}-${month}-${day}`;
    
    console.log('[デバッグ] 生年月日抽出:', {
      year,
      month,
      day,
      birth_date: data.birth_date
    });
    
    // 生年月日から成人式予定年を自動計算
    try {
      const birthYear = parseInt(year, 10);
      const seijinYear = birthYear + 20;
      const currentYear = new Date().getFullYear();
      
      console.log('[デバッグ] 成人式予定年計算:', {
        birthYear,
        seijinYear,
        currentYear,
        minYear: currentYear,
        maxYear: currentYear + 10,
        isInRange: seijinYear >= currentYear && seijinYear <= currentYear + 10
      });
      
      // 現在年から10年後の範囲内であれば設定
      if (seijinYear >= currentYear && seijinYear <= currentYear + 10) {
        data.seijin_year = seijinYear;
        console.log('[デバッグ] 成人式予定年を設定:', seijinYear);
      } else {
        console.warn('[デバッグ] 成人式予定年が範囲外のため設定されませんでした:', seijinYear);
      }
    } catch (error) {
      console.error('[デバッグ] 成人式予定年の計算エラー:', error);
    }
  } else {
    console.log('[デバッグ] 生年月日のマッチがありません');
  }

  // 【 ご検討中のプラン 】 振袖レンタルプラン
  const planMatch = text.match(/【\s*ご検討中のプラン\s*】\s*([^\n\r]+)/);
  if (planMatch) {
    const planText = planMatch[1].trim();
    // プラン名を配列に変換（カンマ区切りの可能性を考慮）
    const plans = planText
      .split(/[,、]/)
      .map((p) => p.trim())
      .filter((p) => p);
    data.considering_plans = plans;
  }

  // 【 お問い合わせ内容 】
  const inquiryMatch = text.match(
    /【\s*お問い合わせ内容\s*】\s*([\s\S]*?)(?=\n\s*【|$)/
  );
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
    alert("予約枠が選択されていません。");
    return;
  }

  if (!textReservationInput.value.trim()) {
    alert("テキストを入力してください。");
    return;
  }

  // テキストから情報を抽出
  const extractedData = parseTextReservation(textReservationInput.value);
  
  console.log('[デバッグ] 抽出されたデータ:', extractedData);
  console.log('[デバッグ] seijin_yearの値:', extractedData.seijin_year);
  console.log('[デバッグ] seijin_yearの型:', typeof extractedData.seijin_year);

  // 予約登録URLを生成
  const baseUrl = route("event.show", props.event.slug);
  const params = new URLSearchParams({
    from_admin: "1",
    timeslot_id: selectedTimeslotForTextReservation.value.id,
  });

  // 抽出したデータをURLパラメータに追加
  Object.keys(extractedData).forEach((key) => {
    const value = extractedData[key];
    console.log(`[デバッグ] パラメータ追加処理: ${key} =`, value, `(型: ${typeof value})`);
    if (value !== null && value !== undefined && value !== "") {
      if (Array.isArray(value)) {
        // 配列の場合はカンマ区切りで追加
        params.append(key, value.join(","));
        console.log(`[デバッグ] 配列として追加: ${key} = ${value.join(",")}`);
      } else {
        params.append(key, value);
        console.log(`[デバッグ] 値を追加: ${key} = ${value}`);
      }
    } else {
      console.log(`[デバッグ] 値が空のためスキップ: ${key}`);
    }
  });
  
  console.log('[デバッグ] 最終的なURLパラメータ:', params.toString());

  // モーダルを閉じて遷移
  closeTextReservationModal();
  window.location.href = `${baseUrl}?${params.toString()}`;
};

// 印刷モーダルを開く
const openPrintModal = () => {
  // 保存された設定を読み込む
  const settings = loadPrintSettings();
  printPaperSize.value = "A4"; // 用紙サイズはA4に固定
  printOrientation.value = settings.orientation;
  printFontSize.value = settings.fontSize;
  printMemoEnabled.value = settings.memoEnabled !== undefined ? settings.memoEnabled : false;
  printMemoSize.value = settings.memoSize || 100;
  
  // カラムが保存されていない、または無効な場合は全カラムを選択
  if (settings.selectedColumns.length === 0 || 
      !settings.selectedColumns.every(col => 
        printColumns.value.some(c => c.key === col)
      )) {
    selectedPrintColumns.value = printColumns.value.map((col) => col.key);
  } else {
    selectedPrintColumns.value = settings.selectedColumns;
  }
  
  showPrintModal.value = true;
};

// 印刷モーダルを閉じる
const closePrintModal = () => {
  showPrintModal.value = false;
};

// 全カラムを選択
const selectAllColumns = () => {
  selectedPrintColumns.value = printColumns.value.map((col) => col.key);
};

// 全カラムを解除
const deselectAllColumns = () => {
  selectedPrintColumns.value = [];
};

// カラムの値を取得するヘルパー関数
const getColumnValue = (reservation, columnKey) => {
  switch (columnKey) {
    case "id":
      return reservation.id;
    case "name":
      return reservation.name;
    case "email":
      return reservation.email;
    case "phone":
      return reservation.phone || "-";
    case "reservation_datetime":
      return reservation.reservation_datetime
        ? formatTimeOnly(reservation.reservation_datetime)
        : "-";
    case "venue":
      return reservation.venue ? reservation.venue.name : "-";
    case "furigana":
      return reservation.furigana || "-";
    case "has_visited_before":
      return reservation.has_visited_before ? "あり" : "なし";
    case "address":
      return reservation.address || "-";
    case "birth_date":
      return reservation.birth_date ? formatDate(reservation.birth_date) : "-";
    case "seijin_year":
      return reservation.seijin_year ? reservation.seijin_year + "年" : "-";
    case "school_name":
      return reservation.school_name || "-";
    case "parking_usage":
      return reservation.parking_usage || "-";
    case "considering_plans":
      return reservation.considering_plans &&
        reservation.considering_plans.length > 0
        ? reservation.considering_plans.join(", ")
        : "-";
    case "referred_by_name":
      return reservation.referred_by_name || "-";
    case "status":
      return reservation.status || "-";
    case "status_updated_by":
      return reservation.status_updated_by
        ? reservation.status_updated_by.name
        : "-";
    case "request_method":
      return reservation.request_method || "-";
    case "postal_code":
      return reservation.postal_code || "-";
    case "privacy_agreed":
      return reservation.privacy_agreed ? "同意" : "-";
    case "heard_from":
      return reservation.heard_from || "-";
    case "participants":
      if (
        reservation.schedule &&
        reservation.schedule.participantUsers &&
        reservation.schedule.participantUsers.length > 0
      ) {
        return reservation.schedule.participantUsers
          .map((p) => p.name)
          .join(", ");
      }
      return "-";
    case "created_at":
      return formatDateTime(reservation.created_at);
    default:
      return "-";
  }
};

// テーブルを印刷
const printTable = () => {
  if (selectedPrintColumns.value.length === 0) {
    alert("少なくとも1つのカラムを選択してください。");
    return;
  }

  // 設定を保存
  savePrintSettings();

  // 選択されたカラムのみを順序通りに取得
  const columns = selectedPrintColumnsList.value;
  
  // メモ欄がある場合は列の最後に追加
  const finalColumns = [...columns];
  if (printMemoEnabled.value) {
    finalColumns.push({ key: 'memo', label: 'メモ' });
  }

  // ページごとにHTMLを生成
  const pages = calculatePages.value.pages;
  let pagesHTML = '';

  pages.forEach((page, index) => {
    let pageHTML = '<div class="print-page">';
    pageHTML += `<h2 style="font-size: ${parseFloat(printFontSize.value) + 2}px; margin-bottom: 15px; text-align: center; color: #1f2937; font-weight: bold;">予約一覧 - ${props.event.title}</h2>`;

    // 予約フォームの場合：日付ごとにグループ化
    if (props.event.form_type === "reservation" && page.dateGroups) {
      page.dateGroups.forEach((group) => {
        if (group.dateDisplay) {
          pageHTML += `<div class="date-header" style="background-color: #f3f4f6; padding: 4px 6px; margin-top: 10px; margin-bottom: 5px; font-size: ${parseFloat(printFontSize.value) + 1}px; font-weight: bold; color: #374151; border-bottom: 2px solid #9ca3af;">${group.dateDisplay}</div>`;
        }
        pageHTML += '<table style="border-collapse: collapse; width: 100%; margin-top: 5px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); table-layout: fixed;">';
        pageHTML += '<thead><tr>';
        finalColumns.forEach((col) => {
          const isMemo = col.key === 'memo';
          const widthStyle = isMemo ? `width: ${printMemoSize.value}px;` : '';
          pageHTML += `<th style="border: 1px solid #9ca3af; padding: 2px 4px; text-align: left; word-wrap: break-word; word-break: break-word; white-space: normal; line-height: 1.4; background: linear-gradient(to bottom, #f3f4f6, #e5e7eb); font-weight: 600; font-size: ${parseFloat(printFontSize.value) - 1}px; color: #374151; height: ${printRowHeight - 2}px; vertical-align: middle; text-transform: uppercase; letter-spacing: 0.05em; overflow: hidden; ${widthStyle}">${col.label}</th>`;
        });
        pageHTML += '</tr></thead><tbody>';
        group.reservations.forEach((reservation, index) => {
          pageHTML += `<tr style="background-color: ${index % 2 === 0 ? '#ffffff' : '#f9fafb'}; height: ${printRowHeight}px;">`;
          finalColumns.forEach((col) => {
            const isMemo = col.key === 'memo';
            const value = isMemo ? '' : getColumnValue(reservation, col.key);
            const widthStyle = isMemo ? `width: ${printMemoSize.value}px;` : '';
            pageHTML += `<td style="border: 1px solid #9ca3af; padding: 3px 5px; text-align: left; word-wrap: break-word; word-break: break-word; white-space: normal; line-height: 1.4; font-size: ${printFontSize.value}px; color: #1f2937; height: ${printRowHeight}px; vertical-align: top; overflow: hidden; ${widthStyle}">${value}</td>`;
          });
          pageHTML += '</tr>';
        });
        pageHTML += '</tbody></table>';
      });
    } else {
      // 予約フォーム以外の場合：通常の表示
      pageHTML += '<table style="border-collapse: collapse; width: 100%; margin-top: 15px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); table-layout: fixed;">';
      pageHTML += '<thead><tr>';
      finalColumns.forEach((col) => {
        const isMemo = col.key === 'memo';
        const widthStyle = isMemo ? `width: ${printMemoSize.value}px;` : '';
        pageHTML += `<th style="border: 1px solid #9ca3af; padding: 4px 6px; text-align: left; word-wrap: break-word; word-break: break-word; white-space: normal; line-height: 1.4; background: linear-gradient(to bottom, #f3f4f6, #e5e7eb); font-weight: 600; font-size: ${parseFloat(printFontSize.value) - 1}px; color: #374151; height: ${printRowHeight - 2}px; vertical-align: middle; text-transform: uppercase; letter-spacing: 0.05em; overflow: hidden; ${widthStyle}">${col.label}</th>`;
      });
      pageHTML += '</tr></thead><tbody>';
      page.reservations.forEach((reservation, index) => {
        pageHTML += `<tr style="background-color: ${index % 2 === 0 ? '#ffffff' : '#f9fafb'}; height: ${printRowHeight}px;">`;
        finalColumns.forEach((col) => {
          const isMemo = col.key === 'memo';
          const value = isMemo ? '' : getColumnValue(reservation, col.key);
          const widthStyle = isMemo ? `width: ${printMemoSize.value}px;` : '';
          pageHTML += `<td style="border: 1px solid #9ca3af; padding: 8px 12px; text-align: left; word-wrap: break-word; word-break: break-word; white-space: normal; line-height: 1.4; font-size: ${printFontSize.value}px; color: #1f2937; height: ${printRowHeight}px; vertical-align: top; overflow: hidden; ${widthStyle}">${value}</td>`;
        });
        pageHTML += '</tr>';
      });
      pageHTML += '</tbody></table>';
    }
    pageHTML += '</div>';
    pagesHTML += pageHTML;
  });

  // 印刷用HTMLを生成
  let html = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>予約一覧 - ${props.event.title}</title>
      <style>
        @page {
          size: ${printPaperSize.value} ${printOrientation.value};
          margin: 1cm;
        }
        body {
          font-family: 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', 'Yu Gothic', 'Meiryo', sans-serif;
          margin: 0;
          padding: 0;
        }
        table {
          page-break-inside: auto;
        }
        tr {
          page-break-inside: avoid;
          page-break-after: auto;
        }
        thead {
          display: table-header-group;
        }
        tfoot {
          display: table-footer-group;
        }
        .date-header {
          background-color: #f3f4f6;
          padding: 8px 12px;
          margin-top: 10px;
          margin-bottom: 5px;
          font-weight: bold;
          color: #374151;
          border-bottom: 2px solid #9ca3af;
        }
        @media print {
          body {
            margin: 0;
            padding: 0;
          }
        }
      </style>
    </head>
    <body>
      ${pagesHTML}
    </body>
    </html>
  `;

  // 新しいウィンドウを開いて印刷
  const printWindow = window.open("", "_blank");
  if (printWindow) {
    printWindow.document.write(html);
    printWindow.document.close();
    
    let printExecuted = false;
    
    // 印刷が実行されたかどうかを検知
    printWindow.addEventListener('beforeprint', () => {
      printExecuted = true;
    });
    
    printWindow.onload = () => {
      setTimeout(() => {
        printWindow.print();
        
        // 印刷ウィンドウが閉じられたときにモーダルを表示
        const checkClosed = setInterval(() => {
          if (printWindow.closed) {
            clearInterval(checkClosed);
            // 印刷が実行されなかった場合（キャンセル）または実行された場合でも、モーダルを表示
            showPrintModal.value = true;
          }
        }, 100);
        
        // 印刷ダイアログが閉じられた後もモーダルを表示するためのフォールバック
        // afterprintイベントは印刷が完了したときに発火
        printWindow.addEventListener('afterprint', () => {
          setTimeout(() => {
            if (!printWindow.closed) {
              printWindow.close();
            }
            showPrintModal.value = true;
          }, 100);
        });
      }, 250);
    };
  } else {
    alert("ポップアップがブロックされています。ブラウザの設定を確認してください。");
  }
};

// 設定変更時に自動保存（モーダル表示中のみ）
watch([printPaperSize, printOrientation, printFontSize, selectedPrintColumns, printMemoEnabled, printMemoSize], () => {
  if (showPrintModal.value) {
    savePrintSettings();
  }
}, { deep: true });

onMounted(() => {
  if (props.success) {
    alert(props.success);
  }
});
</script>
