<template>
    <Head :title="event.title" />

    <div class="min-h-screen" style="background-color: rgb(233, 226, 220);">
        <!-- イベント画像（縦並び） -->
        <div class="w-full md:flex md:justify-center">
            <div class="w-full md:max-w-4xl">
                <img
                    v-for="image in images"
                    :key="image.id"
                    :src="image.path"
                    :alt="image.alt || event.title"
                    class="w-full object-cover md:mx-auto"
                />
            </div>
        </div>

        <!-- イベント情報（予約フォーム以外の場合のみ表示） -->
        <div v-if="event.form_type !== 'reservation'" class="max-w-4xl mx-auto px-4 py-8">
            <h1 class="text-3xl font-bold mb-4">{{ event.title }}</h1>
            <div v-if="event.description" class="text-gray-700 mb-8" v-html="event.description"></div>

            <!-- 店舗情報 -->
            <div v-if="shops && shops.length > 0" class="mb-8">
                <h2 class="text-xl font-semibold mb-4">開催店舗</h2>
                <div class="space-y-4">
                    <div v-for="shop in shops" :key="shop.id" class="bg-white p-4 rounded-lg shadow flex items-start space-x-4">
                        <div v-if="shop.image_url" class="flex-shrink-0">
                            <img
                                :src="shop.image_url"
                                :alt="shop.name"
                                class="w-24 h-24 object-cover rounded"
                            />
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold">{{ shop.name }}</p>
                            <p v-if="shop.address" class="text-sm text-gray-600">{{ shop.address }}</p>
                            <p v-if="shop.phone" class="text-sm text-gray-600">{{ shop.phone }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- フォーム表示 -->
            <div v-if="currentStep === 'form'" class="bg-white p-6 rounded-lg shadow">
                <component
                    :is="formComponent"
                    :event="event"
                    :selected-timeslot="selectedTimeslot"
                    :documents="documents"
                    @confirm="handleConfirm"
                />
            </div>

            <!-- 確認ページ表示 -->
            <div v-if="currentStep === 'confirm'" class="bg-white p-6 rounded-lg shadow">
                <component
                    :is="confirmComponent"
                    :event="event"
                    :form-data="confirmFormData"
                    :venues="venues"
                    @back="currentStep = 'form'"
                />
            </div>

            <!-- 送信完了ページ表示 -->
            <div v-if="currentStep === 'success'" class="bg-white p-6 rounded-lg shadow">
                <component
                    :is="successComponent"
                    :form-data="confirmFormData"
                    @close="handleSuccessClose"
                />
            </div>
        </div>

        <!-- イベント終了メッセージ -->
        <div v-if="isEnded" class="fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.7);">
            <div class="bg-white rounded-lg shadow-xl p-8 max-w-md mx-4 text-center">
                <p class="text-xl font-semibold text-gray-800">
                    このイベントは{{ endDate }}をもって終了いたしました。
                </p>
            </div>
        </div>

        <!-- 固定ボタン（予約フォームの場合のみ、かつ終了していない場合） -->
        <div v-if="event.form_type === 'reservation' && !isEnded" class="fixed bottom-0 left-0 right-0 z-50 p-4" style="background-color: rgba(0, 0, 0, 0.9);">
            <div class="max-w-4xl mx-auto flex gap-4">
                <button
                    @click="showReservationForm = true"
                    class="flex-1 bg-indigo-600 text-white py-4 px-6 rounded-lg font-semibold hover:bg-indigo-700 transition"
                >
                    WEB予約
                </button>
                <a
                    :href="`tel:${shops && shops.length > 0 ? shops[0].phone : ''}`"
                    class="flex-1 bg-green-600 text-white py-4 px-6 rounded-lg font-semibold hover:bg-green-700 transition text-center"
                >
                    電話予約 [受付]10:00 - 19:00
                </a>
            </div>
        </div>

        <!-- 予約フォームモーダル（終了していない場合のみ） -->
        <div
            v-if="showReservationForm && event.form_type === 'reservation' && !isEnded"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background-color: rgba(0, 0, 0, 0.5);"
            @click.self="closeForm"
        >
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                    <h2 class="text-2xl font-bold">予約フォーム</h2>
                    <button
                        @click="closeForm"
                        class="text-gray-500 hover:text-gray-700"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    <!-- フォーム表示 -->
                    <div v-if="currentStep === 'form'">
                        <component
                            :is="formComponent"
                            :event="event"
                            :shops="shops"
                            :venues="venues"
                            :timeslots="timeslots"
                            :selected-timeslot="selectedTimeslot"
                            @confirm="handleConfirm"
                            @timeslot-selected="selectTimeslot"
                        />
                    </div>

                    <!-- 確認ページ表示 -->
                    <div v-if="currentStep === 'confirm'">
                        <component
                            :is="confirmComponent"
                            :event="event"
                            :form-data="confirmFormData"
                            :venues="venues"
                            @back="currentStep = 'form'"
                        />
                    </div>

                    <!-- 送信完了ページ表示 -->
                    <div v-if="currentStep === 'success'">
                        <component
                            :is="successComponent"
                            :form-data="confirmFormData || successFormData"
                            :event="event"
                            :venues="venues"
                            @close="handleSuccessClose"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, defineAsyncComponent } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    images: Array,
    timeslots: Array,
    shops: Array,
    venues: Array,
    documents: Array,
    isEnded: Boolean,
    endDate: String,
    canReserve: Boolean,
    showSuccess: {
        type: Boolean,
        default: false,
    },
    successFormData: {
        type: Object,
        default: null,
    },
});

