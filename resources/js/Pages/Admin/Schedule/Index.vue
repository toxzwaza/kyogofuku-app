<template>
    <Head title="スケジュール管理" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">スケジュール管理</h2>
                <button
                    @click="showCreateModal = true"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                >
                    新規作成
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- フィルター -->
                        <div class="mb-6 flex items-center space-x-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">店舗</label>
                                <select
                                    v-model="selectedShopId"
                                    @change="onShopChange"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">すべての店舗</option>
                                    <option
                                        v-for="shop in userShops"
                                        :key="shop.id"
                                        :value="shop.id"
                                    >
                                        {{ shop.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">スタッフ</label>
                                <select
                                    v-model="selectedUserId"
                                    @change="loadSchedules"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">すべてのスタッフ</option>
                                    <option
                                        v-for="user in shopUsers"
                                        :key="user.id"
                                        :value="user.id"
                                    >
                                        {{ user.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- カレンダー -->
                        <FullCalendar
                            ref="calendar"
                            :options="calendarOptions"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- スケジュール詳細・編集モーダル -->
        <div
            v-if="showDetailModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
            @click.self="closeDetailModal"
        >
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">スケジュール詳細</h3>
                        <button
                            @click="closeDetailModal"
                            class="text-gray-400 hover:text-gray-600"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div v-if="selectedSchedule" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">タイトル</label>
                            <input
                                v-if="isEditing"
                                v-model="editForm.title"
                                type="text"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p v-else class="text-sm text-gray-900">{{ selectedSchedule.title }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">予定登録者</label>
                            <p class="text-sm text-gray-900">{{ selectedSchedule.user?.name || '-' }}</p>
                        </div>


                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">開始日時</label>
                            <input
                                v-if="isEditing"
                                v-model="editForm.start_at"
                                type="datetime-local"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p v-else class="text-sm text-gray-900">{{ formatDateTime(selectedSchedule.start) }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">終了日時</label>
                            <input
                                v-if="isEditing"
                                v-model="editForm.end_at"
                                type="datetime-local"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p v-else class="text-sm text-gray-900">{{ formatDateTime(selectedSchedule.end) }}</p>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input
                                    v-if="isEditing"
                                    v-model="editForm.all_day"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <span v-else class="text-sm text-gray-900">{{ selectedSchedule.allDay ? '終日' : '時間指定' }}</span>
                                <span v-if="isEditing" class="ml-2 text-sm text-gray-700">終日</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">色</label>
                            <input
                                v-if="isEditing"
                                v-model="editForm.color"
                                type="color"
                                class="w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <div v-else class="flex items-center space-x-2">
                                <div
                                    class="w-8 h-8 rounded"
                                    :style="{ backgroundColor: selectedSchedule.color }"
                                ></div>
                                <span class="text-sm text-gray-900">{{ selectedSchedule.color }}</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">作成者</label>
                            <p class="text-sm text-gray-900">{{ selectedSchedule.user?.name || '-' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">参加者</label>
                            
                            <div v-if="isEditing">
                                <!-- 店舗選択（参加者追加用） -->
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">店舗を選択して参加者を追加</label>
                                    <select
                                        v-model="selectedShopIdForParticipants"
                                        @change="loadShopUsers(selectedShopIdForParticipants)"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">店舗を選択してください</option>
                                        <option
                                            v-for="shop in shops"
                                            :key="shop.id"
                                            :value="shop.id"
                                        >
                                            {{ shop.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- 参加者追加済み一覧 -->
                                <div v-if="addedParticipants.length > 0" class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">参加者追加済み</label>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="participant in addedParticipants"
                                            :key="participant.id"
                                            class="inline-flex items-center px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-full"
                                        >
                                            {{ participant.name }}
                                            <button
                                                type="button"
                                                @click="removeParticipant(participant.id)"
                                                class="ml-2 text-blue-600 hover:text-blue-800"
                                            >
                                                ×
                                            </button>
                                        </span>
                                    </div>
                                </div>

                                <!-- 店舗ユーザー一覧（チェックボックス） -->
                                <div v-if="shopUsers.length > 0" class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-md p-3">
                                    <label
                                        v-for="user in shopUsers"
                                        :key="user.id"
                                        class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="user.id"
                                            :checked="isParticipantAdded(user.id)"
                                            @change="toggleParticipant(user.id, $event.target.checked)"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <span class="text-sm text-gray-900">{{ user.name }}</span>
                                    </label>
                                </div>
                                <p v-else-if="!selectedShopIdForParticipants" class="text-sm text-gray-500">店舗を選択すると、その店舗に所属するユーザーが表示されます</p>
                            </div>
                            <div v-else>
                                <div v-if="selectedSchedule.participants && selectedSchedule.participants.length > 0" class="flex flex-wrap gap-2">
                                    <span
                                        v-for="participant in selectedSchedule.participants"
                                        :key="participant.id"
                                        class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full"
                                    >
                                        {{ participant.name }}
                                    </span>
                                </div>
                                <p v-else class="text-sm text-gray-500">参加者なし</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                            <textarea
                                v-if="isEditing"
                                v-model="editForm.description"
                                rows="3"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            <p v-else class="text-sm text-gray-900 whitespace-pre-wrap">{{ selectedSchedule.description || '-' }}</p>
                        </div>

                        <div v-if="!isEditing" class="flex justify-end space-x-2 pt-4">
                            <button
                                @click="startEdit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                            >
                                編集
                            </button>
                            <button
                                @click="deleteSchedule"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                            >
                                削除
                            </button>
                        </div>

                        <div v-else class="flex justify-end space-x-2 pt-4">
                            <button
                                @click="cancelEdit"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                            >
                                キャンセル
                            </button>
                            <button
                                @click="updateSchedule"
                                :disabled="editForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                            >
                                {{ editForm.processing ? '更新中...' : '更新' }}
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

                    <form @submit.prevent="createSchedule" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">タイトル <span class="text-red-500">*</span></label>
                            <input
                                v-model="createForm.title"
                                type="text"
                                required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <div v-if="createForm.errors.title" class="mt-1 text-sm text-red-600">{{ createForm.errors.title }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">予定登録者 <span class="text-red-500">*</span></label>
                            <input
                                v-model="currentUser.name"
                                type="text"
                                disabled
                                class="w-full rounded-md border-gray-300 shadow-sm bg-gray-100 text-gray-600 cursor-not-allowed"
                            />
                            <input
                                v-model="createForm.user_id"
                                type="hidden"
                            />
                            <p class="mt-1 text-xs text-gray-500">ログインしているユーザーが自動的に設定されます</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">開始日時 <span class="text-red-500">*</span></label>
                            <input
                                v-model="createForm.start_at"
                                type="datetime-local"
                                required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <div v-if="createForm.errors.start_at" class="mt-1 text-sm text-red-600">{{ createForm.errors.start_at }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">終了日時 <span class="text-red-500">*</span></label>
                            <input
                                v-model="createForm.end_at"
                                type="datetime-local"
                                required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <div v-if="createForm.errors.end_at" class="mt-1 text-sm text-red-600">{{ createForm.errors.end_at }}</div>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input
                                    v-model="createForm.all_day"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <span class="ml-2 text-sm text-gray-700">終日</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">色</label>
                            <input
                                v-model="createForm.color"
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
                                    v-model="selectedShopIdForParticipants"
                                    @change="loadShopUsers(selectedShopIdForParticipants)"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">店舗を選択してください</option>
                                    <option
                                        v-for="shop in shops"
                                        :key="shop.id"
                                        :value="shop.id"
                                    >
                                        {{ shop.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- 参加者追加済み一覧 -->
                            <div v-if="addedParticipants.length > 0" class="mb-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">参加者追加済み</label>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="participant in addedParticipants"
                                        :key="participant.id"
                                        class="inline-flex items-center px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-full"
                                    >
                                        {{ participant.name }}
                                        <button
                                            type="button"
                                            @click="removeParticipant(participant.id)"
                                            class="ml-2 text-blue-600 hover:text-blue-800"
                                        >
                                            ×
                                        </button>
                                    </span>
                                </div>
                            </div>

                            <!-- 店舗ユーザー一覧（チェックボックス） -->
                            <div v-if="shopUsersForParticipants.length > 0" class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-md p-3">
                                <label
                                    v-for="user in shopUsersForParticipants"
                                    :key="user.id"
                                    class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded"
                                >
                                    <input
                                        type="checkbox"
                                        :value="user.id"
                                        :checked="isParticipantAdded(user.id)"
                                        @change="toggleParticipant(user.id, $event.target.checked)"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <span class="text-sm text-gray-900">{{ user.name }}</span>
                                </label>
                            </div>
                            <p v-else-if="!selectedShopIdForParticipants" class="text-sm text-gray-500">店舗を選択すると、その店舗に所属するユーザーが表示されます</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                            <textarea
                                v-model="createForm.description"
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
                                :disabled="createForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400"
                            >
                                {{ createForm.processing ? '作成中...' : '作成' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import jaLocale from '@fullcalendar/core/locales/ja';
import axios from 'axios';

const props = defineProps({
    shops: Array,
    currentUser: Object,
    userShops: Array,
});

const calendar = ref(null);
const selectedShopId = ref('');
const selectedUserId = ref('');
const showDetailModal = ref(false);
const showCreateModal = ref(false);
const selectedSchedule = ref(null);
const isEditing = ref(false);

const shops = computed(() => props.shops || []);
const currentUser = computed(() => props.currentUser || null);
const userShops = computed(() => props.userShops || []);

const shopUsers = ref([]); // フィルター用
const shopUsersForParticipants = ref([]); // 参加者追加用
const selectedShopIdForParticipants = ref('');
const addedParticipants = ref([]);

// 参加者が追加済みかチェック
function isParticipantAdded(userId) {
    return addedParticipants.value.some(p => p.id === userId);
}

// 参加者を追加/削除
function toggleParticipant(userId, checked) {
    if (checked) {
        const user = shopUsersForParticipants.value.find(u => u.id === userId);
        if (user && !isParticipantAdded(userId)) {
            addedParticipants.value.push(user);
            createForm.participant_ids = addedParticipants.value.map(p => p.id);
        }
    } else {
        removeParticipant(userId);
    }
}

// 参加者を削除
function removeParticipant(userId) {
    addedParticipants.value = addedParticipants.value.filter(p => p.id !== userId);
    createForm.participant_ids = addedParticipants.value.map(p => p.id);
}

// 店舗選択時にその店舗のユーザー一覧を取得（参加者追加用）
async function loadShopUsers(shopId) {
    if (!shopId) {
        shopUsersForParticipants.value = [];
        return;
    }
    
    try {
        const response = await axios.get(route('admin.schedules.shop-users'), {
            params: { shop_id: shopId }
        });
        shopUsersForParticipants.value = response.data;
    } catch (error) {
        console.error('店舗ユーザーの取得に失敗しました:', error);
        shopUsersForParticipants.value = [];
    }
}

const calendarOptions = ref({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: jaLocale,
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    height: 'auto',
    editable: true,
    selectable: true,
    selectMirror: true,
    dayMaxEvents: true,
    weekends: true,
    select: handleDateSelect,
    eventClick: handleEventClick,
    eventDrop: handleEventDrop,
    eventResize: handleEventResize,
    events: loadSchedules,
    eventContent: renderEventContent,
});

const createForm = useForm({
    title: '',
    user_id: '',
    start_at: '',
    end_at: '',
    all_day: false,
    color: '#3788d8',
    description: '',
    participant_ids: [],
});

// 初期化時にデフォルト値を設定
onMounted(async () => {
    // 予定登録者をログインユーザーに設定
    if (currentUser.value) {
        createForm.user_id = currentUser.value.id;
    }
    
    // デフォルト店舗を設定（フィルター用）
    if (userShops.value.length > 0) {
        selectedShopId.value = userShops.value[0].id;
        await loadShopUsersForFilter(userShops.value[0].id);
        // デフォルトスタッフを設定（最初のユーザー）
        if (shopUsers.value.length > 0) {
            selectedUserId.value = shopUsers.value[0].id;
        }
    }
    
    // デフォルト店舗を設定（参加者追加用）
    if (userShops.value.length > 0) {
        selectedShopIdForParticipants.value = userShops.value[0].id;
        loadShopUsers(userShops.value[0].id);
    }
});

const editForm = ref({
    title: '',
    user_id: '',
    start_at: '',
    end_at: '',
    all_day: false,
    color: '#3788d8',
    description: '',
    participant_ids: [],
    processing: false,
});

// 店舗変更時の処理
async function onShopChange() {
    selectedUserId.value = '';
    if (selectedShopId.value) {
        await loadShopUsersForFilter(selectedShopId.value);
    } else {
        shopUsers.value = [];
    }
    loadSchedules();
}

// フィルター用の店舗ユーザー取得
async function loadShopUsersForFilter(shopId) {
    if (!shopId) {
        shopUsers.value = [];
        return;
    }
    
    try {
        const response = await axios.get(route('admin.schedules.shop-users'), {
            params: { shop_id: shopId }
        });
        shopUsers.value = response.data;
    } catch (error) {
        console.error('店舗ユーザーの取得に失敗しました:', error);
        shopUsers.value = [];
    }
}

function loadSchedules(info, successCallback, failureCallback) {
    const params = {
        start: info?.startStr || new Date().toISOString(),
        end: info?.endStr || new Date().toISOString(),
        mode: 'shop',
    };
    
    if (selectedShopId.value) {
        params.shop_id = selectedShopId.value;
    }
    
    if (selectedUserId.value) {
        params.user_id = selectedUserId.value;
    }
    
    axios.get(route('admin.schedules.index'), { params })
        .then(response => {
            if (successCallback) {
                successCallback(response.data);
            }
        })
        .catch(error => {
            console.error('スケジュールの取得に失敗しました:', error);
            if (failureCallback) {
                failureCallback(error);
            }
        });
}

function handleDateSelect(selectInfo) {
    const startDate = new Date(selectInfo.startStr);
    const endDate = new Date(selectInfo.endStr);
    
    createForm.start_at = formatDateTimeLocal(startDate);
    createForm.end_at = formatDateTimeLocal(endDate);
    createForm.all_day = selectInfo.allDay;
    showCreateModal.value = true;
    calendar.value.getApi().unselect();
}

function handleEventClick(clickInfo) {
    selectedSchedule.value = {
        id: clickInfo.event.id,
        title: clickInfo.event.title,
        start: clickInfo.event.startStr,
        end: clickInfo.event.endStr,
        allDay: clickInfo.event.allDay,
        color: clickInfo.event.backgroundColor,
        description: clickInfo.event.extendedProps.description || '',
        user: clickInfo.event.extendedProps.user || null,
        shop: clickInfo.event.extendedProps.shop || null,
        participants: clickInfo.event.extendedProps.participants || [],
    };
    showDetailModal.value = true;
    isEditing.value = false;
}

function handleEventDrop(dropInfo) {
    const scheduleData = {
        title: dropInfo.event.title,
        start_at: dropInfo.event.startStr,
        end_at: dropInfo.event.endStr,
        all_day: dropInfo.event.allDay,
    };
    
    axios.put(route('admin.schedules.update', dropInfo.event.id), scheduleData)
        .catch(error => {
            console.error('スケジュールの更新に失敗しました:', error);
            alert('スケジュールの更新に失敗しました。');
            dropInfo.revert();
        });
}

function handleEventResize(resizeInfo) {
    const scheduleData = {
        title: resizeInfo.event.title,
        start_at: resizeInfo.event.startStr,
        end_at: resizeInfo.event.endStr,
        all_day: resizeInfo.event.allDay,
    };
    
    axios.put(route('admin.schedules.update', resizeInfo.event.id), scheduleData)
        .catch(error => {
            console.error('スケジュールの更新に失敗しました:', error);
            alert('スケジュールの更新に失敗しました。');
            resizeInfo.revert();
        });
}

function createSchedule() {
    createForm.post(route('admin.schedules.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            addedParticipants.value = [];
            selectedShopIdForParticipants.value = '';
            shopUsersForParticipants.value = [];
            // デフォルト店舗を再設定
            if (userShops.value.length > 0) {
                selectedShopIdForParticipants.value = userShops.value[0].id;
                loadShopUsers(userShops.value[0].id);
            }
            calendar.value.getApi().refetchEvents();
        },
    });
}

function startEdit() {
    isEditing.value = true;
    const startDate = new Date(selectedSchedule.value.start);
    const endDate = new Date(selectedSchedule.value.end);
    
    editForm.value = {
        title: selectedSchedule.value.title,
        user_id: selectedSchedule.value.user?.id || '',
        start_at: formatDateTimeLocal(startDate),
        end_at: formatDateTimeLocal(endDate),
        all_day: selectedSchedule.value.allDay,
        color: selectedSchedule.value.color,
        description: selectedSchedule.value.description || '',
        participant_ids: selectedSchedule.value.participants?.map(p => p.id) || [],
        processing: false,
    };
    
    // 既存の参加者を追加済みリストに設定
    addedParticipants.value = selectedSchedule.value.participants?.map(p => ({
        id: p.id,
        name: p.name,
    })) || [];
    
    // デフォルト店舗を設定（参加者追加用）
    if (userShops.value.length > 0) {
        selectedShopIdForParticipants.value = userShops.value[0].id;
        loadShopUsers(userShops.value[0].id);
    }
}

function cancelEdit() {
    isEditing.value = false;
    editForm.value = {
        title: '',
        user_id: '',
        start_at: '',
        end_at: '',
        all_day: false,
        color: '#3788d8',
        description: '',
        participant_ids: [],
        processing: false,
    };
    addedParticipants.value = [];
    selectedShopIdForParticipants.value = '';
    shopUsersForParticipants.value = [];
}

function updateSchedule() {
    editForm.value.processing = true;
    
    const updateData = {
        ...editForm.value,
    };
    delete updateData.processing;
    
    axios.put(route('admin.schedules.update', selectedSchedule.value.id), updateData)
        .then(() => {
            showDetailModal.value = false;
            isEditing.value = false;
            addedParticipants.value = [];
            selectedShopIdForParticipants.value = '';
            shopUsersForParticipants.value = [];
            calendar.value.getApi().refetchEvents();
        })
        .catch(error => {
            console.error('スケジュールの更新に失敗しました:', error);
            alert('スケジュールの更新に失敗しました。');
        })
        .finally(() => {
            editForm.value.processing = false;
        });
}

function deleteSchedule() {
    if (confirm('このスケジュールを削除しますか？')) {
        axios.delete(route('admin.schedules.destroy', selectedSchedule.value.id))
            .then(() => {
                showDetailModal.value = false;
                calendar.value.getApi().refetchEvents();
            })
            .catch(error => {
                console.error('スケジュールの削除に失敗しました:', error);
                alert('スケジュールの削除に失敗しました。');
            });
    }
}

function closeDetailModal() {
    showDetailModal.value = false;
    isEditing.value = false;
    selectedSchedule.value = null;
}

function formatDateTime(datetime) {
    if (!datetime) return '-';
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP');
}

function formatDateTimeLocal(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// 時間をフォーマット
function formatTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', hour12: false });
}

// イベント表示内容をカスタマイズ
function renderEventContent(arg) {
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

