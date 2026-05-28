<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    BookOpen, Layers, List, PlusCircle, FileText, Clock, MapPin,
    Users, FileEdit, Download, HelpCircle, Ticket,
    LayoutGrid, Filter, PlusSquare, UserPlus, Tag, XCircle, Printer,
} from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiCard, UiPageHeader, UiBadge, UiAlert } from '@/Components/UI';

defineProps({ currentUser: { type: Object, default: null } });

const sections = [
    { id: 'intro',         label: 'はじめに',                      icon: BookOpen },
    { id: 'glossary',      label: '用語のご案内',                  icon: Layers },
    { id: 'list',          label: 'イベント一覧の見方',            icon: List },
    { id: 'create',        label: 'イベントを作成する',            icon: PlusCircle },
    { id: 'lp',            label: '公開ページの設定',              icon: FileText },
    { id: 'timeslot',      label: 'タイムスロットを設定する',      icon: Clock },
    { id: 'venue',          label: '開催会場を登録する',           icon: MapPin },
    // 予約一覧（章 8〜16）
    { id: 'rsv-open',      label: '予約一覧を開く',                icon: Users },
    { id: 'rsv-views',     label: '表示モードを切り替える',        icon: LayoutGrid },
    { id: 'rsv-filter',    label: '絞り込みで予約を探す',          icon: Filter },
    { id: 'rsv-capacity',  label: '予約枠の数を増減する',          icon: PlusSquare },
    { id: 'rsv-add',       label: '顧客から新規予約を登録する',    icon: UserPlus },
    { id: 'rsv-status',    label: '予約のステータスを変更する',    icon: Tag },
    { id: 'rsv-edit',      label: '予約の詳細を編集する',          icon: FileEdit },
    { id: 'rsv-cancel',    label: 'キャンセル／復元／削除する',    icon: XCircle },
    { id: 'rsv-print',     label: '予約一覧を印刷する',            icon: Printer },
    { id: 'export',        label: '予約者をCSV出力する',           icon: Download },
    { id: 'faq',           label: 'よくあるご質問',                icon: HelpCircle },
];

const activeSection = ref('intro');
let io = null;

onMounted(() => {
    const targets = sections.map((s) => document.getElementById(s.id)).filter(Boolean);
    io = new IntersectionObserver(
        (entries) => entries.forEach((e) => { if (e.isIntersecting) activeSection.value = e.target.id; }),
        { rootMargin: '-100px 0px -60% 0px', threshold: 0 },
    );
    targets.forEach((t) => io.observe(t));
});

onUnmounted(() => { if (io) io.disconnect(); });

