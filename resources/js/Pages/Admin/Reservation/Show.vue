<template>
  <Head title="予約詳細" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          予約詳細
        </h2>
        <div class="flex space-x-4">
          <Link
            :href="route('admin.reservations.edit', reservation.id)"
            class="text-indigo-600 hover:text-indigo-900"
          >
            編集
          </Link>
          <Link
            :href="
              route('admin.events.reservations.index', reservation.event_id)
            "
            class="text-indigo-600 hover:text-indigo-900"
          >
            ← 予約一覧に戻る
          </Link>
        </div>
      </div>
    </template>

    <div
      v-if="$page.props.success"
      class="mb-4 p-3 rounded bg-green-100 text-green-800 border border-green-200"
    >
      {{ $page.props.success }}
    </div>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- 左側: 予約情報 -->
          <div class="lg:col-span-2">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">予約情報</h3>
                <div class="space-y-4">
                  <!-- 共通フィールド -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                      >お名前</label
                    >
                    <p class="text-sm text-gray-900">{{ reservation.name }}</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                      >メールアドレス</label
                    >
                    <p class="text-sm text-gray-900">{{ reservation.email }}</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                      >電話番号</label
                    >
                    <p class="text-sm text-gray-900">{{ reservation.phone }}</p>
                  </div>

                  <!-- 予約フォーム (reservation) -->
                  <template v-if="event.form_type === 'reservation'">
                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >予約日時</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.reservation_datetime || "-" }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >フリガナ</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.furigana || "-" }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >ご来店会場</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.venue ? reservation.venue.name : "-" }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >過去当店のご来店はありますか</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.has_visited_before ? "あり" : "なし" }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >住所</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.address || "-" }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >生年月日</label
                      >
                      <p class="text-sm text-gray-900">
                        {{
                          reservation.birth_date
                            ? formatDate(reservation.birth_date)
                            : "-"
                        }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >成人式予定年月</label
                      >
                      <p class="text-sm text-gray-900">
                        {{
                          reservation.seijin_year
                            ? reservation.seijin_year + "年"
                            : "-"
                        }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >学校名</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.school_name || "-" }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >駐車場利用</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.parking_usage || "-" }}
                      </p>
                    </div>

                    <div v-if="reservation.parking_usage === 'あり'">
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >駐車台数</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.parking_car_count || "-" }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >検討中のプラン</label
                      >
                      <p class="text-sm text-gray-900">
                        {{
                          reservation.considering_plans &&
                          reservation.considering_plans.length > 0
                            ? reservation.considering_plans.join(", ")
                            : "-"
                        }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >ご紹介者様お名前</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.referred_by_name || "-" }}
                      </p>
                    </div>
                  </template>

                  <!-- 資料請求フォーム (document) -->
                  <template v-if="event.form_type === 'document'">
                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >請求方法</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.request_method || "-" }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >フリガナ</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.furigana || "-" }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >生年月日</label
                      >
                      <p class="text-sm text-gray-900">
                        {{
                          reservation.birth_date
                            ? formatDate(reservation.birth_date)
                            : "-"
                        }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >郵便番号</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.postal_code || "-" }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >住所</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.address || "-" }}
                      </p>
                    </div>

                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >個人情報保護方針への同意</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.privacy_agreed ? "同意" : "-" }}
                      </p>
                    </div>
                  </template>

                  <!-- お問い合わせフォーム (contact) -->
                  <template v-if="event.form_type === 'contact'">
                    <div>
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >問い合わせ回答方法</label
                      >
                      <p class="text-sm text-gray-900">
                        {{ reservation.heard_from || "-" }}
                      </p>
                    </div>
                  </template>

                  <!-- 共通: お問い合わせ内容 -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                      >お問い合わせ内容</label
                    >
                    <p class="text-sm text-gray-900 whitespace-pre-wrap">
                      {{ reservation.inquiry_message || "-" }}
                    </p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                      >登録日時</label
                    >
                    <p class="text-sm text-gray-900">
                      {{ formatDateTime(reservation.created_at) }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- メールやり取りブロック -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
              <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">メールやり取り</h3>
                
                <div v-if="emailThreads && emailThreads.length > 0" class="space-y-6">
                  <div
                    v-for="thread in emailThreads"
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
                        class="border-l-4 pl-4"
                        :class="email.from === reservation.email ? 'border-blue-500' : 'border-green-500'"
                      >
                        <div class="flex justify-between items-start mb-2">
                          <div>
                            <p class="text-sm font-medium text-gray-900">
                              {{ email.from === reservation.email ? 'お客様' : '当店' }}
                            </p>
                            <p class="text-xs text-gray-500">
                              {{ email.from === reservation.email ? email.from : email.to }}
                            </p>
                          </div>
                          <p class="text-xs text-gray-500">
                            {{ formatDateTime(email.created_at) }}
                          </p>
                        </div>
                        <p class="text-sm font-medium text-gray-700 mb-1">
                          {{ email.subject }}
                        </p>
                        <div class="text-sm text-gray-600 whitespace-pre-wrap mt-2">
                          {{ email.text_body || email.html_body || '-' }}
                        </div>
                        <div v-if="email.attachments && email.attachments.length > 0" class="mt-2">
                          <p class="text-xs text-gray-500 mb-1">添付ファイル:</p>
                          <ul class="text-xs text-gray-600">
                            <li v-for="attachment in email.attachments" :key="attachment.id">
                              {{ attachment.filename }}
                            </li>
                          </ul>
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

            <!-- 返信メール送信ブロック -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
              <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">返信メール送信</h3>
                
                <form @submit.prevent="sendReplyEmail">
                  <div class="space-y-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">
                        スレッド選択
                      </label>
                      <select
                        v-model="replyForm.email_thread_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                      >
                        <option value="">スレッドを選択してください</option>
                        <option
                          v-for="thread in emailThreads"
                          :key="thread.id"
                          :value="thread.id"
                        >
                          {{ thread.subject }} ({{ thread.emails ? thread.emails.length : 0 }}件)
                        </option>
                      </select>
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">
                        メッセージ
                      </label>
                      <textarea
                        v-model="replyForm.message"
                        rows="8"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="返信メッセージを入力してください"
                        required
                      ></textarea>
                    </div>

                    <div>
                      <button
                        type="submit"
                        :disabled="replyForm.processing"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150"
                      >
                        {{ replyForm.processing ? "送信中..." : "返信メールを送信" }}
                      </button>
                    </div>
                  </div>
                </form>
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
                        >店舗</label
                      >
                      <select
                        v-model="scheduleForm.selectedShopId"
                        @change="onShopChangeForSchedule"
                        required
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

                    <div class="mb-4">
                      <label
                        class="block text-sm font-medium text-gray-700 mb-1"
                        >スタッフ</label
                      >
                      <select
                        v-model="scheduleForm.user_id"
                        required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      >
                        <option value="">スタッフを選択してください</option>
                        <option
                          v-for="user in shopUsersForSchedule"
                          :key="user.id"
                          :value="user.id"
                        >
                          {{ user.name }}
                        </option>
                      </select>
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
                        >参加者</label
                      >
                      <div v-if="scheduleForm.selectedShopId" class="space-y-2">
                        <div
                          v-for="user in shopUsersForSchedule"
                          :key="user.id"
                          class="flex items-center"
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
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                          />
                          <label
                            :for="`participant-${user.id}`"
                            class="ml-2 text-sm text-gray-700"
                          >
                            {{ user.name }}
                          </label>
                        </div>
                      </div>
                      <p v-else class="text-sm text-gray-500">
                        店舗を選択してください
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
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { ref, computed, onMounted } from "vue";
import axios from "axios";

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
  currentUser: Object,
  userShops: Array,
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
  };
  return classes[status] || "bg-gray-100 text-gray-800";
};

