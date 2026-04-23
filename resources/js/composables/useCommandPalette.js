import { ref, onMounted, onUnmounted } from 'vue';

const open = ref(false);

const toggle = () => { open.value = !open.value; };
const show   = () => { open.value = true; };
const hide   = () => { open.value = false; };

const onKeydown = (e) => {
    // Cmd+K / Ctrl+K
    if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        toggle();
    } else if (e.key === 'Escape' && open.value) {
        hide();
    }
};

let listenerBound = false;

export function useCommandPalette() {
    onMounted(() => {
        if (!listenerBound) {
            window.addEventListener('keydown', onKeydown);
            listenerBound = true;
        }
    });
    onUnmounted(() => {
        // listenerBound は module scope で共有しているため、あえて remove しない
        // （1ページ内で複数 useCommandPalette() された場合も1回だけバインド）
    });
    return { open, show, hide, toggle };
}
