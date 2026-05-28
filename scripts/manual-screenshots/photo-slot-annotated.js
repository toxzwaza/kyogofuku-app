/**
 * 前撮りマニュアル用「注釈付き」スクリーンショット。
 */
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const { annotateElement, clearAnnotations } = require('./_annotate');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_DIR = path.resolve(__dirname, '../../public/images/manual/photo-slot');
const USER_ID = 1;
const SHOP_ID = 1;

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'ja-JP',
        deviceScaleFactor: 2,
    });
    const page = await context.newPage();
    const log = (m) => console.log(`[photo-slot-annotated] ${m}`);

    // ログイン
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.selectOption('#shop_id', String(SHOP_ID));
    await page.waitForTimeout(500);
    await page.waitForSelector(`#user_id option[value="${USER_ID}"]`, { state: 'attached' });
    await page.selectOption('#user_id', String(USER_ID));
    await Promise.all([
        page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 15000 }),
        page.evaluate(() => document.querySelector('form').requestSubmit()),
    ]);
    log(`ログイン完了`);

    // ============ 01. 前撮り一覧 ============
    log('01_photo_slot_index_annotated.png');
    await page.goto(`${BASE_URL}/admin/photo-slots`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    // 新規追加ボタン
    await page.evaluate(() => {
        const link = Array.from(document.querySelectorAll('a, button')).find(
            (e) => (e.textContent || '').includes('新規追加')
        );
        if (link) link.id = '__manual_new_slot__';
        // 絞り込み「年」セレクタは年フィルタの最初のボタン群
        const yearBtn = Array.from(document.querySelectorAll('button')).find(
            (e) => /^2026年/.test((e.textContent || '').trim())
        );
        if (yearBtn) yearBtn.id = '__manual_year_filter__';
    });
    await annotateElement(page, '#__manual_new_slot__', {
        label: '① 新しい枠の登録',
        color: 'red',
        labelPosition: 'bottom',
    });
    await annotateElement(page, '#__manual_year_filter__', {
        label: '② 年で絞り込み',
        color: 'blue',
        labelPosition: 'top',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '01_photo_slot_index_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // ============ 02. 新規追加 ============
    log('02_photo_slot_create_annotated.png');
    await page.goto(`${BASE_URL}/admin/photo-slots/create`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    // 撮影日と撮影時刻が必須
    await page.evaluate(() => {
        const labels = Array.from(document.querySelectorAll('label'));
        const dateLabel = labels.find((l) => l.textContent && l.textContent.includes('撮影日'));
        if (dateLabel) {
            const wrap = dateLabel.parentElement;
            const inp = wrap && wrap.querySelector('input, select');
            if (inp) inp.id = '__manual_shoot_date__';
        }
        const studioLabel = labels.find((l) => l.textContent && (l.textContent.includes('会場') || l.textContent.includes('スタジオ')));
        if (studioLabel) {
            const wrap = studioLabel.parentElement;
            const inp = wrap && wrap.querySelector('select, input');
            if (inp) inp.id = '__manual_studio__';
        }
    });
    await annotateElement(page, '#__manual_studio__', {
        label: '① 撮影場所を選択',
        color: 'blue',
        labelPosition: 'right',
    });
    await annotateElement(page, '#__manual_shoot_date__', {
        label: '② 日付・時刻を入力',
        color: 'blue',
        labelPosition: 'right',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '02_photo_slot_create_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // ============ 03. 編集モーダル（個別枠） ============
    log('03_photo_slot_edit_modal_annotated.png');
    await page.goto(`${BASE_URL}/admin/photo-slots`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    // 2026年→12月→2026年12月12日展開
    await page.evaluate(() => {
        const y = Array.from(document.querySelectorAll('button')).find((b) => /^2026年/.test((b.textContent || '').trim()));
        if (y) y.click();
    });
    await page.waitForTimeout(500);
    await page.evaluate(() => {
        const m = Array.from(document.querySelectorAll('button')).find((b) => /^12月/.test((b.textContent || '').trim()));
        if (m) m.click();
    });
    await page.waitForTimeout(700);
    await page.evaluate(() => {
        const buttons = document.querySelectorAll('button');
        for (const btn of buttons) {
            if (/2026年12月12日/.test(btn.textContent || '')) btn.click();
        }
    });
    await page.waitForTimeout(800);
    // 「サンプル 花子」行の編集
    await page.evaluate(() => {
        const rows = document.querySelectorAll('tr');
        for (const row of rows) {
            const txt = row.textContent || '';
            if (txt.includes('サンプル') && txt.includes('花子')) {
                const btn = row.querySelector('button[title="編集"]');
                if (btn) { btn.click(); return; }
            }
        }
    });
    await page.waitForTimeout(1500);
    // 保存ボタン強調
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        const save = btns.find((b) => /更新|保存/.test((b.textContent || '').trim()));
        if (save) save.id = '__manual_slot_save__';
    });
    await annotateElement(page, '#__manual_slot_save__', {
        label: '③ 変更後は保存',
        color: 'green',
        labelPosition: 'top',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '03_photo_slot_edit_modal_annotated.png'), fullPage: false });
    await clearAnnotations(page);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // ============ 04. 解除確認モーダル ============
    log('04_photo_slot_release_modal_annotated.png');
    await page.goto(`${BASE_URL}/admin/photo-slots`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    await page.evaluate(() => {
        const y = Array.from(document.querySelectorAll('button')).find((b) => /^2026年/.test((b.textContent || '').trim()));
        if (y) y.click();
    });
    await page.waitForTimeout(500);
    await page.evaluate(() => {
        const m = Array.from(document.querySelectorAll('button')).find((b) => /^12月/.test((b.textContent || '').trim()));
        if (m) m.click();
    });
    await page.waitForTimeout(700);
    await page.evaluate(() => {
        const buttons = document.querySelectorAll('button');
        for (const btn of buttons) {
            if (/2026年12月12日/.test(btn.textContent || '')) btn.click();
        }
    });
    await page.waitForTimeout(800);
    await page.evaluate(() => {
        const rows = document.querySelectorAll('tr');
        for (const row of rows) {
            const txt = row.textContent || '';
            if (txt.includes('サンプル') && txt.includes('花子')) {
                const btn = row.querySelector('button[title="顧客の紐付けを解除"]');
                if (btn) { btn.scrollIntoView({ block: 'center' }); btn.click(); return; }
            }
        }
    });
    await page.waitForTimeout(1500);
    // 「解除する」ボタンを強調
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => (b.textContent || '').trim() === '解除する'
        );
        if (btn) btn.id = '__manual_release_btn__';
    });
    await annotateElement(page, '#__manual_release_btn__', {
        label: '② 確認の上、解除',
        color: 'yellow',
        labelPosition: 'top',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '04_photo_slot_release_modal_annotated.png'), fullPage: false });
    await clearAnnotations(page);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // ============ 05. スタジオ一覧 ============
    log('05_photo_studios_annotated.png');
    await page.goto(`${BASE_URL}/admin/photo-studios`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        const link = Array.from(document.querySelectorAll('a, button')).find(
            (e) => (e.textContent || '').includes('新規追加')
        );
        if (link) link.id = '__manual_new_studio__';
    });
    await annotateElement(page, '#__manual_new_studio__', {
        label: '① 新しいスタジオの登録',
        color: 'red',
        labelPosition: 'bottom',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '05_photo_studios_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    await browser.close();
    log(`全 5 枚（注釈付き）保存先: ${OUT_DIR}`);
})().catch((err) => {
    console.error('[photo-slot-annotated] エラー発生:', err);
    process.exit(1);
});
