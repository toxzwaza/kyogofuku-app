<script setup>
// 前撮り枠の印刷：1日分の表（行＝時刻・列＝スタジオ）。見出しの行（日付・スタジオ名）はページをまたぐと次のページにも出る。
const props = defineProps({
    day: { type: Object, required: true },
    showDetail: { type: Boolean, default: true },
    dense: { type: Boolean, default: false },
});

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];
const dayLabel = (day) => {
    const [, m, d] = day.date.split('-').map(Number);
    return `${m}/${d}（${WEEKDAYS[day.weekday]}）`;
};
const weekdayClass = (w) => (w === 0 ? 'is-sun' : w === 6 ? 'is-sat' : '');
const cellOf = (col, time) => props.day.cells?.[col.key]?.[time] || null;
const shopsOf = (col) => {
    const names = new Set();
    Object.values(props.day.cells?.[col.key] || {}).forEach(c => c.seats.forEach(s => s.shops.forEach(n => names.add(n))));
    return [...names].join('・');
};
const seatDetail = (seat) => [seat.shops.join('・'), seat.user, seat.plan].filter(Boolean).join(' / ');
</script>

<template>
    <table class="day-table" :class="{ dense }">
        <colgroup>
            <col class="time-col-w" />
            <col v-for="col in day.columns" :key="col.key" />
        </colgroup>
        <thead>
            <tr>
                <th :colspan="day.columns.length + 1" class="day-head">
                    <span :class="weekdayClass(day.weekday)">{{ dayLabel(day) }}</span>
                    <span class="day-count">予約 {{ day.booked }}／定員 {{ day.capacity }}</span>
                </th>
            </tr>
            <tr>
                <th class="time-col">時刻</th>
                <th v-for="col in day.columns" :key="col.key" class="studio-head">
                    <div>{{ col.studio.name }}</div>
                    <div v-if="shopsOf(col)" class="studio-shops">{{ shopsOf(col) }}</div>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="time in day.times" :key="time">
                <th class="time-col">{{ time }}</th>
                <td v-for="col in day.columns" :key="col.key + time" :class="cellOf(col, time) ? '' : 'no-slot'">
                    <ul v-if="cellOf(col, time)">
                        <li v-for="seat in cellOf(col, time).seats" :key="seat.id" :class="seat.customer ? 'booked' : 'vacant'">
                            <template v-if="seat.customer">
                                <span class="name">{{ seat.customer.name }}</span>
                                <span v-if="showDetail && seatDetail(seat)" class="detail">{{ seatDetail(seat) }}</span>
                                <span v-if="showDetail && seat.remarks" class="detail">備考：{{ seat.remarks }}</span>
                            </template>
                            <template v-else>空き</template>
                        </li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped>
.day-table { width: 100%; border-collapse: collapse; font-size: 9pt; table-layout: fixed; }
.day-table thead { display: table-header-group; }
.day-table tr { break-inside: avoid; page-break-inside: avoid; }
.day-table th, .day-table td { border: 1px solid #888; padding: 1mm 1.5mm; vertical-align: top; overflow-wrap: anywhere; }
.day-head { text-align: left; font-size: 10.5pt; background: #e8e8e8; }
.day-count { margin-left: 4mm; font-size: 8.5pt; font-weight: normal; color: #444; }
.is-sat { color: #1d4ed8; }
.is-sun { color: #b91c1c; }
.time-col-w { width: 14mm; }
.time-col { text-align: center; background: #f3f3f3; font-variant-numeric: tabular-nums; }
.studio-head { text-align: left; background: #f8f8f8; font-weight: 600; }
.studio-shops { font-size: 7.5pt; font-weight: normal; color: #555; }
.no-slot { background: #ececec; }
ul { margin: 0; padding: 0; list-style: none; }
li + li { border-top: 1px dotted #bbb; margin-top: 0.5mm; padding-top: 0.5mm; }
.booked .name { font-weight: 600; }
.booked .detail { display: block; font-size: 7.5pt; color: #444; }
.vacant { color: #888; }

/* 1ページに2日分以上：少し小さく詰める */
.dense { font-size: 8pt; line-height: 1.25; }
.dense th, .dense td { padding: 0.5mm 1mm; }
.dense li + li { margin-top: 0.3mm; padding-top: 0.3mm; }
.dense .day-head { font-size: 9pt; }
.dense .day-count { margin-left: 2mm; font-size: 7.5pt; }
.dense .time-col-w { width: 11mm; }
.dense .studio-shops, .dense .booked .detail { font-size: 6.5pt; }
</style>
