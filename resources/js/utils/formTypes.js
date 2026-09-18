// フォーム種別の共通定義（PHP側 App\Models\Event の定数と対応）

/** 袴予約系のフォーム種別（岡山・福井。フォーム内容は共通、ラベルのみ異なる） */
export const HAKAMA_FORM_TYPES = ['reservation_hakama', 'reservation_hakama_fukui'];

/** タイムスロット予約を使うフォーム種別 */
export const TIMESLOT_RESERVATION_FORM_TYPES = ['reservation', ...HAKAMA_FORM_TYPES];

/** フォーム種別 → 日本語ラベル */
export const FORM_TYPE_LABELS = {
    reservation: '振袖予約',
    reservation_hakama: '袴予約（岡山）',
    reservation_hakama_fukui: '袴予約（福井）',
    document: '資料請求',
    contact: 'お問い合わせ',
};

/** 袴予約系（岡山・福井）か */
export function isHakamaForm(formType) {
    return HAKAMA_FORM_TYPES.includes(formType);
}

/** タイムスロット予約フォーム（振袖・袴）か */
export function isTimeslotReservationForm(formType) {
    return TIMESLOT_RESERVATION_FORM_TYPES.includes(formType);
}

/** フォーム種別の日本語ラベル */
export function formTypeLabel(formType) {
    return FORM_TYPE_LABELS[formType] ?? formType;
}

/** 袴フォームの「振袖利用」設問ラベル（岡山=好一／福井=平田） */
export function furisodeUsageLabel(formType) {
    return formType === 'reservation_hakama_fukui' ? '平田での振袖利用' : '好一での振袖利用';
}
