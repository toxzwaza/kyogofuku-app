/**
 * 顧客検索の適用中フィルタ（実際に結果へ反映されている条件）をチップ表示用に整形する共通ロジック。
 * Admin/Customer/Index と LINE広告の送信先選択（顧客検索）で共用する。
 * 各チップに tab（CustomerSearchFilterPanel のフィルタタブ key）を持たせ、
 * タブボタンの適用中件数バッジにも使う。
 */
export function buildCustomerFilterChips(filters, lists = {}) {
    const f = filters || {};
    const { shops, plans, users, ceremonyAreas, constraintTemplates } = lists;
    const chips = [];
    let currentTab = 'basic';
    const push = (chip) => chips.push({ ...chip, tab: currentTab });
    const findName = (list, id, field = 'name') =>
        (list || []).find((x) => x.id == id)?.[field] ?? `#${id}`;
    const boolLabel = (v) => {
        if (v === true || v === 'true' || v === 1 || v === '1') return 'あり';
        if (v === false || v === 'false' || v === 0 || v === '0') return 'なし';
        return null;
    };
    const text = (key, label) => {
        const v = f[key];
        if (v !== null && v !== undefined && v !== '') push({ key, label, value: String(v) });
    };
    const bool = (key, label) => {
        const v = f[key];
        if (v === null || v === undefined || v === '') return;
        const b = boolLabel(v);
        if (b) push({ key, label, value: b });
    };
    const idChip = (key, label, list, field = 'name') => {
        const v = f[key];
        if (v !== null && v !== undefined && v !== '') push({ key, label, value: findName(list, v, field) });
    };
    const toArray = (v) =>
        Array.isArray(v) ? v : (v !== null && v !== undefined && v !== '' ? [v] : []);

    // 基本情報
    currentTab = 'basic';
    text('name', '顧客名');
    text('kana', 'ふりがな');
    text('phone_number', '電話番号');
    const shopIds = toArray(f.customer_shop_id).filter((x) => x !== 'all');
    if (shopIds.length) {
        push({ key: 'customer_shop_id', label: '担当店舗', value: shopIds.map((id) => findName(shops, id)).join('、') });
    }
    const areaIds = toArray(f.ceremony_area_id);
    if (areaIds.length) {
        push({ key: 'ceremony_area_id', label: '成人式エリア', value: areaIds.map((id) => findName(ceremonyAreas, id)).join('、') });
    }
    text('created_at_from', '登録日(開始)');
    text('created_at_to', '登録日(終了)');
    text('full_body_photo_presence', '顧客写真(全身)');

    // 成人式情報
    currentTab = 'seijin';
    text('seijin_preparation_venue', '仕度会場');
    text('seijin_preparation_time', '時間');
    bool('other_store_preparation', '他店お支度');
    text('other_store_salon_name', '美容室名');
    text('kimono_ship_date', '着物発送日');

    // 成約情報
    currentTab = 'contract';
    text('contract_status', '成約ステータス');
    text('contract_date_from', '成約日(開始)');
    text('contract_date_to', '成約日(終了)');
    text('contract_amount_min', '成約金額(下限)');
    text('contract_amount_max', '成約金額(上限)');
    idChip('shop_id', '成約店舗', shops);
    idChip('plan_id', 'プラン', plans);
    text('kimono_type', '着物種別');
    bool('warranty_flag', '安心保証');
    idChip('user_id', '担当スタッフ', users);
    text('preparation_venue', 'お仕度会場');
    text('preparation_date', 'お仕度日程');

    // 制約情報
    currentTab = 'constraint';
    text('constraint_presence', '制約');
    idChip('constraint_template_id', '制約テンプレート', constraintTemplates);
    text('constraint_signed_at_from', '署名日(開始)');
    text('constraint_signed_at_to', '署名日(終了)');
    idChip('constraint_explainer_user_id', '説明担当', users);

    // 前撮り情報
    currentTab = 'photo';
    idChip('photo_slot_shop_id', '前撮り担当店舗', shops);
    bool('photo_slot_details_undecided', '前撮り詳細未決定');

    return chips;
}

/** タブごとの適用中フィルタ件数（タブボタンの赤丸バッジ用） */
export function countChipsByTab(chips) {
    const counts = {};
    for (const chip of chips) {
        counts[chip.tab] = (counts[chip.tab] || 0) + 1;
    }
    return counts;
}
