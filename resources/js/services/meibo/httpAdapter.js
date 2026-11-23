/**
 * 名簿 API（本物）。次の段階で kyogofuku-app のサーバーに API を作ってから使う。
 * 関数の形は dummyAdapter.js と同じ。パスは仮（/admin/meibo/api/...）で、まだサーバー側にない。
 */
import axios from 'axios';

const BASE = '/admin/meibo/api';
const clean = (params) => Object.fromEntries(
    Object.entries(params || {}).filter(([, v]) => v !== '' && v != null),
);
const get = (url, params) => axios.get(BASE + url, { params: clean(params) }).then((r) => r.data);

export const httpAdapter = {
    isDummy: false,
    options: () => get('/options'),
    persons: (params) => get('/persons', params),
    personIds: (params) => get('/persons/ids', params),
    personsByIds: (ids, limit = 10) => get('/persons/by-ids', { ids: ids.slice(0, limit).join(',') }),
    person: (id) => get(`/persons/${id}`),
    updateDM: (id, dmStatus, dmNote) =>
        axios.patch(`${BASE}/persons/${id}`, { dm_status: dmStatus, dm_note: dmNote }).then((r) => r.data),
    templates: () => get('/templates'),
    saveTemplate: (t) => axios.post(`${BASE}/templates`, t).then((r) => r.data),
    deleteTemplate: (id) => axios.delete(`${BASE}/templates/${id}`).then((r) => r.data),
    labels: async (body) => {
        const r = await axios.post(`${BASE}/labels`, body, { responseType: 'blob' });
        return { blob: r.data, count: Number(r.headers['x-label-count'] || body.ids?.length || 0) };
    },
    stats: () => get('/stats'),
    map: (params) => get('/map', params),
};
