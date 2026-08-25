<script setup>
import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import jscanify from 'jscanify/client';
import Modal from '@/Components/Modal.vue';
import { UiButton } from '@/Components/UI';
import { Camera, RefreshCw, Check, X as XIcon, Move } from 'lucide-vue-next';
import { loadOpenCv, loadAruco } from './useOpenCv';

const props = defineProps({
    show: { type: Boolean, default: false },
    customer: { type: Object, required: true },
    initialPage: { type: Number, default: 1 },
});

const emit = defineEmits(['close']);

// A4比率・150dpi相当の出力サイズ
const OUTPUT_WIDTH = 1240;
const OUTPUT_HEIGHT = 1754;
// 検出用の縮小幅
const DETECT_WIDTH = 640;
// マーカー検出用の幅（マーカーは小さいので高解像度で検出する）
const MARKER_DETECT_WIDTH = 1600;
// 検出対象とみなす面積（フレーム面積に対する比率）
const MIN_AREA_RATIO = 0.08;
const MAX_AREA_RATIO = 0.85; // 画面ほぼ全体はマットや机の縁なので除外
// 紙とみなす最低の平均輝度（0-255）。フォールバック検出で使用
const MIN_PAPER_BRIGHTNESS = 110;
// 用紙四隅のArUcoマーカーID（印刷テンプレートと対応）
const MARKER_GROUPS = [
    { page: 1, ids: [0, 1, 2, 3] }, // TL, TR, BR, BL
    { page: 2, ids: [4, 5, 6, 7] },
];
// 自動撮影: 四隅の移動量(縮小px)がこの値未満のフレームが連続したら撮影
const STABLE_DIST_THRESHOLD = 10;
const STABLE_FRAMES_REQUIRED = 10;

const page = ref(props.initialPage);
const phase = ref('loading'); // loading | detecting | preview | manual | uploading | error
const errorMessage = ref('');
const detectStatus = ref('');
const diagInfo = ref(''); // 診断表示（カメラ解像度・マーカー検出数）
const stableCount = ref(0);

const videoRef = ref(null);
const overlayRef = ref(null);
const videoWrapRef = ref(null);
const previewImgSrc = ref('');
const manualImgRef = ref(null);
const manualWrapRef = ref(null);

let mediaStream = null;
let detectTimer = null;
let scanner = null;
let markerDetector = null;
let lastCorners = null;
let capturedFrameCanvas = null; // 手動指定用のフル解像度スナップショット
// 直近に検出したマーカーの記憶（id → {corners, time}）。
// 4個が同一フレームで同時に写る必要をなくし、チラつきに強くする
let markerCache = {};
const MARKER_CACHE_MS = 1200;

// 手動四隅指定のハンドル位置（表示px）
const manualHandles = ref([]);
let draggingHandleIndex = -1;

const pageLabel = computed(() => `${page.value}ページ目`);

watch(() => props.show, async (show) => {
    if (show) {
        page.value = props.initialPage;
        await start();
    } else {
        cleanup();
    }
});

async function start() {
    phase.value = 'loading';
    errorMessage.value = '';
    previewImgSrc.value = '';
    stableCount.value = 0;
    lastCorners = null;
    markerCache = {};

    try {
        detectStatus.value = 'スキャンエンジンを読み込み中…（初回のみ数秒かかります）';
        await Promise.all([loadOpenCv(), loadAruco()]);
        scanner = scanner || new jscanify();
        // maxHammingDistance: 辞書デフォルト(tau=12)は緩すぎて表の枠線等を誤検出するため厳格化。
        // 検出後にID 0-7へ絞り込むため、4ビットまで許容しても誤検出は実質起きない
        markerDetector = markerDetector || new window.AR.Detector({ dictionaryName: 'ARUCO_MIP_36h12', maxHammingDistance: 4 });

        detectStatus.value = 'カメラを起動中…';
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment',
                width: { ideal: 1920 },
                height: { ideal: 1080 },
            },
            audio: false,
        });

        phase.value = 'detecting';
        await nextTick();
        const video = videoRef.value;
        video.srcObject = mediaStream;
        await video.play();

        detectStatus.value = '用紙全体が映るようにかざしてください';
        detectTimer = setInterval(detectFrame, 150);
    } catch (e) {
        phase.value = 'error';
        errorMessage.value = e?.name === 'NotAllowedError'
            ? 'カメラの使用が許可されませんでした。ブラウザの設定を確認してください。'
            : (e?.message || 'カメラの起動に失敗しました。');
    }
}

