/**
 * 顧客マニュアル用 「注釈付き」スクリーンショット。
 * 元の customer.js と同じ画面を撮り、重要箇所に枠・矢印・ラベルを付ける。
 * 出力ファイル名は ..._annotated.png。
 */
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const { annotateElement, clearAnnotations } = require('./_annotate');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_DIR = path.resolve(__dirname, '../../public/images/manual/customer');
const USER_ID = 1;
const SHOP_ID = 1;
const SAMPLE_HANAKO_ID = 1702;
const SAMPLE_AOI_ID = 1706;

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

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'ja-JP',
        deviceScaleFactor: 2,
    });
    const page = await context.newPage();
    const log = (m) => console.log(`[customer-annotated] ${m}`);

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

    // ============ 01. 顧客一覧 ============
    log('01_customer_index_annotated.png');
    await page.goto(`${BASE_URL}/admin/customers?search=サンプル`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    // 「顧客追加」ボタンを赤枠で強調
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => b.textContent && b.textContent.includes('顧客追加')
        );
        if (btn) btn.id = '__manual_add_customer__';
    });
    await annotateElement(page, '#__manual_add_customer__', {
        label: '① 新規登録はここから',
        color: 'red',
        labelPosition: 'bottom',
    });
    // 検索ボックスを青枠
    await annotateElement(page, 'input[type="text"], input[type="search"]', {
        label: '② お名前・電話・郵便番号で検索',
        color: 'blue',
        labelPosition: 'bottom',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '01_customer_index_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // ============ 02. 顧客追加モーダル ============
    log('02_customer_create_form_annotated.png');
    // モーダル開く
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => b.textContent && b.textContent.includes('顧客追加')
        );
        if (btn) btn.click();
    });
    await page.waitForTimeout(1500);
    // モーダル内の必須項目「顧客名」入力欄を強調
    await page.evaluate(() => {
        const labels = Array.from(document.querySelectorAll('label'));
        const nameLabel = labels.find((l) => l.textContent && l.textContent.includes('顧客名'));
        if (nameLabel) {
            // 同じ親内のinputを取る
            const wrap = nameLabel.parentElement;
            const input = wrap && wrap.querySelector('input');
            if (input) input.id = '__manual_field_name__';
        }
    });
    await annotateElement(page, '#__manual_field_name__', {
        label: '① 必須：お客様のお名前',
        color: 'red',
        labelPosition: 'right',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '02_customer_create_form_annotated.png'), fullPage: false });
    await clearAnnotations(page);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // ============ 03. 顧客詳細 ============
    log('03_customer_show_tabs_annotated.png');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_HANAKO_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    // 概要・詳細情報・連絡メモ のタブ列を強調
    await page.evaluate(() => {
        const tabs = document.querySelector('[role="tablist"], nav.flex');
        if (tabs) tabs.id = '__manual_tabs__';
    });
    await annotateElement(page, '#__manual_tabs__', {
        label: '① タブで情報を切り替え',
        color: 'blue',
        labelPosition: 'bottom',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '03_customer_show_tabs_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // ============ 04. 連絡メモ ============
    log('04_customer_notes_annotated.png');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_AOI_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    const memoTab = page.locator('button:has-text("連絡・メモ"), [role="tab"]:has-text("連絡・メモ")').first();
    if (await memoTab.count()) {
        await memoTab.click().catch(() => {});
        await page.waitForTimeout(800);
    }
    await scrollToText(page, 'メモ');
    // メモ追加ボタンを強調
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        const add = btns.find((b) => /メモを?追加|追加する/.test(b.textContent || ''));
        if (add) add.id = '__manual_memo_add__';
        const textarea = document.querySelector('textarea');
        if (textarea) textarea.id = '__manual_memo_input__';
    });
    await annotateElement(page, '#__manual_memo_input__', {
        label: '① ここに内容をご入力',
        color: 'blue',
        labelPosition: 'top',
    });
    await annotateElement(page, '#__manual_memo_add__', {
        label: '② 追加ボタンで保存',
        color: 'red',
        labelPosition: 'right',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '04_customer_notes_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // ============ 05. 編集モーダル ============
    log('05_customer_edit_modal_annotated.png');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_HANAKO_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.evaluate(() => {
        const editBtn = Array.from(document.querySelectorAll('button')).find(
            (b) => (b.textContent || '').trim() === '編集'
        );
        if (editBtn) editBtn.click();
    });
    await page.waitForTimeout(1500);
    // 保存ボタンを強調
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        const save = btns.find((b) => /保存|更新/.test((b.textContent || '').trim()));
        if (save) save.id = '__manual_save_btn__';
    });
    await annotateElement(page, '#__manual_save_btn__', {
        label: '③ 入力後は必ず保存',
        color: 'green',
        labelPosition: 'top',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '05_customer_edit_modal_annotated.png'), fullPage: false });
    await clearAnnotations(page);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // ============ 06. 連絡先 ============
    log('06_customer_contact_info_annotated.png');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_HANAKO_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await scrollToText(page, '電話番号');
    await page.evaluate(() => {
        // tel: リンクを強調
        const telLink = document.querySelector('a[href^="tel:"]');
        if (telLink) telLink.id = '__manual_tel_link__';
        const mailLink = document.querySelector('a[href^="mailto:"]');
        if (mailLink) mailLink.id = '__manual_mail_link__';
    });
    await annotateElement(page, '#__manual_tel_link__', {
        label: '① 電話番号をクリックでお電話',
        color: 'red',
        labelPosition: 'top',
    });
    await annotateElement(page, '#__manual_mail_link__', {
        label: '② メールアドレスでメール作成',
        color: 'blue',
        labelPosition: 'top',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '06_customer_contact_info_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // ============ 07. タグ ============
    log('07_customer_tags_annotated.png');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_AOI_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await scrollToText(page, '顧客タグ');
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
            (b) => (b.textContent || '').includes('タグを追加') || (b.textContent || '').includes('+ タグ')
        );
        if (btn) btn.id = '__manual_tag_add__';
    });
    await annotateElement(page, '#__manual_tag_add__', {
        label: '① タグはここから追加',
        color: 'red',
        labelPosition: 'left',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '07_customer_tags_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    // ============ 08. 制約 ============
    log('08_customer_constraint_annotated.png');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_HANAKO_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    const infoTab = page.locator('button:has-text("詳細情報"), [role="tab"]:has-text("詳細情報")').first();
    if (await infoTab.count()) {
        await infoTab.click().catch(() => {});
        await page.waitForTimeout(800);
    }
    await scrollToText(page, '制約情報');
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        const add = btns.find((b) => (b.textContent || '').includes('制約追加'));
        if (add) add.id = '__manual_const_add__';
    });
    await annotateElement(page, '#__manual_const_add__', {
        label: '① 制約はここから発行',
        color: 'red',
        labelPosition: 'left',
    });
    await page.screenshot({ path: path.join(OUT_DIR, '08_customer_constraint_annotated.png'), fullPage: false });
    await clearAnnotations(page);

    await browser.close();
    log(`全 8 枚（注釈付き）の保存先: ${OUT_DIR}`);
})().catch((err) => {
    console.error('[customer-annotated] エラー発生:', err);
    process.exit(1);
});
