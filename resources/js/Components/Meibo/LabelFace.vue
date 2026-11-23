<template>
    <!-- 宛名ラベル 1 面（A-One 66732 相当 86.4×50.8mm）の見本。配置は名簿管理システムの labels.py に合わせた -->
    <div class="label-face relative w-full bg-white text-[#1a1a1a] overflow-hidden" :style="{ aspectRatio: `${W} / ${H}` }">
        <template v-if="person">
            <!-- 料金後納（円）・ゆうメール -->
            <div class="absolute rounded-full border border-[#1a1a1a] flex flex-col items-center justify-center leading-tight"
                 :style="{ left: x(3.4), top: y(3.2), width: x(12.4), aspectRatio: '1', fontSize: f(5.8) }">
                <span>料金後納</span><span>郵便</span>
            </div>
            <div class="absolute rounded-full border border-[#1a1a1a] flex items-center justify-center whitespace-nowrap"
                 :style="{ left: x(3), top: y(17.7), width: x(13.4), height: y(5.6), fontSize: f(6.6) }">ゆうメール</div>

            <!-- QR・管理番号 -->
            <img v-if="qr" :src="qr" alt="" class="absolute" :style="{ left: x(68.2), top: y(1.6), width: x(15) }" />
            <div class="absolute text-center text-[#444]" :style="{ left: x(68.2), top: y(17), width: x(15), fontSize: f(6.4) }">{{ person.code }}</div>

            <!-- 宛先 -->
            <div class="absolute whitespace-nowrap tracking-wider" :style="{ left: x(19.5), top: y(3.4), fontSize: f(11.5) }">〒 {{ person.zip }}</div>
            <div class="absolute overflow-hidden" :style="{ left: x(19.5), top: y(10.1), width: x(46.5), fontSize: f(8.6), lineHeight: y2(4.4), maxHeight: y(13.2) }">
                {{ person.pref }}{{ person.address }}
            </div>
            <div class="absolute whitespace-nowrap font-serif" :style="{ left: x(20.5), top: y(23.4), fontSize: f(nameSize), letterSpacing: f(1.6) }">
                {{ addresseeName }}　様
            </div>
            <div v-if="withFamily" class="absolute whitespace-nowrap font-serif" :style="{ left: x(33), top: y(34.2), fontSize: f(12.5) }">ご家族様</div>

            <!-- 差出人 -->
            <div class="absolute border-t border-[#bdbdbd]" :style="{ left: x(3), top: y(40.6), width: x(80.4) }" />
            <div class="absolute text-[#444]" :style="{ left: x(3), top: y(37.5), fontSize: f(6.2) }">差出人</div>
            <div class="absolute whitespace-nowrap" :style="{ left: x(3), top: y(42.1), fontSize: f(7.4) }">〒{{ sender.zip }}　{{ sender.address }}</div>
            <div class="absolute whitespace-nowrap" :style="{ left: x(3), top: y(46), fontSize: f(8.4) }">{{ sender.company }}</div>
            <div v-if="tantou" class="absolute whitespace-nowrap text-[#444] text-right" :style="{ right: x(3), top: y(46.3), fontSize: f(7.6) }">担当：{{ tantou }}</div>
        </template>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    person: { type: Object, default: null },
    sender: { type: Object, default: () => ({ zip: '', address: '', company: '' }) },
    addressee: { type: String, default: 'self' }, // self | guardian
    withFamily: { type: Boolean, default: true },
    tantou: { type: String, default: '' },
});

// mm → 面の中の割合。文字の大きさは面の幅に合わせて変える（container query の cqw）
const W = 86.4;
const H = 50.8;
const x = (mm) => `${(mm / W) * 100}%`;
const y = (mm) => `${(mm / H) * 100}%`;
const y2 = (mm) => `${(mm / W) * 100}cqw`;
const f = (pt) => `${((pt * 0.3528) / W) * 100}cqw`;

const addresseeName = computed(() => {
    if (props.addressee === 'guardian' && props.person?.guardian_name) return props.person.guardian_name;
    return props.person?.name || '';
});
// 長い名前は小さくする（labels.py の _fit_font と同じ考え方）
const nameSize = computed(() => {
    const len = addresseeName.value.replace(/\s/g, '').length + 2;
    return Math.max(12, Math.min(22, 150 / len));
});

const qr = ref('');
watch(() => props.person?.code, async (code) => {
    qr.value = code ? await QRCode.toDataURL(code, { margin: 4, width: 120 }).catch(() => '') : '';
}, { immediate: true });
</script>

<style scoped>
.label-face {
    container-type: inline-size;
    font-family: 'Hiragino Kaku Gothic ProN', 'Noto Sans JP', sans-serif;
}
.label-face .font-serif {
    font-family: 'Hiragino Mincho ProN', 'Noto Serif JP', serif;
}
</style>
