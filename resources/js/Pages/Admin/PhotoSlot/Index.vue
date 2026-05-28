    <template>
  <Head title="前撮り管理" />

  <AdminLayout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-brand-text leading-tight">
          前撮り管理
        </h2>
        <ActionButton
          variant="create"
          label="新規追加"
          :href="route('admin.photo-slots.create')"
        />
      </div>
    </template>

    <div
      v-if="$page.props.success"
      class="mb-4 p-3 rounded bg-green-100 text-green-800 border border-green-200"
    >
      {{ $page.props.success }}
    </div>

    <div
      v-if="$page.props.slotErrors && $page.props.slotErrors.length > 0"
      class="mb-4 p-3 rounded bg-yellow-100 text-yellow-800 border border-yellow-200"
    >
      <ul class="list-disc list-inside">
        <li v-for="(error, index) in $page.props.slotErrors" :key="index">
          {{ error }}
        </li>
      </ul>
    </div>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- 絞り込みフォーム -->
        <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold text-brand-text">検索条件</h3>
              <button
                @click="resetFilters"
                class="text-sm text-brand-text-muted hover:text-brand-text"
              >
                リセット
              </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label class="block text-xs font-medium text-brand-text mb-1"
                  >店舗</label
                >
                <select
                  v-model="filters.shop_id"
                  class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                >
                  <option value="">全て</option>
                  <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                    {{ shop.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-brand-text mb-1"
                  >スタジオ</label
                >
                <select
                  v-model="filters.studio_id"
                  class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                >
                  <option value="">全て</option>
                  <option
                    v-for="studio in photoStudios"
                    :key="studio.id"
                    :value="studio.id"
                  >
                    {{ studio.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-brand-text mb-1"
                  >予約状況</label
                >
                <select
                  v-model="filters.reservation_status"
                  class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                >
                  <option value="">全て</option>
                  <option value="reserved">予約済み</option>
                  <option value="available">空き</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-brand-text mb-1"
                  >日付範囲（開始）</label
                >
                <input
                  v-model="filters.start_date"
                  type="date"
                  class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-brand-text mb-1"
                  >日付範囲（終了）</label
                >
                <input
                  v-model="filters.end_date"
                  type="date"
                  class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- 年・月タブ -->
        <div v-if="availableYears.length > 0" class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg mb-6 divide-y divide-brand-border">
          <!-- 年タブ -->
          <div class="px-6 py-3 flex items-center gap-2 flex-wrap">
            <span class="text-xs text-brand-text-muted font-medium mr-1">年で絞込:</span>
            <button
              @click="clearYears"
              :class="selectedYears.size === 0
                ? 'bg-brand-primary text-brand-on-primary shadow-soft-sm'
                : 'bg-brand-surface-2 text-brand-text-muted hover:bg-sumi-100 hover:text-brand-text'"
              class="px-3 py-1 text-sm rounded-soft font-medium transition-all"
            >
              すべて
              <span class="ml-1 text-xs opacity-80 tabular-nums">{{ totalCount }}</span>
            </button>
            <button
              v-for="year in availableYears"
              :key="year"
              @click="toggleYear(year)"
              :class="selectedYears.has(year)
                ? 'bg-brand-primary text-brand-on-primary shadow-soft-sm'
                : 'bg-brand-surface-2 text-brand-text-muted hover:bg-sumi-100 hover:text-brand-text'"
              class="px-3 py-1 text-sm rounded-soft tabular-nums font-medium transition-all"
            >
              {{ year }}年
              <span class="ml-1 text-xs opacity-80 tabular-nums">{{ countByYear(year) }}</span>
            </button>
          </div>

          <!-- 月タブ -->
          <div v-if="availableMonths.length > 0" class="px-6 py-3 flex items-center gap-2 flex-wrap">
            <span class="text-xs text-brand-text-muted font-medium mr-1">月で絞込:</span>
            <button
              @click="clearMonths"
              :class="selectedMonths.size === 0
                ? 'bg-brand-primary text-brand-on-primary shadow-soft-sm'
                : 'bg-brand-surface-2 text-brand-text-muted hover:bg-sumi-100 hover:text-brand-text'"
              class="px-3 py-1 text-sm rounded-soft font-medium transition-all"
            >
              すべて
            </button>
            <button
              v-for="month in availableMonths"
              :key="month"
              @click="toggleMonth(month)"
              :class="selectedMonths.has(month)
                ? 'bg-brand-primary text-brand-on-primary shadow-soft-sm'
                : 'bg-brand-surface-2 text-brand-text-muted hover:bg-sumi-100 hover:text-brand-text'"
              class="px-3 py-1 text-sm rounded-soft tabular-nums font-medium transition-all"
            >
              {{ formatMonth(month) }}
              <span class="ml-1 text-xs opacity-80 tabular-nums">{{ countByMonth(month) }}</span>
            </button>
          </div>
        </div>

        <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <!-- 日付ごとにグルーピング表示（アコーディオン） -->
            <div
              v-if="
                filteredGroupedSlots &&
                Object.keys(filteredGroupedSlots).length > 0
              "
              class="space-y-2"
            >
              <div
                v-for="(dateGroup, date) in filteredGroupedSlots"
                :key="date"
                class="border border-brand-border rounded-lg overflow-hidden"
              >
                <!-- 日付ヘッダー（クリック可能） -->
                <div
                  class="w-full bg-brand-surface-2 hover:bg-gray-200 px-4 py-3 flex items-center justify-between transition-colors"
                >
                  <button
                    @click="toggleDate(date)"
                    class="flex-1 flex items-center space-x-4"
                  >
                    <svg
                      :class="[
                        'w-5 h-5 text-brand-text-muted transition-transform',
                        expandedDates.has(date) ? 'transform rotate-90' : '',
                      ]"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 5l7 7-7 7"
                      />
                    </svg>
                    <h3 class="text-lg font-semibold text-brand-text">
                      {{ formatDateJaWithWeekday(date) }}
                    </h3>
                    <span class="text-sm text-brand-text-muted"
                      >（{{ dateGroup.length }}件）</span
                    >
                  </button>
                  <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2 flex-wrap">
                      <span
                        v-for="shop in getShopsForDate(dateGroup)"
                        :key="shop.id"
                        class="px-2 py-1 text-xs font-medium rounded-full bg-indigo-100 text-indigo-800"
                      >
                        {{ shop.name }}
                      </span>
                    </div>
                    <div
                      class="flex items-center space-x-4 text-sm text-brand-text-muted"
                    >
                      <span>予約済み: {{ getReservedCount(dateGroup) }}件</span>
                      <span>空き: {{ getAvailableCount(dateGroup) }}件</span>
                    </div>
                    <button
                      @click.stop="openDateEditModal(date, dateGroup)"
                      class="ml-4 px-3 py-1 bg-brand-primary text-white text-sm rounded-md hover:bg-brand-primary-hover flex items-center gap-1"
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
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                        />
                      </svg>
                      編集
                    </button>
                    <button
                      v-if="!hasScheduleForDate(date, dateGroup)"
                      @click.stop="createScheduleForDate(date, dateGroup)"
                      class="ml-2 px-3 py-1 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 flex items-center gap-1"
                      :disabled="creatingSchedule"
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
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                      </svg>
                      登録
                    </button>
                    <span
                      v-else
                      class="ml-2 px-3 py-1 bg-gray-200 text-brand-text-muted text-sm rounded-md flex items-center gap-1"
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
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                      </svg>
                      登録済
                    </span>
                  </div>
                </div>

                <!-- 展開された内容（店舗別データテーブル） -->
                <div
                  v-if="expandedDates.has(date)"
                  class="bg-brand-surface"
                >
                  <div
                    v-for="(shopGroup, sgIdx) in groupSlotsByShop(dateGroup)"
                    :key="shopGroup.shop_id"
                    :class="sgIdx > 0 ? 'border-t-4 border-brand-bg' : ''"
                  >
                    <!-- 店舗ヘッダー -->
                    <div class="px-4 py-2 flex items-center gap-3 bg-brand-surface-2 border-b border-brand-border">
                      <span
                        class="px-2.5 py-0.5 rounded-soft-sm text-xs font-bold tracking-wide"
                        :class="getShopBadgeClass(shopGroup.shop_name)"
                      >
                        {{ shopGroup.shop_name }}
                      </span>
                      <span class="text-xs text-brand-text-muted tabular-nums">
                        {{ shopGroup.slots.length }} 枠
                      </span>
                      <span class="flex items-center gap-2 text-xs">
                        <span class="inline-flex items-center gap-1 text-uguisu-700">
                          <span class="w-2 h-2 bg-uguisu-500 rounded-full"></span>
                          予約 <span class="tabular-nums font-semibold">{{ countReserved(shopGroup.slots) }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1 text-brand-text-muted">
                          <span class="w-2 h-2 bg-sumi-300 rounded-full"></span>
                          空き <span class="tabular-nums font-semibold">{{ countAvailable(shopGroup.slots) }}</span>
                        </span>
                      </span>
                    </div>

                    <!-- データテーブル -->
                    <div class="overflow-x-auto">
                      <table class="w-full text-sm">
                        <thead class="text-xs text-brand-text-muted bg-brand-surface">
                          <tr class="border-b border-brand-border">
                            <th class="w-1 p-0"></th>
                            <th class="px-2 py-2 text-left font-medium tabular-nums">時刻</th>
                            <th class="px-2 py-2 text-left font-medium">スタジオ</th>
                            <th class="px-2 py-2 text-left font-medium">氏名</th>
                            <th class="px-2 py-2 text-left font-medium">保護者</th>
                            <th class="px-2 py-2 text-left font-medium">電話</th>
                            <th class="px-2 py-2 text-left font-medium">住所</th>
                            <th class="px-2 py-2 text-left font-medium">担当・備考</th>
                            <th class="px-2 py-2 text-right font-medium">操作</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr
                            v-for="slot in shopGroup.slots"
                            :key="slot.id"
                            class="border-b border-brand-border/60 hover:bg-ai-50/50 transition-colors group"
                          >
                            <!-- 状態色のレフトバー -->
                            <td class="p-0" :class="slot.customer ? 'bg-uguisu-500' : 'bg-sumi-200'"></td>

                            <!-- 時刻 -->
                            <td class="px-2 py-2 tabular-nums font-semibold text-brand-text whitespace-nowrap">
                              {{ formatTime(slot.shoot_time) }}
                            </td>

                            <!-- スタジオ -->
                            <td class="px-2 py-2 text-brand-text whitespace-nowrap">
                              {{ slot.studio?.name?.trim() || '—' }}
                            </td>

                            <!-- 氏名 -->
                            <td class="px-2 py-2">
                              <template v-if="slot.customer">
                                <div class="font-semibold text-brand-text leading-tight">
                                  {{ slot.customer.name }}
                                </div>
                                <div v-if="slot.customer.kana" class="text-xs text-brand-text-muted leading-tight">
                                  {{ slot.customer.kana }}
                                </div>
                              </template>
                              <span v-else class="text-brand-text-subtle italic">未予約</span>
                            </td>

                            <!-- 保護者 -->
                            <td class="px-2 py-2 text-brand-text">
                              {{ slot.customer?.guardian_name || '—' }}
                            </td>

                            <!-- 電話 -->
                            <td class="px-2 py-2 tabular-nums whitespace-nowrap">
                              <a
                                v-if="slot.customer?.phone_number"
                                :href="'tel:' + slot.customer.phone_number"
                                class="text-brand-primary hover:underline"
                              >{{ slot.customer.phone_number }}</a>
                              <span v-else class="text-brand-text-subtle">—</span>
                            </td>

                            <!-- 住所 -->
                            <td class="px-2 py-2 text-brand-text max-w-xs">
                              <template v-if="slot.customer?.postal_code || slot.customer?.address">
                                <div v-if="slot.customer?.postal_code" class="text-xs text-brand-text-muted tabular-nums">
                                  〒{{ slot.customer.postal_code }}
                                </div>
                                <div v-if="slot.customer?.address" class="truncate" :title="slot.customer.address">
                                  {{ slot.customer.address }}
                                </div>
                              </template>
                              <span v-else class="text-brand-text-subtle">—</span>
                            </td>

                            <!-- 担当・備考 -->
                            <td class="px-2 py-2 text-xs text-brand-text-muted max-w-xs">
                              <div v-if="slot.user" class="truncate">担当: {{ slot.user.name }}</div>
                              <div v-if="slot.plan" class="truncate">プラン: {{ slot.plan.name }}</div>
                              <div v-if="slot.assignment_label" class="truncate text-brand-text">{{ slot.assignment_label }}</div>
                              <div v-if="slot.remarks" class="truncate italic" :title="slot.remarks">{{ slot.remarks }}</div>
                              <span v-if="!slot.user && !slot.plan && !slot.assignment_label && !slot.remarks" class="text-brand-text-subtle">—</span>
                            </td>

                            <!-- 操作 -->
                            <td class="px-2 py-2 text-right whitespace-nowrap">
                              <div class="inline-flex items-center gap-1 opacity-50 group-hover:opacity-100 transition-opacity">
                                <a
                                  v-if="slot.customer"
                                  :href="route('admin.customers.show', slot.customer.id)"
                                  class="p-1.5 rounded-soft-sm hover:bg-ai-100 text-brand-primary"
                                  title="顧客詳細を開く"
                                >
                                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </a>
                                <button
                                  v-if="slot.customer"
                                  @click="openEditModal(slot)"
                                  class="p-1.5 rounded-soft-sm hover:bg-sumi-100 text-brand-text-muted"
                                  title="編集"
                                >
                                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button
                                  v-if="slot.customer"
                                  @click="confirmRelease(slot)"
                                  class="p-1.5 rounded-soft-sm hover:bg-natane-100 text-natane-700"
                                  title="顧客の紐付けを解除"
                                >
                                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                </button>
                                <button
                                  v-else
                                  @click="confirmDelete(slot)"
                                  class="p-1.5 rounded-soft-sm hover:bg-akane-100 text-akane-600"
                                  title="前撮り枠を削除"
                                >
                                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                              </div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-12 text-brand-text-muted">
              条件に一致する前撮り枠がありません
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 編集モーダル -->
    <transition name="modal">
      <div
        v-if="showEditModal"
        class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
        @click.self="showEditModal = false"
      >
        <div
          class="relative bg-brand-surface rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
        >
          <!-- ヘッダー -->
          <div
            class="flex items-center justify-between px-6 py-4 border-b border-brand-border bg-gradient-to-r from-indigo-50 to-purple-50"
          >
            <h3 class="text-xl font-bold text-brand-text flex items-center gap-2">
              <svg
                class="w-6 h-6 text-brand-primary"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
              </svg>
              前撮り枠編集
            </h3>
            <button
              @click="showEditModal = false"
              class="text-brand-text-subtle hover:text-brand-text-muted hover:bg-brand-surface-2 rounded-full p-1 transition-colors"
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
          <form
            @submit.prevent="updatePhotoSlot"
            class="overflow-y-auto flex-1 px-6 py-5"
          >
            <div class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                  <label
                    class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2"
                  >
                    担当店舗
                  </label>
                  <select
                    v-model="editForm.shop_id"
                    @change="onEditShopChange"
                    class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                  >
                    <option value="">選択してください</option>
                    <option
                      v-for="shop in shops"
                      :key="shop.id"
                      :value="shop.id"
                    >
                      {{ shop.name }}
                    </option>
                  </select>
                  <div
                    v-if="editForm.errors.shop_id"
                    class="mt-1 text-sm text-red-600"
                  >
                    {{ editForm.errors.shop_id }}
                  </div>
                </div>
                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                  <label
                    class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2"
                  >
                    会場 <span class="text-red-500">*</span>
                  </label>
                  <select
                    v-model="editForm.selected_studio_id"
                    required
                    :disabled="!editForm.shop_id"
                    @change="onEditStudioChange"
                    class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm disabled:bg-brand-surface-2 disabled:cursor-not-allowed"
                  >
                    <option value="">選択してください</option>
                    <option
                      v-for="studio in availableEditStudios"
                      :key="studio.id"
                      :value="studio.id"
                    >
                      {{ studio.name }}
                    </option>
                  </select>
                  <p
                    v-if="!editForm.shop_id"
                    class="mt-1 text-xs text-brand-text-muted"
                  >
                    まず担当店舗を選択してください
                  </p>
                  <div
                    v-if="editForm.errors.selected_studio_id"
                    class="mt-1 text-sm text-red-600"
                  >
                    {{ editForm.errors.selected_studio_id }}
                  </div>
                </div>
                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                  <label
                    class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2"
                  >
                    撮影日 <span class="text-red-500">*</span>
                  </label>
                  <select
                    v-model="editForm.selected_date"
                    required
                    :disabled="!editForm.selected_studio_id"
                    @change="onEditDateChange"
                    class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm disabled:bg-brand-surface-2 disabled:cursor-not-allowed"
                  >
                    <option value="">選択してください</option>
                    <option
                      v-for="date in availableEditDates"
                      :key="date"
                      :value="date"
                    >
                      {{ formatDateJa(date) }}
                    </option>
                  </select>
                  <p
                    v-if="!editForm.selected_studio_id"
                    class="mt-1 text-xs text-brand-text-muted"
                  >
                    まず担当店舗と会場を選択してください
                  </p>
                </div>
                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                  <label
                    class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2"
                  >
                    撮影時間 <span class="text-red-500">*</span>
                  </label>
                  <select
                    v-model="editForm.photo_slot_id"
                    required
                    :disabled="!editForm.selected_date"
                    @change="onEditPhotoSlotChange"
                    class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm disabled:bg-brand-surface-2 disabled:cursor-not-allowed"
                  >
                    <option value="">選択してください</option>
                    <option
                      v-for="slot in availableEditTimeSlots"
                      :key="slot.id"
                      :value="slot.id"
                    >
                      {{ formatTime(slot.shoot_time) }}
                    </option>
                  </select>
                  <p
                    v-if="!editForm.selected_date"
                    class="mt-1 text-xs text-brand-text-muted"
                  >
                    まず担当店舗、会場、撮影日を選択してください
                  </p>
                  <div
                    v-if="editForm.errors.photo_slot_id"
                    class="mt-1 text-sm text-red-600"
                  >
                    {{ editForm.errors.photo_slot_id }}
                  </div>
                </div>
                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                  <label
                    class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2"
                  >
                    担当者用メモラベル
                  </label>
                  <select
                    v-model="editForm.assignment_label"
                    class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                  >
                    <option :value="null">選択してください</option>
                    <option value="動員">動員</option>
                    <option value="岡山店 / F">岡山店 / F</option>
                    <option value="城東店 / F">城東店 / F</option>
                    <option value="引継ぎ / F">引継ぎ / F</option>
                    <option value="EXPO / F">EXPO / F</option>
                  </select>
                </div>
                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                  <label
                    class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2"
                  >
                    担当者
                  </label>
                  <select
                    v-model="editForm.user_id"
                    :disabled="!editForm.shop_id"
                    class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm disabled:bg-brand-surface-2 disabled:cursor-not-allowed"
                  >
                    <option :value="null">選択してください</option>
                    <option
                      v-for="user in editShopUsers"
                      :key="user.id"
                      :value="user.id"
                    >
                      {{ user.name }}
                    </option>
                  </select>
                  <p
                    v-if="!editForm.shop_id"
                    class="mt-1 text-xs text-brand-text-muted"
                  >
                    まず担当店舗を選択してください
                  </p>
                </div>
                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                  <label
                    class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2"
                  >
                    プラン
                  </label>
                  <select
                    v-model="editForm.plan_id"
                    class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                  >
                    <option :value="null">選択してください</option>
                    <option
                      v-for="plan in plans"
                      :key="plan.id"
                      :value="plan.id"
                    >
                      {{ plan.name }}
                    </option>
                  </select>
                </div>
                <div
                  class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border md:col-span-2"
                >
                  <label
                    class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2"
                  >
                    備考
                  </label>
                  <textarea
                    v-model="editForm.remarks"
                    rows="4"
                    class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                  ></textarea>
                </div>
              </div>
            </div>

            <!-- エラーメッセージ表示 -->
            <div
              v-if="Object.keys(editForm.errors).length > 0"
              class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg"
            >
              <div class="text-sm text-red-800">
                <p class="font-semibold mb-2">以下のエラーが発生しました：</p>
                <ul class="list-disc list-inside space-y-1">
                  <li v-for="(error, key) in editForm.errors" :key="key">
                    {{ error }}
                  </li>
                </ul>
              </div>
            </div>

            <!-- フッター -->
            <div
              class="flex justify-end space-x-3 mt-6 pt-4 border-t border-brand-border"
            >
              <button
                type="button"
                @click="showEditModal = false"
                class="px-4 py-2 border border-brand-border rounded-md text-sm font-medium text-brand-text hover:bg-brand-surface-2"
              >
                キャンセル
              </button>
              <button
                type="submit"
                :disabled="editForm.processing"
                class="px-4 py-2 bg-brand-primary text-white rounded-md text-sm font-medium hover:bg-brand-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="editForm.processing">更新中...</span>
                <span v-else>更新</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </transition>

    <!-- 削除確認モーダル -->
    <transition name="modal">
      <div
        v-if="showDeleteModal"
        class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
        @click.self="showDeleteModal = false"
      >
        <div
          class="relative bg-brand-surface rounded-xl shadow-2xl w-full max-w-md overflow-hidden"
        >
          <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/>
                </svg>
              </div>
              <h3 class="text-lg font-bold text-brand-text">前撮り枠を削除</h3>
            </div>
            <p class="text-sm text-brand-text-muted mb-2">
              この前撮り枠を削除してもよろしいですか？
            </p>
            <p class="text-xs text-red-600 mb-6">
              ※ Googleカレンダーのイベントも削除されます。この操作は取り消せません。
            </p>
            <div class="flex justify-end space-x-3">
              <button
                @click="showDeleteModal = false"
                class="px-4 py-2 border border-brand-border rounded-md text-sm font-medium text-brand-text hover:bg-brand-surface-2"
              >
                キャンセル
              </button>
              <button
                @click="deletePhotoSlot"
                class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700"
              >
                削除する
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- 解除確認モーダル -->
    <transition name="modal">
      <div
        v-if="showReleaseModal"
        class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
        @click.self="showReleaseModal = false"
      >
        <div
          class="relative bg-brand-surface rounded-xl shadow-2xl w-full max-w-md overflow-hidden"
        >
          <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
              </div>
              <h3 class="text-lg font-bold text-brand-text">顧客紐付けを解除</h3>
            </div>
            <p class="text-sm text-brand-text-muted mb-2" v-if="selectedSlot?.customer">
              <span class="font-semibold">{{ selectedSlot.customer.name }}</span> さんの紐付けを解除します。
            </p>
            <p class="text-xs text-orange-600 mb-6">
              ※ 枠自体は残ります（未予約状態に戻る）。Googleカレンダーは「未予約」表示・グレーに変わります。<br>
              削除する場合は、解除した後に「削除」ボタンから実行してください。
            </p>
            <div class="flex justify-end space-x-3">
              <button
                @click="showReleaseModal = false"
                class="px-4 py-2 border border-brand-border rounded-md text-sm font-medium text-brand-text hover:bg-brand-surface-2"
              >
                キャンセル
              </button>
              <button
                @click="releasePhotoSlot"
                class="px-4 py-2 bg-orange-500 text-white rounded-md text-sm font-medium hover:bg-orange-600"
              >
                解除する
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- 日付グループ編集モーダル -->
    <transition name="modal">
      <div
        v-if="showDateEditModal"
        class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
        @click.self="showDateEditModal = false"
      >
        <div
          class="relative bg-brand-surface rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
        >
          <!-- ヘッダー -->
          <div
            class="flex items-center justify-between px-6 py-4 border-b border-brand-border bg-gradient-to-r from-indigo-50 to-purple-50"
          >
            <h3 class="text-xl font-bold text-brand-text flex items-center gap-2">
              <svg
                class="w-6 h-6 text-brand-primary"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
              </svg>
              日付グループ編集
            </h3>
            <button
              @click="showDateEditModal = false"
              class="text-brand-text-subtle hover:text-brand-text-muted hover:bg-brand-surface-2 rounded-full p-1 transition-colors"
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
          <form
            @submit.prevent="updateDateGroup"
            class="overflow-y-auto flex-1 px-6 py-5"
          >
            <div class="space-y-4">
              <div
                class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4"
              >
                <p class="text-sm text-blue-800">
                  <strong>{{ selectedDateGroupSlots.length }}件</strong
                  >の前撮り枠の日付・担当店舗・スタジオを一括変更します。
                </p>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                  <label
                    class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2"
                  >
                    撮影日 <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="dateGroupEditForm.shoot_date"
                    type="date"
                    required
                    class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                  />
                  <div
                    v-if="dateGroupEditForm.errors.shoot_date"
                    class="mt-1 text-sm text-red-600"
                  >
                    {{ dateGroupEditForm.errors.shoot_date }}
                  </div>
                </div>
                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                  <label
                    class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2"
                  >
                    スタジオ <span class="text-red-500">*</span>
                  </label>
                  <select
                    v-model="dateGroupEditForm.photo_studio_id"
                    required
                    class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                  >
                    <option value="">選択してください</option>
                    <option
                      v-for="studio in photoStudios"
                      :key="studio.id"
                      :value="studio.id"
                    >
                      {{ studio.name }}
                    </option>
                  </select>
                  <div
                    v-if="dateGroupEditForm.errors.photo_studio_id"
                    class="mt-1 text-sm text-red-600"
                  >
                    {{ dateGroupEditForm.errors.photo_studio_id }}
                  </div>
                </div>
                <div
                  class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border md:col-span-2"
                >
                  <label
                    class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2"
                  >
                    担当店舗
                  </label>
                  <div
                    class="space-y-2 border border-brand-border rounded-md p-3 bg-brand-surface"
                  >
                    <label
                      v-for="shop in shops"
                      :key="shop.id"
                      class="flex items-center space-x-2 cursor-pointer hover:bg-brand-surface-2 p-2 rounded-md transition-colors"
                    >
                      <input
                        type="checkbox"
                        :value="shop.id"
                        v-model="dateGroupEditForm.shop_ids"
                        class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                      />
                      <span class="text-sm text-brand-text">{{ shop.name }}</span>
                    </label>
                    <p v-if="shops.length === 0" class="text-sm text-brand-text-muted">
                      店舗が登録されていません
                    </p>
                  </div>
                  <div
                    v-if="dateGroupEditForm.errors.shop_ids"
                    class="mt-1 text-sm text-red-600"
                  >
                    {{ dateGroupEditForm.errors.shop_ids }}
                  </div>
                </div>
              </div>
            </div>

            <!-- エラーメッセージ表示 -->
            <div
              v-if="Object.keys(dateGroupEditForm.errors).length > 0"
              class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg"
            >
              <div class="text-sm text-red-800">
                <p class="font-semibold mb-2">以下のエラーが発生しました：</p>
                <ul class="list-disc list-inside space-y-1">
                  <li
                    v-for="(error, key) in dateGroupEditForm.errors"
                    :key="key"
                  >
                    {{ error }}
                  </li>
                </ul>
              </div>
            </div>

            <!-- フッター -->
            <div
              class="flex justify-end space-x-3 mt-6 pt-4 border-t border-brand-border"
            >
              <button
                type="button"
                @click="showDateEditModal = false"
                class="px-4 py-2 border border-brand-border rounded-md text-sm font-medium text-brand-text hover:bg-brand-surface-2"
              >
                キャンセル
              </button>
              <button
                type="submit"
                :disabled="dateGroupEditForm.processing"
                class="px-4 py-2 bg-brand-primary text-white rounded-md text-sm font-medium hover:bg-brand-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="dateGroupEditForm.processing">更新中...</span>
                <span v-else>更新</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </transition>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ActionButton from "@/Components/ActionButton.vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { computed, ref, onMounted } from "vue";
import axios from "axios";
import { formatDateJa, formatDateJaWithWeekday } from "@/utils/dateFormat";

const props = defineProps({
  photoSlots: Array,
  photoStudios: Array,
  shops: Array,
  plans: Array,
  users: Array,
  availablePhotoSlots: Array,
  scheduledSlotIds: Array,
});

const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showReleaseModal = ref(false);
const showDateEditModal = ref(false);
const selectedSlot = ref(null);
const selectedDateGroupSlots = ref([]);
const selectedDateKey = ref("");
const editShopUsers = ref([]);
const expandedDates = ref(new Set());
const creatingSchedule = ref(false);
const creatingSlotSchedule = ref(null); // 個別時間枠のスケジュール作成中フラグ
const dateScheduleStatus = ref(new Map()); // 日付ごとのスケジュール登録状況

// 絞り込み条件
const filters = ref({
  shop_id: "",
  studio_id: "",
  reservation_status: "",
  start_date: "",
  end_date: "",
});

const editForm = useForm({
  photo_slot_id: "",
  shop_id: "",
  selected_studio_id: "",
  selected_date: "",
  assignment_label: null,
  user_id: null,
  plan_id: null,
  remarks: "",
});

// 日付グループ編集フォーム
const dateGroupEditForm = useForm({
  shoot_date: "",
  photo_studio_id: "",
  shop_ids: [],
});

// フィルタリングされた前撮り枠
const filteredSlots = computed(() => {
  if (!props.photoSlots || props.photoSlots.length === 0) return [];

  let filtered = [...props.photoSlots];

  // 店舗でフィルタリング
  if (filters.value.shop_id) {
    const shopId = Number(filters.value.shop_id);
    filtered = filtered.filter((slot) => {
      return slot.shops && slot.shops.some((shop) => shop.id === shopId);
    });
  }

  // スタジオでフィルタリング
  if (filters.value.studio_id) {
    const studioId = Number(filters.value.studio_id);
    filtered = filtered.filter((slot) => slot.studio?.id === studioId);
  }

  // 予約状況でフィルタリング
  if (filters.value.reservation_status === "reserved") {
    filtered = filtered.filter((slot) => slot.customer !== null);
  } else if (filters.value.reservation_status === "available") {
    filtered = filtered.filter((slot) => slot.customer === null);
  }

  // 日付範囲でフィルタリング
  if (filters.value.start_date) {
    filtered = filtered.filter(
      (slot) => slot.shoot_date >= filters.value.start_date
    );
  }
  if (filters.value.end_date) {
    filtered = filtered.filter(
      (slot) => slot.shoot_date <= filters.value.end_date
    );
  }

  // 年タブでフィルタリング（複数選択時はOR）
  if (selectedYears.value.size > 0) {
    filtered = filtered.filter(
      (slot) =>
        slot.shoot_date &&
        selectedYears.value.has(String(slot.shoot_date).substring(0, 4))
    );
  }

  // 月タブでフィルタリング（複数選択時はOR）
  if (selectedMonths.value.size > 0) {
    filtered = filtered.filter(
      (slot) =>
        slot.shoot_date &&
        selectedMonths.value.has(String(slot.shoot_date).substring(0, 7))
    );
  }

  return filtered;
});

// フィルタリングされた前撮り枠を日付ごとにグループ化
const filteredGroupedSlots = computed(() => {
  if (!filteredSlots.value || filteredSlots.value.length === 0) return {};
  const groups = {};
  filteredSlots.value.forEach((slot) => {
    const date = new Date(slot.shoot_date);
    const dateKey = `${date.getFullYear()}-${String(
      date.getMonth() + 1
    ).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
    if (!groups[dateKey]) {
      groups[dateKey] = [];
    }
    groups[dateKey].push(slot);
  });
  // 各日付の枠を店舗順（shop.id昇順）、同じ店舗内では時間順にソート
  Object.keys(groups).forEach((dateKey) => {
    groups[dateKey].sort((a, b) => {
      // 最初の店舗IDを取得（店舗が存在しない場合は0）
      const aShopId =
        a.shops && a.shops.length > 0
          ? Math.min(...a.shops.map((shop) => shop.id))
          : 0;
      const bShopId =
        b.shops && b.shops.length > 0
          ? Math.min(...b.shops.map((shop) => shop.id))
          : 0;

      // 店舗IDで比較
      if (aShopId !== bShopId) {
        return aShopId - bShopId;
      }

      // 同じ店舗の場合は時間順（shoot_time が null の枠は先頭に寄せる）
      const ta = a.shoot_time ?? "";
      const tb = b.shoot_time ?? "";
      return ta.localeCompare(tb);
    });
  });
  return groups;
});

// 日付の展開/折りたたみ
const toggleDate = (date) => {
  if (expandedDates.value.has(date)) {
    expandedDates.value.delete(date);
  } else {
    expandedDates.value.add(date);
  }
};

// 予約済み件数を取得
const getReservedCount = (slots) => {
  return slots.filter((slot) => slot.customer !== null).length;
};

// 空き件数を取得
const getAvailableCount = (slots) => {
  return slots.filter((slot) => slot.customer === null).length;
};

// 日付グループに紐づく店舗を取得（重複除去）
const getShopsForDate = (slots) => {
  const shopMap = new Map();
  slots.forEach((slot) => {
    if (slot.shops && slot.shops.length > 0) {
      slot.shops.forEach((shop) => {
        if (!shopMap.has(shop.id)) {
          shopMap.set(shop.id, shop);
        }
      });
    }
  });
  return Array.from(shopMap.values()).sort((a, b) =>
    a.name.localeCompare(b.name)
  );
};

// スロットの最小店舗IDを取得
const getMinShopId = (slot) => {
  if (!slot.shops || slot.shops.length === 0) return 0;
  return Math.min(...slot.shops.map((shop) => shop.id));
};

// スロットの店舗名を取得（複数の場合はすべての店舗名を「・」で区切って表示）
const getShopName = (slot) => {
  if (!slot.shops || slot.shops.length === 0) return "店舗未設定";
  // 店舗IDでソートして店舗名を取得
  const sortedShops = [...slot.shops].sort((a, b) => a.id - b.id);
  return sortedShops.map((shop) => shop.name).join("・");
};

// dateGroup を店舗別にグルーピング
const groupSlotsByShop = (slots) => {
  const groups = {};
  for (const slot of slots) {
    const shopId = getMinShopId(slot);
    const shopName = getShopName(slot);
    if (!groups[shopId]) {
      groups[shopId] = { shop_id: shopId, shop_name: shopName, slots: [] };
    }
    groups[shopId].slots.push(slot);
  }
  return Object.values(groups).sort((a, b) => a.shop_id - b.shop_id);
};

// 店舗バッジの色クラス（和色パレット）
const getShopBadgeClass = (shopName) => {
  if (shopName?.includes("岡山")) return "bg-natane-100 text-natane-800";
  if (shopName?.includes("城東")) return "bg-ai-100 text-ai-800";
  if (shopName?.includes("浜")) return "bg-uguisu-100 text-uguisu-800";
  if (shopName?.includes("福井")) return "bg-enji-100 text-enji-800";
  return "bg-sumi-100 text-sumi-800";
};

const countReserved = (slots) => slots.filter((s) => s.customer).length;
const countAvailable = (slots) => slots.filter((s) => !s.customer).length;

// 年・月タブ用
const selectedYears = ref(new Set());
const selectedMonths = ref(new Set());

const availableYears = computed(() => {
  const set = new Set();
  for (const slot of props.photoSlots ?? []) {
    if (slot.shoot_date) set.add(String(slot.shoot_date).substring(0, 4));
  }
  return [...set].sort();
});

// 月タブは選択中の年のみに絞る（年が選択されていなければ全月）
const availableMonths = computed(() => {
  const set = new Set();
  for (const slot of props.photoSlots ?? []) {
    if (!slot.shoot_date) continue;
    const year = String(slot.shoot_date).substring(0, 4);
    if (selectedYears.value.size > 0 && !selectedYears.value.has(year)) continue;
    set.add(String(slot.shoot_date).substring(0, 7));
  }
  return [...set].sort();
});

const totalCount = computed(() => (props.photoSlots ?? []).length);

const countByYear = (year) =>
  (props.photoSlots ?? []).filter(
    (s) => s.shoot_date && String(s.shoot_date).startsWith(year)
  ).length;

const countByMonth = (month) =>
  (props.photoSlots ?? []).filter(
    (s) => s.shoot_date && String(s.shoot_date).startsWith(month)
  ).length;

const formatMonth = (month) => {
  const [y, m] = month.split("-");
  return `${m}月`;
};

const toggleYear = (year) => {
  const next = new Set(selectedYears.value);
  if (next.has(year)) next.delete(year);
  else next.add(year);
  selectedYears.value = next;
  // 月選択は年が変わったらクリア（選択中の月が他年に属している可能性があるため）
  selectedMonths.value = new Set();
};

const clearYears = () => {
  selectedYears.value = new Set();
  selectedMonths.value = new Set();
};

const toggleMonth = (month) => {
  const next = new Set(selectedMonths.value);
  if (next.has(month)) next.delete(month);
  else next.add(month);
  selectedMonths.value = next;
};

const clearMonths = () => {
  selectedMonths.value = new Set();
};

// 年・月のデフォルトを設定（今月→今年、無ければ最新年）
const applyDefaultYearMonth = () => {
  const years = availableYears.value;
  if (years.length === 0) {
    selectedYears.value = new Set();
    selectedMonths.value = new Set();
    return;
  }
  const now = new Date();
  const currentYear = String(now.getFullYear());
  const currentMonth = `${currentYear}-${String(now.getMonth() + 1).padStart(2, "0")}`;

  // 年: 今年がデータにあれば今年、無ければ最新年
  selectedYears.value = years.includes(currentYear)
    ? new Set([currentYear])
    : new Set([years[years.length - 1]]);

  // 月: 今月がデータにあれば今月、無ければ未選択
  selectedMonths.value = availableMonths.value.includes(currentMonth)
    ? new Set([currentMonth])
    : new Set();
};

onMounted(() => {
  applyDefaultYearMonth();
});

// 絞り込み条件をリセット
const resetFilters = () => {
  filters.value = {
    shop_id: "",
    studio_id: "",
    reservation_status: "",
    start_date: "",
    end_date: "",
  };
  // 年・月タブはデフォルト（今年・今月）に戻す
  applyDefaultYearMonth();
};

const formatTime = (time) => {
  if (!time) {
    return "-";
  }
  // time形式（HH:mm:ss）から時刻部分のみを取得
  const parts = time.split(":");
  return `${parts[0]}:${parts[1]}`;
};

// 編集モーダルを開く
const openEditModal = (slot) => {
  selectedSlot.value = slot;

  // フォームに現在の値を設定
  editForm.photo_slot_id = slot.id;
  editForm.shop_id =
    slot.shops && slot.shops.length > 0 ? slot.shops[0].id : "";
  editForm.selected_studio_id = slot.studio?.id || "";
  editForm.selected_date = slot.shoot_date || "";
  editForm.assignment_label = slot.assignment_label || null;
  editForm.user_id = slot.user?.id || null;
  editForm.plan_id = slot.plan?.id || null;
  editForm.remarks = slot.remarks || "";

  // 店舗が選択されている場合はユーザーを取得
  if (editForm.shop_id) {
    loadEditShopUsers(editForm.shop_id);
  }

  showEditModal.value = true;
};

// 利用可能な会場（スタジオ）を取得（担当店舗でフィルタリング）
const availableEditStudios = computed(() => {
  if (!props.availablePhotoSlots || props.availablePhotoSlots.length === 0) {
    return props.photoStudios || [];
  }
  if (!editForm.shop_id) {
    return [];
  }
  const studios = new Map();
  const shopId = Number(editForm.shop_id);
  props.availablePhotoSlots.forEach((slot) => {
    if (
      slot.studio &&
      slot.shops &&
      slot.shops.some((shop) => shop.id === shopId) &&
      !studios.has(slot.studio.id)
    ) {
      studios.set(slot.studio.id, slot.studio);
    }
  });
  // 現在選択中のスタジオも含める
  if (editForm.selected_studio_id) {
    const currentStudio = props.photoStudios?.find(
      (s) => s.id == editForm.selected_studio_id
    );
    if (currentStudio) {
      studios.set(currentStudio.id, currentStudio);
    }
  }
  return Array.from(studios.values()).sort((a, b) =>
    a.name.localeCompare(b.name)
  );
});

// 選択された会場の利用可能な日付を取得（担当店舗でフィルタリング）
const availableEditDates = computed(() => {
  if (
    !editForm.selected_studio_id ||
    !editForm.shop_id ||
    !props.availablePhotoSlots
  ) {
    return [];
  }
  const dates = new Set();
  const shopId = Number(editForm.shop_id);
  props.availablePhotoSlots.forEach((slot) => {
    if (
      slot.studio?.id == editForm.selected_studio_id &&
      slot.shops &&
      slot.shops.some((shop) => shop.id === shopId)
    ) {
      dates.add(slot.shoot_date);
    }
  });
  // 現在選択中の日付も含める
  if (editForm.selected_date) {
    dates.add(editForm.selected_date);
  }
  return Array.from(dates).sort();
});

// 選択された会場と日付の利用可能な時間枠を取得（担当店舗でフィルタリング）
const availableEditTimeSlots = computed(() => {
  if (
    !editForm.selected_studio_id ||
    !editForm.selected_date ||
    !editForm.shop_id ||
    !props.availablePhotoSlots
  ) {
    return [];
  }
  const shopId = Number(editForm.shop_id);
  const slots = props.availablePhotoSlots.filter((slot) => {
    return (
      slot.studio?.id == editForm.selected_studio_id &&
      slot.shoot_date === editForm.selected_date &&
      slot.shops &&
      slot.shops.some((shop) => shop.id === shopId)
    );
  });
  // 現在選択中のスロットも含める
  if (selectedSlot.value && selectedSlot.value.id) {
    const currentSlot = {
      id: selectedSlot.value.id,
      shoot_time: selectedSlot.value.shoot_time,
    };
    if (!slots.find((s) => s.id === currentSlot.id)) {
      slots.push(currentSlot);
    }
  }
  return slots.sort((a, b) =>
    (a.shoot_time ?? "").localeCompare(b.shoot_time ?? "")
  );
});

// 編集フォームの店舗変更時の処理
const onEditShopChange = async () => {
  editForm.selected_studio_id = "";
  editForm.selected_date = "";
  editForm.photo_slot_id = "";

  if (editForm.shop_id) {
    await loadEditShopUsers(editForm.shop_id);
  } else {
    editShopUsers.value = [];
    editForm.user_id = null;
  }
};

// 編集フォームの会場変更時の処理
const onEditStudioChange = () => {
  editForm.selected_date = "";
  editForm.photo_slot_id = "";
};

// 編集フォームの日付変更時の処理
const onEditDateChange = () => {
  editForm.photo_slot_id = "";
};

// 編集フォームの時間枠変更時の処理
const onEditPhotoSlotChange = () => {
  // 必要に応じて処理を追加
};

// 編集用の店舗ユーザーを取得
const loadEditShopUsers = async (shopId) => {
  if (!shopId) {
    editShopUsers.value = [];
    editForm.user_id = null;
    return;
  }
  try {
    const response = await axios.get(route("admin.schedules.shop-users"), {
      params: { shop_id: shopId },
    });
    editShopUsers.value = response.data;
    if (
      editForm.user_id &&
      !editShopUsers.value.some((u) => u.id === editForm.user_id)
    ) {
      editForm.user_id = null;
    }
  } catch (error) {
    console.error("店舗ユーザーの取得に失敗しました:", error);
    editShopUsers.value = [];
  }
};

// 前撮り枠を更新
const updatePhotoSlot = () => {
  if (!selectedSlot.value) return;

  const formData = {
    ...editForm.data(),
    shop_ids: editForm.shop_id ? [editForm.shop_id] : [],
    assignment_label: editForm.assignment_label || null,
    user_id: editForm.user_id || null,
    plan_id: editForm.plan_id || null,
  };

  delete formData.selected_studio_id;
  delete formData.selected_date;

  editForm
    .transform(() => formData)
    .put(route("admin.photo-slots.update", selectedSlot.value.id), {
      preserveScroll: true,
      onSuccess: () => {
        showEditModal.value = false;
        editForm.reset();
        selectedSlot.value = null;
      },
    });
};

// 削除確認
const confirmDelete = (slot) => {
  selectedSlot.value = slot;
  showDeleteModal.value = true;
};

// 前撮り枠を削除
const deletePhotoSlot = () => {
  if (!selectedSlot.value) return;

  router.delete(route("admin.photo-slots.destroy", selectedSlot.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteModal.value = false;
      selectedSlot.value = null;
    },
  });
};

// 解除確認
const confirmRelease = (slot) => {
  selectedSlot.value = slot;
  showReleaseModal.value = true;
};

// 顧客紐付けを解除
const releasePhotoSlot = () => {
  if (!selectedSlot.value) return;

  router.patch(
    route("admin.photo-slots.release-customer", selectedSlot.value.id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        showReleaseModal.value = false;
        selectedSlot.value = null;
      },
    }
  );
};

// 日付グループ編集モーダルを開く
const openDateEditModal = (date, dateGroup) => {
  selectedDateKey.value = date;
  selectedDateGroupSlots.value = dateGroup;

  // フォームに現在の値を設定（最初の枠の値をデフォルトとして使用）
  if (dateGroup && dateGroup.length > 0) {
    const firstSlot = dateGroup[0];
    dateGroupEditForm.shoot_date = firstSlot.shoot_date || "";
    dateGroupEditForm.photo_studio_id = firstSlot.studio?.id || "";
    dateGroupEditForm.shop_ids = firstSlot.shops
      ? firstSlot.shops.map((shop) => shop.id)
      : [];
  } else {
    dateGroupEditForm.shoot_date = "";
    dateGroupEditForm.photo_studio_id = "";
    dateGroupEditForm.shop_ids = [];
  }

  showDateEditModal.value = true;
};

// 日付グループを一括更新
const updateDateGroup = () => {
  if (
    !selectedDateGroupSlots.value ||
    selectedDateGroupSlots.value.length === 0
  )
    return;

  const slotIds = selectedDateGroupSlots.value.map((slot) => slot.id);

  dateGroupEditForm
    .transform((data) => {
      return {
        ...data,
        slot_ids: slotIds,
      };
    })
    .put(route("admin.photo-slots.bulk-update"), {
      preserveScroll: true,
      onSuccess: () => {
        showDateEditModal.value = false;
        dateGroupEditForm.reset();
        selectedDateGroupSlots.value = [];
        selectedDateKey.value = "";
      },
    });
};

// 日付グループにスケジュールが登録されているか確認
const hasScheduleForDate = (date, dateGroup) => {
  // dateScheduleStatusにキャッシュがあればそれを使用
  if (dateScheduleStatus.value.has(date)) {
    return dateScheduleStatus.value.get(date);
  }
  // 日付グループ内のいずれかの枠がスケジュール登録済みかチェック
  const scheduledIds = props.scheduledSlotIds || [];
  return dateGroup.some((slot) => scheduledIds.includes(slot.id));
};

// 日付グループのスケジュールを作成
const createScheduleForDate = async (date, dateGroup) => {
  if (creatingSchedule.value) return;
  if (!dateGroup || dateGroup.length === 0) return;

  creatingSchedule.value = true;

  try {
    const firstSlot = dateGroup[0];
    const response = await axios.post(
      route("admin.photo-slots.create-schedule"),
      {
        photo_slot_id: firstSlot.id,
      }
    );

    if (response.data.success) {
      dateScheduleStatus.value.set(date, true);
      alert("カレンダーにスケジュールを追加しました。");
    }
  } catch (error) {
    console.error("スケジュール作成エラー:", error);
    alert("スケジュールの作成に失敗しました。");
  } finally {
    creatingSchedule.value = false;
  }
};

// 個別時間枠がスケジュール登録済みか確認
const isSlotScheduled = (slotId) => {
  return props.scheduledSlotIds && props.scheduledSlotIds.includes(slotId);
};

// 個別時間枠のスケジュールを作成
const createSlotSchedule = async (slot) => {
  if (creatingSlotSchedule.value === slot.id) return;
  if (!slot.customer || !slot.user) {
    alert("顧客または担当者が設定されていないため、スケジュールを登録できません。");
    return;
  }

  creatingSlotSchedule.value = slot.id;

  try {
    const response = await axios.post(
      route("admin.photo-slots.create-slot-schedule"),
      {
        photo_slot_id: slot.id,
      }
    );

    if (response.data.success) {
      alert("担当者のスケジュールに登録しました。");
      // ページをリロードして最新の状態を反映
      router.reload();
    }
  } catch (error) {
    console.error("スケジュール作成エラー:", error);
    const errorMessage = error.response?.data?.message || "スケジュールの作成に失敗しました。";
    alert(errorMessage);
  } finally {
    creatingSlotSchedule.value = null;
  }
};
</script>

