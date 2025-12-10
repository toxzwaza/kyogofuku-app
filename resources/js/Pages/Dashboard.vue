<template>
    <Head title="ダッシュボード" />

    <AuthenticatedLayout>
        <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        ダッシュボード
      </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
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
              <!-- 店舗単位カレンダー（60%幅） -->
              <div style="flex: 0 0 60%; width: 60%;" class="flex flex-col">
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
                <div class="flex-1">
                  <FullCalendar ref="shopCalendar" :options="shopCalendarOptions" />
                </div>
              </div>

              <!-- ユーザー単位カレンダー（40%幅） -->
              <div style="flex: 0 0 40%; width: 40%;" class="flex flex-col">
                <div class="mb-4">
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
                <div class="flex-1">
                  <FullCalendar ref="userCalendar" :options="userCalendarOptions" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- スケジュール詳細モーダル -->
        <div
          v-if="showScheduleDetail"
          class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
          @click.self="showScheduleDetail = false"
        >
          <div
            class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white"
          >
            <div class="mt-3">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                  スケジュール詳細
                </h3>
                <button
                  @click="showScheduleDetail = false"
                  class="text-gray-400 hover:text-gray-600"
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

              <div v-if="selectedScheduleDetail" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1"
                    >タイトル</label
                  >
                  <p class="text-sm text-gray-900">
                    {{ selectedScheduleDetail.title }}
                  </p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1"
                    >作成者</label
                  >
                  <p class="text-sm text-gray-900">
                    {{ selectedScheduleDetail.user?.name || "-" }}
                  </p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1"
                    >参加者</label
                  >
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
                      class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full"
                    >
                      {{ participant.name }}
                    </span>
                  </div>
                  <p v-else class="text-sm text-gray-500">参加者なし</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1"
                    >開始日時</label
                  >
                  <p class="text-sm text-gray-900">
                    {{ formatDateTime(selectedScheduleDetail.start) }}
                  </p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1"
                    >終了日時</label
                  >
                  <p class="text-sm text-gray-900">
                    {{ formatDateTime(selectedScheduleDetail.end) }}
                  </p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1"
                    >終日</label
                  >
                  <p class="text-sm text-gray-900">
                    {{ selectedScheduleDetail.allDay ? "終日" : "時間指定" }}
                  </p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1"
                    >色</label
                  >
                  <div class="flex items-center space-x-2">
                    <div
                      class="w-8 h-8 rounded"
                      :style="{ backgroundColor: selectedScheduleDetail.color }"
                    ></div>
                    <span class="text-sm text-gray-900">{{
                      selectedScheduleDetail.color
                    }}</span>
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1"
                    >説明</label
                  >
                  <p class="text-sm text-gray-900 whitespace-pre-wrap">
                    {{ selectedScheduleDetail.description || "-" }}
                  </p>
                </div>

                <div class="flex justify-end space-x-2 pt-4">
                  <button
                    v-if="canEditSchedule(selectedScheduleDetail)"
                    @click="startEditSchedule"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                  >
                    編集
                  </button>
                  <button
                    v-if="canEditSchedule(selectedScheduleDetail)"
                    @click="deleteScheduleFromDashboard"
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                  >
                    削除
                  </button>
                  <Link
                    :href="route('admin.schedules.show')"
                    class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600"
                  >
                    詳細管理へ
                  </Link>
                  <button
                    @click="showScheduleDetail = false"
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                  >
                    閉じる
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- スケジュール作成モーダル -->
        <div
          v-if="showCreateModal"
          class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
          @click.self="showCreateModal = false"
        >
          <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">新規スケジュール作成</h3>
                <button
                  @click="showCreateModal = false"
                  class="text-gray-400 hover:text-gray-600"
                >
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <form @submit.prevent="createScheduleFromDashboard" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">タイトル <span class="text-red-500">*</span></label>
                  <input
                    v-model="createScheduleForm.title"
                    type="text"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">開始日時 <span class="text-red-500">*</span></label>
                  <input
                    v-model="createScheduleForm.start_at"
                    type="datetime-local"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">終了日時 <span class="text-red-500">*</span></label>
                  <input
                    v-model="createScheduleForm.end_at"
                    type="datetime-local"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label class="flex items-center">
                    <input
                      v-model="createScheduleForm.all_day"
                      type="checkbox"
                      class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <span class="ml-2 text-sm text-gray-700">終日</span>
                  </label>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">色</label>
                  <input
                    v-model="createScheduleForm.color"
                    type="color"
                    class="w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">参加者</label>
                  
                  <!-- 店舗選択（参加者追加用） -->
                  <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">店舗を選択して参加者を追加</label>
                    <select
                      v-model="selectedShopIdForCreate"
                      @change="loadShopUsersForCreate(selectedShopIdForCreate)"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                        class="inline-flex items-center px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-full"
                      >
                        {{ participant.name }}
                        <button
                          type="button"
                          @click="removeParticipantForCreate(participant.id)"
                          class="ml-2 text-blue-600 hover:text-blue-800"
                        >
                          ×
                        </button>
                      </span>
                    </div>
                  </div>

                  <!-- 店舗ユーザー一覧（チェックボックス） -->
                  <div v-if="shopUsersForCreate.length > 0" class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-md p-3">
                    <label
                      v-for="user in shopUsersForCreate"
                      :key="user.id"
                      class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded"
                    >
                      <input
                        type="checkbox"
                        :value="user.id"
                        :checked="isParticipantAddedForCreate(user.id)"
                        @change="toggleParticipantForCreate(user.id, $event.target.checked)"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      />
                      <span class="text-sm text-gray-900">{{ user.name }}</span>
                    </label>
                  </div>
                  <p v-else-if="!selectedShopIdForCreate" class="text-sm text-gray-500">店舗を選択すると、その店舗に所属するユーザーが表示されます</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                  <textarea
                    v-model="createScheduleForm.description"
                    rows="3"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  ></textarea>
                </div>

                <div class="flex justify-end space-x-2 pt-4">
                  <button
                    type="button"
                    @click="showCreateModal = false"
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                  >
                    キャンセル
                  </button>
                  <button
                    type="submit"
                    :disabled="createScheduleForm.processing"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                  >
                    {{ createScheduleForm.processing ? '作成中...' : '作成' }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- スケジュール編集モーダル -->
        <div
          v-if="showEditModal"
          class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
          @click.self="showEditModal = false"
        >
          <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">スケジュール編集</h3>
                <button
                  @click="showEditModal = false"
                  class="text-gray-400 hover:text-gray-600"
                >
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <form @submit.prevent="updateScheduleFromDashboard" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">タイトル <span class="text-red-500">*</span></label>
                  <input
                    v-model="editScheduleForm.title"
                    type="text"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">開始日時 <span class="text-red-500">*</span></label>
                  <input
                    v-model="editScheduleForm.start_at"
                    type="datetime-local"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">終了日時 <span class="text-red-500">*</span></label>
                  <input
                    v-model="editScheduleForm.end_at"
                    type="datetime-local"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label class="flex items-center">
                    <input
                      v-model="editScheduleForm.all_day"
                      type="checkbox"
                      class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <span class="ml-2 text-sm text-gray-700">終日</span>
                  </label>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">色</label>
                  <input
                    v-model="editScheduleForm.color"
                    type="color"
                    class="w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">参加者</label>
                  
                  <!-- 店舗選択（参加者追加用） -->
                  <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">店舗を選択して参加者を追加</label>
                    <select
                      v-model="selectedShopIdForEdit"
                      @change="loadShopUsersForEdit(selectedShopIdForEdit)"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                        class="inline-flex items-center px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-full"
                      >
                        {{ participant.name }}
                        <button
                          type="button"
                          @click="removeParticipantForEdit(participant.id)"
                          class="ml-2 text-blue-600 hover:text-blue-800"
                        >
                          ×
                        </button>
                      </span>
                    </div>
                  </div>

                  <!-- 店舗ユーザー一覧（チェックボックス） -->
                  <div v-if="shopUsersForEdit.length > 0" class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-md p-3">
                    <label
                      v-for="user in shopUsersForEdit"
                      :key="user.id"
                      class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded"
                    >
                      <input
                        type="checkbox"
                        :value="user.id"
                        :checked="isParticipantAddedForEdit(user.id)"
                        @change="toggleParticipantForEdit(user.id, $event.target.checked)"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      />
                      <span class="text-sm text-gray-900">{{ user.name }}</span>
                    </label>
                  </div>
                  <p v-else-if="!selectedShopIdForEdit" class="text-sm text-gray-500">店舗を選択すると、その店舗に所属するユーザーが表示されます</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                  <textarea
                    v-model="editScheduleForm.description"
                    rows="3"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  ></textarea>
                </div>

                <div class="flex justify-end space-x-2 pt-4">
                  <button
                    type="button"
                    @click="showEditModal = false"
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                  >
                    キャンセル
                  </button>
                  <button
                    type="submit"
                    :disabled="editScheduleForm.processing"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                  >
                    {{ editScheduleForm.processing ? '更新中...' : '更新' }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

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
import { computed, ref, onMounted } from "vue";
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
const shops = computed(() => props.shops || []);
const userShops = computed(() => props.userShops || []);
const users = computed(() => props.users || []);

// デフォルト値を設定
onMounted(() => {
  console.log('[Dashboard] onMounted開始');
  console.log('[Dashboard] currentUser:', props.currentUser);
  console.log('[Dashboard] userShops:', userShops.value);
  console.log('[Dashboard] users:', users.value);
  
  // 店舗単位：ログインユーザーの所属店舗の最初の店舗を選択
  if (userShops.value.length > 0) {
    selectedShopId.value = userShops.value[0].id;
    selectedShopIdForCreate.value = userShops.value[0].id;
    loadShopUsersForCreate(userShops.value[0].id);
    console.log('[Dashboard] 店舗ID設定:', selectedShopId.value);
  }
  
  // ユーザー単位：ログインユーザーを選択
  if (props.currentUser) {
    selectedUserId.value = props.currentUser.id;
    console.log('[Dashboard] ユーザーID設定:', selectedUserId.value);
  } else {
    console.warn('[Dashboard] currentUserが存在しません');
  }
  
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

// カレンダーの高さを同期する関数
function syncCalendarHeights() {
  if (shopCalendar.value && userCalendar.value) {
    const shopCalendarEl = shopCalendar.value.getApi().el;
    const userCalendarEl = userCalendar.value.getApi().el;
    
    if (shopCalendarEl && userCalendarEl) {
      const shopHeight = shopCalendarEl.offsetHeight;
      if (shopHeight > 0) {
        userCalendar.value.getApi().setOption('height', shopHeight);
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

// ユーザー単位カレンダーオプション（今日のみ表示で固定、日表示）
const userCalendarOptions = ref({
  plugins: [timeGridPlugin],
  initialView: "timeGridDay",
  locale: jaLocale,
  headerToolbar: {
    left: "",
    center: "title",
    right: "",
  },
  height: "auto",
  editable: false,
  selectable: false,
  weekends: true,
  eventClick: handleEventClick,
  events: loadUserSchedules,
  slotMinTime: "00:00:00",
  slotMaxTime: "24:00:00",
  slotDuration: "00:30:00",
  validRange: (nowDate) => {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth();
    const date = today.getDate();
    
    // ローカルタイムゾーンで今日の日付を取得
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

// ユーザー単位スケジュール読み込み（今日のみ）
function loadUserSchedules(info, successCallback, failureCallback) {
  console.log('[loadUserSchedules] 関数呼び出し');
  console.log('[loadUserSchedules] info:', info);
  console.log('[loadUserSchedules] selectedUserId:', selectedUserId.value);
  
  // ユーザーが選択されていない場合は空の配列を返す
  if (!selectedUserId.value) {
    console.warn('[loadUserSchedules] ユーザーが選択されていません。空の配列を返します。');
    if (successCallback) {
      successCallback([]);
    }
    return;
  }

  // 今日の日付範囲を取得
  const today = new Date();
  const year = today.getFullYear();
  const month = today.getMonth();
  const date = today.getDate();
  
  // ローカルタイムゾーンで今日の開始時刻と終了時刻を取得
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
  
  const todayStart = formatLocalDateTime(startDate);
  const todayEnd = formatLocalDateTime(endDate);
  
  const params = {
    start: todayStart,
    end: todayEnd,
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
  
  createScheduleForm.start_at = formatDateTimeLocal(startDate);
  createScheduleForm.end_at = formatDateTimeLocal(endDate);
  createScheduleForm.all_day = selectInfo.allDay;
  showCreateModal.value = true;
  shopCalendar.value.getApi().unselect();
}

const showScheduleDetail = ref(false);
const selectedScheduleDetail = ref(null);
const showEditModal = ref(false);
const showCreateModal = ref(false);
const isEditingSchedule = ref(false);
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
  if (!selectedScheduleDetail.value) return;
  
  editScheduleForm.value.processing = true;
  
  const updateData = {
    ...editScheduleForm.value,
    user_id: selectedScheduleDetail.value.user?.id,
  };
  delete updateData.processing;
  
  axios.put(route('admin.schedules.update', selectedScheduleDetail.value.id), updateData)
    .then(() => {
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
      alert('スケジュールの更新に失敗しました。');
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
  
  const scheduleData = {
    title: dropInfo.event.title,
    start_at: dropInfo.event.startStr,
    end_at: dropInfo.event.endStr,
    all_day: dropInfo.event.allDay,
    user_id: event.extendedProps.user?.id,
  };

  axios
    .put(route("admin.schedules.update", dropInfo.event.id), scheduleData)
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
  
  const scheduleData = {
    title: resizeInfo.event.title,
    start_at: resizeInfo.event.startStr,
    end_at: resizeInfo.event.endStr,
    all_day: resizeInfo.event.allDay,
    user_id: event.extendedProps.user?.id,
  };

  axios
    .put(route("admin.schedules.update", resizeInfo.event.id), scheduleData)
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
    // 高さを同期
    setTimeout(() => {
      syncCalendarHeights();
    }, 300);
  }
}

// ユーザー変更時の処理
function onUserChange() {
  console.log('[onUserChange] ユーザー変更:', selectedUserId.value);
  if (userCalendar.value && selectedUserId.value) {
    console.log('[onUserChange] カレンダーをリフレッシュ');
    userCalendar.value.getApi().refetchEvents();
    // 高さを同期
    setTimeout(() => {
      syncCalendarHeights();
    }, 300);
  } else {
    console.warn('[onUserChange] カレンダーのリフレッシュをスキップ:', {
      userCalendar: !!userCalendar.value,
      selectedUserId: selectedUserId.value
    });
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
}
</style>
