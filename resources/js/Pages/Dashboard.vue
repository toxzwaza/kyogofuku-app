<template>
    <Head title="ダッシュボード" />

    <AuthenticatedLayout>
        <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        ダッシュボード
      </h2>
        </template>

        <div class="py-12">
            <div class="max-w-10xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <!-- 統計情報カード -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <StatCard
                        v-for="stat in statCards"
                        :key="stat.key"
                        :title="stat.title"
                        :value="stat.value"
                        :icon="stat.icon"
                        :color="stat.color"
                        :link="stat.link"
                    />
                </div>

        <!-- スケジュール管理 -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
              スケジュール管理
            </h3>
            
            <!-- 縦並びで2つのカレンダーを表示 -->
            <div class="space-y-6">
              <!-- 店舗単位カレンダー -->
              <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                <div class="mb-4">
                  <div class="flex items-center mb-3">
                    <h4 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                      <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                      </svg>
                      店舗単位スケジュール
                    </h4>
                  </div>
                  <!-- ユーザーチェックボックス -->
                  <div v-if="selectedShopId && usersForShopCalendar.length > 0" class="pt-3 border-t border-gray-200">
                    <div class="flex items-center gap-3 flex-wrap">
                      <select
                        v-model="selectedShopId"
                        @change="onShopChange"
                        class="w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      >
                        <option value="">店舗を選択</option>
                        <option v-for="shop in userShops" :key="shop.id" :value="shop.id">
                          {{ shop.name }}
                        </option>
                      </select>
                      <div class="flex items-center gap-2 ml-2">
                        <button
                          @click="selectAllUsers"
                          class="px-3 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        >
                          全選択
                        </button>
                        <button
                          @click="deselectAllUsers"
                          class="px-3 py-1 text-xs font-medium text-gray-600 bg-gray-50 border border-gray-200 rounded-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors"
                        >
                          全解除
                        </button>
                      </div>
                      <div class="flex items-center gap-4 flex-wrap ml-2">
                        <label
                          v-for="user in usersForShopCalendar"
                          :key="user.id"
                          class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer hover:text-indigo-600 transition-colors"
                        >
                          <input
                            type="checkbox"
                            :value="user.id"
                            v-model="selectedUserIdsForShopCalendar"
                            @change="onShopCalendarUserChange"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 focus:ring-2"
                          />
                          <span class="select-none">{{ user.name }}</span>
                        </label>
                      </div>
                    </div>
                  </div>
                  <div v-else class="pt-3 border-t border-gray-200">
                    <div class="flex items-center gap-3 flex-wrap">
                      <span class="text-sm font-medium text-gray-700">表示ユーザー:</span>
                      <select
                        v-model="selectedShopId"
                        @change="onShopChange"
                        class="w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      >
                        <option value="">店舗を選択</option>
                        <option v-for="shop in userShops" :key="shop.id" :value="shop.id">
                          {{ shop.name }}
                        </option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm" style="min-height: 600px;">
                  <FullCalendar ref="shopCalendar" :options="shopCalendarOptions" />
                </div>
              </div>

              <!-- ユーザー単位カレンダー -->
              <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                <div class="flex items-center justify-between mb-4">
                  <h4 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    ユーザー単位スケジュール
                  </h4>
                  <div class="flex items-center gap-3">
                    <select
                      v-model="selectedUserShopId"
                      @change="onUserShopChange"
                      class="w-40 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                      <option value="">店舗を選択</option>
                      <option v-for="shop in userShops" :key="shop.id" :value="shop.id">
                        {{ shop.name }}
                      </option>
                    </select>
                    <select
                      v-model="selectedUserId"
                      @change="onUserChange"
                      class="w-40 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                      <option value="">ユーザーを選択</option>
                      <option v-for="user in filteredUsersForUserCalendar" :key="user.id" :value="user.id">
                        {{ user.name }}
                      </option>
                    </select>
                  </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm" style="min-height: 500px;">
                  <FullCalendar ref="userCalendar" :options="userCalendarOptions" />
                </div>
              </div>
            </div>

            <!-- 勤怠データ出力セクション -->
            <div class="mt-6 border-t pt-4">
              <button
                @click="showAttendanceExport = !showAttendanceExport"
                class="flex items-center gap-2 text-sm font-medium text-gray-700 hover:text-indigo-600"
              >
                <svg
                  :class="['w-4 h-4 transition-transform', showAttendanceExport ? 'rotate-90' : '']"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                請求書発行
              </button>
              
              <div v-if="showAttendanceExport" class="mt-4 p-4 bg-gray-50 rounded-lg space-y-4">
                <div class="flex items-end gap-4">
                  <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-700 mb-1">集計開始日</label>
                    <input
                      v-model="attendanceStartDate"
                      type="date"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    />
                  </div>
                  <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-700 mb-1">集計終了日</label>
                    <input
                      v-model="attendanceEndDate"
                      type="date"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    />
                  </div>
                  <div class="flex gap-2">
                    <ActionButton
                      variant="search"
                      :disabled="exportingAttendance"
                      @click="loadAttendanceData"
                    >
                      {{ exportingAttendance ? '読込中...' : '表示' }}
                    </ActionButton>
                    <ActionButton
                      variant="save"
                      :disabled="exportingAttendance || attendanceData.length === 0 || !hasUnsavedChanges"
                      @click="saveExpenseCategories"
                    >
                      費用項目を保存
                    </ActionButton>
                    <ActionButton
                      variant="save"
                      :disabled="exportingAttendance || attendanceData.length === 0"
                      @click="exportAttendanceCsv"
                    >
                      請求書ダウンロード
                    </ActionButton>
                  </div>
                </div>
                
                <!-- 請求書テーブル -->
                <div v-if="attendanceData.length > 0" class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-100">
                      <tr>
                        <th class="whitespace-nowrap px-3 py-2 text-left font-medium text-gray-700">日付</th>
                        <th class="whitespace-nowrap px-3 py-2 text-left font-medium text-gray-700">作業時間</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-medium text-gray-700">勤務時間(h)</th>
                        <th class="whitespace-nowrap px-3 py-2 text-left font-medium text-gray-700">タスク</th>
                        <th class="whitespace-nowrap px-3 py-2 text-left font-medium text-gray-700">費用項目</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                      <tr v-for="row in attendanceData" :key="row.id" class="hover:bg-gray-50">
                        <td class="px-3 py-2 whitespace-nowrap">{{ row.date }}</td>
                        <td class="px-3 py-2 text-xs text-gray-600">{{ row.timeRange }}</td>
                        <td class="px-3 py-2 text-right font-medium">{{ row.totalHours }}</td>
                        <td class="px-3 py-2 text-xs text-gray-600">{{ row.title }}</td>
                        <td class="px-3 py-2">
                          <div class="flex items-center gap-2">
                            <select
                              :value="row.expenseCategory"
                              @change="updateExpenseCategoryLocal(row.id, $event.target.value)"
                              :class="[
                                'w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs',
                                row.isPredicted ? 'border-yellow-400 bg-yellow-50' : row.dbExpenseCategory ? 'border-green-400 bg-green-50' : ''
                              ]"
                            >
                              <option value="">選択してください</option>
                              <option v-for="category in expenseCategories" :key="category" :value="category">
                                {{ category }}
                              </option>
                            </select>
                            <span v-if="row.isPredicted" class="text-yellow-600 text-xs" title="推測された費用項目（未保存）">
                              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                              </svg>
                            </span>
                            <span v-else-if="row.dbExpenseCategory" class="text-green-600 text-xs" title="DBに保存済み">
                              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                              </svg>
                            </span>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                    <tfoot class="bg-gray-100">
                      <tr>
                        <td class="px-3 py-2 font-medium">合計</td>
                        <td class="px-3 py-2"></td>
                        <td class="px-3 py-2 text-right font-bold">{{ attendanceTotalHours }}時間</td>
                        <td class="px-3 py-2"></td>
                        <td class="px-3 py-2"></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
                
                <!-- 労働時間グラフ -->
                <div v-if="attendanceData.length > 0" class="mt-4">
                  <h4 class="text-sm font-medium text-gray-700 mb-2">日毎の労働時間推移</h4>
                  <div class="bg-white p-4 rounded border" style="height: 250px;">
                    <Bar :data="attendanceChartData" :options="attendanceChartOptions" />
                  </div>
                </div>
                
                <!-- 時間単価設定 -->
                <div v-if="attendanceData.length > 0" class="mt-4">
                  <label class="block text-xs font-medium text-gray-700 mb-1">時間単価（円/時間）</label>
                  <input
                    v-model.number="hourlyRate"
                    type="number"
                    min="0"
                    step="100"
                    class="w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                  />
                </div>
              </div>
            </div>
            
            <!-- 請求書モーダル -->
            <transition name="modal">
              <div
                v-if="showInvoiceModal && invoiceData"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="showInvoiceModal = false"
              >
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                  <!-- ヘッダー -->
                  <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                    <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                      請求書プレビュー
                    </h3>
                    <div class="flex gap-2">
                      <button
                        @click="downloadInvoice"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm"
                      >
                        PDFダウンロード
                      </button>
                      <button
                        @click="showInvoiceModal = false"
                        class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                      >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                  </div>
                  
                  <!-- コンテンツ（スクロール可能） -->
                  <div class="overflow-y-auto flex-1 p-6">
                    <Invoice
                      :client="invoiceData.client"
                      :issuer="invoiceData.issuer"
                      :invoice="invoiceData.invoice"
                      :items="invoiceData.items"
                      :total-hours="invoiceData.totalHours"
                    />
                  </div>
                </div>
              </div>
            </transition>
          </div>
        </div>

        <!-- スケジュールツールチップ -->
        <div
          v-if="tooltip.visible"
          :style="{
            position: 'fixed',
            left: tooltip.x + 'px',
            top: tooltip.y + 'px',
            zIndex: 9999,
            pointerEvents: 'none',
          }"
          class="schedule-tooltip bg-gray-900 text-white text-xs rounded-lg shadow-lg p-3 max-w-xs"
        >
          <div class="space-y-1">
            <div class="font-semibold text-sm">{{ tooltip.title }}</div>
            <div v-if="tooltip.user" class="text-gray-300">
              作成者: {{ tooltip.user }}
            </div>
            <div v-if="tooltip.start" class="text-gray-300">
              開始: {{ formatDateTime(tooltip.start) }}
            </div>
            <div v-if="tooltip.end" class="text-gray-300">
              終了: {{ formatDateTime(tooltip.end) }}
            </div>
            <div v-if="tooltip.participants && tooltip.participants.length > 0" class="text-gray-300">
              参加者: {{ tooltip.participants.map(p => p.name).join(', ') }}
            </div>
            <div v-if="tooltip.description" class="text-gray-300 mt-2 pt-2 border-t border-gray-700 whitespace-pre-wrap max-h-32 overflow-y-auto">
              {{ tooltip.description }}
            </div>
          </div>
        </div>

        <!-- スケジュール詳細モーダル -->
        <transition name="modal">
          <div
            v-if="showScheduleDetail"
            class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
            @click.self="showScheduleDetail = false"
          >
            <div
              class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
            >
              <!-- ヘッダー -->
              <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                  <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  スケジュール詳細
                </h3>
                <button
                  @click="showScheduleDetail = false"
                  class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
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

              <!-- コンテンツ（スクロール可能） -->
              <div v-if="selectedScheduleDetail" class="overflow-y-auto flex-1 px-6 py-5">
                <div class="space-y-4">
                  <!-- タイトル -->
                  <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg p-4 border border-indigo-100">
                    <label class="block text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-2">
                      タイトル
                    </label>
                    <p class="text-lg font-semibold text-gray-900">
                      {{ selectedScheduleDetail.title }}
                    </p>
                  </div>

                  <!-- 費用科目（便利機能が有効な場合のみ表示） -->
                  <div v-if="showExpenseCategoryInEdit && selectedScheduleDetail.expense_category" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                      費用科目
                    </label>
                    <p class="text-lg font-semibold text-gray-700">
                      {{ selectedScheduleDetail.expense_category }}
                    </p>
                  </div>

                  <!-- 日時情報 -->
                  <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        開始日時
                      </label>
                      <p class="text-sm font-medium text-gray-900">
                        {{ formatDateTime(selectedScheduleDetail.start) }}
                      </p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        終了日時
                      </label>
                      <p class="text-sm font-medium text-gray-900">
                        {{ formatDateTime(selectedScheduleDetail.end) }}
                      </p>
                    </div>
                  </div>

                  <!-- ステータス情報 -->
                  <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        終日
                      </label>
                      <span
                        :class="selectedScheduleDetail.allDay ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                      >
                        {{ selectedScheduleDetail.allDay ? "終日" : "時間指定" }}
                      </span>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        色
                      </label>
                      <div class="flex items-center gap-2">
                        <div
                          class="w-8 h-8 rounded-lg shadow-sm border-2 border-gray-300"
                          :style="{ backgroundColor: selectedScheduleDetail.color }"
                        ></div>
                        <span class="text-sm font-mono text-gray-700">{{
                          selectedScheduleDetail.color
                        }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- 作成者 -->
                  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                      作成者
                    </label>
                    <div class="flex items-center gap-2">
                      <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                      </div>
                      <p class="text-sm font-medium text-gray-900">
                        {{ selectedScheduleDetail.user?.name || "-" }}
                      </p>
                    </div>
                  </div>

                  <!-- 参加者 -->
                  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                      参加者
                    </label>
                    <div
                      v-if="
                        selectedScheduleDetail.participants &&
                        selectedScheduleDetail.participants.length > 0
                      "
                      class="flex flex-wrap gap-2"
                    >
                      <span
                        v-for="participant in selectedScheduleDetail.participants"
                        :key="participant.id"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-100 text-indigo-800 rounded-full text-xs font-medium"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        {{ participant.name }}
                      </span>
                    </div>
                    <p v-else class="text-sm text-gray-500 italic">参加者なし</p>
                  </div>

                  <!-- 説明 -->
                  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                      説明
                    </label>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">
                      {{ selectedScheduleDetail.description || "-" }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- フッター（ボタン） -->
              <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 flex justify-end gap-3">
                <button
                  @click="showScheduleDetail = false"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  閉じる
                </button>
                <Link
                  :href="route('admin.schedules.show')"
                  class="px-4 py-2 text-sm font-medium text-white bg-gray-600 rounded-lg hover:bg-gray-700 transition-colors"
                >
                  詳細管理へ
                </Link>
                <button
                  v-if="canEditSchedule(selectedScheduleDetail)"
                  @click="deleteScheduleFromDashboard"
                  class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                >
                  削除
                </button>
                <button
                  v-if="canEditSchedule(selectedScheduleDetail)"
                  @click="startEditSchedule"
                  class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors"
                >
                  編集
                </button>
              </div>
            </div>
          </div>
        </transition>

        <!-- スケジュール作成モーダル -->
        <transition name="modal">
          <div
            v-if="showCreateModal"
            class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
            @click.self="showCreateModal = false"
          >
            <div
              class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
            >
              <!-- ヘッダー -->
              <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                  <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  新規スケジュール作成
                </h3>
                <button
                  @click="showCreateModal = false"
                  class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                >
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <!-- コンテンツ（スクロール可能） -->
              <form @submit.prevent="createScheduleFromDashboard" class="overflow-y-auto flex-1 px-6 py-5">
                <div class="space-y-4">
                  <!-- タイトル -->
                  <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg p-4 border border-indigo-100">
                    <label class="block text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-2">
                      タイトル <span class="text-red-500">*</span>
                    </label>
                    <input
                      v-model="createScheduleForm.title"
                      type="text"
                      required
                      class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      placeholder="スケジュールのタイトルを入力"
                    />
                  </div>

                  <!-- 費用科目（便利機能が有効な場合のみ表示） -->
                  <div v-if="showExpenseCategoryInCreate" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                      費用科目
                    </label>
                    <select
                      v-model="createScheduleForm.expense_category"
                      class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                      <option value="">選択してください</option>
                      <option v-for="category in expenseCategories" :key="category" :value="category">
                        {{ category }}
                      </option>
                    </select>
                  </div>

                  <!-- 日時情報 -->
                  <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        開始日時 <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model="createScheduleForm.start_at"
                        type="datetime-local"
                        required
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      />
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        終了日時 <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model="createScheduleForm.end_at"
                        type="datetime-local"
                        required
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      />
                    </div>
                  </div>

                  <!-- ステータス情報 -->
                  <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        終日
                      </label>
                      <label class="flex items-center cursor-pointer">
                        <input
                          v-model="createScheduleForm.all_day"
                          type="checkbox"
                          class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="ml-2 text-sm text-gray-700">終日予定として設定</span>
                      </label>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        色
                      </label>
                      <div class="flex items-center gap-3">
                        <input
                          v-model="createScheduleForm.color"
                          type="color"
                          class="w-12 h-12 rounded-lg border-2 border-gray-300 shadow-sm cursor-pointer"
                        />
                        <span class="text-sm font-mono text-gray-700">{{ createScheduleForm.color }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- 参加者 -->
                  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                      参加者
                    </label>
                    
                    <!-- 店舗選択（参加者追加用） -->
                    <div class="mb-3">
                      <label class="block text-sm font-medium text-gray-700 mb-2">店舗を選択して参加者を追加</label>
                      <select
                        v-model="selectedShopIdForCreate"
                        @change="loadShopUsersForCreate(selectedShopIdForCreate)"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      >
                        <option value="">店舗を選択してください</option>
                        <option
                          v-for="shop in userShops"
                          :key="shop.id"
                          :value="shop.id"
                        >
                          {{ shop.name }}
                        </option>
                      </select>
                    </div>

                    <!-- 参加者追加済み一覧 -->
                    <div v-if="addedParticipantsForCreate.length > 0" class="mb-3">
                      <label class="block text-sm font-medium text-gray-700 mb-2">参加者追加済み</label>
                      <div class="flex flex-wrap gap-2">
                        <span
                          v-for="participant in addedParticipantsForCreate"
                          :key="participant.id"
                          class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-100 text-indigo-800 rounded-full text-xs font-medium"
                        >
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                          </svg>
                          {{ participant.name }}
                          <button
                            type="button"
                            @click="removeParticipantForCreate(participant.id)"
                            class="ml-1 text-indigo-600 hover:text-indigo-800 font-bold"
                          >
                            ×
                          </button>
                        </span>
                      </div>
                    </div>

                    <!-- 店舗ユーザー一覧（チェックボックス） -->
                    <div v-if="shopUsersForCreate.length > 0" class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-3 bg-white">
                      <label
                        v-for="user in shopUsersForCreate"
                        :key="user.id"
                        class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded-lg transition-colors"
                      >
                        <input
                          type="checkbox"
                          :value="user.id"
                          :checked="isParticipantAddedForCreate(user.id)"
                          @change="toggleParticipantForCreate(user.id, $event.target.checked)"
                          class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="text-sm text-gray-900">{{ user.name }}</span>
                      </label>
                    </div>
                    <p v-else-if="!selectedShopIdForCreate" class="text-sm text-gray-500 italic">店舗を選択すると、その店舗に所属するユーザーが表示されます</p>
                  </div>

                  <!-- 説明 -->
                  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                      説明
                    </label>
                    <textarea
                      v-model="createScheduleForm.description"
                      rows="4"
                      class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      placeholder="スケジュールの説明を入力（任意）"
                    ></textarea>
                  </div>

                  <!-- 便利機能 -->
                  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                      便利機能
                    </label>
                    <div class="space-y-2">
                      <label class="flex items-center cursor-pointer">
                        <input
                          v-model="enableExpenseCategoryFeature"
                          type="checkbox"
                          @change="onExpenseCategoryFeatureChange"
                          class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="ml-2 text-sm text-gray-700">費用科目</span>
                      </label>
                    </div>
                  </div>
                </div>
              </form>

              <!-- フッター（ボタン） -->
              <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 flex justify-end gap-3">
                <button
                  type="button"
                  @click="showCreateModal = false"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  キャンセル
                </button>
                <button
                  type="submit"
                  :disabled="createScheduleForm.processing"
                  @click="createScheduleFromDashboard"
                  class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:bg-gray-400 transition-colors"
                >
                  {{ createScheduleForm.processing ? '作成中...' : '作成' }}
                </button>
              </div>
            </div>
          </div>
        </transition>

        <!-- スケジュール編集モーダル -->
        <transition name="modal">
          <div
            v-if="showEditModal"
            class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
            @click.self="showEditModal = false"
          >
            <div
              class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
            >
              <!-- ヘッダー -->
              <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                  <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  スケジュール編集
                </h3>
                <button
                  @click="showEditModal = false"
                  class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
                >
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <!-- コンテンツ（スクロール可能） -->
              <form @submit.prevent="updateScheduleFromDashboard" class="overflow-y-auto flex-1 px-6 py-5">
                <div class="space-y-4">
                  <!-- タイトル -->
                  <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg p-4 border border-indigo-100">
                    <label class="block text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-2">
                      タイトル <span class="text-red-500">*</span>
                    </label>
                    <input
                      v-model="editScheduleForm.title"
                      type="text"
                      required
                      class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      placeholder="スケジュールのタイトルを入力"
                    />
                  </div>

                  <!-- 費用科目（便利機能が有効な場合のみ表示） -->
                  <div v-if="showExpenseCategoryInEdit" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                      費用科目
                    </label>
                    <select
                      v-model="editScheduleForm.expense_category"
                      class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                      <option value="">選択してください</option>
                      <option v-for="category in expenseCategories" :key="category" :value="category">
                        {{ category }}
                      </option>
                    </select>
                  </div>

                  <!-- 日時情報 -->
                  <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        開始日時 <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model="editScheduleForm.start_at"
                        type="datetime-local"
                        required
                        @change="onEditStartAtChange"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      />
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        終了日時 <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model="editScheduleForm.end_at"
                        type="datetime-local"
                        required
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      />
                    </div>
                  </div>

                  <!-- ステータス情報 -->
                  <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        終日
                      </label>
                      <label class="flex items-center cursor-pointer">
                        <input
                          v-model="editScheduleForm.all_day"
                          type="checkbox"
                          class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="ml-2 text-sm text-gray-700">終日予定として設定</span>
                      </label>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        色
                      </label>
                      <div class="flex items-center gap-3">
                        <input
                          v-model="editScheduleForm.color"
                          type="color"
                          class="w-12 h-12 rounded-lg border-2 border-gray-300 shadow-sm cursor-pointer"
                        />
                        <span class="text-sm font-mono text-gray-700">{{ editScheduleForm.color }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- 参加者 -->
                  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                      参加者
                    </label>
                    
                    <!-- 店舗選択（参加者追加用） -->
                    <div class="mb-3">
                      <label class="block text-sm font-medium text-gray-700 mb-2">店舗を選択して参加者を追加</label>
                      <select
                        v-model="selectedShopIdForEdit"
                        @change="loadShopUsersForEdit(selectedShopIdForEdit)"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      >
                        <option value="">店舗を選択してください</option>
                        <option
                          v-for="shop in userShops"
                          :key="shop.id"
                          :value="shop.id"
                        >
                          {{ shop.name }}
                        </option>
                      </select>
                    </div>

                    <!-- 参加者追加済み一覧 -->
                    <div v-if="addedParticipantsForEdit.length > 0" class="mb-3">
                      <label class="block text-sm font-medium text-gray-700 mb-2">参加者追加済み</label>
                      <div class="flex flex-wrap gap-2">
                        <span
                          v-for="participant in addedParticipantsForEdit"
                          :key="participant.id"
                          class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-100 text-indigo-800 rounded-full text-xs font-medium"
                        >
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                          </svg>
                          {{ participant.name }}
                          <button
                            type="button"
                            @click="removeParticipantForEdit(participant.id)"
                            class="ml-1 text-indigo-600 hover:text-indigo-800 font-bold"
                          >
                            ×
                          </button>
                        </span>
                      </div>
                    </div>

                    <!-- 店舗ユーザー一覧（チェックボックス） -->
                    <div v-if="shopUsersForEdit.length > 0" class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-3 bg-white">
                      <label
                        v-for="user in shopUsersForEdit"
                        :key="user.id"
                        class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded-lg transition-colors"
                      >
                        <input
                          type="checkbox"
                          :value="user.id"
                          :checked="isParticipantAddedForEdit(user.id)"
                          @change="toggleParticipantForEdit(user.id, $event.target.checked)"
                          class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="text-sm text-gray-900">{{ user.name }}</span>
                      </label>
                    </div>
                    <p v-else-if="!selectedShopIdForEdit" class="text-sm text-gray-500 italic">店舗を選択すると、その店舗に所属するユーザーが表示されます</p>
                  </div>

                  <!-- 説明 -->
                  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                      説明
                    </label>
                    <textarea
                      v-model="editScheduleForm.description"
                      rows="4"
                      class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      placeholder="スケジュールの説明を入力（任意）"
                    ></textarea>
                  </div>
                </div>
              </form>

              <!-- フッター（ボタン） -->
              <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 flex justify-end gap-3">
                <button
                  type="button"
                  @click="showEditModal = false"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  キャンセル
                </button>
                <button
                  type="button"
                  :disabled="editScheduleForm.processing"
                  @click="updateScheduleFromDashboard"
                  class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:bg-gray-400 transition-colors"
                >
                  {{ editScheduleForm.processing ? '更新中...' : '更新' }}
                </button>
              </div>
            </div>
          </div>
        </transition>

                <!-- フォームタイプ別の統計 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
              フォームタイプ別の予約数
            </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">予約フォーム</p>
                <p class="text-2xl font-bold text-blue-600">
                  {{ formTypeStats?.reservation || 0 }}
                </p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">資料請求フォーム</p>
                <p class="text-2xl font-bold text-green-600">
                  {{ formTypeStats?.document || 0 }}
                </p>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">お問い合わせフォーム</p>
                <p class="text-2xl font-bold text-purple-600">
                  {{ formTypeStats?.contact || 0 }}
                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 期間別の予約トレンド -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold text-gray-800">
                過去7日間の予約トレンド
              </h3>
                            <div class="flex space-x-2">
                                <button
                                    @click="chartType = 'bar'"
                                    :class="[
                                        'px-3 py-1 text-sm rounded-md transition-colors',
                                        chartType === 'bar' 
                                            ? 'bg-indigo-600 text-white' 
                      : 'bg-gray-200 text-gray-700 hover:bg-gray-300',
                                    ]"
                                >
                                    棒グラフ
                                </button>
                                <button
                                    @click="chartType = 'line'"
                                    :class="[
                                        'px-3 py-1 text-sm rounded-md transition-colors',
                                        chartType === 'line' 
                                            ? 'bg-indigo-600 text-white' 
                      : 'bg-gray-200 text-gray-700 hover:bg-gray-300',
                                    ]"
                                >
                                    折れ線グラフ
                                </button>
                            </div>
                        </div>
                        <TrendChart :data="props.trend7Days" :type="chartType" />
                    </div>
                </div>

                <!-- アラート・通知セクション -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- 予約枠が満席に近いイベント -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
              ⚠️ 予約枠が満席に近いイベント
            </h3>
            <div
              v-if="
                props.eventsWithLowCapacity &&
                props.eventsWithLowCapacity.length > 0
              "
              class="space-y-2"
            >
                            <div
                                v-for="item in props.eventsWithLowCapacity"
                                :key="item.event.id"
                                class="bg-white p-3 rounded border border-yellow-300"
                            >
                                <Link
                                    :href="route('admin.events.show', item.event.id)"
                                    class="text-indigo-600 hover:text-indigo-900 font-medium"
                                >
                                    {{ item.event.title }}
                                </Link>
                                <p class="text-sm text-gray-600 mt-1">
                  埋まり率: {{ item.occupancy_rate }}% ({{
                    item.total_reserved
                  }}/{{ item.total_capacity }})
                                </p>
                            </div>
                        </div>
            <p v-else class="text-sm text-gray-500">
              該当するイベントはありません
            </p>
                    </div>

                    <!-- 受付終了間近のイベント -->
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
              📅 受付終了間近のイベント（7日以内）
            </h3>
            <div
              v-if="props.endingSoonEvents && props.endingSoonEvents.length > 0"
              class="space-y-2"
            >
                            <div
                                v-for="event in props.endingSoonEvents"
                                :key="event.id"
                                class="bg-white p-3 rounded border border-orange-300"
                            >
                                <Link
                                    :href="route('admin.events.show', event.id)"
                                    class="text-indigo-600 hover:text-indigo-900 font-medium"
                                >
                                    {{ event.title }}
                                </Link>
                                <p class="text-sm text-gray-600 mt-1">
                                    終了日: {{ formatDate(event.end_at) }}
                                </p>
                            </div>
                        </div>
            <p v-else class="text-sm text-gray-500">
              該当するイベントはありません
            </p>
                    </div>
                </div>

                <!-- 未対応の予約 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold text-gray-800">
                未対応の予約（メモなし）
              </h3>
                            <Link
                                v-if="unhandledReservations && unhandledReservations.length > 0"
                                :href="route('admin.events.index')"
                                class="text-sm text-indigo-600 hover:text-indigo-900"
                            >
                                すべて見る →
                            </Link>
                        </div>
            <div
              v-if="
                props.unhandledReservations &&
                props.unhandledReservations.length > 0
              "
              class="overflow-x-auto"
            >
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                    <th
                      class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                      ID
                    </th>
                    <th
                      class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                      イベント
                    </th>
                    <th
                      class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                      お名前
                    </th>
                    <th
                      class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                      登録日時
                    </th>
                    <th
                      class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                      操作
                    </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                  <tr
                    v-for="reservation in props.unhandledReservations.slice(
                      0,
                      5
                    )"
                    :key="reservation.id"
                  >
                    <td
                      class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                    >
                      {{ reservation.id }}
                                        </td>
                    <td
                      class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                    >
                      {{ reservation.event?.title || "-" }}
                    </td>
                    <td
                      class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                    >
                      {{ reservation.name }}
                    </td>
                    <td
                      class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                    >
                                            {{ formatDateTime(reservation.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link
                                                v-if="reservation.event"
                                                :href="route('admin.reservations.show', reservation.id)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                詳細
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            未対応の予約はありません
                        </div>
                    </div>
                </div>

                <!-- 最近の予約と最近のメモ -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- 最近の予約 -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">最近の予約</h3>
                                <Link
                                    v-if="recentReservations && recentReservations.length > 0"
                                    :href="route('admin.events.index')"
                                    class="text-sm text-indigo-600 hover:text-indigo-900"
                                >
                                    すべて見る →
                                </Link>
                            </div>
              <div
                v-if="
                  props.recentReservations &&
                  props.recentReservations.length > 0
                "
                class="space-y-3"
              >
                                <div
                                    v-for="reservation in props.recentReservations.slice(0, 5)"
                                    :key="reservation.id"
                                    class="border-b border-gray-200 pb-3 last:border-b-0 last:pb-0"
                                >
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <Link
                                                v-if="reservation.event"
                                                :href="route('admin.reservations.show', reservation.id)"
                                                class="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                                            >
                                                {{ reservation.event.title }}
                                            </Link>
                      <p class="text-sm text-gray-900 mt-1">
                        {{ reservation.name }}
                      </p>
                      <p class="text-xs text-gray-500 mt-1">
                        {{ formatDateTime(reservation.created_at) }}
                      </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                予約データがありません
                            </div>
                        </div>
                    </div>

                    <!-- 最近のメモ -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">
                最近のメモ
              </h3>
              <div
                v-if="props.recentNotes && props.recentNotes.length > 0"
                class="space-y-3"
              >
                                <div
                                    v-for="note in props.recentNotes.slice(0, 5)"
                                    :key="note.id"
                                    class="border-b border-gray-200 pb-3 last:border-b-0 last:pb-0"
                                >
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                      <p class="text-sm font-medium text-gray-900">
                        {{ note.user?.name || "不明" }}
                      </p>
                      <p class="text-xs text-gray-500 mt-1">
                        {{ formatDateTime(note.created_at) }}
                      </p>
                      <p class="text-sm text-gray-700 mt-2 line-clamp-2">
                        {{ note.content }}
                      </p>
                                            <Link
                                                v-if="note.reservation"
                        :href="
                          route('admin.reservations.show', note.reservation.id)
                        "
                                                class="text-xs text-indigo-600 hover:text-indigo-900 mt-1 inline-block"
                                            >
                                                予約詳細へ →
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                メモがありません
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 今週・来週の予約 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- 今週の予約 -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">
                今週の予約
              </h3>
              <div
                v-if="
                  props.thisWeekReservations &&
                  props.thisWeekReservations.length > 0
                "
                class="space-y-2"
              >
                                <div
                                    v-for="reservation in props.thisWeekReservations.slice(0, 5)"
                                    :key="reservation.id"
                                    class="border-b border-gray-200 pb-2 last:border-b-0 last:pb-0"
                                >
                                    <Link
                                        :href="route('admin.reservations.show', reservation.id)"
                                        class="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                                    >
                                        {{ reservation.name }}
                                    </Link>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ formatDateTime(reservation.reservation_datetime) }}
                                    </p>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                今週の予約はありません
                            </div>
                        </div>
                    </div>

                    <!-- 来週の予約 -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">
                来週の予約
              </h3>
              <div
                v-if="
                  props.nextWeekReservations &&
                  props.nextWeekReservations.length > 0
                "
                class="space-y-2"
              >
                                <div
                                    v-for="reservation in props.nextWeekReservations.slice(0, 5)"
                                    :key="reservation.id"
                                    class="border-b border-gray-200 pb-2 last:border-b-0 last:pb-0"
                                >
                                    <Link
                                        :href="route('admin.reservations.show', reservation.id)"
                                        class="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                                    >
                                        {{ reservation.name }}
                                    </Link>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ formatDateTime(reservation.reservation_datetime) }}
                                    </p>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                来週の予約はありません
                            </div>
                        </div>
                    </div>
                </div>

                <!-- フォームタイプ別の詳細統計 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
              フォームタイプ別の詳細統計
            </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- 予約フォーム -->
                            <div>
                                <h4 class="font-medium text-gray-700 mb-3">予約フォーム</h4>
                <p class="text-2xl font-bold text-blue-600 mb-3">
                  {{ props.formTypeDetails?.reservation?.total || 0 }}
                </p>
                <div
                  v-if="
                    props.formTypeDetails?.reservation?.by_venue &&
                    props.formTypeDetails.reservation.by_venue.length > 0
                  "
                >
                                    <p class="text-sm font-medium text-gray-600 mb-2">会場別:</p>
                                    <div class="space-y-1">
                                        <div
                                            v-for="item in props.formTypeDetails.reservation.by_venue"
                                            :key="item.venue_name"
                                            class="text-sm text-gray-600"
                                        >
                                            {{ item.venue_name }}: {{ item.count }}件
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 資料請求フォーム -->
                            <div>
                                <h4 class="font-medium text-gray-700 mb-3">資料請求フォーム</h4>
                <p class="text-2xl font-bold text-green-600 mb-3">
                  {{ props.formTypeDetails?.document?.total || 0 }}
                </p>
                <div
                  v-if="
                    props.formTypeDetails?.document?.by_method &&
                    props.formTypeDetails.document.by_method.length > 0
                  "
                >
                  <p class="text-sm font-medium text-gray-600 mb-2">
                    請求方法別:
                  </p>
                                    <div class="space-y-1">
                                        <div
                                            v-for="item in props.formTypeDetails.document.by_method"
                                            :key="item.method"
                                            class="text-sm text-gray-600"
                                        >
                                            {{ item.method }}: {{ item.count }}件
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- お問い合わせフォーム -->
                            <div>
                <h4 class="font-medium text-gray-700 mb-3">
                  お問い合わせフォーム
                </h4>
                <p class="text-2xl font-bold text-purple-600 mb-3">
                  {{ props.formTypeDetails?.contact?.total || 0 }}
                </p>
                <div
                  v-if="
                    props.formTypeDetails?.contact?.by_response_method &&
                    props.formTypeDetails.contact.by_response_method.length > 0
                  "
                >
                  <p class="text-sm font-medium text-gray-600 mb-2">
                    回答方法別:
                  </p>
                                    <div class="space-y-1">
                                        <div
                      v-for="item in props.formTypeDetails.contact
                        .by_response_method"
                                            :key="item.method"
                                            class="text-sm text-gray-600"
                                        >
                                            {{ item.method }}: {{ item.count }}件
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 店舗・スタッフ別の統計 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- 店舗別の予約数 -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">
                店舗別の予約数（上位10店舗）
              </h3>
              <div
                v-if="props.shopStats && props.shopStats.length > 0"
                class="space-y-2"
              >
                                <div
                                    v-for="item in props.shopStats"
                                    :key="item.shop.id"
                                    class="flex justify-between items-center border-b border-gray-200 pb-2 last:border-b-0 last:pb-0"
                                >
                                    <Link
                                        :href="route('admin.shops.edit', item.shop.id)"
                                        class="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                                    >
                                        {{ item.shop.name }}
                                    </Link>
                  <span class="text-sm text-gray-600"
                    >{{ item.reservation_count }}件</span
                  >
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                データがありません
                            </div>
                        </div>
                    </div>

                    <!-- スタッフ別のメモ数 -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">
                スタッフ別のメモ数（上位10名）
              </h3>
              <div
                v-if="props.staffStats && props.staffStats.length > 0"
                class="space-y-2"
              >
                                <div
                                    v-for="item in props.staffStats"
                                    :key="item.user.id"
                                    class="flex justify-between items-center border-b border-gray-200 pb-2 last:border-b-0 last:pb-0"
                                >
                  <span class="text-sm font-medium text-gray-900">{{
                    item.user.name
                  }}</span>
                  <span class="text-sm text-gray-600"
                    >{{ item.note_count }}件</span
                  >
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                データがありません
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted, nextTick, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ActionButton from "@/Components/ActionButton.vue";
import { Head, Link } from "@inertiajs/vue3";
import StatCard from "../Components/StatCard.vue";
import TrendChart from "../Components/TrendChart.vue";
import FullCalendar from "@fullcalendar/vue3";
import { Bar } from "vue-chartjs";
import Invoice from "@/Components/Invoice.vue";
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
} from "chart.js";

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";
import jaLocale from "@fullcalendar/core/locales/ja";
import axios from "axios";

