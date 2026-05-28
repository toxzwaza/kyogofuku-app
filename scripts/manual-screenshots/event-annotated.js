/**
 * イベント・予約マニュアル用「注釈付き」スクリーンショット。
 */
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const { annotateElement, clearAnnotations } = require('./_annotate');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_DIR = path.resolve(__dirname, '../../public/images/manual/event');
const USER_ID = 1;
const SHOP_ID = 1;
const SAMPLE_RESERVATION_ID = 1168;
const SAMPLE_EVENT_ID = 30;

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'ja-JP',
        deviceScaleFactor: 2,
    });
    const page = await context.newPage();
    const log = (m) => console.log(`[event-annotated] ${m}`);

    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.selectOption('#shop_id', String(SHOP_ID));
    await page.waitForTimeout(500);
    await page.waitForSelector(`#user_id option[value="${USER_ID}"]`, { state: 'attached' });
    await page.selectOption('#user_id', String(USER_ID));
    await Promise.all([
        page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 15000 }),
        page.evaluate(() => document.querySelector('form').requestSubmit()),
    ]);

    // 01
    log('01_event_index_annotated.png');
    await page.goto(`${BASE_URL}/admin/events`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('a, button')).find(
            (e) => (e.textContent || '').includes('新規追加')
        );
        if (btn) btn.id = '__manual_new_event__';
    });
    await annotateElement(page, '#__manual_new_event__', {
        label: '① 新規イベントを作成',
        color: 'red',
        labelPosition: 'bottom',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '01_event_index_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // 02
    log('02_event_create_annotated.png');
    await page.goto(`${BASE_URL}/admin/events/create`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        const labels = Array.from(document.querySelectorAll('label'));
        const titleLabel = labels.find((l) => l.textContent && /タイトル|イベント名/.test(l.textContent));
        if (titleLabel) {
            const wrap = titleLabel.parentElement;
            const inp = wrap && wrap.querySelector('input');
            if (inp) inp.id = '__manual_event_title__';
        }
    });
    await annotateElement(page, '#__manual_event_title__', {
        label: '① 必須：イベント名',
        color: 'red',
        labelPosition: 'right',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '02_event_create_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // 03
    log('03_event_show_annotated.png');
    await page.goto(`${BASE_URL}/admin/events/${SAMPLE_EVENT_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button, a')).find(
            (e) => /公開URL|コピー/.test(e.textContent || '')
        );
        if (btn) btn.id = '__manual_copy_url__';
    });
    await annotateElement(page, '#__manual_copy_url__', {
        label: '① 公開ページのURLをコピー',
        color: 'blue',
        labelPosition: 'bottom',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '03_event_show_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // 04 - 予約者セクション
    log('04_event_reservations_annotated.png');
    await page.evaluate(() => {
        const els = document.querySelectorAll('h2, h3, [class*="font-serif"]');
        for (const el of els) {
            if (el.textContent && (el.textContent.includes('予約者') || el.textContent.includes('予約一覧'))) {
                el.scrollIntoView({ block: 'start' });
                window.scrollBy(0, -80);
                el.id = '__manual_rsv_section__';
                return;
            }
        }
    });
    await page.waitForTimeout(800);
    await annotateElement(page, '#__manual_rsv_section__', {
        label: '① 行をクリックで詳細',
        color: 'blue',
        labelPosition: 'right',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '04_event_reservations_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // 05 - 予約詳細
    log('05_reservation_show_annotated.png');
    await page.goto(`${BASE_URL}/admin/reservations/${SAMPLE_RESERVATION_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button, a')).find(
            (e) => (e.textContent || '').trim() === '編集'
        );
        if (btn) btn.id = '__manual_rsv_edit__';
    });
    await annotateElement(page, '#__manual_rsv_edit__', {
        label: '① 編集はこちらから',
        color: 'red',
        labelPosition: 'bottom',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '05_reservation_show_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // 06 - CSV出力
    log('06_reservations_export_annotated.png');
    await page.goto(`${BASE_URL}/admin/events/reservations-export`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button, a')).find(
            (e) => /CSV|ダウンロード|出力/.test(e.textContent || '')
        );
        if (btn) btn.id = '__manual_csv_btn__';
    });
    await annotateElement(page, '#__manual_csv_btn__', {
        label: '① CSVをダウンロード',
        color: 'green',
        labelPosition: 'top',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '06_reservations_export_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // ============ 予約一覧画面（章 8〜16）注釈付き ============
    const RSV_INDEX_URL = `${BASE_URL}/admin/events/${SAMPLE_EVENT_ID}/reservations`;

    // 07. スケジュール表示
    log('07_reservation_index_schedule_annotated.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    await page.evaluate(() => {
        const tabs = Array.from(document.querySelectorAll('button')).filter(
            (b) => /スケジュール|カード|テーブル/.test((b.textContent || '').trim())
        );
        if (tabs[0]) tabs[0].click();
        if (tabs.length >= 2) {
            // 親に id 付与
            const parent = tabs[0].parentElement;
            if (parent) parent.id = '__manual_view_tabs__';
        }
    });
    await page.waitForTimeout(1200);
    await annotateElement(page, '#__manual_view_tabs__', {
        label: '① 表示モードを選ぶ',
        color: 'blue',
        labelPosition: 'bottom',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '07_reservation_index_schedule_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // 08. テーブル表示
    log('08_reservation_index_table_annotated.png');
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => (b.textContent || '').includes('テーブル表示')
        );
        if (btn) btn.click();
    });
    await page.waitForTimeout(1200);
    await page.screenshot({ path: path.join(OUT_DIR, '08_reservation_index_table_annotated.png'), fullPage: false });

    // 09. 絞り込みUI
    log('09_reservation_filter_annotated.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
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
    // 「適用」ボタンを強調
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => /^適用|絞り込む|検索/.test((b.textContent || '').trim())
        );
        if (btn) btn.id = '__manual_apply_filter__';
    });
    await annotateElement(page, '#__manual_apply_filter__', {
        label: '② 絞り込みを適用',
        color: 'red',
        labelPosition: 'top',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '09_reservation_filter_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // 10. 容量増減
    log('10_capacity_adjust_annotated.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        const plus = btns.find((b) => b.title && b.title.includes('1つ増やす'));
        if (plus) { plus.scrollIntoView({ block: 'center' }); plus.id = '__manual_capacity_plus__'; }
        const minus = btns.find((b) => b.title && b.title.includes('1つ減らす'));
        if (minus) minus.id = '__manual_capacity_minus__';
    });
    await page.waitForTimeout(700);
    await annotateElement(page, '#__manual_capacity_minus__', {
        label: '① 1つ減らす',
        color: 'yellow',
        labelPosition: 'top',
    });
    await annotateElement(page, '#__manual_capacity_plus__', {
        label: '② 1つ増やす',
        color: 'green',
        labelPosition: 'top',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '10_capacity_adjust_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // 11. 顧客から予約登録モーダル
    log('11_customer_reserve_modal_annotated.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2500);
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        const btn = btns.find((b) => (b.textContent || '').includes('顧客情報から予約登録'))
                || btns.find((b) => (b.textContent || '').includes('顧客から予約登録'));
        if (btn) { btn.scrollIntoView({ block: 'center' }); btn.id = '__manual_customer_reserve_btn__'; }
    });
    await page.waitForTimeout(700);
    await page.click('#__manual_customer_reserve_btn__').catch(() => {});
    await page.waitForTimeout(2000);
    // 登録ボタンを強調
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => /^登録する|予約を登録|追加/.test((b.textContent || '').trim())
        );
        if (btn) btn.id = '__manual_rsv_register__';
    });
    await annotateElement(page, '#__manual_rsv_register__', {
        label: '③ 登録ボタンで保存',
        color: 'green',
        labelPosition: 'top',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '11_customer_reserve_modal_annotated.png'), fullPage: false });
    await clearAnnotations(page);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // 12. ステータス変更
    log('12_status_change_annotated.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    await page.evaluate(() => {
        const els = document.querySelectorAll('span, label');
        for (const el of els) {
            const txt = el.textContent || '';
            if (txt.includes('対応ステータス')) {
                el.scrollIntoView({ block: 'center' });
                // 近くの select / button を見つけて ID 付与
                const wrap = el.parentElement;
                const sel = wrap && (wrap.querySelector('select') || wrap.querySelector('button'));
                if (sel) sel.id = '__manual_status_select__';
                return;
            }
        }
    });
    await page.waitForTimeout(700);
    await annotateElement(page, '#__manual_status_select__', {
        label: '① ステータスを選ぶ',
        color: 'blue',
        labelPosition: 'right',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '12_status_change_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // 13. キャンセル確認モーダル
    log('13_cancel_modal_annotated.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
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
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => /キャンセルする|実行/.test((b.textContent || '').trim()) && !b.disabled
        );
        if (btn) btn.id = '__manual_cancel_confirm__';
    });
    await annotateElement(page, '#__manual_cancel_confirm__', {
        label: '② 確認の上、実行',
        color: 'yellow',
        labelPosition: 'top',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '13_cancel_modal_annotated.png'), fullPage: false });
    await clearAnnotations(page);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // 14. 印刷設定モーダル
    log('14_print_modal_annotated.png');
    await page.goto(RSV_INDEX_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => (b.textContent || '').includes('テーブル表示')
        );
        if (btn) btn.click();
    });
    await page.waitForTimeout(1200);
    const printOpened = await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => (b.textContent || '').trim() === '印刷'
        );
        if (btn) { btn.scrollIntoView({ block: 'center' }); btn.click(); return true; }
        return false;
    });
    if (printOpened) await page.waitForTimeout(1500);
    // モーダル内の「印刷」ボタンを強調
    await page.evaluate(() => {
        // モーダル内の最後の「印刷」ボタンを取る（モーダル開いた時、外と中で2つある）
        const btns = Array.from(document.querySelectorAll('button')).filter(
            (b) => (b.textContent || '').trim() === '印刷'
        );
        const target = btns[btns.length - 1];
        if (target) target.id = '__manual_print_confirm__';
    });
    await annotateElement(page, '#__manual_print_confirm__', {
        label: '③ 印刷プレビューへ進む',
        color: 'green',
        labelPosition: 'top',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '14_print_modal_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    await browser.close();
    log(`全 14 枚（注釈付き）保存先: ${OUT_DIR}`);
})().catch((err) => {
    console.error('[event-annotated] エラー発生:', err);
    process.exit(1);
});