/**
 * 用紙の四隅を検出する（自前パイプライン）
 * グレースケール → ぼかし → Canny → 膨張 → 輪郭抽出 → 凸四角形近似で候補を集め、
 * 「面積 × 明るさ²」スコアで最も紙らしい四角形を選ぶ。
 * （最大面積だけで選ぶと、紙より大きいデスクマットや机の縁が勝ってしまうため）
 */
function findDocumentCorners(canvasEl, frameWidth, frameHeight) {
    const cv = window.cv;
    let src = null, gray = null, edges = null, kernel = null, contours = null, hierarchy = null;
    const candidates = [];
    try {
        src = cv.imread(canvasEl);
        gray = new cv.Mat();
        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);
        cv.GaussianBlur(gray, gray, new cv.Size(5, 5), 0);
        edges = new cv.Mat();
        cv.Canny(gray, edges, 50, 150);
        kernel = cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(3, 3));
        cv.dilate(edges, edges, kernel);
        contours = new cv.MatVector();
        hierarchy = new cv.Mat();
        // RETR_EXTERNAL だとマットや机の内側に置かれた紙の輪郭が候補から漏れるため全輪郭を対象にする
        cv.findContours(edges, contours, hierarchy, cv.RETR_LIST, cv.CHAIN_APPROX_SIMPLE);

        const frameArea = frameWidth * frameHeight;
        for (let i = 0; i < contours.size(); i++) {
            const contour = contours.get(i);
            const area = cv.contourArea(contour);
            if (area > frameArea * MIN_AREA_RATIO && area < frameArea * MAX_AREA_RATIO) {
                const peri = cv.arcLength(contour, true);
                const approx = new cv.Mat();
                cv.approxPolyDP(contour, approx, 0.03 * peri, true);
                if (approx.rows === 4 && cv.isContourConvex(approx)) {
                    const pts = [];
                    for (let k = 0; k < 4; k++) {
                        pts.push({ x: approx.data32S[k * 2], y: approx.data32S[k * 2 + 1] });
                    }
                    candidates.push({ corners: orderCorners(pts), area });
                }
                approx.delete();
            }
            contour.delete();
        }
    } catch (e) {
        return null;
    } finally {
        [src, gray, edges, kernel, hierarchy].forEach((m) => m && m.delete());
        if (contours) contours.delete();
    }

    if (candidates.length === 0) return null;

    // 内部の明るさで「紙らしさ」をスコアリング
    const imageData = canvasEl.getContext('2d').getImageData(0, 0, frameWidth, frameHeight);
    let best = null;
    let bestScore = -1;
    for (const cand of candidates) {
        const brightness = quadMeanBrightness(imageData, cand.corners, frameWidth);
        const score = cand.area * Math.pow(brightness / 255, 2);
        if (score > bestScore) {
            bestScore = score;
            best = { ...cand, brightness };
        }
    }
    // 明るさが紙とは思えない場合は不採用（暗いマット等の誤検出防止）
    if (best.brightness < MIN_PAPER_BRIGHTNESS) return null;

    return best.corners;
}

/**
 * 四角形内部の平均輝度（0-255）
 * 四隅の双一次補間で内部を格子サンプリングする（外周1割は避ける）
 */
function quadMeanBrightness(imageData, c, frameWidth) {
    const { topLeftCorner: tl, topRightCorner: tr, bottomRightCorner: br, bottomLeftCorner: bl } = c;
    const data = imageData.data;
    let sum = 0;
    let count = 0;
    const N = 12;
    for (let i = 1; i < N; i++) {
        for (let j = 1; j < N; j++) {
            const u = i / N;
            const v = j / N;
            const x = Math.round((1 - v) * ((1 - u) * tl.x + u * tr.x) + v * ((1 - u) * bl.x + u * br.x));
            const y = Math.round((1 - v) * ((1 - u) * tl.y + u * tr.y) + v * ((1 - u) * bl.y + u * br.y));
            const idx = (y * frameWidth + x) * 4;
            if (idx >= 0 && idx < data.length) {
                sum += 0.299 * data[idx] + 0.587 * data[idx + 1] + 0.114 * data[idx + 2];
                count++;
            }
        }
    }
    return count ? sum / count : 0;
}

