<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Users, Camera, Ticket, MessageCircle, BookOpen } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiCard, UiPageHeader } from '@/Components/UI';

defineProps({
    currentUser: { type: Object, default: null },
});

const manuals = [
    {
        key: 'customer',
        title: '顧客マニュアル',
        description: 'お客様の検索・登録・編集、メモやタグ、制約（同意書）、メールスレッドのご案内まで。',
        route: 'admin.manuals.customer',
        icon: Users,
    },
    {
        key: 'photo-slot',
        title: '前撮りマニュアル',
        description: '前撮りの撮影枠を登録し、お客様を割り当てる手順。Googleカレンダーへの自動反映もご案内します。',
        route: 'admin.manuals.photo-slot',
        icon: Camera,
    },
    {
        key: 'event',
        title: 'イベント・予約マニュアル',
        description: 'イベントの作成と予約者の確認・編集・CSV出力までを一連の流れでまとめています。',
        route: 'admin.manuals.event',
        icon: Ticket,
    },
    {
        key: 'peripheral',
        title: 'LINE・タグ・制約・スライドショー',
        description: 'お客様対応を補助する周辺機能（LINE連携、顧客タグ、制約テンプレート、スライドショー）の使い方。',
        route: 'admin.manuals.peripheral',
        icon: MessageCircle,
    },
];
</script>

<template>
    <Head title="業務マニュアル" />
    <AdminLayout :breadcrumb="[{ label: '業務マニュアル' }]">
        <UiPageHeader
            title="業務マニュアル"
            description="本システムの主な業務（顧客・前撮り・イベント予約・LINE連携など）の使い方をまとめています。最初は本資料を見ながらご一緒に操作し、慣れてきましたらこちらをご参考にお進めください。"
        >
            <template #icon>
                <BookOpen :size="24" />
            </template>
        </UiPageHeader>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <Link
                v-for="m in manuals"
                :key="m.key"
                :href="route(m.route)"
                class="block"
            >
                <UiCard variant="default" padding="md" class="hover:shadow-md transition-shadow cursor-pointer h-full">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 p-2 rounded-lg bg-brand-surface-2 text-brand-primary">
                            <component :is="m.icon" :size="24" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-serif text-base text-brand-text">{{ m.title }}</h3>
                            <p class="text-sm text-brand-text-muted mt-1 leading-relaxed">{{ m.description }}</p>
                        </div>
                    </div>
                </UiCard>
            </Link>
        </div>

        <UiCard variant="default" padding="md" class="mt-4">
            <template #header>
                <h2 class="font-serif text-base">マニュアルのご利用方法</h2>
            </template>
            <div class="text-sm text-brand-text leading-relaxed space-y-2">
                <p>① 上のカードから、お調べになりたい業務をお選びください。</p>
                <p>② 各マニュアルでは、画面のスクリーンショットと一緒に手順を①②③とご案内しています。</p>
                <p>③ ご不明な点がありましたら、システム担当（村上）までお気軽にお問い合わせください。</p>
            </div>
        </UiCard>
    </AdminLayout>
</template>
