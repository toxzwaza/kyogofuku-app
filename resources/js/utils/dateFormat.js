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

/**
 * ISO 日付文字列を「yyyy年mm月dd日」形式で返す
 * @param {string|null|undefined} iso - ISO 8601 形式または YYYY-MM-DD 形式の日付文字列
 * @returns {string} フォーマット済み文字列、無効な場合は '-'
 */
export function formatDateJa(iso) {
  if (iso === null || iso === undefined) return '-';
  const date = new Date(iso);
  if (isNaN(date.getTime())) return '-';
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}年${m}月${d}日`;
}

const WEEKDAY_JA = ['日', '月', '火', '水', '木', '金', '土'];

/**
 * ISO 日付文字列を「yyyy年mm月dd日(曜)」形式で返す
 * @param {string|null|undefined} iso - ISO 8601 形式または YYYY-MM-DD 形式の日付文字列
 * @returns {string} フォーマット済み文字列、無効な場合は '-'
 */
export function formatDateJaWithWeekday(iso) {
  if (iso === null || iso === undefined) return '-';
  const date = new Date(iso);
  if (isNaN(date.getTime())) return '-';
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  const w = WEEKDAY_JA[date.getDay()];
  return `${y}年${m}月${d}日(${w})`;
}
