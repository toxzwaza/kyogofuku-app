<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import UiDialog from '@/Components/UI/Dialog.vue';
import UiBadge from '@/Components/UI/Badge.vue';
import {
    MessageCircle, ChevronDown, ArrowRight, Image as ImageIcon, RefreshCw,
} from 'lucide-vue-next';

// 全管理画面に常駐する LINE受信ウィジェット（右下 fixed）。
// マウント時に受信箱を取得し、未読件数を赤バッジで表示。クリックでモーダルを開く。
const data = ref({ groups: [], unread_total: 0, total: 0 });
const loading = ref(false);
const loaded = ref(false);
const open = ref(false);

// 表示フィルタ（未読のみ / 既読も表示）とお客様グループの展開状態
const unreadOnly = ref(true);
const expanded = ref({});
const toggleGroup = (id) => { expanded.value[id] = !expanded.value[id]; };
const isExpanded = (id) => !!expanded.value[id];

const fetchInbox = async () => {
    loading.value = true;
    try {
        const res = await window.axios.get(route('admin.line-inbox'));
        data.value = res.data;
        loaded.value = true;
    } catch (e) {
        // 取得失敗時は静かに握りつぶす（バッジ非表示のまま）
    } finally {
        loading.value = false;
    }
};

onMounted(fetchInbox);

const openModal = () => {
    open.value = true;
    // 開くたびに最新化（未読が消化されている可能性があるため）
    fetchInbox();
};

const unreadTotal = computed(() => data.value.unread_total || 0);

// 未読のみ表示のときは未読を含むグループだけに絞る
const groupsView = computed(() => {
    const groups = data.value.groups || [];
    if (!unreadOnly.value) return groups;
    return groups
        .filter((g) => g.unread_count > 0)
        .map((g) => ({ ...g, view_messages: g.messages.filter((m) => m.is_unread) }));
});
// 既読も表示のときは全メッセージを見せる
const messagesOf = (g) => (unreadOnly.value ? (g.view_messages ?? g.messages) : g.messages);

const latestOf = (g) => (g.messages.length ? g.messages[g.messages.length - 1] : null);

const fmtDateTime = (s) => {
    if (!s) return '';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    return `${mm}/${dd} ${hh}:${mi}`;
};
const fmtRelative = (s) => {
    if (!s) return '';
    const d = new Date(s);
    if (isNaN(d.getTime())) return '';
    const min = Math.floor((Date.now() - d.getTime()) / 60000);
    if (min < 1) return 'たった今';
    if (min < 60) return `${min}分前`;
    const hr = Math.floor(min / 60);
    if (hr < 24) return `${hr}時間前`;
    const day = Math.floor(hr / 24);
    if (day < 7) return `${day}日前`;
    return fmtDateTime(s);
};
</script>