// スケジュール関連
const userShops = computed(() => props.userShops || []);
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
  user_id: "",
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
    scheduleForm.user_id = "";
    return;
  }

  try {
    const response = await axios.get(route("admin.schedules.shop-users"), {
      params: { shop_id: scheduleForm.selectedShopId },
    });
    shopUsersForSchedule.value = response.data;

    // デフォルトでログインユーザーを選択
    if (
      props.currentUser &&
      shopUsersForSchedule.value.some((u) => u.id === props.currentUser.id)
    ) {
      scheduleForm.user_id = props.currentUser.id;
    }
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

// スケジュールに追加
function addToSchedule() {
  scheduleForm.processing = true;

  const formData = {
    title: scheduleForm.title,
    description: scheduleForm.description,
    user_id: scheduleForm.user_id,
    start_at: scheduleForm.start_at,
    end_at: scheduleForm.end_at,
    all_day: scheduleForm.all_day,
    participant_ids: scheduleForm.participant_ids,
  };

  scheduleForm.post(
    route("admin.reservations.schedule.add", props.reservation.id),
    {
      onSuccess: () => {
        scheduleForm.reset();
        scheduleForm.title = getDefaultTitle();
        scheduleForm.description = getDefaultDescription();
        scheduleForm.selectedShopId = "";
        shopUsersForSchedule.value = [];
        addedParticipantsForSchedule.value = [];
        router.reload({ only: ["schedule"] });
      },
      onError: () => {
        scheduleForm.processing = false;
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
  message: "",
});

const sendReplyEmail = () => {
  replyForm.post(
    route("admin.reservations.reply-email", props.reservation.id),
    {
      onSuccess: () => {
        replyForm.reset();
        router.reload({ only: ["emailThreads"] });
      },
    }
  );
};

// 初期化
onMounted(() => {
  // デフォルトで最初の店舗を選択
  if (userShops.value.length > 0) {
    scheduleForm.selectedShopId = userShops.value[0].id;
    onShopChangeForSchedule();
  }
  
  // メールスレッドが1つだけの場合は自動選択
  if (props.emailThreads && props.emailThreads.length === 1) {
    replyForm.email_thread_id = props.emailThreads[0].id;
  }
});
</script>

