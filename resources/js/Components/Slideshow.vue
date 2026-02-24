<template>
    <div 
        class="w-full slideshow-container" 
        :class="{ 'slideshow-fullscreen': fullscreen }"
        :style="{ '--slide-interval': interval + 'ms' }"
    >
        <swiper
            v-if="images && images.length > 0"
            ref="swiperRef"
            :modules="modules"
            :slides-per-view="1"
            :space-between="0"
            :effect="type"
            :autoplay="autoplayConfig"
            :pagination="{ clickable: true }"
            :navigation="false"
            :loop="images.length > 1"
            :speed="animationDuration"
            :fadeEffect="type === 'fade' ? effectConfig : undefined"
            :cubeEffect="type === 'cube' ? effectConfig : undefined"
            :coverflowEffect="type === 'coverflow' ? effectConfig : undefined"
            class="slideshow-swiper"
            @slideChange="onSlideChange"
            @click="openModal"
        >
            <swiper-slide
                v-for="(image, index) in images"
                :key="`slide-${image.id || index}`"
                class="slideshow-slide"
            >
                <div class="slideshow-image-wrapper">
                    <img
                        :src="image.path"
                        :alt="image.alt || 'スライドショー画像'"
                        :class="[
                            'slideshow-image',
                            type === 'fade' ? 'animation-zoom-out' : ''
                        ]"
                        @animationend="onAnimationEnd"
                    />
                </div>
            </swiper-slide>
        </swiper>

        <!-- 拡大アイコン（リキッドスタイル） -->
        <button
            v-if="images && images.length > 0"
            type="button"
            class="slideshow-expand-btn"
            @click="openModal"
            aria-label="画像を拡大して表示"
        >
            <svg class="slideshow-expand-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
            </svg>
        </button>
    </div>

    <!-- 画像拡大モーダル -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showModal && currentModalImage"
                class="slideshow-modal-overlay"
                @click.self="closeModal"
                @keydown.esc="closeModal"
                role="dialog"
                aria-modal="true"
                :aria-label="currentModalImage.alt || '画像を拡大表示'"
            >
                <div ref="panzoomParentRef" class="slideshow-modal-panzoom-parent">
                    <div ref="panzoomElemRef" class="slideshow-modal-panzoom-elem">
                        <img
                            :src="currentModalImage.path"
                            :alt="currentModalImage.alt || 'スライドショー画像'"
                            class="slideshow-modal-img"
                            @click.stop
                        />
                    </div>
                </div>
                <button
                    type="button"
                    class="slideshow-modal-close-btn"
                    @click="closeModal"
                    aria-label="閉じる"
                >
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { Autoplay, Pagination, EffectFade, EffectCube, EffectCoverflow } from 'swiper/modules';

// Import Swiper styles
import 'swiper/css';
import 'swiper/css/pagination';
import 'swiper/css/effect-fade';
import 'swiper/css/effect-cube';
import 'swiper/css/effect-coverflow';

// Panzoom for modal image zoom (transform-origin 50% 50% で中央基準、contain で枠内に制限)
import Panzoom from '@panzoom/panzoom';

const props = defineProps({
    images: {
        type: Array,
        required: true,
    },
    type: {
        type: String,
        default: 'fade', // 'fade', 'slide', 'cube', 'coverflow'
    },
    autoplay: {
        type: Boolean,
        default: true,
    },
    interval: {
        type: Number,
        default: 5000,
    },
    fullscreen: {
        type: Boolean,
        default: true,
    },
});

const modules = computed(() => {
    const baseModules = [Pagination];
    
    if (props.type === 'fade') {
        baseModules.push(EffectFade);
    } else if (props.type === 'cube') {
        baseModules.push(EffectCube);
    } else if (props.type === 'coverflow') {
        baseModules.push(EffectCoverflow);
    }
    // 'slide'はデフォルトのエフェクトなので、特別なモジュールは不要
    
    if (props.autoplay) {
        baseModules.push(Autoplay);
    }
    
    return baseModules;
});

