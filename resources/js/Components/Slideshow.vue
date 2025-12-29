<template>
    <div class="w-full slideshow-container">
        <swiper
            v-if="images && images.length > 0"
            :modules="modules"
            :slides-per-view="1"
            :space-between="0"
            :effect="'fade'"
            :autoplay="autoplayConfig"
            :pagination="{ clickable: true }"
            :navigation="false"
            :loop="images.length > 1"
            class="slideshow-swiper"
        >
            <swiper-slide
                v-for="(image, index) in images"
                :key="image.id || index"
                class="slideshow-slide"
            >
                <img
                    :src="image.path"
                    :alt="image.alt || 'スライドショー画像'"
                    class="w-full object-cover slideshow-image"
                />
            </swiper-slide>
        </swiper>
    </div>
</template>

<script setup>
import { computed } from 'vue';
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
        default: 5000, // 5秒
    },
});

const modules = [Autoplay, Pagination, EffectFade];

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
</script>

<style scoped>
.slideshow-container {
    position: relative;
}

.slideshow-swiper {
    width: 100%;
    height: 100%;
}

.slideshow-image {
    min-height: 300px;
    max-height: 800px;
    object-fit: cover;
    width: 100%;
    display: block;
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
