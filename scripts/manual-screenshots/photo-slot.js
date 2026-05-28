/**
 * 前撮りマニュアル用スクリーンショット撮影。
 * 顧客 (customer.js) と同じパターンで、サンプル花子(id=1702) の枠を使う。
 */
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_DIR = path.resolve(__dirname, '../../public/images/manual/photo-slot');
const USER_ID = 1;
const SHOP_ID = 1;
const SAMPLE_HANAKO_SLOT_DATE = '2026-12-12';

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
    if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'ja-JP',
        deviceScaleFactor: 2,
    });
    const page = await context.newPage();
    const log = (m) => console.log(`[photo-slot] ${m}`);

    // ログイン
    log(`ログイン開始 (shop_id=${SHOP_ID}, user_id=${USER_ID})`);
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.selectOption('#shop_id', String(SHOP_ID));
    await page.waitForTimeout(500);
    await page.waitForSelector(`#user_id option[value="${USER_ID}"]`, { state: 'attached' });
    await page.selectOption('#user_id', String(USER_ID));
    await Promise.all([
        page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 15000 }),
        page.evaluate(() => document.querySelector('form').requestSubmit()),
    ]);
    log(`ログイン完了 -> ${page.url()}`);

    // ---------------- 01. 前撮り枠一覧 ----------------
    log('01_photo_slot_index.png');
    await page.goto(`${BASE_URL}/admin/photo-slots`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT_DIR, '01_photo_slot_index.png'), fullPage: false });

    // ---------------- 02. 新規追加画面 ----------------
    log('02_photo_slot_create.png');
    await page.goto(`${BASE_URL}/admin/photo-slots/create`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT_DIR, '02_photo_slot_create.png'), fullPage: false });

    // ---------------- 03. 編集モーダル（個別枠の編集） ----------------
    log('03_photo_slot_edit_modal.png');
    await page.goto(`${BASE_URL}/admin/photo-slots`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);

    // ① 「2026年」「12月」のフィルタボタンをクリックして年/月を絞り込み
    await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('button'));
        // 2026年
        const y = buttons.find((b) => /^2026年/.test((b.textContent || '').trim()));
        if (y) y.click();
    });
    await page.waitForTimeout(600);
    await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('button'));
        const m = buttons.find((b) => /^12月/.test((b.textContent || '').trim()));
        if (m) m.click();
    });
    await page.waitForTimeout(800);

    // ② 12月12日のグループを展開
    const expanded = await page.evaluate(() => {
        const buttons = document.querySelectorAll('button');
        let count = 0;
        for (const btn of buttons) {
            const txt = btn.textContent || '';
            if (/2026年12月12日/.test(txt)) { btn.click(); count++; }
        }
        return count;
    });
    log(`  12/12 日付グループ展開: ${expanded} 件`);
    await page.waitForTimeout(800);

    // ② 個別枠の「サンプル 花子」を含む行で「編集」title のボタンを押す
    const editClicked = await page.evaluate(() => {
        const rows = document.querySelectorAll('tr');
        for (const row of rows) {
            const txt = row.textContent || '';
            if (txt.includes('サンプル') && txt.includes('花子')) {
                const btn = row.querySelector('button[title="編集"]');
                if (btn) { btn.click(); return 'matched'; }
            }
        }
        // フォールバック: 個別枠で最初の編集ボタンを押す（顧客付きスロット用なら title="編集"）
        const fallback = document.querySelector('button[title="編集"]');
        if (fallback) { fallback.click(); return 'fallback'; }
        return false;
    });
    if (editClicked) {
        await page.waitForTimeout(1500);
        log(`  編集モーダルを起動: ${editClicked}`);
    } else {
        log('  ⚠ 個別枠の編集ボタンが見つかりません');
    }
    await page.screenshot({ path: path.join(OUT_DIR, '03_photo_slot_edit_modal.png'), fullPage: false });

    // モーダルを閉じる（ESC）
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // ---------------- 04. 解除確認モーダル（サンプル花子の枠で撮る） ----------------
    log('04_photo_slot_release_modal.png');
    await page.goto(`${BASE_URL}/admin/photo-slots`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    // 2026年→12月→2026年12月12日 展開
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
    // 「サンプル 花子」を含む行の解除ボタンを取る
    const releaseClicked = await page.evaluate(() => {
        const rows = document.querySelectorAll('tr');
        for (const row of rows) {
            const txt = row.textContent || '';
            if (txt.includes('サンプル') && txt.includes('花子')) {
                const btn = row.querySelector('button[title="顧客の紐付けを解除"]');
                if (btn) { btn.scrollIntoView({ block: 'center' }); btn.click(); return 'matched'; }
            }
        }
        return false;
    });
    if (releaseClicked) {
        await page.waitForTimeout(1500);
        log(`  解除モーダル起動: ${releaseClicked}`);
    } else {
        log('  ⚠ サンプル花子の解除ボタンが見つかりません');
    }
    await page.screenshot({ path: path.join(OUT_DIR, '04_photo_slot_release_modal.png'), fullPage: false });

    // ESCでモーダル閉じる（残ったまま遷移しないようにする保険）
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // ---------------- 05. スタジオ一覧 ----------------
    log('05_photo_studios.png');
    await page.goto(`${BASE_URL}/admin/photo-studios`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT_DIR, '05_photo_studios.png'), fullPage: false });

    await browser.close();
    log('全 5 枚のスクリーンショットを ' + OUT_DIR + ' に保存しました');
})().catch((err) => {
    console.error('[photo-slot] エラー発生:', err);
    process.exit(1);
});
