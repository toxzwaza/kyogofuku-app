// 前撮り枠の時刻（30分刻み）の補助。サーバーの App\Services\PhotoSlot\PhotoStudioHours と同じ考え方。

export const STEP_MINUTES = 30;
export const MAX_CAPACITY = 10;
export const WEEKDAY_NAMES = ['日', '月', '火', '水', '木', '金', '土'];
// 月曜始まりの表示順
export const WEEKDAY_ORDER = [1, 2, 3, 4, 5, 6, 0];

export const toMinutes = (t) => {
    const [h, m] = String(t).split(':').map(Number);
    return h * 60 + m;
};

export const fromMinutes = (m) =>
    `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;

/** 開始〜終了の間の枠の時刻（最後の枠は終了の30分前） */
export const timesBetween = (start, end) => {
    if (!start || !end) return [];
    const out = [];
    for (let m = toMinutes(start); m + STEP_MINUTES <= toMinutes(end); m += STEP_MINUTES) {
        out.push(fromMinutes(m));
    }
    return out;
};

/** 選択肢用の時刻（既定 6:00〜22:00 の30分刻み） */
export const timeOptions = (from = '06:00', to = '22:00') => {
    const out = [];
    for (let m = toMinutes(from); m <= toMinutes(to); m += STEP_MINUTES) out.push(fromMinutes(m));
    return out;
};

/** 表示用 'HH:MM' → 'H:MM' */
export const shortTime = (t) => (t ? String(t).slice(0, 5).replace(/^0/, '') : '');

/**
 * 「この営業時間でできる枠」の説明。曜日ごとの時刻が同じものはまとめる。
 * weekdayHours: [{weekday, start_time, end_time}]
 */
export const hoursSummary = (defaultStart, defaultEnd, weekdayHours = []) => {
    const groups = new Map();
    for (const wd of WEEKDAY_ORDER) {
        const row = weekdayHours.find((r) => Number(r.weekday) === wd);
        const start = row ? row.start_time : defaultStart;
        const end = row ? row.end_time : defaultEnd;
        if (!start || !end) continue;
        const key = `${start}-${end}`;
        if (!groups.has(key)) groups.set(key, { start, end, days: [] });
        groups.get(key).days.push(WEEKDAY_NAMES[wd]);
    }
    return [...groups.values()].map((g) => {
        const times = timesBetween(g.start, g.end);
        return { ...g, days: g.days.join('・'), last: times[times.length - 1] || g.start, count: times.length };
    });
};