function scrollTo(id) {
    const el = document.getElementById(id);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const showAnnotated = ref(true);
const img = (file) => {
    if (!showAnnotated.value) return `/images/manual/event/${file}`;
    return `/images/manual/event/${file.replace(/(\.[^.]+)$/, '_annotated$1')}`;
};
</script>

<template>
    <Head title="イベント・予約マニュアル" />

    <AdminLayout :breadcrumb="[{ label: '業務マニュアル', route: 'admin.manuals.index' }, { label: 'イベント・予約マニュアル' }]">
        <UiPageHeader
            title="イベント・予約マニュアル"
            description="イベントの作成・公開、お客様からのご予約の受け付け、ご予約一覧の確認とCSV出力までをご案内します。"
        >
            <template #icon><Ticket :size="24" /></template>
            <template #actions>
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" v-model="showAnnotated" class="rounded text-brand-primary" />
                    <span>説明用の色枠・矢印を表示</span>
                </label>
            </template>
        </UiPageHeader>

        <div class="manual-layout mt-4">
            <div class="space-y-6 min-w-0 manual-main">

                <!-- 1. はじめに -->
                <section id="intro">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <BookOpen :size="18" class="text-brand-primary" /> 1. はじめに
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                このマニュアルでは、画面左側の <strong>「イベント一覧」</strong> から開く、イベントとご予約を管理する機能をご案内します。
                            </p>
                            <p>
                                振袖大祭典や袴予約など、お客様向けの催しを <strong>「イベント」</strong> として登録し、公開ページ（LP）の見た目、ご予約の時間枠、開催会場までを一連で設定できます。お客様からのご予約は <strong>予約者一覧</strong> でご確認いただけます。
                            </p>
                        </div>
                    </UiCard>
                </section>

                <!-- 2. 用語のご案内 -->
                <section id="glossary">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Layers :size="18" class="text-brand-primary" /> 2. 用語のご案内
                            </h2>
                        </template>
                        <dl class="text-sm space-y-3">
                            <div>
                                <dt class="font-semibold text-brand-text">イベント</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お客様向けに開催する催しの単位です。たとえば「2026年 大創業祭」「冬の振袖大祭典」など。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">公開ページ（LP）</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お客様にご案内するインターネット上のページ（ご予約画面）のことです。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">タイムスロット（時間枠）</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お客様がご予約できる時間帯のことです（例：「2026/12/12 10:00」「12:00」「14:00」…）。1枠ずつご予約数の上限を設定できます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">開催会場</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    イベントを行う場所（住所）です。1イベントに複数の会場を結び付けられます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">予約ステータス</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    各ご予約の状態。
                                    <UiBadge variant="warning" size="sm" class="mx-1">未対応</UiBadge>＝確認前、
                                    <UiBadge variant="info" size="sm" class="mx-1">確認中</UiBadge>＝対応中、
                                    <UiBadge variant="success" size="sm" class="mx-1">対応完了</UiBadge>＝完了。
                                </dd>
                            </div>
                        </dl>
                    </UiCard>
                </section>

                <!-- 3. イベント一覧の見方 -->
                <section id="list">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <List :size="18" class="text-brand-primary" /> 3. イベント一覧の見方
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>① 左のサイドバーから <strong>【イベント一覧】</strong> をお開きください。</p>
                            <p>② 表に、登録されているイベントが新しいものから順に並びます。<strong>「公開中／非公開」</strong> の状態、開催期間、ご予約数も一目でご確認いただけます。</p>
                            <p>③ 行をクリックすると、イベントの詳細画面が開きます。</p>
                            <figure class="mt-4">
                                <img :src="img('01_event_index.png')" alt="イベント一覧" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">イベント一覧画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 4. イベントを作成する -->
                <section id="create">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <PlusCircle :size="18" class="text-brand-primary" /> 4. イベントを作成する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>① 一覧画面の右上にある <strong>【＋新規追加】</strong> ボタンをクリックします。</p>
                            <p>② <strong>「イベント名」「URL用ID（slug）」「フォーム種別」「開催期間」</strong> をご入力ください。フォーム種別は通常「予約フォーム」をお選びください。</p>
                            <p>③ 画面下の <strong>【作成する】</strong> をクリックすると、イベントが登録されます。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：URL用ID（slug）はインターネット上のご予約ページの一部になります。半角英数字とハイフンだけ、わかりやすい単語をお使いください。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('02_event_create.png')" alt="イベント新規作成" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">イベント新規作成画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 5. 公開ページの設定 -->
                <section id="lp">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <FileText :size="18" class="text-brand-primary" /> 5. 公開ページの設定
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                イベント詳細画面で <strong>「LP設定」</strong> を開きますと、お客様にご覧いただく公開ページの色合いや背景画像を編集できます。
                            </p>
                            <p>① イベント詳細画面の <strong>【LP設定】</strong> タブを開きます。</p>
                            <p>② <strong>背景色・コンテンツ色・キャッチコピー</strong> などの項目をご入力ください。</p>
                            <p>③ 画面下の <strong>【保存する】</strong> をクリックすると、公開ページに反映されます。</p>
                            <figure class="mt-4">
                                <img :src="img('03_event_show.png')" alt="イベント詳細" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">イベント詳細画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 6. タイムスロット -->
                <section id="timeslot">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Clock :size="18" class="text-brand-primary" /> 6. タイムスロットを設定する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                タイムスロットは、お客様がご予約できる <strong>「日付＋時刻」</strong> の組み合わせです。1日に複数の時間枠を作り、それぞれ受付可能人数（定員）をご指定いただけます。
                            </p>
                            <p>① イベント詳細画面の <strong>「タイムスロット」</strong> セクションを開きます。</p>
                            <p>② <strong>【＋追加】</strong> をクリックし、日付・時刻・定員をご入力ください。</p>
                            <p>③ 一括登録テンプレート（タイムスロットテンプレ）からの読み込みもご利用いただけます。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：定員に達したタイムスロットは、お客様の予約ページで自動的に「満員」と表示され、ご予約できないようになります。
                            </UiAlert>
                        </div>
                    </UiCard>
                </section>

                <!-- 7. 会場 -->
                <section id="venue">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <MapPin :size="18" class="text-brand-primary" /> 7. 開催会場を登録する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>① イベント詳細画面の <strong>「会場管理」</strong> セクションを開きます。</p>
                            <p>② <strong>【＋会場を追加】</strong> をクリックし、会場名・住所などをご入力ください。</p>
                            <p>③ 1イベントに複数の会場を登録できます。お客様のご予約時にお選びいただけるようになります。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：会場マスタは <strong>イベント・予約 ＞ 開催会場</strong> でまとめてご管理いただけます。
                            </UiAlert>
                        </div>
                    </UiCard>
                </section>

                <!-- 8. 予約一覧を開く -->
                <section id="rsv-open">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Users :size="18" class="text-brand-primary" /> 8. 予約一覧を開く
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                予約一覧では、お客様からのご予約をまとめて管理いただけます。日付・時間帯ごとの予約状況の確認、ステータスの変更、お客様情報の編集など、ご対応に必要な操作はすべてここで行えます。
                            </p>
                            <p>① 左のサイドバーから <strong>【イベント一覧】</strong> をお開きください。</p>
                            <p>② 対象のイベントの行にある <strong>【予約一覧】</strong> ボタンをクリックします。</p>
                            <p>③ そのイベントに紐づいた予約一覧が表示されます。</p>
                            <figure class="mt-4">
                                <img :src="img('07_reservation_index_schedule.png')" alt="予約一覧（スケジュール表示）" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">予約一覧画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 9. 表示モードを切り替える -->
                <section id="rsv-views">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <LayoutGrid :size="18" class="text-brand-primary" /> 9. 表示モードを切り替える
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                予約一覧は、用途に合わせて3つの表示モードをお選びいただけます。画面上部のタブで切り替えてください。
                            </p>
                            <ul>
                                <li><strong>スケジュール表示</strong>：会場・日付・時間枠ごとに並べた表示。予約フォーム（タイムスロット予約）の場合に最適です。</li>
                                <li><strong>テーブル表示</strong>：表形式の一覧表示。並べ替え・絞り込みがしやすい標準的な表示モードです。</li>
                                <li><strong>カード表示</strong>：1件ずつカードで表示する形式。資料請求・お問い合わせフォームの場合に表示されます。</li>
                            </ul>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：迷ったら <strong>スケジュール表示</strong> を選んでください。日付ごとの空き状況と予約の有無が一目でご確認いただけます。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('08_reservation_index_table.png')" alt="予約一覧（テーブル表示）" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">テーブル表示のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 10. 絞り込みで予約を探す -->
                <section id="rsv-filter">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Filter :size="18" class="text-brand-primary" /> 10. 絞り込みで予約を探す
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                予約件数が多くなった際は、絞り込みで目的の予約を素早くお探しいただけます。
                            </p>
                            <p>① 画面上部の <strong>「絞り込み」</strong> 欄を開きます。</p>
                            <p>② <strong>会場・日付・お客様名・ステータス・対応有無</strong> などをご指定いただきます。</p>
                            <p>③ <strong>【適用】</strong> をクリックすると、絞り込まれた予約だけが表示されます。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：絞り込み中は、左側の <strong>「会場・日付・時間へのジャンプ一覧」</strong> をクリックすると、その時間帯の予約までスクロールで一気に移動できます。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('09_reservation_filter.png')" alt="絞り込みUI" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">絞り込みUIのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 11. 予約枠の数を増減する -->
                <section id="rsv-capacity">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <PlusSquare :size="18" class="text-brand-primary" /> 11. 予約枠の数を増減する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                各タイムスロット（時間枠）の予約可能件数は、後から増やしたり減らしたりできます。
                            </p>
                            <p>① <strong>スケジュール表示</strong> で対象の時間枠をご覧ください。</p>
                            <p>② 時間枠の右側に <strong>「−1」「+1」「+5」</strong> のボタンが並んでいます。クリックするごとに、該当の枠数が増減します。</p>
                            <ul>
                                <li><strong>「−1」</strong>：1枠減らす（空き枠があるときのみ可能）</li>
                                <li><strong>「+1」</strong>：1枠増やす</li>
                                <li><strong>「+5」</strong>：5枠まとめて増やす</li>
                            </ul>
                            <UiAlert variant="warning" class="mt-3 text-xs">
                                ご注意：すでにご予約が入っている枠は減らせません。減らしたい場合は、先にご予約をキャンセルしてください。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('10_capacity_adjust.png')" alt="容量増減" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">枠数の増減ボタンのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 12. 顧客から新規予約を登録する -->
                <section id="rsv-add">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <UserPlus :size="18" class="text-brand-primary" /> 12. 顧客から新規予約を登録する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                お電話やご来店でいただいたご予約を、こちらの画面から代理でご登録いただけます。事前に登録済みのお客様カードと紐付けて記録します。
                            </p>
                            <p>① 対象のタイムスロットにある <strong>【顧客から予約登録】</strong> ボタンをクリックします。</p>
                            <p>② 検索ボックスにお名前・お電話番号などをご入力いただき、対象のお客様をお選びください。</p>
                            <p>③ 必要な情報（来店人数・お問い合わせ事項など）をご入力後、<strong>【登録する】</strong> をクリックします。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：お客様が未登録の場合は、先に <strong>顧客マニュアル ＞ 4. 新しいお客様を登録する</strong> をご参考に、お客様カードを作成しておいてください。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('11_customer_reserve_modal.png')" alt="顧客から予約登録モーダル" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">顧客から予約登録モーダルのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 13. 予約のステータスを変更する -->
                <section id="rsv-status">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Tag :size="18" class="text-brand-primary" /> 13. 予約のステータスを変更する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                各予約には対応の進捗を表す <strong>ステータス</strong> が表示されます。お客様への対応が進んだら、ステータスをご更新いただくと、店舗内で進行状況を共有しやすくなります。
                            </p>
                            <ul>
                                <li><UiBadge variant="neutral" size="sm">未対応</UiBadge>：まだご連絡できていない予約</li>
                                <li><UiBadge variant="warning" size="sm">確認中</UiBadge>：お客様とご連絡中</li>
                                <li><UiBadge variant="warning" size="sm">返信待ち</UiBadge>：お客様のお返事待ち</li>
                                <li><UiBadge variant="success" size="sm">対応完了済み</UiBadge>：対応がすべて完了</li>
                            </ul>
                            <p class="mt-3">① 対象予約の行にある <strong>「対応ステータス」</strong> をクリックします。</p>
                            <p>② プルダウンから新しいステータスをお選びください。</p>
                            <p>③ お選びいただくと自動で保存されます（保存ボタンは不要です）。</p>
                            <figure class="mt-4">
                                <img :src="img('12_status_change.png')" alt="ステータス変更" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">ステータス変更のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 14. 予約の詳細を編集する -->
                <section id="rsv-edit">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <FileEdit :size="18" class="text-brand-primary" /> 14. 予約の詳細を編集する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>① 予約一覧から、対象のお客様の行をクリックして詳細画面を開きます。</p>
                            <p>② 右上の <strong>【編集】</strong> ボタンをクリックすると、お客様の情報をご修正いただけます。</p>
                            <p>③ お電話でご対応いただいた内容などは、<strong>「対応・管理」</strong> タブ内の <strong>連絡メモ</strong> にお書き留めいただけます。</p>
                            <figure class="mt-4">
                                <img :src="img('05_reservation_show.png')" alt="予約詳細" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">予約詳細画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 15. キャンセル／復元／削除する -->
                <section id="rsv-cancel">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <XCircle :size="18" class="text-brand-primary" /> 15. キャンセル／復元／削除する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                ご予約に変更があった場合は、状況に応じて以下の操作をお選びください。
                            </p>
                            <h3 class="font-semibold text-brand-text mt-3">キャンセル</h3>
                            <p>お客様からのキャンセルのご連絡があった場合の操作です。予約データは残り、ステータスが「キャンセル」になります。</p>
                            <p>① 対象予約の行にある <strong>【キャンセル】</strong> ボタンをクリックします。</p>
                            <p>② ブラウザの確認画面が表示されますので、「<strong>本当にキャンセルしますか？</strong>」のメッセージにご同意いただける場合は <strong>【OK】</strong> をクリックしてください。</p>

                            <h3 class="font-semibold text-brand-text mt-4">復元</h3>
                            <p>キャンセルした予約を元に戻したい場合は、<strong>【キャンセル解除】</strong> ボタンをクリックします。同じくブラウザの確認画面で <strong>【OK】</strong> をお選びいただくと、ステータスが元に戻ります。</p>

                            <h3 class="font-semibold text-brand-text mt-4">削除</h3>
                            <p>誤って登録した予約や、もう不要なテスト予約は完全に削除できます。<strong>【削除】</strong> ボタンをクリックいただき、ブラウザの確認画面で <strong>【OK】</strong> をお選びください。</p>
                            <UiAlert variant="warning" class="mt-3 text-xs">
                                ご注意：<strong>削除</strong>は元に戻せません。お客様のご意向によるキャンセルは <strong>「キャンセル」</strong> をお選びください。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('13_cancel_modal.png')" alt="キャンセル操作" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">テーブル表示で各予約の右側に【キャンセル】ボタンが並ぶ</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 16. 予約一覧を印刷する -->
                <section id="rsv-print">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Printer :size="18" class="text-brand-primary" /> 16. 予約一覧を印刷する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                ご予約をお手元の紙でご確認いただけるよう、A4 サイズで印刷できる機能です。
                            </p>
                            <p>① 予約一覧画面の <strong>テーブル表示</strong> をお選びください。</p>
                            <p>② 画面上部の <strong>【印刷】</strong> ボタンをクリックします。</p>
                            <p>③ 印刷設定モーダルが開きます。<strong>印刷に含めるカラム</strong>をお選びいただき、画面下の <strong>【印刷】</strong> をクリックします。</p>
                            <p>④ ご利用パソコンの印刷プレビューが開きますので、プリンタをお選びいただいて印刷してください。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：よく使うカラムの組み合わせは、次回の印刷時にもそのまま使えるよう自動でご記憶されます。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('14_print_modal.png')" alt="印刷設定モーダル" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">印刷設定モーダルのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 17. 予約者をCSV出力 -->
                <section id="export">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Download :size="18" class="text-brand-primary" /> 17. 予約者をCSV出力する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                ご予約データを Excel などでご活用いただけるよう、CSV形式でダウンロードいただけます。
                            </p>
                            <p>① 左のサイドバーから <strong>【予約者出力】</strong> をお開きください。</p>
                            <p>② 出力したいイベントと、絞り込み条件（期間・ステータス等）をご指定ください。</p>
                            <p>③ <strong>【CSV出力】</strong> をクリックしますと、ファイルがパソコンに保存されます。</p>
                            <figure class="mt-4">
                                <img :src="img('06_reservations_export.png')" alt="予約者CSV出力" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">予約者CSV出力画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 18. よくあるご質問 -->
                <section id="faq">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <HelpCircle :size="18" class="text-brand-primary" /> 18. よくあるご質問
                            </h2>
                        </template>
                        <dl class="text-sm space-y-4">
                            <div>
                                <dt class="font-semibold text-brand-text">Q. お客様の予約ページのURLを知りたいです。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    イベント詳細画面の上部に <strong>【公開URLをコピー】</strong> ボタンがあります。クリックすると、お客様のご予約ページのURLがコピーされます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. 予約をキャンセルにしたいです。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    予約詳細画面の <strong>【ステータス】</strong> を「キャンセル」に変更して保存してください。お客様には自動でメールは送られませんので、別途ご連絡をお願いします。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. 同じお客様が二重予約してしまいました。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    重複しているご予約のうち一方の <strong>【ステータス】</strong> を「キャンセル」に変更してください。重複予約は予約一覧でお名前検索すると見つけやすくなります。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. 確認メールが届かないとお客様から言われました。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お客様のメールアドレスが正しく登録されているかご確認ください。迷惑メールフォルダに振り分けられている可能性もありますので、お客様にもご確認をお願いします。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. お電話でいただいたご予約を後から登録したい。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    予約一覧の <strong>「12. 顧客から新規予約を登録する」</strong> をご参照ください。事前にお客様カードをご登録のうえ、対象のタイムスロットの <strong>【顧客から予約登録】</strong> ボタンからご登録いただけます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. 予約が満員になりましたが、特別にもう1組ご案内したい。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    予約一覧の <strong>「11. 予約枠の数を増減する」</strong> で <strong>【+1】</strong> ボタンをクリックすると、その時間枠の上限が1つ増えます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. 印刷した一覧をスタッフ間で共有したい。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    予約一覧の <strong>「16. 予約一覧を印刷する」</strong> をご参照ください。<strong>テーブル表示</strong> から印刷ボタンをクリックいただくと、A4サイズでご印刷いただけます。
                                </dd>
                            </div>
                        </dl>
                    </UiCard>
                </section>

            </div>

            <aside class="manual-toc">
                <div class="sticky top-4 space-y-2">
                    <UiCard variant="default" padding="sm">
                        <template #header><h3 class="font-serif text-sm">目次</h3></template>
                        <nav class="text-xs space-y-1">
                            <button
                                v-for="s in sections"
                                :key="s.id"
                                @click="scrollTo(s.id)"
                                :class="[
                                    'flex items-center gap-2 w-full text-left px-2 py-1 rounded',
                                    activeSection === s.id ? 'bg-brand-surface-2 text-brand-primary font-semibold' : 'text-brand-text-muted hover:bg-brand-surface-2',
                                ]"
                            >
                                <component :is="s.icon" :size="14" />
                                <span class="truncate">{{ s.label }}</span>
                            </button>
                        </nav>
                    </UiCard>
                </div>
            </aside>
        </div>
    </AdminLayout>
</template>

<style scoped>
.manual-layout { display: grid; grid-template-columns: 1fr 220px; gap: 1.5rem; }
@media (max-width: 900px) {
    .manual-layout { grid-template-columns: 1fr; }
    .manual-toc { display: none; }
}
.manual-main figure img { max-width: 100%; height: auto; }
</style>
