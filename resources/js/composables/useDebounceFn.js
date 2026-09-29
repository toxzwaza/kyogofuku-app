/**
 * 関数呼び出しを指定ミリ秒だけ間引く（デバウンス）。
 * ライブ検索で入力のたびにリクエストが飛ぶのを防ぐために使う。
 *
 * 使い方:
 *   const debouncedSearch = useDebounceFn(() => doSearch(), 400);
 *   watch(form, debouncedSearch, { deep: true });
 */
export function useDebounceFn(fn, delay = 400) {
    let timer = null;
    const debounced = (...args) => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            timer = null;
            fn(...args);
        }, delay);
    };
    debounced.cancel = () => {
        if (timer) clearTimeout(timer);
        timer = null;
    };
    return debounced;
}
