<template>
    <Head title="S3 テスト" />

    <div class="min-h-screen bg-gray-50 py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">S3 テスト</h1>
                <Link
                    :href="route('login')"
                    class="text-indigo-600 hover:text-indigo-800 text-sm"
                >
                    ログインへ
                </Link>
            </div>

            <!-- 注意書き（方式B） -->
            <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                方式B（public）は <code class="bg-amber-100 px-1 rounded">public/</code> プレフィックスへ保存し、バケットポリシーで公開しているため誰でも固定URLでアクセスできます。方式Aは署名URLでのみ表示されます。
            </div>

            <!-- アップロードフォーム -->
            <div class="bg-white shadow-sm rounded-lg p-6 mb-8">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">アップロード</h2>
                <form @submit.prevent="submit" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ファイル</label>
                        <input
                            ref="fileInput"
                            type="file"
                            required
                            @change="form.file = $event.target.files?.[0] || null"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        <p class="mt-1 text-xs text-gray-500">最大 10MB</p>
                        <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">{{ form.errors.file }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">保存方式</label>
                        <div class="flex gap-6">
                            <label class="inline-flex items-center">
                                <input
                                    v-model="form.visibility_type"
                                    type="radio"
                                    value="private"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="ml-2 text-sm text-gray-700">A. 非公開 + 署名URL（おすすめ）</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input
                                    v-model="form.visibility_type"
                                    type="radio"
                                    value="public"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="ml-2 text-sm text-gray-700">B. public 配下で公開（LP画像等）</span>
                            </label>
                        </div>
                        <p v-if="form.errors.visibility_type" class="mt-1 text-sm text-red-600">{{ form.errors.visibility_type }}</p>
                    </div>
                    <div>
                        <button
                            type="submit"
                            :disabled="form.processing || !form.file"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-400 text-sm font-medium"
                        >
                            {{ form.processing ? 'アップロード中...' : 'アップロード' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- 成功メッセージ -->
            <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 p-4 text-sm text-green-800">
                {{ $page.props.flash.success }}
            </div>

            <!-- 一覧 -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <h2 class="text-lg font-semibold text-gray-800 px-6 py-4 border-b border-gray-200">アップロード一覧</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">サムネイル</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ファイル名</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">方式</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">アップロード日時</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <template v-if="isImage(item)">
                                        <img
                                            v-if="item.thumbnail_url"
                                            :src="item.thumbnail_url"
                                            :alt="item.original_name || item.path"
                                            class="w-14 h-14 object-cover rounded border border-gray-200 bg-gray-50"
                                            loading="lazy"
                                            @error="($e) => $e.target.style.display = 'none'"
                                        />
                                        <span v-else class="inline-flex w-14 h-14 items-center justify-center rounded border border-gray-200 bg-gray-100 text-gray-400">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14" />
                                            </svg>
                                        </span>
                                    </template>
                                    <span v-else class="inline-flex w-14 h-14 items-center justify-center rounded border border-gray-200 bg-gray-100 text-gray-400">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ item.original_name || item.path }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'inline-flex px-2 py-0.5 text-xs font-medium rounded-full',
                                            item.visibility_type === 'private'
                                                ? 'bg-gray-100 text-gray-800'
                                                : 'bg-green-100 text-green-800'
                                        ]"
                                    >
                                        {{ item.visibility_type === 'private' ? 'A. 非公開' : 'B. 公開' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDate(item.created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <template v-if="item.visibility_type === 'private'">
                                        <button
                                            type="button"
                                            :disabled="loadingSignedUrl === item.id"
                                            @click="openSignedUrl(item)"
                                            class="text-indigo-600 hover:text-indigo-800 disabled:opacity-50"
                                        >
                                            {{ loadingSignedUrl === item.id ? '取得中...' : '表示（署名URL）' }}
                                        </button>
                                    </template>
                                    <template v-else>
                                        <a
                                            v-if="item.url"
                                            :href="item.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-indigo-600 hover:text-indigo-800"
                                        >
                                            開く（固定URL）
                                        </a>
                                        <span v-else class="text-gray-400">-</span>
                                    </template>
                                </td>
                            </tr>
                            <tr v-if="items.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                                    まだアップロードされていません
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
});

const fileInput = ref(null);
const loadingSignedUrl = ref(null);

const form = useForm({
    file: null,
    visibility_type: 'private',
});

const submit = () => {
    form.post(route('s3-test.store'), {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

function formatDate(iso) {
    if (!iso) return '-';
    const d = new Date(iso);
    return d.toLocaleString('ja-JP');
}

const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
function isImage(item) {
    const name = (item.original_name || item.path || '').toLowerCase();
    const ext = name.split('.').pop();
    return imageExtensions.includes(ext);
}

async function openSignedUrl(item) {
    loadingSignedUrl.value = item.id;
    try {
        const disk = item.visibility_type === 'public' ? 's3_public' : 's3';
        const { data } = await axios.get(route('s3-test.signed-url'), {
            params: { path: item.path, disk },
        });
        if (data.url) {
            window.open(data.url, '_blank', 'noopener,noreferrer');
        }
    } catch (e) {
        console.error(e);
        alert('署名URLの取得に失敗しました。');
    } finally {
        loadingSignedUrl.value = null;
    }
}
</script>
