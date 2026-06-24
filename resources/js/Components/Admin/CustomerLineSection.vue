<script setup>
import { ref, watch, computed, onUnmounted, nextTick } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import QRCode from 'qrcode';

const POLL_MS = 12000;

/** LINE 公式アカウントの友だち追加用 QR（公式配布 URL） */
const LINE_OFFICIAL_ADD_FRIEND_QR_URL =
    'https://qr-official.line.me/gs/M_723svxpq_GW.png?oat_content=qr';

const props = defineProps({
    /** 顧客詳細用。lineApi 指定時は省略可 */
    customer: {
        type: Object,
        default: null,
    },
    shops: {
        type: Array,
        default: () => [],
    },
    /**
     * 予約詳細用: { context, customer_id, reservation_id, shops, can_issue_line_link, line_contacts }
     */
    lineApi: {
        type: Object,
        default: null,
    },
    /** 親カード内に埋め込むときは embedded（外枠・見出しを抑える） */
    variant: {
        type: String,
        default: 'card',
    },
});

const isEmbedded = computed(() => props.variant === 'embedded');

const lineMode = computed(() => props.lineApi?.context ?? 'customer');

const shopForm = useForm({ shop_id: props.customer?.shop_id ?? null });

const shopDirty = computed(() => {
    const cur = props.customer?.shop_id;
    const sel = shopForm.shop_id;
    const nCur = cur === null || cur === undefined || cur === '' ? null : Number(cur);
    const nSel = sel === null || sel === undefined || sel === '' ? null : Number(sel);
    return nCur !== nSel;
});

watch(
    () => props.customer?.shop_id,
    (id) => {
        shopForm.shop_id = id ?? null;
        shopForm.clearErrors();
    },
);

function saveResponsibleShop() {
    shopForm
        .transform((data) => ({
            shop_id:
                data.shop_id === '' || data.shop_id === undefined || data.shop_id === null
                    ? null
                    : data.shop_id,
        }))
        .patch(route('admin.customers.update-responsible-shop', props.customer?.id), {
            preserveScroll: true,
        });
}

const page = usePage();

const contacts = computed(() => props.lineApi?.line_contacts ?? props.customer?.line_contacts ?? []);

const effectiveShops = computed(() =>
    props.lineApi?.shops?.length ? props.lineApi.shops : props.shops,
);
const selectedContactId = ref(contacts.value[0]?.id ?? null);
const selectedContact = computed(() => contacts.value.find((c) => c.id === selectedContactId.value) ?? null);

let messagesPollTimer = null;

function stopMessagesPolling() {
    if (messagesPollTimer !== null) {
        clearInterval(messagesPollTimer);
        messagesPollTimer = null;
    }
}

function startMessagesPolling(contactId) {
    stopMessagesPolling();
    if (!contactId) {
        return;
    }
    messagesPollTimer = setInterval(() => {
        if (document.visibilityState !== 'visible' || sending.value) {
            return;
        }
        fetchMessages(contactId, { silent: true });
    }, POLL_MS);
}

onUnmounted(() => {
    stopMessagesPolling();
});
const messages = ref([]);
const loadingMessages = ref(false);
const marking = ref(false);

// 未読（受信かつ未読）件数。0 より大きいときのみ「確認」ボタンを押下可能にする
const unreadCount = computed(
    () => messages.value.filter((m) => m.direction === 'inbound' && !m.admin_read_at).length,
);
const hasUnread = computed(() => unreadCount.value > 0);

// 「確認」ボタン: スレッドの未読受信メッセージを既読化する
async function markAsRead() {
    const contactId = selectedContactId.value;
    if (!contactId || !hasUnread.value || marking.value) {
        return;
    }
    marking.value = true;
    try {
        const url =
            lineMode.value === 'reservation'
                ? route('admin.reservations.line.mark-read', {
                      reservation: props.lineApi.reservation_id,
                      contact: contactId,
                  })
                : route('admin.customers.line.mark-read', {
                      customer: props.customer.id,
                      contact: contactId,
                  });
        const { data } = await axios.post(url);
        const readAt = data.admin_read_at ?? new Date().toISOString();
        messages.value = messages.value.map((m) =>
            m.direction === 'inbound' && !m.admin_read_at ? { ...m, admin_read_at: readAt } : m,
        );
    } catch {
        sendError.value = '既読の更新に失敗しました。';
    } finally {
        marking.value = false;
    }
}

