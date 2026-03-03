<template>
    <div class="digital-clock">
        <div class="digital-clock__bezel">
            <div class="digital-clock__screen">
                <div class="digital-clock__display">
                    <template v-for="(char, i) in displayChars" :key="i">
                        <SevenSegmentDigit v-if="char !== ':'" :digit="char" class="digital-clock__digit" />
                        <div v-else class="digital-clock__colon">
                            <span class="digital-clock__dot" />
                            <span class="digital-clock__dot" />
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import SevenSegmentDigit from './SevenSegmentDigit.vue';

const props = defineProps({
    time: { type: String, default: '00:00:00' },
});

const displayChars = computed(() => props.time.split(''));
</script>

<style scoped>
.digital-clock {
    --digit-on: #059669;
    --digit-off: rgba(5, 150, 105, 0.08);
    --screen-bg: #f8fafc;
}

.digital-clock__bezel {
    background: #f1f5f9;
    border-radius: 12px;
    padding: 10px 14px;
    border: 1px solid #e2e8f0;
}

.digital-clock__screen {
    background: var(--screen-bg);
    border-radius: 8px;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
}

.digital-clock__display {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2px;
    min-height: 80px;
}

.digital-clock__digit {
    flex-shrink: 0;
    width: 36px;
    height: 60px;
}

.digital-clock__colon {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 4px;
    padding: 0 2px;
    height: 100%;
}

.digital-clock__dot {
    width: 6px;
    height: 6px;
    background: var(--digit-on);
    border-radius: 50%;
    animation: blink 1s step-end infinite;
}

@keyframes blink {
    0%, 49% { opacity: 1; }
    50%, 100% { opacity: 0.3; }
}
</style>
