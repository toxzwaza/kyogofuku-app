<script setup>
import { computed } from 'vue';

const props = defineProps({
    cells: { type: Object, required: true }, // { [dow]: { [hr]: cnt } }
    max: { type: Number, default: 0 },
    hoursRange: { type: Array, default: () => [9, 20] }, // [startHr, endHr] inclusive
});

const days = ['月', '火', '水', '木', '金', '土', '日'];
const hours = computed(() => {
    const [s, e] = props.hoursRange;
    const arr = [];
    for (let h = s; h <= e; h++) arr.push(h);
    return arr;
});

const cellCnt = (dow, hr) => props.cells?.[dow]?.[hr] ?? 0;

const bgStyle = (dow, hr) => {
    const cnt = cellCnt(dow, hr);
    if (!cnt || !props.max) return { background: 'transparent' };
    // 0.08 〜 0.85 alpha
    const ratio = cnt / props.max;
    const alpha = 0.08 + ratio * 0.77;
    const v = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim();
    return { background: `rgba(${v} / ${alpha})` };
};
</script>

<template>
    <div class="overflow-x-auto">
        <table class="text-[11px] w-full border-separate border-spacing-[2px]">
            <thead>
                <tr>
                    <th class="w-10"></th>
                    <th v-for="d in days" :key="d" class="font-semibold text-brand-text-muted pb-1">{{ d }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="hr in hours" :key="hr">
                    <th class="text-right pr-2 text-brand-text-muted font-normal tabular-nums">{{ String(hr).padStart(2, '0') }}</th>
                    <td
                        v-for="d in 7"
                        :key="d"
                        class="h-6 rounded-sm border border-brand-border"
                        :style="bgStyle(d - 1, hr)"
                        :title="`${days[d-1]}曜 ${hr}時台: ${cellCnt(d-1, hr)}件`"
                    >
                        <span v-if="cellCnt(d - 1, hr) > 0" class="text-[10px] tabular-nums text-brand-text flex items-center justify-center h-full">
                            {{ cellCnt(d - 1, hr) }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
