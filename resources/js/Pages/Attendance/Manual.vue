<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen, Clock, FileEdit, History, CheckCircle2,
    Settings, Calendar, DollarSign, Calculator, Briefcase,
    HelpCircle, AlertTriangle, Info, Layers,
    Download, Printer,
} from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiCard, UiPageHeader, UiBadge, UiAlert } from '@/Components/UI';

const props = defineProps({
    currentUser: { type: Object, default: null },
});

const isManager = computed(() => !!props.currentUser?.canManageAttendance);
const isAttendanceManager = computed(() => !!props.currentUser?.isAttendanceManager);

const sections = computed(() => {
    const base = [
        { id: 'intro',         label: 'はじめに',          icon: BookOpen },
        { id: 'glossary',      label: '用語集',            icon: Layers },
        { id: 'punch',         label: '打刻',              icon: Clock },
        { id: 'cancel',        label: '直前の打刻取消',    icon: AlertTriangle },
        { id: 'provisional',   label: '日付指定で仮登録',  icon: FileEdit },
        { id: 'history',       label: '勤怠履歴',          icon: History },
        { id: 'apply',         label: '申請',              icon: CheckCircle2 },
    ];
    if (isManager.value) {
        base.push({ id: 'approvals', label: '承認依頼（管理者）', icon: CheckCircle2 });
        base.push({ id: 'admin-attendance', label: '勤怠管理（管理者）', icon: Settings });
    }
    if (isAttendanceManager.value) {
        base.push({ id: 'work-attribute', label: '勤務属性マスタ', icon: Briefcase });
        base.push({ id: 'company-calendar', label: '会社カレンダー', icon: Calendar });
        base.push({ id: 'payroll-settings', label: '給与計算閾値', icon: DollarSign });
        base.push({ id: 'payroll-simulator', label: '給与シミュレーター', icon: Calculator });
    }
    base.push({ id: 'faq', label: 'よくあるご質問', icon: HelpCircle });
    return base;
});

const activeSection = ref('intro');
const observers = [];

onMounted(() => {
    const targets = sections.value.map((s) => document.getElementById(s.id)).filter(Boolean);
    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) activeSection.value = e.target.id;
            });
        },
        { rootMargin: '-100px 0px -60% 0px', threshold: 0 }
    );
    targets.forEach((t) => io.observe(t));
    observers.push(io);
});

onUnmounted(() => {
    observers.forEach((o) => o.disconnect());
});