// 4点を左上・右上・右下・左下に並べ替える
function orderCorners(pts) {
    const bySum = [...pts].sort((a, b) => (a.x + a.y) - (b.x + b.y));
    const byDiff = [...pts].sort((a, b) => (a.x - a.y) - (b.x - b.y));
    return {
        topLeftCorner: bySum[0],
        bottomRightCorner: bySum[3],
        bottomLeftCorner: byDiff[0],
        topRightCorner: byDiff[3],
    };
}

// 四角形の面積（靴ひも公式）
function quadArea(c) {
    const p = [c.topLeftCorner, c.topRightCorner, c.bottomRightCorner, c.bottomLeftCorner];
    let area = 0;
    for (let i = 0; i < 4; i++) {
        const j = (i + 1) % 4;
        area += p[i].x * p[j].y - p[j].x * p[i].y;
    }
    return Math.abs(area) / 2;
}

/**
 * 四隅のArUcoマーカーで用紙を検出する（最優先の検出方式）
 * @returns {page, corners, found} または null（4個未満のときはfoundに検出数）
 */
function detectByMarkers(imageData) {
    let raw = [];
    try {
        raw = markerDetector.detect(imageData);
    } catch (e) {
        return null;
    }

    // 対象IDのみ採用し、直近検出キャッシュを更新
    const now = performance.now();
    const markers = raw.filter((m) => m.id >= 0 && m.id <= 7);
    for (const m of markers) {
        markerCache[m.id] = { corners: m.corners.map((p) => ({ x: p.x, y: p.y })), time: now };
    }
    for (const id of Object.keys(markerCache)) {
        if (now - markerCache[id].time > MARKER_CACHE_MS) delete markerCache[id];
    }

    let partial = null;
    for (const group of MARKER_GROUPS) {
        const found = group.ids.filter((id) => markerCache[id]);
        if (found.length === 4) {
            // 4マーカーの中心の重心から見て、各マーカーの最も外側の角＝用紙の四隅
            const centers = group.ids.map((id) => markerCenter(markerCache[id]));
            const cx = centers.reduce((s, c) => s + c.x, 0) / 4;
            const cy = centers.reduce((s, c) => s + c.y, 0) / 4;
            const outer = (id) => outermostCorner(markerCache[id], cx, cy);
            const [tl, tr, br, bl] = group.ids;
            return {
                page: group.page,
                markers,
                corners: {
                    topLeftCorner: outer(tl),
                    topRightCorner: outer(tr),
                    bottomRightCorner: outer(br),
                    bottomLeftCorner: outer(bl),
                },
            };
        }
        if (found.length > 0 && (!partial || found.length > partial.found)) {
            partial = { page: group.page, corners: null, found: found.length, markers };
        }
    }
    return partial || { page: null, corners: null, found: 0, markers };
}

function markerCenter(marker) {
    const c = marker.corners;
    return {
        x: (c[0].x + c[1].x + c[2].x + c[3].x) / 4,
        y: (c[0].y + c[1].y + c[2].y + c[3].y) / 4,
    };
}

function outermostCorner(marker, cx, cy) {
    let best = marker.corners[0];
    let bestDist = -1;
    for (const p of marker.corners) {
        const d = Math.hypot(p.x - cx, p.y - cy);
        if (d > bestDist) {
            bestDist = d;
            best = { x: p.x, y: p.y };
        }
    }
    return best;
}

