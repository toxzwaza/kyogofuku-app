/**
 * 共有ノート（旧称 事案記録）の共通定義。
 * 種類ごとに、質問の見出しと手順（ステップ）を変える。保存する項目（what・action・consult）は同じ。
 * サーバ側の決まりは app/Models/Incident.php（ACTION_OPTIONAL_KINDS・ACTION_SKIPPED_KINDS）と合わせる。
 */

export const OTHER = 'その他';

/** 種類 → バッジの色 */
export const KIND_VARIANT = {
    'クレーム': 'danger',
    '問い合わせ': 'primary',
    '連絡': 'neutral',
    '社内のミス・トラブル': 'warning',
    'ヒヤリ・ハット': 'accent',
    '気づき・提案': 'success',
};

/** 区分 → バッジの色 */
export const CATEGORY_VARIANT = {
    '振袖': 'accent',
    '袴': 'primary',
    '一般呉服': 'success',
    '前撮り': 'warning',
};

/** 一覧のタブ */
export const TABS = [
    { id: 'all', label: 'すべて' },
    { id: 'cust', label: 'お客様対応' },
    { id: 'miss', label: 'ミス・ヒヤリ' },
    { id: 'idea', label: '気づき・提案' },
];

/**
 * 種類ごとの質問。
 *  keys   : ステップの並び（base=基本情報, title=件名, confirm=確認）
 *  what / action / consult : nm=見出し, q=質問文, sub=補足, ph=例, opt=任意
 *  action が無い種類は「その場でした対応」を聞かない
 */
export function flowFor(kind) {
    if (kind === '気づき・提案') {
        return {
            keys: ['base', 'title', 'what', 'consult', 'confirm'],
            what: {
                nm: '気づいたこと',
                q: '気づいたこと・提案を書いてください',
                sub: '「こうしたら良さそう」「ここが分かりにくい」など、一言でも大丈夫です。',
                ph: '例：前撮りの小物を前日にそろえるチェック表があると、入れ忘れが減りそう。',
            },
            action: null,
            consult: {
                nm: 'どうしたら良いと思う？',
                q: 'どうしたら良いと思う？',
                sub: 'アイデアがあれば書いてください。なければ空のまま次へ（任意）。',
                ph: '例：小物セットの袋にチェック表を貼っておく。',
            },
        };
    }
    if (kind === 'ヒヤリ・ハット') {
        return {
            keys: ['base', 'title', 'what', 'action', 'consult', 'confirm'],
            what: {
                nm: 'ヒヤッとしたこと',
                q: 'ヒヤッとしたことを書いてください',
                sub: '事故やミスにはならなかったけれど、危なかったことを書いてください。',
                ph: '例：着付けの後、階段でお客様が振袖の裾を踏みそうになった。',
            },
            action: {
                nm: 'その場でしたこと',
                q: 'その場でしたことを書いてください',
                sub: '特に何もしていなければ空のまま次へ（任意）。',
                ph: '例：手を取って一緒に降りた。',
                opt: true,
            },
            consult: {
                nm: '防ぐためのアイデア',
                q: '防ぐためのアイデア',
                sub: '次から防ぐ方法を思いついたら書いてください（任意）。',
                ph: '例：階段では裾を持つよう、着付け後に一声かける。',
            },
        };
    }
    return {
        keys: ['base', 'title', 'what', 'action', 'consult', 'confirm'],
        what: {
            nm: '何があったか',
            q: '何があったかを書いてください',
            sub: 'いつ・誰から・何を言われたか／何が起きたか。きれいな文章でなくて大丈夫です。',
            ph: '例：前撮りで、契約時に選んだ重ね衿が小物セットに入っていなかった。お母様から「確認してくれていないのか」と言われた。',
        },
        action: {
            nm: 'その場でした対応',
            q: 'その場でした対応を書いてください',
            sub: 'まだ何もしていなければ「未対応」を押してください。',
            ph: '例：浜店から同じ色の重ね衿を取り寄せ、30分遅れで撮影した。',
            opt: false,
        },
        consult: {
            nm: '困っていること',
            q: '困っていること・相談したいこと',
            sub: 'なければ空のまま次へ進んでください（任意）。',
            ph: '例：前日の小物チェックを誰がするか決まっていない。',
        },
    };
}

export function stepLabel(kind, key) {
    return { base: '基本情報', title: '件名', confirm: '確認' }[key] || flowFor(kind)[key]?.nm || '';
}

/** 「その他」を選んで具体的に書いたら、その文字を値にする */
export function finalValue(selected, other) {
    const o = String(other || '').trim();
    return selected === OTHER && o ? o : selected;
}

/** 候補にない値を「その他」＋入力欄の値に分ける */
export function splitOther(value, items) {
    if (!value) return ['', ''];
    return items.includes(value) ? [value, ''] : [OTHER, value];
}

/** 2026-11-10 → 11/10（月） */
export function dateLabel(iso) {
    const m = String(iso || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return String(iso || '');
    const w = '日月火水木金土'[new Date(+m[1], +m[2] - 1, +m[3]).getDay()];
    return `${+m[2]}/${+m[3]}（${w}）`;
}

/** 2026-11-10 18:20 → 11/10 18:20 */
export function dateTimeLabel(s) {
    const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/);
    return m ? `${+m[2]}/${+m[3]} ${m[4]}` : String(s || '');
}

export const isNotYet = (kind, action) => {
    const f = flowFor(kind);
    return !!f.action && !f.action.opt && /^未対応/.test(String(action || '').trim());
};
