import { ref, onMounted } from 'vue';

// ダークモードは廃止し、ライト固定とする。
// 過去に保存されたダーク設定が残っていても、マウント時に必ずライトへ戻す。
const STORAGE_KEY = 'kyogofuku-theme';
const isDark = ref(false);

const forceLight = () => {
    if (typeof document !== 'undefined') {
        document.documentElement.classList.remove('dark');
    }
    try { localStorage.removeItem(STORAGE_KEY); } catch (e) { /* ignore */ }
};

// API互換のため関数は残すが、トグルは無効（常にライト）。
const toggle = () => {
    isDark.value = false;
    forceLight();
};

export function useDarkMode() {
    onMounted(() => {
        isDark.value = false;
        forceLight();
    });
    return { isDark, toggle };
}