const props = defineProps({
    stats: Object,
    formTypeStats: Object,
    occupancyRate: Number,
    trend7Days: Array,
    trend30Days: Array,
    recentReservations: Array,
    recentNotes: Array,
    recentEvents: Array,
    eventsWithLowCapacity: Array,
    endingSoonEvents: Array,
    unhandledReservations: Array,
    formTypeDetails: Object,
    shopStats: Array,
    staffStats: Array,
    thisWeekReservations: Array,
    nextWeekReservations: Array,
  shops: Array,
  userShops: Array,
  users: Array,
  currentUser: Object,
});

const statCards = computed(() => [
    {
        key: "customers_today",
        title: "本日登録された顧客数",
        value: props.stats.customers_today || 0,
        icon: "users",
        color: "blue",
        link: route("admin.customers.index"),
    },
]);

const chartType = ref("bar");
const shopCalendar = ref(null);
const userCalendar = ref(null);
const selectedShopId = ref("");
const selectedUserId = ref("");
const selectedUserShopId = ref(""); // ユーザー単位カレンダー用の店舗選択
const usersForUserCalendar = ref([]); // ユーザー単位カレンダー用のユーザーリスト
const usersForShopCalendar = ref([]); // 店舗単位カレンダー用のユーザーリスト
const selectedUserIdsForShopCalendar = ref([]); // 店舗単位カレンダーで選択されたユーザーIDのリスト

