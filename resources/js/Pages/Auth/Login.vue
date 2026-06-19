<template>
    <GuestLayout>
        <Head title="ログイン" />

        <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 px-4 sm:px-6 lg:px-8 py-8">
            <div class="max-w-2xl w-full space-y-6">
                <!-- ロゴ -->
                <div class="text-center">
                    <div class="flex justify-center mb-4">
                        <img 
                            src="/storage/logo/logo_b.png" 
                            alt="ロゴ" 
                            class="h-16 w-auto object-contain"
                        />
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-1">ログイン</h2>
                    <p class="text-xs text-gray-600">管理画面にアクセスするにはログインしてください</p>
                </div>

                <!-- ブロックメッセージ -->
                <div v-if="blocked" class="bg-white rounded-2xl shadow-xl p-8">
                    <div class="text-center">
                        <div class="flex justify-center mb-4">
                            <svg class="h-16 w-16 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">アクセスが制限されています</h3>
                        <p class="text-gray-600 mb-4">
                            ログイン失敗回数が上限を上回りました。管理者が確認するまで少々お待ちください。
                        </p>
                        <div class="text-sm text-gray-500">
                            <p>失敗回数: {{ failureCount }}回</p>
                            <p class="mt-2">IPアドレス: {{ ipAddress || '不明' }}</p>
                        </div>
                    </div>
                </div>

                <!-- ステータスメッセージ -->
                <div v-if="!blocked && status" class="rounded-md bg-green-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ status }}</p>
                        </div>
                    </div>
                </div>

                <!-- ログインフォーム -->
                <div v-if="!blocked" class="bg-white rounded-2xl shadow-xl p-6 space-y-5">
                    <form @submit.prevent="submit" class="space-y-5">
                        <!-- 店舗 -->
                        <div>
                            <label for="shop_id" class="block text-sm font-medium text-gray-700 mb-2">
                                店舗
                            </label>
                            <select
                                id="shop_id"
                                v-model="form.shop_id"
                                required
                                autofocus
                                class="block w-full pl-3 pr-10 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200"
                            >
                                <option value="" disabled>店舗を選択してください</option>
                                <option
                                    v-for="shop in shops"
                                    :key="shop.id"
                                    :value="String(shop.id)"
                                >
                                    {{ shop.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.shop_id" />
                        </div>

                        <!-- ユーザー -->
                        <div>
                            <label for="user_id" class="block text-sm font-medium text-gray-700 mb-2">
                                ユーザー
                            </label>
                            <select
                                id="user_id"
                                v-model="form.user_id"
                                required
                                class="block w-full pl-3 pr-10 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200"
                            >
                                <option value="" disabled>ユーザーを選択してください</option>
                                <option
                                    v-for="user in selectedShopUsers"
                                    :key="user.id"
                                    :value="String(user.id)"
                                >
                                    {{ user.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.user_id" />
                        </div>

                        <!-- 端末登録パスワード（端末ゲート有効 ＆ 未登録端末のみ表示） -->
                        <div v-if="deviceGateEnabled && !deviceRegistered">
                            <label for="device_password" class="block text-sm font-medium text-gray-700 mb-2">
                                端末登録パスワード
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <input
                                    id="device_password"
                                    type="password"
                                    v-model="devicePassword"
                                    autocomplete="off"
                                    class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200"
                                    placeholder="この端末を登録するためのパスワード"
                                />
                            </div>
                            <p class="mt-1.5 text-xs text-gray-500">この端末は未登録です。店舗の端末登録パスワードを入力すると、次回以降は入力不要になります。</p>
                            <InputError class="mt-2" :message="deviceError" />
                        </div>

                        <!-- 登録済み端末の表示 -->
                        <div v-else-if="deviceGateEnabled && deviceRegistered" class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3">
                            <div class="flex items-center gap-2 text-sm text-emerald-800">
                                <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>この端末は登録済みです（端末ID: <span class="font-mono font-semibold">{{ deviceCode }}</span>）</span>
                            </div>
                        </div>
                        <InputError v-if="deviceGateEnabled" class="mt-2" :message="form.errors.device_token" />

                        <!-- パスワード（SECURITY_LOGIN=true の場合のみ表示） -->
                        <div v-if="securityLogin">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                パスワード
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <input
                                    id="password"
                                    type="password"
                                    v-model="form.password"
                                    required
                                    autocomplete="current-password"
                                    class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200"
                                    placeholder="パスワードを入力"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <!-- ログイン状態を保持 -->
                        <div class="flex items-center">
                            <input
                                id="remember"
                                type="checkbox"
                                v-model="form.remember"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            />
                            <label for="remember" class="ml-2 block text-sm text-gray-700">
                                ログイン状態を保持する
                            </label>
                        </div>

                        <!-- パスワードリセットリンク（パスワード認証時のみ表示） -->
                        <div v-if="securityLogin && canResetPassword" class="text-sm text-right">
                            <Link
                                :href="route('password.request')"
                                class="font-medium text-indigo-600 hover:text-indigo-500 transition-colors duration-200"
                            >
                                パスワードをお忘れですか？
                            </Link>
                        </div>

                        <!-- ログインボタン -->
                        <div>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98]"
                            >
                                <span v-if="form.processing" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    ログイン中...
                                </span>
                                <span v-else class="flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                    </svg>
                                    ログイン
                                </span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- フッター -->
                <div class="text-center text-sm text-gray-500">
                    <p>&copy; {{ new Date().getFullYear() }} 京呉服 好一. All rights reserved.</p>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>

<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';

const page = usePage();

// Inertia の page.props から取得し、必ず配列に正規化する
const shops = computed(() => {
    const raw = page.props.shops;
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object' && !Array.isArray(raw)) return Object.values(raw);
    return [];
});

const canResetPassword = computed(() => !!page.props.canResetPassword);
const status = computed(() => page.props.status ?? null);
const blocked = computed(() => !!page.props.blocked);
const failureCount = computed(() => Number(page.props.failureCount) || 0);
const ipAddress = computed(() => page.props.ipAddress ?? null);
const securityLogin = computed(() => page.props.securityLogin !== false);
const deviceGateEnabled = computed(() => page.props.deviceGateEnabled === true);

const form = useForm({
    shop_id: '',
    user_id: '',
    password: '',
    device_token: '',
    remember: false,
});

// 端末ゲート用の状態
const DEVICE_TOKEN_KEY = 'kyogofuku_device_token';
const DEVICE_CODE_KEY = 'kyogofuku_device_code';
const deviceToken = ref('');
const deviceCode = ref('');
const deviceRegistered = ref(false);
const devicePassword = ref('');
const deviceError = ref('');

async function checkDeviceStatus() {
    if (!deviceGateEnabled.value) return;
    let token = '';
    try {
        token = localStorage.getItem(DEVICE_TOKEN_KEY) || '';
    } catch {
        token = '';
    }
    if (!token) {
        deviceRegistered.value = false;
        return;
    }
    try {
        const { data } = await axios.get(route('device.status'), { params: { device_token: token } });
        if (data.registered) {
            deviceRegistered.value = true;
            deviceToken.value = token;
            deviceCode.value = data.device_code;
            form.device_token = token;
        } else {
            // 解除済み等で無効 → ローカルを掃除して再登録を促す
            deviceRegistered.value = false;
            deviceToken.value = '';
            try {
                localStorage.removeItem(DEVICE_TOKEN_KEY);
                localStorage.removeItem(DEVICE_CODE_KEY);
            } catch {
                // 無視
            }
        }
    } catch {
        deviceRegistered.value = false;
    }
}

const selectedShopUsers = computed(() => {
    if (!form.shop_id) return [];
    const shop = shops.value.find((s) => Number(s.id) === Number(form.shop_id));
    const users = shop?.users;
    if (Array.isArray(users)) return users;
    if (users && typeof users === 'object') return Object.values(users);
    return [];
});

// 店舗変更時、選択中のユーザーがその店舗にいなければ user_id をクリア
watch(
    () => form.shop_id,
    (newShopId) => {
        const shop = shops.value.find((s) => Number(s.id) === Number(newShopId));
        const users = shop?.users;
        const userIds = Array.isArray(users) ? users.map((u) => Number(u.id)) : [];
        if (form.user_id && !userIds.includes(Number(form.user_id))) {
            form.user_id = '';
        }
    }
);

const STORAGE_KEY = 'kyogofuku_last_login';

onMounted(() => {
    checkDeviceStatus();
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        const saved = JSON.parse(raw);
        const shopId = saved.shop_id != null ? Number(saved.shop_id) : null;
        const userId = saved.user_id != null ? Number(saved.user_id) : null;
        if (shopId == null || userId == null) return;
        const shop = shops.value.find((s) => Number(s.id) === shopId);
        const users = shop?.users;
        const userExists = Array.isArray(users) && users.some((u) => Number(u.id) === userId);
        if (shop && userExists) {
            form.shop_id = String(shopId);
            form.user_id = String(userId);
        }
    } catch {
        // 無効なJSONやデータは無視
    }
});

const submit = async () => {
    // 端末ゲート有効＆未登録の場合は、先に端末登録（店舗パスワード）を行う
    if (deviceGateEnabled.value && !deviceRegistered.value) {
        deviceError.value = '';
        if (!form.shop_id) {
            deviceError.value = '店舗を選択してください。';
            return;
        }
        if (!devicePassword.value) {
            deviceError.value = '端末登録パスワードを入力してください。';
            return;
        }
        try {
            const { data } = await axios.post(route('device.register'), {
                shop_id: form.shop_id,
                password: devicePassword.value,
                user_id: form.user_id || null,
            });
            if (data.success) {
                deviceToken.value = data.device_token;
                deviceCode.value = data.device_code;
                deviceRegistered.value = true;
                form.device_token = data.device_token;
                devicePassword.value = '';
                try {
                    localStorage.setItem(DEVICE_TOKEN_KEY, data.device_token);
                    localStorage.setItem(DEVICE_CODE_KEY, data.device_code);
                } catch {
                    // localStorage が使えない場合は無視
                }
            } else {
                deviceError.value = data.message || '端末登録に失敗しました。';
                return;
            }
        } catch (e) {
            deviceError.value = e.response?.data?.message || '端末登録に失敗しました。';
            return;
        }
    } else if (deviceGateEnabled.value) {
        form.device_token = deviceToken.value || '';
    }

    if (form.shop_id && form.user_id) {
        try {
            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({
                    shop_id: form.shop_id,
                    user_id: form.user_id,
                })
            );
        } catch {
            // localStorage が使えない場合は無視
        }
    }
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>
