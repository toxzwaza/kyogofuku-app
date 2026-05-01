import { ref, computed } from 'vue';

const STORAGE_KEY = 'ui_version';
const COOKIE_KEY = 'ui_version';
const COOKIE_MAX_AGE_DAYS = 365;

const VALID_VERSIONS = ['modern', 'legacy'];

function readCookie(name) {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
}

function writeCookie(name, value) {
    if (typeof document === 'undefined') return;
    const maxAge = COOKIE_MAX_AGE_DAYS * 24 * 60 * 60;
    document.cookie = `${name}=${encodeURIComponent(value)}; Path=/; Max-Age=${maxAge}; SameSite=Lax`;
}

function readStorage() {
    if (typeof localStorage === 'undefined') return null;
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch (e) {
        return null;
    }
}

function writeStorage(value) {
    if (typeof localStorage === 'undefined') return;
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch (e) {
        // ignore (private mode等)
    }
}

function normalize(value) {
    return VALID_VERSIONS.includes(value) ? value : 'modern';
}

const currentVersion = ref(normalize(readStorage() || readCookie(COOKIE_KEY) || 'modern'));

export function useUiVersion() {
    const isLegacy = computed(() => currentVersion.value === 'legacy');
    const isModern = computed(() => currentVersion.value === 'modern');

    const setVersion = (version) => {
        const next = normalize(version);
        currentVersion.value = next;
        writeStorage(next);
        writeCookie(COOKIE_KEY, next);
    };

    const toggle = () => {
        setVersion(isLegacy.value ? 'modern' : 'legacy');
    };

    /** 端末側のlocalStorage / Cookieを正としてサーバ側Cookieを同期する（初回ロード時に呼び出す） */
    const syncCookieFromStorage = () => {
        const stored = readStorage();
        const cookied = readCookie(COOKIE_KEY);
        if (stored && stored !== cookied) {
            writeCookie(COOKIE_KEY, normalize(stored));
        } else if (!cookied) {
            writeCookie(COOKIE_KEY, currentVersion.value);
        }
    };

    return {
        currentVersion,
        isLegacy,
        isModern,
        setVersion,
        toggle,
        syncCookieFromStorage,
    };
}
