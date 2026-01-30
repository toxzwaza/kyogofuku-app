<template>
  <Head title="予約詳細" />

  <AuthenticatedLayout>
    <!-- ローディングオーバーレイ -->
    <div
      v-if="isSendingEmail"
      class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50"
    >
      <div class="bg-white rounded-lg p-8 flex flex-col items-center">
        <svg
          class="animate-spin h-12 w-12 text-indigo-600 mb-4"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          ></circle>
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
        <p class="text-gray-700 font-medium">メールを送信しています...</p>
      </div>
    </div>

    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          予約詳細
        </h2>
        <div class="flex items-center space-x-3">
          <ActionButton
            variant="edit"
            label="編集"
            :href="route('admin.reservations.edit', reservation.id)"
          />
          <ActionButton
            variant="back"
            label="予約一覧に戻る"
            :href="route('admin.events.reservations.index', reservation.event_id)"
          />
        </div>
      </div>
    </template>

    <div
      v-if="$page.props.success"
      class="mb-4 p-3 rounded bg-green-100 text-green-800 border border-green-200"
    >
      {{ $page.props.success }}
    </div>

    <div
      v-if="$page.props.flash?.error"
      class="mb-4 p-3 rounded bg-red-100 text-red-800 border border-red-200"
    >
      {{ $page.props.flash.error }}
    </div>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- 左側: 予約情報 -->
          <div class="lg:col-span-2 space-y-4">
            <!-- キャンセル登録済みの表示（横いっぱい） -->
            <div
              v-if="reservation.cancel_flg"
              class="w-full rounded-lg border-2 border-amber-300 bg-amber-50 p-4 shadow-sm"
            >
              <p class="text-base font-semibold text-amber-900 mb-4">
                この予約はキャンセル登録済みです
              </p>
              <div v-if="canRestore" class="flex items-center">
                <button
                  type="button"
                  @click="restoreReservation"
                  :disabled="isRestoring"
                  class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {{ isRestoring ? '処理中...' : 'キャンセルを解除する' }}
                </button>
              </div>
              <p v-else class="text-sm text-amber-800">
                枠がいっぱいです。先に枠を増やしてください。
              </p>
            </div>

            <!-- 予約情報ブロック（キャンセル時は薄いグレー背景） -->
            <div
              :class="[
                'overflow-hidden shadow-sm sm:rounded-lg',
                reservation.cancel_flg ? 'bg-gray-100' : 'bg-white',
              ]"
            >
              <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                  <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    予約情報
                  </h3>
                </div>

                <!-- 基本情報セクション -->
                <div class="mb-6">
                  <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    基本情報
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- 予約ID -->
                    <div class="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-lg p-4 border border-indigo-100 md:col-span-2">
                      <label class="block text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">予約ID</label>
                      <p class="text-lg font-bold text-gray-900">{{ reservation.id }}</p>
                    </div>
                    <!-- お名前、フリガナ -->
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        お名前
                      </label>
                      <p class="text-lg font-semibold text-gray-900">{{ reservation.name }}</p>
                    </div>
                    <div v-if="event.form_type === 'reservation' || event.form_type === 'document'" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                        フリガナ
                      </label>
                      <p class="text-base font-medium text-gray-900">{{ reservation.furigana || '-' }}</p>
                    </div>

                    <!-- メールアドレス、電話番号 -->
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        メールアドレス
                      </label>
                      <p class="text-base font-semibold text-gray-900">{{ reservation.email }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        電話番号
                      </label>
                      <p class="text-base font-semibold text-gray-900">{{ reservation.phone }}</p>
                    </div>
                  </div>
                </div>

                <!-- 予約フォーム (reservation) -->
                <template v-if="event.form_type === 'reservation'">
                  <!-- 予約情報セクション -->
                  <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      予約情報
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                          </svg>
                          予約日時
                        </label>
                        <p class="text-base font-semibold text-gray-900">{{ reservation.reservation_datetime || "-" }}</p>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                          </svg>
                          ご来店会場
                        </label>
                        <p class="text-base font-semibold text-gray-900">{{ reservation.venue ? reservation.venue.name : "-" }}</p>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                          過去当店のご来店
                        </label>
                        <p class="text-base font-medium text-gray-900">{{ reservation.has_visited_before ? "あり" : "なし" }}</p>
                      </div>
                    </div>
                  </div>

                  <!-- 個人情報セクション -->
                  <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                      個人情報
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                          </svg>
                          郵便番号
                        </label>
                        <p class="text-base font-medium text-gray-900">{{ formatPostalCode(reservation.postal_code) }}</p>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                          </svg>
                          住所
                        </label>
                        <p class="text-base font-medium text-gray-900">{{ reservation.address || "-" }}</p>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                          </svg>
                          生年月日
                        </label>
                        <p class="text-base font-medium text-gray-900">{{ reservation.birth_date ? formatDate(reservation.birth_date) : "-" }}</p>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                          </svg>
                          成人式予定年月
                        </label>
                        <p class="text-base font-medium text-gray-900">{{ reservation.seijin_year ? reservation.seijin_year + "年" : "-" }}</p>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                          </svg>
                          学校名
                        </label>
                        <p class="text-base font-medium text-gray-900">{{ reservation.school_name || "-" }}</p>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                          </svg>
                          担当者指名
                        </label>
                        <p class="text-base font-medium text-gray-900">{{ reservation.staff_name || "-" }}</p>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                          </svg>
                          来店動機
                        </label>
                        <p class="text-base font-medium text-gray-900">{{ reservation.visit_reasons && reservation.visit_reasons.length > 0 ? reservation.visit_reasons.join("、") : "-" }}</p>
                      </div>
                    </div>
                  </div>

                  <!-- その他情報セクション -->
                  <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      その他情報
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                          </svg>
                          駐車場利用
                        </label>
                        <p class="text-base font-medium text-gray-900">{{ reservation.parking_usage || "-" }}</p>
                      </div>
                      <div v-if="reservation.parking_usage === 'あり'" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                          </svg>
                          駐車台数
                        </label>
                        <p class="text-base font-medium text-gray-900">{{ reservation.parking_car_count || "-" }}台</p>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                          </svg>
                          検討中のプラン
                        </label>
                        <p class="text-base font-medium text-gray-900">{{ reservation.considering_plans && reservation.considering_plans.length > 0 ? reservation.considering_plans.join("、") : "-" }}</p>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                          </svg>
                          ご紹介者様お名前
                        </label>
                        <p class="text-base font-medium text-gray-900">{{ reservation.referred_by_name || "-" }}</p>
                      </div>
                    </div>
                  </div>
                </template>

                  <!-- 資料請求フォーム (document) -->
                  <template v-if="event.form_type === 'document'">
                    <!-- 資料請求情報セクション -->
                    <div class="mb-6">
                      <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        資料請求情報
                      </h4>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                          <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            請求方法
                          </label>
                          <p class="text-base font-semibold text-gray-900">{{ reservation.request_method || "-" }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                          <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            生年月日
                          </label>
                          <p class="text-base font-medium text-gray-900">{{ reservation.birth_date ? formatDate(reservation.birth_date) : "-" }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                          <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            郵便番号
                          </label>
                          <p class="text-base font-medium text-gray-900">{{ formatPostalCode(reservation.postal_code) }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                          <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            住所
                          </label>
                          <p class="text-base font-medium text-gray-900">{{ reservation.address || "-" }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                          <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            個人情報保護方針への同意
                          </label>
                          <p class="text-base font-medium text-gray-900">{{ reservation.privacy_agreed ? "同意" : "-" }}</p>
                        </div>
                      </div>
                    </div>
                  </template>

                  <!-- お問い合わせフォーム (contact) -->
                  <template v-if="event.form_type === 'contact'">
                    <div class="mb-6">
                      <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                        お問い合わせ情報
                      </h4>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                          <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            問い合わせ回答方法
                          </label>
                          <p class="text-base font-medium text-gray-900">{{ reservation.heard_from || "-" }}</p>
                        </div>
                      </div>
                    </div>
                  </template>

                  <!-- お問い合わせ内容セクション -->
                  <div v-if="reservation.inquiry_message" class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                      </svg>
                      お問い合わせ内容
                    </h4>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <p class="text-sm text-gray-900 whitespace-pre-wrap leading-relaxed">{{ reservation.inquiry_message }}</p>
                    </div>
                  </div>

                  <!-- システム情報セクション -->
                  <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                      </svg>
                      システム情報
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                          </svg>
                          登録日時
                        </label>
                        <p class="text-base font-semibold text-gray-900">{{ formatDateTime(reservation.created_at) }}</p>
                      </div>
                    </div>
                  </div>
              </div>
            </div>

            <!-- メールやり取りブロック -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
              <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">メールやり取り</h3>
                
                <!-- 返信メール送信エリア -->
                <div class="mb-6 pb-6 border-b border-gray-200">
                  <form @submit.prevent="sendReplyEmail">
                    <div class="space-y-4">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                          スレッド選択
                        </label>
                        <select
                          v-model="replyForm.email_thread_id"
                          @change="onThreadChange"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          :required="!isNewEmail"
                        >
                          <option value="">スレッドを選択してください</option>
                          <option value="new">新規メール作成</option>
                          <option
                            v-for="thread in sortedEmailThreads"
                            :key="thread.id"
                            :value="thread.id"
                          >
                            {{ thread.subject }} ({{ thread.emails ? thread.emails.length : 0 }}件)
                          </option>
                        </select>
                      </div>

                      <!-- 新規メール作成時の件名入力フィールド -->
                      <div v-if="isNewEmail">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                          件名 <span class="text-red-500">*</span>
                        </label>
                        <input
                          v-model="replyForm.subject"
                          type="text"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="メールの件名を入力してください"
                          required
                        />
                        <div
                          v-if="replyForm.errors.subject"
                          class="mt-1 text-sm text-red-600"
                        >
                          {{ replyForm.errors.subject }}
                        </div>
                      </div>

                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                          メッセージ
                        </label>
                        <textarea
                          v-model="replyForm.message"
                          rows="8"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          :placeholder="isNewEmail ? 'メッセージを入力してください' : '返信メッセージを入力してください'"
                          required
                        ></textarea>
                      </div>

                      <div>
                        <button
                          type="submit"
                          :disabled="replyForm.processing || isSendingEmail"
                          class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150"
                        >
                          {{ (replyForm.processing || isSendingEmail) ? "送信中..." : (isNewEmail ? "メールを送信" : "返信メールを送信") }}
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
                
                <!-- メールやり取り表示エリア -->
                <div v-if="filteredEmailThreads && filteredEmailThreads.length > 0" class="space-y-6">
                  <div
                    v-for="thread in filteredEmailThreads"
                    :key="thread.id"
                    class="border border-gray-200 rounded-lg p-4"
                  >
                    <div class="mb-3">
                      <h4 class="font-medium text-gray-900">{{ thread.subject }}</h4>
                      <p class="text-xs text-gray-500 mt-1">
                        作成日時: {{ formatDateTime(thread.created_at) }}
                      </p>
                    </div>
                    
                    <div class="space-y-4">
                      <div
                        v-for="email in thread.emails"
                        :key="email.id"
                        :class="[
                          'flex items-start gap-3',
                          email.from === reservation.email ? 'justify-start' : 'justify-end'
                        ]"
                      >
                        <!-- お客様側のアイコン（左側） -->
                        <div
                          v-if="email.from === reservation.email"
                          class="flex-shrink-0 w-12 h-12 rounded-full bg-gray-200 border-2 border-gray-300 overflow-hidden flex items-center justify-center"
                        >
                          <svg class="w-7 h-7 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                          </svg>
                        </div>
                        
                        <div
                          :class="[
                            'relative max-w-[80%] rounded-lg px-4 py-3 shadow-sm',
                            email.from === reservation.email 
                              ? 'bg-blue-50 border border-blue-200 chat-bubble-left' 
                              : 'bg-green-50 border border-green-200 chat-bubble-right'
                          ]"
                        >
                          <!-- 吹き出しの尾（三角形） - お客様（左側） -->
                          <template v-if="email.from === reservation.email">
                            <div
                              class="absolute left-0 top-4 -ml-2 w-0 h-0 border-t-[8px] border-t-transparent border-r-[8px] border-r-blue-200 border-b-[8px] border-b-transparent"
                            ></div>
                            <div
                              class="absolute left-0 top-4 -ml-[6px] w-0 h-0 border-t-[6px] border-t-transparent border-r-[6px] border-r-blue-50 border-b-[6px] border-b-transparent"
                            ></div>
                          </template>
                          <!-- 吹き出しの尾（三角形） - 当店（右側） -->
                          <template v-else>
                            <div
                              class="absolute right-0 top-4 -mr-2 w-0 h-0 border-t-[8px] border-t-transparent border-l-[8px] border-l-green-200 border-b-[8px] border-b-transparent"
                            ></div>
                            <div
                              class="absolute right-0 top-4 -mr-[6px] w-0 h-0 border-t-[6px] border-t-transparent border-l-[6px] border-l-green-50 border-b-[6px] border-b-transparent"
                            ></div>
                          </template>
                          
                          <div class="mb-2">
                            <div class="flex justify-between items-start gap-3">
                              <div>
                                <p 
                                  :class="[
                                    'text-sm font-medium',
                                    email.from === reservation.email ? 'text-blue-900' : 'text-green-900'
                                  ]"
                                >
                                  {{ email.from === reservation.email ? 'お客様' : '当店' }}
                                </p>
                                <p 
                                  :class="[
                                    'text-xs mt-0.5',
                                    email.from === reservation.email ? 'text-blue-600' : 'text-green-600'
                                  ]"
                                >
                                  {{ email.from === reservation.email ? email.from : email.to }}
                                </p>
                              </div>
                              <p 
                                :class="[
                                  'text-xs whitespace-nowrap',
                                  email.from === reservation.email ? 'text-blue-600' : 'text-green-600'
                                ]"
                              >
                                {{ formatDateTime(email.created_at) }}
                              </p>
                            </div>
                          </div>
                          
                          <p 
                            :class="[
                              'text-sm font-medium mb-2',
                              email.from === reservation.email ? 'text-blue-900' : 'text-green-900'
                            ]"
                          >
                            {{ email.subject }}
                          </p>
                          
                          <div 
                            :class="[
                              'text-sm whitespace-pre-wrap',
                              email.from === reservation.email ? 'text-blue-800' : 'text-green-800'
                            ]"
                          >
                            {{ email.text_body || email.html_body || '-' }}
                          </div>
                          
                          <div v-if="email.attachments && email.attachments.length > 0" class="mt-3 pt-3 border-t" :class="email.from === reservation.email ? 'border-blue-200' : 'border-green-200'">
                            <p 
                              :class="[
                                'text-xs mb-1',
                                email.from === reservation.email ? 'text-blue-600' : 'text-green-600'
                              ]"
                            >
                              添付ファイル:
                            </p>
                            <ul 
                              :class="[
                                'text-xs space-y-1',
                                email.from === reservation.email ? 'text-blue-700' : 'text-green-700'
                              ]"
                            >
                              <li v-for="attachment in email.attachments" :key="attachment.id">
                                • {{ attachment.filename }}
                              </li>
                            </ul>
                          </div>
                        </div>
                        
                        <!-- 当店側のアイコン（右側） -->
                        <div
                          v-if="email.from !== reservation.email"
                          class="flex-shrink-0 w-12 h-12 rounded-full bg-white border-2 border-green-300 overflow-hidden flex items-center justify-center shadow-sm"
                        >
                          <img
                            src="/storage/logo/logo_b.png"
                            alt="店舗ロゴ"
                            class="w-full h-full object-contain p-1"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div v-else class="text-sm text-gray-500 text-center py-8">
                  メールのやり取りはありません
                </div>
              </div>
            </div>
          </div>

          <!-- 右側: ステータス・メモ機能 -->
          <div class="lg:col-span-1">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div class="p-6">
                <!-- ステータス登録 -->
                <div class="mb-6 pb-6 border-b border-gray-200">
                  <h3 class="text-lg font-semibold mb-4">対応ステータス</h3>
                  <div v-if="reservation.status" class="mb-3">
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600"
                        >現在のステータス:</span
                      >
                      <span
                        :class="getStatusBadgeClass(reservation.status)"
                        class="px-2 py-1 text-xs font-medium rounded-full"
                      >
                        {{ reservation.status }}
                      </span>
                    </div>
                    <div
                      v-if="reservation.status_updated_by"
                      class="mt-2 text-xs text-gray-500"
                    >
                      更新者: {{ reservation.status_updated_by.name }}
                    </div>
                  </div>
                  <form @submit.prevent="updateStatus">
                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >ステータス</label
                      >
                      <select
                        v-model="statusForm.status"
                        required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      >
                        <option value="未対応">未対応</option>
                        <option value="確認中">確認中</option>
                        <option value="返信待ち">返信待ち</option>
                        <option value="対応完了済み">対応完了済み</option>
                        <option value="キャンセル済み">キャンセル済み</option>
                      </select>
                      <div
                        v-if="statusForm.errors.status"
                        class="mt-1 text-sm text-red-600"
                      >
                        {{ statusForm.errors.status }}
                      </div>
                    </div>
                    <button
                      type="submit"
                      :disabled="statusForm.processing"
                      class="mt-2 w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                    >
                      {{
                        statusForm.processing ? "更新中..." : "ステータスを更新"
                      }}
                    </button>
                  </form>
                </div>

                <h3 class="text-lg font-semibold mb-4">メモ</h3>

                <!-- メモ登録フォーム -->
                <form @submit.prevent="submitNote" class="mb-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                      >新しいメモ</label
                    >
                    <textarea
                      v-model="noteForm.content"
                      rows="4"
                      required
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      placeholder="メモを入力してください"
                    ></textarea>
                    <div
                      v-if="noteForm.errors.content"
                      class="mt-1 text-sm text-red-600"
                    >
                      {{ noteForm.errors.content }}
                    </div>
                  </div>
                  <button
                    type="submit"
                    :disabled="noteForm.processing"
                    class="mt-2 w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                  >
                    {{ noteForm.processing ? "登録中..." : "メモを追加" }}
                  </button>
                </form>

                <!-- メモ一覧 -->
                <div class="space-y-4">
                  <div
                    v-for="note in notes"
                    :key="note.id"
                    class="border-b border-gray-200 pb-4 last:border-b-0 last:pb-0"
                  >
                    <div class="flex justify-between items-start mb-2">
                      <div>
                        <p class="text-sm font-medium text-gray-900">
                          {{ note.user ? note.user.name : "不明" }}
                        </p>
                        <p class="text-xs text-gray-500">
                          {{ formatDateTime(note.created_at) }}
                        </p>
                      </div>
                      <button
                        @click="deleteNote(note.id)"
                        class="text-red-600 hover:text-red-900 text-sm"
                      >
                        削除
                      </button>
                    </div>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">
                      {{ note.content }}
                    </p>
                  </div>
                  <p
                    v-if="notes.length === 0"
                    class="text-sm text-gray-500 text-center py-4"
                  >
                    メモがありません
                  </p>
                </div>

                <!-- スケジュール追加ブロック -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                  <h3 class="text-lg font-semibold mb-4">スケジュール</h3>

                  <!-- スケジュール追加済みの場合 -->
                  <div
                    v-if="schedule"
                    class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md"
                  >
                    <div class="flex items-center justify-between">
                      <div>
                        <p class="text-sm font-medium text-green-800">
                          スケジュールに追加済み
                        </p>
                        <p class="text-xs text-green-600 mt-1">
                          {{ formatDateTime(schedule.start_at) }} ～
                          {{ formatDateTime(schedule.end_at) }}
                        </p>
                      </div>
                      <button
                        @click="removeFromSchedule"
                        :disabled="scheduleForm.processing"
                        class="px-3 py-1 text-sm bg-red-600 text-white rounded-md hover:bg-red-700 disabled:bg-gray-400"
                      >
                        解除
                      </button>
                    </div>
                  </div>

                  <!-- スケジュール追加フォーム -->
                  <form v-else @submit.prevent="addToSchedule" class="mb-6">
                    <div class="mb-4">
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >タイトル</label
                      >
                      <input
                        v-model="scheduleForm.title"
                        type="text"
                        required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="スケジュールのタイトル"
                      />
                      <div
                        v-if="scheduleForm.errors.title"
                        class="mt-1 text-sm text-red-600"
                      >
                        {{ scheduleForm.errors.title }}
                      </div>
                    </div>

                    <div class="mb-4">
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >開始日時</label
                      >
                      <input
                        v-model="scheduleForm.start_at"
                        type="datetime-local"
                        required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      />
                    </div>

                    <div class="mb-4">
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >終了日時</label
                      >
                      <input
                        v-model="scheduleForm.end_at"
                        type="datetime-local"
                        required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      />
                    </div>

                    <div class="mb-4">
                      <label class="flex items-center">
                        <input
                          v-model="scheduleForm.all_day"
                          type="checkbox"
                          class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="ml-2 text-sm text-gray-700">終日</span>
                      </label>
                    </div>

                    <div class="mb-4">
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >担当者</label
                      >
                      
                      <!-- 店舗選択（参加者追加用） -->
                      <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">店舗を選択して参加者を追加</label>
                        <select
                          v-model="scheduleForm.selectedShopId"
                          @change="onShopChangeForSchedule"
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                          <option value="">店舗を選択してください</option>
                          <option
                            v-for="shop in eventShops"
                            :key="shop.id"
                            :value="shop.id"
                          >
                            {{ shop.name }}
                          </option>
                        </select>
                      </div>

                      <!-- 参加者追加済み一覧 -->
                      <div v-if="addedParticipantsForSchedule.length > 0" class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">参加者追加済み</label>
                        <div class="flex flex-wrap gap-2">
                          <span
                            v-for="participant in addedParticipantsForSchedule"
                            :key="participant.id"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-100 text-indigo-800 rounded-full text-xs font-medium"
                          >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            {{ participant.name }}
                            <button
                              type="button"
                              @click="removeParticipantForSchedule(participant.id)"
                              class="ml-1 text-indigo-600 hover:text-indigo-800 font-bold"
                            >
                              ×
                            </button>
                          </span>
                        </div>
                      </div>

                      <!-- 店舗ユーザー一覧（チェックボックス） -->
                      <div v-if="scheduleForm.selectedShopId && shopUsersForSchedule.length > 0" class="space-y-2">
                        <div class="max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-3 bg-white">
                          <label
                            v-for="user in shopUsersForSchedule"
                            :key="user.id"
                            class="flex items-center space-x-2 p-2 rounded-lg transition-colors cursor-pointer hover:bg-gray-50"
                          >
                            <input
                              :id="`participant-${user.id}`"
                              type="checkbox"
                              :checked="isParticipantAddedForSchedule(user.id)"
                              @change="
                                toggleParticipantForSchedule(
                                  user.id,
                                  $event.target.checked
                                )
                              "
                              class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-sm text-gray-900">
                              {{ user.name }}
                            </span>
                          </label>
                        </div>
                      </div>
                      <p v-else-if="!scheduleForm.selectedShopId" class="text-sm text-gray-500">
                        店舗を選択すると、その店舗に所属するユーザーが表示されます
                      </p>
                    </div>

                    <div class="mb-4">
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >説明</label
                      >
                      <textarea
                        v-model="scheduleForm.description"
                        rows="6"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="スケジュールの説明"
                      ></textarea>
                      <div
                        v-if="scheduleForm.errors.description"
                        class="mt-1 text-sm text-red-600"
                      >
                        {{ scheduleForm.errors.description }}
                      </div>
                    </div>

                    <button
                      type="submit"
                      :disabled="scheduleForm.processing"
                      class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                    >
                      {{
                        scheduleForm.processing
                          ? "追加中..."
                          : "スケジュールに追加"
                      }}
                    </button>
                  </form>
                </div>

                <!-- キャンセル済みの場合のみ：完全削除 -->
                <div
                  v-if="reservation.cancel_flg"
                  class="mt-6 pt-6 border-t border-gray-200"
                >
                  <button
                    type="button"
                    @click="forceDeleteReservation"
                    class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                  >
                    削除
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ActionButton from "@/Components/ActionButton.vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { ref, computed, onMounted } from "vue";
import axios from "axios";