// トーク表示：スクロールコンテナと「最新（一番下）」への自動スクロール
const chatScrollRef = ref(null);
function scrollChatToBottom() {
    nextTick(() => {
        const el = chatScrollRef.value;
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    });
}
// 最新メッセージが変わったとき（初回読込・連絡先切替・送信・新着）に一番下へ
watch(
    () => (messages.value.length ? messages.value[messages.value.length - 1].id : null),
    () => scrollChatToBottom(),
);

// タブ内に配置され初期は非表示(display:none)のため、可視化された瞬間に一番下へスクロールする
let chatVisibilityObserver = null;
watch(chatScrollRef, (el) => {
    if (chatVisibilityObserver) {
        chatVisibilityObserver.disconnect();
        chatVisibilityObserver = null;
    }
    if (el && typeof IntersectionObserver !== 'undefined') {
        chatVisibilityObserver = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                scrollChatToBottom();
            }
        });
        chatVisibilityObserver.observe(el);
    }
});

// トーク拡大モーダル
const isExpanded = ref(false);
function onModalKeydown(e) {
    if (e.key === 'Escape') {
        isExpanded.value = false;
    }
}
watch(isExpanded, (open) => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = open ? 'hidden' : '';
    }
    if (open) {
        scrollChatToBottom();
        window.addEventListener('keydown', onModalKeydown);
    } else {
        window.removeEventListener('keydown', onModalKeydown);
    }
});
onUnmounted(() => {
    window.removeEventListener('keydown', onModalKeydown);
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
    if (chatVisibilityObserver) {
        chatVisibilityObserver.disconnect();
        chatVisibilityObserver = null;
    }
});
const sendText = ref('');
const sending = ref(false);
const sendError = ref('');
const unlinking = ref(false);

// 画像添付
const imageInputRef = ref(null);
const selectedImageFile = ref(null);
const selectedImagePreview = ref('');
const IMAGE_MAX_BYTES = 5 * 1024 * 1024; // 5MB (LINE 仕様上限は 10MB だが負荷バランスで 5MB に設定)
const IMAGE_ALLOWED_MIMES = ['image/jpeg', 'image/png'];
// label[for] / input[id] を一意にして複数インスタンス共存にも対応
const imageInputId = `line-image-input-${Math.random().toString(36).slice(2, 10)}`;

const linkIssueLabel = ref('');
const linkIssueClientError = ref('');
const lastLineLiffUrl = ref('');
const lineLiffQrDataUrl = ref('');
const showLineAddFriendQr = ref(true);

watch(
    contacts,
    (list) => {
        if (!list?.length) {
            selectedContactId.value = null;
            messages.value = [];
            return;
        }
        if (!list.some((c) => c.id === selectedContactId.value)) {
            selectedContactId.value = list[0].id;
        }
    },
    { immediate: true },
);

