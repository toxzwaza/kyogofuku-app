<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen, Clock, FileEdit, History, CheckCircle2,
    Settings, Calendar, DollarSign, Calculator, Briefcase,
    HelpCircle, AlertTriangle, Info, Layers,
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
            description="出勤・休憩・退勤の打刻、仮登録、申請から、管理者向けの承認・閾値設定まで、本システムの勤怠機能の使い方をまとめています。"
        />

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
                                本システムでは、出勤・退勤・休憩の打刻から、月次の勤怠履歴の確認、申請、承認、給与計算用の閾値設定まで、勤怠管理に必要な機能を一通り提供します。
                            </p>
                            <p>このマニュアルは、サイドバー左側の <strong>「勤怠」</strong>グループ内にあるすべての機能の使い方をまとめたものです。 ご自身のロール（一般スタッフ／管理者／勤怠管理者）に応じて、表示される章だけ参照すれば OK です。</p>
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
                                    会社カレンダーのパターン × あなたの勤務属性 × 平日/土日 で決まる、その日の業務開始～終了時刻です。残業は <strong>「打刻退勤がベース業務終了より後」</strong>のときだけ発生します。
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
                                サイドバー「勤怠 → 打刻」を開くと、デジタル時計と4つのボタン（出勤／休憩開始／休憩終了／退勤）が表示されます。
                                各ボタンは現在の状態に応じて自動で有効化／無効化されます。
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
                                    <strong>休憩開始</strong> — 休憩に入るとき押します。
                                    <figure class="mt-2">
                                        <img :src="img('03_after_breakstart.png')" alt="休憩開始後" class="rounded-soft border border-brand-border" />
                                        <figcaption class="text-xs text-brand-text-muted mt-1">▲ 休憩中はオレンジ色の「休憩中」バッジが表示されます。</figcaption>
                                    </figure>
                                </li>
                                <li>
                                    <strong>休憩終了</strong> — 休憩から戻ったら押します。 1日に複数回の休憩を取ることもできます。
                                </li>
                                <li>
                                    <strong>退勤</strong> — 業務終了時に押します。
                                    <figure class="mt-2">
                                        <img :src="img('05_after_clockout.png')" alt="退勤後" class="rounded-soft border border-brand-border" />
                                        <figcaption class="text-xs text-brand-text-muted mt-1">▲ 退勤後は全ボタンがグレーアウトし、「退勤済み」バッジが表示されます。</figcaption>
                                    </figure>
                                </li>
                            </ol>

                            <UiAlert variant="info" class="mt-3">
                                <span class="text-xs">休憩中は退勤できません。先に「休憩終了」を押してから「退勤」してください。</span>
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
                                取消できるのは <strong>「一番最後に行った打刻」</strong>のみです。優先順位は次のとおり：
                            </p>
                            <ul class="text-xs list-disc list-inside text-brand-text-muted space-y-1">
                                <li>休憩中なら「休憩開始」を取消</li>
                                <li>退勤後なら「退勤」を取消</li>
                                <li>休憩終了後なら「休憩終了」を取消</li>
                                <li>出勤直後なら「出勤」を取消（レコードごと削除）</li>
                            </ul>
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
                                    <strong>残業（丸め後）</strong> は、シフト退勤より後の打刻時間から休憩を控除し、給与閾値の「残業・丸め単位（分）」で丸めて表示します。 シフトが取得できない日（会社カレンダーが未設定など）は「？」アイコンで理由が表示されます。
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
                                サイドバー「勤怠 → 勤怠管理」で、所属スタッフ全員の勤怠を一覧・編集・CSVエクスポートできます。
                            </p>
                            <figure>
                                <img :src="img('18_admin_attendance_top.png')" alt="勤怠管理画面" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 勤怠管理画面。ユーザー毎にグループ化される表示モードがデフォルト。</figcaption>
                            </figure>
                            <h3 class="font-semibold text-brand-text mt-3">主な操作</h3>
                            <ul class="list-disc list-inside text-brand-text-muted space-y-1">
                                <li><strong>絞り込み</strong> … 店舗・ユーザー・期間で抽出</li>
                                <li><strong>表示方法切替</strong> … テーブル形式 / ユーザー毎</li>
                                <li><strong>CSVダウンロード</strong> … 対象ユーザーをチェックしてエクスポート</li>
                                <li><strong>編集</strong> … 各行から時刻・休憩を直接修正可能（モーダル）</li>
                            </ul>
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
                                サイドバー「勤怠 → 勤務属性」で、社員・パートなどの<strong>属性ごとの業務時間（A/B/C × 平日/土日）</strong>を管理します。
                            </p>
                            <figure>
                                <img :src="img('19_work_attributes.png')" alt="勤務属性マスタ一覧" class="rounded-soft border border-brand-border" />
                                <figcaption class="text-xs text-brand-text-muted mt-1">▲ 勤務属性の一覧。「新規追加」または既存の編集アイコンから操作。</figcaption>
                            </figure>
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
                                    <dd class="text-brand-text-muted">残業時間を丸める単位（例：30分単位）。</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-brand-text">残業・端数の切り捨て上限（分）</dt>
                                    <dd class="text-brand-text-muted">丸め単位未満の端数のうち、この分数<strong>以下なら切り捨て</strong>、超えたら次の単位へ繰り上げ。</dd>
                                </div>
                            </dl>
                            <UiAlert variant="warning" class="mt-2">
                                <span class="text-xs">
                                    「端数の切り捨て上限」は<strong>「丸め単位（分）」未満</strong>でなければなりません（例: 単位30分なら、上限は29分まで）。
                                </span>
                            </UiAlert>
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
                                <p class="font-semibold text-brand-text">Q. 退勤ボタンが押せません。</p>
                                <p class="text-brand-text-muted mt-1">
                                    A. 休憩中の状態では退勤できません。先に「休憩終了」を押してください。 また、まだ出勤打刻していない場合も退勤できません。
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
                                    A. 残業はシフト退勤時刻より後に打刻された場合にのみ発生します。 シフト時刻が取得できていない場合や、シフト終了より早く退勤している場合は0分になります。
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
</style>
