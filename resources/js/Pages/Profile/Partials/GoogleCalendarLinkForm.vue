<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    authGoogleUrl: {
        type: String,
        required: true,
    },
    googleCalendarRefreshToken: {
        type: String,
        default: null,
    },
    googleAuthError: {
        type: String,
        default: null,
    },
    googleRedirectUri: {
        type: String,
        default: null,
    },
});

const tokenCopied = ref(false);

const copyToken = async () => {
    if (!props.googleCalendarRefreshToken) return;
    try {
        await navigator.clipboard.writeText(props.googleCalendarRefreshToken);
        tokenCopied.value = true;
        setTimeout(() => {
            tokenCopied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

watch(() => props.googleCalendarRefreshToken, (val) => {
    if (val) {
        tokenCopied.value = false;
    }
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Google カレンダー連携</h2>

            <p class="mt-1 text-sm text-gray-600">
                スケジュールを店舗の Google カレンダーに同期するには、refresh_token が必要です。
                以下の手順で取得し、<code class="px-1 py-0.5 bg-gray-100 rounded text-xs">.env</code> の <code class="px-1 py-0.5 bg-gray-100 rounded text-xs">GOOGLE_CALENDAR_REFRESH_TOKEN</code> に設定してください。
                <template v-if="googleRedirectUri">
                    <span class="mt-2 block">事前に次の URI を Google Cloud Console の認証情報のリダイレクト URI に追加してください。</span>
                    <code class="mt-1 block px-2 py-1 bg-gray-100 rounded text-xs break-all">{{ googleRedirectUri }}</code>
                </template>
            </p>
        </header>

        <div class="mt-6 space-y-4">
            <!-- エラー表示 -->
            <div
                v-if="googleAuthError"
                class="rounded-md bg-red-50 p-4 text-sm text-red-700"
            >
                {{ googleAuthError }}
            </div>

            <!-- 連携成功：トークン表示 -->
            <div
                v-if="googleCalendarRefreshToken"
                class="rounded-md bg-green-50 p-4 space-y-3"
            >
                <p class="text-sm font-medium text-green-800">連携に成功しました</p>
                <p class="text-sm text-green-700">
                    以下の refresh_token をコピーし、<code class="px-1 py-0.5 bg-green-100 rounded">.env</code> の <code class="px-1 py-0.5 bg-green-100 rounded">GOOGLE_CALENDAR_REFRESH_TOKEN=</code> の値に設定してください。
                    設定後、アプリケーションを再起動してください。
                </p>
                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        :value="googleCalendarRefreshToken"
                        readonly
                        class="flex-1 rounded-md border-gray-300 text-sm font-mono"
                    />
                    <SecondaryButton type="button" @click="copyToken">
                        {{ tokenCopied ? 'コピーしました' : 'コピー' }}
                    </SecondaryButton>
                </div>
            </div>

            <!-- 連携ボタン -->
            <div v-else>
                <a :href="authGoogleUrl">
                    <PrimaryButton type="button">
                        Google アカウントで連携する
                    </PrimaryButton>
                </a>
            </div>
        </div>
    </section>
</template>
