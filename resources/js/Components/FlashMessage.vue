<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const showSuccess = ref(false);
const showError = ref(false);

// フラッシュメッセージが変更されたときに表示状態を更新
watch(flashSuccess, (newVal) => {
    if (newVal) {
        showSuccess.value = true;
        // 5秒後に自動的に非表示にする
        setTimeout(() => {
            showSuccess.value = false;
        }, 5000);
    }
});

watch(flashError, (newVal) => {
    if (newVal) {
        showError.value = true;
        // 5秒後に自動的に非表示にする
        setTimeout(() => {
            showError.value = false;
        }, 5000);
    }
});

// 初期表示時にもチェック
if (flashSuccess.value) {
    showSuccess.value = true;
    setTimeout(() => {
        showSuccess.value = false;
    }, 5000);
}

if (flashError.value) {
    showError.value = true;
    setTimeout(() => {
        showError.value = false;
    }, 5000);
}
</script>

<template>
    <div v-if="(showSuccess && flashSuccess) || (showError && flashError)" class="fixed top-4 right-4 z-50 max-w-md w-full">
        <!-- 成功メッセージ -->
        <transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 translate-x-full"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-full"
        >
            <div
                v-if="showSuccess && flashSuccess"
                class="mb-4 rounded-md bg-green-50 p-4 shadow-lg border border-green-200"
            >
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg
                            class="h-5 w-5 text-green-400"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-green-800">
                            {{ flashSuccess }}
                        </p>
                    </div>
                    <div class="ml-4 flex-shrink-0">
                        <button
                            @click="showSuccess = false"
                            class="inline-flex text-green-400 hover:text-green-500 focus:outline-none"
                        >
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- エラーメッセージ -->
        <transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 translate-x-full"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-full"
        >
            <div
                v-if="showError && flashError"
                class="mb-4 rounded-md bg-red-50 p-4 shadow-lg border border-red-200"
            >
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg
                            class="h-5 w-5 text-red-400"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-red-800">
                            {{ flashError }}
                        </p>
                    </div>
                    <div class="ml-4 flex-shrink-0">
                        <button
                            @click="showError = false"
                            class="inline-flex text-red-400 hover:text-red-500 focus:outline-none"
                        >
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>


