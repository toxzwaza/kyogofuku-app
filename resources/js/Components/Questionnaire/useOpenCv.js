/**
 * OpenCV.js（public/vendor/opencv/opencv.js・約10MB）の遅延ロード
 *
 * スキャンモーダルを開いた時に初めてロードする。
 * ロード後は window.cv がグローバルに定義され、jscanify から参照される。
 */
let loadPromise = null;

export function loadOpenCv() {
    if (window.cv && window.cv.Mat) {
        return Promise.resolve();
    }
    if (loadPromise) {
        return loadPromise;
    }

    loadPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = '/vendor/opencv/opencv.js';
        script.async = true;
        script.onload = () => {
            // ビルドにより cv.Mat が同期/非同期どちらで初期化されるか異なるためポーリングで待つ
            const startedAt = Date.now();
            const timer = setInterval(() => {
                if (window.cv && window.cv.Mat) {
                    clearInterval(timer);
                    resolve();
                } else if (Date.now() - startedAt > 30000) {
                    clearInterval(timer);
                    loadPromise = null;
                    reject(new Error('OpenCV.js の初期化がタイムアウトしました。'));
                }
            }, 100);
        };
        script.onerror = () => {
            loadPromise = null;
            reject(new Error('OpenCV.js の読み込みに失敗しました。'));
        };
        document.head.appendChild(script);
    });

    return loadPromise;
}
