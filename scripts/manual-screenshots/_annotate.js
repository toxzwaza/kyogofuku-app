/**
 * Playwright 撮影前に、対象要素を強調表示する共通ヘルパー。
 *
 * 使い方:
 *   const { annotateElement, clearAnnotations } = require('./_annotate');
 *   await annotateElement(page, '#some-button', { label: '① ここをクリック', color: 'red' });
 *   await page.screenshot({ path: '...' });
 *   await clearAnnotations(page);
 *
 * 描画されるもの:
 *   - 対象要素を太い色枠 (outline + box-shadow) で囲む
 *   - 要素の左上にラベル付き吹き出し（番号＋短い説明）を配置
 *   - 要素の上方向に矢印（▼）を配置
 *
 * 色のパレット:
 *   red    : 赤（最重要のボタン）
 *   blue   : 青（入力欄・選択欄）
 *   green  : 緑（完了・成功アクション）
 *   yellow : 黄（注意・警告）
 */

const COLOR_PALETTE = {
    red:    { border: '#ef4444', bg: '#fee2e2', text: '#7f1d1d' },
    blue:   { border: '#3b82f6', bg: '#dbeafe', text: '#1e3a8a' },
    green:  { border: '#10b981', bg: '#d1fae5', text: '#065f46' },
    yellow: { border: '#f59e0b', bg: '#fef3c7', text: '#78350f' },
};

/**
 * 単一要素に枠＋ラベル＋矢印を注入する。
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} selector  CSS セレクタ
 * @param {object} opts
 * @param {string} [opts.label='']  吹き出しに表示する文言
 * @param {'red'|'blue'|'green'|'yellow'} [opts.color='red']
 * @param {'top'|'bottom'|'left'|'right'} [opts.labelPosition='top']  ラベルの位置
 */
async function annotateElement(page, selector, opts = {}) {
    const { label = '', color = 'red', labelPosition = 'top' } = opts;
    const colors = COLOR_PALETTE[color] || COLOR_PALETTE.red;

    await page.evaluate(({ selector, label, colors, labelPosition }) => {
        const el = document.querySelector(selector);
        if (!el) return false;

        const rect = el.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) return false;

        // 共通コンテナ（複数回呼ばれた時のため、まとめて保管）
        let host = document.getElementById('__manual_annotations__');
        if (!host) {
            host = document.createElement('div');
            host.id = '__manual_annotations__';
            host.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:99999;';
            document.body.appendChild(host);
        }

        // ① 枠
        const frame = document.createElement('div');
        frame.style.cssText = `
            position:absolute;
            left:${rect.left - 4}px;
            top:${rect.top - 4}px;
            width:${rect.width + 8}px;
            height:${rect.height + 8}px;
            border:3px solid ${colors.border};
            border-radius:6px;
            box-shadow:0 0 0 6px ${colors.border}22, 0 4px 12px rgba(0,0,0,0.15);
            pointer-events:none;
        `;
        host.appendChild(frame);

        // ② ラベル吹き出し
        if (label) {
            const tag = document.createElement('div');
            tag.textContent = label;
            tag.style.cssText = `
                position:absolute;
                padding:4px 10px;
                background:${colors.bg};
                color:${colors.text};
                border:2px solid ${colors.border};
                border-radius:6px;
                font-size:13px;
                font-weight:600;
                font-family:'Hiragino Sans','Yu Gothic',sans-serif;
                white-space:nowrap;
                box-shadow:0 2px 6px rgba(0,0,0,0.15);
                pointer-events:none;
            `;
            host.appendChild(tag);
            // 位置を計算（一旦DOMに入れて寸法取得）
            const tagRect = tag.getBoundingClientRect();
            let left, top;
            if (labelPosition === 'top') {
                left = rect.left + rect.width / 2 - tagRect.width / 2;
                top  = rect.top - tagRect.height - 12;
            } else if (labelPosition === 'bottom') {
                left = rect.left + rect.width / 2 - tagRect.width / 2;
                top  = rect.bottom + 12;
            } else if (labelPosition === 'left') {
                left = rect.left - tagRect.width - 12;
                top  = rect.top + rect.height / 2 - tagRect.height / 2;
            } else { // right
                left = rect.right + 12;
                top  = rect.top + rect.height / 2 - tagRect.height / 2;
            }
            // 画面端を超えないように補正
            left = Math.max(8, Math.min(left, window.innerWidth - tagRect.width - 8));
            top  = Math.max(8, Math.min(top, window.innerHeight - tagRect.height - 8));
            tag.style.left = `${left}px`;
            tag.style.top = `${top}px`;

            // ③ 矢印（吹き出しから要素の中心へ向けて）
            const arrow = document.createElement('div');
            const ax = rect.left + rect.width / 2;
            const ay = (labelPosition === 'top') ? rect.top - 6 :
                       (labelPosition === 'bottom') ? rect.bottom + 6 :
                       (labelPosition === 'left') ? rect.left - 6 : rect.right + 6;
            arrow.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" style="filter:drop-shadow(0 1px 2px rgba(0,0,0,0.2));">
                    <polygon points="10,18 4,6 16,6" fill="${colors.border}" />
                </svg>
            `;
            if (labelPosition === 'top') {
                arrow.style.cssText = `position:absolute;left:${ax - 10}px;top:${ay - 14}px;pointer-events:none;`;
                arrow.querySelector('svg').style.transform = 'rotate(0deg)';
            } else if (labelPosition === 'bottom') {
                arrow.style.cssText = `position:absolute;left:${ax - 10}px;top:${ay - 6}px;pointer-events:none;`;
                arrow.querySelector('svg').style.transform = 'rotate(180deg)';
            } else if (labelPosition === 'left') {
                arrow.style.cssText = `position:absolute;left:${ay - 14}px;top:${rect.top + rect.height / 2 - 10}px;pointer-events:none;`;
                arrow.querySelector('svg').style.transform = 'rotate(-90deg)';
            } else {
                arrow.style.cssText = `position:absolute;left:${ay - 6}px;top:${rect.top + rect.height / 2 - 10}px;pointer-events:none;`;
                arrow.querySelector('svg').style.transform = 'rotate(90deg)';
            }
            host.appendChild(arrow);
        }
        return true;
    }, { selector, label, colors, labelPosition });
}

/** 既に置かれている注釈をすべて消去する */
async function clearAnnotations(page) {
    await page.evaluate(() => {
        const host = document.getElementById('__manual_annotations__');
        if (host) host.remove();
    });
}

/** 複数の注釈を一括で配置 */
async function annotateMany(page, list) {
    for (const item of list) {
        await annotateElement(page, item.selector, item);
    }
}

module.exports = { annotateElement, clearAnnotations, annotateMany, COLOR_PALETTE };
