<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

const props = defineProps({
    data: { type: Array, required: true }, // [{ source, count }]
});

const canvas = ref(null);
let chart = null;

// 上位から色を割り当て（和色パレット。件数の多い経路ほど濃い色）
const palette = [
    '#2C5C94', // ai-500
    '#6A8737', // uguisu-500
    '#B5920F', // natane-500
    '#C42929', // akane-500
    '#7B5CA6',
    '#3B8686',
    '#B06A3B',
    '#62626B', // sumi-500
];

const total = computed(() => props.data.reduce((s, d) => s + d.count, 0));

// バー数に応じて高さを可変にする（1本あたり約32px）
const chartHeight = computed(() => Math.max(120, props.data.length * 32 + 24));

const build = () => {
    if (!canvas.value) return;
    if (chart) chart.destroy();

    chart = new Chart(canvas.value, {
        type: 'bar',
        data: {
            labels: props.data.map((d) => d.source),
            datasets: [{
                data: props.data.map((d) => d.count),
                backgroundColor: props.data.map((_, i) => palette[i % palette.length]),
                borderRadius: 3,
                barThickness: 16,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const pct = total.value > 0
                                ? Math.round((ctx.parsed.x / total.value) * 1000) / 10
                                : 0;
                            return `${ctx.parsed.x} 件（${pct}%）`;
                        },
                    },
                },
            },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } },
                y: { ticks: { autoSkip: false } },
            },
        },
    });
};

onMounted(build);
watch(() => props.data, build, { deep: true });
</script>

<template>
    <div :style="{ height: chartHeight + 'px' }">
        <canvas ref="canvas" />
    </div>
</template>
