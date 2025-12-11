<template>
    <Head :title="document.name" />

    <div class="document-wrapper">
        <h1 class="document-title">{{ document.name }}</h1>
        <p v-if="document.description" class="document-description">{{ document.description }}</p>

        <div v-if="loading" class="loading-container">
            <div class="loading-spinner"></div>
            <p>読み込み中...</p>
        </div>

        <div v-else-if="errorMessage" class="error-container">
            <p>{{ errorMessage }}</p>
        </div>

        <div v-else-if="!pdfUrl" class="error-container">
            <p>PDFファイルが見つかりません</p>
        </div>

        <!-- PDF表示 -->
        <div v-else class="pdf-container">
            <iframe
                :src="pdfUrl"
                class="pdf-viewer"
                frameborder="0"
            ></iframe>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    document: Object,
});

const loading = ref(false);
const errorMessage = ref('');

// PDFのURLを取得
const pdfUrl = computed(() => {
    if (!props.document || !props.document.pdf_path) {
        return null;
    }
    
    // 既にURLの場合はそのまま返す
    if (props.document.pdf_path.startsWith('http')) {
        return props.document.pdf_path;
    }
    
    // 相対パスの場合はassetヘルパーでURLを生成
    return `/storage/${props.document.pdf_path}`;
});
</script>

<style scoped>
.document-wrapper {
    width: 100%;
    min-height: 100vh;
    padding: 2rem 1rem;
    background: linear-gradient(140deg, #eef1f5 0%, #d6dae1 100%);
}

.document-title {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 1rem;
    color: #2e3a59;
    text-align: center;
}

.document-description {
    font-size: 1.1rem;
    color: #6b7280;
    text-align: center;
    margin-bottom: 2rem;
}

.pdf-container {
    width: 100%;
    height: calc(100vh - 200px);
    margin: 0 auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.pdf-viewer {
    width: 100%;
    height: 100%;
    border: none;
}

/* ローディング */
.loading-container,
.error-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
}

.loading-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #d1d5db;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.error-container p {
    color: #ef4444;
    font-size: 1.1rem;
}
</style>