const animationDuration = computed(() => {
    if (props.type === 'fade') {
        return 1500;
    }
    return 600;
});

const swiperRef = ref(null);
const currentSlideIndex = ref(0);
const isAnimating = ref(false);
const showModal = ref(false);

const currentModalImage = computed(() => {
    if (!props.images || props.images.length === 0) return null;
    const idx = currentSlideIndex.value % props.images.length;
    return props.images[idx];
});

const openModal = (_swiper, event) => {
    // ページネーションドットのクリックはスライド変更のみでモーダルを開かない
    if (event?.target?.closest?.('.swiper-pagination')) return;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const panzoomParentRef = ref(null);
const panzoomElemRef = ref(null);
let panzoomInstance = null;

const initPanzoom = () => {
    const parent = panzoomParentRef.value;
    const elem = panzoomElemRef.value;
    if (!parent || !elem) return;
    if (panzoomInstance) {
        panzoomInstance.destroy();
        panzoomInstance = null;
    }
    panzoomInstance = Panzoom(elem, {
        minScale: 0.5,
        maxScale: 15,
        cursor: 'grab',
        startScale: 1,
    });
    parent.addEventListener('wheel', panzoomInstance.zoomWithWheel, { passive: false });
};

const destroyPanzoom = () => {
    const parent = panzoomParentRef.value;
    if (panzoomInstance) {
        if (parent) parent.removeEventListener('wheel', panzoomInstance.zoomWithWheel);
        panzoomInstance.destroy();
        panzoomInstance = null;
    }
};

watch(showModal, async (visible) => {
    if (visible) {
        await nextTick();
        initPanzoom();
    } else {
        destroyPanzoom();
    }
});

const onEscapeKey = (e) => {
    if (e.key === 'Escape' && showModal.value) {
        closeModal();
    }
};


const onSlideChange = (swiper) => {
    currentSlideIndex.value = swiper.realIndex;
    isAnimating.value = true;
    
    if (props.type === 'fade') {
        // アニメーションをリセットして再実行
        setTimeout(() => {
            const activeSlide = swiper.slides[swiper.activeIndex];
            if (activeSlide) {
                const image = activeSlide.querySelector('.slideshow-image');
                if (image) {
                    // アニメーションをリセット
                    image.classList.remove('animation-zoom-out');
                    // 再フローをトリガーしてアニメーションを再適用
                    void image.offsetWidth;
                    image.classList.add('animation-zoom-out');
                }
            }
        }, 100);
    }
};

const effectConfig = computed(() => {
    if (props.type === 'cube') {
        return {
            shadow: true,
            slideShadows: true,
            shadowOffset: 20,
            shadowScale: 0.94,
        };
    } else if (props.type === 'coverflow') {
        return {
            rotate: 50,
            stretch: 0,
            depth: 100,
            modifier: 1,
            slideShadows: true,
        };
    } else if (props.type === 'fade') {
        return {
            crossFade: true,
        };
    }
    return {};
});

const onAnimationEnd = (event) => {
    // アニメーション完了時の処理（必要に応じて使用可能）
    if (event.animationName === 'zoomOut') {
        isAnimating.value = false;
    }
};

const autoplayConfig = computed(() => {
    if (props.autoplay && props.images.length > 1) {
        return {
            delay: props.interval,
            disableOnInteraction: false,
            pauseOnMouseEnter: true,
        };
    }
    return false;
});

// 初期化時に最初のスライドのアニメーションを開始
onMounted(() => {
    if (props.images && props.images.length > 0) {
        isAnimating.value = true;
        currentSlideIndex.value = 0;
    }
    window.addEventListener('keydown', onEscapeKey);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onEscapeKey);
    destroyPanzoom();
});

</script>

<style scoped>
.slideshow-container {
    position: relative;
    overflow: hidden;
}

/* 拡大アイコン（リキッドスタイル・明暗どちらの背景でも見やすい） */
.slideshow-expand-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    padding: 0;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.98);
    background: rgba(0, 0, 0, 0.35);
    backdrop-filter: blur(12px) saturate(180%);
    -webkit-backdrop-filter: blur(12px) saturate(180%);
    box-shadow:
        0 4px 20px rgba(0, 0, 0, 0.35),
        0 0 0 1px rgba(0, 0, 0, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.25);
    transition: transform 0.2s ease, opacity 0.2s ease, background 0.2s ease;
}