const scrollTo = (id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const img = (file) => `/images/manual/attendance/${file}`;
</script>

<template>
    <Head title="勤怠マニュアル" />
    <AdminLayout :breadcrumb="[{ label: '勤怠' }, { label: '勤怠マニュアル' }]">

        <UiPageHeader
            title="勤怠マニュアル"
            description="出勤・退勤の打刻、休憩登録、仮登録、申請から、管理者向けの承認・休暇登録・残業判定方式・閾値設定・月次集計CSVまで、本システムの勤怠機能の使い方をまとめています。"
        >
            <template #actions>
                <div class="flex items-center gap-3 flex-wrap">
                    <a
                        href="/manuals/勤怠マニュアル.pdf"
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
                </div>
            </template>
        </UiPageHeader>

        <div class="manual-layout">
            <!-- 本文 -->
            <div class="space-y-6 min-w-0 manual-main">

                <!-- 1. はじめに -->
                <section id="intro">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <BookOpen :size="18" class="text-brand-primary" /> はじめに
                            </h2>
                        </template>
                        <div class="prose prose-sm max-w-none text-brand-text leading-relaxed">
                            <p>
                                本システムでは、出勤・退勤の打刻から、休憩の登録、月次の勤怠履歴の確認、申請、承認、休暇（有給・特別休暇・欠勤）の登録、残業判定方式・給与計算閾値の設定、月次集計CSVの出力まで、勤怠管理に必要な機能を一通り提供します。
                            </p>
                            <p>このマニュアルは、サイドバー左側の <strong>「勤怠」</strong>グループ内にあるすべての機能の使い方をまとめたものです。 ご自身のロール（一般スタッフ／管理者／勤怠管理者）に応じて、表示される章だけ参照すれば OK です。</p>
                            <UiAlert variant="warning" class="mt-3">
                                <span class="text-xs">
                                    <strong>休憩の登録方法が変わりました。</strong> 以前の「休憩開始／休憩終了」ボタンによるリアルタイム打刻は廃止され、<strong>休憩登録モーダルから取得した休憩時間（分）を後から登録する方式</strong>になりました。所定休憩が決まっている方は登録自体が不要です（自動控除）。詳しくは「打刻」章をご覧ください。
                                </span>
                            </UiAlert>
                            <UiAlert variant="info" class="mt-3">
                                <span class="text-xs">
                                    あなたの現在のロール:
                                    <strong v-if="isAttendanceManager">勤怠管理者</strong>
                                    <strong v-else-if="isManager">管理者（自店舗のみ承認可）</strong>
                                    <strong v-else>一般スタッフ</strong>
                                </span>
                            </UiAlert>
                        </div>
                    </UiCard>
                </section>

                <!-- 2. 用語集 -->
                <section id="glossary">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Layers :size="18" class="text-brand-primary" /> 用語集
                            </h2>
                        </template>
                        <dl class="text-sm space-y-3">
                            <div>
                                <dt class="font-semibold text-brand-text">ステータス</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    勤怠1件ごとの状態。
                                    <UiBadge variant="neutral" size="sm" class="mx-1">未申請</UiBadge>＝下書き、
                                    <UiBadge variant="warning" size="sm" class="mx-1">申請済</UiBadge>＝管理者の承認待ち、
                                    <UiBadge variant="success" size="sm" class="mx-1">承認済</UiBadge>＝確定。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">シフトパターン（A / B / C）</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    会社カレンダーに登録する1日の業務時間帯のパターンです。 勤務属性マスタで「A=09:00–18:00」「B=10:00–19:00」「C=13:00–22:00」のように、属性ごとに時間が設定されています。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">ベース業務（シフト）</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    会社カレンダーのパターン × あなたの勤務属性 × 平日/土日 で決まる、その日の業務開始～終了時刻です。 早出・遅刻・早退の判定は、このベース時刻が取得できる勤務属性でのみ行われます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">残業の判定方式（パターン方式 / 閾値方式）</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    残業の決め方は勤務属性ごとに2種類あります。
                                    <strong>パターン方式</strong>＝退勤打刻が<strong>ベース業務終了より後</strong>になった分を残業とする方式（主に正社員）。
                                    <strong>閾値方式</strong>＝1日の<strong>実働時間が「残業閾値」（例：480分＝8時間）を超えた分</strong>を残業とする方式（主にパート・時短）。 どちらを使うかは勤怠管理者が勤務属性ごとに設定します。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">深夜残業</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    残業のうち <strong>22:00〜翌5:00</strong> の時間帯に重なる分です。 月次集計CSVでは「普通残業」と「深夜残業」に分けて集計されます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">休憩方式（所定固定 / 都度入力）</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    休憩の扱いはスタッフごとに設定されます。
                                    <strong>所定固定</strong>＝毎回の休憩時間が一定の方。所定分数（例：60分）が自動控除され、打刻・登録は不要です。
                                    <strong>都度入力</strong>＝休憩が変動する方。打刻画面の「休憩登録」から取得した休憩時間（分）を登録します。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">休暇区分（有給 / 特別休暇 / 欠勤）</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    管理者が登録する休暇の種類です。打刻とは別に登録し、月次集計CSVの「有給日数／特別休暇日数／欠勤日数」に反映されます。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">振替出勤・振替対象日</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    会社カレンダーにパターンの無い日（休日など）に出勤した場合、管理者が「適用パターン」を手動指定してベース時刻を算出します。 このとき<strong>「振替対象日」</strong>（代わりに休んだ出勤予定日）を登録すると<strong>振替勤務</strong>として扱われ、<strong>休日出勤日数には計上されません</strong>。
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-brand-text">仮登録（draft）</dt>
                                <dd class="text-brand-text-muted mt-1">
                                    打ち忘れや過去日入力のための下書き勤怠です。 申請するまで管理者からは見えず、何度でも編集可能です。
                                </dd>
                            </div>
                        </dl>
                    </UiCard>
                </section>

                <!-- 3. 打刻 -->
                <section id="punch">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Clock :size="18" class="text-brand-primary" /> 打刻
                            </h2>
                        </template>
                        <div class="space-y-4 text-sm leading-relaxed">
                            <p>
                                サイドバー「勤怠 → 打刻」を開くと、デジタル時計と<strong>「出勤」「退勤」</strong>のボタンが表示されます。
                                ボタンは現在の状態に応じて自動で有効化／無効化されます。
                            </p>
                            <figure>
                                <img :src="img('01_punch_initial.png')" alt="打刻トップ画面（初期状態）" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 打刻前の初期状態。「出勤」ボタンだけが有効です。</figcaption>
                            </figure>

                            <h3 class="font-semibold text-brand-text mt-4">1日の流れ</h3>
                            <ol class="list-decimal list-inside space-y-3 text-brand-text">
                                <li>
                                    <strong>出勤</strong> — 緑色の「出勤」ボタンを押します。<br>
                                    <span class="text-brand-text-muted text-xs">複数店舗に所属している場合は、押す前に「店舗」プルダウンから選択してください。</span>
                                </li>
                                <li>
                                    <strong>（必要な場合のみ）休憩登録</strong> — 休憩の登録が必要な方は「休憩登録」から入力します。<br>
                                    <span class="text-brand-text-muted text-xs">後述「休憩の登録」を参照。通常は登録不要です。</span>
                                </li>
                                <li>
                                    <strong>退勤</strong> — 業務終了時に押します。
                                    <figure class="mt-2">
                                        <img :src="img('05_after_clockout.png')" alt="退勤後" class="rounded-soft border border-brand-border" />
                                        <figcaption class="text-xs text-brand-text-muted mt-1">▲ 退勤後は全ボタンがグレーアウトし、「退勤済み」バッジが表示されます。</figcaption>
                                    </figure>
                                </li>
                            </ol>

                            <h3 class="font-semibold text-brand-text mt-4">休憩の登録（重要：方式が変わりました）</h3>
                            <p>
                                以前の「休憩開始／休憩終了」ボタンによるリアルタイム打刻は廃止されました。 現在の休憩の扱いは、あなたの<strong>休憩方式</strong>によって異なります。
                            </p>
                            <div class="rounded-soft border border-brand-border overflow-hidden">
                                <table class="w-full text-xs">
                                    <thead class="bg-brand-surface-2 text-brand-text">
                                        <tr>
                                            <th class="text-left px-3 py-2 font-semibold">休憩方式</th>
                                            <th class="text-left px-3 py-2 font-semibold">打刻画面の表示</th>
                                            <th class="text-left px-3 py-2 font-semibold">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-brand-text-muted">
                                        <tr class="border-t border-brand-border">
                                            <td class="px-3 py-2"><strong>所定固定</strong></td>
                                            <td class="px-3 py-2">「所定休憩（◯時間◯分）を自動控除」と表示</td>
                                            <td class="px-3 py-2">操作不要。所定分が自動で差し引かれます。</td>
                                        </tr>
                                        <tr class="border-t border-brand-border">
                                            <td class="px-3 py-2"><strong>都度入力</strong></td>
                                            <td class="px-3 py-2">「休憩登録」リンク（コーヒーアイコン）が表示</td>
                                            <td class="px-3 py-2">必要時のみ、取得した休憩時間（分）を登録します。</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-2">
                                「都度入力」方式で<strong>「休憩登録」</strong>を押すとモーダルが開きます。 入力するのは<strong>「取得した休憩時間（分）」のプルダウン</strong>だけで、開始・終了の時刻は不要です（15分刻み・最大120分。複数行の追加可）。 対象日をプルダウンで選べるため、当日だけでなく過去日（出勤打刻済みの直近2か月）の休憩も後から登録できます。
                            </p>
                            <UiAlert variant="info" class="mt-2">
                                <span class="text-xs">
                                    <strong>通常、休憩の登録は不要です。</strong> 所定の休憩どおりに取得した場合は登録しなくて構いません。 <strong>所定より超過して休憩した場合や、休憩を取得できなかった場合など、実態が所定と異なるときだけ</strong>登録してください。 登録した休憩はその日の勤怠に反映され、就労時間の計算・CSV出力に使われます。
                                </span>
                            </UiAlert>
                            <UiAlert variant="info" class="mt-2">
                                <span class="text-xs">出勤していない状態では退勤できません。また、退勤後は当日の打刻ボタンはグレーアウトします。</span>
                            </UiAlert>
                        </div>
                    </UiCard>
                </section>

                <!-- 4. 直前の打刻取消 -->
                <section id="cancel">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <AlertTriangle :size="18" class="text-natane-700" /> 直前の打刻取消
                            </h2>
                        </template>
                        <div class="space-y-3 text-sm leading-relaxed">
                            <p>誤って打ってしまった打刻は、画面右側「打刻履歴」の <span class="text-akane-600 font-semibold">取消</span> ボタンで取り消せます。</p>
                            <p class="text-brand-text-muted">
                                取消できるのは <strong>「一番最後に行ったアクション」</strong>のみです。優先順位は次のとおり：
                            </p>
                            <ul class="text-xs list-disc list-inside text-brand-text-muted space-y-1">
                                <li>退勤後なら「退勤」を取消（勤務中に戻る）</li>
                                <li>出勤だけの状態なら「出勤」を取消（レコードごと削除）</li>
                            </ul>
                            <p class="text-xs text-brand-text-muted">
                                ※ 誤って登録した休憩を消したい場合は、勤怠履歴の「編集」（未申請のみ）や、管理者の勤怠管理画面から修正できます。
                            </p>
                            <figure>
                                <img :src="img('06_after_cancel.png')" alt="退勤を取消した直後" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 退勤を取り消すと「勤務中」状態に戻ります。</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 5. 仮登録 -->
                <section id="provisional">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <FileEdit :size="18" class="text-brand-primary" /> 日付指定で仮登録
                            </h2>
                        </template>
                        <div class="space-y-3 text-sm leading-relaxed">
                            <p>
                                打刻し忘れた／前日分を入力したい場合は、サイドバー「勤怠 → 仮登録」から日付・店舗・時刻を直接入力できます。
                            </p>
                            <figure>
                                <img :src="img('07_provisional_form.png')" alt="仮登録フォーム" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 仮登録フォーム。 日付・店舗が必須。出退勤時刻と休憩は任意です。</figcaption>
                            </figure>
                            <p>
                                <strong>休憩欄</strong>もこのフォームから入力できます。「休憩を追加」で行を増やし、各行に<strong>開始時刻・終了時刻</strong>を入力します（複数可・不要なら空のままでOK）。 打刻画面の休憩登録が「分数」で入力するのに対し、仮登録は<strong>開始・終了の時刻ペア</strong>で入力する点にご注意ください。
                            </p>
                            <p>
                                登録すると <UiBadge variant="neutral" size="sm">未申請</UiBadge> ステータスで保存され、勤怠履歴から何度でも編集できます。
                                準備が整ったら <strong>申請</strong>ボタンで管理者に承認依頼を送ります。
                            </p>
                            <UiAlert variant="info" class="mt-2">
                                <span class="text-xs">
                                    「<strong>確定申請として登録</strong>」のチェックを入れると、登録と同時に申請済（applied）になります。 編集を繰り返したくない場合に便利です。
                                </span>
                            </UiAlert>
                        </div>
                    </UiCard>
                </section>

                <!-- 6. 履歴 -->
                <section id="history">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <History :size="18" class="text-brand-primary" /> 勤怠履歴
                            </h2>
                        </template>
                        <div class="space-y-3 text-sm leading-relaxed">
                            <p>
                                サイドバー「勤怠 → 勤怠履歴」で、自分の勤怠を期間指定で一覧できます。
                                初期表示は <strong>前月21日〜今月20日</strong> の締め期間です。
                            </p>
                            <figure>
                                <img :src="img('09_history.png')" alt="勤怠履歴一覧" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 勤怠履歴。残業は会社カレンダーのシフトと比較して自動算出されます。</figcaption>
                            </figure>
                            <h3 class="font-semibold text-brand-text mt-3">表示される列</h3>
                            <p class="text-brand-text-muted">
                                日付／店舗／ステータス／<strong>シフトパターン（A/B/C）</strong>／シフト出勤／シフト退勤／打刻出勤／打刻退勤／<strong>残業（丸め後）</strong>／<strong>休憩（合計）</strong>／操作 が表示されます。
                            </p>
                            <ul class="list-disc list-inside text-brand-text-muted space-y-1 text-xs">
                                <li><strong>残業（丸め後）</strong> … セルにマウスを乗せると、丸める前の実分（休憩控除後）がツールチップで確認できます。算出できない日は「—」。</li>
                                <li><strong>休憩（合計）</strong> … 終了時刻まで揃った休憩を合算して「◯時間◯分」で表示。マウスオーバーで内訳が出ます。</li>
                                <li>シフトが取得できない日は「?」アイコンで理由（会社カレンダー未設定・勤務属性未割当など）が表示されます。</li>
                            </ul>
                            <h3 class="font-semibold text-brand-text mt-3">期間の動かし方</h3>
                            <ul class="list-disc list-inside text-brand-text-muted space-y-1">
                                <li><strong>「前月」「次月」</strong>ボタン … 締め単位で1か月ずつ移動します</li>
                                <li><strong>開始日／終了日</strong>を直接入力して「絞り込み」</li>
                                <li>日付欄を空にして「絞り込み」 … 当月の締め期間に戻ります</li>
                            </ul>
                            <figure>
                                <img :src="img('13_history_prev_month.png')" alt="前月へ移動" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 「前月」ボタンで 3/21〜4/20 の期間に移動。</figcaption>
                            </figure>
                            <UiAlert variant="info" class="mt-2">
                                <span class="text-xs">
                                    <strong>残業（丸め後）</strong> は、勤務属性の残業判定方式に応じて算出されます。<strong>パターン方式</strong>の方はシフト退勤より後の打刻分から休憩を控除した時間、<strong>閾値方式</strong>の方は1日の実働が残業閾値を超えた分が残業になり、給与閾値の「残業・丸め単位（分）」で切り捨てて表示します。 出勤の早出・丸めは「始業」設定に従います。 シフト（ベース時刻）が取得できない日は「？」アイコンで理由が表示されます。
                                </span>
                            </UiAlert>
                        </div>
                    </UiCard>
                </section>

                <!-- 7. 申請 -->
                <section id="apply">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <CheckCircle2 :size="18" class="text-brand-primary" /> 申請
                            </h2>
                        </template>
                        <div class="space-y-3 text-sm leading-relaxed">
                            <p>
                                履歴で <UiBadge variant="neutral" size="sm">未申請</UiBadge> 状態のレコードには、行末に <strong>編集</strong>と<strong>申請</strong>のリンクが表示されます。
                            </p>
                            <figure>
                                <img :src="img('10_apply_modal.png')" alt="申請モーダル" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 「申請」を押すと理由入力モーダルが開きます（理由は任意）。</figcaption>
                            </figure>
                            <p>
                                申請すると <UiBadge variant="warning" size="sm">申請済</UiBadge> ステータスに変わり、管理者の承認待ちとなります。
                            </p>
                            <figure>
                                <img :src="img('12_after_apply.png')" alt="申請後" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 申請完了後の履歴画面。</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 管理者向け：承認依頼 -->
                <section v-if="isManager" id="approvals">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <CheckCircle2 :size="18" class="text-natane-700" /> 承認依頼（管理者）
                            </h2>
                        </template>
                        <div class="space-y-3 text-sm leading-relaxed">
                            <p>
                                サイドバー「勤怠 → 承認依頼」で、申請されてきた勤怠を承認・差し戻しできます。
                                <strong>shop_manager</strong> は所属店舗のレコードのみ、<strong>勤怠管理者</strong>は全店舗が対象です。
                            </p>
                            <figure>
                                <img :src="img('14_approvals_index.png')" alt="承認依頼一覧" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 承認待ちの申請一覧。シフトと打刻、残業、申請理由まで一覧で確認できます。</figcaption>
                            </figure>
                            <h3 class="font-semibold text-brand-text mt-3">操作方法</h3>
                            <ul class="list-disc list-inside text-brand-text-muted space-y-1">
                                <li>各行の「承認」「差し戻し」で<strong>1件ずつ</strong>処理</li>
                                <li>左端のチェックでまとめて選択 → 「まとめて承認／差し戻し」で<strong>一括処理</strong></li>
                                <li>差し戻すと申請者の<strong>未申請</strong>状態に戻り、再編集できるようになります</li>
                            </ul>
                            <figure>
                                <img :src="img('15_approvals_one_selected.png')" alt="1件選択時" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 選択中件数の表示と、まとめて承認ボタンの有効化。</figcaption>
                            </figure>
                        </div>
                    </UiCard>
                </section>

                <!-- 管理者向け：勤怠管理 -->
                <section v-if="isManager" id="admin-attendance">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Settings :size="18" class="text-natane-700" /> 勤怠管理（管理者）
                            </h2>
                        </template>
                        <div class="space-y-3 text-sm leading-relaxed">
                            <p>
                                サイドバー「勤怠 → 勤怠管理」で、所属スタッフ全員の勤怠を一覧・編集・承認・CSV出力できます。 さらに休暇登録・振替出勤のパターン設定もここから行います。
                            </p>
                            <figure>
                                <img :src="img('18_admin_attendance_top.png')" alt="勤怠管理画面" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 勤怠管理画面。ユーザー毎にグループ化される表示モードがデフォルト。</figcaption>
                            </figure>

                            <h3 class="font-semibold text-brand-text mt-3">絞り込みと表示</h3>
                            <ul class="list-disc list-inside text-brand-text-muted space-y-1">
                                <li><strong>絞り込み</strong> … 店舗・ユーザー・<strong>勤務属性</strong>（「未設定」も選択可）・期間で抽出。既定の期間は前月21日〜当月20日。</li>
                                <li><strong>表示方法切替</strong> … 「テーブル表示」／「ユーザー毎」（既定）。「ユーザー毎」では各スタッフのブロック下に月次集計表が付きます。</li>
                                <li><strong>休憩列</strong> … 所定固定の方は「◯分（所定）」、都度入力の方は打刻合計を「◯分」表示。</li>
                                <li><strong>色分けバッジ</strong> … 遅刻（青）／早出（オレンジ）／残業（紫）、および要対応の「要パターン設定」「要勤務属性設定」。</li>
                            </ul>

                            <h3 class="font-semibold text-brand-text mt-3">編集・承認（各行「詳細」）</h3>
                            <ul class="list-disc list-inside text-brand-text-muted space-y-1">
                                <li><strong>時刻の修正</strong> … 出勤・退勤時刻を修正できます（退勤は出勤より後）。</li>
                                <li><strong>休憩</strong> … 開始・終了の時刻ペアで複数追加・削除可能（保存時に入れ直し）。</li>
                                <li><strong>適用パターン／振替対象日</strong> … 会社カレンダーにパターンの無い日はオレンジ枠の案内が出ます（下記）。</li>
                                <li><strong>承認</strong> … 未申請・申請済の勤怠は「この勤怠を承認する」で確定。時刻を直したときは先に「変更を保存」してください。</li>
                            </ul>

                            <h3 class="font-semibold text-brand-text mt-3">休暇登録（有給・特別休暇・欠勤）</h3>
                            <p class="text-brand-text-muted">
                                絞り込みフォーム下の琥珀色<strong>「＋ 休暇登録」</strong>ボタンからモーダルを開き、<strong>スタッフ・日付・区分（有給／特別休暇／欠勤）・メモ（任意）</strong>を入力して登録します。 打刻が無い日でも登録でき、月次集計CSVの「有給日数／特別休暇日数／欠勤日数」に反映されます。 同一スタッフ・同一日は1区分のみで、再登録すると上書きされます。 登録済みの休暇はボタン下の「登録済みの休暇」一覧から「削除」できます。
                            </p>

                            <h3 class="font-semibold text-brand-text mt-3">振替出勤（適用パターンの手動指定）</h3>
                            <p class="text-brand-text-muted">
                                会社カレンダーにパターンの無い日に出勤があると、その行は<strong>オレンジ背景</strong>＋「要パターン設定」バッジで強調されます。 「詳細」を開くとオレンジ枠の案内が出るので、<strong>適用パターン（A/B/C）</strong>を選ぶとベース出勤・退勤が再計算されます。 このとき<strong>「振替対象日」</strong>（代わりに休んだ出勤予定日）も登録すると<strong>振替勤務</strong>扱いとなり、休日出勤日数に計上されません。 振替対象日を空のままにすると「休日出勤」として1日カウントされます。
                            </p>

                            <h3 class="font-semibold text-brand-text mt-3">CSV出力（2種類）</h3>
                            <div class="rounded-soft border border-brand-border overflow-hidden">
                                <table class="w-full text-xs">
                                    <thead class="bg-brand-surface-2 text-brand-text">
                                        <tr>
                                            <th class="text-left px-3 py-2 font-semibold">種類</th>
                                            <th class="text-left px-3 py-2 font-semibold">粒度</th>
                                            <th class="text-left px-3 py-2 font-semibold">対象</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-brand-text-muted">
                                        <tr class="border-t border-brand-border">
                                            <td class="px-3 py-2"><strong>CSVダウンロード</strong>（日次明細）</td>
                                            <td class="px-3 py-2">1勤怠＝1行。打刻・給与用時刻・休憩の明細。</td>
                                            <td class="px-3 py-2"><strong>チェックで選んだユーザー</strong>のみ。</td>
                                        </tr>
                                        <tr class="border-t border-brand-border">
                                            <td class="px-3 py-2"><strong>月次集計CSV</strong>（藍色ボタン）</td>
                                            <td class="px-3 py-2">1スタッフ＝1行の月合計（下記12列）。</td>
                                            <td class="px-3 py-2"><strong>絞り込み条件内の全員</strong>（チェック無関係）。</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-brand-text-muted mt-2">
                                <strong>月次集計CSVの列（前月21日〜当月20日が既定）：</strong>
                                社員番号／氏名／出勤日数／休日出勤日数／有給日数／特別休暇日数／欠勤日数／就労時間／普通残業／深夜残業／遅早回数／遅早時間。
                                「ユーザー毎」表示では各スタッフの集計表の行末「詳細（計算ロジック）」から、列ごとの計算根拠と日別の寄与データを確認できます。
                            </p>
                        </div>
                    </UiCard>
                </section>

                <!-- 勤怠管理者向け：勤務属性 -->
                <section v-if="isAttendanceManager" id="work-attribute">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Briefcase :size="18" class="text-enji-700" /> 勤務属性マスタ（勤怠管理者）
                            </h2>
                        </template>
                        <div class="space-y-3 text-sm leading-relaxed">
                            <p>
                                サイドバー「勤怠 → 勤務属性」で、社員・パートなどの<strong>属性ごとの業務時間（A/B/C × 平日/土日）</strong>と<strong>残業の判定方式</strong>を管理します。
                            </p>
                            <figure>
                                <img :src="img('19_work_attributes.png')" alt="勤務属性マスタ一覧" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 勤務属性の一覧。「新規追加」または既存の編集アイコンから操作。</figcaption>
                            </figure>

                            <h3 class="font-semibold text-brand-text mt-3">残業の判定方式</h3>
                            <p class="text-brand-text-muted">
                                属性の追加・編集画面で「残業の判定方式」を選びます。
                            </p>
                            <dl class="text-sm space-y-2">
                                <div>
                                    <dt class="font-semibold text-brand-text">パターン方式（所定終業を超えた分が残業）</dt>
                                    <dd class="text-brand-text-muted">主に<strong>正社員</strong>向け。A/B/Cパターンの所定終業時刻を超えた分を残業とします。パターン時刻の登録が必要です。</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-brand-text">閾値方式（1日の実働が閾値を超えた分が残業）</dt>
                                    <dd class="text-brand-text-muted">主に<strong>パート・時短</strong>向け。「残業閾値（分）」（例：480＝8時間、470＝7時間50分）を登録し、1日の実働がそれを超えた分を残業とします。時短でパターン時刻も登録した場合は、早出・遅刻・早退の判定にだけパターンが使われます。</dd>
                                </div>
                            </dl>
                            <UiAlert variant="info" class="mt-2">
                                <span class="text-xs">
                                    閾値方式は<strong>「給与計算閾値」画面の「閾値方式の適用開始日」以降の勤務分</strong>から有効になります（それより前は従来どおり据え置き）。
                                </span>
                            </UiAlert>
                            <UiAlert variant="warning" class="mt-2">
                                <span class="text-xs">
                                    すでにスタッフに割り当てられている勤務属性は<strong>削除できません</strong>。 該当スタッフの「勤務属性」を別のものに切り替えてから削除してください。
                                </span>
                            </UiAlert>
                        </div>
                    </UiCard>
                </section>

                <!-- 勤怠管理者向け：会社カレンダー -->
                <section v-if="isAttendanceManager" id="company-calendar">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Calendar :size="18" class="text-enji-700" /> 会社カレンダー（勤怠管理者）
                            </h2>
                        </template>
                        <div class="space-y-3 text-sm leading-relaxed">
                            <p>
                                サイドバー「勤怠 → 会社カレンダー」で、毎月の各日付に <strong>A / B / C</strong> パターンを設定します。 ここで設定したパターンが、各スタッフのその日の「ベース業務時間」を決定します。
                            </p>
                            <figure>
                                <img :src="img('20_company_calendar.png')" alt="会社カレンダー" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 会社カレンダー。日付クリックでA/B/C切替。「保存」忘れに注意。</figcaption>
                            </figure>
                            <UiAlert variant="info" class="mt-2">
                                <span class="text-xs">
                                    パターンを設定していない日は、その日の勤怠で「シフト時刻を取得できません」と表示され、残業計算もスキップされます。
                                </span>
                            </UiAlert>
                        </div>
                    </UiCard>
                </section>

                <!-- 勤怠管理者向け：給与閾値 -->
                <section v-if="isAttendanceManager" id="payroll-settings">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <DollarSign :size="18" class="text-enji-700" /> 給与計算閾値（勤怠管理者）
                            </h2>
                        </template>
                        <div class="space-y-3 text-sm leading-relaxed">
                            <p>サイドバー「勤怠 → 給与閾値」で、給与計算の基準を設定します。</p>
                            <figure>
                                <img :src="img('21_payroll_settings.png')" alt="給与計算閾値" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 給与閾値設定画面。</figcaption>
                            </figure>
                            <h3 class="font-semibold text-brand-text mt-3">各設定の意味</h3>
                            <dl class="text-sm space-y-2">
                                <div>
                                    <dt class="font-semibold text-brand-text">早出とみなす閾値（分）</dt>
                                    <dd class="text-brand-text-muted">シフト開始よりこの分数<strong>以上前</strong>の打刻を、給与計算上「早出」として扱います。</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-brand-text">始業・丸め単位（分）</dt>
                                    <dd class="text-brand-text-muted">早出と判定されなかった出勤打刻を、その日の0:00からこの単位の<strong>最近接</strong>に丸めます。</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-brand-text">残業・丸め単位（分）</dt>
                                    <dd class="text-brand-text-muted">残業時間をこの単位で<strong>切り捨て</strong>ます（短い方へ丸め。例：単位15分なら38分→30分）。</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-brand-text">閾値方式の適用開始日</dt>
                                    <dd class="text-brand-text-muted">勤務属性の<strong>閾値方式</strong>（残業閾値で残業を判定する新方式）を<strong>この日以降の勤務分</strong>から適用します。それより前の勤怠は従来どおり据え置きです。未設定なら全期間に適用されます。<strong>給与の締め期間の切り替わりに合わせる</strong>ことを推奨します。</dd>
                                </div>
                            </dl>
                        </div>
                    </UiCard>
                </section>

                <!-- 勤怠管理者向け：給与シミュレーター -->
                <section v-if="isAttendanceManager" id="payroll-simulator">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <Calculator :size="18" class="text-enji-700" /> 給与シミュレーター（勤怠管理者）
                            </h2>
                        </template>
                        <div class="space-y-3 text-sm leading-relaxed">
                            <p>
                                サイドバー「勤怠 → 給与シミュレーター」で、特定の出勤・退勤パターンに対して給与計算結果が<strong>1分刻み</strong>でどう変化するかをシミュレーションできます。 設定変更後の影響確認や、運用ルールの説明資料作成に便利です。
                            </p>
                            <figure>
                                <img :src="img('22_payroll_simulator.png')" alt="給与シミュレーター" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ シミュレーター入力画面。</figcaption>
                            </figure>
                            <UiAlert variant="info" class="mt-2">
                                <span class="text-xs">
                                    出勤/退勤の「スイープ範囲」は片側<strong>最大1440分（24時間）</strong>まで。広すぎる場合はエラーになります。
                                </span>
                            </UiAlert>
                        </div>
                    </UiCard>
                </section>

                <!-- FAQ -->
                <section id="faq">
                    <UiCard variant="default" padding="md">
                        <template #header>
                            <h2 class="font-serif text-lg flex items-center gap-2">
                                <HelpCircle :size="18" class="text-brand-primary" /> よくあるご質問
                            </h2>
                        </template>
                        <div class="space-y-4 text-sm leading-relaxed">
                            <div>
                                <p class="font-semibold text-brand-text">Q. 休憩の「開始／終了」ボタンが見当たりません。</p>
                                <p class="text-brand-text-muted mt-1">
                                    A. 休憩の打刻方式は変更されました。所定休憩が決まっている方は<strong>自動控除</strong>のため操作不要です。 都度入力の方は「休憩登録」から<strong>取得した休憩時間（分）</strong>を後から登録します（通常は登録不要。所定と異なるときだけ登録）。
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold text-brand-text">Q. 退勤ボタンが押せません。</p>
                                <p class="text-brand-text-muted mt-1">
                                    A. まだ出勤打刻していない場合や、すでに退勤済みの場合は退勤できません。
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold text-brand-text">Q. 「該当日は既に勤怠が登録されています」と出ます。</p>
                                <p class="text-brand-text-muted mt-1">
                                    A. その日の勤怠は既に存在します。<strong>承認済み</strong>の場合は新規仮登録できません。 <strong>未申請／申請済</strong>の場合は、勤怠履歴から「編集」リンクで修正してください。
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold text-brand-text">Q. シフト出勤・退勤が「？」になっています。</p>
                                <p class="text-brand-text-muted mt-1">
                                    A. ?を マウスオーバーすると詳細理由が表示されます。多くの場合、<strong>会社カレンダーの未設定</strong>か、<strong>勤務属性が未割り当て</strong>です。 勤怠管理者にご相談ください。
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold text-brand-text">Q. 残業時間が0分のままになります。</p>
                                <p class="text-brand-text-muted mt-1">
                                    A. 残業の発生条件は勤務属性の判定方式によります。<strong>パターン方式</strong>はシフト退勤より後に打刻された場合、<strong>閾値方式</strong>は1日の実働が残業閾値を超えた場合にのみ発生します。 いずれにも達しない場合は0分です。 閾値方式は「閾値方式の適用開始日」以降の勤務分から有効になる点にもご注意ください。
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold text-brand-text">Q. 有給や欠勤はどこで登録しますか？</p>
                                <p class="text-brand-text-muted mt-1">
                                    A. 休暇は本人の打刻ではなく、<strong>管理者が勤怠管理画面の「＋ 休暇登録」</strong>から登録します（有給／特別休暇／欠勤）。 登録内容は月次集計CSVの休暇日数に反映されます。
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold text-brand-text">Q. 休日に出勤した行がオレンジ色になっています。</p>
                                <p class="text-brand-text-muted mt-1">
                                    A. 会社カレンダーにその日のパターンが無いためです。管理者が「詳細」から<strong>適用パターン</strong>を選ぶとベース時刻が算出されます。 振替出勤の場合は「振替対象日」も登録すると休日出勤にカウントされません。
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold text-brand-text">Q. 打刻を間違えました。直したいです。</p>
                                <p class="text-brand-text-muted mt-1">
                                    A. 打ち間違えた直後なら、本マニュアル「直前の打刻取消」の手順で取り消せます。 すでに次の打刻を進めてしまった場合は、勤怠履歴から該当日を「編集」（未申請のみ）か、管理者に修正を依頼してください。
                                </p>
                            </div>
                        </div>
                    </UiCard>
                </section>

            </div>

            <!-- サイド目次（右固定） -->
            <aside class="manual-toc">
                <UiCard variant="default" padding="sm">
                    <nav class="text-sm">
                        <p class="px-2 pb-2 text-[11px] font-semibold uppercase tracking-wider text-brand-text-subtle">
                            目次
                        </p>
                        <ul class="space-y-0.5">
                            <li v-for="s in sections" :key="s.id">
                                <button
                                    type="button"
                                    @click="scrollTo(s.id)"
                                    :class="[
                                        'w-full flex items-center gap-2 px-2 py-1.5 rounded-soft text-left transition-colors',
                                        activeSection === s.id
                                            ? 'bg-ai-50 text-brand-primary font-medium dark:bg-ai-900'
                                            : 'text-brand-text-muted hover:bg-brand-surface-2'
                                    ]"
                                >
                                    <component :is="s.icon" :size="14" />
                                    <span>{{ s.label }}</span>
                                </button>
                            </li>
                        </ul>
                    </nav>
                </UiCard>
            </aside>
        </div>

    </AdminLayout>
