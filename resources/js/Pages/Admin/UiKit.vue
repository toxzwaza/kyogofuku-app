<script setup>
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    UiButton, UiCard, UiBadge, UiInput, UiTextarea, UiSelect, UiFormField,
    UiAlert, UiDialog, UiDropdown, UiDropdownItem, UiTabs, UiTooltip, UiSkeleton,
    UiPageHeader,
} from '@/Components/UI';
import { useToast } from '@/composables/useToast.js';
import {
    Search, Plus, Filter, Download, MoreVertical, Trash2,
    User, Calendar, Settings,
} from 'lucide-vue-next';

const toast = useToast();

// Dialog state
const dialogOpen = ref(false);

// Form
const formInput = ref('');
const formTextarea = ref('');
const formSelect = ref('');
const selectOptions = [
    { value: 'okayama', label: '岡山店' },
    { value: 'joto',    label: '城東店' },
    { value: 'hama',    label: '浜店' },
    { value: 'fukui',   label: '福井店' },
];

// Tabs
const activeTab = ref('overview');
const tabs = [
    { id: 'overview', label: '概要' },
    { id: 'timeline', label: 'タイムライン' },
    { id: 'sync',     label: 'カレンダー同期' },
];

</script>

<template>
    <Head title="UIキット" />
    <AdminLayout :breadcrumb="[{ label: 'システム' }, { label: 'UIキット' }]">
        <div class="max-w-7xl mx-auto space-y-10">

                <UiPageHeader
                    title="UIキット ショーケース"
                    description="和＋モダン デザインシステムのコンポーネント一覧。管理画面はこの部品で組み上げます。"
                />

                <!-- Buttons -->
                <UiCard variant="default" padding="lg">
                    <template #header><h2 class="font-serif text-lg">1. Button</h2></template>
                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-2 items-center">
                            <UiButton variant="primary">予約を確定</UiButton>
                            <UiButton variant="accent">LINE送信</UiButton>
                            <UiButton variant="ghost">キャンセル</UiButton>
                            <UiButton variant="subtle">エクスポート</UiButton>
                            <UiButton variant="danger">削除</UiButton>
                            <UiButton variant="link">リンク</UiButton>
                        </div>
                        <div class="flex flex-wrap gap-2 items-center">
                            <UiButton size="sm" variant="primary">小</UiButton>
                            <UiButton size="md" variant="primary">中</UiButton>
                            <UiButton size="lg" variant="primary">大</UiButton>
                            <UiButton variant="primary" loading>送信中...</UiButton>
                            <UiButton variant="primary" disabled>無効</UiButton>
                        </div>
                        <div class="flex flex-wrap gap-2 items-center">
                            <UiButton variant="primary">
                                <template #leading><Plus :size="16" /></template>
                                新規追加
                            </UiButton>
                            <UiButton variant="ghost">
                                <template #leading><Download :size="16" /></template>
                                CSVエクスポート
                            </UiButton>
                            <UiButton variant="subtle">
                                フィルタ
                                <template #trailing><Filter :size="14" /></template>
                            </UiButton>
                        </div>
                    </div>
                </UiCard>

                <!-- Badges -->
                <UiCard variant="default" padding="lg">
                    <template #header><h2 class="font-serif text-lg">2. Badge（ステータス表示）</h2></template>
                    <div class="flex flex-wrap gap-2 items-center">
                        <UiBadge variant="primary">確認中</UiBadge>
                        <UiBadge variant="accent">予約受付</UiBadge>
                        <UiBadge variant="success">対応完了</UiBadge>
                        <UiBadge variant="warning">返信待ち</UiBadge>
                        <UiBadge variant="danger">キャンセル</UiBadge>
                        <UiBadge variant="neutral">未対応</UiBadge>
                        <UiBadge variant="primary" dot>稼働中</UiBadge>
                        <UiBadge variant="success" dot>オンライン</UiBadge>
                        <UiBadge size="sm" variant="warning">小サイズ</UiBadge>
                    </div>
                </UiCard>

                <!-- Alerts -->
                <UiCard variant="default" padding="lg">
                    <template #header><h2 class="font-serif text-lg">3. Alert</h2></template>
                    <div class="space-y-3">
                        <UiAlert variant="info" title="Googleカレンダー連携">予約スケジュールが同期されました。</UiAlert>
                        <UiAlert variant="success">予約を保存しました。</UiAlert>
                        <UiAlert variant="warning" title="枠が残り少なくなっています" dismissible>残り3枠です。早めの案内を推奨します。</UiAlert>
                        <UiAlert variant="danger" title="送信失敗">LINE通知の送信に失敗しました。時間をおいて再試行してください。</UiAlert>
                    </div>
                </UiCard>

                <!-- Toasts -->
                <UiCard variant="default" padding="lg">
                    <template #header><h2 class="font-serif text-lg">4. Toast</h2></template>
                    <div class="flex flex-wrap gap-2">
                        <UiButton variant="ghost" @click="toast.info('情報メッセージです')">情報</UiButton>
                        <UiButton variant="ghost" @click="toast.success('予約を保存しました')">成功</UiButton>
                        <UiButton variant="ghost" @click="toast.warning('まもなく満席になります')">警告</UiButton>
                        <UiButton variant="ghost" @click="toast.danger('送信に失敗しました')">エラー</UiButton>
                    </div>
                    <p class="mt-3 text-xs text-brand-text-muted">画面右上に自動で表示されます。Laravel の <code>session()-&gt;with('success')</code> からも自動で流れ込みます。</p>
                </UiCard>

                <!-- Form -->
                <UiCard variant="default" padding="lg">
                    <template #header><h2 class="font-serif text-lg">5. Form</h2></template>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <UiFormField label="お客様名" required hint="フルネームでご入力ください">
                            <UiInput v-model="formInput" placeholder="京呉服 太郎" />
                        </UiFormField>
                        <UiFormField label="店舗" required>
                            <UiSelect v-model="formSelect" :options="selectOptions" placeholder="選択してください" />
                        </UiFormField>
                        <UiFormField label="検索" hint="お名前・電話・予約番号で横断検索">
                            <UiInput placeholder="三宅 愛奈">
                                <template #leading><Search :size="14" /></template>
                            </UiInput>
                        </UiFormField>
                        <UiFormField label="担当メモ">
                            <UiTextarea v-model="formTextarea" placeholder="例：初めてのご来店、ご姉妹で予約" :rows="3" />
                        </UiFormField>
                        <UiFormField label="メール" error="正しいメールアドレスを入力してください">
                            <UiInput error placeholder="example@example.com" />
                        </UiFormField>
                    </div>
                </UiCard>

                <!-- Dialog / Dropdown / Tabs / Tooltip -->
                <UiCard variant="default" padding="lg">
                    <template #header><h2 class="font-serif text-lg">6. Dialog / Dropdown / Tooltip</h2></template>
                    <div class="flex flex-wrap gap-3 items-center">
                        <UiButton variant="primary" @click="dialogOpen = true">Dialog を開く</UiButton>

                        <UiDropdown align="left">
                            <template #trigger>
                                <UiButton variant="ghost">
                                    操作
                                    <template #trailing><MoreVertical :size="14" /></template>
                                </UiButton>
                            </template>
                            <UiDropdownItem>
                                <User :size="14" /><span>プロフィール</span>
                            </UiDropdownItem>
                            <UiDropdownItem>
                                <Settings :size="14" /><span>設定</span>
                            </UiDropdownItem>
                            <UiDropdownItem danger>
                                <Trash2 :size="14" /><span>削除</span>
                            </UiDropdownItem>
                        </UiDropdown>

                        <UiTooltip text="CSVでエクスポートします">
                            <UiButton variant="subtle">
                                <template #leading><Download :size="14" /></template>
                                エクスポート
                            </UiButton>
                        </UiTooltip>

                        <UiTooltip text="新しい予約カレンダー" placement="right">
                            <UiButton variant="ghost">
                                <template #leading><Calendar :size="14" /></template>
                                カレンダー
                            </UiButton>
                        </UiTooltip>
                    </div>

                    <UiDialog v-model:open="dialogOpen" title="予約をキャンセル">
                        <p class="text-sm text-brand-text-muted">この予約をキャンセルしますか？Googleカレンダーからも削除されます。</p>
                        <template #footer>
                            <UiButton variant="ghost" @click="dialogOpen = false">戻る</UiButton>
                            <UiButton variant="danger" @click="dialogOpen = false; toast.success('キャンセルしました')">キャンセル実行</UiButton>
                        </template>
                    </UiDialog>
                </UiCard>

                <!-- Tabs -->
                <UiCard variant="default" padding="lg">
                    <template #header><h2 class="font-serif text-lg">7. Tabs</h2></template>
                    <UiTabs v-model="activeTab" :tabs="tabs">
                        <template #overview>
                            <p class="text-sm">予約 #862 村上テスト様 2026-04-23 11:00 浜店</p>
                        </template>
                        <template #timeline>
                            <ul class="text-sm space-y-2">
                                <li class="flex items-center gap-2"><UiBadge variant="primary" size="sm">受付</UiBadge>2026-04-23 08:01</li>
                                <li class="flex items-center gap-2"><UiBadge variant="success" size="sm">同期</UiBadge>2026-04-23 08:03 Googleカレンダーへ</li>
                            </ul>
                        </template>
                        <template #sync>
                            <p class="text-sm">浜店カレンダー: <code class="text-xs">6e7e70a0...@group.calendar.google.com</code></p>
                        </template>
                    </UiTabs>
                </UiCard>

                <!-- Skeleton -->
                <UiCard variant="default" padding="lg">
                    <template #header><h2 class="font-serif text-lg">8. Skeleton（読み込み中の骨組み）</h2></template>
                    <div class="flex items-center gap-3">
                        <UiSkeleton variant="circle" height="40px" />
                        <div class="flex-1 space-y-2">
                            <UiSkeleton height="14px" width="40%" />
                            <UiSkeleton height="12px" width="70%" />
                        </div>
                    </div>
                </UiCard>

                <!-- Card variants -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <UiCard variant="default">
                        <div class="text-xs text-brand-text-muted">本日の予約</div>
                        <div class="mt-2 font-serif text-3xl">12<span class="text-sm text-brand-text-muted ml-1">件</span></div>
                        <div class="mt-1 text-xs text-uguisu-600 dark:text-uguisu-400">▲ 前週比 +20%</div>
                    </UiCard>
                    <UiCard variant="elevated">
                        <div class="text-xs text-brand-text-muted">今週の来店</div>
                        <div class="mt-2 font-serif text-3xl">47<span class="text-sm text-brand-text-muted ml-1">人</span></div>
                        <div class="mt-1 text-xs text-brand-text-subtle">前週同期比 +5</div>
                    </UiCard>
                    <UiCard variant="outlined">
                        <div class="text-xs text-brand-text-muted">キャンセル率</div>
                        <div class="mt-2 font-serif text-3xl">3.2<span class="text-sm text-brand-text-muted ml-1">%</span></div>
                        <div class="mt-1 text-xs text-akane-600 dark:text-akane-400">▼ 前週比 -1.1pt</div>
                    </UiCard>
                </div>

        </div>
    </AdminLayout>
</template>
