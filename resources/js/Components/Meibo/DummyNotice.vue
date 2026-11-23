<template>
    <UiAlert v-if="show" variant="warning" class="mb-4">
        いまは画面の確認用のダミーデータです（名前・住所・電話はすべて架空）。変更はこのタブを開いている間だけ残ります。本物の名簿につなぐのは次の段階です。
    </UiAlert>
</template>

<script setup>
import { UiAlert } from '@/Components/UI';

defineProps({
    show: { type: Boolean, default: false },
});
</script>
