<template>
    <UiCard padding="sm">
        <div class="flex flex-wrap items-end gap-3 mb-3">
            <h2 class="font-serif text-base mr-auto sm:mr-2 self-center">地域分布マップ</h2>

            <div class="inline-flex rounded-soft border border-brand-border-strong overflow-hidden text-sm" role="group" aria-label="表示のしかた">
                <button
                    v-for="v in VIEWS"
                    :key="v.value"
                    type="button"
                    :aria-pressed="view === v.value"
                    :class="['px-3 py-1.5 transition-colors', view === v.value ? 'bg-brand-primary text-brand-on-primary' : 'bg-brand-surface hover:bg-brand-surface-2']"
                    @click="view = v.value"
                >{{ v.label }}</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 w-full sm:w-auto sm:ml-auto">
                <UiSelect v-model="f.seijin_year" :options="yearOptions" />
                <UiSelect v-model="f.status" :options="statusOptions" />
                <UiSelect v-model="f.source" :options="SOURCE_OPTIONS" />
            </div>
        </div>

        <div class="relative isolate z-0">
            <div ref="mapEl" class="h-[360px] sm:h-[460px] w-full rounded-soft border border-brand-border bg-brand-surface-2" />
            <div v-if="loading" class="absolute inset-0 grid place-items-center bg-brand-surface/60 text-sm text-brand-text-muted rounded-soft z-[500]">読み込み中…</div>
            <div v-else-if="failed" class="absolute inset-0 grid place-items-center text-sm text-brand-danger z-[500]">地図を表示できませんでした：{{ failed }}</div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-brand-text-muted">
            <span>表示 <b class="text-brand-text tabular-nums">{{ num(info.plotted) }}</b> 人・{{ num(info.spots) }} 地点</span>
            <span v-if="info.unmatched">位置が分からない {{ num(info.unmatched) }} 人は除いています</span>
            <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
                <template v-if="view === 'bubble'">
                    <span v-for="s in STEPS" :key="s.min" class="inline-flex items-center gap-1">
                        <span class="inline-block w-3 h-3 rounded-full" :style="{ background: s.color }" />{{ s.label }}
                    </span>
                </template>
                <template v-else>
                    <span>少</span>
                    <span class="inline-block h-2.5 w-32 rounded-full" :style="{ background: heatLegend }" />
                    <span>多</span>
                </template>
            </div>
        </div>
    </UiCard>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { UiCard, UiSelect } from '@/Components/UI';
import { num, yearLabel } from '@/services/meibo';

const props = defineProps({
    api: { type: Object, required: true },
    options: { type: Object, default: () => ({ seijin_years: [], statuses: [] }) },
});

const VIEWS = [
    { value: 'heat', label: 'ヒートマップ' },
    { value: 'bubble', label: 'バブル' },
];
const SOURCE_OPTIONS = [
    { value: '', label: '出所：すべて' },
    { value: 'r11', label: 'R11 成人名簿' },
    { value: 'other', label: 'R11 以外' },
];
// 名簿管理システムの MapBlock と同じ色の段階
const STEPS = [
    { min: 200, color: '#7b0d1e', label: '200〜' },
    { min: 100, color: '#b3172f', label: '100〜' },
    { min: 50, color: '#e0563b', label: '50〜' },
    { min: 20, color: '#f0902f', label: '20〜' },
    { min: 5, color: '#f2c14e', label: '5〜' },
    { min: 0, color: '#3f8ccb', label: '〜4' },
];
const HEAT_GRADIENT = { 0.0: '#3a6ea5', 0.25: '#66c2a5', 0.45: '#f4e04d', 0.65: '#f4a13c', 0.85: '#e0563b', 1.0: '#b3172f' };
const heatLegend = `linear-gradient(to right, ${Object.entries(HEAT_GRADIENT).map(([k, c]) => `${c} ${k * 100}%`).join(', ')})`;
const colorFor = (count) => STEPS.find((s) => count >= s.min).color;
const radiusFor = (count) => Math.max(5, Math.min(42, Math.sqrt(count) * 2));

const f = reactive({ seijin_year: '', status: '', source: '' });
const view = ref('heat');
const info = ref({ plotted: 0, spots: 0, unmatched: 0 });
const loading = ref(true);
const failed = ref('');

const yearOptions = computed(() => [{ value: '', label: '成人式年：すべて' }, ...props.options.seijin_years.map((y) => ({ value: String(y), label: yearLabel(y) }))]);
const statusOptions = computed(() => [{ value: '', label: 'DM状態：すべて' }, ...props.options.statuses.map((s) => ({ value: s, label: s }))]);

const mapEl = ref(null);
let L = null;
let map = null;
let bubbles = null;
let heat = null;
let points = [];

const escape = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const draw = () => {
    if (!map) return;
    bubbles.clearLayers();
    if (heat) { map.removeLayer(heat); heat = null; }
    if (!points.length) return;
    if (view.value === 'heat') {
        const maxC = Math.max(...points.map((p) => p.count), 1);
        heat = L.heatLayer(points.map((p) => [p.lat, p.lng, Math.sqrt(p.count)]), {
            radius: 18, blur: 14, minOpacity: 0.4, max: Math.sqrt(maxC) * 0.8, gradient: HEAT_GRADIENT,
        }).addTo(map);
    } else {
        // 大きい円を下、小さい円を上に描いて全部見えるようにする
        [...points].sort((a, b) => b.count - a.count).forEach((p) => {
            L.circleMarker([p.lat, p.lng], {
                radius: radiusFor(p.count), color: '#fff', weight: 1, fillColor: colorFor(p.count), fillOpacity: 0.82,
            })
                .bindPopup(`<b>${escape(p.area)}</b><br>〒${escape(p.zip)}<br><b style="font-size:15px">${p.count.toLocaleString()}</b> 名`)
                .addTo(bubbles);
        });
    }
};

const load = async () => {
    if (!map) return;
    loading.value = true;
    try {
        const d = await props.api.map({ ...f });
        info.value = { plotted: d.plotted, spots: d.spots, unmatched: d.unmatched };
        points = d.points;
        draw();
        if (points.length) map.fitBounds(L.latLngBounds(points.map((p) => [p.lat, p.lng])).pad(0.15));
        failed.value = '';
    } catch (e) {
        failed.value = e.message;
    } finally {
        loading.value = false;
    }
};

watch(f, load);
watch(view, draw);

onMounted(async () => {
    try {
        // 地図のライブラリは重いので、この画面を開いたときだけ読み込む。
        // leaflet.heat は window.L に足す作りなので、先に L を置いてから読む
        L = (await import('leaflet')).default;
        window.L = L;
        await Promise.all([import('leaflet/dist/leaflet.css'), import('leaflet.heat/dist/leaflet-heat.js')]);
        if (!mapEl.value) return;
        map = L.map(mapEl.value, { scrollWheelZoom: false }).setView([35.5, 135.5], 7);
        // 下地は国土地理院の淡色地図（キー不要。元の名簿管理システムの CARTO light は API キーが要るようになったため）
        L.tileLayer('https://cyberjapandata.gsi.go.jp/xyz/pale/{z}/{x}/{y}.png', {
            attribution: '<a href="https://maps.gsi.go.jp/development/ichiran.html" target="_blank" rel="noopener">国土地理院</a>',
            maxZoom: 18,
        }).addTo(map);
        bubbles = L.layerGroup().addTo(map);
        await load();
    } catch (e) {
        failed.value = e.message;
        loading.value = false;
    }
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>
