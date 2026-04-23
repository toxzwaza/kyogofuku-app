<template>
  <Head title="予約詳細" />

  <AdminLayout
    :breadcrumb="[
      { label: 'イベント管理', href: route('admin.events.index') },
      { label: '予約詳細' },
    ]"
  >
    <!-- ローディングオーバーレイ -->
    <div
      v-if="isSendingEmail"
      class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50"
    >
      <div class="bg-brand-surface rounded-lg p-8 flex flex-col items-center">
        <svg
          class="animate-spin h-12 w-12 text-brand-primary mb-4"
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
        <p class="text-brand-text font-medium">メールを送信しています...</p>
      </div>
    </div>

    <UiPageHeader
      :title="`予約詳細 #${reservation.id}`"
      :description="reservation.name ? `${reservation.name} 様の予約` : '予約の詳細情報と操作'"
    >
      <template #actions>
        <UiBadge v-if="reservation.cancel_flg" variant="danger" dot>キャンセル済み</UiBadge>
        <UiBadge v-else-if="reservation.status" :variant="statusBadgeVariant(reservation.status)" dot>{{ reservation.status }}</UiBadge>
        <UiButton variant="ghost" :href="indexBackUrl">
          <template #leading><ArrowLeft :size="14" /></template>
          予約一覧に戻る
        </UiButton>
        <UiButton variant="primary" :href="route('admin.reservations.edit', reservation.id)">
          <template #leading><Pencil :size="14" /></template>
          編集
        </UiButton>
      </template>
    </UiPageHeader>

    <UiTabs v-model="activeTab" :tabs="tabs">
      <!-- 概要タブ -->
      <template #overview>
        <div class="space-y-4 max-w-4xl">
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
                  class="inline-flex items-center px-4 py-2 bg-brand-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-primary-hover focus:bg-brand-primary-hover active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-brand-primary focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
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
                reservation.cancel_flg ? 'bg-brand-surface-2' : 'bg-brand-surface',
              ]"
            >
              <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                  <h3 class="text-xl font-bold text-brand-text flex items-center gap-2">
                    <svg class="w-6 h-6 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    予約情報
                  </h3>
                </div>

                <!-- 基本情報セクション -->
                <div class="mb-6">
                  <h4 class="text-sm font-semibold text-brand-text-muted uppercase tracking-wide mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    基本情報
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- 予約ID -->
                    <div class="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-lg p-4 border border-indigo-100 md:col-span-2">
                      <label class="block text-xs font-semibold text-brand-primary uppercase tracking-wide mb-1">予約ID</label>
                      <p class="text-lg font-bold text-brand-text">{{ reservation.id }}</p>
                    </div>
                    <!-- お名前、フリガナ -->
                    <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                      <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        お名前
                      </label>
                      <p class="text-lg font-semibold text-brand-text">{{ reservation.name }}</p>
                    </div>
                    <div v-if="event.form_type === 'reservation' || event.form_type === 'reservation_hakama' || event.form_type === 'document'" class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                      <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                        フリガナ
                      </label>
                      <p class="text-base font-medium text-brand-text">{{ reservation.furigana || '-' }}</p>
                    </div>

                    <!-- メールアドレス、電話番号 -->
                    <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                      <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        メールアドレス
                      </label>
                      <p class="text-base font-semibold text-brand-text">{{ reservation.email }}</p>
                    </div>
                    <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                      <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        電話番号
                      </label>
                      <p class="text-base font-semibold text-brand-text">{{ reservation.phone }}</p>
                    </div>
                  </div>
                </div>

                <!-- 予約フォーム (reservation) -->
                <template v-if="event.form_type === 'reservation'">
                  <!-- 予約情報セクション -->
                  <div class="mb-6">
                    <h4 class="text-sm font-semibold text-brand-text-muted uppercase tracking-wide mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      予約情報
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                          </svg>
                          予約日時
                        </label>
                        <p class="text-base font-semibold text-brand-text">{{ reservation.reservation_datetime || "-" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                          </svg>
                          ご来店会場
                        </label>
                        <p class="text-base font-semibold text-brand-text">{{ reservation.venue ? reservation.venue.name : "-" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                          過去当店のご来店
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.has_visited_before ? "あり" : "なし" }}</p>
                      </div>
                    </div>
                  </div>

                  <!-- 個人情報セクション -->
                  <div class="mb-6">
                    <h4 class="text-sm font-semibold text-brand-text-muted uppercase tracking-wide mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                      個人情報
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                          </svg>
                          郵便番号
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ formatPostalCode(reservation.postal_code) }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                          </svg>
                          住所
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.address || "-" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                          </svg>
                          生年月日
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.birth_date ? formatDate(reservation.birth_date) : "-" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                          </svg>
                          成人式予定年月
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.seijin_year ? reservation.seijin_year + "年" : "-" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                          </svg>
                          学校名
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.school_name || "-" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                          </svg>
                          担当者指名
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.staff_name || "-" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                          </svg>
                          来店動機
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.visit_reasons && reservation.visit_reasons.length > 0 ? reservation.visit_reasons.join("、") : "-" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                          </svg>
                          流入経路
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.utm_source || "-" }}</p>
                      </div>
                    </div>
                  </div>

                  <!-- その他情報セクション -->
                  <div class="mb-6">
                    <h4 class="text-sm font-semibold text-brand-text-muted uppercase tracking-wide mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      その他情報
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                          </svg>
                          駐車場利用
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.parking_usage || "-" }}</p>
                      </div>
                      <div v-if="reservation.parking_usage === 'あり'" class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                          </svg>
                          駐車台数
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.parking_car_count || "-" }}台</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                          </svg>
                          検討中のプラン
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.considering_plans && reservation.considering_plans.length > 0 ? reservation.considering_plans.join("、") : "-" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                          </svg>
                          ご紹介者様お名前
                        </label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.referred_by_name || "-" }}</p>
                      </div>
                    </div>
                  </div>
                </template>

                <!-- 袴予約（岡山） -->
                <template v-if="event.form_type === 'reservation_hakama'">
                  <div class="mb-6">
                    <h4 class="text-sm font-semibold text-brand-text-muted uppercase tracking-wide mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      予約情報
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">予約日時</label>
                        <p class="text-base font-semibold text-brand-text">{{ reservation.reservation_datetime || "—" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">ご来店会場</label>
                        <p class="text-base font-semibold text-brand-text">{{ reservation.venue ? reservation.venue.name : "—" }}</p>
                      </div>
                    </div>
                  </div>
                  <div class="mb-6">
                    <h4 class="text-sm font-semibold text-brand-text-muted uppercase tracking-wide mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                      お客様情報
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">郵便番号</label>
                        <p class="text-base font-medium text-brand-text">{{ formatPostalCode(reservation.postal_code) }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">住所</label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.address || "—" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">学校名</label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.school_name || "—" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">好一での振袖利用</label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.koichi_furisode_used === true ? "あり" : reservation.koichi_furisode_used === false ? "なし" : "—" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">卒業式</label>
                        <p class="text-base font-medium text-brand-text">
                          {{ formatGraduationCeremonyDisplay(reservation) }}
                        </p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">来店人数</label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.visitor_count != null ? reservation.visitor_count + "名" : "—" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">お連れ様</label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.companion_types && reservation.companion_types.length > 0 ? reservation.companion_types.join("、") : "—" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">お連れ様の袴着用</label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.companion_hakama_usage === true ? "着用する" : reservation.companion_hakama_usage === false ? "着用しない" : "—" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border md:col-span-2">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">来店動機</label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.visit_reasons && reservation.visit_reasons.length > 0 ? reservation.visit_reasons.join("、") : "—" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">流入経路</label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.utm_source || "—" }}</p>
                      </div>
                    </div>
                  </div>
                  <div class="mb-6">
                    <h4 class="text-sm font-semibold text-brand-text-muted uppercase tracking-wide mb-4 flex items-center gap-2">その他情報</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">お車で来店</label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.parking_usage || "—" }}</p>
                      </div>
                      <div v-if="reservation.parking_usage === 'あり'" class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">台数</label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.parking_car_count || "—" }}台</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border md:col-span-2">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">検討中のプラン</label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.considering_plans && reservation.considering_plans.length > 0 ? reservation.considering_plans.join("、") : "—" }}</p>
                      </div>
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1">ご紹介者様お名前</label>
                        <p class="text-base font-medium text-brand-text">{{ reservation.referred_by_name || "—" }}</p>
                      </div>
                    </div>
                  </div>
                </template>

                  <!-- 資料請求フォーム (document) -->
                  <template v-if="event.form_type === 'document'">
                    <!-- 資料請求情報セクション -->
                    <div class="mb-6">
                      <h4 class="text-sm font-semibold text-brand-text-muted uppercase tracking-wide mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        資料請求情報
                      </h4>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                          <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            請求方法
                          </label>
                          <p class="text-base font-semibold text-brand-text">{{ reservation.request_method || "-" }}</p>
                        </div>
                        <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                          <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            生年月日
                          </label>
                          <p class="text-base font-medium text-brand-text">{{ reservation.birth_date ? formatDate(reservation.birth_date) : "-" }}</p>
                        </div>
                        <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                          <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            郵便番号
                          </label>
                          <p class="text-base font-medium text-brand-text">{{ formatPostalCode(reservation.postal_code) }}</p>
                        </div>
                        <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border md:col-span-2">
                          <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            住所
                          </label>
                          <p class="text-base font-medium text-brand-text">{{ reservation.address || "-" }}</p>
                        </div>
                        <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                          <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            個人情報保護方針への同意
                          </label>
                          <p class="text-base font-medium text-brand-text">{{ reservation.privacy_agreed ? "同意" : "-" }}</p>
                        </div>
                      </div>
                    </div>
                  </template>

                  <!-- お問い合わせフォーム (contact) -->
                  <template v-if="event.form_type === 'contact'">
                    <div class="mb-6">
                      <h4 class="text-sm font-semibold text-brand-text-muted uppercase tracking-wide mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                        お問い合わせ情報
                      </h4>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                          <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            問い合わせ回答方法
                          </label>
                          <p class="text-base font-medium text-brand-text">{{ reservation.heard_from || "-" }}</p>
                        </div>
                      </div>
                    </div>
                  </template>

                  <!-- お問い合わせ内容セクション -->
                  <div v-if="reservation.inquiry_message" class="mb-6">
                    <h4 class="text-sm font-semibold text-brand-text-muted uppercase tracking-wide mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                      </svg>
                      お問い合わせ内容
                    </h4>
                    <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                      <p class="text-sm text-brand-text whitespace-pre-wrap leading-relaxed">{{ reservation.inquiry_message }}</p>
                    </div>
                  </div>

                  <!-- システム情報セクション -->
                  <div class="mb-6">
                    <h4 class="text-sm font-semibold text-brand-text-muted uppercase tracking-wide mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                      </svg>
                      システム情報
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                        <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                          </svg>
                          登録日時
                        </label>
                        <p class="text-base font-semibold text-brand-text">{{ formatDateTime(reservation.created_at) }}</p>
                      </div>
                    </div>
                  </div>
              </div>
            </div>
          </div>
      </template>

      <!-- 対応・管理タブ -->
      <template #manage>
        <div class="max-w-3xl">
            <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg">
              <div class="p-6">
                <!-- ステータス登録 -->
                <div class="mb-6 pb-6 border-b border-brand-border">
                  <h3 class="text-lg font-semibold mb-4">対応ステータス</h3>
                  <div v-if="reservation.status" class="mb-3">
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-brand-text-muted"
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
                      class="mt-2 text-xs text-brand-text-muted"
                    >
                      更新者: {{ reservation.status_updated_by.name }}
                    </div>
                  </div>
                  <form @submit.prevent="updateStatus">
                    <div>
                      <label
                        class="block text-sm font-medium text-brand-text mb-1"
                        >ステータス</label
                      >
                      <select
                        v-model="statusForm.status"
                        required
                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
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
                      class="mt-2 w-full px-4 py-2 bg-brand-primary text-white rounded-md hover:bg-brand-primary-hover disabled:bg-gray-400"
                    >
                      {{
                        statusForm.processing ? "更新中..." : "ステータスを更新"
                      }}
                    </button>
                  </form>
                </div>

                <!-- 担当者（管理） -->
                <div class="mb-6 pb-6 border-b border-brand-border">
                  <h3 class="text-lg font-semibold mb-4">担当者</h3>
                  <form @submit.prevent="submitAssignee">
                    <label
                      class="block text-sm font-medium text-brand-text mb-1"
                      for="reservation-admin-assignee"
                      >担当者</label
                    >
                    <input
                      id="reservation-admin-assignee"
                      v-model="assigneeForm.admin_assignee"
                      type="text"
                      list="reservation-assignee-options"
                      autocomplete="off"
                      class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                      placeholder="名前を入力するか一覧から選択"
                    />
                    <datalist id="reservation-assignee-options">
                      <option
                        v-for="(opt, idx) in assigneeDatalistOptions"
                        :key="`${opt}-${idx}`"
                        :value="opt"
                      />
                    </datalist>
                    <div
                      v-if="assigneeForm.errors.admin_assignee"
                      class="mt-1 text-sm text-red-600"
                    >
                      {{ assigneeForm.errors.admin_assignee }}
                    </div>
                    <button
                      type="submit"
                      :disabled="assigneeForm.processing"
                      class="mt-2 w-full px-4 py-2 bg-brand-primary text-white rounded-md hover:bg-brand-primary-hover disabled:bg-gray-400"
                    >
                      {{
                        assigneeForm.processing
                          ? "更新中..."
                          : "保存担当者を更新"
                      }}
                    </button>
                  </form>
                </div>

                <!-- 入場チケット送付 -->
                <div class="mb-6 pb-6 border-b border-brand-border">
                  <h3 class="text-lg font-semibold mb-4">入場チケット送付</h3>
                  <div class="mb-3">
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-brand-text-muted">現在のステータス:</span>
                      <span
                        :class="
                          getEntranceTicketSendBadgeClass(
                            reservation.entrance_ticket_send_status
                          )
                        "
                        class="px-2 py-1 text-xs font-medium rounded-full"
                      >
                        {{
                          reservation.entrance_ticket_send_status || "未送付"
                        }}
                      </span>
                    </div>
                  </div>
                  <form @submit.prevent="submitEntranceTicketSendStatus">
                    <div>
                      <label
                        class="block text-sm font-medium text-brand-text mb-1"
                        >ステータス</label
                      >
                      <select
                        v-model="entranceTicketSendForm.entrance_ticket_send_status"
                        required
                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                      >
                        <option value="未送付">未送付</option>
                        <option value="送付済み">送付済み</option>
                      </select>
                      <div
                        v-if="
                          entranceTicketSendForm.errors
                            .entrance_ticket_send_status
                        "
                        class="mt-1 text-sm text-red-600"
                      >
                        {{
                          entranceTicketSendForm.errors
                            .entrance_ticket_send_status
                        }}
                      </div>
                    </div>
                    <button
                      type="submit"
                      :disabled="entranceTicketSendForm.processing"
                      class="mt-2 w-full px-4 py-2 bg-brand-primary text-white rounded-md hover:bg-brand-primary-hover disabled:bg-gray-400"
                    >
                      {{
                        entranceTicketSendForm.processing
                          ? "更新中..."
                          : "ステータスを更新"
                      }}
                    </button>
                  </form>
                </div>

                <!-- Googleカレンダー連携 -->
                <div class="mb-6 pb-6 border-b border-brand-border">
                  <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <svg
                      class="w-5 h-5 text-blue-600"
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
                    Googleカレンダー連携
                  </h3>

                  <!-- 連携済み -->
                  <div
                    v-if="
                      googleCalendarSyncInfo.syncs &&
                      googleCalendarSyncInfo.syncs.length > 0
                    "
                  >
                    <div class="mb-3 flex items-center gap-2">
                      <span
                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"
                      >
                        <span
                          class="w-2 h-2 bg-green-500 rounded-full mr-1.5"
                        ></span>
                        連携済み（{{ googleCalendarSyncInfo.syncs.length }}件）
                      </span>
                    </div>
                    <div class="space-y-2 mb-3">
                      <div
                        v-for="sync in googleCalendarSyncInfo.syncs"
                        :key="sync.id"
                        class="p-3 bg-green-50 rounded-lg border border-green-200"
                      >
                        <div class="flex items-start justify-between gap-2">
                          <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium text-brand-text">
                              {{ sync.shop_name || "—" }}
                            </div>
                            <div
                              class="text-xs text-brand-text-muted mt-0.5 truncate"
                              :title="sync.google_calendar_id"
                            >
                              {{ sync.google_calendar_id }}
                            </div>
                          </div>
                          <a
                            :href="sync.html_link"
                            target="_blank"
                            rel="noopener"
                            class="shrink-0 text-xs text-brand-primary hover:underline whitespace-nowrap"
                          >
                            カレンダーで開く →
                          </a>
                        </div>
                      </div>
                    </div>
                    <button
                      type="button"
                      @click="syncGoogleCalendar"
                      :disabled="googleCalendarSyncForm.processing"
                      class="w-full px-3 py-2 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-md hover:bg-indigo-200 disabled:opacity-50"
                    >
                      {{
                        googleCalendarSyncForm.processing
                          ? "処理中..."
                          : "再同期"
                      }}
                    </button>
                  </div>

                  <!-- 未連携 -->
                  <div v-else>
                    <div class="mb-3 flex items-center gap-2">
                      <span
                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"
                      >
                        <span
                          class="w-2 h-2 bg-yellow-500 rounded-full mr-1.5"
                        ></span>
                        未連携
                      </span>
                    </div>
                    <p
                      v-if="googleCalendarSyncInfo.can_sync"
                      class="text-xs text-brand-text-muted mb-3"
                    >
                      この予約はまだGoogleカレンダーに登録されていません。
                      <span
                        v-if="
                          googleCalendarSyncInfo.expected_shops &&
                          googleCalendarSyncInfo.expected_shops.length > 0
                        "
                        class="font-medium"
                      >
                        {{
                          googleCalendarSyncInfo.expected_shops
                            .map((s) => s.name)
                            .join("、")
                        }}
                      </span>
                      のカレンダーに登録できます。
                    </p>
                    <p v-else class="text-xs text-red-600 mb-3">
                      {{
                        googleCalendarSyncInfo.cannot_sync_reason ||
                        "連携できない状態です。"
                      }}
                    </p>
                    <button
                      type="button"
                      @click="syncGoogleCalendar"
                      :disabled="
                        !googleCalendarSyncInfo.can_sync ||
                        googleCalendarSyncForm.processing
                      "
                      class="w-full px-4 py-2 bg-brand-primary text-white rounded-md hover:bg-brand-primary-hover disabled:bg-gray-400 text-sm font-medium"
                    >
                      {{
                        googleCalendarSyncForm.processing
                          ? "連携中..."
                          : "Googleカレンダーに連携する"
                      }}
                    </button>
                  </div>
                </div>


                <!-- 顧客紐づけ -->
                <div class="mt-6 pt-6 border-t border-brand-border">
                  <h3 class="text-lg font-semibold text-brand-text mb-1">顧客紐づけ</h3>
                  <p class="text-xs text-brand-text-muted mb-4">
                    この予約と顧客マスタを紐づけると、顧客詳細から参加イベントとして表示されます。
                  </p>

                  <!-- 顧客が紐づいている場合 -->
                  <div
                    v-if="reservation.customer && !showCustomerLinkSearch"
                    class="rounded-lg border-2 border-indigo-200 bg-indigo-50/50 p-4"
                  >
                    <div class="flex items-start justify-between gap-3">
                      <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-brand-primary mb-1">紐づき顧客</p>
                        <p class="text-base font-semibold text-brand-text">
                          {{ reservation.customer.name }}
                        </p>
                        <p class="text-sm text-brand-text-muted mt-0.5">
                          顧客ID: {{ reservation.customer.id }}
                        </p>
                      </div>
                      <div class="flex shrink-0 items-center gap-2">
                        <button
                          type="button"
                          :disabled="isUnlinkingCustomer"
                          @click="unlinkCustomer"
                          class="inline-flex items-center gap-1.5 rounded-md border border-brand-border bg-brand-surface px-3 py-1.5 text-sm font-medium text-brand-text shadow-sm hover:bg-brand-surface-2 disabled:opacity-50"
                        >
                          {{ isUnlinkingCustomer ? '解除中...' : '解除' }}
                        </button>
                        <button
                          type="button"
                          @click="openCustomerLinkSearch"
                          class="inline-flex items-center gap-1.5 rounded-md bg-brand-primary px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-brand-primary-hover"
                        >
                          変更
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- 顧客未紐づけ、または変更時：検索UI -->
                  <div v-else class="rounded-lg border border-brand-border bg-brand-surface-2/50 p-4">
                    <p v-if="!reservation.customer" class="text-sm text-brand-text mb-4">
                      <span class="font-medium text-brand-text">顧客が紐づいていません。</span><br />
                      下の検索で既存顧客を選ぶか、下の「顧客追加」から新規登録してください。
                    </p>
                    <p v-else class="text-sm text-brand-text mb-4">
                      別の顧客に変更する場合は、名前で検索して紐づけ直してください。
                    </p>
                    <div class="mb-3">
                      <label class="mb-1.5 block text-sm font-medium text-brand-text">顧客名で検索</label>
                      <input
                        v-model="customerSearchName"
                        type="text"
                        class="block w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                        placeholder="例: 山田 太郎"
                        @input="searchCustomers"
                      />
                      <p class="mt-1 text-xs text-brand-text-muted">スペースは無視して検索されます</p>
                    </div>
                    <div v-if="isSearchingCustomers" class="flex items-center gap-2 py-3 text-sm text-brand-text-muted">
                      <svg class="h-4 w-4 animate-spin text-brand-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                      </svg>
                      検索中...
                    </div>
                    <div
                      v-else-if="customerSearchResults.length > 0"
                      class="overflow-x-auto rounded-md border border-brand-border bg-brand-surface shadow-sm"
                    >
                      <table class="min-w-full divide-y divide-brand-border">
                        <thead class="bg-brand-surface-2">
                          <tr>
                            <th class="whitespace-nowrap px-3 py-2 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">ID</th>
                            <th class="whitespace-nowrap px-3 py-2 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">名前</th>
                            <th class="whitespace-nowrap px-3 py-2 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">電話番号</th>
                            <th class="whitespace-nowrap px-3 py-2 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">成人エリア</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border bg-brand-surface">
                          <tr
                            v-for="c in customerSearchResults"
                            :key="c.id"
                            :class="[
                              'cursor-pointer transition-colors',
                              selectedSearchCustomer?.id === c.id
                                ? 'bg-indigo-100 hover:bg-indigo-100'
                                : 'hover:bg-brand-surface-2',
                            ]"
                            @click="selectedSearchCustomer = c"
                          >
                            <td class="whitespace-nowrap px-3 py-2 text-sm text-brand-text">{{ c.id }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-sm font-medium text-brand-text">{{ c.name }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-sm text-brand-text-muted">{{ c.phone_number || '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-sm text-brand-text-muted">{{ c.ceremony_area?.name || '-' }}</td>
                          </tr>
                        </tbody>
                      </table>
                      <!-- 選択した顧客の操作ボタン（テーブル下部に横並び） -->
                      <div
                        v-if="selectedSearchCustomer"
                        class="flex flex-wrap items-center gap-2 border-t border-brand-border bg-brand-surface-2 px-3 py-3"
                      >
                        <span class="mr-2 text-sm text-brand-text-muted">
                          {{ selectedSearchCustomer.name }} を選択中
                        </span>
                        <Link
                          :href="route('admin.customers.show', selectedSearchCustomer.id)"
                          class="inline-flex items-center rounded-md border border-brand-border bg-brand-surface px-3 py-1.5 text-sm font-medium text-brand-text shadow-sm hover:bg-brand-surface-2"
                        >
                          詳細
                        </Link>
                        <span
                          v-if="reservation.customer_id && selectedSearchCustomer.id === reservation.customer_id"
                          class="inline-flex items-center rounded-md border border-brand-border bg-brand-surface-2 px-3 py-1.5 text-sm font-medium text-brand-text-muted"
                        >
                          紐づけ済
                        </span>
                        <button
                          v-else
                          type="button"
                          :disabled="isLinkingCustomerId === selectedSearchCustomer.id"
                          @click="linkCustomer(selectedSearchCustomer.id)"
                          class="inline-flex items-center rounded-md border border-transparent bg-brand-primary px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-brand-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          {{ isLinkingCustomerId === selectedSearchCustomer.id ? '紐づけ中...' : '紐づけ' }}
                        </button>
                      </div>
                    </div>
                    <p
                      v-else-if="customerSearchName && !isSearchingCustomers"
                      class="py-3 text-sm text-brand-text-muted"
                    >
                      該当する顧客がいません。別のキーワードで検索するか、顧客を新規追加してください。
                    </p>
                    <button
                      v-if="reservation.customer && showCustomerLinkSearch"
                      type="button"
                      @click="showCustomerLinkSearch = false"
                      class="mt-3 text-sm font-medium text-brand-text-muted hover:text-brand-text"
                    >
                      ← キャンセル（紐づき表示に戻る）
                    </button>
                  </div>
                </div>

                <!-- 顧客閲覧 / 顧客追加 -->
                <div class="mt-6 pt-6 border-t border-brand-border">
                  <div class="flex flex-wrap gap-2">
                    <ActionButton
                      v-if="reservation.customer_id"
                      variant="detail"
                      label="顧客閲覧"
                      :href="route('admin.customers.show', reservation.customer_id)"
                    />
                    <ActionButton
                      v-else
                      variant="create"
                      label="顧客追加"
                      :href="route('admin.customers.index', { add_from_reservation: reservation.id })"
                    />
                  </div>
                </div>

                <!-- キャンセル済みの場合のみ：完全削除 -->
                <div
                  v-if="reservation.cancel_flg"
                  class="mt-6 pt-6 border-t border-brand-border"
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
      </template>

      <!-- 連絡・履歴タブ -->
      <template #comm>
        <div class="max-w-4xl space-y-4">
            <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg">
              <div
                class="flex border-b border-brand-border gap-1 px-1 pt-1"
                role="tablist"
                aria-label="連絡"
              >
                <button
                  type="button"
                  role="tab"
                  :class="[
                    'flex-1 px-3 py-2.5 text-sm font-medium rounded-t-md border-b-2 transition-colors',
                    communicationTab === 'line'
                      ? 'border-indigo-600 text-indigo-700 bg-indigo-50/50'
                      : 'border-transparent text-brand-text-muted hover:text-brand-text hover:bg-brand-surface-2',
                  ]"
                  :aria-selected="communicationTab === 'line'"
                  @click="communicationTab = 'line'"
                >
                  LINE
                </button>
                <button
                  type="button"
                  role="tab"
                  :class="[
                    'flex-1 px-3 py-2.5 text-sm font-medium rounded-t-md border-b-2 transition-colors',
                    communicationTab === 'email'
                      ? 'border-indigo-600 text-indigo-700 bg-indigo-50/50'
                      : 'border-transparent text-brand-text-muted hover:text-brand-text hover:bg-brand-surface-2',
                  ]"
                  :aria-selected="communicationTab === 'email'"
                  @click="communicationTab = 'email'"
                >
                  メール
                </button>
              </div>
              <div class="p-6">
                <div v-show="communicationTab === 'line'" class="min-w-0">
                  <CustomerLineSection
                    v-if="line_section"
                    variant="embedded"
                    :customer="lineCustomerForLineSection"
                    :shops="line_section.shops"
                    :line-api="line_section.context === 'reservation' ? line_section : null"
                  />
                  <p v-else class="text-sm text-brand-text-muted text-center py-6">
                    LINE 連携情報がありません。
                  </p>
                </div>
                <div v-show="communicationTab === 'email'" class="min-w-0">
                  <!-- ヘッダー: スレッド件数 + 新規メール作成ボタン -->
                  <div class="flex items-center justify-between mb-4">
                    <p class="text-sm text-brand-text-muted">
                      <span class="font-medium text-brand-text">{{ sortedEmailThreads.length }}</span>
                      件のスレッド
                    </p>
                    <button
                      type="button"
                      @click="openNewComposer"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-brand-primary text-white rounded-md text-sm font-medium hover:bg-brand-primary-hover"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                      </svg>
                      新規メール作成
                    </button>
                  </div>

                  <!-- 新規メール作成フォーム（インライン展開） -->
                  <div
                    v-if="showNewComposer"
                    class="mb-4 border border-indigo-200 bg-indigo-50/30 rounded-lg p-4"
                  >
                    <div class="flex items-center justify-between mb-3">
                      <h4 class="text-sm font-semibold text-brand-text">新規メール作成</h4>
                      <button
                        type="button"
                        @click="cancelNewComposer"
                        class="text-brand-text-subtle hover:text-brand-text-muted"
                        aria-label="閉じる"
                      >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                    <form @submit.prevent="submitNewEmail" class="space-y-3">
                      <div>
                        <label class="block text-xs font-medium text-brand-text mb-1">
                          宛先
                        </label>
                        <input
                          type="text"
                          :value="reservation.email"
                          readonly
                          class="block w-full rounded-md border-brand-border bg-brand-surface-2 text-sm text-brand-text-muted"
                        />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-brand-text mb-1">
                          件名 <span class="text-red-500">*</span>
                        </label>
                        <input
                          v-model="replyForm.subject"
                          type="text"
                          required
                          class="block w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                          placeholder="メールの件名を入力してください"
                        />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-brand-text mb-1">
                          本文
                        </label>
                        <textarea
                          v-model="replyForm.message"
                          rows="8"
                          required
                          class="block w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm font-mono"
                          placeholder="メッセージを入力してください"
                        ></textarea>
                      </div>
                      <div class="flex gap-2">
                        <button
                          type="submit"
                          :disabled="replyForm.processing || isSendingEmail"
                          class="inline-flex items-center gap-1.5 px-4 py-2 bg-brand-primary text-white rounded-md text-sm font-medium hover:bg-brand-primary-hover disabled:opacity-50"
                        >
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                          </svg>
                          {{ (replyForm.processing || isSendingEmail) ? "送信中..." : "送信" }}
                        </button>
                        <button
                          type="button"
                          @click="cancelNewComposer"
                          :disabled="replyForm.processing || isSendingEmail"
                          class="px-4 py-2 bg-brand-surface border border-brand-border text-brand-text rounded-md text-sm font-medium hover:bg-brand-surface-2 disabled:opacity-50"
                        >
                          キャンセル
                        </button>
                      </div>
                    </form>
                  </div>

                  <!-- スレッド一覧（Gmail風アコーディオン） -->
                  <div
                    v-if="sortedEmailThreads && sortedEmailThreads.length > 0"
                    class="space-y-3"
                  >
                    <div
                      v-for="thread in sortedEmailThreads"
                      :key="thread.id"
                      class="border border-brand-border rounded-lg bg-brand-surface overflow-hidden"
                      :class="{ 'ring-2 ring-indigo-300': isThreadExpanded(thread.id) }"
                    >
                      <!-- スレッドヘッダー -->
                      <button
                        type="button"
                        @click="toggleThread(thread.id)"
                        class="w-full px-4 py-3 text-left flex items-start gap-3 hover:bg-brand-surface-2 transition-colors"
                      >
                        <div class="flex-1 min-w-0">
                          <div class="flex items-center gap-2 mb-1">
                            <span
                              class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-brand-surface-2 text-brand-text"
                            >
                              {{ thread.emails ? thread.emails.length : 0 }}件
                            </span>
                            <span class="text-xs text-brand-text-muted">
                              {{ relativeTime(latestEmailTime(thread)) }}
                            </span>
                          </div>
                          <h3 class="text-base font-semibold text-brand-text truncate">
                            {{ thread.subject || "(件名なし)" }}
                          </h3>
                          <p
                            v-if="!isThreadExpanded(thread.id)"
                            class="text-sm text-brand-text-muted truncate mt-0.5"
                          >
                            {{ latestEmailPreview(thread) }}
                          </p>
                        </div>
                        <svg
                          :class="{ 'rotate-180': isThreadExpanded(thread.id) }"
                          class="w-5 h-5 text-brand-text-subtle shrink-0 mt-1 transition-transform"
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

                      <!-- 展開時：メールリスト + 返信フォーム -->
                      <div v-if="isThreadExpanded(thread.id)">
                        <div class="border-t border-brand-border divide-y divide-gray-100">
                          <div
                            v-for="(email, idx) in thread.emails"
                            :key="email.id"
                            class="px-4 py-3"
                            :class="
                              !isEmailExpanded(thread, email, idx)
                                ? 'cursor-pointer hover:bg-brand-surface-2'
                                : ''
                            "
                            @click="
                              !isEmailExpanded(thread, email, idx)
                                ? toggleEmail(email.id)
                                : null
                            "
                          >
                            <!-- メールヘッダー -->
                            <div class="flex items-start justify-between gap-3 mb-2">
                              <div class="flex items-center gap-2 min-w-0 flex-1">
                                <span
                                  class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold shrink-0"
                                  :class="
                                    isFromCustomer(email)
                                      ? 'bg-blue-100 text-blue-700'
                                      : 'bg-green-100 text-green-700'
                                  "
                                >
                                  {{ isFromCustomer(email) ? "客" : "店" }}
                                </span>
                                <div class="min-w-0 flex-1">
                                  <div class="text-sm font-medium text-brand-text">
                                    {{ isFromCustomer(email) ? "お客様" : "当店" }}
                                  </div>
                                  <div class="text-xs text-brand-text-muted truncate">
                                    {{ isFromCustomer(email) ? email.from : email.to }}
                                  </div>
                                </div>
                              </div>
                              <span class="text-xs text-brand-text-muted shrink-0 whitespace-nowrap">
                                {{ relativeTime(email.created_at) }}
                              </span>
                            </div>

                            <!-- 展開: 本文 + 添付 -->
                            <div v-if="isEmailExpanded(thread, email, idx)" class="mt-2">
                              <div class="text-sm text-brand-text whitespace-pre-wrap leading-relaxed">
                                <template
                                  v-for="(block, bIdx) in parseEmailBody(
                                    email.text_body || email.html_body || ''
                                  )"
                                  :key="bIdx"
                                >
                                  <div v-if="block.type === 'text'">{{ block.content }}</div>
                                  <div v-else class="my-2">
                                    <button
                                      type="button"
                                      @click.stop="toggleQuote(email.id, bIdx)"
                                      class="inline-flex items-center gap-1 px-2 py-0.5 bg-brand-surface-2 hover:bg-gray-200 text-brand-text-muted rounded text-xs"
                                    >
                                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                                      </svg>
                                      {{ isQuoteShown(email.id, bIdx) ? "引用を隠す" : "引用を表示" }}
                                    </button>
                                    <div
                                      v-if="isQuoteShown(email.id, bIdx)"
                                      class="mt-1 pl-3 border-l-2 border-brand-border text-brand-text-muted text-xs whitespace-pre-wrap"
                                    >{{ block.content }}</div>
                                  </div>
                                </template>
                              </div>

                              <!-- 添付ファイル -->
                              <div
                                v-if="email.attachments && email.attachments.length > 0"
                                class="mt-3 pt-3 border-t border-brand-border"
                              >
                                <p class="text-xs text-brand-text-muted mb-2">添付ファイル</p>
                                <div class="flex flex-wrap gap-2">
                                  <a
                                    v-for="att in email.attachments"
                                    :key="att.id"
                                    :href="route('admin.emails.attachments.download', att.id)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-brand-surface-2 hover:bg-gray-200 text-brand-text rounded-md text-xs font-medium"
                                  >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    {{ att.filename }}
                                  </a>
                                </div>
                              </div>
                            </div>

                            <!-- 折りたたみ: 1行プレビュー -->
                            <div v-else class="text-sm text-brand-text-muted truncate">
                              {{ emailPreview(email) }}
                            </div>
                          </div>
                        </div>

                        <!-- 返信フォーム（インライン） -->
                        <div class="border-t border-brand-border bg-brand-surface-2 p-3">
                          <div v-if="activeReplyThreadId !== thread.id">
                            <button
                              type="button"
                              @click="openReplyForm(thread.id)"
                              class="w-full py-2 bg-brand-surface border border-indigo-200 text-brand-primary hover:bg-indigo-50 rounded-md text-sm font-medium flex items-center justify-center gap-1.5"
                            >
                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                              </svg>
                              返信する
                            </button>
                          </div>
                          <form v-else @submit.prevent="submitInlineReply(thread.id)" class="space-y-2">
                            <div class="text-xs text-brand-text-muted">
                              <span class="font-medium">返信先:</span> {{ reservation.email }}
                            </div>
                            <textarea
                              v-model="replyForm.message"
                              rows="6"
                              required
                              class="block w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm font-mono"
                              placeholder="返信メッセージを入力してください"
                            ></textarea>
                            <div class="flex gap-2">
                              <button
                                type="submit"
                                :disabled="replyForm.processing || isSendingEmail"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-brand-primary text-white rounded-md text-sm font-medium hover:bg-brand-primary-hover disabled:opacity-50"
                              >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                                {{ (replyForm.processing || isSendingEmail) ? "送信中..." : "送信" }}
                              </button>
                              <button
                                type="button"
                                @click="cancelReply"
                                :disabled="replyForm.processing || isSendingEmail"
                                class="px-4 py-2 bg-brand-surface border border-brand-border text-brand-text rounded-md text-sm font-medium hover:bg-brand-surface-2 disabled:opacity-50"
                              >
                                キャンセル
                              </button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-else class="text-center py-12 text-brand-text-muted">
                    <svg class="w-12 h-12 mx-auto mb-3 text-brand-text-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <p class="text-sm">メールのやり取りはありません</p>
                    <button
                      type="button"
                      @click="openNewComposer"
                      class="mt-3 text-sm text-brand-primary hover:text-brand-primary-hover font-medium"
                    >
                      新規メールを作成する
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg">
              <div class="p-6">
                <div
                  class="flex border-b border-brand-border mb-4 -mt-1 gap-1"
                  role="tablist"
                  aria-label="メモと操作履歴"
                >
                  <button
                    type="button"
                    role="tab"
                    :class="[
                      'flex-1 px-3 py-2.5 text-sm font-medium rounded-t-md border-b-2 transition-colors',
                      rightPanelTab === 'memo'
                        ? 'border-indigo-600 text-indigo-700 bg-indigo-50/50'
                        : 'border-transparent text-brand-text-muted hover:text-brand-text hover:bg-brand-surface-2',
                    ]"
                    :aria-selected="rightPanelTab === 'memo'"
                    @click="rightPanelTab = 'memo'"
                  >
                    メモ ({{ notes.length }})
                  </button>
                  <button
                    type="button"
                    role="tab"
                    :class="[
                      'flex-1 px-3 py-2.5 text-sm font-medium rounded-t-md border-b-2 transition-colors',
                      rightPanelTab === 'history'
                        ? 'border-indigo-600 text-indigo-700 bg-indigo-50/50'
                        : 'border-transparent text-brand-text-muted hover:text-brand-text hover:bg-brand-surface-2',
                    ]"
                    :aria-selected="rightPanelTab === 'history'"
                    @click="rightPanelTab = 'history'"
                  >
                    操作履歴 ({{ activityLogs.length }})
                  </button>
                </div>

                <div v-show="rightPanelTab === 'memo'">
                  <form @submit.prevent="submitNote" class="mb-6">
                    <div>
                      <label
                        class="block text-sm font-medium text-brand-text mb-1"
                        >新しいメモ</label
                      >
                      <textarea
                        v-model="noteForm.content"
                        rows="4"
                        required
                        class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary"
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
                      class="mt-2 w-full px-4 py-2 bg-brand-primary text-white rounded-md hover:bg-brand-primary-hover disabled:bg-gray-400"
                    >
                      {{ noteForm.processing ? "登録中..." : "メモを追加" }}
                    </button>
                  </form>

                  <div class="space-y-4">
                    <div
                      v-for="note in notes"
                      :key="note.id"
                      class="border-b border-brand-border pb-4 last:border-b-0 last:pb-0"
                    >
                      <div class="flex justify-between items-start mb-2">
                        <div>
                          <p class="text-sm font-medium text-brand-text">
                            {{ note.user ? note.user.name : "不明" }}
                          </p>
                          <p class="text-xs text-brand-text-muted">
                            {{ formatDateTime(note.created_at) }}
                          </p>
                        </div>
                        <button
                          type="button"
                          @click="deleteNote(note.id)"
                          class="text-red-600 hover:text-red-900 text-sm"
                        >
                          削除
                        </button>
                      </div>
                      <p class="text-sm text-brand-text whitespace-pre-wrap">
                        {{ note.content }}
                      </p>
                    </div>
                    <p
                      v-if="notes.length === 0"
                      class="text-sm text-brand-text-muted text-center py-4"
                    >
                      メモがありません
                    </p>
                  </div>
                </div>

                <div v-show="rightPanelTab === 'history'">
                  <div
                    v-if="activityLogs.length === 0"
                    class="text-sm text-brand-text-muted text-center py-8"
                  >
                    操作履歴がありません
                  </div>
                  <div v-else class="overflow-x-auto -mx-1">
                    <table class="min-w-full text-sm">
                      <thead>
                        <tr
                          class="border-b border-brand-border text-left text-xs font-medium text-brand-text-muted uppercase tracking-wide"
                        >
                          <th class="py-2 pr-3 whitespace-nowrap">日時</th>
                          <th class="py-2 pr-3">操作内容</th>
                          <th class="py-2 whitespace-nowrap">操作者</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        <tr
                          v-for="log in activityLogs"
                          :key="log.id"
                          class="align-top"
                        >
                          <td class="py-2 pr-3 text-brand-text-muted whitespace-nowrap">
                            {{ formatDateTime(log.created_at) }}
                          </td>
                          <td class="py-2 pr-3 text-brand-text break-words">
                            {{ log.description || "—" }}
                          </td>
                          <td class="py-2 text-brand-text whitespace-nowrap">
                            {{ log.operator_name }}
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
        </div>
      </template>
    </UiTabs>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { UiPageHeader, UiButton, UiTabs, UiBadge } from "@/Components/UI";
