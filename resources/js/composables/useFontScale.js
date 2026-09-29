import { ref, onMounted } from 'vue';

/**
 * 文字サイズ切替（アクセシビリティ）。
 *
 * html のルートフォントサイズを変更する。Tailwind は rem ベースのため、
 * これ1つで UI 全体（文字・余白・ボタン）が一括で拡大／縮小する。
 * 年配のスタッフが自分で読みやすい大きさに調整できるようにするのが目的。
 */
const STORAGE_KEY = 'kyogofuku-font-scale';

// 標準=16px（ブラウザ既定）／大きめ=18px／特大=20px
export const FONT_LEVELS = [
    { key: 'normal', label: '標準', size: '16px' },
    { key: 'large',  label: '大きめ', size: '18px' },
    { key: 'xlarge', label: '特大', size: '20px' },
];

const scale = ref('normal');

const sizeOf = (key) => (FONT_LEVELS.find((l) => l.key === key) || FONT_LEVELS[0]).size;

const apply = () => {
    if (typeof document === 'undefined') return;
    document.documentElement.style.fontSize = sizeOf(scale.value);
};

const setScale = (key) => {
    scale.value = FONT_LEVELS.some((l) => l.key === key) ? key : 'normal';
    try { localStorage.setItem(STORAGE_KEY, scale.value); } catch (e) { /* ignore */ }
    apply();
};

const init = () => {
    const saved = typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null;
    if (saved && FONT_LEVELS.some((l) => l.key === saved)) {
        scale.value = saved;
    }
    apply();
};

export function useFontScale() {
    onMounted(init);
    return { scale, setScale, levels: FONT_LEVELS };
}
