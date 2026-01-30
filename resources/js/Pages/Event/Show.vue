<template>
    <Head :title="event.title" />

    <div class="min-h-screen relative" style="background-color: rgb(233, 226, 220);">
        <!-- 背景画像 -->
        <div class="fixed inset-0 z-0 opacity-30" style="background-image: url('/storage/background_img/1.png'); background-size: cover; background-position: center; background-repeat: no-repeat;"></div>
        
        <!-- ローディング画面 -->
        <div v-if="isLoading" class="fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgb(233, 226, 220);">
            <div class="text-center">
                <!-- ローディングスピナー -->
                <div class="relative w-20 h-20 mx-auto mb-6">
                    <div class="absolute inset-0 border-4 border-pink-200 rounded-full"></div>
                    <div class="absolute inset-0 border-4 border-pink-600 rounded-full border-t-transparent animate-spin"></div>
                    <div class="absolute inset-2 border-4 border-rose-200 rounded-full"></div>
                    <div class="absolute inset-2 border-4 border-rose-500 rounded-full border-t-transparent animate-spin" style="animation-direction: reverse; animation-duration: 1.5s;"></div>
                </div>
                <!-- ローディングテキスト -->
                <p class="text-pink-600 text-lg font-medium animate-pulse">読み込み中...</p>
            </div>
        </div>
        
        <!-- コンテンツ -->
        <Transition
            enter-active-class="transition-all duration-1000 ease-out"
            enter-from-class="opacity-0 translate-y-6 scale-98"
            enter-to-class="opacity-100 translate-y-0 scale-100"
        >
            <div 
                v-if="!isLoading" 
                class="relative z-10"
            >
        <!-- イベント画像とスライドショー（縦並び） -->
        <div v-if="!showSuccess" class="w-full md:flex md:justify-center">
            <div class="w-full md:max-w-2xl">
                <template v-for="(item, index) in displayItems" :key="`${item.type}-${item.id || index}`">
                    <!-- 画像 -->
                    <div
                        v-if="item.type === 'image'"
                        :ref="el => setImageRef(el, item.originalIndex !== undefined ? item.originalIndex : index)"
                        :class="[
                            // 一枚目（originalIndex 0）はアニメーションなし、二枚目以降はアニメーション
                            (item.originalIndex !== undefined ? item.originalIndex : index) === 0 
                                ? '' 
                                : 'scroll-reveal-image',
                            // 二枚目以降のみrevealedクラスを適用
                            (item.originalIndex !== undefined ? item.originalIndex : index) !== 0 && 
                            (item.originalIndex !== undefined ? revealedImages.has(item.originalIndex) : revealedImages.has(index))
                                ? 'revealed' 
                                : ''
                        ]"
                    >
                        <!-- WebPパスが存在する場合（新規アップロード画像）のみ<picture>要素を使用 -->
                        <picture v-if="item.data.webp_path">
                            <source 
                                :srcset="item.data.webp_path" 
                                type="image/webp"
                            />
                            <img
                                :src="item.data.path"
                                :alt="item.data.alt || event.title"
                                class="w-full object-cover md:mx-auto"
                                loading="lazy"
                            />
                        </picture>
                        <!-- WebPパスが存在しない場合（既存画像）は通常の<img>要素 -->
                        <img
                            v-else
                            :src="item.data.path"
                            :alt="item.data.alt || event.title"
                            class="w-full object-cover md:mx-auto"
                            loading="lazy"
                        />
                    </div>
                    <!-- スライドショー -->
                    <div v-else-if="item.type === 'slideshow'" class="w-full">
                        <Slideshow 
                            :images="item.data.images" 
                            :type="item.data.type"
                            :autoplay="item.data.autoplay_enabled"
                            :interval="item.data.autoplay_interval"
                            :fullscreen="item.data.fullscreen"
                        />
                    </div>
                </template>
            </div>
        </div>

        <!-- 開催会場（予約フォームの場合のみ） -->
        <div v-if="event.form_type === 'reservation' && venues && venues.length > 0 && !showSuccess" :class="['max-w-4xl mx-auto px-4 py-8', !isEnded && !showSuccess ? 'pb-32' : '']">
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-4">開催会場</h2>
                <div class="space-y-6">
                    <div v-for="venue in venues" :key="venue.id" class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                        <!-- 画像とテキストのグリッドレイアウト -->
                        <div class="md:flex">
                            <!-- テキスト情報（左側または上側） -->
                            <div class="flex-1 p-6">
                                <h3 class="font-bold text-xl text-gray-900 mb-3">{{ venue.name }}</h3>
                                
                                <div v-if="venue.description" class="text-sm text-gray-700 mb-4 leading-relaxed" v-html="venue.description"></div>
                                
                                <div class="space-y-3">
                                    <!-- 住所 -->
                                    <div v-if="venue.address" class="flex items-start space-x-3">
                                        <div class="flex-shrink-0 mt-0.5">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </div>
                                        <p class="text-sm text-gray-700 flex-1">{{ venue.address }}</p>
                                    </div>
                                    
                                    <!-- 電話番号 -->
                                    <div v-if="venue.phone" class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                        </div>
                                        <a :href="`tel:${venue.phone}`" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                            {{ venue.phone }}
                                        </a>
                                    </div>

                                    <!-- 開催日時（目立つ表示） -->
                                    <div v-if="venue.dates && venue.dates.length > 0" class="mt-4 pt-4 border-t border-rose-100">
                                        <div class="flex items-start gap-3 rounded-lg bg-rose-50/80 px-4 py-3">
                                            <div class="flex-shrink-0 mt-0.5">
                                                <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-bold text-rose-600 uppercase tracking-wider mb-2">開催日</p>
                                                <div class="space-y-2">
                                                    <div
                                                        v-for="(block, blockIdx) in formatVenueDates(venue.dates)"
                                                        :key="blockIdx"
                                                        class="flex flex-wrap items-baseline gap-x-1.5"
                                                    >
                                                        <span class="text-2xl font-bold tabular-nums text-rose-700 tracking-tight">{{ block.monthLabel }}</span>
                                                        <span class="text-rose-400 font-medium select-none text-lg">/</span>
                                                        <span class="text-base font-semibold tabular-nums text-gray-800">{{ block.dayParts.join(', ') }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 画像（右側または下側） -->
                            <div v-if="venue.image_url" class="md:w-1/2 lg:w-2/5 flex-shrink-0">
                                <img
                                    :src="venue.image_url"
                                    :alt="venue.name"
                                    class="w-full h-full object-cover"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- イベント情報（予約フォーム以外の場合、または成功ページ表示時） -->
        <div v-if="event.form_type !== 'reservation' || showSuccess" class="max-w-4xl mx-auto px-4 py-8">
            <h1 v-if="!showSuccess" class="text-3xl font-bold mb-4">{{ event.title }}</h1>
            <div v-if="event.description && !showSuccess" class="text-gray-700 mb-8" v-html="event.description"></div>

            <!-- 店舗情報 -->
            <!-- <div v-if="shops && shops.length > 0" class="mb-8">
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
            </div> -->

            <!-- フォーム表示 -->
            <div v-if="currentStep === 'form' && !showSuccess" class="bg-white p-6 rounded-lg shadow">
                <component
                    :is="formComponent"
                    :event="event"
                    :shops="shops"
                    :selected-timeslot="selectedTimeslot"
                    :documents="documents"
                    @confirm="handleConfirm"
                />
            </div>

            <!-- 確認ページ表示 -->
            <div v-if="currentStep === 'confirm' && !showSuccess" class="bg-white p-6 rounded-lg shadow">
                <component
                    :is="confirmComponent"
                    :event="event"
                    :form-data="confirmFormData"
                    :venues="venues"
                    @back="currentStep = 'form'"
                />
            </div>

            <!-- 送信完了ページ表示（通常ページとして表示） -->
            <div v-if="currentStep === 'success' || showSuccess" class="bg-white p-6 rounded-lg shadow">
                <component
                    :is="successComponent"
                    :form-data="confirmFormData || successFormData"
                    :event="event"
                    :venues="venues"
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

        <!-- 固定ボタン（予約フォームの場合のみ、かつ終了していない場合、かつ成功ページ表示時ではない場合） -->
        <div v-if="event.form_type === 'reservation' && !isEnded && !showSuccess && !isLoading" class="fixed bottom-0 left-0 right-0 z-50 p-4" style="background-color: rgb(137 13 13 / 90%);">
            <div class="max-w-4xl md:max-w-xl mx-auto flex gap-4">
                <button
                    @click="showReservationForm = true"
                    class="flex-1 hover:opacity-80 transition-opacity"
                >
                    <img
                        src="/storage/button/web.png"
                        alt="WEB予約"
                        class="w-full h-auto"
                    />
                </button>
                <a
                    :href="`tel:${shops && shops.length > 0 ? shops[0].phone : ''}`"
                    class="flex-1 hover:opacity-80 transition-opacity"
                >
                    <img
                        src="/storage/button/tell.png"
                        alt="電話予約"
                        class="w-full h-auto"
                    />
                </a>
            </div>
        </div>

        <!-- 予約フォームモーダル（終了していない場合、かつ成功ページ表示時ではない場合のみ） -->
        <div
            v-if="showReservationForm && event.form_type === 'reservation' && !isEnded && !showSuccess"
            class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-3"
            style="background-color: rgba(0, 0, 0, 0.5);"
            @click.self="closeForm"
        >
            <div class="bg-white rounded-lg shadow-xl max-w-7xl w-full max-h-[95vh] overflow-y-auto relative" style="background-image: linear-gradient(rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.7)), url('/storage/background_img/2.png'); background-size: cover; background-position: right; background-repeat: no-repeat;">
                <div class="sticky top-0 bg-gradient-to-r from-pink-50 to-rose-50 border-b-2 border-pink-200 px-4 sm:px-6 py-4 sm:py-5 flex justify-between items-center z-10 shadow-sm">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 mr-2 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        予約フォーム
                    </h2>
                    <button
                        @click="closeForm"
                        class="text-gray-500 hover:text-pink-600 hover:bg-pink-100 rounded-full p-2 transition-all duration-200"
                    >
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-4 sm:p-5 lg:p-6 relative">
                    <!-- フォーム表示 -->
                    <div v-if="currentStep === 'form'">
                        <component
                            :is="formComponent"
                            :event="event"
                            :shops="shops"
                            :venues="venues"
                            :timeslots="timeslots"
                            :selected-timeslot="selectedTimeslot"
                            :from-admin="fromAdmin"
                            @confirm="handleConfirm"
                            @timeslot-selected="selectTimeslot"
                        />
                    </div>

                    <!-- 確認ページ表示 -->
                    <div v-if="currentStep === 'confirm'" class="relative z-0">
                        <component
                            :is="confirmComponent"
                            :event="event"
                            :form-data="confirmFormData"
                            :venues="venues"
                            :from-admin="fromAdmin"
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
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, defineAsyncComponent, onMounted, nextTick, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Slideshow from '@/Components/Slideshow.vue';

const props = defineProps({
    event: Object,
    images: Array,
    slideshowPositions: {
        type: Object,
        default: () => ({}),
    },
    slideshows: {
        type: Object,
        default: () => ({}),
    },
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
const isLoading = ref(true);

/**
 * 会場の開催日リストを表示用にフォーマット
 * ルール: 月を大きく表示し「/」で区切り、2日連続は「〇, 〇」、3日以上連続は「〇 ~ 〇」、月をまたぐ場合は改行して月ごとに表示
 * @param {string[]} dateStrings - 'Y-m-d' 形式の日付配列
 * @returns {{ monthLabel: string, dayParts: string[] }[]}
 */
function formatVenueDates(dateStrings) {
  if (!dateStrings || dateStrings.length === 0) return [];
  const sorted = [...dateStrings].sort();
  const byMonth = {};
  for (const d of sorted) {
    const [y, m] = d.split('-');
    const key = `${y}-${m}`;
    if (!byMonth[key]) byMonth[key] = [];
    byMonth[key].push(parseInt(d.split('-')[2], 10));
  }
  const monthOrder = Object.keys(byMonth).sort();
  return monthOrder.map((key) => {
    const [y, m] = key.split('-');
    const monthNum = parseInt(m, 10);
    const monthLabel = `${monthNum}月`;
    const days = byMonth[key].sort((a, b) => a - b);
    const ranges = [];
    let start = days[0];
    let end = days[0];
    for (let i = 1; i < days.length; i++) {
      if (days[i] === end + 1) {
        end = days[i];
      } else {
        ranges.push({ start, end });
        start = days[i];
        end = days[i];
      }
    }
    ranges.push({ start, end });
    const dayParts = ranges.map(({ start: s, end: e }) => {
      if (s === e) return String(s);
      if (e === s + 1) return `${s}, ${e}`;
      return `${s} ~ ${e}`;
    });
    return { monthLabel, dayParts };
  });
}

// スクロールアニメーション用
const imageRefs = ref(new Map());
const revealedImages = ref(new Set());
let observer = null;

// 初回ローディング管理
onMounted(() => {
    // 画像の読み込みを待つ
    const images = document.querySelectorAll('img');
    let loadedCount = 0;
    const totalImages = images.length;
    
    if (totalImages === 0) {
        // 画像がない場合は短い遅延後にローディングを終了
        setTimeout(async () => {
            await nextTick();
            isLoading.value = false;
        }, 500);
    } else {
        images.forEach((img) => {
            if (img.complete) {
                loadedCount++;
                if (loadedCount === totalImages) {
                    setTimeout(async () => {
                        await nextTick();
                        isLoading.value = false;
                        // ローディング終了後にスクロールアニメーションを設定
                        setTimeout(() => {
                            setupScrollAnimation();
                        }, 500);
                    }, 300);
                }
            } else {
                img.addEventListener('load', async () => {
                    loadedCount++;
                    if (loadedCount === totalImages) {
                        setTimeout(async () => {
                            await nextTick();
                            isLoading.value = false;
                            // ローディング終了後にスクロールアニメーションを設定
                            setTimeout(() => {
                                setupScrollAnimation();
                            }, 500);
                        }, 300);
                    }
                });
                img.addEventListener('error', async () => {
                    loadedCount++;
                    if (loadedCount === totalImages) {
                        setTimeout(async () => {
                            await nextTick();
                            isLoading.value = false;
                            // ローディング終了後にスクロールアニメーションを設定
                            setTimeout(() => {
                                setupScrollAnimation();
                            }, 500);
                        }, 300);
                    }
                });
            }
        });
    }
    
    // 最大3秒でローディングを終了（タイムアウト）
    setTimeout(async () => {
        await nextTick();
        isLoading.value = false;
        // ローディング終了後にスクロールアニメーションを設定
        setTimeout(() => {
            setupScrollAnimation();
        }, 500);
    }, 3000);
});

// クリーンアップ
onUnmounted(() => {
    if (observer) {
        observer.disconnect();
        observer = null;
    }
});

// Inertia.jsのページ遷移時のローディング管理
router.on('start', () => {
    isLoading.value = true;
});

router.on('finish', async () => {
    // 少し遅延させてスムーズに表示
    setTimeout(async () => {
        await nextTick();
        isLoading.value = false;
        // ページ遷移後にもスクロールアニメーションを設定
        setTimeout(() => {
            setupScrollAnimation();
        }, 500);
    }, 300);
});

// URLパラメータからfrom_adminとtimeslot_idを取得
const urlParams = new URLSearchParams(window.location.search);
const fromAdmin = urlParams.get('from_admin') === '1';
const urlTimeslotId = urlParams.get('timeslot_id');

// 管理画面からのアクセスの場合、予約フォームを自動的に開く（成功ページ表示時は開かない）
if (fromAdmin && props.event.form_type === 'reservation' && !props.isEnded && !props.showSuccess) {
    showReservationForm.value = true;
    
    // URLパラメータから予約枠を取得して選択
    if (urlTimeslotId && props.timeslots) {
        const timeslotFromUrl = props.timeslots.find(t => t.id == urlTimeslotId);
        if (timeslotFromUrl) {
            selectedTimeslot.value = timeslotFromUrl;
        }
    }
}

// 成功ページ表示時はモーダルを閉じる
if (props.showSuccess) {
    showReservationForm.value = false;
}

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
    // from_adminフラグをformDataに追加
    confirmFormData.value = {
        ...formData,
        from_admin: fromAdmin,
    };
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

// 画像要素のrefを設定
const setImageRef = (el, index) => {
    if (el && index !== undefined && index !== null) {
        imageRefs.value.set(index, el);
        // 既にobserverが設定されている場合は、すぐに監視を開始
        if (observer && !revealedImages.value.has(index)) {
            observer.observe(el);
        }
    }
};

// Intersection Observerの設定
const setupScrollAnimation = () => {
    if (typeof window === 'undefined' || !window.IntersectionObserver) {
        return;
    }

    // 既存のobserverがあれば破棄
    if (observer) {
        observer.disconnect();
    }

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    // Mapからindexを取得
                    let foundIndex = null;
                    imageRefs.value.forEach((ref, index) => {
                        if (ref === entry.target) {
                            foundIndex = index;
                        }
                    });
                    
                    if (foundIndex !== null && !revealedImages.value.has(foundIndex)) {
                        // 少し遅延させてアニメーションを開始
                        setTimeout(() => {
                            revealedImages.value.add(foundIndex);
                        }, 50);
                        // 一度表示されたら監視を停止
                        observer.unobserve(entry.target);
                    }
                }
            });
        },
        {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px', // ビューポートに入る少し前にトリガー
        }
    );

    // 画像要素を監視
    nextTick(() => {
        imageRefs.value.forEach((ref, index) => {
            if (ref && observer && !revealedImages.value.has(index)) {
                // 一枚目（index 0）はアニメーションなしで表示済みなのでスキップ
                // 2枚目以降の画像はスクロールでビューポートに入ったときにアニメーション
                if (index !== 0) {
                    observer.observe(ref);
                }
            }
        });
    });
};