function detectFrame() {
    const video = videoRef.value;
    const overlay = overlayRef.value;
    if (!video || !overlay || video.readyState < 2 || phase.value !== 'detecting') return;

    const scale = DETECT_WIDTH / video.videoWidth;
    const detectHeight = Math.round(video.videoHeight * scale);

    const small = document.createElement('canvas');
    small.width = DETECT_WIDTH;
    small.height = detectHeight;
    small.getContext('2d').drawImage(video, 0, 0, DETECT_WIDTH, detectHeight);

    // 1) 四隅マーカー検出（最優先・確実。マーカーは小さいため高解像度で検出）
    let corners = null;
    let markerHint = '';
    const mw = Math.min(MARKER_DETECT_WIDTH, video.videoWidth);
    const mh = Math.round(video.videoHeight * mw / video.videoWidth);
    const markerCanvas = document.createElement('canvas');
    markerCanvas.width = mw;
    markerCanvas.height = mh;
    markerCanvas.getContext('2d').drawImage(video, 0, 0, mw, mh);
    const markerImageData = markerCanvas.getContext('2d').getImageData(0, 0, mw, mh);
    const markerResult = detectByMarkers(markerImageData);
    const markerScale = DETECT_WIDTH / mw;
    if (markerResult?.corners) {
        // マーカー座標系(mw)→検出座標系(DETECT_WIDTH)へ変換
        corners = scaleCorners(markerResult.corners, markerScale);
        if (page.value !== markerResult.page) {
            page.value = markerResult.page; // 用紙のマーカーからページを自動判定
        }
        markerHint = `${markerResult.page}ページ目のマーカーを検出`;
    } else if (markerResult?.found) {
        markerHint = `マーカー ${markerResult.found}/4 検出中…四隅すべてが映るようにしてください`;
    }
    diagInfo.value = `カメラ ${video.videoWidth}×${video.videoHeight} ／ マーカー検出 ${markerResult?.markers?.length ?? 0}個`;
    const markerOutlines = (markerResult?.markers ?? []).map((m) =>
        m.corners.map((p) => ({ x: p.x * markerScale, y: p.y * markerScale }))
    );

    // 2) 自前の四角形検出（明るさスコアで紙らしい四角形を選ぶ）
    // マーカーが1個でも見えている場合はマーカー付き用紙なので、輪郭検出による
    // 誤った範囲（表の枠など）での自動撮影を避け、4隅そろうのを待つ
    if (!corners && !markerHint) {
        corners = findDocumentCorners(small, DETECT_WIDTH, detectHeight);
    }

    // 3) フォールバック: jscanifyの最大輪郭方式（面積・明るさフィルタ付き）
    if (!corners && !markerHint) {
        let mat = null;
        let contour = null;
        try {
            mat = window.cv.imread(small);
            contour = scanner.findPaperContour(mat);
            if (contour) {
                const c = scanner.getCornerPoints(contour);
                if (c && c.topLeftCorner && c.topRightCorner && c.bottomLeftCorner && c.bottomRightCorner
                    && quadArea(c) > DETECT_WIDTH * detectHeight * MIN_AREA_RATIO) {
                    const imageData = small.getContext('2d').getImageData(0, 0, DETECT_WIDTH, detectHeight);
                    if (quadMeanBrightness(imageData, c, DETECT_WIDTH) >= MIN_PAPER_BRIGHTNESS) {
                        corners = c;
                    }
                }
            }
        } catch (e) {
            // 検出エラーは次フレームで再試行
        } finally {
            if (contour) contour.delete();
            if (mat) mat.delete();
        }
    }

    drawOverlay(corners, DETECT_WIDTH, detectHeight, markerOutlines);

    if (corners) {
        if (lastCorners && cornersDistance(corners, lastCorners) < STABLE_DIST_THRESHOLD) {
            stableCount.value++;
        } else {
            stableCount.value = 1;
        }
        lastCorners = corners;
        const label = markerHint || '用紙を検出しました';
        detectStatus.value = `${label}（自動撮影まで ${Math.max(0, STABLE_FRAMES_REQUIRED - stableCount.value)}）`;

        if (stableCount.value >= STABLE_FRAMES_REQUIRED) {
            capture(corners, DETECT_WIDTH);
        }
    } else {
        stableCount.value = 0;
        lastCorners = null;
        detectStatus.value = markerHint
            || '用紙全体が映るようにかざしてください（検出できない場合は「手動で四隅指定」）';
    }
}

function cornersDistance(a, b) {
    const keys = ['topLeftCorner', 'topRightCorner', 'bottomLeftCorner', 'bottomRightCorner'];
    return keys.reduce((sum, k) => sum + Math.hypot(a[k].x - b[k].x, a[k].y - b[k].y), 0);
}