const isRestoring = ref(false);

const props = defineProps({
  emailThreads: {
    type: Array,
    default: () => [],
  },
  reservation: Object,
  event: Object,
  venues: Array,
  notes: Array,
  schedule: Object,
  canRestore: {
    type: Boolean,
    default: false,
  },
  currentUser: Object,
  userShops: Array,
  eventShops: Array,
});

const formatDateTime = (datetime) => {
  if (!datetime) return "-";
  const date = new Date(datetime);
  return date.toLocaleString("ja-JP");
};

const formatDate = (dateString) => {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return date.toLocaleDateString("ja-JP");
};

// 郵便番号を XXX-XXXX 形式で表示
const formatPostalCode = (val) => {
  if (!val) return "-";
  const digits = String(val).replace(/-/g, "").replace(/\D/g, "").slice(0, 7);
  if (digits.length === 7) return digits.slice(0, 3) + "-" + digits.slice(3, 7);
  return digits || "-";
};

// キャンセルを解除する
const restoreReservation = () => {
  if (!confirm("キャンセルを解除しますか？")) return;
  isRestoring.value = true;
  router.patch(route("admin.reservations.restore", props.reservation.id), {}, {
    preserveScroll: true,
    onFinish: () => {
      isRestoring.value = false;
    },
  });
};

