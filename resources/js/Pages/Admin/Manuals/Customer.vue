<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    BookOpen, Layers, Search, UserPlus, FileText, FileEdit,
    StickyNote, Phone, Tag, Lock, HelpCircle, Users,
} from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiCard, UiPageHeader, UiBadge, UiAlert } from '@/Components/UI';

defineProps({ currentUser: { type: Object, default: null } });

const sections = [
    { id: 'intro',       label: 'はじめに',                    icon: BookOpen },
    { id: 'glossary',    label: '用語のご案内',                icon: Layers },
    { id: 'search',      label: 'お客様を探す',                icon: Search },
    { id: 'register',    label: '新しいお客様を登録する',      icon: UserPlus },
    { id: 'detail',      label: 'お客様の詳細を見る',          icon: FileText },
    { id: 'edit',        label: 'お客様の情報を編集する',      icon: FileEdit },
    { id: 'notes',       label: '連絡メモを追加する',          icon: StickyNote },
    { id: 'contact',     label: '連絡先からご連絡を始める',    icon: Phone },
    { id: 'tags',        label: 'タグを付ける／外す',          icon: Tag },
    { id: 'constraints', label: '同意書を発行する',            icon: Lock },
    { id: 'faq',         label: 'よくあるご質問',              icon: HelpCircle },
];

const activeSection = ref('intro');
let io = null;

onMounted(() => {
    const targets = sections.map((s) => document.getElementById(s.id)).filter(Boolean);
    io = new IntersectionObserver(
        (entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) activeSection.value = e.target.id;
            });
        },
        { rootMargin: '-100px 0px -60% 0px', threshold: 0 },
    );
    targets.forEach((t) => io.observe(t));
});

onUnmounted(() => {
    if (io) io.disconnect();
});

