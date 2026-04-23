<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler);

const props = defineProps({
    data: { type: Array, required: true }, // [{ date, count, is_past, is_today }]
});

const canvas = ref(null);
let chart = null;

const labels = computed(() => props.data.map((d) => {
    const parts = d.date.split('-');
    return `${parts[1]}/${parts[2]}`;
}));
const counts = computed(() => props.data.map((d) => d.count));
const todayIdx = computed(() => props.data.findIndex((d) => d.is_today));

const getPrimary = () => {
    const v = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim();
    return `rgb(${v})`;
};
const getMuted = () => {
    const v = getComputedStyle(document.documentElement).getPropertyValue('--color-text-subtle').trim();
    return `rgb(${v})`;
};

const build = () => {
    if (!canvas.value) return;
    if (chart) chart.destroy();
    const primary = getPrimary();
    const muted   = getMuted();
    chart = new Chart(canvas.value, {
        type: 'line',
        data: {
            labels: labels.value,
            datasets: [{
                data: counts.value,
                borderColor: primary,
                backgroundColor: primary + '22',
                fill: true,
                tension: 0.35,
                pointRadius: props.data.map((d) => d.is_today ? 5 : 2),
                pointHoverRadius: 5,
                pointBackgroundColor: props.data.map((d) => d.is_today ? primary : '#fff'),
                pointBorderColor: primary,
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    callbacks: {
                        title: (items) => props.data[items[0].dataIndex]?.date,
                        label:  (ctx)  => `${ctx.parsed.y} 件`,
                    },
                },
            },
            scales: {
                x: {
                    ticks: { color: muted, font: { size: 10 }, maxRotation: 0 },
                    grid:  { display: false },
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: muted, font: { size: 10 }, precision: 0 },
                    grid:  { color: muted + '20' },
                },
            },
        },
    });
};

onMounted(build);
watch(() => props.data, build, { deep: true });
</script>

<template>
    <div class="relative h-40">
        <canvas ref="canvas" />
    </div>
</template>