import { ArrowLeft, Pencil, FileText, Settings, MessageSquare } from "lucide-vue-next";
import ActionButton from "@/Components/ActionButton.vue";
import CustomerLineSection from "@/Components/Admin/CustomerLineSection.vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { ref, computed, onMounted, watch } from "vue";
import axios from "axios";
import { formatDateTimeJa, formatDateJa } from "@/utils/dateFormat";

const isRestoring = ref(false);
const communicationTab = ref("line");
const rightPanelTab = ref("memo");

// Phase4 タブ切替
const activeTab = ref("overview");
const tabs = [
    { id: "overview", label: "概要" },
    { id: "manage",   label: "対応・管理" },
    { id: "comm",     label: "連絡・履歴" },
];

// ステータスのバッジ色
const statusBadgeVariant = (status) => ({
    "確認中":       "primary",
    "返信待ち":     "warning",
    "対応完了済み": "success",
    "キャンセル":   "danger",
    "未対応":       "neutral",
}[status] || "neutral");

// 顧客紐づけ（デフォルト値: 未紐づけ時は予約者名、紐づけ済み時は変更で顧客名をセット）
const showCustomerLinkSearch = ref(false);
const customerSearchName = ref("");
const customerSearchResults = ref([]);
const selectedSearchCustomer = ref(null);
const isSearchingCustomers = ref(false);
const isLinkingCustomerId = ref(null);
const isUnlinkingCustomer = ref(false);
let customerSearchTimeout = null;

