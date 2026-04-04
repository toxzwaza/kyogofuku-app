/**
 * ISO 日付文字列を「yyyy年mm月dd日 hh:mm:ss」形式で返す
 * @param {string|null|undefined} iso - ISO 8601 形式の日付文字列
 * @returns {string} フォーマット済み文字列、無効な場合は '-'
 */
export function formatDateTimeJa(iso) {
  if (iso === null || iso === undefined) return '-';
  const date = new Date(iso);
  if (isNaN(date.getTime())) return '-';
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  const h = String(date.getHours()).padStart(2, '0');
  const min = String(date.getMinutes()).padStart(2, '0');
  const s = String(date.getSeconds()).padStart(2, '0');
  return `${y}年${m}月${d}日 ${h}:${min}:${s}`;
}

/**
 * ISO 日付文字列を「hh:mm」形式で返す（出勤・退勤・休憩時刻用）
 * @param {string|null|undefined} iso - ISO 8601 形式の日付文字列
 * @returns {string} フォーマット済み文字列、無効な場合は '-'
 */
export function formatTimeJa(iso) {
  if (iso === null || iso === undefined) return '-';
  const date = new Date(iso);
  if (isNaN(date.getTime())) return '-';
  const h = String(date.getHours()).padStart(2, '0');
  const min = String(date.getMinutes()).padStart(2, '0');
  return `${h}:${min}`;
}

/** アプリの「日付のみ」表示は日本の暦日に揃える（DBの date / Y-m-d と一致させる） */
const TOKYO = 'Asia/Tokyo';

function ymdPartsInTokyo(iso) {
  const date = new Date(iso);
  if (isNaN(date.getTime())) return null;
  const ymd = new Intl.DateTimeFormat('en-CA', {
    timeZone: TOKYO,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(date);
  const [y, m, d] = ymd.split('-').map((n) => parseInt(n, 10));
  if (!y || !m || !d) return null;
  return { y, m, d };
}

/**
 * ISO 日付文字列を「yyyy年mm月dd日」形式で返す
 * - 純粋な YYYY-MM-DD はタイムゾーンに依存せずその暦日を表示
 * - それ以外（Carbon の ISO 等）は Asia/Tokyo の暦日で表示（前日ずれ防止）
 * @param {string|null|undefined} iso - ISO 8601 形式または YYYY-MM-DD 形式の日付文字列
 * @returns {string} フォーマット済み文字列、無効な場合は '-'
 */
export function formatDateJa(iso) {
  if (iso === null || iso === undefined) return '-';
  const raw = String(iso).trim();
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    const [y, mo, d] = raw.split('-').map((n) => parseInt(n, 10));
    return `${y}年${String(mo).padStart(2, '0')}月${String(d).padStart(2, '0')}日`;
  }
  const parts = ymdPartsInTokyo(raw);
  if (!parts) return '-';
  const { y, m, d } = parts;
  return `${y}年${String(m).padStart(2, '0')}月${String(d).padStart(2, '0')}日`;
}

const WEEKDAY_JA = ['日', '月', '火', '水', '木', '金', '土'];

/**
 * ISO 日付文字列を「yyyy年mm月dd日(曜)」形式で返す
 * @param {string|null|undefined} iso - ISO 8601 形式または YYYY-MM-DD 形式の日付文字列
 * @returns {string} フォーマット済み文字列、無効な場合は '-'
 */
export function formatDateJaWithWeekday(iso) {
  if (iso === null || iso === undefined) return '-';
  const raw = String(iso).trim();
  let y;
  let mo;
  let d;
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    [y, mo, d] = raw.split('-').map((n) => parseInt(n, 10));
  } else {
    const parts = ymdPartsInTokyo(raw);
    if (!parts) return '-';
    y = parts.y;
    mo = parts.m;
    d = parts.d;
  }
  const w = WEEKDAY_JA[new Date(Date.UTC(y, mo - 1, d)).getUTCDay()];
  return `${y}年${String(mo).padStart(2, '0')}月${String(d).padStart(2, '0')}日(${w})`;
}

/**
 * input[type=date] の value 用 YYYY-MM-DD（formatDateJa と同じ暦日解釈。toISOString().slice は使わない）
 * @param {string|null|undefined} iso
 * @returns {string} 空のとき ''
 */
export function formatDateInputValueJa(iso) {
  if (iso === null || iso === undefined || iso === '') return '';
  const raw = String(iso).trim();
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
  const parts = ymdPartsInTokyo(raw);
  if (!parts) return '';
  const { y, m, d } = parts;
  return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}