<template>
    <!-- 右下 fixed の通知アイコン -->
    <button
        type="button"
        class="fixed bottom-5 right-5 z-40 w-14 h-14 rounded-full bg-brand-primary text-white shadow-soft-lg flex items-center justify-center hover:brightness-110 transition"
        aria-label="LINE受信"
        @click="openModal"
    >
        <MessageCircle :size="24" />
        <span
            v-if="unreadTotal > 0"
            class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 rounded-full bg-akane-500 text-white text-[11px] font-bold flex items-center justify-center border-2 border-brand-bg"
        >
            {{ unreadTotal > 99 ? '99+' : unreadTotal }}
        </span>
    </button>

    <!-- 受信箱モーダル -->
    <UiDialog v-model:open="open" size="lg">
        <template #header>
            <div class="flex items-center gap-2">
                <MessageCircle :size="18" class="text-brand-primary" />
                <span>LINE受信</span>
                <UiBadge v-if="unreadTotal > 0" variant="success" size="sm">未読 {{ unreadTotal }}</UiBadge>
            </div>
        </template>

        <div class="flex items-center justify-between mb-3">
            <!-- 未読のみトグル -->
            <button
                type="button"
                role="switch"
                :aria-checked="unreadOnly"
                @click="unreadOnly = !unreadOnly"
                class="flex items-center gap-1.5 text-xs text-brand-text-muted hover:text-brand-text transition-colors"
            >
                <span>未読のみ</span>
                <span
                    class="relative inline-flex h-4 w-7 flex-shrink-0 items-center rounded-full transition-colors"
                    :class="unreadOnly ? 'bg-uguisu-500' : 'bg-brand-border'"
                >
                    <span
                        class="inline-block h-3 w-3 transform rounded-full bg-white shadow transition-transform"
                        :class="unreadOnly ? 'translate-x-3.5' : 'translate-x-0.5'"
                    />
                </span>
            </button>
            <button
                type="button"
                class="flex items-center gap-1 text-xs text-brand-text-muted hover:text-brand-text transition-colors"
                :disabled="loading"
                @click="fetchInbox"
            >
                <RefreshCw :size="13" :class="loading ? 'animate-spin' : ''" />
                更新
            </button>
        </div>

        <div class="max-h-[60vh] overflow-y-auto -mx-5">
            <div v-if="loading && !loaded" class="p-6 text-center text-sm text-brand-text-muted">
                読み込み中…
            </div>
            <div v-else-if="groupsView.length === 0" class="p-6 text-center text-sm text-brand-text-muted">
                {{ unreadOnly ? '未読のLINEメッセージはありません。' : 'LINEメッセージはありません。' }}
                <button
                    v-if="unreadOnly && data.total > 0"
                    type="button"
                    @click="unreadOnly = false"
                    class="block mx-auto mt-2 text-xs text-brand-primary hover:underline"
                >
                    既読も表示する
                </button>
            </div>
            <div v-else class="divide-y divide-brand-border">
                <div v-for="g in groupsView" :key="g.contact_id">
                    <!-- お客様の見出し行（クリックで展開） -->
                    <button
                        type="button"
                        class="w-full flex items-center gap-3 px-5 py-3 text-left hover:bg-brand-surface-2 transition-colors"
                        @click="toggleGroup(g.contact_id)"
                    >
                        <span
                            class="flex-shrink-0 w-1 self-stretch rounded-full"
                            :class="g.unread_count > 0 ? 'bg-uguisu-500' : 'bg-brand-border'"
                        ></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span
                                    class="text-sm font-medium truncate"
                                    :class="g.unread_count > 0 ? '' : 'text-brand-text-muted'"
                                >{{ g.name }}</span>
                                <UiBadge :variant="g.link_kind === 'reservation' ? 'primary' : 'neutral'" size="sm">
                                    {{ g.link_kind === 'reservation' ? '予約' : '顧客' }}
                                </UiBadge>
                                <UiBadge v-if="g.unread_count > 0" variant="success" size="sm">未読 {{ g.unread_count }}</UiBadge>
                                <UiBadge v-else variant="neutral" size="sm">既読</UiBadge>
                            </div>
                            <div class="text-xs text-brand-text-muted truncate mt-0.5">
                                <span v-if="latestOf(g)?.is_image" class="inline-flex items-center gap-1">
                                    <ImageIcon :size="12" /> 画像
                                </span>
                                <span v-else>{{ (latestOf(g)?.text && latestOf(g).text.trim()) ? latestOf(g).text : '（テキスト以外）' }}</span>
                            </div>
                        </div>
                        <div class="flex-shrink-0 flex flex-col items-end gap-1">
                            <span class="text-[11px] text-brand-text-muted whitespace-nowrap">{{ fmtRelative(latestOf(g)?.created_at) }}</span>
                            <ChevronDown
                                :size="16"
                                class="text-brand-text-muted transition-transform"
                                :class="{ 'rotate-180': isExpanded(g.contact_id) }"
                            />
                        </div>
                    </button>

                    <!-- 展開部：そのお客様のメッセージ一覧（時系列・既読は淡色） -->
                    <div v-if="isExpanded(g.contact_id)" class="bg-brand-surface-2 px-5 py-3 space-y-2">
                        <div
                            v-for="msg in messagesOf(g)"
                            :key="msg.id"
                            class="rounded-md border px-3 py-2"
                            :class="msg.is_unread
                                ? 'border-brand-border bg-brand-surface'
                                : 'border-brand-border/60 bg-brand-surface/60'"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p v-if="msg.is_image" class="flex items-center gap-1 text-sm text-brand-text-muted">
                                    <ImageIcon :size="14" /> 画像メッセージ
                                </p>
                                <p
                                    v-else
                                    class="text-sm whitespace-pre-wrap break-words flex-1"
                                    :class="msg.is_unread ? '' : 'text-brand-text-muted'"
                                >
                                    {{ msg.text?.trim() ? msg.text : '（テキスト以外）' }}
                                </p>
                                <span class="flex-shrink-0 flex items-center gap-1 whitespace-nowrap">
                                    <span v-if="msg.is_unread" class="inline-block w-1.5 h-1.5 rounded-full bg-uguisu-500" title="未読"></span>
                                    <span class="text-[10px] text-brand-text-subtle">{{ fmtDateTime(msg.created_at) }}</span>
                                </span>
                            </div>
                        </div>
                        <Link
                            v-if="(g.link_kind === 'reservation' && g.reservation_id) || g.customer_id"
                            :href="g.link_kind === 'reservation' && g.reservation_id
                                ? route('admin.reservations.show', g.reservation_id)
                                : route('admin.customers.show', g.customer_id)"
                            class="inline-flex items-center gap-1 text-xs text-brand-primary hover:underline mt-1"
                            @click="open = false"
                        >
                            {{ g.link_kind === 'reservation' ? '予約詳細を開く' : '顧客詳細を開く' }}
                            <ArrowRight :size="12" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </UiDialog>
</template>
