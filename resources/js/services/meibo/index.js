/**
 * 名簿のデータの取り方を 1 か所で切り替える。
 * 画面は useMeiboApi() だけを使い、ダミーか本物かを知らない。
 * どちらを使うかはサーバー（MeiboController::DATA_SOURCE）が Inertia の props.dataSource で渡す。
 */
import { usePage } from '@inertiajs/vue3';
import { dummyAdapter } from './dummyAdapter.js';
import { httpAdapter } from './httpAdapter.js';

export function createMeiboApi(source) {
    return source === 'http' ? httpAdapter : dummyAdapter;
}

export function useMeiboApi() {
    const page = usePage();
    return createMeiboApi(page.props.dataSource);
}

export * from './constants.js';
