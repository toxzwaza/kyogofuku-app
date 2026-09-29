import { watch, onUnmounted } from 'vue';

/**
 * 指定した ref が true の間、body のスクロールを固定する。
 * 検索モーダルを開いている間に背後の一覧がスクロールしてしまうのを防ぐ。
 *
 * 使い方:
 *   const open = ref(false);
 *   useScrollLock(open);
 */
export function useScrollLock(isLocked) {
    const apply = (locked) => {
        if (typeof document === 'undefined') return;
        document.body.style.overflow = locked ? 'hidden' : '';
    };
    watch(isLocked, apply);
    onUnmounted(() => apply(false));
}