// ユーザー単位カレンダー用の店舗変更時の処理
async function onUserShopChange() {
  if (!selectedUserShopId.value) {
    usersForUserCalendar.value = [];
    selectedUserId.value = "";
    return;
  }
  
  try {
    const response = await axios.get(route('admin.schedules.shop-users'), {
      params: { shop_id: selectedUserShopId.value }
    });
    usersForUserCalendar.value = response.data;
    
    // 現在選択中のユーザーがリストにいない場合はクリア
    if (selectedUserId.value && !response.data.some(u => u.id === selectedUserId.value)) {
      selectedUserId.value = "";
    }
  } catch (error) {
    console.error('店舗ユーザーの取得に失敗しました:', error);
    usersForUserCalendar.value = [];
  }
}

// フィルタリングされたユーザーリスト
const filteredUsersForUserCalendar = computed(() => {
  return usersForUserCalendar.value;
});

// ユーザー単位カレンダーの表示日付（デフォルトは今日）
const getTodayDateString = () => {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const date = String(today.getDate()).padStart(2, '0');
  return `${year}-${month}-${date}`;
};
const selectedUserDate = ref(getTodayDateString());

// 勤怠データ出力
const showAttendanceExport = ref(false);
const showInvoiceModal = ref(false);
// 時間単価をlocalStorageから読み込む（デフォルト5000円）
const savedHourlyRate = localStorage.getItem('hourlyRate');
const hourlyRate = ref(savedHourlyRate ? Number(savedHourlyRate) : 5000);
const invoiceData = ref(null);

