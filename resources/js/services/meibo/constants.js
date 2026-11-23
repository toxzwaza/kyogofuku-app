// 名簿（成人式 DM 名簿）の画面で使う定数と小さな関数。

/** DM 状態。並びは画面の表示順 */
export const DM_STATUSES = ['送付可', '未達', '停止'];

/** DM 状態 → UiBadge の variant */
export const DM_STATUS_VARIANT = {
    送付可: 'success',
    未達: 'warning',
    停止: 'danger',
};

/** DM 状態の説明（状態変更ダイアログで使う） */
export const DM_STATUS_HINT = {
    送付可: 'DM を送ってよい',
    未達: '宛先不明などで返送された',
    停止: 'ご本人・ご家族から送付停止の依頼があった',
};

/** 1 ページの件数 */
export const PAGE_SIZE = 50;

/** R11 成人名簿（2026-11-23 統合方針）の出所コード */
export const R11_SOURCE_CODE = 'G_R11';

/** 西暦の成人式年 → 「令和N年」 */
export const wareki = (year) => (year ? `令和${Number(year) - 2018}年` : '');

/** 成人式年の選択肢ラベル「令和11年（2029）」 */
export const yearLabel = (year) => (year ? `${wareki(year)}（${year}）` : '');

/**
 * 統合元版（"G_R07/G_R11/H_2024" のような「/」区切り）を配列にする。
 * 先頭の英字1文字が出所の系統、"_" の後ろが版。
 */
export const parseSources = (sourceVersions) =>
    String(sourceVersions || '')
        .split('/')
        .map((s) => s.trim())
        .filter(Boolean);

/** 統合元版に R11 成人名簿（G_R11）を含むか */
export const hasR11 = (sourceVersions) => parseSources(sourceVersions).includes(R11_SOURCE_CODE);

/** 数値を 3 桁区切りに */
export const num = (n) => Number(n || 0).toLocaleString('ja-JP');

/** ラベル発行テンプレートの初期値（差出人は会社の住所。岡山は画面で入力して保存する） */
export const DEFAULT_LABEL_FORM = {
    addressee: 'self',
    with_family: true,
    tantou: '',
    sender_zip: '918-8015',
    sender_address: '福井県福井市花堂南1-2-1',
    sender_company: '(株)京呉服　平田',
};
