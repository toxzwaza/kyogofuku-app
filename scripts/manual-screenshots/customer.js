/**
 * 顧客マニュアル用のスクリーンショットを Playwright で撮影する。
 *
 * 前提:
 *   - php artisan serve が http://127.0.0.1:8000 で稼働中
 *   - .env の DB_DATABASE=localdb_prod
 *   - SECURITY_LOGIN=false（パスワード不要、user_id+shop_idだけでログイン可）
 *   - ManualSampleCustomerSeeder 実行済み（サンプル顧客 8名）
 *
 * 実行: node scripts/manual-screenshots/customer.js
 *
 * 出力先: public/images/manual/customer/{NN}_xxx.png
 */
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_DIR = path.resolve(__dirname, '../../public/images/manual/customer');
// 撮影に使うユーザー（村上 飛羽 = id 1）と店舗（岡山店 = id 1）
const USER_ID = 1;
const SHOP_ID = 1;
// サンプル顧客の id（ManualSampleCustomerSeeder と一致）
const SAMPLE_HANAKO_ID = 1702;  // フル機能サンプル
const SAMPLE_AOI_ID = 1706;     // メモ・タグサンプル

/** 見出し系要素から text を含むものを探し、画面トップに来るようスクロール */
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
    await page.waitForTimeout(600);
}

(async () => {
    if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'ja-JP',
        deviceScaleFactor: 2, // Retina相当の鮮明な画像
    });
    const page = await context.newPage();

    // 失敗時に出力を絞り込みやすいよう操作内容をログ
    const log = (msg) => console.log(`[customer] ${msg}`);

    // ---------------- ログイン ----------------
    log(`ログイン開始 (shop_id=${SHOP_ID}, user_id=${USER_ID})`);
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });

    // ① 店舗を先に選択（store_id が空だと user 選択肢が出ない仕様）
    await page.selectOption('#shop_id', String(SHOP_ID));
    await page.waitForTimeout(500);
    // ② ユーザー選択 (Vue の watcher が user options を生成するのを待つ)
    await page.waitForSelector(`#user_id option[value="${USER_ID}"]`, { state: 'attached', timeout: 5000 });
    await page.selectOption('#user_id', String(USER_ID));
    await page.waitForTimeout(300);

    // ③ form を直接 submit（Inertia.js の form は requestSubmit に反応する）
    log('  form 送信...');
    await Promise.all([
        page.waitForURL((url) => !url.toString().includes('/login'), { timeout: 15000 }),
        page.evaluate(() => {
            const form = document.querySelector('form');
            if (form && typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else if (form) {
                form.submit();
            }
        }),
    ]);
    log(`ログイン完了 -> ${page.url()}`);

    // ---------------- 01. 顧客一覧（サンプルでフィルタ） ----------------
    log('01_customer_index.png');
    await page.goto(`${BASE_URL}/admin/customers?search=サンプル`, { waitUntil: 'networkidle' });
    // テーブルが描画されるまで余裕を見て待つ
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT_DIR, '01_customer_index.png'), fullPage: false });

    // ---------------- 02. 新規登録モーダル ----------------
    log('02_customer_create_form.png');
    // 一覧画面にいない場合に備えて goto
    if (!page.url().includes('/admin/customers')) {
        await page.goto(`${BASE_URL}/admin/customers`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
    }
    // 顧客追加ボタンをクリックしてモーダルを開く
    const addBtn = page.locator('button:has-text("顧客追加"), a:has-text("顧客追加"), button:has-text("新規")').first();
    if (await addBtn.count()) {
        await addBtn.click();
        await page.waitForTimeout(1500);
    } else {
        log('  ⚠ 顧客追加ボタンが見つかりません');
    }
    await page.screenshot({ path: path.join(OUT_DIR, '02_customer_create_form.png'), fullPage: false });

    // ---------------- 03. 詳細タブ（花子） ----------------
    log('03_customer_show_tabs.png');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_HANAKO_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT_DIR, '03_customer_show_tabs.png'), fullPage: false });

    // ---------------- 04. 連絡メモ（葵） ----------------
    log('04_customer_notes.png');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_AOI_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    // 「連絡・メモ」タブをクリック
    const memoTab = page.locator('button:has-text("連絡・メモ"), [role="tab"]:has-text("連絡・メモ"), button:has-text("メモ")').first();
    if (await memoTab.count()) {
        await memoTab.click().catch(() => {});
        await page.waitForTimeout(800);
    }
    // 「連絡メモ」見出しが画面に来るよう DOM 上で探してスクロール
    await scrollToText(page, 'メモ');
    await page.screenshot({ path: path.join(OUT_DIR, '04_customer_notes.png'), fullPage: false });

    // ---------------- 05. 編集モーダル（花子） ----------------
    log('05_customer_edit_modal.png');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_HANAKO_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    // 概要タブの「基本情報」付近の「編集」ボタンをクリック
    // 顧客タグの「タグを追加」と区別するため「基本情報」直近の編集を狙う
    const editClicked = await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('button'));
        const editBtn = buttons.find((b) => b.textContent && b.textContent.trim() === '編集');
        if (editBtn) { editBtn.click(); return true; }
        return false;
    });
    if (editClicked) {
        await page.waitForTimeout(1500);
    } else {
        log('  ⚠ 編集ボタンが見つかりません');
    }
    await page.screenshot({ path: path.join(OUT_DIR, '05_customer_edit_modal.png'), fullPage: false });

    // ---------------- 06. 連絡先情報（花子の概要タブ） ----------------
    log('06_customer_contact_info.png');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_HANAKO_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    // 概要タブにいる前提（デフォルトで概要）。「電話番号」や「メールアドレス」見出しまでスクロール
    await scrollToText(page, '電話番号');
    await page.screenshot({ path: path.join(OUT_DIR, '06_customer_contact_info.png'), fullPage: false });

    // ---------------- 07. タグ追加（葵） ----------------
    log('07_customer_tags.png');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_AOI_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    // 概要タブの顧客タグ欄が見える位置までスクロール
    await scrollToText(page, '顧客タグ');
    await page.screenshot({ path: path.join(OUT_DIR, '07_customer_tags.png'), fullPage: false });

    // ---------------- 08. 制約追加（花子の詳細情報タブ） ----------------
    log('08_customer_constraint.png');
    await page.goto(`${BASE_URL}/admin/customers/${SAMPLE_HANAKO_ID}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    // 詳細情報タブをクリック
    const infoTab = page.locator('button:has-text("詳細情報"), [role="tab"]:has-text("詳細情報")').first();
    if (await infoTab.count()) {
        await infoTab.click().catch(() => {});
        await page.waitForTimeout(800);
    }
    // 「制約情報」見出しまでスクロール
    await scrollToText(page, '制約情報');
    await page.screenshot({ path: path.join(OUT_DIR, '08_customer_constraint.png'), fullPage: false });

    await browser.close();
    log('全 8 枚のスクリーンショットを ' + OUT_DIR + ' に保存しました');
})().catch((err) => {
    console.error('[customer] エラー発生:', err);
    process.exit(1);
});