// 変更押下時: 検索欄に予約情報のお名前をデフォルトにして検索UIを表示
const openCustomerLinkSearch = () => {
  customerSearchName.value = props.reservation?.name ?? "";
  showCustomerLinkSearch.value = true;
  searchCustomers();
};

const searchCustomers = () => {
  if (customerSearchTimeout) clearTimeout(customerSearchTimeout);
  const raw = customerSearchName.value.trim();
  if (!raw) {
    customerSearchResults.value = [];
    return;
  }
  // スペースを排除して検索（スペースの有無で紐づかないことを防ぐ）
  const nameForSearch = raw.replace(/\s/g, "");
  customerSearchTimeout = setTimeout(() => {
    isSearchingCustomers.value = true;
    axios
      .get(route("admin.customers.search"), { params: { name: nameForSearch } })
      .then((res) => {
        customerSearchResults.value = res.data.customers || [];
        selectedSearchCustomer.value = null;
      })
      .finally(() => {
        isSearchingCustomers.value = false;
      });
  }, 300);
};

const linkCustomer = (customerId) => {
  isLinkingCustomerId.value = customerId;
  router.patch(
    route("admin.reservations.customer.link", props.reservation.id),
    { customer_id: customerId },
    {
      preserveScroll: true,
      onSuccess: () => {
        showCustomerLinkSearch.value = false;
        customerSearchName.value = "";
        customerSearchResults.value = [];
        selectedSearchCustomer.value = null;
      },
      onFinish: () => {
        isLinkingCustomerId.value = null;
      },
    }
  );
};