// 時間単価が変更されたらlocalStorageに保存
watch(hourlyRate, (newValue) => {
  if (newValue !== null && newValue !== undefined) {
    localStorage.setItem('hourlyRate', newValue.toString());
  }
}, { immediate: false });

// デフォルト日付を計算（20日締め）
const getDefaultAttendanceDates = () => {
  const today = new Date();
  const currentDay = today.getDate();
  const currentYear = today.getFullYear();
  const currentMonth = today.getMonth();
  
  let startDate, endDate;
  
  if (currentDay >= 21) {
    // 21日以降：今月21日〜来月20日
    startDate = new Date(currentYear, currentMonth, 21);
    endDate = new Date(currentYear, currentMonth + 1, 20);
  } else {
    // 20日以前：前月21日〜今月20日
    startDate = new Date(currentYear, currentMonth - 1, 21);
    endDate = new Date(currentYear, currentMonth, 20);
  }
  
  const formatDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  
  return {
    start: formatDate(startDate),
    end: formatDate(endDate),
  };
};

const defaultDates = getDefaultAttendanceDates();
const attendanceStartDate = ref(defaultDates.start);
const attendanceEndDate = ref(defaultDates.end);
const exportingAttendance = ref(false);
const attendanceData = ref([]);

// 費用項目の選択肢
const expenseCategories = [
  'システム設計・要件定義費',
  'システム開発・実装費',
  'インフラ構築・外部サービス連携費',
  '運用支援・マニュアル作成費',
  'データ移行・PC設定・ITサポート費',
  '調査・打合せ・進行管理費',
  'デザイン制作費',
  '外注先折衝・ディレクション費',
  'そのほか雑費',
];

// タスク名から費用項目を推測するマッピング
const taskToExpenseMapping = {
  'システム設計・要件定義費': [
    '業務引き継ぎ', '要件定義', '現場ヒアリング', '設計', '仕様検討', '業務整理', '計画策定'
  ],
  'システム開発・実装費': [
    'プログラム実装', '機能追加', 'UI/UX実装', 'フォーム作成', '管理画面開発', 'データベース設計・追加'
  ],
  'インフラ構築・外部サービス連携費': [
    '本番環境構築', 'サーバー設定', 'DNS設定', 'Amazon SES', 'LINE API', '外部API連携', '環境構築'
  ],
  '運用支援・マニュアル作成費': [
    '運用マニュアル作成', '会議資料作成', '稟議書作成', '社内説明資料作成'
  ],
  'データ移行・PC設定・ITサポート費': [
    'PCデータ移行', 'PC初期設定', '故障PC対応', 'バックアップ', 'データ削除', '端末対応'
  ],
  '調査・打合せ・進行管理費': [
    '打合せ', '調査', '比較検討', '市場調査', '外部業者調整', 'タスク整理', '進行管理'
  ],
  'デザイン制作費': [
    'バナー作成', 'チラシ作成', 'LPデザイン', 'UIデザイン', '印刷物制作'
  ],
  '外注先折衝・ディレクション費': [
    '外注管理', '制作進行', '修正依頼', 'クオリティ管理'
  ],
};

// タスク名から費用項目を推測する関数（APIから取得）
async function predictExpenseCategory(taskTitle) {
  if (!taskTitle) return null;
  
  try {
    const response = await axios.post(route('admin.schedules.predict-expense-category'), {
      task_title: taskTitle,
    });
    
    if (response.data.success && response.data.expense_category) {
      return response.data.expense_category;
    }
  } catch (error) {
    console.error('費用項目の推測に失敗しました:', error);
    // エラー時は固定マッピングにフォールバック
    return predictExpenseCategoryFallback(taskTitle);
  }
  
  // APIから結果が得られない場合、固定マッピングにフォールバック
  return predictExpenseCategoryFallback(taskTitle);
}

// 固定マッピングによる推測（フォールバック用）
function predictExpenseCategoryFallback(taskTitle) {
  if (!taskTitle) return null;
  
  for (const [category, keywords] of Object.entries(taskToExpenseMapping)) {
    for (const keyword of keywords) {
      if (taskTitle.includes(keyword)) {
        return category;
      }
    }
  }
  
  return null;
}

// 勤怠データの合計時間
const attendanceTotalHours = computed(() => {
  return attendanceData.value.reduce((sum, row) => sum + parseFloat(row.totalHours), 0).toFixed(1);
});

// ローカルで費用項目を更新（DBには保存しない）
function updateExpenseCategoryLocal(scheduleId, category) {
  const item = attendanceData.value.find(item => item.id === scheduleId);
  if (item) {
    item.expenseCategory = category || null;
    // 推測状態を更新（手動で変更した場合は推測ではない）
    if (category) {
      const predictedCategory = predictExpenseCategory(item.title);
      // DBに保存されていない、かつ推測値と一致する場合のみ推測として扱う
      item.isPredicted = !item.dbExpenseCategory && category === predictedCategory;
    } else {
      item.isPredicted = false;
    }
  }
}

// 費用項目をDBに保存する関数
async function updateExpenseCategory(scheduleId, category) {
  try {
    await axios.patch(route('admin.schedules.update-expense-category', scheduleId), {
      expense_category: category,
    });
    
    // ローカルデータを更新
    const item = attendanceData.value.find(item => item.id === scheduleId);
    if (item) {
      item.expenseCategory = category;
      item.dbExpenseCategory = category;
      item.isPredicted = false;
    }
  } catch (error) {
    console.error('費用項目の更新に失敗しました:', error);
    alert('費用項目の更新に失敗しました。');
    throw error;
  }
}

// 未保存の変更があるかチェック
const hasUnsavedChanges = computed(() => {
  return attendanceData.value.some(item => {
    // 推測された項目がある、または手動で変更された項目がある
    return item.isPredicted || (item.expenseCategory && !item.dbExpenseCategory);
  });
});

// すべての費用項目を一括保存
async function saveExpenseCategories() {
  if (attendanceData.value.length === 0) {
    alert('保存するデータがありません。');
    return;
  }
  
  // 保存が必要な項目をフィルタリング
  const itemsToSave = attendanceData.value.filter(item => {
    return item.expenseCategory && item.expenseCategory !== item.dbExpenseCategory;
  });
  
  if (itemsToSave.length === 0) {
    alert('保存する変更がありません。');
    return;
  }
  
  if (!confirm(`${itemsToSave.length}件の費用項目を保存しますか？`)) {
    return;
  }
  
  exportingAttendance.value = true;
  
  try {
    // 並列で保存
    const promises = itemsToSave.map(item => 
      updateExpenseCategory(item.id, item.expenseCategory)
    );
    
    await Promise.all(promises);
    
    alert(`${itemsToSave.length}件の費用項目を保存しました。`);
  } catch (error) {
    console.error('費用項目の保存に失敗しました:', error);
    alert('一部の費用項目の保存に失敗しました。');
  } finally {
    exportingAttendance.value = false;
  }
}

// Chart.jsのグラフデータ（日付ごとに集計）
const attendanceChartData = computed(() => {
  // データがない場合は空のグラフを返す
  if (!attendanceData.value || attendanceData.value.length === 0) {
    return {
      labels: [],
      datasets: [
        {
          label: '勤務時間 (h)',
          data: [],
          backgroundColor: 'rgba(99, 102, 241, 0.7)',
          borderColor: 'rgb(99, 102, 241)',
          borderWidth: 1,
          borderRadius: 4,
        },
      ],
    };
  }
  
  // 日付ごとにグループ化
  const groupedByDate = {};
  attendanceData.value.forEach(row => {
    if (!row.date) return;
    if (!groupedByDate[row.date]) {
      groupedByDate[row.date] = 0;
    }
    groupedByDate[row.date] += parseFloat(row.totalHours || 0);
  });
  
  const dates = Object.keys(groupedByDate).sort();
  
  // データがない場合は空のグラフを返す
  if (dates.length === 0) {
    return {
      labels: [],
      datasets: [
        {
          label: '勤務時間 (h)',
          data: [],
          backgroundColor: 'rgba(99, 102, 241, 0.7)',
          borderColor: 'rgb(99, 102, 241)',
          borderWidth: 1,
          borderRadius: 4,
        },
      ],
    };
  }
  
  return {
    labels: dates.map(date => date.slice(5)), // MM-DD形式
    datasets: [
      {
        label: '勤務時間 (h)',
        data: dates.map(date => groupedByDate[date]),
        backgroundColor: 'rgba(99, 102, 241, 0.7)',
        borderColor: 'rgb(99, 102, 241)',
        borderWidth: 1,
        borderRadius: 4,
      },
    ],
  };
});

// Chart.jsのオプション
const attendanceChartOptions = computed(() => {
  // 日付ごとにグループ化（ツールチップ用）
  const groupedByDate = {};
  if (attendanceData.value && attendanceData.value.length > 0) {
    attendanceData.value.forEach(row => {
      if (!row.date) return;
      if (!groupedByDate[row.date]) {
        groupedByDate[row.date] = {
          total: 0,
          items: [],
        };
      }
      groupedByDate[row.date].total += parseFloat(row.totalHours || 0);
      groupedByDate[row.date].items.push(row);
    });
  }
  const dates = Object.keys(groupedByDate).sort();
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false,
      },
      tooltip: {
        enabled: true,
        callbacks: {
          title: (context) => {
            const index = context[0].dataIndex;
            if (dates[index]) {
              return dates[index];
            }
            return '';
          },
          label: (context) => {
            const index = context.dataIndex;
            if (dates[index] && groupedByDate[dates[index]]) {
              const totalHours = groupedByDate[dates[index]].total || 0;
              return `勤務時間: ${totalHours.toFixed(1)}h`;
            }
            return '';
          },
        },
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        title: {
          display: true,
          text: '時間 (h)',
        },
        ticks: {
          stepSize: 1,
        },
      },
      x: {
        title: {
          display: true,
          text: '日付',
        },
      },
    },
  };
});
const shops = computed(() => props.shops || []);
const userShops = computed(() => props.userShops || []);
const users = computed(() => props.users || []);

// リサイズイベントハンドラー
let resizeTimeout = null;
function handleResize() {
  // デバウンス処理（リサイズが完了してから実行）
  if (resizeTimeout) {
    clearTimeout(resizeTimeout);
  }
  resizeTimeout = setTimeout(() => {
    syncCalendarHeights();
  }, 150);
}

// 勤怠データを読み込む
async function loadAttendanceData() {
  if (!attendanceStartDate.value || !attendanceEndDate.value) {
    alert('集計開始日と集計終了日を選択してください。');
    return;
  }
  
  exportingAttendance.value = true;
  
  try {
    // 終了日を+1日して、その日まで含めるようにする
    const endDate = new Date(attendanceEndDate.value);
    endDate.setDate(endDate.getDate() + 1);
    const endDateStr = `${endDate.getFullYear()}-${String(endDate.getMonth() + 1).padStart(2, '0')}-${String(endDate.getDate()).padStart(2, '0')}`;
    
    const response = await axios.get(route('admin.schedules.index'), {
      params: {
        start: attendanceStartDate.value,
        end: endDateStr,
        mode: 'user',
        user_id: props.currentUser.id,
      }
    });
    
    // 日付ごとにグループ化（終日予定は除外）
    const groupedByDate = {};
    response.data.forEach(schedule => {
      // 終日予定は除外
      if (schedule.allDay) return;
      
      const startDate = new Date(schedule.start);
      const dateKey = `${startDate.getFullYear()}-${String(startDate.getMonth() + 1).padStart(2, '0')}-${String(startDate.getDate()).padStart(2, '0')}`;
      
      if (!groupedByDate[dateKey]) {
        groupedByDate[dateKey] = [];
      }
      groupedByDate[dateKey].push(schedule);
    });
    
    // データを生成（各スケジュールごとに）
    const data = [];
    
    Object.keys(groupedByDate).sort().forEach(date => {
      const schedules = groupedByDate[date];
      
      schedules.forEach(s => {
        const start = new Date(s.start);
        const end = new Date(s.end);
        
        // 時間範囲をフォーマット
        const startTime = `${String(start.getHours()).padStart(2, '0')}:${String(start.getMinutes()).padStart(2, '0')}`;
        const endTime = `${String(end.getHours()).padStart(2, '0')}:${String(end.getMinutes()).padStart(2, '0')}`;
        
        // 分数を計算
        const minutes = (end - start) / (1000 * 60);
        
        const title = s.title || '';
        const dbExpenseCategory = s.expense_category || null;
        // 推測は非同期なので、後で処理する
        data.push({
          id: s.id,
          date,
          timeRange: `${startTime}-${endTime}`,
          totalHours: (minutes / 60).toFixed(1),
          title: title,
          expenseCategory: dbExpenseCategory, // まずDBの値を設定
          isPredicted: false,
          dbExpenseCategory: dbExpenseCategory,
          _pendingPrediction: !dbExpenseCategory, // 推測待ちフラグ（DBに値がない場合のみ）
        });
      });
    });
    
    attendanceData.value = data;
    
    // 推測待ちの項目に対して非同期で推測を実行
    const predictionPromises = data
      .filter(item => item._pendingPrediction)
      .map(async (item) => {
        try {
          const predictedCategory = await predictExpenseCategory(item.title);
          if (predictedCategory) {
            item.expenseCategory = predictedCategory;
            item.isPredicted = true;
          }
          delete item._pendingPrediction;
        } catch (error) {
          console.error('費用項目の推測に失敗しました:', error);
          delete item._pendingPrediction;
        }
      });
    
    // 推測が完了するまで待機（並列実行）
    if (predictionPromises.length > 0) {
      await Promise.all(predictionPromises);
    }
    
  } catch (error) {
    console.error('勤怠データの取得に失敗しました:', error);
    alert('勤怠データの取得に失敗しました。');
  } finally {
    exportingAttendance.value = false;
  }
}

