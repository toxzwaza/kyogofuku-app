/**
 * 簡易マニュアル 2026-05-29 用スクリーンショット撮影。
 * 通常版（注釈なし）と注釈版を同時に生成する。
 *
 * 通常版: NN_xxx.png
 * 注釈版: NN_xxx_annotated.png
 *
 * 章対応:
 *  01: イベント一覧                (Admin/Event/Index)
 *  02: イベント詳細                (Admin/Event/Show)
 *  03: LP公開ページを表示          (公開LP)
 *  04: 予約一覧 - 日付表示         (Admin/Reservation/Index)
 *  05: 予約一覧 - テーブル表示
 *  06: 顧客から予約登録モーダル
 *  07: 予約登録モーダル（電話/来店）
 *  08: 枠の増減ボタン
 *  09: 予約詳細 - 対応・管理タブ
 *  10: 予約詳細 - LINE/メール
 *  11: 顧客一覧                    (Admin/Customer/Index)
 *  12: 顧客追加モーダル
 *  13: 顧客詳細 - 概要タブ
 *  14: 顧客詳細 - 詳細情報タブ
 *  15: 顧客詳細 - 前撮り情報追加モーダル
 *  16: 顧客詳細 - 連絡・メモタブ
 */
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const { annotateElement, clearAnnotations } = require('./_annotate');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_DIR = path.resolve(__dirname, '../../public/images/manual/simple-20260529');
const USER_ID = 1;
const SHOP_ID = 1;
const SAMPLE_EVENT_ID = 30;
const SAMPLE_RESERVATION_ID = 1168;
const SAMPLE_HANAKO_ID = 1702;
const SAMPLE_AOI_ID = 1706;

async function login(page) {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.selectOption('#shop_id', String(SHOP_ID));
    await page.waitForTimeout(500);
    await page.waitForSelector(`#user_id option[value="${USER_ID}"]`, { state: 'attached' });
    await page.selectOption('#user_id', String(USER_ID));
    await Promise.all([
        page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 15000 }),
        page.evaluate(() => document.querySelector('form').requestSubmit()),
    ]);
}

async function scrollToText(page, text, headerOffset = 80) {
    await page.evaluate(({ text, headerOffset }) => {
        const els = document.querySelectorAll('h1, h2, h3, h4, [class*="font-serif"], [class*="font-semibold"], label, legend');
        for (const el of els) {
            if (el.textContent && el.textContent.includes(text)) {
                el.scrollIntoView({ block: 'start' });
                window.scrollBy(0, -headerOffset);
                return true;
            }
        }
        return false;
    }, { text, headerOffset });
    await page.waitForTimeout(500);
}

/** 通常版＋注釈版を撮影する小ヘルパー */
async function shot(page, filename, annotations = []) {
    // まず注釈なしで撮影
    await page.screenshot({ path: path.join(OUT_DIR, filename), fullPage: false });
    // 注釈を付けて再撮影
    for (const a of annotations) {
        await annotateElement(page, a.selector, a);
    }
    const annotatedName = filename.replace(/(\.[^.]+)$/, '_annotated$1');
    await page.screenshot({ path: path.join(OUT_DIR, annotatedName), fullPage: false });
    await clearAnnotations(page);
}

