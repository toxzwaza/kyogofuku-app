import { reactive } from 'vue';

const state = reactive({
    toasts: [], // { id, variant, title, message, duration }
});

let seq = 0;

const remove = (id) => {
    const idx = state.toasts.findIndex((t) => t.id === id);
    if (idx >= 0) state.toasts.splice(idx, 1);
};

const push = ({ variant = 'info', title = '', message = '', duration = 4000 } = {}) => {
    const id = ++seq;
    state.toasts.push({ id, variant, title, message, duration });
    if (duration > 0) {
        setTimeout(() => remove(id), duration);
    }
    return id;
};

export function useToast() {
    return {
        state,
        push,
        remove,
        info:    (message, opts = {}) => push({ ...opts, variant: 'info', message }),
        success: (message, opts = {}) => push({ ...opts, variant: 'success', message }),
        warning: (message, opts = {}) => push({ ...opts, variant: 'warning', message }),
        danger:  (message, opts = {}) => push({ ...opts, variant: 'danger', message }),
    };
}