// 請求書データを生成
function generateInvoiceData() {
  if (attendanceData.value.length === 0) {
    alert('先にデータを表示してください。');
    return null;
  }
  
  // 費用項目ごとにグループ化
  const groupedByCategory = {};
  attendanceData.value.forEach(row => {
    const category = row.expenseCategory || '未分類';
    if (!groupedByCategory[category]) {
      groupedByCategory[category] = {
        items: [],
        totalHours: 0,
      };
    }
    groupedByCategory[category].items.push(row);
    groupedByCategory[category].totalHours += parseFloat(row.totalHours);
  });
  
  // 請求書項目を生成（時間×時間単価で計算）
  const hourlyRateForInvoice = hourlyRate.value || 5000; // localStorageから読み込んだ時間単価を使用
  const items = Object.keys(groupedByCategory).sort().map(category => {
    const group = groupedByCategory[category];
    const amount = Math.round(group.totalHours * hourlyRateForInvoice);
    return {
      name: category,
      amount: amount,
      totalHours: group.totalHours, // 時間情報を追加
    };
  });
  
  // 日付をフォーマット
  const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}年${month}月${day}日`;
  };
  
  // 支払期限を計算（発行日の翌月末日）
  const today = new Date();
  const dueDate = new Date(today.getFullYear(), today.getMonth() + 2, 0);
  const formatDueDate = `${dueDate.getFullYear()}年${String(dueDate.getMonth() + 1).padStart(2, '0')}月${String(dueDate.getDate()).padStart(2, '0')}日`;
  
  // 請求書番号を生成（YYYYMMDD形式）
  const invoiceNumber = `${today.getFullYear()}${String(today.getMonth() + 1).padStart(2, '0')}${String(today.getDate()).padStart(2, '0')}-001`;
  
  return {
    client: {
      name: '	株式会社京呉服平田', // TODO: 実際の請求先情報に置き換え
      postal: '918-8015',
      address: '	福井県福井市花堂南1丁目2-1',
    },
    issuer: {
      name: 'Onsite Solution', // TODO: 実際の事業者情報に置き換え
      postal: '701-0221',
      address1: '岡山県岡山市南区藤田564-4',
      address2: 'リバーサイド藤田B棟102',
      tel: '090-6182-7735',
      person: '村上 飛羽', // TODO: 実際の担当者名に置き換え
    },
    invoice: {
      issueDate: formatDate(today.toISOString().split('T')[0]),
      number: invoiceNumber,
      dueDate: formatDueDate,
      title: `ITサポート業務（${attendanceStartDate.value} ～ ${attendanceEndDate.value}）`,
      bank: '楽天銀行(0036) タイコ支店(242) 普通 2487908 村上 飛羽', // TODO: 実際の振込先情報に置き換え
      remarks: `集計期間: ${attendanceStartDate.value} ～ ${attendanceEndDate.value}\n合計勤務時間: ${attendanceTotalHours.value}時間`,
    },
    items: items,
    totalHours: parseFloat(attendanceTotalHours.value), // 合計時間を追加
  };
}

// 請求書発行
function exportAttendanceCsv() {
  const data = generateInvoiceData();
  if (!data) return;
  
  invoiceData.value = data;
  showInvoiceModal.value = true;
}

// 請求書をPDFとしてダウンロード
function downloadInvoice() {
  if (!invoiceData.value) return;
  
  // HTMLを取得
  const invoiceElement = document.querySelector('.invoice');
  if (!invoiceElement) return;
  
  // スタイルを取得（scopedスタイルの属性を削除）
  const invoiceHTML = invoiceElement.outerHTML.replace(/data-v-[a-z0-9]+/g, '');
  
  // 完全なスタイル定義
  const invoiceStyles = `
    @page {
      size: A4;
      margin: 15mm;
    }
    @media print {
      @page {
        size: A4;
        margin: 15mm;
      }
      body {
        margin: 0;
        padding: 0;
      }
      .invoice {
        margin: 0;
        padding: 10mm 12mm;
        min-height: 267mm;
        page-break-inside: avoid;
        page-break-after: avoid;
        page-break-before: avoid;
      }
    }
    * {
      box-sizing: border-box;
    }
    html, body {
      font-family: "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif;
      margin: 0;
      padding: 0;
      background: white;
      height: auto;
      overflow: visible;
    }
    .invoice {
      font-family: "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif;
      border-top: 4px solid #2e7d32;
      color: #000;
      padding: 10mm 12mm;
      max-width: 210mm;
      min-height: 267mm;
      margin: 0 auto;
      background: white;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      page-break-inside: avoid;
      page-break-after: avoid;
      page-break-before: avoid;
    }
    .header {
      flex-shrink: 0;
    }
    .header h1 {
      text-align: center;
      letter-spacing: 0.3em;
      margin: 6px 0;
      font-size: 20px;
      page-break-inside: avoid;
    }
    .top {
      display: flex;
      justify-content: space-between;
      margin-bottom: 6px;
      flex-shrink: 0;
      page-break-inside: avoid;
    }
    .client {
      width: 55%;
      font-size: 12px;
    }
    .client-name {
      font-weight: bold;
      margin-bottom: 3px;
      font-size: 14px;
    }
    .client p {
      margin: 1px 0;
      font-size: 12px;
    }
    .meta table {
      border-collapse: collapse;
      font-size: 11px;
    }
    .meta th,
    .meta td {
      border: 1px solid #000;
      padding: 3px 6px;
      font-size: 11px;
    }
    .meta th {
      background: #f5f5f5;
      width: 80px;
    }
    .message {
      margin: 6px 0;
      font-size: 12px;
      flex-shrink: 0;
      page-break-inside: avoid;
    }
    .summary {
      width: 50%;
      margin-bottom: 6px;
      font-size: 11px;
      flex-shrink: 0;
      page-break-inside: avoid;
    }
    .summary table {
      width: 100%;
      border-collapse: collapse;
    }
    .summary th,
    .summary td {
      border: 1px solid #000;
      padding: 3px;
      font-size: 11px;
    }
    .summary th {
      background: #f5f5f5;
      width: 80px;
    }
    .issuer {
      text-align: right;
      margin-top: -60px;
      font-size: 11px;
      flex-shrink: 0;
      page-break-inside: avoid;
    }
    .issuer-name {
      font-weight: bold;
      font-size: 14px;
      margin-bottom: 1px;
    }
    .issuer p {
      margin: 0.5px 0;
      font-size: 11px;
    }
    .total-box {
      display: flex;
      justify-content: space-between;
      width: 280px;
      border: 2px solid #000;
      padding: 6px;
      margin: 8px 0;
      font-size: 16px;
      font-weight: bold;
      flex-shrink: 0;
      page-break-inside: avoid;
    }
    .items {
      width: 100%;
      border-collapse: collapse;
      margin-top: 6px;
      font-size: 12px;
      flex: 1;
      min-height: 0;
      page-break-inside: avoid;
    }
    .items th,
    .items td {
      border: 1px solid #000;
      padding: 4px;
      font-size: 12px;
    }
    .items th {
      background: #333;
      color: #fff;
      padding: 5px 4px;
    }
    .items tbody tr {
      height: 24px;
      page-break-inside: avoid;
    }
    .amount {
      text-align: right;
      width: 180px;
    }
    .footer {
      margin-top: 6px;
      font-size: 11px;
      flex-shrink: 0;
      page-break-inside: avoid;
    }
    .footer p {
      margin: 2px 0;
    }
    .remarks {
      margin-top: 8px;
      flex-shrink: 0;
      page-break-inside: avoid;
    }
    .remarks h3 {
      background: #333;
      color: #fff;
      padding: 3px;
      font-size: 12px;
      margin: 0;
    }
    .remarks-box {
      border: 1px dashed #000;
      min-height: 45px;
      padding: 5px;
      white-space: pre-wrap;
      font-size: 11px;
    }
  `;
  
  // 印刷用のウィンドウを開く
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>請求書</title>
      <style>
        ${invoiceStyles}
      </style>
    </head>
    <body>
      ${invoiceHTML}
    </body>
    </html>
  `);
  printWindow.document.close();
  
  // スタイルが適用されるまで待機してから印刷ダイアログを表示
  setTimeout(() => {
    // 印刷設定を調整（単一ページとして保存）
    printWindow.print();
  }, 500);
}

// デフォルト値を設定
onMounted(() => {
  console.log('[Dashboard] onMounted開始');
  console.log('[Dashboard] currentUser:', props.currentUser);
  console.log('[Dashboard] userShops:', userShops.value);
  console.log('[Dashboard] users:', users.value);
  
  // LocalStorageから費用科目機能の設定を読み込む
  loadExpenseCategoryFeatureSetting();
  
  // 店舗単位：mainフラグが立っている店舗を優先的に選択、なければ最初の店舗を選択
  if (userShops.value.length > 0) {
    const mainShop = userShops.value.find(shop => shop.main === true || shop.main === 1);
    const defaultShop = mainShop || userShops.value[0];
    selectedShopId.value = defaultShop.id;
    selectedShopIdForCreate.value = defaultShop.id;
    loadShopUsersForCreate(defaultShop.id);
    // 店舗単位カレンダー用のユーザーリストを取得
    onShopChange();
    console.log('[Dashboard] 店舗ID設定:', selectedShopId.value, 'main:', defaultShop.main);
  }
  
  // ユーザー単位：店舗とログインユーザーを選択
  if (userShops.value.length > 0) {
    const mainShop = userShops.value.find(shop => shop.main === true || shop.main === 1);
    const defaultShop = mainShop || userShops.value[0];
    selectedUserShopId.value = defaultShop.id;
    // 店舗ユーザーを読み込み
    onUserShopChange().then(() => {
      if (props.currentUser) {
        selectedUserId.value = props.currentUser.id;
        console.log('[Dashboard] ユーザーID設定:', selectedUserId.value);
        // ユーザー設定後にカレンダーをリフレッシュ
        setTimeout(() => {
          if (userCalendar.value && selectedUserId.value) {
            console.log('[Dashboard] ユーザー単位カレンダーをリフレッシュ（初期化）');
            userCalendar.value.getApi().refetchEvents();
          }
        }, 100);
      }
    });
  } else if (props.currentUser) {
    selectedUserId.value = props.currentUser.id;
    console.log('[Dashboard] ユーザーID設定:', selectedUserId.value);
  } else {
    console.warn('[Dashboard] currentUserが存在しません');
  }
  
  // リサイズイベントリスナーを追加
  window.addEventListener('resize', handleResize);
  
  // カレンダーを初期読み込み（デフォルト値で絞り込み）
  // 少し待ってから実行（DOMの準備とデフォルト値の設定を待つ）
  setTimeout(() => {
    console.log('[Dashboard] カレンダー初期読み込み開始');
    console.log('[Dashboard] shopCalendar:', shopCalendar.value);
    console.log('[Dashboard] userCalendar:', userCalendar.value);
    console.log('[Dashboard] selectedShopId:', selectedShopId.value);
    console.log('[Dashboard] selectedUserId:', selectedUserId.value);
    
    if (shopCalendar.value && selectedShopId.value) {
      console.log('[Dashboard] 店舗単位カレンダーをリフレッシュ');
      shopCalendar.value.getApi().refetchEvents();
    }
    
    // ユーザー単位カレンダーの現在時刻インジケーターを初期化
    if (userCalendar.value) {
      // カレンダーのレンダリング完了を待つ
      setTimeout(() => {
        updateCurrentTimeIndicator();
        // 30秒ごとに現在時刻のインジケーターを更新
        currentTimeIndicatorInterval = setInterval(() => {
          updateCurrentTimeIndicator();
        }, 30000); // 30000ms = 30秒
      }, 500);
    }
  }, 200);
});

// クリーンアップ
onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
  if (resizeTimeout) {
    clearTimeout(resizeTimeout);
  }
  // 現在時刻インジケーターのインターバルをクリア
  if (currentTimeIndicatorInterval) {
    clearInterval(currentTimeIndicatorInterval);
    currentTimeIndicatorInterval = null;
  }
});

// カレンダーの高さを同期する関数（縦並びレイアウトでは不要だが互換性のため残す）
function syncCalendarHeights() {
  // 縦並びレイアウトでは高さ同期は不要
  // 各カレンダーは固定高さで表示される
}

// 時間をフォーマット
function formatTime(dateStr) {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  return date.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', hour12: false });
}

// イベント表示内容をカスタマイズ（店舗単位用）
function renderShopEventContent(arg) {
  const event = arg.event;
  const isAllDay = event.allDay;
  const startTime = isAllDay ? null : formatTime(event.start);
  const user = event.extendedProps.user;
  
  // コンテナ要素を作成
  const container = document.createElement('div');
  container.className = 'custom-event-content';
  
  // 終日予定と時間指定予定で異なるスタイルを適用
  if (isAllDay) {
    container.className += ' all-day-event';
  } else {
    container.className += ' timed-event';
  }
  
  // 時間表示（時間指定の場合のみ）
  if (!isAllDay && startTime) {
    const timeEl = document.createElement('span');
    timeEl.className = 'event-time';
    timeEl.textContent = startTime;
    container.appendChild(timeEl);
  }
  
  // タイトル表示
  const titleEl = document.createElement('span');
  titleEl.className = 'event-title';
  titleEl.textContent = event.title;
  container.appendChild(titleEl);
  
  // 作成者名を表示
  if (user) {
    const userEl = document.createElement('span');
    userEl.className = 'event-user';
    userEl.textContent = `(${user.name})`;
    container.appendChild(userEl);
  }
  
  return { domNodes: [container] };
}

