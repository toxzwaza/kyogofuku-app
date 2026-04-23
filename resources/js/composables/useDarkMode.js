import { ref, onMounted } from 'vue';

const STORAGE_KEY = 'kyogofuku-theme';
const isDark = ref(false);

const apply = () => {
    if (typeof document === 'undefined') return;
    document.documentElement.classList.toggle('dark', isDark.value);
};

const init = () => {
    const saved = typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null;
    if (saved === 'dark' || saved === 'light') {
        isDark.value = saved === 'dark';
    } else if (typeof window !== 'undefined' && window.matchMedia) {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    apply();
};

const toggle = () => {
    isDark.value = !isDark.value;
    try {
        localStorage.setItem(STORAGE_KEY, isDark.value ? 'dark' : 'light');
    } catch (e) { /* ignore */ }
    apply();
};

export function useDarkMode() {
    onMounted(() => {
        init();
    });
    return { isDark, toggle };
}