function drawOverlay(corners, detectWidth, detectHeight, markerOutlines = []) {
    const overlay = overlayRef.value;
    const video = videoRef.value;
    if (!overlay || !video) return;

    // overlayの実ピクセルを表示サイズに合わせる
    const rect = video.getBoundingClientRect();
    if (overlay.width !== Math.round(rect.width)) overlay.width = Math.round(rect.width);
    if (overlay.height !== Math.round(rect.height)) overlay.height = Math.round(rect.height);

    const ctx = overlay.getContext('2d');
    ctx.clearRect(0, 0, overlay.width, overlay.height);

    const sx = overlay.width / detectWidth;
    const sy = overlay.height / detectHeight;

    // 検出できた個々のマーカーを青枠で表示（診断用・部分検出でも見える）
    for (const outline of markerOutlines) {
        ctx.beginPath();
        outline.forEach((p, i) => {
            i === 0 ? ctx.moveTo(p.x * sx, p.y * sy) : ctx.lineTo(p.x * sx, p.y * sy);
        });
        ctx.closePath();
        ctx.strokeStyle = 'rgb(59, 130, 246)';
        ctx.lineWidth = 2;
        ctx.stroke();
    }

    if (!corners) return;

    const pts = [corners.topLeftCorner, corners.topRightCorner, corners.bottomRightCorner, corners.bottomLeftCorner];

    ctx.beginPath();
    pts.forEach((p, i) => {
        const x = p.x * sx;
        const y = p.y * sy;
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.closePath();
    ctx.fillStyle = 'rgba(34, 197, 94, 0.15)';
    ctx.fill();
    ctx.strokeStyle = 'rgb(34, 197, 94)';
    ctx.lineWidth = 3;
    ctx.stroke();
}

/**
 * 撮影して台形補正 → プレビュー表示
 * @param corners 縮小画像上の四隅（nullなら手動シャッター＝検出なし）
 * @param detectWidth cornersの座標系の幅
 */
function capture(corners, detectWidth) {
    const video = videoRef.value;
    if (!video) return;

    stopDetectLoop();

    // フル解像度スナップショット
    const full = document.createElement('canvas');
    full.width = video.videoWidth;
    full.height = video.videoHeight;
    full.getContext('2d').drawImage(video, 0, 0);
    capturedFrameCanvas = full;

    if (corners) {
        const scale = video.videoWidth / detectWidth;
        const scaled = scaleCorners(corners, scale);
        try {
            const paper = scanner.extractPaper(full, OUTPUT_WIDTH, OUTPUT_HEIGHT, scaled);
            if (paper) {
                previewImgSrc.value = paper.toDataURL('image/jpeg', 0.92);
                phase.value = 'preview';
                return;
            }
        } catch (e) {
            // 補正失敗時は手動指定へ
        }
    }
    enterManualMode();
}

function scaleCorners(corners, scale) {
    const s = (p) => ({ x: p.x * scale, y: p.y * scale });
    return {
        topLeftCorner: s(corners.topLeftCorner),
        topRightCorner: s(corners.topRightCorner),
        bottomLeftCorner: s(corners.bottomLeftCorner),
        bottomRightCorner: s(corners.bottomRightCorner),
    };
}

function manualShutter() {
    // 現在検出中の四隅があればそれを使い、なければ手動指定モードへ
    capture(lastCorners, DETECT_WIDTH);
}

async function enterManualMode() {
    if (!capturedFrameCanvas) return;
    errorMessage.value = '';
    phase.value = 'manual';
    await nextTick();
    // 初期ハンドル位置: 表示領域の10%内側
    const wrap = manualWrapRef.value;
    if (!wrap) return;
    const w = wrap.clientWidth;
    const h = wrap.clientHeight;
    manualHandles.value = [
        { x: w * 0.1, y: h * 0.1 },
        { x: w * 0.9, y: h * 0.1 },
        { x: w * 0.9, y: h * 0.9 },
        { x: w * 0.1, y: h * 0.9 },
    ];
}

function onHandlePointerDown(index, e) {
    draggingHandleIndex = index;
    e.target.setPointerCapture(e.pointerId);
}

function onHandlePointerMove(e) {
    if (draggingHandleIndex < 0) return;
    const wrap = manualWrapRef.value;
    if (!wrap) return;
    const rect = wrap.getBoundingClientRect();
    const x = Math.min(Math.max(e.clientX - rect.left, 0), rect.width);
    const y = Math.min(Math.max(e.clientY - rect.top, 0), rect.height);
    manualHandles.value[draggingHandleIndex] = { x, y };
}

function onHandlePointerUp() {
    draggingHandleIndex = -1;
}

function confirmManualCorners() {
    const wrap = manualWrapRef.value;
    if (!wrap || !capturedFrameCanvas) return;

    const scaleX = capturedFrameCanvas.width / wrap.clientWidth;
    const scaleY = capturedFrameCanvas.height / wrap.clientHeight;
    const [tl, tr, br, bl] = manualHandles.value;
    const corners = {
        topLeftCorner: { x: tl.x * scaleX, y: tl.y * scaleY },
        topRightCorner: { x: tr.x * scaleX, y: tr.y * scaleY },
        bottomRightCorner: { x: br.x * scaleX, y: br.y * scaleY },
        bottomLeftCorner: { x: bl.x * scaleX, y: bl.y * scaleY },
    };

    try {
        const paper = scanner.extractPaper(capturedFrameCanvas, OUTPUT_WIDTH, OUTPUT_HEIGHT, corners);
        if (paper) {
            previewImgSrc.value = paper.toDataURL('image/jpeg', 0.92);
            phase.value = 'preview';
        }
    } catch (e) {
        errorMessage.value = '補正に失敗しました。撮り直してください。';
    }
}

async function retake() {
    previewImgSrc.value = '';
    capturedFrameCanvas = null;
    stableCount.value = 0;
    lastCorners = null;
    markerCache = {};
    phase.value = 'detecting';
    await nextTick();
    // カメラが生きていれば再開、切れていれば再起動
    if (mediaStream && mediaStream.getVideoTracks().some((t) => t.readyState === 'live')) {
        const video = videoRef.value;
        video.srcObject = mediaStream;
        await video.play();
        detectTimer = setInterval(detectFrame, 150);
    } else {
        cleanup();
        await start();
    }
}

function upload() {
    if (!previewImgSrc.value) return;
    phase.value = 'uploading';

    fetch(previewImgSrc.value)
        .then((res) => res.blob())
        .then((blob) => {
            const fd = new FormData();
            fd.append('page', String(page.value));
            fd.append('photo', blob, `questionnaire_page${page.value}.jpg`);
            router.post(
                route('admin.customers.questionnaire.scans.store', props.customer.id),
                fd,
                {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => emit('close'),
                    onError: () => {
                        phase.value = 'preview';
                        errorMessage.value = 'アップロードに失敗しました。再度お試しください。';
                    },
                }
            );
        });
}

function stopDetectLoop() {
    if (detectTimer) {
        clearInterval(detectTimer);
        detectTimer = null;
    }
}

function cleanup() {
    stopDetectLoop();
    if (mediaStream) {
        mediaStream.getTracks().forEach((t) => t.stop());
        mediaStream = null;
    }
    capturedFrameCanvas = null;
    previewImgSrc.value = '';
}

onBeforeUnmount(cleanup);
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-brand-text flex items-center gap-2">
                    <Camera :size="18" class="text-brand-primary" />
                    アンケート用紙スキャン
                </h3>
                <div class="flex items-center gap-2">
                    <select
                        v-model.number="page"
                        class="text-sm border-brand-border rounded-soft py-1"
                        :disabled="phase === 'uploading'"
                    >
                        <option :value="1">1ページ目</option>
                        <option :value="2">2ページ目</option>
                    </select>
                    <button class="text-brand-text-muted hover:text-brand-text" @click="emit('close')">
                        <XIcon :size="20" />
                    </button>
                </div>
            </div>

            <!-- エラー -->
            <div v-if="phase === 'error'" class="p-6 text-center">
                <p class="text-red-600 mb-4">{{ errorMessage }}</p>
                <UiButton variant="ghost" @click="start">再試行</UiButton>
            </div>

            <!-- ローディング -->
            <div v-else-if="phase === 'loading'" class="p-10 text-center text-brand-text-muted">
                <RefreshCw :size="24" class="animate-spin mx-auto mb-3" />
                <p class="text-sm">{{ detectStatus }}</p>
            </div>

            <!-- カメラ検出中 -->
            <div v-show="phase === 'detecting'">
                <div ref="videoWrapRef" class="relative bg-black rounded-lg overflow-hidden">
                    <video ref="videoRef" playsinline muted class="w-full max-h-[60vh] object-contain"></video>
                    <canvas ref="overlayRef" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>
                </div>
                <p class="text-sm text-brand-text-muted text-center mt-2">{{ detectStatus }}</p>
                <p v-if="diagInfo" class="text-[10px] text-brand-text-muted/70 text-center mt-0.5">{{ diagInfo }}</p>
                <div class="flex justify-center gap-3 mt-3">
                    <UiButton variant="primary" @click="manualShutter">
                        <Camera :size="16" /> シャッター
                    </UiButton>
                    <UiButton variant="ghost" @click="capture(null, DETECT_WIDTH)">
                        <Move :size="16" /> 手動で四隅指定
                    </UiButton>
                    <UiButton variant="ghost" @click="emit('close')">キャンセル</UiButton>
                </div>
            </div>

            <!-- 手動四隅指定 -->
            <div v-if="phase === 'manual'">
                <p class="text-sm text-brand-text-muted mb-2 flex items-center gap-1">
                    <Move :size="14" /> 用紙の四隅に合わせて4つの点をドラッグしてください
                </p>
                <div
                    ref="manualWrapRef"
                    class="relative rounded-lg overflow-hidden select-none touch-none"
                    @pointermove="onHandlePointerMove"
                    @pointerup="onHandlePointerUp"
                >
                    <img
                        v-if="capturedFrameCanvas"
                        ref="manualImgRef"
                        :src="capturedFrameCanvas.toDataURL('image/jpeg', 0.7)"
                        class="w-full max-h-[60vh] object-contain block"
                    >
                    <svg v-if="manualHandles.length === 4" class="absolute inset-0 w-full h-full pointer-events-none">
                        <polygon
                            :points="manualHandles.map(h => `${h.x},${h.y}`).join(' ')"
                            fill="rgba(34,197,94,0.12)"
                            stroke="rgb(34,197,94)"
                            stroke-width="2"
                        />
                    </svg>
                    <div
                        v-for="(h, i) in manualHandles"
                        :key="i"
                        class="absolute w-6 h-6 -ml-3 -mt-3 rounded-full bg-green-500 border-2 border-white shadow cursor-move"
                        :style="{ left: h.x + 'px', top: h.y + 'px' }"
                        @pointerdown="onHandlePointerDown(i, $event)"
                    ></div>
                </div>
                <div class="flex justify-center gap-3 mt-3">
                    <UiButton variant="primary" @click="confirmManualCorners">
                        <Check :size="16" /> この範囲で補正
                    </UiButton>
                    <UiButton variant="ghost" @click="retake">撮り直す</UiButton>
                </div>
            </div>

            <!-- プレビュー -->
            <div v-if="phase === 'preview' || phase === 'uploading'">
                <div class="bg-brand-surface-2 rounded-lg p-2 flex justify-center">
                    <img :src="previewImgSrc" class="max-h-[60vh] rounded shadow" alt="補正後プレビュー">
                </div>
                <p v-if="errorMessage" class="text-sm text-red-600 text-center mt-2">{{ errorMessage }}</p>
                <div class="flex justify-center gap-3 mt-3">
                    <UiButton variant="primary" :loading="phase === 'uploading'" @click="upload">
                        <Check :size="16" /> {{ pageLabel }}として取り込む
                    </UiButton>
                    <UiButton variant="ghost" :disabled="phase === 'uploading'" @click="enterManualMode">
                        <Move :size="16" /> 範囲を手動調整
                    </UiButton>
                    <UiButton variant="ghost" :disabled="phase === 'uploading'" @click="retake">
                        <RefreshCw :size="16" /> 撮り直す
                    </UiButton>
                </div>
            </div>
        </div>
    </Modal>
</template>
