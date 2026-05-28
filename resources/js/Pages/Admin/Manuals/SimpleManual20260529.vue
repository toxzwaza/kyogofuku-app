<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    BookOpen, List, FileText, ExternalLink, Calendar, PlusCircle,
    UserPlus, PlusSquare, Tag, MessageCircle, Users, Camera, Mail, HelpCircle,
    Download, Printer,
} from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiCard, UiPageHeader, UiBadge, UiAlert } from '@/Components/UI';

defineProps({ currentUser: { type: Object, default: null } });

const sections = [
    // 1. イベント関連
    { id: 'event-index',   label: '1-1. イベント一覧を確認する',   icon: List },
    { id: 'event-show',    label: '1-2. イベント詳細を確認する',   icon: FileText },
    { id: 'event-lp',      label: '1-3. 公開ページ（LP）を確認',   icon: ExternalLink },
    // 2. 予約一覧
    { id: 'rsv-index',     label: '2-1. 予約一覧を確認する',       icon: Calendar },
    { id: 'rsv-register',  label: '2-2. 予約を登録する',           icon: PlusCircle },
    { id: 'rsv-capacity',  label: '2-3. 予約枠を増減する',         icon: PlusSquare },
    { id: 'rsv-show',      label: '2-4. 予約者詳細を確認・対応',   icon: Tag },
    { id: 'rsv-comm',      label: '2-5. メール・LINEで連絡',       icon: MessageCircle },
    // 3. 顧客一覧
    { id: 'cust-index',    label: '3-1. お客様を探す／追加する',   icon: Users },
    // 4. 顧客詳細
    { id: 'cust-show',     label: '4-1. 顧客詳細の全体構成',       icon: FileText },
    { id: 'cust-overview', label: '4-2. 概要タブ',                 icon: Users },
    { id: 'cust-info',     label: '4-3. 詳細情報タブ',             icon: FileText },
    { id: 'cust-photo',    label: '4-4. 前撮りを顧客詳細から登録', icon: Camera },
    { id: 'cust-comm',     label: '4-5. 連絡・メモタブ',           icon: Mail },
    // 5. 補足
    { id: 'faq',           label: '5. その他の補足',                icon: HelpCircle },
];

const activeSection = ref('event-index');
let io = null;