// キャンセル済み予約を完全削除する
const forceDeleteReservation = () => {
  if (!confirm("この操作は取り消しできません。本当に削除しますか？")) return;
  router.delete(route("admin.reservations.force-destroy", props.reservation.id));
};

// メールスレッドを新しい順（降順）でソート
const sortedEmailThreads = computed(() => {
  if (!props.emailThreads || props.emailThreads.length === 0) {
    return [];
  }
  
  return [...props.emailThreads]
    .map(thread => ({
      ...thread,
      emails: thread.emails 
        ? [...thread.emails].sort((a, b) => {
            const dateA = new Date(a.created_at);
            const dateB = new Date(b.created_at);
            return dateB - dateA; // 降順（新しい順）
          })
        : []
    }))
    .sort((a, b) => {
      // スレッド内の最新メールの日時で比較
      const latestEmailA = a.emails && a.emails.length > 0 
        ? new Date(a.emails[0].created_at) 
        : new Date(a.created_at);
      const latestEmailB = b.emails && b.emails.length > 0 
        ? new Date(b.emails[0].created_at) 
        : new Date(b.created_at);
      return latestEmailB - latestEmailA; // 降順（新しい順）
    });
});

const statusForm = useForm({
  status: props.reservation.status || "未対応",
});

const updateStatus = () => {
  statusForm.patch(
    route("admin.reservations.status.update", props.reservation.id),
    {
      onSuccess: (page) => {
        // ステータス更新後、ページをリロードして最新の状態を取得
        router.reload({ only: ["reservation"] });
        // 成功メッセージを表示
        if (page.props.success) {
          alert(page.props.success);
        }
      },
    }
  );
};

