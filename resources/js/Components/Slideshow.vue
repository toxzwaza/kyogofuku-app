<template>
    <div class="w-full slideshow-container" :style="{ '--slide-interval': interval + 'ms' }">
        <swiper
            v-if="images && images.length > 0"
            ref="swiperRef"
            :modules="modules"
            :slides-per-view="1"
            :space-between="0"
            :effect="'fade'"
            :autoplay="autoplayConfig"
            :pagination="{ clickable: true }"
            :navigation="false"
            :loop="images.length > 1"
            :speed="animationDuration"
            :fadeEffect="{ crossFade: true }"
            class="slideshow-swiper"
            @slideChange="onSlideChange"
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
                        class="slideshow-image animation-zoom-out"
                        @animationend="onAnimationEnd"
                    />
                </div>
            </swiper-slide>
        </swiper>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { Autoplay, Pagination, EffectFade } from 'swiper/modules';

// Import Swiper styles
import 'swiper/css';
import 'swiper/css/pagination';
import 'swiper/css/effect-fade';

const props = defineProps({
    images: {
        type: Array,
        required: true,
    },
    autoplay: {
        type: Boolean,
        default: true,
    },
    interval: {
        type: Number,
        default: 5000, // 5秒（ズームアウトアニメーション時間）
    },
});

const modules = [Autoplay, Pagination, EffectFade];

// Swiperのフェード切り替え時間（ゆっくりとしたフェード）
const animationDuration = 1500;

const swiperRef = ref(null);
const currentSlideIndex = ref(0);
const isAnimating = ref(false);


const onSlideChange = (swiper) => {
    currentSlideIndex.value = swiper.realIndex;
    isAnimating.value = true;
    
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
};

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
});

</script>

<style scoped>
.slideshow-container {
    position: relative;
    overflow: hidden;
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
}
</style>