.slideshow-expand-btn:hover {
    background: rgba(0, 0, 0, 0.5);
    transform: scale(1.08);
    opacity: 1;
    box-shadow:
        0 6px 24px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(0, 0, 0, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.slideshow-expand-btn:active {
    transform: scale(0.96);
}

.slideshow-expand-icon {
    width: 22px;
    height: 22px;
}

.slideshow-swiper {
    width: 100%;
    height: 100%;
}

.slideshow-image-wrapper {
    width: 100%;
    height: 100%;
    overflow: hidden;
    position: relative;
}

.slideshow-image {
    min-height: 300px;
    max-height: 800px;
    object-fit: cover;
    width: 100%;
    height: 100%;
    display: block;
    will-change: transform;
}

.slideshow-fullscreen .slideshow-image {
    min-height: 100vh;
    max-height: 100vh;
}

/* ズームアウトアニメーション（引く） */
.slideshow-image.animation-zoom-out {
    animation: zoomOut 5s ease-out forwards;
    transform-origin: center center;
}

@keyframes zoomOut {
    0% {
        transform: scale(1.2);
    }
    100% {
        transform: scale(1);
    }
}

/* フェードエフェクトと組み合わせるために、スライドの初期状態も設定 */
.slideshow-slide {
    opacity: 0;
    transition: opacity 1.5s ease-in-out;
}

.slideshow-slide.swiper-slide-active {
    opacity: 1;
}

/* ページネーション（ドット）のスタイル */
.slideshow-swiper :deep(.swiper-pagination) {
    bottom: 20px;
}

.slideshow-swiper :deep(.swiper-pagination-bullet) {
    width: 12px;
    height: 12px;
    background: white;
    opacity: 0.5;
    transition: all 0.3s ease;
}

.slideshow-swiper :deep(.swiper-pagination-bullet-active) {
    opacity: 1;
    background: white;
    width: 30px;
    border-radius: 6px;
}

/* モバイル対応 */
@media (max-width: 640px) {
    .slideshow-image {
        min-height: 200px;
    }
    
    .slideshow-fullscreen .slideshow-image {
        min-height: 100vh;
    }
}

/* モーダルオーバーレイ（画面いっぱい） */
.slideshow-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    background-color: rgba(0, 0, 0, 0.7);
    min-height: 100vh;
    min-height: 100dvh;
}

/* Panzoom 親（画面いっぱい、中央に画像を配置） */
.slideshow-modal-panzoom-parent {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

/* Panzoom 適用要素（画像のラッパー、常に中央に配置） */
.slideshow-modal-panzoom-elem {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* モーダル内画像（横に余白を持たせてすっきり表示） */
.slideshow-modal-img {
    max-width: calc(100vw - 2.5rem);
    max-height: calc(100vh - 2.5rem);
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 0.5rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    display: block;
}

/* モーダル閉じるボタン（リキッドスタイル・拡大アイコンと統一） */
.slideshow-modal-close-btn {
    position: fixed;
    top: 0.5rem;
    right: 0.5rem;
    z-index: 200;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    padding: 0;
    color: rgba(255, 255, 255, 0.98);
    background: rgba(0, 0, 0, 0.35);
    backdrop-filter: blur(12px) saturate(180%);
    -webkit-backdrop-filter: blur(12px) saturate(180%);
    box-shadow:
        0 4px 20px rgba(0, 0, 0, 0.35),
        0 0 0 1px rgba(0, 0, 0, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 50%;
    cursor: pointer;
    transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
}
.slideshow-modal-close-btn:hover {
    background: rgba(0, 0, 0, 0.5);
    transform: scale(1.08);
    box-shadow:
        0 6px 24px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(0, 0, 0, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}
.slideshow-modal-close-btn:active {
    transform: scale(0.96);
}
</style>