const unlinkCustomer = () => {
  if (!confirm("この予約から顧客の紐づけを解除しますか？")) return;
  isUnlinkingCustomer.value = true;
  router.delete(route("admin.reservations.customer.unlink", props.reservation.id), {
    preserveScroll: true,
    onFinish: () => {
      isUnlinkingCustomer.value = false;
    },
  });
};

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
  assigneeDatalistOptions: {
    type: Array,
    default: () => [],
  },
  indexFilters: {
    type: Object,
    default: () => ({}),
  },
  activity_logs: {
    type: Array,
    default: () => [],
  },
  line_section: {
    type: Object,
    default: null,
  },
  googleCalendarSyncInfo: {
    type: Object,
    default: () => ({
      has_schedule: false,
      sync_enabled: false,
      syncs: [],
      expected_shops: [],
      can_sync: false,
      cannot_sync_reason: null,
    }),
  },
});

const lineCustomerForLineSection = computed(() => {
  if (!props.line_section || props.line_section.context !== "customer") {
    return null;
  }
  const c = props.reservation?.customer;
  if (!c) {
    return null;
  }
  return {
    id: c.id,
    shop_id: c.shop_id ?? null,
    line_contacts: props.line_section.line_contacts ?? [],
  };
});

const activityLogs = computed(() => props.activity_logs ?? []);

