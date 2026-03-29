<script setup>
import { ref, watch, computed, onUnmounted } from 'vue';
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
const sendText = ref('');
const sending = ref(false);
const sendError = ref('');
const unlinking = ref(false);

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

async function sendMessage() {
    const text = sendText.value.trim();
    if (!text || !selectedContactId.value) {
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
        await axios.post(url, { text });
        sendText.value = '';
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

function onComposerKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
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

                    <div class="line-chat-panel rounded-xl overflow-hidden border border-gray-200/80 shadow-inner mb-3">
                        <div class="line-chat-scroll min-h-[220px] max-h-80 overflow-y-auto px-3 py-4">
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
                                                    <div
                                                        v-if="m.message_type && m.message_type !== 'text'"
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
                                                    <div class="line-bubble line-bubble--out text-gray-900">
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
                            <div class="flex items-end gap-2">
                                <div class="line-composer__icons shrink-0 hidden sm:flex items-center gap-1 text-gray-400 pb-2 pr-1" aria-hidden="true">
                                    <span class="line-composer__icon-btn" title="（装飾）">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </span>
                                    <span class="line-composer__icon-btn" title="（装飾）">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                    </span>
                                </div>
                                <textarea
                                    v-model="sendText"
                                    rows="2"
                                    class="line-composer__input flex-1 min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-[#06C755]/50 focus:ring-1 focus:ring-[#06C755]/30 resize-y min-h-[44px] max-h-32"
                                    placeholder="Enterで送信 / Shift + Enterで改行"
                                    maxlength="4500"
                                    @keydown="onComposerKeydown"
                                />
                                <button
                                    type="button"
                                    class="line-send-btn shrink-0 flex items-center gap-1 rounded-md px-4 py-2.5 text-sm font-medium text-white shadow-sm disabled:opacity-45 disabled:cursor-not-allowed"
                                    :disabled="sending || !sendText.trim()"
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
                </template>
            </template>
        </div>
    </div>
</template>

<style scoped>
.line-chat-panel {
    background-color: #ececec;
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