onMounted(() => {
    const targets = sections.map((s) => document.getElementById(s.id)).filter(Boolean);
    if (!targets.length) return;
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
    if (!showAnnotated.value) return `/images/manual/simple-20260529/${file}`;
    return `/images/manual/simple-20260529/${file.replace(/(\.[^.]+)$/, '_annotated$1')}`;
};
</script>

<template>
    <Head title="簡易マニュアル 2026-05-29" />

    <AdminLayout :breadcrumb="[{ label: '業務マニュアル' }, { label: '簡易マニュアル 2026-05-29' }]">
        <UiPageHeader
            title="簡易マニュアル 2026-05-29"
            description="本システムの主要操作を、業務の流れに沿ってまとめた版です。福井店スタッフ向けにご説明する内容を中心にしています。"
        >
            <template #icon><BookOpen :size="24" /></template>
            <template #actions>
                <div class="flex items-center gap-3 flex-wrap">
                    <a
                        href="/manuals/簡易マニュアル_20260529.pdf"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-brand-primary text-white text-sm rounded hover:opacity-90 transition"
                        title="A4印刷向けPDFをダウンロード"
                    >
                        <Download :size="14" />
                        PDFをダウンロード
                    </a>
                    <button
                        type="button"
                        @click="window.print()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-brand-border text-sm rounded hover:bg-brand-surface-2 transition"
                        title="このページを印刷"
                    >
                        <Printer :size="14" />
                        印刷
                    </button>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" v-model="showAnnotated" class="rounded text-brand-primary" />
                        <span>説明用の色枠・矢印を表示</span>
                    </label>
                </div>
            </template>
        </UiPageHeader>

        <div class="manual-layout mt-4">
            <div class="space-y-6 min-w-0 manual-main">

                <!-- ====== 1. イベント関連 ====== -->
                <section id="event-index">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <List :size="18" class="text-brand-primary" /> 1-1. イベント一覧を確認する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>左メニューの <strong>【イベント一覧】</strong> をクリックすると、本システムに登録されているすべてのイベントをご確認いただけます。</p>
                            <p>① 初期表示では、ログイン中のユーザーが <strong>所属する店舗</strong> で自動的に絞り込まれています。</p>
                            <p>② 別店舗のイベントをご確認になりたい場合は、画面上部の <strong>「店舗」</strong> を選び直してください。</p>
                            <p>③ 過去のイベントは <strong>「公開状態」</strong> を <strong>「受付終了」</strong> に切り替えていただくと表示されます。</p>
                            <p>④ 条件を変更した後は、必ず <strong>【検索】</strong> ボタンをクリックしてください。</p>
                            <figure class="mt-4">
                                <img :src="img('01_event_index.png')" alt="イベント一覧" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">イベント一覧画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <section id="event-show">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <FileText :size="18" class="text-brand-primary" /> 1-2. イベント詳細を確認する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>イベント一覧から対象イベントの行をクリックすると、詳細画面が開きます。</p>
                            <p>
                                この画面では <strong>基本情報・会場管理・LP設定・タイムスロット・CTAデザイン</strong> など、関連する設定をひとつの画面で確認・編集いただけます。
                            </p>
                            <p>画面上部のボタンから、各設定ページへスムーズに移動できます。</p>
                            <ul>
                                <li><strong>【公開ページを表示】</strong>：お客様が見るLPを確認</li>
                                <li><strong>【LP設定】【画像管理】【CTAデザイン】</strong>：見た目の調整</li>
                                <li><strong>【予約一覧】</strong>：このイベントの予約者一覧へ</li>
                                <li><strong>【枠管理】</strong>：タイムスロットの増減・調整</li>
                            </ul>
                            <p>
                                また、<strong>「予約枠状況」</strong> カードでは <strong>総枠数 / 予約済み数 / 残り枠数 / 埋まり率</strong> が一目でご確認いただけます。
                            </p>
                            <figure class="mt-4">
                                <img :src="img('02_event_show.png')" alt="イベント詳細" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">イベント詳細画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <section id="event-lp">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <ExternalLink :size="18" class="text-brand-primary" /> 1-3. 公開ページ（LP）を確認する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                イベント詳細画面の <strong>【公開ページを表示】</strong> ボタンをクリックすると、お客様がご覧になっているLP（公開ページ）をそのままの形で確認いただけます。
                            </p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                <strong>ご協力のお願い：</strong> 特典内容やプラン内容など、実際と相違がある場合はお手数ですがご連絡をお願いいたします。
                                また、現場目線の <strong>「こんな感じにしてほしい」</strong> というご要望は大切な改善のヒントになりますので、お気兼ねなくご連絡ください。<br>
                                ※ 反映の可否は、社長・玉村さん・広告代理店さんと協議のうえで決定いたします。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('03_event_lp.png')" alt="LP公開ページ" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">公開ページのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- ====== 2. 予約一覧 ====== -->
                <section id="rsv-index">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Calendar :size="18" class="text-brand-primary" /> 2-1. 予約一覧を確認する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>イベント詳細から <strong>【予約一覧】</strong> をクリックすると、そのイベントに紐づいたお客様のご予約一覧が表示されます。</p>
                            <UiAlert variant="warning" class="mt-3 text-xs">
                                <strong>通知について：</strong> ご予約が入りますとほぼ100% リアルタイムで LINEグループに通知されます。ただし、メンテナンス中や LINE 側の障害で通知が届かない場合もあります。
                                <strong>いかなる場合もご予約は必ずこの画面に登録されますので、イベント期間中は定期的にご確認ください。</strong>
                            </UiAlert>
                            <h3 class="font-semibold text-brand-text mt-4">表示モードの使い分け</h3>
                            <ul>
                                <li><strong>日付表示（スケジュール）</strong>：通常の確認用。日付・時間枠ごとに見やすく整理されます。</li>
                                <li><strong>テーブル表示</strong>：一覧を <strong>印刷したいとき</strong> に切り替えます。</li>
                            </ul>
                            <h3 class="font-semibold text-brand-text mt-3">絞り込み・ジャンプ</h3>
                            <ul>
                                <li>画面上部 ── <strong>会場・日付・時間</strong> で絞り込み可能</li>
                                <li>画面左側 ── 絞り込み結果の <strong>「会場 / 日付 / 時間」へのジャンプ一覧</strong>。スクロール操作が大変な場合はこちらをご利用ください。</li>
                            </ul>
                            <figure class="mt-4">
                                <img :src="img('04_reservation_index_schedule.png')" alt="予約一覧 日付表示" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">日付表示のサンプル</figcaption>
                            </figure>
                            <figure class="mt-4">
                                <img :src="img('05_reservation_index_table.png')" alt="予約一覧 テーブル表示" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">テーブル表示（印刷向け）のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <section id="rsv-register">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <PlusCircle :size="18" class="text-brand-primary" /> 2-2. 予約を登録する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>ご予約のご登録方法は、状況に応じて3通りございます。</p>

                            <h3 class="font-semibold text-brand-text mt-3">A. 既存のお客様情報から登録する</h3>
                            <p>すでにお客様カードが登録されている場合の方法です。</p>
                            <p>① タイムスロット内の <strong>【顧客情報から予約登録】</strong> ボタンをクリック</p>
                            <p>② 検索ボックスにお名前をご入力いただき、対象のお客様をお選びください</p>
                            <p>③ <strong>【登録する】</strong> をクリックしてご予約完了</p>
                            <figure class="mt-3">
                                <img :src="img('06_customer_reserve_modal.png')" alt="顧客から予約登録モーダル" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">顧客情報から予約登録モーダル</figcaption>
                            </figure>

                            <h3 class="font-semibold text-brand-text mt-5">B. お電話・ご来店時にその場で登録する</h3>
                            <p>まだお客様カードがない方をその場でご予約するときは、<strong>【予約登録】</strong> ボタンをクリックして、必要事項をご入力ください。</p>
                            <figure class="mt-3">
                                <img :src="img('07_reservation_register_modal.png')" alt="予約登録モーダル" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">予約登録モーダル</figcaption>
                            </figure>

                            <h3 class="font-semibold text-brand-text mt-5">C. お客様ご自身に登録していただく</h3>
                            <p><strong>【公開ページを表示】</strong> から、実際の LP 画面で登録することも可能です。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                <strong>使い分けのポイント：</strong><br>
                                ・ お客様のメールアドレスに <strong>自動確認メールが届き、LINEグループにも通知</strong>が飛びます。<br>
                                ・ 通知や確認メールが <strong>不要な場合は B（予約登録ボタン）</strong> から、<br>
                                ・ お送りしたい場合・LINE 連携をご案内したい場合は <strong>C（公開ページから）</strong> をお選びください。
                            </UiAlert>
                        </div>
                    </UiCard>
                </section>

                <section id="rsv-capacity">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <PlusSquare :size="18" class="text-brand-primary" /> 2-3. 予約枠を増減する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>各タイムスロットの右側にあるボタンで、予約可能枠を自由に増減いただけます。</p>
                            <ul>
                                <li><strong>【＋1】</strong>：1枠増やす</li>
                                <li><strong>【＋5】</strong>：5枠まとめて増やす</li>
                                <li><strong>【−1】</strong>：1枠減らす（空き枠があるときのみ）</li>
                            </ul>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                <strong>運用方針：</strong> 現状はイベント開始前に村上が固定で登録し、社長と相談のうえで増減しております。
                                今後は <strong>現場スタッフが状況を見ながら臨機応変に増減</strong>いただくのが最適と考えております。お気軽にご操作ください。
                            </UiAlert>
                            <UiAlert variant="warning" class="mt-3 text-xs">
                                ご注意：すでにご予約が入っている枠は減らせません。減らしたい場合は先にキャンセル操作をお願いします。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('08_capacity_adjust.png')" alt="枠の増減" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">枠数の増減ボタンのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <section id="rsv-show">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Tag :size="18" class="text-brand-primary" /> 2-4. 予約者詳細を確認・対応する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>予約一覧から対象のご予約をクリックすると、詳細画面が開きます。基本情報・予約情報・お客様情報がすべて確認いただけます。</p>

                            <h3 class="font-semibold text-brand-text mt-3">「対応・管理」タブでできること</h3>
                            <ul>
                                <li><strong>対応ステータスの変更</strong>：ご予約への対応進捗を記録。変更すると Googleカレンダーのイベント色も連動します。</li>
                                <li><strong>担当者の登録</strong>：対応スタッフを記録できます。</li>
                                <li><strong>入場チケット送付状況の記録</strong>：送付済み／未送付を管理できます。</li>
                                <li><strong>顧客紐付け</strong>：名前一致のお客様カードがあれば紐付け可能。なければ <strong>ワンクリックで新規顧客カードとして登録</strong>できます。</li>
                            </ul>

                            <h3 class="font-semibold text-brand-text mt-4">対応ステータスとGoogleカレンダーの色対応表</h3>
                            <div class="overflow-x-auto">
                                <table class="text-xs w-full border-collapse">
                                    <thead>
                                        <tr class="bg-brand-surface-2">
                                            <th class="border border-brand-border px-2 py-1 text-left">対応ステータス</th>
                                            <th class="border border-brand-border px-2 py-1 text-left">一覧バッジ色</th>
                                            <th class="border border-brand-border px-2 py-1 text-left">Googleカレンダー色</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="border border-brand-border px-2 py-1"><UiBadge variant="neutral" size="sm">未対応</UiBadge></td>
                                            <td class="border border-brand-border px-2 py-1">灰色</td>
                                            <td class="border border-brand-border px-2 py-1">グラファイト（灰色）</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-brand-border px-2 py-1"><UiBadge variant="warning" size="sm">確認中</UiBadge></td>
                                            <td class="border border-brand-border px-2 py-1">黄色</td>
                                            <td class="border border-brand-border px-2 py-1">みかん（オレンジ）</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-brand-border px-2 py-1"><UiBadge variant="info" size="sm">返信待ち</UiBadge></td>
                                            <td class="border border-brand-border px-2 py-1">青色</td>
                                            <td class="border border-brand-border px-2 py-1">みかん（オレンジ）</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-brand-border px-2 py-1"><UiBadge variant="success" size="sm">対応完了済み</UiBadge></td>
                                            <td class="border border-brand-border px-2 py-1">緑色</td>
                                            <td class="border border-brand-border px-2 py-1">トマト（赤）</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <figure class="mt-4">
                                <img :src="img('09_reservation_show_manage.png')" alt="予約詳細 対応管理タブ" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">「対応・管理」タブのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <section id="rsv-comm">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <MessageCircle :size="18" class="text-brand-primary" /> 2-5. メール・LINEで連絡を取る
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                お電話でのやりとりだけでは <strong>「〇〇さんの対応どうなってたっけ？」</strong> と確認する手段がありません。
                                メール・LINEを使うと、誰がいつどんな内容をやりとりしたかが <strong>いつでも確認できる履歴</strong> として残り、引き継ぎ漏れを防げます。積極的にご利用ください。
                            </p>

                            <h3 class="font-semibold text-brand-text mt-3">LINE 連携のご案内方法</h3>
                            <table class="text-xs w-full mt-2 border-collapse">
                                <thead>
                                    <tr class="bg-brand-surface-2">
                                        <th class="border border-brand-border px-2 py-1 text-left">方法</th>
                                        <th class="border border-brand-border px-2 py-1 text-left">内容</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="border border-brand-border px-2 py-1">メール経由</td>
                                        <td class="border border-brand-border px-2 py-1">お客様に送るメールにLINE連携用URLを添えてご案内</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-brand-border px-2 py-1">画面のQRコードを読み取る</td>
                                        <td class="border border-brand-border px-2 py-1">お客様の端末で店舗内画面のQRコードを読み取り</td>
                                    </tr>
                                </tbody>
                            </table>

                            <UiAlert variant="info" class="mt-3 text-xs">
                                📌 <strong>予約一覧画面のLINE連携</strong>は <strong>ご本人1名のみ</strong>連携できます。<br>
                                📌 <strong>顧客一覧画面のLINE連携</strong>は <strong>ご本人＋お母様・おばあ様など複数名</strong>を連携可能です。
                            </UiAlert>

                            <h3 class="font-semibold text-brand-text mt-4">メール</h3>
                            <ul>
                                <li>ご予約時に自動送信される確認メールに、お客様がご返信いただくと、システム内で確認できます。</li>
                                <li>お客様からのご返信時には <strong>LINEグループにも通知</strong>されますので、見落とし防止に役立ちます。</li>
                            </ul>
                            <figure class="mt-4">
                                <img :src="img('10_reservation_show_communication.png')" alt="予約詳細 連絡・履歴タブ" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">「連絡・履歴」タブのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- ====== 3. 顧客一覧 ====== -->
                <section id="cust-index">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Users :size="18" class="text-brand-primary" /> 3-1. お客様を探す／追加する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>左メニュー <strong>【顧客一覧】</strong> から、登録済みのお客様カードをご確認いただけます。</p>

                            <h3 class="font-semibold text-brand-text mt-3">絞り込み検索</h3>
                            <p>画面左側 <strong>「検索条件」</strong> で複数の条件を組み合わせて絞り込めます。</p>
                            <ul>
                                <li><strong>基本情報</strong>：お名前、ふりがな、成人式エリア、お電話番号、登録日範囲</li>
                                <li><strong>成人式情報</strong>：成人式予定年、紹介者</li>
                                <li><strong>成約情報</strong>：ご契約有無、対象プラン</li>
                                <li><strong>制約情報</strong>：制約署名状況（署名済み／未署名）</li>
                                <li><strong>前撮り情報</strong>：前撮り枠割当の有無、撮影日範囲</li>
                                <li><strong>タグ</strong>：設定済みタグでの絞り込み</li>
                            </ul>
                            <p>入力後 <strong>【検索を実行】</strong> をクリック。やり直す場合は <strong>【リセット】</strong>。</p>

                            <h3 class="font-semibold text-brand-text mt-4">新規お客様の登録</h3>
                            <p>① 画面右上の <strong>【＋顧客追加】</strong> をクリック</p>
                            <p>② <strong>お名前は必須</strong>。お電話番号・ご住所・成人式予定年などは可能な範囲でご入力</p>
                            <p>③ <strong>【登録する】</strong> でお客様カードが作成されます</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                💡 同じお名前のお客様が登録済みの場合、注意メッセージが表示されます。重複登録にご注意ください。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('11_customer_index.png')" alt="顧客一覧" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">顧客一覧画面のサンプル</figcaption>
                            </figure>
                            <figure class="mt-4">
                                <img :src="img('12_customer_add_modal.png')" alt="顧客追加モーダル" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">顧客追加モーダルのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- ====== 4. 顧客詳細 ====== -->
                <section id="cust-show">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <FileText :size="18" class="text-brand-primary" /> 4-1. 顧客詳細の全体構成
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>顧客一覧から対象のお客様をクリックすると、詳細画面が開きます。</p>
                            <h3 class="font-semibold text-brand-text mt-2">顧客サマリーパネル（画面上部）</h3>
                            <p>総予約数 / 対応完了数 / キャンセル率 / 成約件数 / 前撮り件数 が一目でご確認いただけます。</p>
                            <h3 class="font-semibold text-brand-text mt-3">3つのタブ構成</h3>
                            <table class="text-xs w-full mt-2 border-collapse">
                                <thead>
                                    <tr class="bg-brand-surface-2">
                                        <th class="border border-brand-border px-2 py-1 text-left">タブ</th>
                                        <th class="border border-brand-border px-2 py-1 text-left">主な内容</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="border border-brand-border px-2 py-1"><strong>概要</strong></td>
                                        <td class="border border-brand-border px-2 py-1">顧客タグ、基本情報（プロフィール・成人式情報・連絡先・システム情報）</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-brand-border px-2 py-1"><strong>詳細情報</strong></td>
                                        <td class="border border-brand-border px-2 py-1">追加情報（振袖アンケート）、参加イベント、成約情報、制約情報、前撮り情報、顧客写真</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-brand-border px-2 py-1"><strong>連絡・メモ</strong></td>
                                        <td class="border border-brand-border px-2 py-1">LINEメッセージ、メールスレッド、連絡メモ</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </UiCard>
                </section>

                <section id="cust-overview">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Users :size="18" class="text-brand-primary" /> 4-2. 概要タブの主な機能
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <h3 class="font-semibold text-brand-text">顧客タグの貼り付け／取り外し</h3>
                            <p>右上 <strong>【＋タグを追加】</strong> から「要フォロー」「クレーム履歴あり」などのタグをお選びいただけます。取り外す場合はタグ横の <strong>×</strong> をクリック。</p>

                            <h3 class="font-semibold text-brand-text mt-3">基本情報の編集</h3>
                            <p>右上 <strong>【編集】</strong> ボタンで、お名前・連絡先・成人式情報などを編集モーダルでご修正いただけます。</p>
                            <p>お電話番号・メールアドレスは <strong>クリックで電話発信／メーラー起動</strong> ができます。</p>
                            <figure class="mt-4">
                                <img :src="img('13_customer_show_overview.png')" alt="顧客詳細 概要タブ" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">概要タブのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <section id="cust-info">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <FileText :size="18" class="text-brand-primary" /> 4-3. 詳細情報タブの主な機能
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <ul>
                                <li><strong>追加情報（振袖アンケート）</strong>：ご来店時のアンケート（来店理由、検討プラン、お持込振袖の有無など）を保存・参照</li>
                                <li><strong>参加イベント</strong>：そのお客様の予約／参加イベント一覧。行クリックで予約詳細へ遷移</li>
                                <li><strong>成約情報</strong>：ご成約済みのプラン・金額・契約日を管理</li>
                                <li><strong>制約情報</strong>：同意書（規約）を <strong>店舗のタブレットで直接ご署名</strong>いただけます</li>
                                <li><strong>前撮り情報</strong>：前撮り枠の予約状況。次の章で登録手順をご案内</li>
                                <li><strong>顧客写真</strong>：お顔写真や試着写真などを保存</li>
                            </ul>
                            <figure class="mt-4">
                                <img :src="img('14_customer_show_info.png')" alt="顧客詳細 詳細情報タブ" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">詳細情報タブのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <section id="cust-photo">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Camera :size="18" class="text-brand-primary" /> 4-4. 顧客詳細から前撮りを登録する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>お客様カードを開いた状態で、その場で前撮り枠をお選びいただける流れです。</p>
                            <p>① 顧客詳細画面の <strong>【詳細情報】</strong> タブを開きます。</p>
                            <p>② ページ内をスクロールし、<strong>「前撮り情報」</strong> セクションをご覧ください。</p>
                            <p>③ 右上の <strong>【＋前撮り追加】</strong> ボタンをクリックすると、<strong>「前撮り情報追加」モーダル</strong>が開きます。</p>
                            <p>④ モーダル内で以下をお選びください。</p>
                            <ul>
                                <li><strong>担当店舗</strong>：撮影を担当する店舗（岡山店／城東店／浜店／福井店）</li>
                                <li><strong>会場（スタジオ）</strong>：岡山ガーデン／いかしの舎／詩仙堂 など</li>
                                <li><strong>撮影日</strong>：空き枠のある日付がプルダウンに表示</li>
                                <li><strong>撮影枠（時間）</strong>：撮影日内の空き時間枠</li>
                                <li><strong>プラン</strong>：振袖フルセット／袴R／購入 など</li>
                                <li><strong>担当者</strong>：撮影に立ち会うスタッフ</li>
                                <li><strong>担当者用メモラベル / 備考</strong>：必要に応じて</li>
                            </ul>
                            <p>⑤ ご入力後 <strong>【登録する】</strong> をクリック。前撮り情報セクションの一覧に新しい枠が追加され、Googleカレンダーにも自動反映されます。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                💡 <strong>担当店舗 → 会場 → 撮影日 → 撮影枠</strong> の順にお選びいただきますと、空き枠だけが絞り込まれるので便利です。
                            </UiAlert>
                            <UiAlert variant="warning" class="mt-3 text-xs">
                                ⚠️ ご注意：<br>
                                ・ 既に他のお客様が割り当てられた枠は表示されません（空き枠のみ）。<br>
                                ・ 誤って登録してしまった場合は、前撮り情報一覧の <strong>【編集】</strong> または <strong>【解除】</strong> ボタンから修正できます。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('15_customer_photo_add_modal.png')" alt="前撮り情報追加モーダル" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">前撮り情報追加モーダルのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <section id="cust-comm">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Mail :size="18" class="text-brand-primary" /> 4-5. 連絡・メモタブの主な機能
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <h3 class="font-semibold text-brand-text">LINEメッセージ</h3>
                            <ul>
                                <li>お客様の LINE 連携状況とメッセージ履歴をご確認いただけます。</li>
                                <li><strong>「LINE 連携用リンクを発行」</strong> から、ご本人だけでなく <strong>お母様・おばあ様など複数名のLINE</strong>もご連携いただけます（予約一覧側のLINE連携は本人のみ）。</li>
                                <li>LINE 不明メッセージ（紐付け前のメッセージ）からの紐付けもこちらから可能です。</li>
                            </ul>
                            <h3 class="font-semibold text-brand-text mt-3">メールスレッド</h3>
                            <p>お客様とのメール送受信履歴がスレッド形式で表示されます。</p>
                            <h3 class="font-semibold text-brand-text mt-3">連絡メモ</h3>
                            <ul>
                                <li>お電話やご来店時のやりとりを、店舗内向けの短い書き留めとして保存できます。</li>
                                <li><strong>誰がいつ書いたか自動で記録</strong>され、過去のやりとりがいつでも確認できます。</li>
                            </ul>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                💡 メモは時系列で並び、お客様ご自身には表示されません（店舗内のみ）。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('16_customer_show_communication.png')" alt="顧客詳細 連絡・メモタブ" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">連絡・メモタブのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- ====== 5. 補足 ====== -->
                <section id="faq">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <HelpCircle :size="18" class="text-brand-primary" /> 5. その他の補足
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <h3 class="font-semibold text-brand-text">操作にお困りの際は</h3>
                            <ul>
                                <li>システム担当（村上）までお気軽にお問い合わせください。</li>
                                <li>「こんな機能があったら便利」というご要望もお待ちしております。</li>
                            </ul>
                            <h3 class="font-semibold text-brand-text mt-3">Googleカレンダー連携について</h3>
                            <ul>
                                <li>予約者と前撮り枠は、<strong>店舗ごとのGoogleカレンダーに自動反映</strong>されます。</li>
                                <li>スマートフォンの Google カレンダーアプリでもご確認いただけます。</li>
                                <li>同期されない場合は連携設定の確認が必要ですので、村上までご連絡ください。</li>
                            </ul>
                        </div>
                    </UiCard>
                </section>

            </div>

            <!-- 目次 -->
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
.manual-layout {
    display: grid;
    grid-template-columns: 1fr 240px;
    gap: 1.5rem;
}
@media (max-width: 900px) {
    .manual-layout { grid-template-columns: 1fr; }
    .manual-toc { display: none; }
}
.manual-main figure img { max-width: 100%; height: auto; }

/* ============ 印刷時（A4縦向き）レイアウト ============ */
@media print {
    /* 目次サイドバーは非表示。本文を全幅に */
    .manual-layout {
        display: block !important;
    }
    .manual-toc {
        display: none !important;
    }
    /* 各章の途中で改ページしないようにする */
    section {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    /* 画像は章をまたがないように */
    figure {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    /* 印刷時の本文文字サイズを 10pt ベースに */
    .manual-main {
        font-size: 10pt;
        line-height: 1.5;
    }
    .manual-main figure img {
        max-width: 100%;
        max-height: 18cm; /* 1ページ収まる目安 */
        object-fit: contain;
    }
    /* 表の罫線が出るように */
    table, th, td {
        border-color: #999 !important;
    }
    /* 注釈/Alert のboxは控えめに */
    [class*="UiAlert"], .ui-alert {
        background: #f9fafb !important;
        border: 1px solid #d1d5db !important;
    }
}
</style>

<style>
/* グローバル印刷ルール: AdminLayout のサイドバー・ヘッダーを非表示にする */
@media print {
    /* 印刷時に隠したい管理画面要素 */
    aside.admin-sidebar, .admin-topbar, header.admin-topbar,
    [class*="AdminSidebar"], [class*="AdminTopbar"],
    nav.admin-nav, .breadcrumb, .page-header-actions {
        display: none !important;
    }
    body, html {
        background: #fff !important;
    }
    /* 印刷時の余白とサイズ */
    @page {
        size: A4 portrait;
        margin: 12mm 12mm 14mm 12mm;
    }
}
</style>