// 予約一覧への戻りURL（URLのクエリから絞り込みを取得し維持）
const indexQueryFromUrl = ref("");
const indexBackUrl = computed(() => {
  const base = route("admin.events.reservations.index", props.reservation?.event_id);
  const search = indexQueryFromUrl.value;
  return search ? `${base}?${search}` : base;
});

const formatDateTime = (datetime) => formatDateTimeJa(datetime);
const formatDate = (dateString) => formatDateJa(dateString);

// 郵便番号を XXX-XXXX 形式で表示
const formatGraduationCeremonyDisplay = (r) => {
  if (r.graduation_ceremony_date) {
    // ISO（例: JST 4/4 深夜 → UTC 4/3）を slice すると暦日がずれるため、そのまま formatDateJa に渡す
    return formatDateJa(r.graduation_ceremony_date);
  }
  if (r.graduation_ceremony_year != null && r.graduation_ceremony_month != null) {
    return `${r.graduation_ceremony_year}年${r.graduation_ceremony_month}月`;
  }
  return "—";
};

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

const assigneeForm = useForm({
  admin_assignee: props.reservation.admin_assignee ?? "",
});

const entranceTicketSendForm = useForm({
  entrance_ticket_send_status:
    props.reservation.entrance_ticket_send_status ?? "未送付",
});