function scrollTo(id) {
    const el = document.getElementById(id);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// マニュアル画像の置き場所
// 注釈版（色枠・矢印・ラベル付き）と原版を切替できる
const showAnnotated = ref(true);
const img = (file) => {
    if (!showAnnotated.value) return `/images/manual/customer/${file}`;
    // 拡張子の前に _annotated を挿入
    const annotated = file.replace(/(\.[^.]+)$/, '_annotated$1');
    return `/images/manual/customer/${annotated}`;
};
</script>

<template>
    <Head title="顧客マニュアル" />

    <AdminLayout :breadcrumb="[{ label: '業務マニュアル', route: 'admin.manuals.index' }, { label: '顧客マニュアル' }]">
        <UiPageHeader
            title="顧客マニュアル"
            description="お客様の検索・登録・編集、メモやタグ、同意書のやりとりまで、顧客機能でできることをまとめています。"
        >
            <template #icon><Users :size="24" /></template>
            <template #actions>
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" v-model="showAnnotated" class="rounded text-brand-primary" />
                    <span>説明用の色枠・矢印を表示</span>
                </label>
            </template>
        </UiPageHeader>

        <div class="manual-layout mt-4">
            <!-- 本文 -->
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
                                このマニュアルでは、画面左側の <strong>「顧客一覧」</strong> から開く、お客様の管理機能の使い方をご案内します。
                            </p>
                            <p>
                                ご来店いただいたお客様や、お電話・LINE・イベント予約からお問い合わせいただいた方をお客様カードとして登録し、保護者様のお名前、ご住所、お電話番号、ご来店時のメモ、同意書（制約）の取り交わしなど、対応に必要な情報を1か所にまとめてお持ちいただけます。
                            </p>
                            <UiAlert variant="info" class="mt-3">
                                <span class="text-xs">
                                    ご不明な点がございましたら、各章の <strong>よくあるご質問</strong> または、システム担当（村上）までお問い合わせください。
                                </span>
                            </UiAlert>
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
                                <dt class="font-semibold text-brand-text">お客様カード</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お一人のお客様に対して1枚作る、システム上の登録情報のことです。ご本人のお名前・保護者様のお名前・ご住所などをまとめて記録できます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">担当店舗</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    そのお客様を主にご担当する店舗です（岡山店／城東店／浜店／福井店）。ご担当の変更は詳細画面で行います。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">成人式予定年</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お客様が成人式にご出席される予定の年（西暦）です。同じ学年でグループにして検索したり、ご案内メールを送る際にお使いいただけます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">タグ</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お客様カードに貼り付ける付箋のようなものです。「要フォロー」「クレーム履歴あり」などをタグで表示し、対応時の目印にできます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">制約（どうい書）</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お持込振袖の取り扱いやご利用規約への同意などを、お客様にご署名いただく書類のことです。タブレットでのご署名にも対応しています。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">連絡メモ</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お電話・ご来店時のやりとりを記録する、店舗内向けの短い書き留めです。次回ご来店時のご案内にお役立てください。
                                </dd>
                            </div>
                        </dl>
                    </UiCard>
                </section>

                <!-- 3. お客様を探す -->
                <section id="search">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Search :size="18" class="text-brand-primary" /> 3. お客様を探す
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <h3 class="font-semibold text-brand-text mt-2">基本の流れ</h3>
                            <p>① 左のサイドバーから <strong>【顧客一覧】</strong> をお開きください。</p>
                            <p>② 画面上の <strong>検索ボックス</strong> に、お探しの方のお名前（ふりがな可）・お電話番号・郵便番号のいずれかをご入力ください。</p>
                            <p>③ 検索結果の表で、お探しの方の行をクリックすると、お客様カードの詳細画面が開きます。</p>

                            <h3 class="font-semibold text-brand-text mt-4">絞り込みの使い方</h3>
                            <p>
                                検索ボックスの右にある <strong>【絞り込み】</strong> をクリックすると、担当店舗・成人式予定年・タグ・登録日範囲などで結果を絞ることができます。
                            </p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：お名前は漢字とふりがな、どちらでもお探しいただけます。一部のお名前だけでもかまいません（例：「山田」だけ）。
                            </UiAlert>
                            <!-- 画像 -->
                            <figure class="mt-4">
                                <img :src="img('01_customer_index.png')" alt="顧客一覧画面" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">顧客一覧画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 4. 新しいお客様を登録する -->
                <section id="register">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <UserPlus :size="18" class="text-brand-primary" /> 4. 新しいお客様を登録する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>① 顧客一覧画面の右上にある <strong>【＋新規登録】</strong> ボタンをクリックします。</p>
                            <p>② お客様のお名前（必須）・ふりがな・ご担当店舗・お電話番号・ご住所などをご入力ください。</p>
                            <p>③ 入力が終わりましたら、画面下の <strong>【登録する】</strong> をクリックします。</p>
                            <UiAlert variant="warning" class="mt-3 text-xs">
                                ご注意：同じお名前のお客様がすでに登録されている場合は、登録の前にメッセージが表示されます。重複登録にならないよう、内容をご確認ください。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('02_customer_create_form.png')" alt="新規登録フォーム" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">新規登録フォームのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 5. お客様の詳細を見る -->
                <section id="detail">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <FileText :size="18" class="text-brand-primary" /> 5. お客様の詳細を見る
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                詳細画面は <strong>タブ形式</strong> になっており、画面上部のタブで切り替えながら情報をご確認いただけます。
                            </p>
                            <ul>
                                <li><strong>概要</strong>：お名前・ご連絡先・ご住所など基本情報</li>
                                <li><strong>予約</strong>：このお客様のご予約一覧</li>
                                <li><strong>前撮り</strong>：割り当てられた撮影枠</li>
                                <li><strong>メモ</strong>：店舗で書き留めた連絡メモ</li>
                                <li><strong>制約</strong>：取り交わし済みの同意書</li>
                                <li><strong>LINE</strong>：LINEメッセージのやりとり履歴</li>
                            </ul>
                            <figure class="mt-4">
                                <img :src="img('03_customer_show_tabs.png')" alt="詳細タブ" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">タブ切り替えのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 6. お客様の情報を編集する -->
                <section id="edit">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <FileEdit :size="18" class="text-brand-primary" /> 6. お客様の情報を編集する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>① 詳細画面の <strong>「基本情報」</strong> セクションにある <strong>【編集】</strong> ボタンをクリックします。</p>
                            <p>② 編集モーダル（小さな入力画面）が開きますので、変更が必要な項目を書き換えてください。</p>
                            <p>③ 画面下の <strong>【保存する】</strong> をクリックすると、内容が反映されます。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：「成人式情報」「成約情報」「制約情報」など項目ごとに編集ボタンが用意されています。修正したい項目の近くの【編集】をお使いください。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('05_customer_edit_modal.png')" alt="編集モーダル" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">基本情報の編集モーダルのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 7. 連絡メモを追加する -->
                <section id="notes">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <StickyNote :size="18" class="text-brand-primary" /> 7. 連絡メモを追加する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>① 詳細画面の <strong>【メモ】</strong> タブを開きます。</p>
                            <p>② 上の入力欄にお書き留めの内容をご入力ください。</p>
                            <p>③ <strong>【追加する】</strong> をクリックすると一覧に反映されます。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                メモは時系列で並び、誰が書いたか自動で記録されます。お客様ご自身には表示されません（店舗内向け）。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('04_customer_notes.png')" alt="メモタブ" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">連絡メモのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 8. 連絡先からご連絡を始める -->
                <section id="contact">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Phone :size="18" class="text-brand-primary" /> 8. 連絡先からご連絡を始める
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                詳細画面の <strong>「基本情報」</strong> 欄に表示されているお電話番号やメールアドレスは、クリックするだけでご連絡を始められます。
                            </p>
                            <p>
                                ① 詳細画面の <strong>【概要】</strong> タブを開きます。
                            </p>
                            <p>
                                ② <strong>「基本情報」</strong> 欄のお電話番号をクリックすると、お使いのパソコンの電話アプリが起動します（タブレットやスマートフォンの場合はそのままお電話がかけられます）。
                            </p>
                            <p>
                                ③ メールアドレスをクリックすると、ご利用中のメールソフトが立ち上がり、宛先がご入力済みの新規メールが開きます。
                            </p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：お電話番号やメールアドレスが間違って登録されている場合は、<strong>6 番（お客様の情報を編集する）</strong> をご参考に修正してください。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('06_customer_contact_info.png')" alt="連絡先情報" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">連絡先情報のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 9. タグを付ける／外す -->
                <section id="tags">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Tag :size="18" class="text-brand-primary" /> 9. タグを付ける／外す
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>① 詳細画面の <strong>【概要】</strong> タブ内にある <strong>「顧客タグ」</strong> 欄をご覧ください。</p>
                            <p>② <strong>【＋タグを追加】</strong> をクリックし、ご用意されているタグの中からお選びください。</p>
                            <p>③ 不要なタグを外す場合は、タグの右側にある <strong>×</strong> をクリックします。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                タグの種類（例：「要フォロー」「クレーム履歴あり」など）を増やしたい場合は、<strong>顧客 ＞ 顧客タグ</strong> から管理者にご相談ください。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('07_customer_tags.png')" alt="タグ追加" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">顧客タグ欄のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 10. 同意書を発行する -->
                <section id="constraints">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Lock :size="18" class="text-brand-primary" /> 10. 同意書を発行する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                お持込振袖規約などの <strong>同意書（制約）</strong> を、システム上でご署名いただけます。
                            </p>
                            <p>① 詳細画面の <strong>【詳細情報】</strong> タブを開き、<strong>「制約情報」</strong> 欄をご覧ください。</p>
                            <p>② <strong>【＋制約追加】</strong> をクリックし、ご署名いただくテンプレートをお選びください。</p>
                            <p>③ 店舗のタブレットでご署名いただきます。ご署名が完了しますと、<UiBadge variant="success" size="sm">署名済み</UiBadge> と表示されます。</p>
                            <UiAlert variant="warning" class="mt-3 text-xs">
                                ご注意：未署名のまま放置されたものは、再度ご来店時にご署名のご案内をお願いします。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('08_customer_constraint.png')" alt="制約追加" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">制約情報の追加画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 11. よくあるご質問 -->
                <section id="faq">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <HelpCircle :size="18" class="text-brand-primary" /> 11. よくあるご質問
                            </h2>
                        </template>
                        <dl class="text-sm space-y-4">
                            <div>
                                <dt class="font-semibold text-brand-text">Q. 同じお客様が二重に登録されてしまいました。どうすれば？</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    片方の詳細画面の <strong>【完全削除】</strong> ボタンで削除いただけます。削除する前に、メモやご予約が紐づいているかをご確認ください。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. 担当店舗を間違えて登録してしまいました。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    詳細画面の <strong>【編集】</strong> から、担当店舗を変更できます。変更履歴はログに残ります。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. お客様にメールを送るにはどうしますか？</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    詳細画面の <strong>基本情報</strong> 欄に表示されているメールアドレスをクリックしてください。ご利用中のメールソフト（OutlookやGmail等）が立ち上がり、宛先がご入力済みの新規メールが開きます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. 検索しても見つかりません。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    お名前にスペースが入っていないか、ふりがな・お電話番号でも検索してみてください。それでも見つからない場合は、未登録の可能性がございますので新規登録をお願いします。
                                </dd>
                            </div>
                        </dl>
                    </UiCard>
                </section>

            </div>

            <!-- 右側 目次 -->
            <aside class="manual-toc">
                <div class="sticky top-4 space-y-2">
                    <UiCard variant="default" padding="sm">
                        <template #header>
                            <h3 class="font-serif text-sm">目次</h3>
                        </template>
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
    grid-template-columns: 1fr 220px;
    gap: 1.5rem;
}
@media (max-width: 900px) {
    .manual-layout {
        grid-template-columns: 1fr;
    }
    .manual-toc {
        display: none;
    }
}
.manual-main figure img {
    max-width: 100%;
    height: auto;
}
</style>
