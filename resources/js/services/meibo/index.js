/**
 * 名簿のデータの取り方を 1 か所で切り替える。
 * 画面は useMeiboApi() だけを使い、ダミーか本物かを知らない。
 * どちらを使うかはサーバー（config('meibo.data_source')＝env MEIBO_DATA_SOURCE）が Inertia の props.dataSource で渡す。
 *  - 'dummy'：画面の中の架空データ（dummyAdapter.js）
 *  - 'db'：この kyogofuku-app の DB（dbAdapter.js → MeiboController）
 */
import { usePage } from '@inertiajs/vue3';
import { dummyAdapter } from './dummyAdapter.js';
import { dbAdapter } from './dbAdapter.js';

export function createMeiboApi(source, preloadedOptions = null) {
    if (source !== 'db') return dummyAdapter;
    // 絞り込みの選択肢は画面を開いたときの props で受け取っているので、もう一度は読まない
    return preloadedOptions ? { ...dbAdapter, options: () => Promise.resolve(preloadedOptions) } : dbAdapter;
}

export function useMeiboApi() {
    const page = usePage();
    return createMeiboApi(page.props.dataSource, page.props.meiboOptions);
}

export * from './constants.js';