watch(
  () => props.reservation?.admin_assignee,
  (v) => {
    assigneeForm.admin_assignee = v ?? "";
  }
);

watch(
  () => props.reservation?.entrance_ticket_send_status,
  (v) => {
    entranceTicketSendForm.entrance_ticket_send_status = v ?? "未送付";
  }
);

const submitAssignee = () => {
  assigneeForm.patch(
    route("admin.reservations.assignee.update", props.reservation.id),
    {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ["reservation", "activity_logs"] });
      },
    }
  );
};

const updateStatus = () => {
  statusForm.patch(
    route("admin.reservations.status.update", props.reservation.id),
    {
      onSuccess: (page) => {
        // ステータス更新後、ページをリロードして最新の状態を取得
        router.reload({ only: ["reservation", "activity_logs"] });
        // 成功メッセージを表示
        if (page.props.success) {
          alert(page.props.success);
        }
      },
    }
  );
};

const submitEntranceTicketSendStatus = () => {
  entranceTicketSendForm.patch(
    route(
      "admin.reservations.entrance-ticket-send-status.update",
      props.reservation.id
    ),
    {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ["reservation", "activity_logs"] });
      },
    }
  );
};

// Googleカレンダー連携
const googleCalendarSyncForm = useForm({});

const syncGoogleCalendar = () => {
  googleCalendarSyncForm.post(
    route("admin.reservations.google-calendar.sync", props.reservation.id),
    {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({
          only: ["reservation", "googleCalendarSyncInfo", "activity_logs"],
        });
      },
    }
  );
};