const noteForm = useForm({
  content: "",
});

const submitNote = () => {
  noteForm.post(route("admin.reservations.notes.store", props.reservation.id), {
    onSuccess: () => {
      noteForm.reset();
    },
  });
};

const deleteNote = (noteId) => {
  if (confirm("このメモを削除しますか？")) {
    router.delete(route("admin.reservations.notes.destroy", noteId));
  }
};

const getStatusBadgeClass = (status) => {
  const classes = {
    未対応: "bg-gray-100 text-gray-800",
    確認中: "bg-yellow-100 text-yellow-800",
    返信待ち: "bg-blue-100 text-blue-800",
    対応完了済み: "bg-green-100 text-green-800",
    キャンセル済み: "bg-red-100 text-red-800",
  };
  return classes[status] || "bg-gray-100 text-gray-800";
};

// スケジュール関連
const userShops = computed(() => props.userShops || []);
const eventShops = computed(() => props.eventShops || []);
const shopUsersForSchedule = ref([]);
const addedParticipantsForSchedule = ref([]);

// デフォルトのタイトルを生成
function getDefaultTitle() {
  return `${props.reservation.name}様の予約`;
}

// デフォルトの説明を生成
function getDefaultDescription() {
  let description = `予約ID: ${props.reservation.id}\n`;
  description += `お名前: ${props.reservation.name}\n`;
  description += `メール: ${props.reservation.email}\n`;
  description += `電話: ${props.reservation.phone}\n`;
  if (props.reservation.reservation_datetime) {
    description += `予約日時: ${props.reservation.reservation_datetime}\n`;
  }
  if (props.reservation.venue) {
    description += `会場: ${props.reservation.venue.name}\n`;
  }
  if (props.reservation.inquiry_message) {
    description += `お問い合わせ内容: ${props.reservation.inquiry_message}`;
  }
  return description;
}

