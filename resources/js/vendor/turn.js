// turn.js ラッパー
// jQuery が確実に設定された後に turn.js を読み込む

// jQuery が既にグローバルに設定されていることを確認
if (!window.$ || !window.jQuery) {
    throw new Error('jQuery がグローバルに設定されていません。先に jQuery をインポートして window.$ と window.jQuery に設定してください。');
}

// turn.js をインポート（jQuery が設定された後）
import './turn.min.js';

