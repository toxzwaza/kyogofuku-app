<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { Chart, DoughnutController, ArcElement, Tooltip } from 'chart.js';

Chart.register(DoughnutController, ArcElement, Tooltip);

const props = defineProps({
    data: { type: Array, required: true }, // [{ status, count }]
});

const canvas = ref(null);
let chart = null;

const statusColor = {
    '未対応':       '#62626B', // sumi-500
    '確認中':       '#2C5C94', // ai-500
    '返信待ち':     '#B5920F', // natane-500
    '対応完了済み': '#6A8737', // uguisu-500
    'キャンセル':   '#C42929', // akane-500
};

const total = computed(() => props.data.reduce((s, d) => s + d.count, 0));

const build = () => {
    if (!canvas.value) return;
    if (chart) chart.destroy();
    const labels = props.data.map((d) => d.status);
    const counts = props.data.map((d) => d.count);
    const colors = props.data.map((d) => statusColor[d.status] || '#8A8A93');

    chart = new Chart(canvas.value, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: counts,
                backgroundColor: colors,
                borderWidth: 0,
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.label}: ${ctx.parsed} 件`,
                    },
                },
            },
        },
    });
};

onMounted(build);
watch(() => props.data, build, { deep: true });
</script>

<template>
    <div class="flex items-center gap-5">
        <div class="relative w-32 h-32 flex-shrink-0">
            <canvas ref="canvas" />
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="font-serif text-xl leading-none">{{ total }}</span>
                <span class="text-[10px] text-brand-text-muted">30日合計</span>
            </div>
        </div>
        <ul class="flex-1 space-y-1 text-xs min-w-0">
            <li v-for="d in data" :key="d.status" class="flex items-center justify-between gap-2">
                <span class="flex items-center gap-1.5 min-w-0">
                    <span
                        class="inline-block w-2.5 h-2.5 rounded-sm flex-shrink-0"
                        :style="{ background: statusColor[d.status] || '#8A8A93' }"
                    />
                    <span class="truncate">{{ d.status }}</span>
                </span>
                <span class="text-brand-text-muted tabular-nums">{{ d.count }}</span>
            </li>
        </ul>
    </div>
</template>
