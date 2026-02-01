<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    googleCalendarConnected: Boolean,
    status: String,
});

function connectGoogleCalendar() {
    window.location.href = route('google.redirect');
}

function disconnectGoogleCalendar() {
    if (confirm('Google カレンダーとの連携を解除しますか？')) {
        router.post(route('google.disconnect'));
    }
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Google カレンダー連携</h2>

            <p class="mt-1 text-sm text-gray-600">
                同一 Google アカウントで店舗カレンダーへの同期を有効にします。参加者として含まれるスケジュールが、所属店舗の Google カレンダーにリアルタイムで反映されます。
            </p>
        </header>

        <div class="mt-6 space-y-4">
            <div
                v-if="status"
                class="rounded-md p-4 text-sm"
                :class="status.includes('失敗') ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'"
            >
                {{ status }}
            </div>

            <div v-if="googleCalendarConnected" class="flex items-center gap-4">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    連携済み
                </span>
                <button
                    type="button"
                    @click="disconnectGoogleCalendar"
                    class="text-sm text-gray-600 hover:text-gray-900 underline focus:outline-none"
                >
                    連携を解除
                </button>
            </div>

            <div v-else>
                <PrimaryButton type="button" @click="connectGoogleCalendar">
                    Google カレンダーと連携
                </PrimaryButton>
            </div>
        </div>
    </section>
</template>
