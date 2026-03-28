import { formatTimeJa } from '@/utils/dateFormat';

/** 会社カレンダーの A/B/C バッジ用クラス */
export function shiftPatternBadgeClass(p) {
    const key = String(p).toUpperCase();
    const map = {
        A: 'bg-sky-100 text-sky-900',
        B: 'bg-violet-100 text-violet-900',
        C: 'bg-emerald-100 text-emerald-900',
    };
    return map[key] ?? 'bg-gray-100 text-gray-800';
}

/** 丸め後の残業分数表示 */
export function formatOvertimeRounded(payroll) {
    if (!payroll || payroll.overtime_minutes_rounded === null || payroll.overtime_minutes_rounded === undefined) {
        return '—';
    }
    return `${payroll.overtime_minutes_rounded}分`;
}

export function overtimeTooltip(payroll) {
    if (!payroll || payroll.overtime_minutes_rounded === null || payroll.overtime_minutes_rounded === undefined) {
        return '';
    }
    const raw = payroll.overtime_minutes_raw;
    if (raw === null || raw === undefined) {
        return '';
    }
    return `休憩控除後の実分（丸め前）: ${raw}分`;
}

/** 終了時刻が記録された休憩のみ合算（分） */
export function sumCompletedBreakMinutes(breaks) {
    if (!breaks?.length) {
        return null;
    }
    let total = 0;
    for (const b of breaks) {
        if (!b?.start_at || !b?.end_at) {
            continue;
        }
        const start = new Date(b.start_at).getTime();
        const end = new Date(b.end_at).getTime();
        if (Number.isNaN(start) || Number.isNaN(end) || end <= start) {
            continue;
        }
        total += Math.floor((end - start) / 60000);
    }
    return total;
}

export function formatMinutesJa(total) {
    if (total <= 0) {
        return '0分';
    }
    const h = Math.floor(total / 60);
    const m = total % 60;
    if (h === 0) {
        return `${m}分`;
    }
    if (m === 0) {
        return `${h}時間`;
    }
    return `${h}時間${m}分`;
}

export function formatBreakTotalLabel(breaks) {
    if (!breaks?.length) {
        return '—';
    }
    const total = sumCompletedBreakMinutes(breaks);
    const hasOpen = breaks.some((b) => b?.start_at && !b?.end_at);
    if (total === 0 && !hasOpen) {
        return '0分';
    }
    if (total === 0 && hasOpen) {
        return '—';
    }
    return formatMinutesJa(total);
}

export function breaksHasTooltip(breaks) {
    return !!(breaks?.length);
}

/** ネイティブツールチップ用 */
export function formatBreaksTooltip(breaks) {
    if (!breaks?.length) {
        return '';
    }
    const total = sumCompletedBreakMinutes(breaks);
    const lines = [`終了済の合計: ${formatMinutesJa(total ?? 0)}`];
    for (const b of breaks) {
        const s = formatTimeJa(b.start_at);
        const e = b.end_at ? formatTimeJa(b.end_at) : '〜（未終了）';
        lines.push(`${s} 〜 ${e}`);
    }
    return lines.join('\n');
}
