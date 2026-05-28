/**
 * マニュアルのPDF版を生成する。
 *
 * 対象:
 *   1. 簡易マニュアル 2026-05-29  → public/manuals/簡易マニュアル_20260529.pdf
 *   2. 勤怠マニュアル              → public/manuals/勤怠マニュアル.pdf
 *
 * 仕組み:
 *   ・ Playwright で headless chromium にてログイン
 *   ・ 対象ページを開いて全要素が読み込まれてからスクロール → 画像遅延読込を強制
 *   ・ page.emulateMedia({ media: 'print' }) で印刷モードCSSを適用
 *   ・ page.pdf({ format: 'A4' }) で出力
 */
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_DIR = path.resolve(__dirname, '../../public/manuals');
const USER_ID = 1;
const SHOP_ID = 1;

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

/** ページ全体をゆっくりスクロール → 画像（lazy-load含む）を確実に読み込ませる */
async function scrollToBottom(page) {
    await page.evaluate(async () => {
        await new Promise((resolve) => {
            let totalHeight = 0;
            const distance = 400;
            const timer = setInterval(() => {
                const scrollHeight = document.body.scrollHeight;
                window.scrollBy(0, distance);
                totalHeight += distance;
                if (totalHeight >= scrollHeight) {
                    clearInterval(timer);
                    resolve();
                }
            }, 150);
        });
    });
    // 画像が完全に読み込まれるまで待つ
    await page.evaluate(async () => {
        const imgs = Array.from(document.images);
        await Promise.all(imgs.map((img) => {
            if (img.complete) return Promise.resolve();
            return new Promise((res) => {
                img.addEventListener('load', res, { once: true });
                img.addEventListener('error', res, { once: true });
            });
        }));
    });
    await page.waitForTimeout(500);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(500);
}

async function generatePdf(page, urlPath, outputFile) {
    console.log(`[pdf] ${outputFile} 生成開始`);
    await page.goto(`${BASE_URL}${urlPath}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    await scrollToBottom(page);
    // 印刷モードに切替
    await page.emulateMedia({ media: 'print' });
    await page.waitForTimeout(800);

    const outPath = path.join(OUT_DIR, outputFile);
    await page.pdf({
        path: outPath,
        format: 'A4',
        printBackground: true,
        preferCSSPageSize: true,
        margin: { top: '12mm', right: '12mm', bottom: '14mm', left: '12mm' },
        displayHeaderFooter: true,
        headerTemplate: '<div style="font-size:8px; width:100%; text-align:right; padding-right:12mm; color:#888;"></div>',
        footerTemplate: '<div style="font-size:8px; width:100%; text-align:center; color:#888;"><span class="pageNumber"></span> / <span class="totalPages"></span></div>',
    });
    // 戻す
    await page.emulateMedia({ media: 'screen' });
    const size = fs.statSync(outPath).size;
    console.log(`[pdf] ${outputFile} 生成完了 (${(size / 1024).toFixed(1)} KB)`);
}

(async () => {
    if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1240, height: 1754 }, // A4比率
        locale: 'ja-JP',
        deviceScaleFactor: 2,
    });
    const page = await context.newPage();
    await login(page);
    console.log('[pdf] ログイン完了');

    await generatePdf(page, '/admin/manuals/simple-20260529', '簡易マニュアル_20260529.pdf');
    await generatePdf(page, '/attendance/manual', '勤怠マニュアル.pdf');

    await browser.close();
    console.log('[pdf] 全完了');
})().catch((err) => {
    console.error('[pdf] エラー:', err);
    process.exit(1);
});
