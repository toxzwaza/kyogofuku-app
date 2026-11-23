/**
 * 名簿のデータ（本物）。この kyogofuku-app の DB の meibo_* の表を、MeiboController のルート
 * （/admin/meibo/data/...。ログインのセッション・CSRF つき）から読む・書く。別の API サーバーは無い。
 * 関数の形は dummyAdapter.js と同じ。使うのは MEIBO_DATA_SOURCE=db のとき。
 */
import axios from 'axios';

const BASE = '/admin/meibo/data';
const clean = (params) => Object.fromEntries(
    Object.entries(params || {}).filter(([, v]) => v !== '' && v != null),
);
// サーバーのエラー文（422 の最初の項目・409 など）を Error の文にする
const toError = (e) => {
    const d = e.response?.data;
    const first = d?.errors ? Object.values(d.errors).flat()[0] : null;
    const err = new Error(first || d?.message || e.message);
    if (e.response?.status === 409) err.code = 'name_taken';
    if (e.response?.status === 404) err.message = first || '見つかりません';
    throw err;
};
const get = (url, params) => axios.get(BASE + url, { params: clean(params) }).then((r) => r.data).catch(toError);
const send = (method, url, body) => axios({ method, url: BASE + url, data: body }).then((r) => r.data).catch(toError);

export const dbAdapter = {
    isDummy: false,
    options: () => get('/options'),
    persons: (params) => get('/persons', params),
    personIds: (params) => get('/persons/ids', params),
    personsByIds: (ids, limit = 10) => get('/persons/by-ids', { ids: ids.slice(0, limit).join(',') }),
    // 選んだ人の件数・うち R11 の人数（全選択の 5 万件を URL に載せないよう POST）
    summary: (ids) => send('post', '/persons/summary', { ids }),
    person: (id) => get(`/persons/${id}`),
    updateDM: (id, dmStatus, dmNote) => send('patch', `/persons/${id}`, { dm_status: dmStatus, dm_note: dmNote }),
    templates: () => get('/templates'),
    // 追加：同じ名前があれば 409 → code: 'name_taken'（画面で上書きの確認をしてから updateTemplate を呼ぶ）
    createTemplate: (t) => send('post', '/templates', t),
    // 編集（上書き）：ほかのテンプレートと同じ名前にすると 409
    updateTemplate: (id, t) => send('put', `/templates/${id}`, t),
    deleteTemplate: (id) => send('delete', `/templates/${id}`),
    // ラベルPDF（TCPDF）は次の段階。いまは件数だけ返す
    labels: async ({ ids = [] } = {}) => ({ count: ids.length, blob: null }),
    stats: () => get('/stats'),
    map: (params) => get('/map', params),
};