// イベント表示内容をカスタマイズ（ユーザー単位用）
function renderUserEventContent(arg) {
  const event = arg.event;
  const isAllDay = event.allDay;
  const startTime = isAllDay ? null : formatTime(event.start);
  const user = event.extendedProps.user;
  
  // コンテナ要素を作成
  const container = document.createElement('div');
  container.className = 'custom-event-content';
  
  // 終日予定と時間指定予定で異なるスタイルを適用
  if (isAllDay) {
    container.className += ' all-day-event';
  } else {
    container.className += ' timed-event';
  }
  
  // 時間表示（時間指定の場合のみ）
  if (!isAllDay && startTime) {
    const timeEl = document.createElement('span');
    timeEl.className = 'event-time';
    timeEl.textContent = startTime;
    container.appendChild(timeEl);
  }
  
  // タイトル表示
  const titleEl = document.createElement('span');
  titleEl.className = 'event-title';
  titleEl.textContent = event.title;
  container.appendChild(titleEl);
  
  // 作成者名を表示（ユーザー単位でも表示）
  if (user) {
    const userEl = document.createElement('span');
    userEl.className = 'event-user';
    userEl.textContent = `(${user.name})`;
    container.appendChild(userEl);
  }
  
  return { domNodes: [container] };
}

// イベントホバー時の処理（店舗単位）
function handleEventMouseEnter(mouseEnterInfo) {
  const event = mouseEnterInfo.event;
  const jsEvent = mouseEnterInfo.jsEvent;
  
  tooltip.value = {
    visible: true,
    x: jsEvent.clientX + 10,
    y: jsEvent.clientY + 10,
    title: event.title || '',
    user: event.extendedProps.user?.name || '',
    start: event.startStr || '',
    end: event.endStr || '',
    participants: event.extendedProps.participants || [],
    description: event.extendedProps.description || '',
  };
}

// イベントホバー終了時の処理
function handleEventMouseLeave() {
  tooltip.value.visible = false;
}

// 店舗単位カレンダーオプション
const shopCalendarOptions = ref({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  initialView: "dayGridMonth",
  locale: jaLocale,
  headerToolbar: {
    left: "prev,next today",
    center: "title",
    right: "dayGridMonth,timeGridWeek,timeGridDay",
  },
  height: "auto",
  editable: true,
  selectable: true,
  selectMirror: true,
  dayMaxEvents: true,
  weekends: true,
  select: handleDateSelect,
  eventClick: handleEventClick,
  eventDrop: handleEventDrop,
  eventResize: handleEventResize,
  eventMouseEnter: handleEventMouseEnter,
  eventMouseLeave: handleEventMouseLeave,
  events: loadShopSchedules,
  eventContent: renderShopEventContent,
});

// 今日の日付を取得（開始時刻と終了時刻）
const getTodayRange = () => {
  const today = new Date();
  // ローカルタイムゾーンで今日の開始時刻と終了時刻を取得
  const year = today.getFullYear();
  const month = today.getMonth();
  const date = today.getDate();
  const start = new Date(year, month, date, 0, 0, 0);
  const end = new Date(year, month, date, 23, 59, 59);
  return { start, end };
};

// 本日を中心として前3日後3日の合計7日間の開始日を計算する関数
function getCenteredDateRange() {
  const today = new Date();
  const startDate = new Date(today);
  startDate.setDate(today.getDate() - 3); // 本日から3日前を開始日とする（本日を中心として前3日後3日）
  return startDate;
}

// ユーザー単位カレンダーオプション（7日間表示）
const userCalendarOptions = computed(() => {
  // 本日の日付を中心として前3日後3日の合計7日間の開始日を計算
  const startDate = getCenteredDateRange();
  
  return {
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: "timeGridWeek",
    duration: { days: 7 }, // 7日間表示（前3日 + 本日 + 後3日）
    initialDate: startDate, // 本日を中心とする前後7日間の開始日
    locale: jaLocale,
    customButtons: {
      customToday: {
        text: '今日',
        click: function() {
          // 本日を中心として前3日後3日の合計7日間を表示
          const centeredStartDate = getCenteredDateRange();
          if (userCalendar.value) {
            userCalendar.value.getApi().gotoDate(centeredStartDate);
          }
        }
      }
    },
    headerToolbar: {
      left: "prev,next customToday",
      center: "title",
      right: "",
    },
    height: "auto",
    editable: true,
    selectable: true,
    selectMirror: true,
    weekends: true,
    nowIndicator: true, // 現在時刻のインジケーターを有効化
    select: handleUserDateSelect,
    eventClick: handleEventClick,
    eventDrop: handleEventDrop,
    eventResize: handleEventResize,
    eventMouseEnter: handleEventMouseEnter,
    eventMouseLeave: handleEventMouseLeave,
    events: loadUserSchedules,
    slotMinTime: "06:00:00",
    slotMaxTime: "24:00:00",
    slotDuration: "00:30:00",
    datesSet: handleUserCalendarDatesSet, // カレンダーの日付が変更されたときに呼ばれる
  };
});

// 店舗単位スケジュール読み込み
function loadShopSchedules(info, successCallback, failureCallback) {
  // 店舗が選択されていない場合は空の配列を返す
  if (!selectedShopId.value) {
    if (successCallback) {
      successCallback([]);
    }
    return;
  }

  const params = {
    start: info?.startStr || new Date().toISOString(),
    end: info?.endStr || new Date().toISOString(),
    mode: 'shop',
    shop_id: selectedShopId.value,
  };

  axios
    .get(route("admin.schedules.index"), { params })
    .then((response) => {
      if (successCallback) {
        // ユーザーリストが存在し、選択されたユーザーIDが0の場合は空配列を返す（全解除時）
        if (usersForShopCalendar.value.length > 0 && selectedUserIdsForShopCalendar.value.length === 0) {
          successCallback([]);
          return;
        }
        
        // 選択されたユーザーIDでフィルタリング
        let filteredData = response.data;
        
        // ユーザーが選択されている場合のみフィルタリング
        if (selectedUserIdsForShopCalendar.value.length > 0) {
          // 全ユーザーが選択されている場合はフィルタリング不要
          if (selectedUserIdsForShopCalendar.value.length < usersForShopCalendar.value.length) {
            filteredData = response.data.filter(event => {
              // extendedProps.user.idでフィルタリング（スケジュールの作成者）
              if (event.extendedProps?.user?.id) {
                return selectedUserIdsForShopCalendar.value.includes(event.extendedProps.user.id);
              }
              // ユーザー情報が取得できない場合は表示しない
              return false;
            });
          }
        }
        
        successCallback(filteredData);
      }
    })
    .catch((error) => {
      console.error("スケジュールの取得に失敗しました:", error);
      if (failureCallback) {
        failureCallback(error);
      }
    });
}

// 店舗単位カレンダーのユーザーチェックボックス変更時の処理
function onShopCalendarUserChange() {
  if (shopCalendar.value) {
    shopCalendar.value.getApi().refetchEvents();
    // 高さを同期（イベント読み込み完了を待つ）
    setTimeout(() => {
      syncCalendarHeights();
    }, 300);
    // 念のため、もう一度同期（レンダリング完了を待つ）
    setTimeout(() => {
      syncCalendarHeights();
    }, 600);
  }
}

// 全ユーザーを選択
function selectAllUsers() {
  if (usersForShopCalendar.value.length > 0) {
    selectedUserIdsForShopCalendar.value = usersForShopCalendar.value.map(u => u.id);
    // スケジュール情報を再度取得
    nextTick(() => {
      onShopCalendarUserChange();
    });
  }
}

// 全ユーザーの選択を解除
function deselectAllUsers() {
  selectedUserIdsForShopCalendar.value = [];
  // スケジュール情報を再度取得
  nextTick(() => {
    onShopCalendarUserChange();
  });
}

// ユーザー単位スケジュール読み込み（カレンダーの表示期間）
function loadUserSchedules(info, successCallback, failureCallback) {
  // ユーザーが選択されていない場合は空の配列を返す
  if (!selectedUserId.value) {
    if (successCallback) {
      successCallback([]);
    }
    return;
  }

  // FullCalendarから渡される表示期間を使用
  const params = {
    start: info?.startStr || new Date().toISOString(),
    end: info?.endStr || new Date().toISOString(),
    mode: 'user',
    user_id: selectedUserId.value,
  };

  axios
    .get(route("admin.schedules.index"), { params })
    .then((response) => {
      if (successCallback) {
        successCallback(response.data);
      }
      // イベント読み込み後に現在時刻の線を更新
      nextTick(() => {
        setTimeout(() => {
          updateCurrentTimeIndicator();
        }, 100);
      });
    })
    .catch((error) => {
      console.error("スケジュールの取得に失敗しました:", error);
      if (failureCallback) {
        failureCallback(error);
      }
    });
}

function handleDateSelect(selectInfo) {
  const startDate = new Date(selectInfo.startStr);
  const endDate = new Date(selectInfo.endStr);
  
  createScheduleForm.value.start_at = formatDateTimeLocal(startDate);
  createScheduleForm.value.end_at = formatDateTimeLocal(endDate);
  createScheduleForm.value.all_day = selectInfo.allDay;
  
  // 参加者リストをクリアしてからログインユーザーをデフォルトで参加者として追加
  addedParticipantsForCreate.value = [];
  shopUsersForCreate.value = [];
  selectedShopIdForCreate.value = '';
  if (props.currentUser) {
    addedParticipantsForCreate.value.push({
      id: props.currentUser.id,
      name: props.currentUser.name,
    });
    createScheduleForm.value.participant_ids = [props.currentUser.id];
  } else {
    createScheduleForm.value.participant_ids = [];
  }
  
  showCreateModal.value = true;
  shopCalendar.value.getApi().unselect();
}

// ユーザー単位カレンダーでの時間選択ハンドラー
function handleUserDateSelect(selectInfo) {
  const startDate = new Date(selectInfo.startStr);
  const endDate = new Date(selectInfo.endStr);
  
  // 選択された時間で予定作成フォームを初期化
  createScheduleForm.value.start_at = formatDateTimeLocal(startDate);
  createScheduleForm.value.end_at = formatDateTimeLocal(endDate);
  createScheduleForm.value.all_day = selectInfo.allDay;
  
  // 参加者リストをクリアしてからログインユーザーをデフォルトで参加者として追加
  addedParticipantsForCreate.value = [];
  shopUsersForCreate.value = [];
  selectedShopIdForCreate.value = '';
  if (props.currentUser) {
    addedParticipantsForCreate.value.push({
      id: props.currentUser.id,
      name: props.currentUser.name,
    });
    createScheduleForm.value.participant_ids = [props.currentUser.id];
  } else {
    createScheduleForm.value.participant_ids = [];
  }
  
  // 予定作成モーダルを表示
  showCreateModal.value = true;
  
  // 選択状態を解除
  userCalendar.value.getApi().unselect();
}

// ユーザー単位カレンダーの日付が変更されたときのハンドラー
function handleUserCalendarDatesSet(dateInfo) {
  // カレンダーのレンダリング後に現在時刻の線を更新
  nextTick(() => {
    // レンダリング完了を待つために少し遅延させる
    setTimeout(() => {
      updateCurrentTimeIndicator();
    }, 300);
    // 念のため、もう一度試行
    setTimeout(() => {
      updateCurrentTimeIndicator();
    }, 600);
  });
}

// 現在時刻のインジケーターを更新（7日間すべてをまたぐ赤い線）
let currentTimeIndicatorInterval = null;

