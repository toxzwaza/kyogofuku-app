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
            
            <!-- 横並びで2つのカレンダーを表示 -->
            <div class="flex gap-4 items-stretch">
              <!-- 店舗単位カレンダー（70%幅） -->
              <div style="flex: 0 0 70%; width: 70%;" class="flex flex-col">
                <div class="mb-4">
                  <label class="block text-sm font-medium text-gray-700 mb-2">店舗単位</label>
                  <select
                    v-model="selectedShopId"
                    @change="onShopChange"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                  >
                    <option value="">店舗を選択</option>
                    <option v-for="shop in userShops" :key="shop.id" :value="shop.id">
                      {{ shop.name }}
                    </option>
                  </select>
                </div>
                <div class="flex-1 min-h-0">
                  <FullCalendar ref="shopCalendar" :options="shopCalendarOptions" class="h-full" />
                </div>
              </div>

              <!-- ユーザー単位カレンダー（30%幅） -->
              <div style="flex: 0 0 30%; width: 30%;" class="flex flex-col">
                <div class="mb-4 space-y-2">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ユーザー単位</label>
                    <select
                      v-model="selectedUserId"
                      @change="onUserChange"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                      <option value="">ユーザーを選択</option>
                      <option v-for="user in users" :key="user.id" :value="user.id">
                        {{ user.name }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">表示日付</label>
                    <div class="flex items-center gap-2">
                      <button
                        @click="changeUserDate(-1)"
                        type="button"
                        class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm font-medium"
                        title="一日前"
                      >
                        ←
                      </button>
                      <input
                        v-model="selectedUserDate"
                        type="date"
                        @change="onUserDateChange"
                        class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      />
                      <button
                        @click="changeUserDate(1)"
                        type="button"
                        class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm font-medium"
                        title="一日後"
                      >
                        →
                      </button>
                    </div>
                  </div>
                </div>
                <div class="flex-1 min-h-0">
                  <FullCalendar ref="userCalendar" :options="userCalendarOptions" class="h-full" />
                </div>
              </div>
            </div>
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
import { computed, ref, onMounted, onUnmounted } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import StatCard from "../Components/StatCard.vue";
import TrendChart from "../Components/TrendChart.vue";
import FullCalendar from "@fullcalendar/vue3";
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
    key: "events",
    title: "イベント数",
        value: props.stats.events || 0,
    icon: "calendar",
    color: "blue",
    link: route("admin.events.index"),
  },
  {
    key: "active_events",
    title: "アクティブなイベント",
        value: props.stats.active_events || 0,
    icon: "calendar",
    color: "green",
    link: route("admin.events.index"),
  },
  {
    key: "reservations_today",
    title: "今日の予約",
        value: props.stats.reservations_today || 0,
    icon: "clipboard",
    color: "yellow",
    link: route("admin.events.index"),
  },
  {
    key: "reservations_this_month",
    title: "今月の予約",
        value: props.stats.reservations_this_month || 0,
    icon: "clipboard",
    color: "green",
    link: route("admin.events.index"),
  },
  {
    key: "reservations",
    title: "総予約数",
        value: props.stats.reservations || 0,
    icon: "clipboard",
    color: "indigo",
    link: route("admin.events.index"),
  },
  {
    key: "occupancy_rate",
    title: "予約枠埋まり率",
            value: `${props.occupancyRate || 0}%`,
    icon: "chart",
    color: "purple",
    link: route("admin.events.index"),
  },
  {
    key: "shops",
    title: "店舗数",
        value: props.stats.shops || 0,
    icon: "store",
    color: "orange",
    link: route("admin.shops.index"),
  },
  {
    key: "users",
    title: "スタッフ数",
        value: props.stats.users || 0,
    icon: "users",
    color: "indigo",
    link: route("admin.users.index"),
    },
]);

const chartType = ref("bar");
const shopCalendar = ref(null);
const userCalendar = ref(null);
const selectedShopId = ref("");
const selectedUserId = ref("");
// ユーザー単位カレンダーの表示日付（デフォルトは今日）
const getTodayDateString = () => {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const date = String(today.getDate()).padStart(2, '0');
  return `${year}-${month}-${date}`;
};
const selectedUserDate = ref(getTodayDateString());
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

