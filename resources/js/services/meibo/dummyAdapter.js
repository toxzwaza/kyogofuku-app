/**
 * 名簿 API のダミー（画面確認用）。
 *
 * 名前・住所・電話はすべて架空（決まった種から作るので、開くたびに同じ並びになる）。
 * 実データは入れない。変更（DM 状態・テンプレート）はこのタブを開いている間だけ覚える。
 * 関数の形は dbAdapter.js と同じ。本物（この DB の meibo_* の表）につなぐときは env MEIBO_DATA_SOURCE=db にする。
 */
import { DM_STATUSES, R11_SOURCE_CODE, hasR11, DEFAULT_LABEL_FORM } from './constants.js';

// ---- 決まった種の乱数 ----
function mulberry32(seed) {
    let a = seed;
    return () => {
        a |= 0;
        a = (a + 0x6d2b79f5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}
const rand = mulberry32(20261123);
const pick = (arr) => arr[Math.floor(rand() * arr.length)];
const pad = (n, w) => String(n).padStart(w, '0');

// ---- 架空の名前 ----
const SURNAMES = [
    ['山田', 'ヤマダ'], ['佐藤', 'サトウ'], ['鈴木', 'スズキ'], ['高橋', 'タカハシ'], ['田中', 'タナカ'],
    ['伊藤', 'イトウ'], ['渡辺', 'ワタナベ'], ['中村', 'ナカムラ'], ['小林', 'コバヤシ'], ['加藤', 'カトウ'],
    ['吉田', 'ヨシダ'], ['山口', 'ヤマグチ'], ['松本', 'マツモト'], ['井上', 'イノウエ'], ['木村', 'キムラ'],
    ['清水', 'シミズ'], ['森', 'モリ'], ['池田', 'イケダ'], ['橋本', 'ハシモト'], ['石川', 'イシカワ'],
];
const GIVEN = [
    ['花子', 'ハナコ'], ['美咲', 'ミサキ'], ['結衣', 'ユイ'], ['陽菜', 'ヒナ'], ['葵', 'アオイ'],
    ['さくら', 'サクラ'], ['凛', 'リン'], ['芽依', 'メイ'], ['心春', 'コハル'], ['莉子', 'リコ'],
    ['彩花', 'アヤカ'], ['七海', 'ナナミ'], ['楓', 'カエデ'], ['紬', 'ツムギ'], ['澪', 'ミオ'],
];
const GUARDIAN_GIVEN = ['一郎', '健二', '浩', '誠', '洋子', '恵子', '直美', '由美'];

// ---- 地域（市区の中心の座標と郵便番号の頭3桁。番地はすべて架空） ----
const AREAS = [
    { pref: '福井県', city: '福井市', zip: '910', lat: 36.0641, lng: 136.2196, w: 30, tel: '0776' },
    { pref: '福井県', city: '坂井市', zip: '919', lat: 36.1669, lng: 136.2316, w: 10, tel: '0776' },
    { pref: '福井県', city: '鯖江市', zip: '916', lat: 35.9566, lng: 136.1843, w: 8, tel: '0778' },
    { pref: '福井県', city: '越前市', zip: '915', lat: 35.9035, lng: 136.1688, w: 8, tel: '0778' },
    { pref: '福井県', city: '敦賀市', zip: '914', lat: 35.6452, lng: 136.0555, w: 5, tel: '0770' },
    { pref: '福井県', city: '大野市', zip: '912', lat: 35.9797, lng: 136.4875, w: 3, tel: '0779' },
    { pref: '福井県', city: 'あわら市', zip: '919', lat: 36.2114, lng: 136.229, w: 3, tel: '0776' },
    { pref: '岡山県', city: '岡山市北区', zip: '700', lat: 34.6551, lng: 133.9195, w: 12, tel: '086' },
    { pref: '岡山県', city: '岡山市南区', zip: '702', lat: 34.6037, lng: 133.9137, w: 6, tel: '086' },
    { pref: '岡山県', city: '倉敷市', zip: '710', lat: 34.585, lng: 133.772, w: 10, tel: '086' },
    { pref: '岡山県', city: '津山市', zip: '708', lat: 35.0691, lng: 134.0044, w: 3, tel: '0868' },
    { pref: '岡山県', city: '総社市', zip: '719', lat: 34.6727, lng: 133.7465, w: 2, tel: '0866' },
    { pref: '石川県', city: '金沢市', zip: '920', lat: 36.5613, lng: 136.6562, w: 4, tel: '076' },
    { pref: '石川県', city: '加賀市', zip: '922', lat: 36.3028, lng: 136.315, w: 2, tel: '0761' },
];
const TOWNS = ['中央', '本町', '花園', '若葉町', '旭町', '緑ケ丘', '桜木町', '栄町'];
const SOURCES = ['G_R07', 'G_R08', 'H_2023', 'H_2024', 'N_2025'];
const NOTES_MISS = ['宛先不明で返送', '転居先不明で返送', '受取拒否で返送'];
const NOTES_STOP = ['ご家族から送付停止の依頼', '電話で停止の依頼'];

const weighted = (() => {
    const list = [];
    AREAS.forEach((a, i) => { for (let k = 0; k < a.w; k++) list.push(i); });
    return () => AREAS[pick(list)];
})();

function buildPersons(n) {
    const rows = [];
    for (let id = 1; id <= n; id++) {
        const area = weighted();
        const townIdx = Math.floor(rand() * TOWNS.length);
        const [sur, surKana] = pick(SURNAMES);
        const [given, givenKana] = pick(GIVEN);
        const seijinYear = 2026 + Math.floor(rand() * 5); // 2026〜2030
        // 出所：福井の令和11年（2029）成人は R11 成人名簿にも載っている人が多い
        const sources = new Set([pick(SOURCES)]);
        if (rand() < 0.25) sources.add(pick(SOURCES));
        if (area.pref === '福井県' && seijinYear === 2029 && rand() < 0.7) sources.add(R11_SOURCE_CODE);
        const r = rand();
        const status = r < 0.86 ? '送付可' : r < 0.94 ? '未達' : '停止';
        const hasTel = rand() < 0.55;
        rows.push({
            id,
            code: `M${pad(id, 6)}`,
            pref: area.pref,
            city: area.city,
            seijin_year: seijinYear,
            name: `${sur}　${given}`,
            kana: rand() < 0.92 ? `${surKana}　${givenKana}` : '',
            zip: `${area.zip}-${pad(townIdx * 11 + 1, 4)}`,
            address: `${area.city}${TOWNS[townIdx]}${1 + Math.floor(rand() * 5)}丁目${1 + Math.floor(rand() * 30)}-${1 + Math.floor(rand() * 20)}`,
            tel: hasTel ? `${area.tel}-00-${pad(Math.floor(rand() * 10000), 4)}` : '',
            mobile: !hasTel && rand() < 0.6 ? `090-0000-${pad(Math.floor(rand() * 10000), 4)}` : '',
            guardian_name: rand() < 0.8 ? `${sur}　${pick(GUARDIAN_GIVEN)}` : '',
            dm_status: status,
            dm_note: status === '未達' ? pick(NOTES_MISS) : status === '停止' ? pick(NOTES_STOP) : '',
            source_versions: [...sources].join('/'),
            merge_count: sources.size,
            lat: area.lat + ((townIdx % 4) - 1.5) * 0.012,
            lng: area.lng + (Math.floor(townIdx / 4) - 0.5) * 0.016,
        });
    }
    return rows;
}

const persons = buildPersons(1200);
const byId = new Map(persons.map((p) => [p.id, p]));

// ---- 変更履歴（このタブの間だけ） ----
const logs = new Map();
let logSeq = 0;
persons
    .filter((p) => p.dm_status !== '送付可')
    .forEach((p) => {
        logs.set(p.id, [{
            id: ++logSeq,
            changed_at: '2026-02-10T10:15:00',
            old_status: '送付可',
            new_status: p.dm_status,
            note: p.dm_note,
            changed_by: '（ダミー）',
        }]);
    });

// ---- ラベルのテンプレート（福井発送／岡山発送） ----
let tplSeq = 2;
let templates = [
    { id: 1, name: '福井発送', ...DEFAULT_LABEL_FORM },
    { id: 2, name: '岡山発送', ...DEFAULT_LABEL_FORM, sender_zip: '', sender_address: '' },
];

const TEMPLATE_KEYS = Object.keys(DEFAULT_LABEL_FORM);
const pickTemplate = (t) => Object.fromEntries(TEMPLATE_KEYS.filter((k) => k in t).map((k) => [k, k === 'with_family' ? !!t[k] : String(t[k] ?? '')]));
const templateNameTaken = (name) => Object.assign(new Error(`「${name}」という名前のテンプレートがすでにあります`), { code: 'name_taken' });

const wait = (v) => new Promise((resolve) => setTimeout(() => resolve(structuredCloneSafe(v)), 120));
const structuredCloneSafe = (v) => (v === undefined ? v : JSON.parse(JSON.stringify(v)));
const toListItem = ({ lat, lng, ...rest }) => rest;

/** 端末の時刻（日本時間）のまま "YYYY-MM-DDTHH:mm:ss" にする（toISOString は UTC になるため使わない） */
const localIso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 19);

function filterPersons(f = {}) {
    const q = String(f.q || '').trim();
    return persons.filter((p) => {
        if (f.pref && p.pref !== f.pref) return false;
        if (f.seijin_year && String(p.seijin_year) !== String(f.seijin_year)) return false;
        if (f.status && p.dm_status !== f.status) return false;
        if (f.source === 'r11' && !hasR11(p.source_versions)) return false;
        if (f.source === 'other' && hasR11(p.source_versions)) return false;
        if (q) {
            const hay = [p.name, p.kana, p.guardian_name, p.address, p.zip, p.tel, p.mobile].join(' ');
            // 全角・半角スペースの違いは無視する
            const norm = (s) => s.replace(/[\s　]/g, '');
            if (!norm(hay).includes(norm(q))) return false;
        }
        return true;
    });
}

export const dummyAdapter = {
    isDummy: true,

    options() {
        return wait({
            prefs: [...new Set(persons.map((p) => p.pref))].sort(),
            seijin_years: [...new Set(persons.map((p) => p.seijin_year))].sort(),
            statuses: DM_STATUSES,
        });
    },

    persons({ page = 1, page_size = 50, ...f } = {}) {
        const hit = filterPersons(f);
        const start = (page - 1) * page_size;
        return wait({ items: hit.slice(start, start + page_size).map(toListItem), total: hit.length });
    },

    personIds(f = {}) {
        return wait({ ids: filterPersons(f).map((p) => p.id) });
    },

    /** 選んだ人の件数と、うち R11 成人名簿の人の数 */
    summary(ids = []) {
        const rows = ids.map((id) => byId.get(id)).filter(Boolean);
        return wait({ count: rows.length, r11: rows.filter((p) => hasR11(p.source_versions)).length });
    },

    /** ラベルの見本用。ids の先頭から limit 件 */
    personsByIds(ids = [], limit = 10) {
        return wait(ids.slice(0, limit).map((id) => byId.get(id)).filter(Boolean).map(toListItem));
    },

    person(id) {
        const p = byId.get(Number(id));
        if (!p) return Promise.reject(new Error('見つかりません'));
        return wait({ ...toListItem(p), logs: [...(logs.get(p.id) || [])].reverse() });
    },

    updateDM(id, dmStatus, dmNote) {
        const p = byId.get(Number(id));
        if (!p) return Promise.reject(new Error('見つかりません'));
        if (p.dm_status !== dmStatus || (p.dm_note || '') !== (dmNote || '')) {
            const list = logs.get(p.id) || [];
            list.push({
                id: ++logSeq,
                changed_at: localIso(new Date()),
                old_status: p.dm_status,
                new_status: dmStatus,
                note: dmNote || '',
                changed_by: '（ダミー）',
            });
            logs.set(p.id, list);
        }
        p.dm_status = dmStatus;
        p.dm_note = dmNote || '';
        return wait(toListItem(p));
    },

    templates() {
        return wait([...templates].sort((a, b) => a.id - b.id));
    },

    /**
     * テンプレートを追加する。同じ名前があれば断る（画面で上書きの確認をしてから updateTemplate を呼ぶ）。
     * 本物の API では 409 を返す想定。
     */
    createTemplate(t) {
        const name = String(t.name || '').trim();
        if (!name) return Promise.reject(new Error('テンプレート名を入れてください'));
        if (templates.some((x) => x.name === name)) return Promise.reject(templateNameTaken(name));
        const row = { ...DEFAULT_LABEL_FORM, ...pickTemplate(t), name, id: ++tplSeq };
        templates.push(row);
        return wait(row);
    },

    /** テンプレートを書き換える（名前を変えるときも、ほかのテンプレートと同じ名前にはできない） */
    updateTemplate(id, t) {
        const cur = templates.find((x) => x.id === Number(id));
        if (!cur) return Promise.reject(new Error('テンプレートが見つかりません'));
        const name = String(t.name ?? cur.name).trim();
        if (!name) return Promise.reject(new Error('テンプレート名を入れてください'));
        if (templates.some((x) => x.name === name && x.id !== cur.id)) return Promise.reject(templateNameTaken(name));
        Object.assign(cur, pickTemplate(t), { name, id: cur.id });
        return wait(cur);
    },

    deleteTemplate(id) {
        templates = templates.filter((t) => t.id !== Number(id));
        return wait({ ok: true });
    },

    /** ダミーでは PDF を作らない（件数だけ返す） */
    labels({ ids = [] } = {}) {
        return wait({ count: ids.length, blob: null });
    },

    stats() {
        const byStatus = Object.fromEntries(DM_STATUSES.map((s) => [s, 0]));
        const pivot = new Map();
        let r11 = 0;
        persons.forEach((p) => {
            byStatus[p.dm_status] += 1;
            const key = `${p.pref}|${p.seijin_year}`;
            pivot.set(key, (pivot.get(key) || 0) + 1);
            if (hasR11(p.source_versions)) r11 += 1;
        });
        const byYear = [...pivot.entries()].map(([k, c]) => {
            const [pref, y] = k.split('|');
            return { pref, seijin_year: Number(y), c };
        });
        return wait({ total: persons.length, by_status: byStatus, by_year: byYear, r11 });
    },

    map(f = {}) {
        const hit = filterPersons(f);
        const spots = new Map();
        hit.forEach((p) => {
            const s = spots.get(p.zip) || { zip: p.zip, pref: p.pref, lat: p.lat, lng: p.lng, count: 0, area: p.pref + p.city };
            s.count += 1;
            spots.set(p.zip, s);
        });
        const points = [...spots.values()].sort((a, b) => b.count - a.count);
        return wait({ points, plotted: hit.length, spots: points.length, unmatched: 0 });
    },
};
