<template>
    <Head :title="`WEB予約 | ${event.title}`">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link
            href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700;900&family=Zen+Old+Mincho:wght@400;700;900&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        ref="rootRef"
        class="lp-pastel-root lp-reserve-page reserve-page"
        :style="themeStyle"
    >
        <header class="rv-header">
            <a href="#" class="rv-back" @click.prevent="goEventPage">&larr; イベントページに戻る</a>
            <p class="rv-header-title">{{ event.title }}</p>
        </header>

        <main class="rv-main">
            <div class="rv-container">
                <div class="rv-title-area">
                    <p class="rv-title-en">RESERVATION</p>
                    <h1 class="rv-title">来場予約フォーム</h1>
                    <p class="rv-title-note">ご予約は各回先着順です。お早めにお申し込みください。</p>
                </div>

                <div class="rv-steps">
                    <div :class="step1Class">
                        <span class="rv-step-num">1</span>
                        <span class="rv-step-label">会場・日時</span>
                    </div>
                    <div class="rv-step-line" :class="{ done: line12Done }"></div>
                    <div :class="step2Class">
                        <span class="rv-step-num">2</span>
                        <span class="rv-step-label">お客様情報</span>
                    </div>
                    <div class="rv-step-line" :class="{ done: line23Done }"></div>
                    <div :class="step3Class">
                        <span class="rv-step-num">3</span>
                        <span class="rv-step-label">確認</span>
                    </div>
                </div>

                <div id="reserveForm">
                    <div v-show="currentStep === 'form'" class="rv-panel">
                        <component
                            :is="formComponent"
                            :event="event"
                            :shops="shops"
                            :venues="venues"
                            :timeslots="timeslots"
                            :selected-timeslot="selectedTimeslot"
                            :from-admin="fromAdmin"
                            :embed-pastel-reserve="embedPastelReserveUi"
                            @confirm="handleConfirm"
                            @timeslot-selected="selectTimeslot"
                        />
                    </div>

                    <div v-if="currentStep === 'confirm' && confirmFormData" class="rv-panel">
                        <h2 class="rv-panel-title">ご予約内容の確認</h2>
                        <component
                            :is="confirmComponent"
                            :event="event"
                            :form-data="confirmFormData"
                            :venues="venues"
                            :from-admin="fromAdmin"
                            :embed-pastel-reserve="embedPastelReserveUi"
                            @back="currentStep = 'form'"
                        />
                    </div>

                    <div v-if="currentStep === 'success' && successPayload">
                        <component
                            :is="successComponent"
                            :form-data="successPayload"
                            :event="event"
                            :venues="venues"
                            omit-step-indicator
                            @close="handleSuccessClose"
                        />
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer" style="margin-bottom: 0">
            <p class="footer-brand">京呉服平田 / edel</p>
            <p class="footer-copy">&copy; 2026 京呉服平田</p>
        </footer>
    </div>
</template>

<script setup>
import '@lp_design/style.css';
import '@lp_design/reserve.css';
import './pastel-reserve-inertia.css';
import { ref, computed, defineAsyncComponent } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    images: Array,
    slideshowPositions: { type: Object, default: () => ({}) },
    slideshows: { type: Object, default: () => ({}) },
    timeslots: Array,
    shops: Array,
    venues: Array,
    documents: Array,
    isEnded: Boolean,
    endDate: String,
    canReserve: Boolean,
    showSuccess: { type: Boolean, default: false },
    successFormData: { type: Object, default: null },
    ctaButtonPositions: { type: Array, default: () => [] },
    lpThemeCssVars: { type: Object, default: () => ({}) },
});

const rootRef = ref(null);

const themeStyle = computed(() => props.lpThemeCssVars || {});

const embedPastelReserveUi = computed(() => {
    const t = props.event?.form_type;
    return t === 'reservation' || t === 'reservation_hakama';
});

const selectedTimeslot = ref(null);
const currentStep = ref(props.showSuccess ? 'success' : 'form');
const confirmFormData = ref(props.successFormData || null);

/** 成功画面用（v-if でマウントするため、null のときは子を描画しない） */
const successPayload = computed(() => confirmFormData.value ?? props.successFormData ?? null);

const line12Done = computed(() => currentStep.value !== 'form');
const line23Done = computed(() => currentStep.value === 'confirm' || currentStep.value === 'success');

const step1Class = computed(() => {
    const base = 'rv-step';
    if (currentStep.value === 'form') return `${base} active`;
    return `${base} done`;
});

const step2Class = computed(() => {
    const base = 'rv-step';
    if (currentStep.value === 'form') return `${base} pastel-secondary-active`;
    return `${base} done`;
});

const step3Class = computed(() => {
    const base = 'rv-step';
    if (currentStep.value === 'confirm') return `${base} active`;
    if (currentStep.value === 'success') return `${base} done`;
    return base;
});

const urlParams = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
const fromAdmin = urlParams.get('from_admin') === '1';
const urlTimeslotId = urlParams.get('timeslot_id');

if (fromAdmin && !props.isEnded && !props.showSuccess && urlTimeslotId && props.timeslots) {
    const t = props.timeslots.find((x) => String(x.id) === String(urlTimeslotId));
    if (t) selectedTimeslot.value = t;
}

const formComponent = computed(() => {
    const formType = props.event?.form_type;
    if (formType === 'reservation') {
        return defineAsyncComponent(() => import('../Forms/ReservationForm.vue'));
    }
    if (formType === 'reservation_hakama') {
        return defineAsyncComponent(() => import('../Forms/HakamaReservationForm.vue'));
    }
    if (formType === 'document') {
        return defineAsyncComponent(() => import('../Forms/DocumentForm.vue'));
    }
    if (formType === 'contact') {
        return defineAsyncComponent(() => import('../Forms/ContactForm.vue'));
    }
    return null;
});

const confirmComponent = computed(() => {
    const formType = props.event?.form_type;
    if (formType === 'reservation' || formType === 'reservation_hakama') {
        return defineAsyncComponent(() => import('../Forms/ConfirmReservation.vue'));
    }
    if (formType === 'document') {
        return defineAsyncComponent(() => import('../Forms/ConfirmDocument.vue'));
    }
    if (formType === 'contact') {
        return defineAsyncComponent(() => import('../Forms/ConfirmContact.vue'));
    }
    return null;
});

const successComponent = computed(() => {
    const formType = props.event?.form_type;
    if (formType === 'reservation' || formType === 'reservation_hakama') {
        return defineAsyncComponent(() => import('../Forms/SuccessReservation.vue'));
    }
    if (formType === 'document') {
        return defineAsyncComponent(() => import('../Forms/SuccessDocument.vue'));
    }
    if (formType === 'contact') {
        return defineAsyncComponent(() => import('../Forms/SuccessContact.vue'));
    }
    return null;
});

function selectTimeslot(timeslot) {
    selectedTimeslot.value = timeslot;
}

function handleConfirm(formData) {
    confirmFormData.value = { ...formData, from_admin: fromAdmin };
    currentStep.value = 'confirm';
}

function handleSuccessClose() {
    router.visit(route('event.show', props.event.slug));
}

function goEventPage() {
    router.visit(route('event.show', props.event.slug));
}
</script>

<style scoped>
#reserveForm :deep(.bg-white) {
    border-radius: var(--radius-soft, 20px);
}
</style>