function updateCurrentTimeIndicator() {
  if (!userCalendar.value) {
    return;
  }
  
  const calendarEl = userCalendar.value.$el;
  if (!calendarEl) {
    return;
  }
  
  // 既存のカスタムインジケーターを削除
  const existingIndicators = calendarEl.querySelectorAll('.custom-current-time-indicator');
  existingIndicators.forEach(indicator => indicator.remove());
  
  const calendarApi = userCalendar.value.getApi();
  const view = calendarApi.view;
  if (!view || view.type !== 'timeGridWeek') {
    return;
  }
  
  // 現在時刻を取得
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  
  // 表示期間を取得
  const start = view.activeStart;
  const end = view.activeEnd;
  
  // 今日が表示期間内にあるか確認
  const todayStartOfDay = new Date(today.getTime());
  const todayEndOfDay = new Date(today.getTime());
  todayEndOfDay.setHours(23, 59, 59, 999);
  
  if (todayEndOfDay < start || todayStartOfDay > end) {
    return; // 表示期間外の場合は何もしない
  }
  
  // FullCalendarのDOM構造を確認して、正しい要素を取得
  // まず、.fc-scrollgrid を探す
  let scrollGrid = calendarEl.querySelector('.fc-scrollgrid');
  if (!scrollGrid) {
    // 見つからない場合は、カレンダー要素全体を探す
    scrollGrid = calendarEl;
  }
  
  // 時間グリッドのボディを取得（複数のセレクタを試す）
  let timeGridBody = scrollGrid.querySelector('.fc-timegrid-body');
  if (!timeGridBody) {
    timeGridBody = scrollGrid.querySelector('.fc-scrollgrid-sync-table');
  }
  if (!timeGridBody) {
    timeGridBody = scrollGrid.querySelector('table');
  }
  if (!timeGridBody) {
    setTimeout(() => {
      updateCurrentTimeIndicator();
    }, 200);
    return;
  }
  
  // 時間スロットのテーブル行を取得（複数のセレクタを試す）
  let timeSlots = timeGridBody.querySelectorAll('tbody tr.fc-timegrid-slot');
  if (timeSlots.length === 0) {
    timeSlots = timeGridBody.querySelectorAll('tbody tr');
  }
  if (timeSlots.length === 0) {
    setTimeout(() => {
      updateCurrentTimeIndicator();
    }, 200);
    return;
  }
  
  // スロットの開始時刻を取得（userCalendarOptionsで設定した値を直接使用）
  // slotMinTimeは "06:00:00" 形式の文字列なので、パースする
  const slotMinTimeStr = "06:00:00"; // userCalendarOptionsで設定した値
  const slotDurationStr = "00:30:00"; // userCalendarOptionsで設定した値
  
  // 時間文字列をパース
  const [minHour, minMinute] = slotMinTimeStr.split(':').map(Number);
  const [durationHour, durationMinute] = slotDurationStr.split(':').map(Number);
  const slotDurationMs = (durationHour * 60 + durationMinute) * 60 * 1000; // ミリ秒
  
  // 現在時刻が表示時間範囲内にあるか確認
  const nowHours = now.getHours() + now.getMinutes() / 60 + now.getSeconds() / 3600;
  const minHours = minHour + minMinute / 60;
  const maxHours = 24; // slotMaxTimeは24:00:00
  
  if (nowHours < minHours || nowHours > maxHours) {
    return; // 表示時間範囲外の場合は何もしない
  }
  
  // 最初のスロットの位置と高さを取得
  const firstSlot = timeSlots[0];
  const firstSlotRect = firstSlot.getBoundingClientRect();
  const timeGridBodyRect = timeGridBody.getBoundingClientRect();
  const slotTop = firstSlotRect.top - timeGridBodyRect.top;
  const slotHeight = firstSlotRect.height;
  
  if (!slotHeight || slotHeight === 0) {
    setTimeout(() => {
      updateCurrentTimeIndicator();
    }, 200);
    return;
  }
  
  // 現在時刻の位置を計算
  const todayStart = new Date(today);
  todayStart.setHours(minHour, minMinute, 0, 0);
  
  const diffMs = now.getTime() - todayStart.getTime();
  const slotDurationHours = slotDurationMs / (1000 * 60 * 60); // 時間単位
  const diffHours = diffMs / (1000 * 60 * 60);
  const topOffset = slotTop + (diffHours / slotDurationHours) * slotHeight;
  
  // 現在時刻をフォーマット
  const formatCurrentTime = (date) => {
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${hours}:${minutes}`;
  };
  
  const currentTimeStr = formatCurrentTime(now);
  
  // 7日間すべてをまたぐ線を作成
  const indicator = document.createElement('div');
  indicator.className = 'custom-current-time-indicator';
  indicator.style.position = 'absolute';
  indicator.style.left = '0';
  indicator.style.right = '0';
  indicator.style.top = `${topOffset}px`;
  indicator.style.height = '2px';
  indicator.style.backgroundColor = '#ef4444'; // 赤色
  indicator.style.zIndex = '1000';
  indicator.style.pointerEvents = 'auto'; // ホバー可能にする
  indicator.style.cursor = 'pointer';
  indicator.style.boxShadow = '0 0 4px rgba(239, 68, 68, 0.5)';
  
  // ツールチップ要素を作成
  const tooltip = document.createElement('div');
  tooltip.className = 'custom-current-time-tooltip';
  tooltip.textContent = currentTimeStr;
  tooltip.style.position = 'absolute';
  tooltip.style.left = '50%';
  tooltip.style.transform = 'translateX(-50%)';
  tooltip.style.bottom = '100%';
  tooltip.style.marginBottom = '8px';
  tooltip.style.padding = '4px 8px';
  tooltip.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
  tooltip.style.color = 'white';
  tooltip.style.borderRadius = '4px';
  tooltip.style.fontSize = '12px';
  tooltip.style.fontWeight = '600';
  tooltip.style.whiteSpace = 'nowrap';
  tooltip.style.pointerEvents = 'none';
  tooltip.style.opacity = '0';
  tooltip.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
  tooltip.style.transform = 'translateX(-50%) translateY(4px)';
  tooltip.style.zIndex = '1001';
  
  // ホバーイベントを追加
  indicator.addEventListener('mouseenter', () => {
    tooltip.style.opacity = '1';
    tooltip.style.transform = 'translateX(-50%) translateY(0)';
  });
  
  indicator.addEventListener('mouseleave', () => {
    tooltip.style.opacity = '0';
    tooltip.style.transform = 'translateX(-50%) translateY(4px)';
  });
  
  // ツールチップをインジケーターに追加
  indicator.appendChild(tooltip);
  
  // カレンダーのボディに追加
  if (timeGridBody.style.position !== 'relative') {
    timeGridBody.style.position = 'relative';
  }
  timeGridBody.appendChild(indicator);
}

const showScheduleDetail = ref(false);
const selectedScheduleDetail = ref(null);
const showEditModal = ref(false);
const showCreateModal = ref(false);
const isEditingSchedule = ref(false);
const tooltip = ref({
  visible: false,
  x: 0,
  y: 0,
  title: '',
  user: '',
  start: '',
  end: '',
  participants: [],
  description: '',
});
const createScheduleForm = ref({
  title: '',
  description: '',
  start_at: '',
  end_at: '',
  all_day: false,
  color: '#3788d8',
  participant_ids: [],
  expense_category: '',
  processing: false,
});

// 便利機能：費用科目の表示制御
const enableExpenseCategoryFeature = ref(false);
const showExpenseCategoryInCreate = computed(() => enableExpenseCategoryFeature.value);
const showExpenseCategoryInEdit = computed(() => enableExpenseCategoryFeature.value);

// LocalStorageから費用科目機能の設定を読み込む
function loadExpenseCategoryFeatureSetting() {
  try {
    const saved = localStorage.getItem('enableExpenseCategoryFeature');
    if (saved !== null) {
      enableExpenseCategoryFeature.value = saved === 'true';
    }
  } catch (error) {
    console.error('LocalStorageからの読み込みに失敗しました:', error);
  }
}

// 費用科目機能の設定をLocalStorageに保存
function onExpenseCategoryFeatureChange() {
  try {
    localStorage.setItem('enableExpenseCategoryFeature', enableExpenseCategoryFeature.value.toString());
  } catch (error) {
    console.error('LocalStorageへの保存に失敗しました:', error);
  }
}
const editScheduleForm = ref({
  title: '',
  description: '',
  start_at: '',
  end_at: '',
  all_day: false,
  color: '#3788d8',
  participant_ids: [],
  expense_category: '',
  processing: false,
});
const shopUsersForEdit = ref([]);
const selectedShopIdForEdit = ref('');
const addedParticipantsForEdit = ref([]);
const shopUsersForCreate = ref([]);
const selectedShopIdForCreate = ref('');
const addedParticipantsForCreate = ref([]);

// スケジュール編集権限チェック
function canEditSchedule(schedule) {
  if (!schedule || !props.currentUser) return false;
  // 作成者または参加者の場合のみ編集可能
  const isCreator = schedule.user?.id === props.currentUser.id;
  const isParticipant = schedule.participants?.some(p => p.id === props.currentUser.id);
  return isCreator || isParticipant;
}

function handleEventClick(clickInfo) {
  selectedScheduleDetail.value = {
    id: clickInfo.event.id,
    title: clickInfo.event.title,
    start: clickInfo.event.startStr,
    end: clickInfo.event.endStr,
    allDay: clickInfo.event.allDay,
    color: clickInfo.event.backgroundColor,
    description: clickInfo.event.extendedProps.description || "",
    user: clickInfo.event.extendedProps.user || null,
    participants: clickInfo.event.extendedProps.participants || [],
    expense_category: clickInfo.event.extendedProps.expense_category || null,
  };
  showScheduleDetail.value = true;
}

// 参加者が追加済みかチェック（編集用）
function isParticipantAddedForEdit(userId) {
  return addedParticipantsForEdit.value.some(p => p.id === userId);
}

// 参加者を追加/削除（編集用）
function toggleParticipantForEdit(userId, checked) {
  if (checked) {
    const user = shopUsersForEdit.value.find(u => u.id === userId);
    if (user && !isParticipantAddedForEdit(userId)) {
      addedParticipantsForEdit.value.push(user);
      editScheduleForm.value.participant_ids = addedParticipantsForEdit.value.map(p => p.id);
    }
  } else {
    removeParticipantForEdit(userId);
  }
}

// 参加者を削除（編集用）
function removeParticipantForEdit(userId) {
  addedParticipantsForEdit.value = addedParticipantsForEdit.value.filter(p => p.id !== userId);
  editScheduleForm.value.participant_ids = addedParticipantsForEdit.value.map(p => p.id);
}

// 店舗ユーザー取得（編集用）
async function loadShopUsersForEdit(shopId) {
  if (!shopId) {
    shopUsersForEdit.value = [];
    return;
  }
  
  try {
    const response = await axios.get(route('admin.schedules.shop-users'), {
      params: { shop_id: shopId }
    });
    shopUsersForEdit.value = response.data;
  } catch (error) {
    console.error('店舗ユーザーの取得に失敗しました:', error);
    shopUsersForEdit.value = [];
  }
}

// スケジュール編集開始
function startEditSchedule() {
  if (!selectedScheduleDetail.value) return;
  
  const startDate = new Date(selectedScheduleDetail.value.start);
  const endDate = new Date(selectedScheduleDetail.value.end);
  
  editScheduleForm.value = {
    title: selectedScheduleDetail.value.title,
    description: selectedScheduleDetail.value.description || '',
    start_at: formatDateTimeLocal(startDate),
    end_at: formatDateTimeLocal(endDate),
    all_day: selectedScheduleDetail.value.allDay,
    color: selectedScheduleDetail.value.color,
    participant_ids: selectedScheduleDetail.value.participants?.map(p => p.id) || [],
    expense_category: selectedScheduleDetail.value.expense_category || '',
    processing: false,
  };
  
  // 既存の参加者を追加済みリストに設定
  addedParticipantsForEdit.value = selectedScheduleDetail.value.participants?.map(p => ({
    id: p.id,
    name: p.name,
  })) || [];
  
  // デフォルト店舗を設定（参加者追加用）
  if (userShops.value.length > 0) {
    selectedShopIdForEdit.value = userShops.value[0].id;
    loadShopUsersForEdit(userShops.value[0].id);
  }
  
  showScheduleDetail.value = false;
  showEditModal.value = true;
  isEditingSchedule.value = true;
}

// スケジュール更新
// 編集フォームの開始日時変更時の処理（終日の場合、終了日時を自動設定）
function onEditStartAtChange() {
  if (editScheduleForm.value.all_day && editScheduleForm.value.start_at) {
    // 開始日時の日付部分を取得して23:59に設定
    const startDate = editScheduleForm.value.start_at.split('T')[0];
    editScheduleForm.value.end_at = `${startDate}T23:59`;
  }
}

function updateScheduleFromDashboard() {
  if (!selectedScheduleDetail.value) {
    console.error('selectedScheduleDetailが存在しません');
    return;
  }
  
  console.log('updateScheduleFromDashboard開始');
  console.log('selectedScheduleDetail:', selectedScheduleDetail.value);
  console.log('editScheduleForm:', editScheduleForm.value);
  
  editScheduleForm.value.processing = true;
  
  const updateData = {
    title: editScheduleForm.value.title,
    description: editScheduleForm.value.description || '',
    start_at: editScheduleForm.value.start_at,
    end_at: editScheduleForm.value.end_at,
    all_day: editScheduleForm.value.all_day,
    color: editScheduleForm.value.color,
    user_id: selectedScheduleDetail.value.user?.id,
    participant_ids: editScheduleForm.value.participant_ids || [],
    expense_category: editScheduleForm.value.expense_category || null,
  };
  
  console.log('更新データ:', updateData);
  console.log('更新URL:', route('admin.schedules.update', selectedScheduleDetail.value.id));
  
  axios.put(route('admin.schedules.update', selectedScheduleDetail.value.id), updateData)
    .then((response) => {
      console.log('更新成功:', response);
      
      // カレンダーのイベントを直接更新（リロードなしで反映）
      const scheduleId = selectedScheduleDetail.value.id;
      
      // 店舗単位カレンダーのイベントを更新
      if (shopCalendar.value) {
        const shopEvent = shopCalendar.value.getApi().getEventById(scheduleId);
        if (shopEvent) {
          shopEvent.setExtendedProp('expense_category', editScheduleForm.value.expense_category || null);
          // その他の変更も反映
          if (editScheduleForm.value.title !== shopEvent.title) {
            shopEvent.setProp('title', editScheduleForm.value.title);
          }
          if (editScheduleForm.value.description !== shopEvent.extendedProps.description) {
            shopEvent.setExtendedProp('description', editScheduleForm.value.description || '');
          }
        }
      }
      
      // ユーザー単位カレンダーのイベントを更新
      if (userCalendar.value) {
        const userEvent = userCalendar.value.getApi().getEventById(scheduleId);
        if (userEvent) {
          userEvent.setExtendedProp('expense_category', editScheduleForm.value.expense_category || null);
          // その他の変更も反映
          if (editScheduleForm.value.title !== userEvent.title) {
            userEvent.setProp('title', editScheduleForm.value.title);
          }
          if (editScheduleForm.value.description !== userEvent.extendedProps.description) {
            userEvent.setExtendedProp('description', editScheduleForm.value.description || '');
          }
        }
      }
      
      // selectedScheduleDetailも更新（モーダルを閉じる前に）
      if (selectedScheduleDetail.value) {
        selectedScheduleDetail.value.expense_category = editScheduleForm.value.expense_category || null;
        selectedScheduleDetail.value.title = editScheduleForm.value.title;
        selectedScheduleDetail.value.description = editScheduleForm.value.description || '';
      }
      
      showEditModal.value = false;
      isEditingSchedule.value = false;
      addedParticipantsForEdit.value = [];
      selectedShopIdForEdit.value = '';
      shopUsersForEdit.value = [];
      
      // 念のため、イベントを再読み込み（非同期で実行）
      setTimeout(() => {
        shopCalendar.value?.getApi().refetchEvents();
        userCalendar.value?.getApi().refetchEvents();
        syncCalendarHeights();
      }, 100);
    })
    .catch(error => {
      console.error('スケジュールの更新に失敗しました:', error);
      console.error('エラー詳細:', error.response?.data || error.message);
      alert('スケジュールの更新に失敗しました。' + (error.response?.data?.message || ''));
    })
    .finally(() => {
      editScheduleForm.value.processing = false;
    });
}

// スケジュール削除
function deleteScheduleFromDashboard() {
  if (!selectedScheduleDetail.value) return;
  
  if (confirm('このスケジュールを削除しますか？')) {
    axios.delete(route('admin.schedules.destroy', selectedScheduleDetail.value.id))
      .then(() => {
        showScheduleDetail.value = false;
        shopCalendar.value.getApi().refetchEvents();
        userCalendar.value.getApi().refetchEvents();
      })
      .catch(error => {
        console.error('スケジュールの削除に失敗しました:', error);
        alert('スケジュールの削除に失敗しました。');
      });
  }
}

function formatDateTimeLocal(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function handleEventDrop(dropInfo) {
  // 権限チェック
  const event = dropInfo.event;
  const schedule = {
    user: event.extendedProps.user,
    participants: event.extendedProps.participants || [],
  };
  
  if (!canEditSchedule(schedule)) {
    alert('このスケジュールを編集する権限がありません。');
    dropInfo.revert();
    return;
  }
  
  // 参加者情報を引き継ぐ
  const participantIds = event.extendedProps.participants?.map(p => p.id) || [];
  
  const scheduleData = {
    title: dropInfo.event.title,
    start_at: dropInfo.event.startStr,
    end_at: dropInfo.event.endStr,
    all_day: dropInfo.event.allDay,
    user_id: event.extendedProps.user?.id,
    participant_ids: participantIds,
  };

  axios
    .put(route("admin.schedules.update", dropInfo.event.id), scheduleData)
    .then(() => {
      // 両方のカレンダーをリフレッシュ
      if (shopCalendar.value) {
        shopCalendar.value.getApi().refetchEvents();
      }
      if (userCalendar.value) {
        userCalendar.value.getApi().refetchEvents();
      }
      // 高さを同期
      setTimeout(() => {
        syncCalendarHeights();
      }, 300);
    })
    .catch((error) => {
      console.error("スケジュールの更新に失敗しました:", error);
      alert("スケジュールの更新に失敗しました。");
      dropInfo.revert();
    });
}

function handleEventResize(resizeInfo) {
  // 権限チェック
  const event = resizeInfo.event;
  const schedule = {
    user: event.extendedProps.user,
    participants: event.extendedProps.participants || [],
  };
  
  if (!canEditSchedule(schedule)) {
    alert('このスケジュールを編集する権限がありません。');
    resizeInfo.revert();
    return;
  }
  
  // 参加者情報を引き継ぐ
  const participantIds = event.extendedProps.participants?.map(p => p.id) || [];
  
  const scheduleData = {
    title: resizeInfo.event.title,
    start_at: resizeInfo.event.startStr,
    end_at: resizeInfo.event.endStr,
    all_day: resizeInfo.event.allDay,
    user_id: event.extendedProps.user?.id,
    participant_ids: participantIds,
  };

  axios
    .put(route("admin.schedules.update", resizeInfo.event.id), scheduleData)
    .then(() => {
      // 両方のカレンダーをリフレッシュ
      if (shopCalendar.value) {
        shopCalendar.value.getApi().refetchEvents();
      }
      if (userCalendar.value) {
        userCalendar.value.getApi().refetchEvents();
      }
      // 高さを同期
      setTimeout(() => {
        syncCalendarHeights();
      }, 300);
    })
    .catch((error) => {
      console.error("スケジュールの更新に失敗しました:", error);
      alert("スケジュールの更新に失敗しました。");
      resizeInfo.revert();
    });
}

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

// 店舗変更時の処理
async function onShopChange() {
  // 店舗が選択されていない場合はユーザーリストをクリア
  if (!selectedShopId.value) {
    usersForShopCalendar.value = [];
    selectedUserIdsForShopCalendar.value = [];
    if (shopCalendar.value) {
      shopCalendar.value.getApi().refetchEvents();
    }
    return;
  }
  
  // 選択された店舗に所属するユーザーを取得
  try {
    const response = await axios.get(route('admin.schedules.shop-users'), {
      params: { shop_id: selectedShopId.value }
    });
    usersForShopCalendar.value = response.data;
    // デフォルトで全ユーザーを選択
    selectedUserIdsForShopCalendar.value = response.data.map(u => u.id);
  } catch (error) {
    console.error('店舗ユーザーの取得に失敗しました:', error);
    usersForShopCalendar.value = [];
    selectedUserIdsForShopCalendar.value = [];
  }
  
  // カレンダーをリフレッシュ
  if (shopCalendar.value) {
    shopCalendar.value.getApi().refetchEvents();
    // 高さを同期（イベント読み込み完了を待つ）
    setTimeout(() => {
      syncCalendarHeights();
    }, 300);
    // 念のため、もう一度同期（レンダリング完了を待つ）
    setTimeout(() => {
      syncCalendarHeights();
    }, 600);
  }
}

// ユーザー変更時の処理
function onUserChange() {
  console.log('[onUserChange] ユーザー変更:', selectedUserId.value);
  if (userCalendar.value && selectedUserId.value) {
    console.log('[onUserChange] カレンダーをリフレッシュ');
    userCalendar.value.getApi().refetchEvents();
    // 高さを同期（イベント読み込み完了を待つ）
    setTimeout(() => {
      syncCalendarHeights();
      updateCurrentTimeIndicator();
    }, 300);
    // 念のため、もう一度同期（レンダリング完了を待つ）
    setTimeout(() => {
      syncCalendarHeights();
      updateCurrentTimeIndicator();
    }, 600);
  } else {
    console.warn('[onUserChange] カレンダーのリフレッシュをスキップ:', {
      userCalendar: !!userCalendar.value,
      selectedUserId: selectedUserId.value
    });
  }
}

// ユーザー単位カレンダーの日付を変更（日数で加算/減算）
function changeUserDate(days) {
  if (!selectedUserDate.value) return;
  
  const currentDate = new Date(selectedUserDate.value);
  currentDate.setDate(currentDate.getDate() + days);
  
  const year = currentDate.getFullYear();
  const month = String(currentDate.getMonth() + 1).padStart(2, '0');
  const date = String(currentDate.getDate()).padStart(2, '0');
  selectedUserDate.value = `${year}-${month}-${date}`;
  
  // カレンダーを更新
  onUserDateChange();
}

// ユーザー単位カレンダーの日付変更時の処理
function onUserDateChange() {
  console.log('[onUserDateChange] 日付変更:', selectedUserDate.value);
  if (userCalendar.value && selectedUserDate.value) {
    // カレンダーの日付を変更
    const selectedDate = new Date(selectedUserDate.value);
    userCalendar.value.getApi().gotoDate(selectedDate);
    // validRangeを更新
    const year = selectedDate.getFullYear();
    const month = selectedDate.getMonth();
    const date = selectedDate.getDate();
    const startStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
    const endStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date + 1).padStart(2, '0')}`;
    userCalendar.value.getApi().setOption('validRange', {
      start: startStr,
      end: endStr,
    });
    // スケジュールを再読み込み
    userCalendar.value.getApi().refetchEvents();
    // 高さを同期（イベント読み込み完了を待つ）
    setTimeout(() => {
      syncCalendarHeights();
    }, 300);
    // 念のため、もう一度同期（レンダリング完了を待つ）
    setTimeout(() => {
      syncCalendarHeights();
    }, 600);
  }
}