// 画像とスライドショーを順序付けして配列化
const displayItems = computed(() => {
    const items = [];
    const positions = props.slideshowPositions || {};
    const slideshows = props.slideshows || {};
    const images = props.images || [];

    // 最初の画像の前にスライドショーがある場合（複数対応）
    if (positions['0'] && Array.isArray(positions['0'])) {
        positions['0']
            .sort((a, b) => a.sort_order - b.sort_order)
            .forEach((posData) => {
                if (slideshows[posData.slideshow_id]) {
                    items.push({
                        type: 'slideshow',
                        id: `slideshow-0-${posData.slideshow_id}`,
                        data: slideshows[posData.slideshow_id],
                    });
                }
            });
    }

    // 画像とスライドショーを交互に配置
    images.forEach((image, index) => {
        // 画像を追加
        items.push({
            type: 'image',
            id: `image-${image.id}`,
            data: image,
            originalIndex: index,
        });

        // この画像の後にスライドショーがある場合（複数対応）
        const position = index + 1;
        if (positions[position.toString()] && Array.isArray(positions[position.toString()])) {
            positions[position.toString()]
                .sort((a, b) => a.sort_order - b.sort_order)
                .forEach((posData) => {
                    if (slideshows[posData.slideshow_id]) {
                        items.push({
                            type: 'slideshow',
                            id: `slideshow-${position}-${posData.slideshow_id}`,
                            data: slideshows[posData.slideshow_id],
                        });
                    }
                });
        }
    });

    return items;
});

// displayItemsが変更されたときにスクロールアニメーションを再設定
const imageItems = computed(() => {
    return displayItems.value.filter(item => item.type === 'image');
});

// 画像のインデックスを取得するヘルパー関数
const getImageIndex = (item, allItems) => {
    return allItems.findIndex(i => i.id === item.id && i.type === 'image');
};
</script>

<style scoped>
.scroll-reveal-image {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.8s ease-out, transform 0.8s ease-out;
    will-change: opacity, transform;
}

.scroll-reveal-image.revealed {
    opacity: 1;
    transform: translateY(0);
}
</style>