// デフォルト値を設定
onMounted(() => {
  console.log('[Dashboard] onMounted開始');
  console.log('[Dashboard] currentUser:', props.currentUser);
  console.log('[Dashboard] userShops:', userShops.value);
  console.log('[Dashboard] users:', users.value);
  
  // 店舗単位：mainフラグが立っている店舗を優先的に選択、なければ最初の店舗を選択
  if (userShops.value.length > 0) {
    const mainShop = userShops.value.find(shop => shop.main === true || shop.main === 1);
    const defaultShop = mainShop || userShops.value[0];
    selectedShopId.value = defaultShop.id;
    selectedShopIdForCreate.value = defaultShop.id;
    loadShopUsersForCreate(defaultShop.id);
    console.log('[Dashboard] 店舗ID設定:', selectedShopId.value, 'main:', defaultShop.main);
  }
  
  // ユーザー単位：ログインユーザーを選択
  if (props.currentUser) {
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
    if (userCalendar.value && selectedUserId.value) {
      console.log('[Dashboard] ユーザー単位カレンダーをリフレッシュ');
      // 初期日付を設定
      const initialDate = new Date(selectedUserDate.value);
      userCalendar.value.getApi().gotoDate(initialDate);
      userCalendar.value.getApi().refetchEvents();
    } else {
      console.warn('[Dashboard] ユーザー単位カレンダーのリフレッシュをスキップ:', {
        userCalendar: !!userCalendar.value,
        selectedUserId: selectedUserId.value
      });
    }
    
    // カレンダーの高さを揃える
    setTimeout(() => {
      syncCalendarHeights();
    }, 500);
  }, 200);
});

// クリーンアップ
onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
  if (resizeTimeout) {
    clearTimeout(resizeTimeout);
  }
});