</template>

<style scoped>
.manual-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}
.manual-main { order: 2; }
.manual-toc { order: 1; }

@media (min-width: 1024px) {
    .manual-layout {
        grid-template-columns: 7fr 3fr;
    }
    .manual-main { order: 1; }
    .manual-toc {
        order: 2;
        position: sticky;
        top: 1rem;
        align-self: start;
        max-height: calc(100vh - 2rem);
        overflow-y: auto;
    }
}

/* ============ 印刷時（A4縦向き）レイアウト ============ */
@media print {
    .manual-layout {
        display: block !important;
        grid-template-columns: none !important;
    }
    .manual-toc { display: none !important; }
    section {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    figure {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .manual-main {
        font-size: 10pt;
        line-height: 1.5;
    }
    .manual-main figure img,
    .manual-main img {
        max-width: 100%;
        max-height: 18cm;
        object-fit: contain;
    }
    table, th, td {
        border-color: #999 !important;
    }
}
</style>

<style>
/* グローバル印刷ルール（AdminLayoutのナビ等を非表示） */
@media print {
    aside.admin-sidebar, .admin-topbar, header.admin-topbar,
    [class*="AdminSidebar"], [class*="AdminTopbar"],
    nav.admin-nav, .breadcrumb, .page-header-actions {
        display: none !important;
    }
    body, html {
        background: #fff !important;
    }
    @page {
        size: A4 portrait;
        margin: 12mm 12mm 14mm 12mm;
    }
}
</style>
