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
                        <!-- ログインID/メールアドレス -->
                        <div>
                            <label for="login" class="block text-sm font-medium text-gray-700 mb-2">
                                メールアドレスまたはログインID
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <input
                                    id="login"
                                    type="text"
                                    v-model="form.login"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200"
                                    placeholder="メールアドレスまたはログインIDを入力"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.login" />
                        </div>

                        <!-- パスワード -->
                        <div>
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

                        <!-- パスワードリセットリンク -->
                        <div v-if="canResetPassword" class="text-sm text-right">
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
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
    blocked: {
        type: Boolean,
        default: false,
    },
    failureCount: {
        type: Number,
        default: 0,
    },
    ipAddress: {
        type: String,
        default: null,
    },
});

const form = useForm({
    login: '', // emailまたはlogin_id
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>