const noteForm = useForm({
  content: "",
});

const submitNote = () => {
  noteForm.post(route("admin.reservations.notes.store", props.reservation.id), {
    preserveScroll: true,
    onSuccess: () => {
      noteForm.reset();
    },
  });
};

const deleteNote = (noteId) => {
  if (confirm("このメモを削除しますか？")) {
    router.delete(route("admin.reservations.notes.destroy", noteId), {
      preserveScroll: true,
    });
  }
};

const getStatusBadgeClass = (status) => {
  const classes = {
    未対応: "bg-brand-surface-2 text-brand-text",
    確認中: "bg-yellow-100 text-yellow-800",
    返信待ち: "bg-blue-100 text-blue-800",
    対応完了済み: "bg-green-100 text-green-800",
    キャンセル済み: "bg-red-100 text-red-800",
  };
  return classes[status] || "bg-brand-surface-2 text-brand-text";
};

const getEntranceTicketSendBadgeClass = (value) => {
  if (value === "送付済み") {
    return "bg-green-100 text-green-800";
  }
  return "bg-brand-surface-2 text-brand-text";
};

// スケジュール関連
const userShops = computed(() => props.userShops || []);
const eventShops = computed(() => props.eventShops || []);
const shopUsersForSchedule = ref([]);
const addedParticipantsForSchedule = ref([]);

function formatSeijinYearForCalendar(reservation) {
  if (reservation.seijin_year == null || reservation.seijin_year === "") {
    return "未設定";
  }
  return `${reservation.seijin_year}年`;
}

function formatPlansForCalendar(reservation) {
  const p = reservation.considering_plans;
  if (!Array.isArray(p) || p.length === 0) return "未設定";
  const filtered = p.filter((x) => x != null && x !== "");
  return filtered.length === 0 ? "未設定" : filtered.join("、");
}

function formatCeremonyAreaForCalendar(reservation) {
  const n = reservation.customer?.ceremony_area?.name;
  if (n == null || String(n).trim() === "") return "未設定";
  return String(n).trim();
}

function formatAssigneeForCalendar(reservation) {
  const a = reservation.admin_assignee;
  if (a == null || String(a).trim() === "") return "未設定";
  return String(a).trim();
}

// デフォルトのタイトルを生成（Googleカレンダー／サーバの EventReservationCalendarPresentationService と同じルール）
function getDefaultTitle() {
  const r = props.reservation;
  return [
    r.name || "",
    formatSeijinYearForCalendar(r),
    formatPlansForCalendar(r),
    formatCeremonyAreaForCalendar(r),
    formatAssigneeForCalendar(r),
  ].join("/");
}

// デフォルトの説明を生成
function getDefaultDescription() {
  const r = props.reservation;
  let description = `成人年度: ${formatSeijinYearForCalendar(r)}\n`;
  description += `プラン: ${formatPlansForCalendar(r)}\n`;
  description += `成人式エリア: ${formatCeremonyAreaForCalendar(r)}\n`;
  description += `担当: ${formatAssigneeForCalendar(r)}\n`;
  description += "\n";
  description += `予約ID: ${r.id}\n`;
  description += `お名前: ${r.name}\n`;
  description += `メール: ${r.email}\n`;
  description += `電話: ${r.phone}\n`;
  if (r.reservation_datetime) {
    description += `予約日時: ${r.reservation_datetime}\n`;
  }
  if (r.venue) {
    description += `会場: ${r.venue.name}\n`;
  }
  if (r.inquiry_message) {
    description += `お問い合わせ内容: ${r.inquiry_message}`;
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
  is_public: true,
  sync_to_google_calendar: true,
  participant_ids: [],
  processing: false,
});

const isEditingSchedule = ref(false);

