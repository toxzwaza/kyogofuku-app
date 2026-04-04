<template>
    <div class="success-reservation-root w-full">
        <!-- 親に rv-steps が無いときだけ（Event/Show インライン完了など）フローと同じステップ表示 -->
        <div v-if="!omitStepIndicator" class="rv-steps success-reservation-steps">
            <div class="rv-step done">
                <span class="rv-step-num">1</span>
                <span class="rv-step-label">会場・日時</span>
            </div>
            <div class="rv-step-line done"></div>
            <div class="rv-step done">
                <span class="rv-step-num">2</span>
                <span class="rv-step-label">お客様情報</span>
            </div>
            <div class="rv-step-line done"></div>
            <div class="rv-step done">
                <span class="rv-step-num">3</span>
                <span class="rv-step-label">確認</span>
            </div>
        </div>

        <div class="rv-panel success-reservation-panel">
            <h2 class="rv-panel-title">予約完了</h2>

            <div class="success-reservation-celebrate">
                <p class="success-reservation-kicker">COMPLETE</p>

                <p class="success-reservation-thanks">ご予約ありがとうございます</p>
                <p class="success-reservation-lead">
                    ご予約内容をメールでお送りしました。<br />
                    当日のご来場をお待ちしております。
                </p>
            </div>

            <h3 class="success-reservation-subheading">ご予約内容</h3>
            <dl class="rv-confirm success-reservation-dl w-full max-w-lg mx-auto box-border">
                <div class="rv-confirm-row">
                    <dt>予約日時</dt>
                    <dd>
                        <template v-if="selectedVenueName">{{ selectedVenueName }} / </template>
                        {{ formatDateTime(formData?.reservation_datetime) }}
                    </dd>
                </div>
                <div class="rv-confirm-row">
                    <dt>お名前</dt>
                    <dd>{{ formData?.name ?? '—' }}</dd>
                </div>
                <div class="rv-confirm-row">
                    <dt>メールアドレス</dt>
                    <dd class="break-all">{{ formData?.email ?? '—' }}</dd>
                </div>
                <div class="rv-confirm-row">
                    <dt>電話番号</dt>
                    <dd>{{ formData?.phone ?? '—' }}</dd>
                </div>

                <template v-if="event?.form_type === 'reservation_hakama'">
                    <div v-if="formData?.furigana" class="rv-confirm-row">
                        <dt>フリガナ</dt>
                        <dd>{{ formData.furigana }}</dd>
                    </div>
                    <div v-if="formData?.address" class="rv-confirm-row">
                        <dt>住所</dt>
                        <dd>{{ formData.address }}</dd>
                    </div>
                    <div v-if="formData?.school_name" class="rv-confirm-row">
                        <dt>学校名</dt>
                        <dd>{{ formData.school_name }}</dd>
                    </div>
                    <div class="rv-confirm-row">
                        <dt>卒業式</dt>
                        <dd>{{ formatGraduationCeremonyDate(formData) }}</dd>
                    </div>
                    <div class="rv-confirm-row">
                        <dt>来店人数</dt>
                        <dd>{{ formData?.visitor_count != null ? formData.visitor_count + '名' : '—' }}</dd>
                    </div>
                    <div class="rv-confirm-row">
                        <dt>お車で来店</dt>
                        <dd>
                            {{ formData?.parking_usage || '—' }}
                            <template v-if="formData?.parking_usage === 'あり' && formData?.parking_car_count">
                                （{{ formData.parking_car_count }}台）
                            </template>
                        </dd>
                    </div>
                    <div v-if="formData?.visit_reasons?.length" class="rv-confirm-row">
                        <dt>来店動機</dt>
                        <dd>{{ formData.visit_reasons.join('、') }}</dd>
                    </div>
                    <div v-if="formData?.considering_plans?.length" class="rv-confirm-row">
                        <dt>検討中のプラン</dt>
                        <dd>{{ formData.considering_plans.join('、') }}</dd>
                    </div>
                    <div class="rv-confirm-row">
                        <dt>好一での振袖利用</dt>
                        <dd>
                            {{
                                formData?.koichi_furisode_used === true
                                    ? 'あり'
                                    : formData?.koichi_furisode_used === false
                                      ? 'なし'
                                      : '—'
                            }}
                        </dd>
                    </div>
                    <div class="rv-confirm-row">
                        <dt>ご紹介者様お名前</dt>
                        <dd>{{ formData?.referred_by_name?.trim() ? formData.referred_by_name : '—' }}</dd>
                    </div>
                    <div class="rv-confirm-row">
                        <dt>お問い合わせ内容</dt>
                        <dd class="whitespace-pre-wrap">{{ formData?.inquiry_message?.trim() ? formData.inquiry_message : '—' }}</dd>
                    </div>
                </template>

                <template v-if="event?.form_type === 'reservation'">
                    <div class="rv-confirm-row">
                        <dt>ご紹介者様お名前</dt>
                        <dd>{{ formData?.referred_by_name?.trim() ? formData.referred_by_name : '—' }}</dd>
                    </div>
                    <div class="rv-confirm-row">
                        <dt>お問い合わせ内容</dt>
                        <dd class="whitespace-pre-wrap">{{ formData?.inquiry_message?.trim() ? formData.inquiry_message : '—' }}</dd>
                    </div>
                </template>
            </dl>

            <div class="rv-nav success-reservation-nav">
                <button type="button" class="rv-btn rv-btn-next" @click="$emit('close')">イベントページへ戻る</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatDateJa } from '@/utils/dateFormat';
import '@lp_design/reserve.css';
import './success-reservation.css';

const props = defineProps({
    formData: Object,
    event: {
        type: Object,
        default: null,
    },
    venues: {
        type: Array,
        default: () => [],
    },
    /** 後方互換 */
    embedPastelReserve: {
        type: Boolean,
        default: false,
    },
    /**
     * true のとき、直上の親ですでに rv-steps を出している（PastelFestReserve / Show モーダル）
     */
    omitStepIndicator: {
        type: Boolean,
        default: false,
    },
});

const formatGraduationCeremonyDate = (fd) => {
    if (!fd) return '—';
    if (fd.graduation_ceremony_date) {
        return formatDateJa(fd.graduation_ceremony_date);
    }
    if (fd.graduation_ceremony_year != null && fd.graduation_ceremony_month != null) {
        return `${fd.graduation_ceremony_year}年${fd.graduation_ceremony_month}月`;
    }
    return '—';
};

defineEmits(['close']);

const selectedVenueName = computed(() => {
    if (!props.formData?.venue_id || !props.venues) return null;
    const venue = props.venues.find((v) => v.id === props.formData.venue_id);
    return venue ? venue.name : null;
});

const formatDateTime = (datetime) => {
    if (!datetime) return '-';
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>
