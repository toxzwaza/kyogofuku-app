/**
 * 周辺機能マニュアル用「注釈付き」スクリーンショット。
 */
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const { annotateElement, clearAnnotations } = require('./_annotate');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_DIR = path.resolve(__dirname, '../../public/images/manual/peripheral');
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
    const log = (m) => console.log(`[peripheral-annotated] ${m}`);

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
        {
            file: '01_line_contacts_annotated.png',
            url: '/admin/line-contacts',
            highlight: { search: '検索', label: '① キーワードで検索', color: 'blue', position: 'bottom' },
        },
        {
            file: '02_line_unknown_annotated.png',
            url: '/admin/line-unknown-inbox',
        },
        {
            file: '03_customer_tags_annotated.png',
            url: '/admin/customer-tags',
            highlight: { search: '新規', label: '① 新しいタグを追加', color: 'red', position: 'bottom' },
        },
        {
            file: '04_constraint_templates_annotated.png',
            url: '/admin/constraint-templates',
            highlight: { search: '新規', label: '① 新しいテンプレート', color: 'red', position: 'bottom' },
        },
        {
            file: '05_slideshows_annotated.png',
            url: '/admin/slideshows',
            highlight: { search: '新規', label: '① 新しいスライドショー', color: 'red', position: 'bottom' },
        },
        {
            file: '06_media_annotated.png',
            url: '/admin/media',
            highlight: { search: 'アップロード', label: '① 画像をアップロード', color: 'red', position: 'bottom' },
        },
    ];

    for (const s of shots) {
        log(s.file);
        await page.goto(`${BASE_URL}${s.url}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);

        if (s.highlight) {
            const found = await page.evaluate((search) => {
                const btn = Array.from(document.querySelectorAll('button, a')).find(
                    (e) => (e.textContent || '').includes(search)
                );
                if (btn) { btn.id = '__manual_target__'; return true; }
                return false;
            }, s.highlight.search);
            if (found) {
                await annotateElement(page, '#__manual_target__', {
                    label: s.highlight.label,
                    color: s.highlight.color,
                    labelPosition: s.highlight.position,
                });
            }
        }
        await page.screenshot({ path: path.join(OUT_DIR, s.file), fullPage: false });
        await clearAnnotations(page);
    }

    await browser.close();
    log(`全 ${shots.length} 枚（注釈付き）保存先: ${OUT_DIR}`);
})().catch((err) => {
    console.error('[peripheral-annotated] エラー発生:', err);
    process.exit(1);
});