// 編集時の登録者選択肢（担当者＋店舗ユーザー＋現在の登録者）
const scheduleResponsibleUserOptions = computed(() => {
  const byId = new Map();
  if (props.schedule?.user) {
    byId.set(props.schedule.user.id, props.schedule.user);
  }
  addedParticipantsForSchedule.value.forEach((u) => byId.set(u.id, u));
  shopUsersForSchedule.value.forEach((u) => byId.set(u.id, u));
  return Array.from(byId.values());
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
        router.reload({ only: ["schedule", "activity_logs"] });
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

// スケジュール編集を開始
function startEditSchedule() {
  if (!props.schedule) return;
  scheduleForm.title = props.schedule.title;
  scheduleForm.description = props.schedule.description || "";
  scheduleForm.start_at = formatDateTimeForInput(props.schedule.start_at);
  scheduleForm.end_at = formatDateTimeForInput(props.schedule.end_at);
  scheduleForm.all_day = props.schedule.all_day || false;
  scheduleForm.is_public = props.schedule.is_public !== false;
  scheduleForm.sync_to_google_calendar = props.schedule.sync_to_google_calendar !== false;
  scheduleForm.user_id = props.schedule.user?.id || props.currentUser?.id || "";
  scheduleForm.participant_ids = props.schedule.participantUsers?.map((p) => p.id) || [];
  addedParticipantsForSchedule.value = props.schedule.participantUsers?.map((p) => ({ id: p.id, name: p.name })) || [];
  scheduleForm.selectedShopId = props.eventShops?.[0]?.id || "";
  if (scheduleForm.selectedShopId) {
    onShopChangeForSchedule();
  } else {
    shopUsersForSchedule.value = [];
  }
  isEditingSchedule.value = true;
}

// スケジュールを更新
async function updateSchedule() {
  if (!props.schedule) return;
  scheduleForm.processing = true;
  scheduleForm.participant_ids = addedParticipantsForSchedule.value.map((p) => p.id);

  try {
    await axios.put(route("admin.schedules.update", props.schedule.id), {
      title: scheduleForm.title,
      description: scheduleForm.description || "",
      start_at: scheduleForm.start_at,
      end_at: scheduleForm.end_at,
      all_day: scheduleForm.all_day,
      is_public: scheduleForm.is_public,
      sync_to_google_calendar: scheduleForm.sync_to_google_calendar,
      user_id: scheduleForm.user_id,
      participant_ids: scheduleForm.participant_ids,
    });
    isEditingSchedule.value = false;
    router.reload({ only: ["schedule", "activity_logs"] });
  } catch (error) {
    if (error.response?.data?.errors) {
      scheduleForm.errors = error.response.data.errors;
    } else {
      alert("スケジュールの更新に失敗しました。");
    }
  } finally {
    scheduleForm.processing = false;
  }
}

// スケジュール編集をキャンセル
function cancelEditSchedule() {
  isEditingSchedule.value = false;
  scheduleForm.clearErrors();
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
          isEditingSchedule.value = false;
          router.reload({ only: ["schedule", "activity_logs"] });
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

// Gmail風UI: スレッド・メール・引用の展開状態
const expandedThreadIds = ref(new Set());
const expandedEmailIds = ref(new Set());
const shownQuoteKeys = ref(new Set());
const activeReplyThreadId = ref(null);
const showNewComposer = ref(false);

// 新規メール作成かどうかを判定
const isNewEmail = computed(() => {
  return replyForm.email_thread_id === "new" || replyForm.email_thread_id === "";
});

// お客様からのメールか判定
const isFromCustomer = (email) => {
  if (!email || !email.from) return false;
  return email.from === props.reservation.email;
};

// スレッドの最新メールの時刻
const latestEmailTime = (thread) => {
  if (!thread.emails || thread.emails.length === 0) return thread.created_at;
  // emails は降順ソート済み（sortedEmailThreadsで処理）
  return thread.emails[0]?.created_at || thread.created_at;
};

// スレッドの最新メールの1行プレビュー
const latestEmailPreview = (thread) => {
  if (!thread.emails || thread.emails.length === 0) return "";
  const latest = thread.emails[0];
  return emailPreview(latest);
};

// メール本文の1行プレビュー（引用除外・改行除去・先頭100文字）
const emailPreview = (email) => {
  const text = email.text_body || email.html_body || "";
  if (!text) return "(本文なし)";
  // 引用行を除外
  const lines = text
    .split("\n")
    .filter((line) => !/^\s*>/.test(line))
    .join(" ")
    .replace(/\s+/g, " ")
    .trim();
  return lines.slice(0, 100) || "(本文なし)";
};

// 相対時刻フォーマット
const relativeTime = (iso) => {
  if (!iso) return "";
  const date = new Date(iso);
  const now = new Date();
  const diffMs = now - date;
  const diffMin = Math.floor(diffMs / 60000);

  if (diffMin < 1) return "たった今";
  if (diffMin < 60) return `${diffMin}分前`;

  const pad = (n) => String(n).padStart(2, "0");
  const hm = `${pad(date.getHours())}:${pad(date.getMinutes())}`;

  const isSameDay = (a, b) =>
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate();

  if (isSameDay(date, now)) return `今日 ${hm}`;

  const yesterday = new Date(now);
  yesterday.setDate(yesterday.getDate() - 1);
  if (isSameDay(date, yesterday)) return `昨日 ${hm}`;

  const diffDays = Math.floor(diffMs / 86400000);
  if (diffDays < 7) return `${diffDays}日前`;

  return `${date.getFullYear()}/${pad(date.getMonth() + 1)}/${pad(date.getDate())}`;
};

// 本文を text/quote ブロックにパース
const parseEmailBody = (text) => {
  if (!text) return [{ type: "text", content: "" }];
  const lines = text.split("\n");
  const blocks = [];
  let current = null;
  for (const line of lines) {
    const isQuote = /^\s*>/.test(line);
    const type = isQuote ? "quote" : "text";
    if (current && current.type === type) {
      current.content += "\n" + line;
    } else {
      if (current) blocks.push(current);
      current = { type, content: line };
    }
  }
  if (current) blocks.push(current);
  // 空の text ブロックを除去
  return blocks.filter((b) => b.type === "quote" || b.content.trim() !== "");
};

// スレッド展開トグル
const isThreadExpanded = (threadId) => expandedThreadIds.value.has(threadId);
const toggleThread = (threadId) => {
  const next = new Set(expandedThreadIds.value);
  if (next.has(threadId)) {
    next.delete(threadId);
    // 閉じたスレッドの返信フォームもクローズ
    if (activeReplyThreadId.value === threadId) {
      activeReplyThreadId.value = null;
    }
  } else {
    next.add(threadId);
  }
  expandedThreadIds.value = next;
};

// メール展開判定（最新メールは常に展開扱い、それ以外は手動展開時のみ）
const isEmailExpanded = (thread, email, idx) => {
  // スレッド内の最新（先頭＝idx=0）は自動展開
  if (idx === 0) return true;
  return expandedEmailIds.value.has(email.id);
};
const toggleEmail = (emailId) => {
  const next = new Set(expandedEmailIds.value);
  if (next.has(emailId)) next.delete(emailId);
  else next.add(emailId);
  expandedEmailIds.value = next;
};

// 引用ブロック展開トグル
const quoteKey = (emailId, bIdx) => `${emailId}_${bIdx}`;
const isQuoteShown = (emailId, bIdx) =>
  shownQuoteKeys.value.has(quoteKey(emailId, bIdx));
const toggleQuote = (emailId, bIdx) => {
  const key = quoteKey(emailId, bIdx);
  const next = new Set(shownQuoteKeys.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  shownQuoteKeys.value = next;
};

// 返信フォームの操作
const openReplyForm = (threadId) => {
  activeReplyThreadId.value = threadId;
  replyForm.email_thread_id = threadId;
  replyForm.subject = "";
  replyForm.message = "";
  selectedThreadId.value = threadId;
};
const cancelReply = () => {
  activeReplyThreadId.value = null;
  replyForm.reset();
  replyForm.email_thread_id = "";
  replyForm.subject = "";
  replyForm.message = "";
};
const submitInlineReply = (threadId) => {
  replyForm.email_thread_id = threadId;
  sendReplyEmail();
};

// 新規メール作成の操作
const openNewComposer = () => {
  showNewComposer.value = true;
  replyForm.email_thread_id = "new";
  replyForm.subject = "";
  replyForm.message = "";
  activeReplyThreadId.value = null;
};
const cancelNewComposer = () => {
  showNewComposer.value = false;
  replyForm.reset();
  replyForm.email_thread_id = "";
  replyForm.subject = "";
  replyForm.message = "";
};
const submitNewEmail = () => {
  replyForm.email_thread_id = "new";
  sendReplyEmail();
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

        // UI 状態リセット
        showNewComposer.value = false;
        activeReplyThreadId.value = null;

        // メールスレッドを再読み込み（少し待ってから実行）
        setTimeout(() => {
          router.reload({
            only: ["emailThreads", "activity_logs"],
            preserveScroll: true,
            onSuccess: (reloadedPage) => {
              if (wasNewEmail && sortedEmailThreads.value && sortedEmailThreads.value.length > 0) {
                // 新規作成した場合、最新スレッドを展開
                const newest = sortedEmailThreads.value[0];
                expandedThreadIds.value = new Set([newest.id]);
                selectedThreadId.value = newest.id;
              } else if (!wasNewEmail && previousThreadId) {
                // 既存スレッドへの返信の場合、そのスレッドを展開維持
                const next = new Set(expandedThreadIds.value);
                next.add(previousThreadId);
                expandedThreadIds.value = next;
                selectedThreadId.value = previousThreadId;
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
  const params = new URLSearchParams(window.location.search);
  const relevant = {};
  if (params.has("venue_id")) relevant.venue_id = params.get("venue_id");
  if (params.has("reservation_datetime")) relevant.reservation_datetime = params.get("reservation_datetime");
  indexQueryFromUrl.value = new URLSearchParams(relevant).toString();

  // 顧客未紐づけ時: 顧客名検索のデフォルト値にお名前（予約者名）を格納し、検索実行して結果を表示
  if (!props.reservation?.customer && props.reservation?.name) {
    customerSearchName.value = props.reservation.name;
    searchCustomers();
  }

  // メールスレッドがある場合、最新スレッドを自動展開
  if (sortedEmailThreads.value && sortedEmailThreads.value.length > 0) {
    const latestThreadId = sortedEmailThreads.value[0].id;
    expandedThreadIds.value = new Set([latestThreadId]);
    selectedThreadId.value = latestThreadId;
  }

  // ログインユーザーの所属店舗の最初の店舗を自動選択
  if (userShops.value.length > 0) {
    scheduleForm.selectedShopId = userShops.value[0].id;
    onShopChangeForSchedule();
  }
});
</script>