const scheduleForm = useForm({
  title: getDefaultTitle(),
  description: getDefaultDescription(),
  selectedShopId: "",
  user_id: props.currentUser ? props.currentUser.id : "",
  start_at: props.reservation.reservation_datetime
    ? formatDateTimeForInput(props.reservation.reservation_datetime)
    : "",
  end_at: props.reservation.reservation_datetime
    ? formatDateTimeForInput(props.reservation.reservation_datetime, 1)
    : "",
  all_day: false,
  participant_ids: [],
  processing: false,
});

// 日時をdatetime-local形式に変換
function formatDateTimeForInput(datetime, addHours = 0) {
  if (!datetime) return "";
  const date = new Date(datetime);
  if (addHours > 0) {
    date.setHours(date.getHours() + addHours);
  }
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// 店舗変更時の処理
async function onShopChangeForSchedule() {
  if (!scheduleForm.selectedShopId) {
    shopUsersForSchedule.value = [];
    return;
  }

  try {
    const response = await axios.get(route("admin.schedules.shop-users"), {
      params: { shop_id: scheduleForm.selectedShopId },
    });
    shopUsersForSchedule.value = response.data;
  } catch (error) {
    console.error("店舗ユーザーの取得に失敗しました:", error);
    shopUsersForSchedule.value = [];
  }
}

// 参加者が追加済みかチェック
function isParticipantAddedForSchedule(userId) {
  return addedParticipantsForSchedule.value.some((p) => p.id === userId);
}

// 参加者を追加/削除
function toggleParticipantForSchedule(userId, checked) {
  if (checked) {
    const user = shopUsersForSchedule.value.find((u) => u.id === userId);
    if (user && !isParticipantAddedForSchedule(userId)) {
      addedParticipantsForSchedule.value.push(user);
      scheduleForm.participant_ids = addedParticipantsForSchedule.value.map(
        (p) => p.id
      );
    }
  } else {
    addedParticipantsForSchedule.value =
      addedParticipantsForSchedule.value.filter((p) => p.id !== userId);
    scheduleForm.participant_ids = addedParticipantsForSchedule.value.map(
      (p) => p.id
    );
  }
}

// 参加者を削除（バッジから）
function removeParticipantForSchedule(userId) {
  addedParticipantsForSchedule.value =
    addedParticipantsForSchedule.value.filter((p) => p.id !== userId);
  scheduleForm.participant_ids = addedParticipantsForSchedule.value.map(
    (p) => p.id
  );
}

// スケジュールに追加
function addToSchedule() {
  // ログインユーザーをuser_idとして使用
  if (!props.currentUser || !props.currentUser.id) {
    alert("ログインユーザー情報が取得できません。");
    return;
  }

  const user_id = props.currentUser.id;

  // チェックボックスで選択された参加者のみを使用（ログインユーザーは自動で含めない）
  const participantIds = scheduleForm.participant_ids || [];

  // フォームデータを更新
  scheduleForm.user_id = user_id;
  scheduleForm.participant_ids = participantIds;

  scheduleForm.post(
    route("admin.reservations.schedule.add", props.reservation.id),
    {
      onSuccess: () => {
        scheduleForm.reset();
        scheduleForm.title = getDefaultTitle();
        scheduleForm.description = getDefaultDescription();
        scheduleForm.selectedShopId = "";
        scheduleForm.user_id = props.currentUser ? props.currentUser.id : "";
        shopUsersForSchedule.value = [];
        addedParticipantsForSchedule.value = [];
        router.reload({ only: ["schedule"] });
      },
      onError: (errors) => {
        scheduleForm.processing = false;
        // エラーメッセージを表示
        if (errors && Object.keys(errors).length > 0) {
          const errorMessages = Object.values(errors).flat();
          alert(errorMessages.join("\n"));
        }
      },
    }
  );
}

// スケジュールから解除
function removeFromSchedule() {
  if (confirm("スケジュールから解除しますか？")) {
    scheduleForm.processing = true;
    router.delete(
      route("admin.reservations.schedule.remove", props.reservation.id),
      {
        onSuccess: () => {
          scheduleForm.processing = false;
          router.reload({ only: ["schedule"] });
        },
        onError: () => {
          scheduleForm.processing = false;
        },
      }
    );
  }
}

// 返信メールフォーム
const replyForm = useForm({
  email_thread_id: "",
  subject: "",
  message: "",
});

const isSendingEmail = ref(false);
const selectedThreadId = ref(null);

// 新規メール作成かどうかを判定
const isNewEmail = computed(() => {
  return replyForm.email_thread_id === "new" || replyForm.email_thread_id === "";
});

// 選択されたスレッドのメールのみ表示
const filteredEmailThreads = computed(() => {
  if (!selectedThreadId.value) {
    return sortedEmailThreads.value;
  }
  return sortedEmailThreads.value.filter(thread => thread.id === selectedThreadId.value);
});

// スレッド選択時の処理
const onThreadChange = () => {
  if (replyForm.email_thread_id === "new" || replyForm.email_thread_id === "") {
    selectedThreadId.value = null;
    replyForm.subject = "";
  } else {
    selectedThreadId.value = replyForm.email_thread_id;
  }
};

const sendReplyEmail = () => {
  console.log('メール送信開始', {
    reservation_id: props.reservation.id,
    email_thread_id: replyForm.email_thread_id,
    is_new_email: isNewEmail.value,
    subject: replyForm.subject,
    message_length: replyForm.message.length,
  });

  // 新規メール作成の場合
  if (isNewEmail.value) {
    if (!replyForm.subject || replyForm.subject.trim() === '') {
      console.error('件名が入力されていません');
      alert('件名を入力してください');
      return;
    }
  } else {
    // 既存スレッドへの返信の場合
    if (!replyForm.email_thread_id) {
      console.error('スレッドIDが選択されていません');
      alert('スレッドを選択してください');
      return;
    }
  }

  if (!replyForm.message || replyForm.message.trim() === '') {
    console.error('メッセージが入力されていません');
    alert('メッセージを入力してください');
    return;
  }

  // ローディング開始
  isSendingEmail.value = true;

  // 新規メール作成の場合はemail_thread_idをnullに設定
  const formData = {
    email_thread_id: isNewEmail.value ? null : replyForm.email_thread_id,
    subject: isNewEmail.value ? replyForm.subject : null,
    message: replyForm.message,
  };

  replyForm.transform(() => formData).post(
    route("admin.reservations.reply-email", props.reservation.id),
    {
      onSuccess: (page) => {
        console.log('メール送信成功', page);
        
        // 成功メッセージを表示
        const successMessage = page.props.flash?.success || page.props.success || (isNewEmail.value ? 'メールを送信しました。' : '返信メールを送信しました。');
        alert(successMessage);
        
        // 新規メール作成の場合、作成したスレッドIDを保存
        const wasNewEmail = isNewEmail.value;
        // 既存スレッドへの返信の場合、選択したスレッドIDを保存
        const previousThreadId = !wasNewEmail ? replyForm.email_thread_id : null;
        
        // フォームをリセット
        replyForm.reset();
        replyForm.email_thread_id = "";
        replyForm.subject = "";
        replyForm.message = "";
        
        // メールスレッドを再読み込み（少し待ってから実行）
        setTimeout(() => {
          router.reload({ 
            only: ["emailThreads"],
            preserveScroll: true,
            onSuccess: (reloadedPage) => {
              // 新規作成した場合、最新のスレッド（新規作成したもの）を自動選択
              if (wasNewEmail && sortedEmailThreads.value && sortedEmailThreads.value.length > 0) {
                selectedThreadId.value = sortedEmailThreads.value[0].id;
                replyForm.email_thread_id = sortedEmailThreads.value[0].id;
              } else if (!wasNewEmail && previousThreadId) {
                // 既存スレッドへの返信の場合、選択したスレッドを維持
                selectedThreadId.value = previousThreadId;
                replyForm.email_thread_id = previousThreadId;
              } else {
                selectedThreadId.value = null;
              }
            }
          });
        }, 500);
      },
      onError: (errors) => {
        console.error('メール送信エラー', errors);
        
        // エラーメッセージを表示
        let errorMessage = isNewEmail.value ? 'メールの送信に失敗しました。' : '返信メールの送信に失敗しました。';
        
        if (errors.email_thread_id) {
          errorMessage = 'スレッドの選択に問題があります: ' + (Array.isArray(errors.email_thread_id) ? errors.email_thread_id[0] : errors.email_thread_id);
        } else if (errors.subject) {
          errorMessage = '件名に問題があります: ' + (Array.isArray(errors.subject) ? errors.subject[0] : errors.subject);
        } else if (errors.message && Array.isArray(errors.message)) {
          errorMessage = 'メッセージに問題があります: ' + errors.message[0];
        } else if (errors.message) {
          errorMessage = errors.message;
        }
        
        alert(errorMessage);
      },
      onFinish: () => {
        console.log('メール送信処理完了');
        // ローディング終了
        isSendingEmail.value = false;
      },
    }
  );
};

// 初期化
onMounted(() => {
  // メールスレッドが1つだけの場合は自動選択（新しい順にソート済みの最初のスレッド）
  if (sortedEmailThreads.value && sortedEmailThreads.value.length === 1) {
    replyForm.email_thread_id = sortedEmailThreads.value[0].id;
    selectedThreadId.value = sortedEmailThreads.value[0].id;
  } else if (sortedEmailThreads.value && sortedEmailThreads.value.length > 0) {
    // 複数のスレッドがある場合、最新のスレッドを自動選択
    replyForm.email_thread_id = sortedEmailThreads.value[0].id;
    selectedThreadId.value = sortedEmailThreads.value[0].id;
  }

  // ログインユーザーの所属店舗の最初の店舗を自動選択
  if (userShops.value.length > 0) {
    scheduleForm.selectedShopId = userShops.value[0].id;
    onShopChangeForSchedule();
  }
});
</script>

