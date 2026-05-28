<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    BookOpen, Layers, Calendar, PlusCircle, UserCheck, UserX, Trash2,
    Building2, Cloud, HelpCircle, Camera,
} from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiCard, UiPageHeader, UiBadge, UiAlert } from '@/Components/UI';

defineProps({ currentUser: { type: Object, default: null } });

const sections = [
    { id: 'intro',       label: 'はじめに',                    icon: BookOpen },
    { id: 'glossary',    label: '用語のご案内',                icon: Layers },
    { id: 'list',        label: '前撮り枠一覧の見方',          icon: Calendar },
    { id: 'create',      label: '新しい枠を登録する',          icon: PlusCircle },
    { id: 'assign',      label: 'お客様を割り当てる',          icon: UserCheck },
    { id: 'release',     label: 'お客様の紐付けを解除する',    icon: UserX },
    { id: 'delete',      label: '枠を削除する',                icon: Trash2 },
    { id: 'studios',     label: 'スタジオを管理する',          icon: Building2 },
    { id: 'calendar',    label: 'Googleカレンダーの反映',      icon: Cloud },
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

const showAnnotated = ref(true);
const img = (file) => {
    if (!showAnnotated.value) return `/images/manual/photo-slot/${file}`;
    return `/images/manual/photo-slot/${file.replace(/(\.[^.]+)$/, '_annotated$1')}`;
};
</script>

<template>
    <Head title="前撮りマニュアル" />

    <AdminLayout :breadcrumb="[{ label: '業務マニュアル', route: 'admin.manuals.index' }, { label: '前撮りマニュアル' }]">
        <UiPageHeader
            title="前撮りマニュアル"
            description="撮影日・時刻の枠の登録、お客様の割り当て、Googleカレンダーへの反映までの一連の流れをご案内します。"
        >
            <template #icon><Camera :size="24" /></template>
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
                                このマニュアルでは、画面左側の <strong>「前撮り枠」</strong> から開く、前撮り撮影の枠を管理する機能をご案内します。
                            </p>
                            <p>
                                各スタジオ（岡山ガーデン・いかしの舎・詩仙堂など）で、撮影日と時刻の <strong>「枠」</strong> を登録し、その枠にお客様を割り当てると、自動的にスタッフ用Googleカレンダーへ予定が反映される仕組みです。
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
                                <dt class="font-semibold text-brand-text">撮影枠（前撮り枠）</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    1組のお客様の撮影に使う、日付＋時刻のセットのことです。例：「2026年12月12日 14時00分 / 岡山ガーデン」。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">スタジオ（会場）</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    撮影を行う場所のことです。岡山ガーデン・いかしの舎・詩仙堂などがあらかじめ登録されています。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">担当店舗</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    その枠を担当する店舗（岡山店・城東店など）です。Googleカレンダーへの反映先は店舗ごとに分かれます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">お客様の割り当て</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    撮影枠にお客様を結び付ける操作です。割り当てると、Googleカレンダーの予定タイトルに「<strong>[前撮り] お客様名（会場名）</strong>」と表示されます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">紐付け解除</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    一度割り当てたお客様を外す操作です。枠は残り、お客様だけ外れます。
                                </dd>
                            </div>
                        </dl>
                    </UiCard>
                </section>

                <!-- 3. 前撮り枠一覧の見方 -->
                <section id="list">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Calendar :size="18" class="text-brand-primary" /> 3. 前撮り枠一覧の見方
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>① 左のサイドバーから <strong>【前撮り枠】</strong> をお開きください。</p>
                            <p>② 画面上部で <strong>「年」「月」「日」「店舗」「会場」</strong> を指定し、表示する枠を絞り込めます。</p>
                            <p>③ 絞り込み結果が日付ごとにまとめて表示されます。お客様名が入っている枠は予約済み、空欄の枠は未予約の枠です。</p>
                            <figure class="mt-4">
                                <img :src="img('01_photo_slot_index.png')" alt="前撮り枠一覧" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">前撮り枠一覧画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 4. 新しい枠を登録する -->
                <section id="create">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <PlusCircle :size="18" class="text-brand-primary" /> 4. 新しい枠を登録する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>① 一覧画面の右上にある <strong>【＋新規追加】</strong> ボタンをクリックします。</p>
                            <p>② <strong>「スタジオ」「撮影日」「撮影時刻」「担当店舗」</strong> を選択し、必要であれば <strong>「お客様」「プラン」「担当者」</strong> もあわせて指定してください。</p>
                            <p>③ 画面下の <strong>【登録する】</strong> をクリックすると、枠が作成されます。</p>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：同じスタジオ・同じ日時の枠を二度登録しようとすると、エラーで止まる仕組みになっています。重複の心配は不要です。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('02_photo_slot_create.png')" alt="新規追加画面" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">新規追加画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 5. お客様を割り当てる -->
                <section id="assign">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <UserCheck :size="18" class="text-brand-primary" /> 5. お客様を割り当てる
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                登録済みの空き枠にお客様を後から結び付ける操作です。
                            </p>
                            <p>① 一覧画面で、対象の枠の行にある <strong>【編集】</strong> ボタンをクリックします。</p>
                            <p>② 編集モーダル内の <strong>「お客様」</strong> 欄から、お客様をお選びください。お名前の一部を入力すると候補が絞り込まれます。</p>
                            <p>③ あわせて <strong>「プラン」「担当者」「備考」</strong> もご入力ください。</p>
                            <p>④ <strong>【保存する】</strong> をクリックすると、お客様の情報が枠に紐付き、Googleカレンダー上の予定タイトルも自動更新されます。</p>
                            <figure class="mt-4">
                                <img :src="img('03_photo_slot_edit_modal.png')" alt="編集モーダル" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">編集モーダルでお客様を割り当てるサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 6. お客様の紐付けを解除する -->
                <section id="release">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <UserX :size="18" class="text-brand-primary" /> 6. お客様の紐付けを解除する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                ご予約のキャンセルなどで、お客様の情報だけ外したい場合にお使いください。枠そのものは残ります。
                            </p>
                            <p>① 一覧画面で、対象の枠の行にある <strong>【解除】</strong> ボタン（ユーザーから×印のアイコン）をクリックします。</p>
                            <p>② 確認モーダルが表示されますので、内容をご確認のうえ <strong>【解除する】</strong> をクリックします。</p>
                            <p>③ 紐付けが外れ、Googleカレンダー上のタイトルも <strong>「[前撮り] 未予約（会場名）」</strong> に戻ります（色もグレーに変わります）。</p>
                            <UiAlert variant="warning" class="mt-3 text-xs">
                                ご注意：枠そのものを消したい場合は、解除した後に <strong>「7. 枠を削除する」</strong> をご参考に削除してください。
                            </UiAlert>
                            <figure class="mt-4">
                                <img :src="img('04_photo_slot_release_modal.png')" alt="解除モーダル" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">解除確認モーダルのサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 7. 枠を削除する -->
                <section id="delete">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Trash2 :size="18" class="text-brand-primary" /> 7. 枠を削除する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>① 削除したい枠の行にある <strong>【削除】</strong> ボタンをクリックします。</p>
                            <p>② 確認モーダルで <strong>【削除する】</strong> をクリックします。</p>
                            <UiAlert variant="warning" class="mt-3 text-xs">
                                ご注意：お客様が紐付いている枠は削除できません。先に <strong>「6. 紐付けを解除」</strong> を行ってから削除してください。
                            </UiAlert>
                        </div>
                    </UiCard>
                </section>

                <!-- 8. スタジオを管理する -->
                <section id="studios">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Building2 :size="18" class="text-brand-primary" /> 8. スタジオを管理する
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                撮影場所を増やしたいときは、サイドバー <strong>「スタジオ」</strong> から登録できます。
                            </p>
                            <p>① 左のサイドバーから <strong>【スタジオ】</strong> をお開きください。</p>
                            <p>② 右上の <strong>【＋新規追加】</strong> から、新しいスタジオ名・住所などをご入力ください。</p>
                            <p>③ 登録後は、前撮り枠の登録時にお選びいただける一覧に加わります。</p>
                            <figure class="mt-4">
                                <img :src="img('05_photo_studios.png')" alt="スタジオ一覧" class="border rounded shadow-sm" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">スタジオ一覧画面のサンプル</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 9. Googleカレンダーの反映 -->
                <section id="calendar">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Cloud :size="18" class="text-brand-primary" /> 9. Googleカレンダーの反映
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                前撮り枠の登録・編集・解除を行うと、<strong>担当店舗のGoogleカレンダー</strong> に自動的に予定が反映されます。スタッフの皆様は、ご自身のスマートフォン等のGoogleカレンダーアプリでもご確認いただけます。
                            </p>
                            <ul>
                                <li><strong>お客様割当済み</strong>：予定タイトルが「[前撮り] お客様名（会場名）」、色は <UiBadge variant="success" size="sm">セージ（緑）</UiBadge> で表示されます。</li>
                                <li><strong>お客様未割当</strong>：予定タイトルが「[前撮り] 未予約（会場名）」、色は <UiBadge variant="neutral" size="sm">グラファイト（灰色）</UiBadge> で表示されます。</li>
                            </ul>
                            <UiAlert variant="info" class="mt-3 text-xs">
                                ヒント：予定をクリックすると、説明欄に「お客様情報」「枠詳細URL」などが表示されます。
                            </UiAlert>
                        </div>
                    </UiCard>
                </section>

                <!-- 10. よくあるご質問 -->
                <section id="faq">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <HelpCircle :size="18" class="text-brand-primary" /> 10. よくあるご質問
                            </h2>
                        </template>
                        <dl class="text-sm space-y-4">
                            <div>
                                <dt class="font-semibold text-brand-text">Q. Googleカレンダーに反映されません。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    担当店舗にGoogleカレンダーが連携されているかご確認ください。連携状況はシステム担当（村上）までお問い合わせください。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. 同じ日に複数の枠を一気に登録したいです。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    新規追加画面では、撮影時刻を複数指定できます。例：「09:00, 10:00, 11:00, ...」のように時間ごとに枠を一括作成できます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">Q. 別店舗の枠が見えてしまいます。</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    画面上の <strong>「店舗」</strong> 絞り込みでご自身の店舗を選ぶと、自店分だけが表示されます。
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
    grid-template-columns: 1fr 220px;
    gap: 1.5rem;
}
@media (max-width: 900px) {
    .manual-layout { grid-template-columns: 1fr; }
    .manual-toc { display: none; }
}
.manual-main figure img {
    max-width: 100%;
    height: auto;
}
</style>
