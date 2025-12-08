<template>
    <div class="chart-container">
        <canvas ref="chartCanvas"></canvas>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue';
import {
    Chart,
    CategoryScale,
    LinearScale,
    BarElement,
    BarController,
    LineElement,
    LineController,
    PointElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

// Chart.jsのコンポーネントを登録
Chart.register(
    CategoryScale,
    LinearScale,
    BarElement,
    BarController,
    LineElement,
    LineController,
    PointElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    data: {
        type: Array,
        required: true,
    },
    type: {
        type: String,
        default: 'bar', // 'bar' or 'line'
    },
});

const chartCanvas = ref(null);
const chartInstance = ref(null);

const chartData = computed(() => {
    return {
        labels: props.data.map(item => item.label),
        datasets: [
            {
                label: '予約',
                data: props.data.map(item => item.reservation),
                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 2,
                tension: 0.4,
                fill: props.type === 'line',
            },
            {
                label: '資料請求',
                data: props.data.map(item => item.document),
                backgroundColor: 'rgba(34, 197, 94, 0.6)',
                borderColor: 'rgba(34, 197, 94, 1)',
                borderWidth: 2,
                tension: 0.4,
                fill: props.type === 'line',
            },
            {
                label: '問い合わせ',
                data: props.data.map(item => item.contact),
                backgroundColor: 'rgba(168, 85, 247, 0.6)',
                borderColor: 'rgba(168, 85, 247, 1)',
                borderWidth: 2,
                tension: 0.4,
                fill: props.type === 'line',
            },
        ],
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
            labels: {
                usePointStyle: true,
                padding: 15,
                font: {
                    size: 12,
                },
            },
        },
        tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: {
                size: 14,
                weight: 'bold',
            },
            bodyFont: {
                size: 12,
            },
            callbacks: {
                label: function(context) {
                    return `${context.dataset.label}: ${context.parsed.y}件`;
                },
            },
        },
    },
    scales: {
        x: {
            stacked: props.type === 'bar',
            grid: {
                display: false,
            },
            ticks: {
                font: {
                    size: 11,
                },
            },
        },
        y: {
            stacked: props.type === 'bar',
            beginAtZero: true,
            ticks: {
                stepSize: 1,
                font: {
                    size: 11,
                },
                callback: function(value) {
                    return value + '件';
                },
            },
            grid: {
                color: 'rgba(0, 0, 0, 0.05)',
            },
        },
    },
    interaction: {
        mode: 'nearest',
        axis: 'x',
        intersect: false,
    },
}));

onMounted(() => {
    if (chartCanvas.value) {
        const ctx = chartCanvas.value.getContext('2d');
        chartInstance.value = new Chart(ctx, {
            type: props.type,
            data: chartData.value,
            options: chartOptions.value,
        });
    }
});

watch(
    () => props.data,
    () => {
        if (chartInstance.value) {
            chartInstance.value.data = chartData.value;
            chartInstance.value.update('active');
        }
    },
    { deep: true }
);

watch(
    () => props.type,
    () => {
        if (chartInstance.value) {
            chartInstance.value.destroy();
            const ctx = chartCanvas.value.getContext('2d');
            chartInstance.value = new Chart(ctx, {
                type: props.type,
                data: chartData.value,
                options: chartOptions.value,
            });
        }
    }
);

onBeforeUnmount(() => {
    if (chartInstance.value) {
        chartInstance.value.destroy();
    }
});
</script>

<style scoped>
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}
</style>