const selectedTimeslot = ref(null);
const showReservationForm = ref(false);
const currentStep = ref(props.showSuccess ? 'success' : 'form'); // 'form', 'confirm', 'success'
const confirmFormData = ref(props.successFormData || null);

const formComponent = computed(() => {
    const formType = props.event.form_type;
    if (formType === 'reservation') {
        return defineAsyncComponent(() => import('./Forms/ReservationForm.vue'));
    } else if (formType === 'document') {
        return defineAsyncComponent(() => import('./Forms/DocumentForm.vue'));
    } else if (formType === 'contact') {
        return defineAsyncComponent(() => import('./Forms/ContactForm.vue'));
    }
    return null;
});

const confirmComponent = computed(() => {
    const formType = props.event.form_type;
    if (formType === 'reservation') {
        return defineAsyncComponent(() => import('./Forms/ConfirmReservation.vue'));
    } else if (formType === 'document') {
        return defineAsyncComponent(() => import('./Forms/ConfirmDocument.vue'));
    } else if (formType === 'contact') {
        return defineAsyncComponent(() => import('./Forms/ConfirmContact.vue'));
    }
    return null;
});

const successComponent = computed(() => {
    const formType = props.event.form_type;
    if (formType === 'reservation') {
        return defineAsyncComponent(() => import('./Forms/SuccessReservation.vue'));
    } else if (formType === 'document') {
        return defineAsyncComponent(() => import('./Forms/SuccessDocument.vue'));
    } else if (formType === 'contact') {
        return defineAsyncComponent(() => import('./Forms/SuccessContact.vue'));
    }
    return null;
});

const selectTimeslot = (timeslot) => {
    selectedTimeslot.value = timeslot;
};

const formatDateTime = (datetime) => {
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getRemainingCapacity = (timeslot) => {
    // 残枠数の計算はサーバー側で行うため、ここでは簡易的に表示
    return timeslot.remaining_capacity || 0;
};

const handleConfirm = (formData) => {
    confirmFormData.value = formData;
    currentStep.value = 'confirm';
};

const handleSuccessClose = () => {
    // 送信完了後、イベントページに戻る
    router.visit(route('event.show', props.event.slug));
};

const closeForm = () => {
    showReservationForm.value = false;
    selectedTimeslot.value = null;
    currentStep.value = 'form';
    confirmFormData.value = null;
};
</script>