(async () => {
    if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'ja-JP',
        deviceScaleFactor: 2,
    });
    const page = await context.newPage();
    const log = (m) => console.log(`[simple-20260529] ${m}`);

    await login(page);
    log('ログイン完了');

    // ===== 01. イベント一覧 =====
    log('01_event_index');
    await page.goto(`${BASE_URL}/admin/events`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        // 「店舗」セレクタを取得（label テキスト「店舗」近傍の select）
        const labels = Array.from(document.querySelectorAll('label, span'));
        const sl = labels.find((l) => (l.textContent || '').trim() === '店舗' || (l.textContent || '').trim() === '担当店舗');
        if (sl) {
            const wrap = sl.parentElement;
            const sel = wrap && wrap.querySelector('select');
            if (sel) sel.id = '__manual_shop_filter__';
        }
        // 「公開状態」セレクタ
        const sl2 = labels.find((l) => (l.textContent || '').includes('公開状態'));
        if (sl2) {
            const wrap = sl2.parentElement;
            const sel = wrap && wrap.querySelector('select');
            if (sel) sel.id = '__manual_public_filter__';
        }
        // 検索ボタン
        const sb = Array.from(document.querySelectorAll('button')).find((b) => (b.textContent || '').trim() === '検索');
        if (sb) sb.id = '__manual_search_btn__';
    });
    await shot(page, '01_event_index.png', [
        { selector: '#__manual_shop_filter__', label: '① 店舗を切り替え', color: 'blue', labelPosition: 'top' },
        { selector: '#__manual_public_filter__', label: '② 過去は「受付終了」に', color: 'blue', labelPosition: 'top' },
        { selector: '#__manual_search_btn__', label: '③ 検索で適用', color: 'red', labelPosition: 'top' },
    ]);

    // ===== 02. イベント詳細 =====
    log('02_event_show');
    await page.goto(`${BASE_URL}/admin/events/${SAMPLE_EVENT_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        // 公開ページを表示ボタン
        const btn = Array.from(document.querySelectorAll('a, button')).find((b) => (b.textContent || '').includes('公開ページを表示'));
        if (btn) btn.id = '__manual_view_lp__';
        // 予約一覧ボタン
        const rsv = Array.from(document.querySelectorAll('a, button')).find((b) => (b.textContent || '').trim() === '予約一覧');
        if (rsv) rsv.id = '__manual_rsv_link__';
    });
    await shot(page, '02_event_show.png', [
        { selector: '#__manual_view_lp__', label: '① 公開ページを確認', color: 'blue', labelPosition: 'bottom' },
        { selector: '#__manual_rsv_link__', label: '② 予約一覧へ', color: 'red', labelPosition: 'bottom' },
    ]);

    // ===== 03. LP公開ページ =====
    log('03_event_lp');
    // 公開LPの slug を取得
    let lpSlug = null;
    await page.evaluate(() => {
        const a = document.querySelector('a[href*="/event/"]');
        if (a) window.__lp_href = a.getAttribute('href');
    });
    lpSlug = await page.evaluate(() => window.__lp_href);
    if (lpSlug) {
        const url = lpSlug.startsWith('http') ? lpSlug : `${BASE_URL}${lpSlug}`;
        await page.goto(url, { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
    } else {
        // フォールバック: slug を直接組み立て
        await page.goto(`${BASE_URL}/event/daisougyousai-okayama-2026`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
    }
    await shot(page, '03_event_lp.png', []);

    // ===== 予約一覧 共通 =====
    const RSV_URL = `${BASE_URL}/admin/events/${SAMPLE_EVENT_ID}/reservations`;

    // 04. 予約一覧 - 日付表示
    log('04_reservation_index_schedule');
    await page.goto(RSV_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    await page.evaluate(() => {
        // 日付/テーブル切替タブ
        const tabs = Array.from(document.querySelectorAll('button')).filter(
            (b) => /日付表示|テーブル表示/.test((b.textContent || '').trim())
        );
        if (tabs.length) {
            const parent = tabs[0].closest('div, nav');
            if (parent) parent.id = '__manual_view_tabs__';
        }
        // 絞り込みエリア（適用ボタンを起点に親）
        const apply = Array.from(document.querySelectorAll('button')).find(
            (b) => /^絞り込み|適用|検索/.test((b.textContent || '').trim())
        );
        if (apply) apply.id = '__manual_filter_apply__';
        // 左カラムのジャンプ一覧（「表示中の枠一覧」など）
        const els = document.querySelectorAll('h2, h3, [class*="font-serif"], [class*="font-semibold"]');
        for (const el of els) {
            const t = el.textContent || '';
            if (t.includes('表示中の枠一覧') || t.includes('クリックで該当箇所')) {
                const wrap = el.closest('div');
                if (wrap) { wrap.id = '__manual_jump_list__'; break; }
            }
        }
    });
    await shot(page, '04_reservation_index_schedule.png', [
        { selector: '#__manual_view_tabs__', label: '① 表示モードを切替', color: 'blue', labelPosition: 'bottom' },
        { selector: '#__manual_filter_apply__', label: '② 絞り込みを適用', color: 'red', labelPosition: 'top' },
    ]);

    // 05. 予約一覧 - テーブル表示
    log('05_reservation_index_table');
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find((b) => (b.textContent || '').includes('テーブル表示'));
        if (btn) btn.click();
    });
    await page.waitForTimeout(1200);
    await page.evaluate(() => {
        const print = Array.from(document.querySelectorAll('button')).find((b) => (b.textContent || '').trim() === '印刷');
        if (print) print.id = '__manual_print_btn__';
    });
    await shot(page, '05_reservation_index_table.png', [
        { selector: '#__manual_print_btn__', label: '① 印刷はテーブル表示で', color: 'green', labelPosition: 'right' },
    ]);

    // 06. 顧客から予約登録モーダル（A方式）
    log('06_customer_reserve_modal');
    await page.goto(RSV_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2500);
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        const btn = btns.find((b) => (b.textContent || '').includes('顧客情報から予約登録'))
                || btns.find((b) => (b.textContent || '').includes('顧客から予約登録'));
        if (btn) { btn.scrollIntoView({ block: 'center' }); btn.id = '__manual_customer_rsv_btn__'; }
    });
    await page.waitForTimeout(700);
    await page.click('#__manual_customer_rsv_btn__').catch(() => {});
    await page.waitForTimeout(2000);
    await page.evaluate(() => {
        // モーダル内の入力欄
        const input = document.querySelector('input[placeholder*="名前"], input[placeholder*="検索"]');
        if (input) input.id = '__manual_customer_search__';
        const confirm = Array.from(document.querySelectorAll('button')).find((b) => /^確定|^登録/.test((b.textContent || '').trim()));
        if (confirm) confirm.id = '__manual_customer_rsv_confirm__';
    });
    await shot(page, '06_customer_reserve_modal.png', [
        { selector: '#__manual_customer_search__', label: '① お名前で検索', color: 'blue', labelPosition: 'top' },
        { selector: '#__manual_customer_rsv_confirm__', label: '② 登録', color: 'green', labelPosition: 'top' },
    ]);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // 07. 予約登録モーダル（電話/来店からの新規）
    log('07_reservation_register_modal');
    await page.goto(RSV_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2500);
    await page.evaluate(() => {
        // 「予約登録」ボタン（顧客情報からではない方）
        const btns = Array.from(document.querySelectorAll('button'));
        const btn = btns.find((b) => {
            const t = (b.textContent || '').trim();
            return t === '予約登録' || (t.includes('予約登録') && !t.includes('顧客') && !t.includes('テキスト'));
        });
        if (btn) { btn.scrollIntoView({ block: 'center' }); btn.id = '__manual_register_btn__'; }
    });
    await page.waitForTimeout(700);
    await page.click('#__manual_register_btn__').catch(() => {});
    await page.waitForTimeout(2000);
    await shot(page, '07_reservation_register_modal.png', []);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // 08. 枠の増減
    log('08_capacity_adjust');
    await page.goto(RSV_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        const plus = btns.find((b) => b.title && b.title.includes('1つ増やす'));
        if (plus) { plus.scrollIntoView({ block: 'center' }); plus.id = '__manual_cap_plus__'; }
        const minus = btns.find((b) => b.title && b.title.includes('1つ減らす'));
        if (minus) minus.id = '__manual_cap_minus__';
        const plus5 = btns.find((b) => b.title && b.title.includes('5つ増やす'));
        if (plus5) plus5.id = '__manual_cap_plus5__';
    });
    await page.waitForTimeout(500);
    await shot(page, '08_capacity_adjust.png', [
        { selector: '#__manual_cap_minus__', label: '① 1つ減らす', color: 'yellow', labelPosition: 'top' },
        { selector: '#__manual_cap_plus__', label: '② 1つ増やす', color: 'green', labelPosition: 'top' },
        { selector: '#__manual_cap_plus5__', label: '③ 5つ増やす', color: 'green', labelPosition: 'top' },
    ]);

    // 09. 予約詳細 - 対応・管理タブ
    log('09_reservation_show_manage');
    await page.goto(`${BASE_URL}/admin/reservations/${SAMPLE_RESERVATION_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        const tab = Array.from(document.querySelectorAll('button, a')).find((b) => (b.textContent || '').includes('対応・管理'));
        if (tab) tab.click();
    });
    await page.waitForTimeout(1200);
    await page.evaluate(() => {
        const els = document.querySelectorAll('span, label');
        for (const el of els) {
            const t = el.textContent || '';
            if (t.includes('対応ステータス')) {
                const wrap = el.parentElement;
                const sel = wrap && (wrap.querySelector('select') || wrap.querySelector('button'));
                if (sel) sel.id = '__manual_status_select__';
                break;
            }
        }
    });
    await shot(page, '09_reservation_show_manage.png', [
        { selector: '#__manual_status_select__', label: '① ステータスを変更', color: 'blue', labelPosition: 'right' },
    ]);

    // 10. 予約詳細 - 連絡・履歴タブ
    log('10_reservation_show_communication');
    await page.evaluate(() => {
        const tab = Array.from(document.querySelectorAll('button, a')).find((b) => (b.textContent || '').includes('連絡・履歴'));
        if (tab) tab.click();
    });
    await page.waitForTimeout(1500);
    await shot(page, '10_reservation_show_communication.png', []);

    // 11. 顧客一覧
    log('11_customer_index');
    await page.goto(`${BASE_URL}/admin/customers`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        const add = Array.from(document.querySelectorAll('button')).find((b) => (b.textContent || '').includes('顧客追加'));
        if (add) add.id = '__manual_customer_add__';
        const exec = Array.from(document.querySelectorAll('button')).find((b) => (b.textContent || '').includes('検索を実行') || (b.textContent || '').trim() === '検索');
        if (exec) exec.id = '__manual_search_exec__';
    });
    await shot(page, '11_customer_index.png', [
        { selector: '#__manual_customer_add__', label: '① 新規顧客を登録', color: 'red', labelPosition: 'bottom' },
        { selector: '#__manual_search_exec__', label: '② 検索を実行', color: 'blue', labelPosition: 'top' },
    ]);

    // 12. 顧客追加モーダル
    log('12_customer_add_modal');
    await page.click('#__manual_customer_add__').catch(() => {});
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        const labels = Array.from(document.querySelectorAll('label'));
        const nameLabel = labels.find((l) => /顧客名|^お名前/.test(l.textContent || ''));
        if (nameLabel) {
            const wrap = nameLabel.parentElement;
            const input = wrap && wrap.querySelector('input');
            if (input) input.id = '__manual_customer_name__';
        }
    });
    await shot(page, '12_customer_add_modal.png', [
        { selector: '#__manual_customer_name__', label: '① 必須：お客様のお名前', color: 'red', labelPosition: 'right' },
    ]);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // 13. 顧客詳細 - 概要タブ
    log('13_customer_show_overview');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_HANAKO_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        // タブ列
        const ovTab = Array.from(document.querySelectorAll('button, a')).find((b) => (b.textContent || '').trim() === '概要');
        if (ovTab && ovTab.parentElement) ovTab.parentElement.id = '__manual_customer_tabs__';
    });
    await shot(page, '13_customer_show_overview.png', [
        { selector: '#__manual_customer_tabs__', label: '① タブで情報を切替', color: 'blue', labelPosition: 'bottom' },
    ]);

    // 14. 顧客詳細 - 詳細情報タブ
    log('14_customer_show_info');
    await page.evaluate(() => {
        const tab = Array.from(document.querySelectorAll('button, a')).find((b) => (b.textContent || '').trim() === '詳細情報');
        if (tab) tab.click();
    });
    await page.waitForTimeout(1200);
    await scrollToText(page, '前撮り情報');
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        const add = btns.find((b) => (b.textContent || '').includes('前撮り追加'));
        if (add) add.id = '__manual_photo_add__';
    });
    await shot(page, '14_customer_show_info.png', [
        { selector: '#__manual_photo_add__', label: '① 前撮り枠を追加', color: 'red', labelPosition: 'left' },
    ]);

    // 15. 顧客詳細 - 前撮り情報追加モーダル
    log('15_customer_photo_add_modal');
    await page.click('#__manual_photo_add__').catch(() => {});
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        // モーダル内のセレクタを順次特定
        const labels = Array.from(document.querySelectorAll('label'));
        const shopLabel = labels.find((l) => /^担当店舗/.test((l.textContent || '').trim()));
        if (shopLabel) {
            const wrap = shopLabel.parentElement;
            const sel = wrap && wrap.querySelector('select');
            if (sel) sel.id = '__manual_photo_shop__';
        }
        const studioLabel = labels.find((l) => /会場|スタジオ/.test(l.textContent || ''));
        if (studioLabel) {
            const wrap = studioLabel.parentElement;
            const sel = wrap && wrap.querySelector('select');
            if (sel) sel.id = '__manual_photo_studio__';
        }
        const dateLabel = labels.find((l) => /撮影日/.test(l.textContent || ''));
        if (dateLabel) {
            const wrap = dateLabel.parentElement;
            const sel = wrap && (wrap.querySelector('select') || wrap.querySelector('input'));
            if (sel) sel.id = '__manual_photo_date__';
        }
        const slotLabel = labels.find((l) => /撮影枠|時間|撮影時刻/.test(l.textContent || ''));
        if (slotLabel) {
            const wrap = slotLabel.parentElement;
            const sel = wrap && wrap.querySelector('select');
            if (sel) sel.id = '__manual_photo_slot__';
        }
        // 登録ボタン
        const submit = Array.from(document.querySelectorAll('button')).find((b) => /^登録|^追加|^保存/.test((b.textContent || '').trim()));
        if (submit) submit.id = '__manual_photo_submit__';
    });
    await shot(page, '15_customer_photo_add_modal.png', [
        { selector: '#__manual_photo_shop__',   label: '① 担当店舗',   color: 'blue', labelPosition: 'right' },
        { selector: '#__manual_photo_studio__', label: '② 会場',       color: 'blue', labelPosition: 'right' },
        { selector: '#__manual_photo_date__',   label: '③ 撮影日',     color: 'blue', labelPosition: 'right' },
        { selector: '#__manual_photo_slot__',   label: '④ 撮影枠',     color: 'blue', labelPosition: 'right' },
        { selector: '#__manual_photo_submit__', label: '⑤ 登録', color: 'green', labelPosition: 'top' },
    ]);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // 16. 顧客詳細 - 連絡・メモタブ
    log('16_customer_show_communication');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_AOI_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        const tab = Array.from(document.querySelectorAll('button, a')).find((b) => (b.textContent || '').includes('連絡・メモ'));
        if (tab) tab.click();
    });
    await page.waitForTimeout(1200);
    await scrollToText(page, 'メモ');
    await shot(page, '16_customer_show_communication.png', []);

    await browser.close();
    log(`完了: ${OUT_DIR}`);
})().catch((err) => {
    console.error('[simple-20260529] エラー:', err);
    process.exit(1);
});
