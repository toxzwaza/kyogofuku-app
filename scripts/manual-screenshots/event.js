/**
 * イベント・予約マニュアル用スクリーンショット撮影。
 */
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_DIR = path.resolve(__dirname, '../../public/images/manual/event');
const USER_ID = 1;
const SHOP_ID = 1;
const SAMPLE_RESERVATION_ID = 1168;  // サンプル結奈 のイベント予約
const SAMPLE_EVENT_ID = 30;          // サンプル予約が紐づくイベント

(async () => {
    if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'ja-JP',
        deviceScaleFactor: 2,
    });
    const page = await context.newPage();
    const log = (m) => console.log(`[event] ${m}`);

    // ログイン
    log(`ログイン (shop_id=${SHOP_ID}, user_id=${USER_ID})`);
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.selectOption('#shop_id', String(SHOP_ID));
    await page.waitForTimeout(500);
    await page.waitForSelector(`#user_id option[value="${USER_ID}"]`, { state: 'attached' });
    await page.selectOption('#user_id', String(USER_ID));
    await Promise.all([
        page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 15000 }),
        page.evaluate(() => document.querySelector('form').requestSubmit()),
    ]);

    // 01. イベント一覧
    log('01_event_index.png');
    await page.goto(`${BASE_URL}/admin/events`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT_DIR, '01_event_index.png'), fullPage: false });

    // 02. イベント新規作成
    log('02_event_create.png');
    await page.goto(`${BASE_URL}/admin/events/create`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT_DIR, '02_event_create.png'), fullPage: false });

    // 03. イベント詳細
    log('03_event_show.png');
    await page.goto(`${BASE_URL}/admin/events/${SAMPLE_EVENT_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT_DIR, '03_event_show.png'), fullPage: false });

    // 04. 予約者一覧（イベント詳細内）
    log('04_event_reservations.png');
    // 予約者セクションまでスクロール
    await page.evaluate(() => {
        const els = document.querySelectorAll('h2, h3, [class*="font-serif"], [class*="font-semibold"]');
        for (const el of els) {
            if (el.textContent && (el.textContent.includes('予約者') || el.textContent.includes('予約一覧'))) {
                el.scrollIntoView({ block: 'start' });
                window.scrollBy(0, -80);
                return;
            }
        }
    });
    await page.waitForTimeout(800);
    await page.screenshot({ path: path.join(OUT_DIR, '04_event_reservations.png'), fullPage: false });

    // 05. 予約詳細
    log('05_reservation_show.png');
    await page.goto(`${BASE_URL}/admin/reservations/${SAMPLE_RESERVATION_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT_DIR, '05_reservation_show.png'), fullPage: false });

    // 06. 予約者出力
    log('06_reservations_export.png');
    await page.goto(`${BASE_URL}/admin/events/reservations-export`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT_DIR, '06_reservations_export.png'), fullPage: false });

    // ============ 予約一覧画面（章 8〜16） ============
    const RSV_INDEX_URL = `${BASE_URL}/admin/events/${SAMPLE_EVENT_ID}/reservations`;

    // 07. 予約一覧スケジュール表示
    log('07_reservation_index_schedule.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    // スケジュールタブをクリック（usesTimeslotReservation の場合に表示される）
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => (b.textContent || '').includes('スケジュール')
        );
        if (btn) btn.click();
    });
    await page.waitForTimeout(1000);
    await page.screenshot({ path: path.join(OUT_DIR, '07_reservation_index_schedule.png'), fullPage: false });

    // 08. テーブル表示
    log('08_reservation_index_table.png');
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => (b.textContent || '').includes('テーブル表示')
        );
        if (btn) btn.click();
    });
    await page.waitForTimeout(1200);
    await page.screenshot({ path: path.join(OUT_DIR, '08_reservation_index_table.png'), fullPage: false });

    // 09. 絞り込みUI
    log('09_reservation_filter.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    // 絞り込みエリアまでスクロール
    await page.evaluate(() => {
        const els = document.querySelectorAll('h2, h3, [class*="font-serif"], [class*="font-semibold"]');
        for (const el of els) {
            if (el.textContent && (el.textContent.includes('絞り込み') || el.textContent.includes('フィルタ'))) {
                el.scrollIntoView({ block: 'start' });
                window.scrollBy(0, -80);
                return;
            }
        }
    });
    await page.waitForTimeout(800);
    await page.screenshot({ path: path.join(OUT_DIR, '09_reservation_filter.png'), fullPage: false });

    // 10. 容量増減ボタン
    log('10_capacity_adjust.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    // 「+1」ボタンが見える位置までスクロール
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        const plus = btns.find((b) => b.title && b.title.includes('1つ増やす'));
        if (plus) plus.scrollIntoView({ block: 'center' });
    });
    await page.waitForTimeout(700);
    await page.screenshot({ path: path.join(OUT_DIR, '10_capacity_adjust.png'), fullPage: false });

    // 11. 顧客から予約登録モーダル
    log('11_customer_reserve_modal.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2500);
    // 「顧客情報から予約登録」ボタンに ID を付与してから page.click でクリック
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        const btn = btns.find((b) => (b.textContent || '').includes('顧客情報から予約登録'))
                || btns.find((b) => (b.textContent || '').includes('顧客から予約登録'));
        if (btn) { btn.scrollIntoView({ block: 'center' }); btn.id = '__manual_customer_reserve_btn__'; }
    });
    await page.waitForTimeout(700);
    await page.click('#__manual_customer_reserve_btn__').catch(() => {});
    await page.waitForTimeout(2000);
    await page.screenshot({ path: path.join(OUT_DIR, '11_customer_reserve_modal.png'), fullPage: false });
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // 12. ステータス変更UI
    log('12_status_change.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    // ステータス表示までスクロール
    await page.evaluate(() => {
        const els = document.querySelectorAll('span, button, label');
        for (const el of els) {
            const txt = el.textContent || '';
            if (txt.includes('対応ステータス')) {
                el.scrollIntoView({ block: 'center' });
                return;
            }
        }
    });
    await page.waitForTimeout(800);
    await page.screenshot({ path: path.join(OUT_DIR, '12_status_change.png'), fullPage: false });

    // 13. キャンセル確認モーダル
    log('13_cancel_modal.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    // テーブル表示に切り替えてからキャンセルボタンを探すのが楽
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => (b.textContent || '').includes('テーブル表示')
        );
        if (btn) btn.click();
    });
    await page.waitForTimeout(1200);
    const cancelClicked = await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => (b.textContent || '').trim() === 'キャンセル' && !b.disabled
        );
        if (btn) { btn.scrollIntoView({ block: 'center' }); btn.click(); return true; }
        return false;
    });
    if (cancelClicked) await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT_DIR, '13_cancel_modal.png'), fullPage: false });
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // 14. 印刷設定モーダル
    log('14_print_modal.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    // テーブル表示
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => (b.textContent || '').includes('テーブル表示')
        );
        if (btn) btn.click();
    });
    await page.waitForTimeout(1200);
    const printClicked = await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => (b.textContent || '').trim() === '印刷'
        );
        if (btn) { btn.scrollIntoView({ block: 'center' }); btn.click(); return true; }
        return false;
    });
    if (printClicked) await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT_DIR, '14_print_modal.png'), fullPage: false });

    await browser.close();
    log('全 14 枚のスクリーンショットを ' + OUT_DIR + ' に保存しました');
})().catch((err) => {
    console.error('[event] エラー発生:', err);
    process.exit(1);
});
