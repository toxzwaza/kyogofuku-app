/**
 * 周辺機能マニュアル用スクリーンショット撮影。
 */
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_DIR = path.resolve(__dirname, '../../public/images/manual/peripheral');
const USER_ID = 1;
const SHOP_ID = 1;

(async () => {
    if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'ja-JP',
        deviceScaleFactor: 2,
    });
    const page = await context.newPage();
    const log = (m) => console.log(`[peripheral] ${m}`);

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

    const shots = [
        { file: '01_line_contacts.png',          url: '/admin/line-contacts' },
        { file: '02_line_unknown.png',           url: '/admin/line-unknown-inbox' },
        { file: '03_customer_tags.png',          url: '/admin/customer-tags' },
        { file: '04_constraint_templates.png',   url: '/admin/constraint-templates' },
        { file: '05_slideshows.png',             url: '/admin/slideshows' },
        { file: '06_media.png',                  url: '/admin/media' },
    ];

    for (const s of shots) {
        log(s.file);
        await page.goto(`${BASE_URL}${s.url}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);
        await page.screenshot({ path: path.join(OUT_DIR, s.file), fullPage: false });
    }

    await browser.close();
    log(`全 ${shots.length} 枚のスクリーンショットを ${OUT_DIR} に保存しました`);
})().catch((err) => {
    console.error('[peripheral] エラー発生:', err);
    process.exit(1);
});
