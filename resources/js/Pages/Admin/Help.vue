<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiCard, UiPageHeader, UiBadge } from '@/Components/UI';
import {
    BookOpen, Keyboard, Sparkles, MessageCircle, HelpCircle, ExternalLink,
} from 'lucide-vue-next';
</script>

<template>
    <Head title="ヘルプ" />
    <AdminLayout :breadcrumb="[{ label: 'ホーム' }, { label: 'ヘルプ' }]">

        <UiPageHeader
            title="ヘルプ"
            description="管理画面の使い方・ショートカット・UIキットへのリンクをまとめています。"
        />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <UiCard variant="default">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-soft bg-ai-50 dark:bg-ai-900 flex items-center justify-center flex-shrink-0">
                        <BookOpen :size="20" class="text-brand-primary" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-serif text-base mb-1">マニュアル</h3>
                        <p class="text-xs text-brand-text-muted mb-3">管理画面の機能別操作手順（準備中）</p>
                        <div class="space-y-1 text-sm">
                            <div class="text-brand-text-subtle">・ 予約管理の基本</div>
                            <div class="text-brand-text-subtle">・ Googleカレンダー連携</div>
                            <div class="text-brand-text-subtle">・ LINE顧客連絡</div>
                            <div class="text-brand-text-subtle">・ CSV出力</div>
                        </div>
                    </div>
                </div>
            </UiCard>

            <UiCard variant="default">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-soft bg-uguisu-50 dark:bg-uguisu-900 flex items-center justify-center flex-shrink-0">
                        <Keyboard :size="20" class="text-brand-success" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-serif text-base mb-1">ショートカット</h3>
                        <p class="text-xs text-brand-text-muted mb-3">業務を素早くこなすためのキー操作</p>
                        <ul class="space-y-1.5 text-sm">
                            <li class="flex items-center justify-between">
                                <span>コマンドパレット</span>
                                <span>
                                    <kbd class="text-[10px] px-1.5 py-0.5 border border-brand-border-strong rounded bg-brand-surface">⌘K</kbd>
                                    <span class="text-brand-text-subtle mx-1 text-xs">/</span>
                                    <kbd class="text-[10px] px-1.5 py-0.5 border border-brand-border-strong rounded bg-brand-surface">Ctrl+K</kbd>
                                </span>
                            </li>
                            <li class="flex items-center justify-between">
                                <span>パレットを閉じる</span>
                                <kbd class="text-[10px] px-1.5 py-0.5 border border-brand-border-strong rounded bg-brand-surface">Esc</kbd>
                            </li>
                        </ul>
                    </div>
                </div>
            </UiCard>

            <UiCard variant="default">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-soft bg-enji-50 dark:bg-enji-900 flex items-center justify-center flex-shrink-0">
                        <Sparkles :size="20" class="text-brand-accent" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-serif text-base mb-1">UIキット</h3>
                        <p class="text-xs text-brand-text-muted mb-3">新デザインシステムのコンポーネント一覧</p>
                        <Link :href="route('admin.ui-kit')" class="text-sm text-brand-primary hover:underline inline-flex items-center gap-1">
                            ショーケースを開く <ExternalLink :size="12" />
                        </Link>
                    </div>
                </div>
            </UiCard>

            <UiCard variant="default" class="lg:col-span-3">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-soft bg-natane-50 dark:bg-natane-900 flex items-center justify-center flex-shrink-0">
                        <HelpCircle :size="20" class="text-brand-warning" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-serif text-base mb-1">困ったとき</h3>
                        <p class="text-sm text-brand-text-muted mb-2">
                            操作方法で不明な点や動作不具合がある場合は、システム管理者までご連絡ください。
                        </p>
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <UiBadge variant="neutral">管理者</UiBadge>
                            <span class="text-brand-text">村上</span>
                            <span class="text-brand-text-subtle">|</span>
                            <MessageCircle :size="14" class="text-brand-text-muted" />
                            <span class="text-brand-text-muted">Teams / メール</span>
                        </div>
                    </div>
                </div>
            </UiCard>

        </div>

    </AdminLayout>
</template>
