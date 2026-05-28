<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    BookOpen, Layers, MessageCircle, MailQuestion, Tag, Lock,
    Film, Images, HelpCircle,
} from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiCard, UiPageHeader, UiAlert } from '@/Components/UI';

defineProps({ currentUser: { type: Object, default: null } });

const sections = [
    { id: 'intro',          label: 'はじめに',                  icon: BookOpen },
    { id: 'glossary',       label: '用語のご案内',              icon: Layers },
    { id: 'line-contacts',  label: 'LINE連携を確認する',        icon: MessageCircle },
    { id: 'line-unknown',   label: '不明メッセージを処理する',  icon: MailQuestion },
    { id: 'tags',           label: '顧客タグを管理する',        icon: Tag },
    { id: 'constraints',    label: '制約テンプレートを作る',    icon: Lock },
    { id: 'slideshow',      label: 'スライドショーを管理する',  icon: Film },
    { id: 'media',          label: 'メディアライブラリ',        icon: Images },
    { id: 'faq',            label: 'よくあるご質問',            icon: HelpCircle },
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
    if (!showAnnotated.value) return `/images/manual/peripheral/${file}`;
    return `/images/manual/peripheral/${file.replace(/(\.[^.]+)$/, '_annotated$1')}`;
};
</script>

<template>
    <Head title="LINE・タグ・制約・スライドショー マニュアル" />

    <AdminLayout :breadcrumb="[{ label: '業務マニュアル', route: 'admin.manuals.index' }, { label: '周辺機能マニュアル' }]">
        <UiPageHeader
            title="LINE・タグ・制約・スライドショー"
            description="お客様対応を補助する周辺機能（LINE連携、顧客タグ、制約テンプレート、スライドショー、メディアライブラリ）の使い方をご案内します。"
        >
            <template #icon><MessageCircle :size="24" /></template>
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
                                このマニュアルでは、お客様対応を補助する周辺機能をご案内します。
                            </p>
                            <p>
                                どれも毎日お使いいただく機能ではありませんが、必要に応じてご活用いただくと、お客様とのコミュニケーションがより円滑になります。
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
                                <dt class="font-semibold text-brand-text">LINE連携</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お客様の LINE と本システムを結び付ける機能です。連携が済むと、お客様とのLINEメッセージのやりとりがシステム上でも確認できます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">不明メッセージ</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    どのお客様からのメッセージか自動で判別できなかった LINE のメッセージのことです。手動で「このお客様です」と紐付けて整理できます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">顧客タグ</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お客様カードに貼り付ける付箋のようなものです。「要フォロー」「クレーム履歴あり」などの目印として利用します。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">制約テンプレート</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お客様にご署名いただく同意書（規約）のひな型のことです。「振袖お持ち込み規約」「ご利用規約」などをあらかじめご用意いただけます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">スライドショー</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お客様のご予約ページやTOP画像で、複数の写真を順番に切り替えて表示する機能です。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">メディアライブラリ</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    一度アップロードした画像を、何度でも使い回せる画像の保管庫です。
                                </dd>
                            </div>
                        </dl>
                    </UiCard>
                </section>

                <!-- 3. LINE連携を確認する -->
                <section id="line-contacts">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <MessageCircle :size="18" class="text-brand-primary" /> 3. LINE連携を確認する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>① 左のサイドバーから <strong>【LINE連携】</strong> をお開きください。</p>
                            <p>② 一覧には、これまでに LINE と紐付けられたお客様が表示されます。</p>
                            <p>③ 行をクリックすると、お客様の詳細画面に飛び、過去のメッセージのやりとりがご確認いただけます。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：新しく LINE連携をしたいときは、<strong>顧客詳細画面の【連絡・メモ】タブ</strong> から「連携用リンク」を発行できます（顧客マニュアル参照）。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('01_line_contacts.png')" alt="LINE連携一覧" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">LINE連携一覧のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 4. 不明メッセージを処理する -->
                <section id="line-unknown">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <MailQuestion :size="18" class="text-brand-primary" /> 4. 不明メッセージを処理する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                お客様の LINE 連携前のメッセージや、どのお客様か判別できない LINE は <strong>「不明メッセージ」</strong> として一時的に保管されます。
                            </p>
                            <p>① 左のサイドバーから <strong>【不明メッセージ】</strong> をお開きください。</p>
                            <p>② 一覧にある未処理のメッセージをクリックして、内容をご確認ください。</p>
                            <p>③ 該当するお客様が分かった場合は、<strong>【お客様に紐付ける】</strong> ボタンからお選びください。</p>
                            <UiAlert variant="warning" class="mt-3 text-xs">
                                ご注意：誤ったお客様に紐付けますと、お客様の LINE 履歴が混ざってしまいます。お名前・お電話番号などを確認の上、慎重にお願いします。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('02_line_unknown.png')" alt="不明メッセージ" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">不明メッセージ一覧のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 5. 顧客タグを管理する -->
                <section id="tags">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Tag :size="18" class="text-brand-primary" /> 5. 顧客タグを管理する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                顧客タグの種類（「要フォロー」「クレーム履歴あり」など）は、こちらで追加・編集・削除できます。
                            </p>
                            <p>① 左のサイドバーから <strong>【顧客タグ】</strong> をお開きください。</p>
                            <p>② 一覧に登録済みのタグが並びます。右上の <strong>【＋新規タグ】</strong> から、新しいタグを追加できます。</p>
                            <p>③ タグごとに <strong>色</strong> を選んで設定いただけます。お客様カードに貼った際の見た目が変わり、ひと目で意味を把握しやすくなります。</p>
                            <figure class="mt-4">
                                <img :src="img('03_customer_tags.png')" alt="顧客タグ一覧" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">顧客タグ一覧のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 6. 制約テンプレート -->
                <section id="constraints">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Lock :size="18" class="text-brand-primary" /> 6. 制約テンプレート（同意書のひな型）を作る
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                お客様にご署名いただく同意書のひな型を、こちらで作成・編集できます。
                            </p>
                            <p>① 左のサイドバーから <strong>【制約テンプレート】</strong> をお開きください。</p>
                            <p>② 一覧から既存のひな型を確認・編集いただけます。</p>
                            <p>③ 右上の <strong>【＋新規テンプレート】</strong> から、新しいひな型を追加できます。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：ひな型本文の途中にチェックボックスを差し込むことで、「私は…に同意します」のような項目ごとのご同意を取ることができます。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('04_constraint_templates.png')" alt="制約テンプレート一覧" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">制約テンプレート一覧のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 7. スライドショー -->
                <section id="slideshow">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Film :size="18" class="text-brand-primary" /> 7. スライドショーを管理する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                お客様向け公開ページのTOPなどで使う、複数の写真を切り替えて見せる機能を管理できます。
                            </p>
                            <p>① 左のサイドバーから <strong>【スライドショー】</strong> をお開きください。</p>
                            <p>② 一覧から既存のスライドショーをご確認いただけます。</p>
                            <p>③ <strong>【＋新規追加】</strong> から、新しいスライドショーを作成し、表示順や画像を組み合わせていただけます。</p>
                            <figure class="mt-4">
                                <img :src="img('05_slideshows.png')" alt="スライドショー一覧" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">スライドショー一覧のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 8. メディアライブラリ -->
                <section id="media">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Images :size="18" class="text-brand-primary" /> 8. メディアライブラリ
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                一度アップロードした画像を保管しておき、別のイベントやスライドショーでもお使いいただける、画像の <strong>使い回し用フォルダ</strong> です。
                            </p>
                            <p>① 左のサイドバーから <strong>【メディアライブラリ】</strong> をお開きください。</p>
                            <p>② 画面上の <strong>【画像をアップロード】</strong> から、お手元の画像をアップロードしてください。</p>
                            <p>③ アップロードした画像は、イベントの画像追加画面で <strong>「ライブラリから選ぶ」</strong> でお選びいただけます。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：画像の容量は1枚あたり2〜5MBまでにすると、表示が速く快適になります。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('06_media.png')" alt="メディアライブラリ" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">メディアライブラリのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 9. FAQ -->
                <section id="faq">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <HelpCircle :size="18" class="text-brand-primary" /> 9. よくあるご質問
                            </h2>
                        </template>
                        <dl class="text-sm space-y-4">
                            <div>
                                <dt class="font-semibold text-brand-text">Q. LINE連携を解除するにはどうしますか？</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お客様詳細画面の <strong>【連絡・メモ】</strong> タブで、該当の LINE連絡先の右側にある <strong>「解除」</strong> ボタンをクリックしてください。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. 不明メッセージにスパム的なものが届きました。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    内容を確認のうえ、対象メッセージを <strong>【削除】</strong> ボタンで削除いただけます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. スライドショーの順番を入れ替えたいです。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    スライドショー詳細画面で画像をドラッグして並べ替えていただけます。並べ替え後は <strong>【保存する】</strong> をお忘れなく。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. メディアライブラリの古い画像を削除したいです。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    画像をクリックして詳細を開き、<strong>【削除】</strong> ボタンをご利用ください。すでにイベントやスライドショーで使われている画像は削除できない場合があります。
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