// スケジュール作成
function createScheduleFromDashboard() {
  createScheduleForm.value.processing = true;
  
  const createData = {
    ...createScheduleForm.value,
    user_id: props.currentUser?.id,
  };
  delete createData.processing;
  
  axios.post(route('admin.schedules.store'), createData)
    .then(() => {
      showCreateModal.value = false;
      createScheduleForm.value = {
        title: '',
        description: '',
        start_at: '',
        end_at: '',
        all_day: false,
        color: '#3788d8',
        participant_ids: [],
        expense_category: '',
        processing: false,
      };
      addedParticipantsForCreate.value = [];
      selectedShopIdForCreate.value = '';
      shopUsersForCreate.value = [];
      shopCalendar.value.getApi().refetchEvents();
      userCalendar.value.getApi().refetchEvents();
      setTimeout(() => {
        syncCalendarHeights();
      }, 300);
    })
    .catch(error => {
      console.error('スケジュールの作成に失敗しました:', error);
      alert('スケジュールの作成に失敗しました。');
    })
    .finally(() => {
      createScheduleForm.value.processing = false;
    });
}

// 参加者が追加済みかチェック（作成用）
function isParticipantAddedForCreate(userId) {
  return addedParticipantsForCreate.value.some(p => p.id === userId);
}

// 参加者を追加/削除（作成用）
function toggleParticipantForCreate(userId, checked) {
  if (checked) {
    const user = shopUsersForCreate.value.find(u => u.id === userId);
    if (user && !isParticipantAddedForCreate(userId)) {
      addedParticipantsForCreate.value.push(user);
      createScheduleForm.value.participant_ids = addedParticipantsForCreate.value.map(p => p.id);
    }
  } else {
    removeParticipantForCreate(userId);
  }
}

// 参加者を削除（作成用）
function removeParticipantForCreate(userId) {
  addedParticipantsForCreate.value = addedParticipantsForCreate.value.filter(p => p.id !== userId);
  createScheduleForm.value.participant_ids = addedParticipantsForCreate.value.map(p => p.id);
}

// 店舗ユーザー取得（作成用）
async function loadShopUsersForCreate(shopId) {
  if (!shopId) {
    shopUsersForCreate.value = [];
    return;
  }
  
  try {
    const response = await axios.get(route('admin.schedules.shop-users'), {
      params: { shop_id: shopId }
    });
    shopUsersForCreate.value = response.data;
  } catch (error) {
    console.error('店舗ユーザーの取得に失敗しました:', error);
    shopUsersForCreate.value = [];
  }
}
</script>

<style scoped>
/* カスタムイベント表示スタイル */
:deep(.custom-event-content) {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 2px 4px;
  font-size: 0.75rem;
  line-height: 1.3;
  overflow: hidden;
}

/* 時間指定予定のスタイル */
:deep(.timed-event) {
  background: rgba(255, 255, 255, 0.95) !important;
  border-left: 4px solid;
  border-radius: 4px;
  padding: 4px 6px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

:deep(.timed-event .event-time) {
  font-weight: 700;
  color: #374151;
  font-size: 0.7rem;
  white-space: nowrap;
  flex-shrink: 0;
  background: rgba(0, 0, 0, 0.05);
  padding: 2px 4px;
  border-radius: 3px;
}

:deep(.timed-event .event-title) {
  font-weight: 600;
  color: #1f2937;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

:deep(.timed-event .event-user) {
  font-size: 0.65rem;
  color: #6b7280;
  flex-shrink: 0;
  font-weight: 500;
}

/* 終日予定のスタイル */
:deep(.all-day-event) {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%);
  border-radius: 4px;
  padding: 4px 6px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

:deep(.all-day-event .event-title) {
  font-weight: 600;
  color: rgba(255, 255, 255, 1);
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

:deep(.all-day-event .event-user) {
  font-size: 0.7rem;
  color: rgba(255, 255, 255, 0.85);
  flex-shrink: 0;
}

/* FullCalendarのイベントスタイル調整 */
:deep(.fc-event) {
  border: none;
  border-radius: 4px;
  padding: 0;
  margin: 1px 0;
}

:deep(.fc-daygrid-event) {
  margin: 2px 4px;
}

:deep(.fc-event-main) {
  padding: 0;
}

/* 月表示でのイベント表示改善 */
:deep(.fc-daygrid-day-frame) {
  padding: 2px;
}

:deep(.fc-daygrid-day-events) {
  margin-top: 2px;
}

/* ホバー効果 */
:deep(.fc-event:hover) {
  opacity: 0.9;
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
  transition: all 0.2s ease;
  cursor: pointer;
}

/* ツールチップスタイル */
.schedule-tooltip {
  animation: fadeIn 0.2s ease-in;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-5px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* モーダルアニメーション */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.1s ease;
}

.modal-enter-active .relative,
.modal-leave-active .relative {
  transition: transform 0.1s ease, opacity 0.1s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
  transform: scale(0.98) translateY(-10px);
  opacity: 0;
}

/* カレンダーの高さを親要素いっぱいに設定 */
:deep(.fc) {
  height: 100% !important;
}

:deep(.fc-view-harness) {
  height: 100% !important;
}

:deep(.fc-view-harness-active) {
  height: 100% !important;
}

/* ユーザー単位カレンダーの現在時刻インジケーター（7日間すべてをまたぐ赤い線） */
.custom-current-time-indicator {
  position: absolute;
  left: 0;
  right: 0;
  height: 2px;
  background-color: #ef4444;
  z-index: 1000;
  pointer-events: auto;
  cursor: pointer;
  box-shadow: 0 0 4px rgba(239, 68, 68, 0.5);
  transition: height 0.2s ease, box-shadow 0.2s ease;
}

.custom-current-time-indicator:hover {
  height: 3px;
  box-shadow: 0 0 8px rgba(239, 68, 68, 0.7);
}

/* 現在時刻ツールチップ */
.custom-current-time-tooltip {
  position: absolute;
  left: 50%;
  transform: translateX(-50%) translateY(4px);
  bottom: 100%;
  margin-bottom: 8px;
  padding: 4px 8px;
  background-color: rgba(0, 0, 0, 0.8);
  color: white;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.2s ease, transform 0.2s ease;
  z-index: 1001;
}

.custom-current-time-indicator:hover .custom-current-time-tooltip {
  opacity: 1;
  transform: translateX(-50%) translateY(0);
}

/* FullCalendarのデフォルトのnowIndicatorを非表示（カスタムの線を使用するため） */
:deep(.fc-timegrid-now-indicator-line) {
  display: none !important;
}

:deep(.fc-timegrid-now-indicator-arrow) {
  display: none !important;
}
</style>
