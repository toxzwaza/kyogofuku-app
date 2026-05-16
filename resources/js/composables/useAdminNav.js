import {
    LayoutDashboard, Home, Calendar, ListChecks, CalendarCheck,
    Users, UserCog, MessageCircle, HelpCircle, Tag, Lock,
    Ticket, MapPin, Images, Film, Download,
    Camera, Building2,
    Store, Settings,
    Clock, History, CheckCircle2, Briefcase, DollarSign, Calculator, AlarmClock,
    FileText, Sparkles, BookOpen, LifeBuoy,
} from 'lucide-vue-next';

/**
 * 管理画面のサイドバーナビ定義。
 *   group : セクション見出し
 *   items : 子リンク（label, route, icon, activePatterns, permission）
 *
 * activePatterns に該当する現在ルートがあるとアクティブ表示になる。
 * permission に文字列を指定すると page.props.auth.user[permission] が真のときだけ表示される。
 */
export function useAdminNav() {
    return [
        {
            group: 'ホーム',
            items: [
                { label: 'オーバービュー', route: 'admin.overview',  icon: LayoutDashboard, activePatterns: ['admin.overview'] },
                { label: '従来ダッシュボード',  route: 'dashboard',      icon: Home,            activePatterns: ['dashboard'], routeParams: { force_legacy: 1 } },
            ],
        },
        {
            group: '顧客',
            items: [
                { label: '顧客一覧',       route: 'admin.customers.index',           icon: Users,         activePatterns: ['admin.customers.*'] },
                { label: 'LINE連携',       route: 'admin.line-contacts.index',       icon: MessageCircle, activePatterns: ['admin.line-contacts.*'] },
                { label: '不明メッセージ', route: 'admin.line-unknown-inbox.index',  icon: HelpCircle,    activePatterns: ['admin.line-unknown-inbox.*'] },
                { label: '顧客タグ',       route: 'admin.customer-tags.index',       icon: Tag,           activePatterns: ['admin.customer-tags.*'] },
                { label: '制約テンプレート', route: 'admin.constraint-templates.index', icon: Lock,        activePatterns: ['admin.constraint-templates.*'] },
            ],
        },
        {
            group: 'イベント・予約',
            items: [
                { label: 'イベント一覧',       route: 'admin.events.index',                    icon: Ticket,        activePatterns: ['admin.events.*'] },
                { label: '予約者出力',         route: 'admin.events.reservations-export.index', icon: Download,      activePatterns: ['admin.events.reservations-export.*'] },
                { label: '開催会場',           route: 'admin.venues.index',                    icon: MapPin,        activePatterns: ['admin.venues.*'] },
                { label: 'スライドショー',     route: 'admin.slideshows.index',                icon: Film,          activePatterns: ['admin.slideshows.*'] },
                { label: 'メディアライブラリ', route: 'admin.media.index',                     icon: Images,        activePatterns: ['admin.media.*'] },
            ],
        },
        {
            group: '前撮り',
            items: [
                { label: '前撮り枠',   route: 'admin.photo-slots.index',   icon: Camera,     activePatterns: ['admin.photo-slots.*'] },
                { label: 'スタジオ',   route: 'admin.photo-studios.index', icon: Building2,  activePatterns: ['admin.photo-studios.*'] },
            ],
        },
        {
            group: 'マスタ',
            items: [
                { label: '店舗',     route: 'admin.shops.index', icon: Store,    activePatterns: ['admin.shops.*'] },
                { label: 'スタッフ', route: 'admin.users.index', icon: UserCog,  activePatterns: ['admin.users.*'] },
            ],
        },
        {
            group: '勤怠',
            items: [
                { label: '打刻',           route: 'attendance.index',            icon: AlarmClock,   activePatterns: ['attendance.index'] },
                { label: '勤怠履歴',       route: 'attendance.history',          icon: History,      activePatterns: ['attendance.history'] },
                { label: '仮登録',         route: 'attendance.provisional.create', icon: FileText,    activePatterns: ['attendance.provisional.*'] },
                { label: '勤怠マニュアル', route: 'attendance.manual',           icon: LifeBuoy,     activePatterns: ['attendance.manual'] },
                { label: '承認依頼',       route: 'attendance.approvals',        icon: CheckCircle2, activePatterns: ['attendance.approvals*'], permission: 'canManageAttendance' },
                { label: '勤怠管理',       route: 'admin.attendance.index',      icon: Clock,        activePatterns: ['admin.attendance.*'], permission: 'canManageAttendance' },
                { label: '勤務属性',       route: 'admin.work-attributes.index', icon: Briefcase,    activePatterns: ['admin.work-attributes.*'], permission: 'isAttendanceManager' },
                { label: '会社カレンダー', route: 'admin.attendance.company-calendar.index', icon: Calendar, activePatterns: ['admin.attendance.company-calendar.*'], permission: 'isAttendanceManager' },
                { label: '給与閾値',       route: 'admin.attendance.payroll-settings.edit',  icon: DollarSign, activePatterns: ['admin.attendance.payroll-settings.*'], permission: 'isAttendanceManager' },
                { label: '給与シミュレーター', route: 'admin.attendance.payroll-simulator.index', icon: Calculator, activePatterns: ['admin.attendance.payroll-simulator.*'], permission: 'isAttendanceManager' },
            ],
        },
        {
            group: 'システム',
            items: [
                { label: 'ログ',       route: 'admin.activity-logs.index', icon: FileText, activePatterns: ['admin.activity-logs.*'] },
                { label: 'UIキット',   route: 'admin.ui-kit',              icon: Sparkles, activePatterns: ['admin.ui-kit'] },
            ],
        },
    ];
}

/** ルートパターンのいずれかがアクティブか判定 */
export function isItemActive(item) {
    if (!item.activePatterns || !item.activePatterns.length) return false;
    if (typeof route !== 'function') return false;
    try {
        return item.activePatterns.some((p) => route().current(p));
    } catch {
        return false;
    }
}