watch(
    selectedContactId,
    (id) => {
        stopMessagesPolling();
        if (id) {
            fetchMessages(id);
            startMessagesPolling(id);
        } else {
            messages.value = [];
        }
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.line_liff_link_url,
    async (url) => {
        if (!url) {
            return;
        }
        lastLineLiffUrl.value = url;
        try {
            lineLiffQrDataUrl.value = await QRCode.toDataURL(url, {
                width: 220,
                margin: 2,
                color: { dark: '#111827', light: '#ffffff' },
            });
        } catch {
            lineLiffQrDataUrl.value = '';
        }
    },
    { immediate: true },
);

async function fetchMessages(contactId, { silent = false } = {}) {
    if (!silent) {
        loadingMessages.value = true;
        sendError.value = '';
    }
    try {
        const url =
            lineMode.value === 'reservation'
                ? route('admin.reservations.line.contact-messages', {
                      reservation: props.lineApi.reservation_id,
                      contact: contactId,
                  })
                : route('admin.customers.line.contact-messages', {
                      customer: props.customer.id,
                      contact: contactId,
                  });
        const { data } = await axios.get(url);
        messages.value = data.messages ?? [];
    } catch {
        if (!silent) {
            messages.value = [];
            sendError.value = 'メッセージの取得に失敗しました。';
        }
    } finally {
        if (!silent) {
            loadingMessages.value = false;
        }
    }
}

function issueLinkToken() {
    linkIssueClientError.value = '';
    if (lineMode.value === 'reservation') {
        router.post(route('admin.reservations.line.link-token', props.lineApi.reservation_id), {}, { preserveScroll: true });
        return;
    }
    const label = linkIssueLabel.value.trim();
    if (!label) {
        linkIssueClientError.value = '表示名を入力してください（リストから「本人」「母」などを選んでも構いません）。';
        return;
    }

    router.post(
        route('admin.customers.line.link-token', props.customer.id),
        { label },
        { preserveScroll: true },
    );
}

function copyLineLiffUrl() {
    if (!lastLineLiffUrl.value || !navigator.clipboard?.writeText) {
        return;
    }
    navigator.clipboard.writeText(lastLineLiffUrl.value);
}

function onImageSelected(event) {
    const file = event.target?.files?.[0];
    if (!file) {
        clearSelectedImage();
        return;
    }
    if (!IMAGE_ALLOWED_MIMES.includes(file.type)) {
        sendError.value = '画像は JPEG / PNG 形式のみ送信できます。';
        if (imageInputRef.value) imageInputRef.value.value = '';
        return;
    }
    if (file.size > IMAGE_MAX_BYTES) {
        const maxMb = (IMAGE_MAX_BYTES / 1024 / 1024).toFixed(0);
        const curMb = (file.size / 1024 / 1024).toFixed(2);
        sendError.value = `画像サイズは ${maxMb}MB 以下にしてください。(現在: ${curMb}MB)`;
        if (imageInputRef.value) imageInputRef.value.value = '';
        return;
    }
    sendError.value = '';
    selectedImageFile.value = file;
    const reader = new FileReader();
    reader.onload = (e) => {
        selectedImagePreview.value = e.target?.result || '';
    };
    reader.readAsDataURL(file);
}

function clearSelectedImage() {
    selectedImageFile.value = null;
    selectedImagePreview.value = '';
    if (imageInputRef.value) imageInputRef.value.value = '';
}

function openImagePicker() {
    if (imageInputRef.value) imageInputRef.value.click();
}

async function sendMessage() {
    const text = sendText.value.trim();
    const hasImage = !!selectedImageFile.value;
    if ((!text && !hasImage) || !selectedContactId.value) {
        return;
    }
    sending.value = true;
    sendError.value = '';
    try {
        const url =
            lineMode.value === 'reservation'
                ? route('admin.reservations.line.send', {
                      reservation: props.lineApi.reservation_id,
                      contact: selectedContactId.value,
                  })
                : route('admin.customers.line.send', {
                      customer: props.customer.id,
                      contact: selectedContactId.value,
                  });

        if (hasImage) {
            // 画像送信時は multipart/form-data。LINE仕様で1メッセージ1枚なので text と排他
            const fd = new FormData();
            fd.append('image_file', selectedImageFile.value);
            await axios.post(url, fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            clearSelectedImage();
        } else {
            await axios.post(url, { text });
            sendText.value = '';
        }

        await fetchMessages(selectedContactId.value, { silent: true });
    } catch (e) {
        sendError.value = e.response?.data?.message || '送信に失敗しました。';
    } finally {
        sending.value = false;
    }
}

function formatLineTime(iso) {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return '';
    }
    return d.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', hour12: false });
}

function unlinkSelectedContact() {
    if (!selectedContactId.value || unlinking.value) {
        return;
    }
    const c = selectedContact.value;
    const label = c?.label?.trim() || 'この連絡先';
    const ok = window.confirm(
        `「${label}」の LINE 連携を解除しますか？\n\nこの連絡先のメッセージ履歴も削除されます。解除後は同じ LINE アカウントを別の顧客・予約に紐づけられます。`,
    );
    if (!ok) {
        return;
    }
    stopMessagesPolling();
    unlinking.value = true;
    if (lineMode.value === 'reservation') {
        router.delete(
            route('admin.reservations.line.contact-destroy', {
                reservation: props.lineApi.reservation_id,
                contact: selectedContactId.value,
            }),
            {
                preserveScroll: true,
                onFinish: () => {
                    unlinking.value = false;
                },
            },
        );

        return;
    }
    if (!props.customer?.id) {
        unlinking.value = false;

        return;
    }
    router.delete(
        route('admin.customers.line.contact-destroy', {
            customer: props.customer.id,
            contact: selectedContactId.value,
        }),
        {
            preserveScroll: true,
            onFinish: () => {
                unlinking.value = false;
            },
        },
    );
}

const contactAvatarChar = computed(() => {
    const label = selectedContact.value?.label?.trim();
    if (!label) {
        return 'L';
    }
    return label.slice(0, 1);
});

const lineFormError = computed(() => page.props.errors?.line);
const labelFieldError = computed(() => page.props.errors?.label);
const canIssueLink = computed(() => {
    if (lineMode.value === 'reservation') {
        return props.lineApi?.can_issue_line_link === true;
    }
    return !!(props.customer?.shop_id && linkIssueLabel.value.trim().length > 0);
});
</script>

<template>
    <div
        v-if="lineApi || customer"
        :class="isEmbedded ? 'min-w-0' : 'bg-white overflow-hidden shadow-sm sm:rounded-lg'"
    >
        <div :class="isEmbedded ? '' : 'p-6'">
            <h3
                v-if="!isEmbedded"
                class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2"
            >
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                    />
                </svg>
                LINE メッセージ
            </h3>

            <div v-if="lineFormError" class="mb-4 text-sm text-red-600">{{ lineFormError }}</div>

            <div v-if="lineMode === 'reservation'" class="mb-4 text-sm text-gray-600">
                イベント予約のご本人向け LINE です（1 予約 1 アカウント）。サンクスメールの連携 URL でも同じ手順で連携できます。
            </div>
            <div
                v-if="lineMode === 'reservation' && lineApi && !lineApi.can_issue_line_link"
                class="mb-4 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded p-3"
            >
                イベントに店舗が未設定のため、ここから連携用リンクを発行できません。イベントに店舗を紐づけてください。
            </div>

            <div v-if="lineMode === 'customer'" class="mb-4 pb-4 border-b border-gray-200">
                <label for="customer-line-responsible-shop" class="block text-sm font-medium text-gray-700 mb-1">
                    担当店舗（LINE 等）
                </label>
                <p class="text-xs text-gray-500 mb-2">LINE 連携・メッセージ送信の対象店舗です。</p>
                <div v-if="effectiveShops.length" class="flex flex-wrap items-end gap-2">
                    <div class="flex-1 min-w-[12rem]">
                        <select
                            id="customer-line-responsible-shop"
                            v-model="shopForm.shop_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option :value="null">未設定</option>
                            <option v-for="s in effectiveShops" :key="s.id" :value="s.id">
                                {{ s.name }}
                            </option>
                        </select>
                        <p v-if="shopForm.errors.shop_id" class="mt-1 text-sm text-red-600">{{ shopForm.errors.shop_id }}</p>
                    </div>
                    <button
                        type="button"
                        class="px-3 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
                        :disabled="shopForm.processing || !shopDirty"
                        @click="saveResponsibleShop"
                    >
                        {{ shopForm.processing ? '保存中…' : '保存' }}
                    </button>
                </div>
                <p v-else class="text-sm text-gray-500">店舗マスタがありません。</p>
            </div>

            <div
                v-if="lineMode === 'customer' && !customer?.shop_id"
                class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded p-3 mb-4"
            >
                担当店舗が未設定のため、LINE 連携・送信は利用できません。上で店舗を選んで保存してください。
            </div>

            <template v-if="lineMode === 'reservation' || customer?.shop_id">
                <div v-if="lineMode === 'customer'" class="mb-4 max-w-lg">
                    <label for="line-link-issue-label" class="block text-sm font-medium text-gray-700 mb-1">
                        連携後の表示名 <span class="text-red-500">*</span>
                    </label>
                    <p class="text-xs text-gray-500 mb-1">本人・母など。入力補完から選ぶか、自由に入力してください。</p>
                    <input
                        id="line-link-issue-label"
                        v-model="linkIssueLabel"
                        type="text"
                        list="line-link-label-options"
                        maxlength="50"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="例: 本人"
                        autocomplete="off"
                    />
                    <datalist id="line-link-label-options">
                        <option value="本人" />
                        <option value="母" />
                        <option value="父" />
                    </datalist>
                    <p v-if="linkIssueClientError" class="mt-1 text-sm text-red-600">{{ linkIssueClientError }}</p>
                    <p v-if="labelFieldError" class="mt-1 text-sm text-red-600">{{ labelFieldError }}</p>
                </div>

                <div class="mb-4 space-y-3">
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="px-3 py-2 text-sm rounded-md border border-green-600 text-green-700 bg-white hover:bg-green-50 shrink-0"
                            :aria-expanded="showLineAddFriendQr"
                            aria-controls="line-official-add-friend-qr"
                            @click="showLineAddFriendQr = !showLineAddFriendQr"
                        >
                            友だち追加
                        </button>
                        <button
                            type="button"
                            class="px-3 py-2 text-sm rounded-md bg-green-600 text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!canIssueLink"
                            @click="issueLinkToken"
                        >
                            {{ lineMode === 'reservation' ? '連携用リンクを発行（本人）' : '連携用リンクを発行' }}
                        </button>
                        <a
                            :href="route('admin.line-unknown-inbox.index')"
                            class="px-3 py-2 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 inline-flex items-center"
                        >
                            LINE 不明メッセージ
                        </a>
                    </div>
                    <div
                        v-show="showLineAddFriendQr"
                        id="line-official-add-friend-qr"
                        class="flex flex-wrap items-center gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4"
                    >
                        <div class="shrink-0 rounded-lg bg-white p-2 shadow-sm border border-gray-100">
                            <img
                                :src="LINE_OFFICIAL_ADD_FRIEND_QR_URL"
                                width="200"
                                height="200"
                                class="block max-w-full h-auto"
                                alt="LINE 公式アカウントの友だち追加用 QR コード"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                            />
                        </div>
                        <p class="text-xs text-gray-600 max-w-md leading-relaxed">
                            お客様に公式アカウントの友だち追加を案内するときにご利用ください。
                        </p>
                    </div>
                </div>

                <div
                    v-if="lastLineLiffUrl"
                    class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4"
                >
                    <p class="text-sm font-medium text-gray-800 mb-3">発行した連携 URL（スマホで読み取り）</p>
                    <div class="flex flex-wrap items-start gap-6">
                        <div class="shrink-0 rounded bg-white p-2 shadow-sm border border-gray-100">
                            <img
                                v-if="lineLiffQrDataUrl"
                                :src="lineLiffQrDataUrl"
                                width="220"
                                height="220"
                                class="block"
                                alt="LINE 連携用 QR コード"
                            />
                            <p v-else class="text-xs text-gray-500 w-[220px] h-[220px] flex items-center justify-center">QR を生成中…</p>
                        </div>
                        <div class="flex-1 min-w-[200px] max-w-xl">
                            <input
                                type="text"
                                readonly
                                class="w-full text-xs border border-gray-300 rounded-md px-2 py-2 bg-white text-gray-800 font-mono break-all"
                                :value="lastLineLiffUrl"
                            />
                            <button
                                type="button"
                                class="mt-2 text-sm text-indigo-600 hover:text-indigo-800"
                                @click="copyLineLiffUrl"
                            >
                                URL をコピー
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!contacts.length" class="text-sm text-gray-500 mb-4">連携済みの LINE がありません。上記リンクからお客様に連携してもらってください。</div>

                <template v-else>
                    <Teleport to="body" :disabled="!isExpanded">
                    <div
                        :class="isExpanded ? 'line-chat-overlay fixed inset-0 z-[60] flex items-start justify-center bg-black/40 p-3 sm:p-6 overflow-y-auto' : 'contents'"
                        @click.self="isExpanded = false"
                    >
                    <div
                        :class="isExpanded ? 'line-chat-modal w-full max-w-3xl my-auto bg-white rounded-2xl shadow-2xl p-4 sm:p-5' : 'contents'"
                    >
                    <div v-if="isExpanded" class="flex items-center justify-between mb-3">
                        <h3 class="text-base font-semibold text-gray-800">LINE トーク（拡大表示）</h3>
                        <button
                            type="button"
                            class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors"
                            title="閉じる"
                            aria-label="閉じる"
                            @click="isExpanded = false"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div
                        class="flex border-b border-gray-200 mb-3 gap-1 min-w-0 -mt-0.5"
                        role="tablist"
                        aria-label="LINE 連絡先の切り替え"
                    >
                        <button
                            v-for="c in contacts"
                            :key="c.id"
                            type="button"
                            role="tab"
                            :aria-selected="selectedContactId === c.id"
                            :class="[
                                'min-w-0 flex-1 px-2 sm:px-3 py-2.5 text-sm font-medium rounded-t-md border-b-2 transition-colors text-center',
                                selectedContactId === c.id
                                    ? 'border-indigo-600 text-indigo-700 bg-indigo-50/50'
                                    : 'border-transparent text-gray-600 hover:text-gray-900 hover:bg-gray-50',
                            ]"
                            @click="selectedContactId = c.id"
                        >
                            <span
                                class="block truncate"
                                :title="`${c.label}（${c.line_user_id_masked}）`"
                            >
                                {{ c.label }}（{{ c.line_user_id_masked }}）
                            </span>
                        </button>
                        <button
                            v-if="!isExpanded"
                            type="button"
                            class="shrink-0 self-center mb-1 ml-0.5 p-1.5 rounded-md text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                            title="拡大表示して大きな画面でやり取りする"
                            aria-label="拡大表示"
                            @click="isExpanded = true"
                        >
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                            </svg>
                        </button>
                    </div>

                    <div
                        v-if="selectedContactId"
                        class="flex flex-wrap items-center justify-between gap-2 mb-2 px-0.5"
                    >
                        <p class="text-xs text-gray-500 max-w-xl leading-relaxed">
                            誤って連携した場合など、解除するとトーク履歴も削除され、同じ LINE を別の紐づけに使えます。
                        </p>
                        <button
                            type="button"
                            class="shrink-0 px-3 py-1.5 text-sm rounded-md border border-red-200 text-red-700 bg-white hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="unlinking || sending || loadingMessages"
                            @click="unlinkSelectedContact"
                        >
                            {{ unlinking ? '解除中…' : 'LINE 連携を解除' }}
                        </button>
                    </div>

                    <div class="line-chat-panel rounded-xl overflow-hidden border border-gray-200/80 shadow-inner" :class="isExpanded ? 'mb-0' : 'mb-3'">
                        <div
                            ref="chatScrollRef"
                            class="line-chat-scroll overflow-y-auto px-3 py-4"
                            :class="isExpanded ? 'min-h-[55vh] max-h-[65vh]' : 'min-h-[220px] max-h-80'"
                        >
                            <p v-if="loadingMessages" class="text-sm text-gray-500 text-center py-8">読み込み中…</p>
                            <template v-else>
                                <p
                                    v-if="!messages.length"
                                    class="text-sm text-gray-500 text-center py-10"
                                >
                                    メッセージはまだありません。
                                </p>
                                <div v-else class="space-y-4">
                                    <div
                                        v-for="m in messages"
                                        :key="m.id"
                                        class="flex w-full"
                                        :class="m.direction === 'outbound' ? 'justify-end' : 'justify-start'"
                                    >
                                        <!-- 受信（左・LINE風） -->
                                        <template v-if="m.direction !== 'outbound'">
                                            <div class="flex items-end gap-1.5 max-w-[min(100%,28rem)]">
                                                <div
                                                    class="line-avatar shrink-0"
                                                    :title="selectedContact?.label || 'お客様'"
                                                    aria-hidden="true"
                                                >
                                                    {{ contactAvatarChar }}
                                                </div>
                                                <div class="line-bubble-wrap line-bubble-wrap--in shrink min-w-0">
                                                    <!-- 画像メッセージ -->
                                                    <a
                                                        v-if="m.message_type === 'image' && m.image_url"
                                                        :href="m.image_url"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="block max-w-[14rem] rounded-2xl overflow-hidden border border-gray-200"
                                                    >
                                                        <img :src="m.image_url" alt="受信画像" class="w-full h-auto block" loading="lazy" />
                                                    </a>
                                                    <!-- それ以外の非テキスト（sticker/video等） -->
                                                    <div
                                                        v-else-if="m.message_type && m.message_type !== 'text'"
                                                        class="line-rich-card text-gray-900"
                                                    >
                                                        <p class="line-rich-card__title">LINE</p>
                                                        <p class="text-sm whitespace-pre-wrap">
                                                            {{ m.text || '（テキスト以外のメッセージ）' }}
                                                        </p>
                                                    </div>
                                                    <div v-else class="line-bubble line-bubble--in text-gray-900">
                                                        <span class="whitespace-pre-wrap text-[15px] leading-relaxed">{{
                                                            m.text || '（テキスト以外）'
                                                        }}</span>
                                                    </div>
                                                </div>
                                                <span class="line-time-side shrink-0 self-end pb-0.5">{{
                                                    formatLineTime(m.created_at)
                                                }}</span>
                                            </div>
                                        </template>
                                        <!-- 送信（右・LINE風） -->
                                        <template v-else>
                                            <div class="flex items-end gap-2 max-w-[min(100%,28rem)] flex-row-reverse">
                                                <div class="line-bubble-wrap line-bubble-wrap--out shrink min-w-0">
                                                    <a
                                                        v-if="m.message_type === 'image' && m.image_url"
                                                        :href="m.image_url"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="block max-w-[14rem] rounded-2xl overflow-hidden border border-gray-200"
                                                    >
                                                        <img :src="m.image_url" alt="送信画像" class="w-full h-auto block" loading="lazy" />
                                                    </a>
                                                    <div v-else class="line-bubble line-bubble--out text-gray-900">
                                                        <span class="whitespace-pre-wrap text-[15px] leading-relaxed">{{
                                                            m.text || '（テキスト以外）'
                                                        }}</span>
                                                    </div>
                                                </div>
                                                <div class="line-out-meta shrink-0 self-end pb-0.5">
                                                    <span class="line-out-meta__status">送信済</span>
                                                    <span class="line-out-meta__time">{{ formatLineTime(m.created_at) }}</span>
                                                    <span
                                                        v-if="m.sent_by"
                                                        class="line-out-meta__sender"
                                                        :title="m.sent_by"
                                                    >{{ m.sent_by }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="line-composer border-t border-gray-200/90 bg-white px-3 py-2.5">
                            <div v-if="sendError" class="text-sm text-red-600 mb-2">{{ sendError }}</div>

                            <!-- 画像プレビュー -->
                            <div v-if="selectedImagePreview" class="mb-2 inline-flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 p-2">
                                <img :src="selectedImagePreview" alt="送信プレビュー" class="h-20 w-20 object-cover rounded" />
                                <div class="flex flex-col text-xs text-gray-600">
                                    <span class="font-medium text-gray-800 truncate max-w-[14rem]">{{ selectedImageFile?.name }}</span>
                                    <span>{{ (selectedImageFile?.size / 1024).toFixed(0) }} KB</span>
                                    <button
                                        type="button"
                                        class="mt-1 self-start text-red-600 hover:text-red-800"
                                        @click="clearSelectedImage"
                                    >削除</button>
                                </div>
                            </div>

                            <!-- file input は label とは独立して body 直前に配置する（sr-only でクリックが届かないブラウザ対策） -->
                            <input
                                ref="imageInputRef"
                                :id="imageInputId"
                                type="file"
                                accept="image/jpeg,image/png"
                                style="position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0;"
                                :disabled="!!selectedImageFile"
                                @change="onImageSelected"
                            />

                            <!-- 確認（既読）ボタン：未読がある時のみ押下可 -->
                            <div class="flex justify-end mb-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-45 disabled:cursor-not-allowed"
                                    :disabled="!hasUnread || marking"
                                    @click="markAsRead"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ marking ? '更新中…' : (hasUnread ? `確認 (${unreadCount})` : '確認') }}
                                </button>
                            </div>

                            <div class="flex items-end gap-2">
                                <div class="line-composer__icons shrink-0 flex items-center gap-1 text-gray-400 pb-2 pr-1">
                                    <!-- label[for] で input[id] と明示的に紐付け（HTML標準：label クリック → input click トリガー） -->
                                    <label
                                        :for="imageInputId"
                                        class="hover:text-gray-700 cursor-pointer inline-flex p-0.5 rounded opacity-65 hover:opacity-100"
                                        :class="{ 'opacity-40 cursor-not-allowed pointer-events-none': !!selectedImageFile }"
                                        title="画像を添付（JPEG/PNG・最大5MB）"
                                    >
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </label>
                                </div>
                                <textarea
                                    v-model="sendText"
                                    rows="2"
                                    class="line-composer__input flex-1 min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-[#06C755]/50 focus:ring-1 focus:ring-[#06C755]/30 resize-y min-h-[44px] max-h-32"
                                    :placeholder="selectedImageFile ? '画像を送信します（テキストは併送できません）' : 'Enterで改行 ／ 送信は「送信」ボタンから'"
                                    maxlength="4500"
                                    :disabled="!!selectedImageFile"
                                />
                                <button
                                    type="button"
                                    class="line-send-btn shrink-0 flex items-center gap-1 rounded-md px-4 py-2.5 text-sm font-medium text-white shadow-sm disabled:opacity-45 disabled:cursor-not-allowed"
                                    :disabled="sending || (!sendText.trim() && !selectedImageFile)"
                                    @click="sendMessage"
                                >
                                    {{ sending ? '送信中…' : '送信' }}
                                    <svg class="w-4 h-4 opacity-90" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    </div>
                    </div>
                    </Teleport>
                </template>
            </template>
        </div>
    </div>