// カレンダーの高さを同期する関数
function syncCalendarHeights() {
  if (shopCalendar.value && userCalendar.value) {
    const shopCalendarEl = shopCalendar.value.getApi().el;
    const userCalendarEl = userCalendar.value.getApi().el;
    
    if (shopCalendarEl && userCalendarEl) {
      // 店舗単位カレンダーの実際の高さを取得
      const shopHeight = shopCalendarEl.offsetHeight;
      if (shopHeight > 0) {
        // ユーザー単位カレンダーの高さを店舗単位カレンダーに合わせる
        userCalendar.value.getApi().setOption('height', shopHeight);
        // 少し待ってから再度確認（レンダリングが完了するまで待つ）
        setTimeout(() => {
          const updatedShopHeight = shopCalendarEl.offsetHeight;
          if (updatedShopHeight > 0 && updatedShopHeight !== shopHeight) {
            userCalendar.value.getApi().setOption('height', updatedShopHeight);
          }
        }, 100);
      }
    }
  }
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
  viewDidMount: () => {
    // ビューがマウントされた後に高さを同期
    setTimeout(() => syncCalendarHeights(), 100);
  },
  eventsSet: () => {
    // イベントが読み込まれた後に高さを同期
    setTimeout(() => syncCalendarHeights(), 100);
  },
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

// ユーザー単位カレンダーオプション（選択された日付を表示、日表示）
const userCalendarOptions = computed(() => ({
  plugins: [timeGridPlugin, interactionPlugin],
  initialView: "timeGridDay",
  locale: jaLocale,
  headerToolbar: {
    left: "",
    center: "title",
    right: "",
  },
  height: "auto",
  editable: true,
  selectable: true,
  selectMirror: true,
  weekends: true,
  select: handleUserDateSelect,
  eventClick: handleEventClick,
  eventDrop: handleEventDrop,
  eventResize: handleEventResize,
  eventMouseEnter: handleEventMouseEnter,
  eventMouseLeave: handleEventMouseLeave,
  events: loadUserSchedules,
  slotMinTime: "00:00:00",
  slotMaxTime: "24:00:00",
  slotDuration: "00:30:00",
  viewDidMount: () => {
    // ビューがマウントされた後に高さを同期
    setTimeout(() => syncCalendarHeights(), 100);
  },
  eventsSet: () => {
    // イベントが読み込まれた後に高さを同期
    setTimeout(() => syncCalendarHeights(), 100);
  },
  validRange: () => {
    // 選択された日付を使用
    const selectedDate = new Date(selectedUserDate.value);
    const year = selectedDate.getFullYear();
    const month = selectedDate.getMonth();
    const date = selectedDate.getDate();
    
    // ローカルタイムゾーンで選択された日付を取得
    const start = new Date(year, month, date);
    const end = new Date(year, month, date + 1);
    
    // YYYY-MM-DD形式で返す（タイムゾーンを考慮）
    const startStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
    const endStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date + 1).padStart(2, '0')}`;
    
    return {
      start: startStr,
      end: endStr,
    };
  },
}));

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
        successCallback(response.data);
      }
    })
    .catch((error) => {
      console.error("スケジュールの取得に失敗しました:", error);
      if (failureCallback) {
        failureCallback(error);
      }
    });
}

// ユーザー単位スケジュール読み込み（選択された日付）
function loadUserSchedules(info, successCallback, failureCallback) {
  console.log('[loadUserSchedules] 関数呼び出し');
  console.log('[loadUserSchedules] info:', info);
  console.log('[loadUserSchedules] selectedUserId:', selectedUserId.value);
  console.log('[loadUserSchedules] selectedUserDate:', selectedUserDate.value);
  
  // ユーザーが選択されていない場合は空の配列を返す
  if (!selectedUserId.value) {
    console.warn('[loadUserSchedules] ユーザーが選択されていません。空の配列を返します。');
    if (successCallback) {
      successCallback([]);
    }
    return;
  }

  // 選択された日付の範囲を取得
  const selectedDate = new Date(selectedUserDate.value);
  const year = selectedDate.getFullYear();
  const month = selectedDate.getMonth();
  const date = selectedDate.getDate();
  
  // ローカルタイムゾーンで選択された日付の開始時刻と終了時刻を取得
  const startDate = new Date(year, month, date, 0, 0, 0);
  const endDate = new Date(year, month, date, 23, 59, 59);
  
  // ローカルタイムゾーンの日時文字列を作成（YYYY-MM-DDTHH:mm:ss形式）
  const formatLocalDateTime = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    const h = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    const s = String(date.getSeconds()).padStart(2, '0');
    return `${y}-${m}-${d}T${h}:${min}:${s}`;
  };
  
  const dateStart = formatLocalDateTime(startDate);
  const dateEnd = formatLocalDateTime(endDate);
  
  const params = {
    start: dateStart,
    end: dateEnd,
    mode: 'user',
    user_id: selectedUserId.value,
  };

  console.log('[loadUserSchedules] リクエストパラメータ:', params);
  console.log('[loadUserSchedules] リクエストURL:', route("admin.schedules.index"));

  axios
    .get(route("admin.schedules.index"), { params })
    .then((response) => {
      console.log('[loadUserSchedules] レスポンス取得成功:', response.data);
      console.log('[loadUserSchedules] スケジュール数:', response.data?.length || 0);
      if (successCallback) {
        successCallback(response.data);
      }
    })
    .catch((error) => {
      console.error("[loadUserSchedules] スケジュールの取得に失敗しました:", error);
      console.error("[loadUserSchedules] エラー詳細:", error.response?.data || error.message);
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
  processing: false,
});
const editScheduleForm = ref({
  title: '',
  description: '',
  start_at: '',
  end_at: '',
  all_day: false,
  color: '#3788d8',
  participant_ids: [],
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
  };
  
  console.log('更新データ:', updateData);
  console.log('更新URL:', route('admin.schedules.update', selectedScheduleDetail.value.id));
  
  axios.put(route('admin.schedules.update', selectedScheduleDetail.value.id), updateData)
    .then((response) => {
      console.log('更新成功:', response);
      showEditModal.value = false;
      isEditingSchedule.value = false;
      addedParticipantsForEdit.value = [];
      selectedShopIdForEdit.value = '';
      shopUsersForEdit.value = [];
      shopCalendar.value.getApi().refetchEvents();
      userCalendar.value.getApi().refetchEvents();
      setTimeout(() => {
        syncCalendarHeights();
      }, 300);
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
function onShopChange() {
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
    }, 300);
    // 念のため、もう一度同期（レンダリング完了を待つ）
    setTimeout(() => {
      syncCalendarHeights();
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
</style>
