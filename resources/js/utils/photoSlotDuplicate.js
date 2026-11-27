// 前撮りの二重登録：お客様詳細で「前撮り情報を追加」する前に、今の登録を確かめる。
// 数え方はサーバー（PhotoSlotBookingService::countedBookingsQuery）と同じ：日時未定の行と、撮影日が今日以降の枠。
// 最後に決めるのはサーバー（ここで見落としても、サーバーが止める）。

const WEEK = ['日', '月', '火', '水', '木', '金', '土'];

const todayString = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

/** 今の登録として数える前撮り（取り消した予約はお客様から外れているので入らない） */
export const countedPhotoSlots = (photoSlots) => {
    const today = todayString();
    return (photoSlots || []).filter((s) => s.details_undecided || (s.shoot_date && String(s.shoot_date).slice(0, 10) >= today));
};

const hm = (time) => {
    if (!time) return '';
    const [h, m] = String(time).split(':');
    return `${Number(h)}:${m}`;
};

/** 確認の文に出す日時（例：2026/12/5（土）8:30 岡山ガーデン／日時未定） */
export const photoSlotLabel = (slot) => {
    if (!slot || slot.details_undecided || !slot.shoot_date) return '日時未定';
    const [y, m, d] = String(slot.shoot_date).slice(0, 10).split('-').map(Number);
    const week = WEEK[new Date(y, m - 1, d).getDay()];
    const studio = slot.studio?.name ? ` ${slot.studio.name}` : '';
    return `${y}/${m}/${d}（${week}）${hm(slot.shoot_time)}${studio}`;
};

/** 入れる先の日時（例：2026/12/5（土）9:00） */
export const targetLabel = (date, time) => {
    if (!date) return '日時未定';
    return photoSlotLabel({ shoot_date: date, shoot_time: time });
};