</template>

<style scoped>
.line-chat-panel {
    background-color: #ececec;
}

/* トーク拡大モーダル：フワッと出現させる */
.line-chat-overlay {
    animation: lineOverlayFade 0.18s ease-out;
}
.line-chat-modal {
    animation: lineModalPop 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes lineOverlayFade {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
@keyframes lineModalPop {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.97);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.line-chat-scroll {
    scrollbar-width: thin;
    scrollbar-color: #c4c4c4 transparent;
}

.line-avatar {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 9999px;
    background: linear-gradient(145deg, #9ca3af, #6b7280);
    color: #fff;
    font-size: 0.8125rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
}

.line-bubble-wrap {
    position: relative;
    filter: drop-shadow(0 1px 0.5px rgb(0 0 0 / 0.04));
}

.line-bubble-wrap--in {
    margin-left: 2px;
}

.line-bubble-wrap--out {
    margin-right: 2px;
}

.line-bubble {
    position: relative;
    padding: 0.5rem 0.75rem;
    max-width: 100%;
    word-break: break-word;
}

.line-bubble--in {
    background: #f0f0f0;
    border-radius: 18px;
    border-top-left-radius: 18px;
    border-bottom-left-radius: 4px;
}

.line-bubble-wrap--in::before {
    content: '';
    position: absolute;
    left: -5px;
    bottom: 8px;
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 5px 6px 5px 0;
    border-color: transparent #f0f0f0 transparent transparent;
    z-index: 0;
}

.line-bubble--out {
    background: #d9e5ff;
    border-radius: 18px;
    border-top-right-radius: 18px;
    border-bottom-right-radius: 4px;
}

.line-bubble-wrap--out::after {
    content: '';
    position: absolute;
    right: -5px;
    bottom: 8px;
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 5px 0 5px 6px;
    border-color: transparent transparent transparent #d9e5ff;
    z-index: 0;
}

.line-time-side {
    font-size: 0.6875rem;
    color: #9ca3af;
    line-height: 1.2;
    min-width: 2.25rem;
}

.line-out-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.125rem;
    font-size: 0.6875rem;
    color: #9ca3af;
    line-height: 1.15;
    max-width: 5rem;
}

.line-out-meta__status {
    letter-spacing: 0.02em;
}

.line-out-meta__time {
    font-variant-numeric: tabular-nums;
}

.line-out-meta__sender {
    font-size: 0.625rem;
    opacity: 0.85;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.line-rich-card {
    border: 1px solid #d1d5db;
    border-radius: 12px;
    background: #fff;
    padding: 0.625rem 0.75rem;
    max-width: 100%;
}

.line-rich-card__title {
    font-weight: 700;
    font-size: 0.8125rem;
    margin-bottom: 0.25rem;
}

.line-composer__icon-btn {
    padding: 0.125rem;
    border-radius: 0.25rem;
    opacity: 0.65;
    pointer-events: none;
}

.line-send-btn {
    background-color: #06c755;
}

.line-send-btn:hover:not(:disabled) {
    background-color: #05b34c;
}
</style>
