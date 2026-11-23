import { ref, computed } from 'vue';

/**
 * 名簿一覧で選んだ人（ラベル発行の対象）。
 * 一覧 → ラベル発行の画面へ移っても残るよう、モジュールで 1 つだけ持ち、sessionStorage にも控える
 * （タブを閉じると消える。控えるのは id の番号だけで、氏名・住所は持たない）。
 */
const STORAGE_KEY = 'kyogofuku-meibo-selection';

const load = () => {
    try {
        const v = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
        return Array.isArray(v) ? v.filter((n) => Number.isInteger(n)) : [];
    } catch {
        return [];
    }
};

const ids = ref(new Set(typeof window === 'undefined' ? [] : load()));

const persist = () => {
    try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify([...ids.value])); } catch { /* ignore */ }
};

const replace = (next) => {
    ids.value = new Set(next);
    persist();
};

export function useMeiboSelection() {
    return {
        ids,
        count: computed(() => ids.value.size),
        list: computed(() => [...ids.value]),
        has: (id) => ids.value.has(id),
        toggle(id) {
            const s = new Set(ids.value);
            if (s.has(id)) s.delete(id); else s.add(id);
            replace(s);
        },
        addMany(list) { replace([...ids.value, ...list]); },
        removeMany(list) {
            const drop = new Set(list);
            replace([...ids.value].filter((id) => !drop.has(id)));
        },
        set: replace,
        clear: () => replace([]),
    };
}
