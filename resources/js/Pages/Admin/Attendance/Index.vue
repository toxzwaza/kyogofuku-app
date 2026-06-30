<template>
    <Head title="勤怠管理" />
    <AdminLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-brand-text leading-tight">勤怠管理</h2>
                <Link :href="route('dashboard')" class="text-brand-text-muted hover:text-brand-text">ダッシュボードへ</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-brand-surface overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-md text-sm">
                            {{ $page.props.flash.success }}
                        </div>
                        <form @submit.prevent="applyFilters" class="mb-4 flex flex-wrap gap-4">
                            <div>
                                <label class="block text-xs font-medium text-brand-text-muted mb-1">店舗</label>
                                <select v-model="filters.shop_id" class="rounded-md border-brand-border text-sm">
                                    <option value="">すべて</option>
                                    <option v-for="s in shops" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-brand-text-muted mb-1">ユーザー</label>
                                <select v-model="filters.user_id" class="rounded-md border-brand-border text-sm">
                                    <option value="">すべて</option>
                                    <option v-for="u in filteredUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-brand-text-muted mb-1">開始日</label>
                                <input v-model="filters.from" type="date" class="rounded-md border-brand-border text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-brand-text-muted mb-1">終了日</label>
                                <input v-model="filters.to" type="date" class="rounded-md border-brand-border text-sm" />
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="px-4 py-2 bg-brand-primary text-white rounded-md text-sm hover:bg-brand-primary-hover">絞り込み</button>
                            </div>
                        </form>

                        <div v-if="records.data?.length > 0" class="mb-4 flex flex-wrap items-center gap-3">
                            <span class="text-sm font-medium text-brand-text">CSVエクスポート</span>
                            <button type="button" @click="selectAllUsers" class="px-2 py-1 text-sm bg-brand-surface-2 text-brand-text rounded hover:bg-gray-200">全選択</button>
                            <button type="button" @click="deselectAllUsers" class="px-2 py-1 text-sm bg-brand-surface-2 text-brand-text rounded hover:bg-gray-200">全解除</button>
                            <button
                                type="button"
                                :disabled="selectedUserIds.length === 0"
                                @click="exportCsv"
                                class="px-3 py-1.5 text-sm bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                CSVダウンロード
                            </button>
                            <span class="text-xs text-brand-text-muted">選択中: {{ selectedUserIds.length }}件</span>
                            <button
                                type="button"
                                @click="exportSummaryCsv"
                                class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700"
                            >
                                月次集計CSV
                            </button>
                        </div>

                        <div class="mb-4 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                @click="openLeaveModal"
                                class="px-3 py-1.5 text-sm bg-amber-600 text-white rounded hover:bg-amber-700"
                            >
                                ＋ 休暇登録
                            </button>
                            <span class="text-xs text-brand-text-muted">有給・特別休暇・欠勤を登録します</span>
                        </div>

                        <!-- 登録済み休暇（期間内） -->
                        <div v-if="leaves?.length" class="mb-4 rounded-md border border-brand-border overflow-hidden">
                            <div class="px-3 py-2 bg-brand-surface-2 text-sm font-medium text-brand-text">登録済みの休暇（{{ leaves.length }}件）</div>
                            <div class="divide-y divide-brand-border">
                                <div v-for="lv in leaves" :key="lv.id" class="flex items-center gap-3 px-3 py-1.5 text-sm">
                                    <span class="font-mono text-brand-text-muted">{{ lv.date }}</span>
                                    <span>{{ userName(lv.user_id) }}</span>
                                    <span :class="leaveTypeClass(lv.leave_type)" class="inline-block rounded px-1.5 py-0.5 text-xs font-medium">{{ leaveTypeLabel(lv.leave_type) }}</span>
                                    <span v-if="lv.note" class="text-xs text-brand-text-muted">{{ lv.note }}</span>
                                    <button type="button" @click="deleteLeave(lv)" class="ml-auto text-red-600 hover:text-red-800 text-xs">削除</button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4 flex items-center gap-4">
                            <span class="text-sm font-medium text-brand-text">表示方法</span>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="displayMode"
                                    type="radio"
                                    value="table"
                                    class="rounded-full border-brand-border text-brand-primary focus:ring-brand-primary"
                                />
                                <span class="text-sm text-brand-text">テーブル表示</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="displayMode"
                                    type="radio"
                                    value="byUser"
                                    class="rounded-full border-brand-border text-brand-primary focus:ring-brand-primary"
                                />
                                <span class="text-sm text-brand-text">ユーザー毎</span>
                            </label>
                        </div>

                        <!-- テーブル表示 -->
                        <div v-if="displayMode === 'table'" class="space-y-4">
                            <div v-if="uniqueUsersInData.length > 0" class="flex flex-wrap items-center gap-2 p-3 bg-brand-surface-2 rounded-md text-sm">
                                <span class="font-medium text-brand-text">エクスポート対象ユーザー:</span>
                                <label v-for="u in uniqueUsersInData" :key="u.id" class="flex items-center gap-1.5 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="selectedUserIds.includes(u.id)"
                                        @change="toggleUserSelection(u.id)"
                                        class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                                    />
                                    <span>{{ u.name }}</span>
                                </label>
                            </div>
                            <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-brand-border text-sm">
                                <thead class="bg-brand-surface-2">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-brand-text">日付</th>
                                        <th class="px-4 py-2 text-left font-medium text-brand-text">ユーザー</th>
                                        <th class="px-4 py-2 text-left font-medium text-brand-text">店舗</th>
                                        <th class="px-4 py-2 text-left font-medium text-brand-text">ステータス</th>
                                        <th class="px-4 py-2 text-left font-medium text-brand-text">出勤打刻</th>
                                        <th class="px-4 py-2 text-left font-medium text-brand-text">退勤打刻</th>
                                        <th class="px-4 py-2 text-left font-medium text-brand-text">ベース出勤</th>
                                        <th class="px-4 py-2 text-left font-medium text-brand-text">ベース退勤</th>
                                        <th class="px-4 py-2 text-left font-medium text-brand-text">業務開始(給与)</th>
                                        <th class="px-4 py-2 text-left font-medium text-brand-text">業務終了(給与)</th>
                                        <th class="px-4 py-2 text-left font-medium text-brand-text">残業(丸め)</th>
                                        <th class="px-4 py-2 text-left font-medium text-brand-text w-20">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-brand-border">
                                    <tr v-for="r in records.data" :key="r.id" :class="[r.payroll?.needs_pattern ? 'bg-orange-50 hover:bg-orange-100' : 'hover:bg-brand-surface-2']">
                                        <td class="px-4 py-2 align-top">
                                            <div class="whitespace-nowrap">{{ formatDateJaWithWeekday(r.date) }}</div>
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                <span v-if="r.payroll?.needs_pattern" class="inline-block rounded px-1.5 py-0.5 text-xs font-medium bg-orange-200 text-orange-900">要パターン設定</span>
                                                <span v-if="r.payroll?.needs_work_attribute" class="inline-block rounded px-1.5 py-0.5 text-xs font-medium bg-rose-200 text-rose-900">要勤務属性設定</span>
                                                <span v-for="badge in payrollBadges(r.payroll)" :key="badge.label" :class="badge.class" class="inline-block rounded px-1.5 py-0.5 text-xs font-medium">{{ badge.label }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2">{{ r.user?.name ?? '-' }}</td>
                                        <td class="px-4 py-2">{{ r.shop?.name ?? '-' }}</td>
                                        <td class="px-4 py-2">
                                            <span :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span>
                                        </td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.clock_in_at) }}</td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.clock_out_at) }}</td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.payroll?.base_start_at) }}</td>
                                        <td class="px-4 py-2">{{ formatTimeJa(r.payroll?.base_end_at) }}</td>
                                        <td class="px-4 py-2" :class="payrollClockInClass(r.payroll)">{{ formatTimeJa(r.payroll?.payroll_clock_in_at) }}</td>
                                        <td class="px-4 py-2" :class="payrollClockOutClass(r.payroll)">{{ formatTimeJa(r.payroll?.payroll_clock_out_at) }}</td>
                                        <td class="px-4 py-2">{{ formatOvertimeRounded(r.payroll?.overtime_minutes_rounded) }}</td>
                                        <td class="px-4 py-2">
                                            <button type="button" @click="openEditModal(r)" class="text-brand-primary hover:text-brand-primary-hover text-sm">詳細</button>
                                        </td>
                                    </tr>
                                    <tr v-if="!records.data || records.data.length === 0">
                                        <td colspan="12" class="px-4 py-8 text-center text-brand-text-muted">データがありません</td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>

                        <!-- ユーザー毎表示 -->
                        <div v-else class="space-y-6">
                            <template v-if="groupedByUser.length > 0">
                                <section
                                    v-for="group in groupedByUser"
                                    :key="group.userId"
                                    class="rounded-lg border border-brand-border overflow-hidden"
                                >
                                    <div class="px-4 py-2 bg-brand-surface-2 border-b border-brand-border font-medium text-brand-text flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            :checked="selectedUserIds.includes(group.userId)"
                                            @change="toggleUserSelection(group.userId)"
                                            class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                                        />
                                        <span>{{ group.userName }}（{{ group.records.length }}件）</span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-brand-border text-sm">
                                            <thead class="bg-brand-surface-2">
                                                <tr>
                                                    <th class="px-4 py-2 text-left font-medium text-brand-text">日付</th>
                                                    <th class="px-4 py-2 text-left font-medium text-brand-text">店舗</th>
                                                    <th class="px-4 py-2 text-left font-medium text-brand-text">ステータス</th>
                                                    <th class="px-4 py-2 text-left font-medium text-brand-text">出勤打刻</th>
                                                    <th class="px-4 py-2 text-left font-medium text-brand-text">退勤打刻</th>
                                                    <th class="px-4 py-2 text-left font-medium text-brand-text">ベース出勤</th>
                                                    <th class="px-4 py-2 text-left font-medium text-brand-text">ベース退勤</th>
                                                    <th class="px-4 py-2 text-left font-medium text-brand-text">業務開始(給与)</th>
                                                    <th class="px-4 py-2 text-left font-medium text-brand-text">業務終了(給与)</th>
                                                    <th class="px-4 py-2 text-left font-medium text-brand-text">残業(丸め)</th>
                                                    <th class="px-4 py-2 text-left font-medium text-brand-text w-20">操作</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-brand-border">
                                                <tr v-for="r in group.records" :key="r.id" :class="[r.payroll?.needs_pattern ? 'bg-orange-50 hover:bg-orange-100' : 'hover:bg-brand-surface-2']">
                                                    <td class="px-4 py-2 align-top">
                                                        <div class="whitespace-nowrap">{{ formatDateJaWithWeekday(r.date) }}</div>
                                                        <div class="mt-1 flex flex-wrap gap-1">
                                                            <span v-if="r.payroll?.needs_pattern" class="inline-block rounded px-1.5 py-0.5 text-xs font-medium bg-orange-200 text-orange-900">要パターン設定</span>
                                                            <span v-if="r.payroll?.needs_work_attribute" class="inline-block rounded px-1.5 py-0.5 text-xs font-medium bg-rose-200 text-rose-900">要勤務属性設定</span>
                                                            <span v-for="badge in payrollBadges(r.payroll)" :key="badge.label" :class="badge.class" class="inline-block rounded px-1.5 py-0.5 text-xs font-medium">{{ badge.label }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2">{{ r.shop?.name ?? '-' }}</td>
                                                    <td class="px-4 py-2">
                                                        <span :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span>
                                                    </td>
                                                    <td class="px-4 py-2">{{ formatTimeJa(r.clock_in_at) }}</td>
                                                    <td class="px-4 py-2">{{ formatTimeJa(r.clock_out_at) }}</td>
                                                    <td class="px-4 py-2">{{ formatTimeJa(r.payroll?.base_start_at) }}</td>
                                                    <td class="px-4 py-2">{{ formatTimeJa(r.payroll?.base_end_at) }}</td>
                                                    <td class="px-4 py-2" :class="payrollClockInClass(r.payroll)">{{ formatTimeJa(r.payroll?.payroll_clock_in_at) }}</td>
                                                    <td class="px-4 py-2" :class="payrollClockOutClass(r.payroll)">{{ formatTimeJa(r.payroll?.payroll_clock_out_at) }}</td>
                                                    <td class="px-4 py-2">{{ formatOvertimeRounded(r.payroll?.overtime_minutes_rounded) }}</td>
                                                    <td class="px-4 py-2">
                                                        <button type="button" @click="openEditModal(r)" class="text-brand-primary hover:text-brand-primary-hover text-sm">詳細</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- 月次集計（CSV出力と同じ列） -->
                                    <div class="border-t border-brand-border bg-brand-surface-2/50">
                                        <div class="flex items-center justify-between px-4 py-2">
                                            <span class="text-sm font-medium text-brand-text">月次集計</span>
                                            <button type="button" @click="openSummaryDetail(group)" class="text-brand-primary hover:text-brand-primary-hover text-sm">詳細（計算ロジック）</button>
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-sm">
                                                <thead>
                                                    <tr class="bg-brand-surface-2">
                                                        <th v-for="col in SUMMARY_COLUMNS" :key="col.key" class="px-3 py-1.5 text-left font-medium text-brand-text whitespace-nowrap">{{ col.label }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td v-for="col in SUMMARY_COLUMNS" :key="col.key" class="px-3 py-1.5 whitespace-nowrap tabular-nums">{{ col.fmt(group.summary[col.key]) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </section>
                            </template>
                            <p v-else class="py-8 text-center text-brand-text-muted text-sm">データがありません</p>
                        </div>

                        <!-- 月次集計 詳細（計算ロジック・使用データ）モーダル -->
                        <div v-if="summaryDetail.open" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="summaryDetail.open = false">
                            <div class="bg-brand-surface rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                                <div class="p-4 border-b font-medium text-brand-text flex items-center justify-between sticky top-0 bg-brand-surface">
                                    <span>月次集計の詳細 — {{ summaryDetail.group?.userName }}</span>
                                    <button type="button" @click="summaryDetail.open = false" class="text-brand-text-muted hover:text-brand-text text-sm">閉じる</button>
                                </div>
                                <div class="p-4 space-y-4">
                                    <p class="text-xs text-brand-text-muted">集計期間：{{ filters.from }} 〜 {{ filters.to }}。各列の計算ロジックと、実際に集計へ使用したデータ（日付別の寄与）を表示します。</p>
                                    <div v-for="col in SUMMARY_COLUMNS" :key="col.key" class="rounded-md border border-brand-border">
                                        <div class="flex items-center justify-between px-3 py-2 bg-brand-surface-2">
                                            <span class="font-medium text-brand-text text-sm">{{ col.label }}</span>
                                            <span class="text-sm tabular-nums font-mono">{{ col.fmt(summaryDetail.group.summary[col.key]) }}</span>
                                        </div>
                                        <div class="px-3 py-2 space-y-2">
                                            <p class="text-xs text-brand-text-muted">{{ col.logic }}</p>
                                            <div v-if="col.rows(summaryDetail.group).length" class="text-xs">
                                                <div class="font-medium text-brand-text-muted mb-1">使用データ（{{ col.rows(summaryDetail.group).length }}件）</div>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <span v-for="(row, i) in col.rows(summaryDetail.group)" :key="i" class="inline-flex items-center gap-1 rounded bg-brand-surface-2 px-2 py-1">
                                                        <span class="text-brand-text-muted">{{ formatDateJaWithWeekday(row.date) }}</span>
                                                        <span class="font-medium text-brand-text">{{ row.value }}</span>
                                                    </span>
                                                </div>
                                            </div>
                                            <p v-else class="text-xs text-brand-text-muted">該当データなし（0）</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 休暇登録モーダル -->
                        <div v-if="leaveModal.open" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="leaveModal.open = false">
                            <div class="bg-brand-surface rounded-lg shadow-xl max-w-md w-full">
                                <div class="p-4 border-b font-medium text-brand-text">休暇登録</div>
                                <form @submit.prevent="submitLeave" class="p-4 space-y-4">
                                    <div>
                                        <label class="block text-xs font-medium text-brand-text-muted mb-1">スタッフ</label>
                                        <select v-model="leaveModal.user_id" class="w-full rounded-md border-brand-border text-sm">
                                            <option value="">選択してください</option>
                                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-brand-text-muted mb-1">日付</label>
                                        <input v-model="leaveModal.date" type="date" class="w-full rounded-md border-brand-border text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-brand-text-muted mb-1">区分</label>
                                        <select v-model="leaveModal.leave_type" class="w-full rounded-md border-brand-border text-sm">
                                            <option v-for="t in LEAVE_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-brand-text-muted mb-1">メモ（任意）</label>
                                        <input v-model="leaveModal.note" type="text" class="w-full rounded-md border-brand-border text-sm" />
                                    </div>
                                    <div v-if="Object.keys(leaveModal.errors).length" class="p-2 bg-red-50 text-red-700 rounded text-sm">
                                        <ul><li v-for="(msg, k) in leaveModal.errors" :key="k">{{ msg }}</li></ul>
                                    </div>
                                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-brand-border">
                                        <button type="button" @click="leaveModal.open = false" class="px-4 py-2 text-brand-text-muted rounded-md text-sm hover:bg-brand-surface-2">キャンセル</button>
                                        <button type="submit" :disabled="leaveModal.processing" class="px-4 py-2 bg-amber-600 text-white rounded-md text-sm font-medium hover:bg-amber-700 disabled:opacity-50">
                                            {{ leaveModal.processing ? '登録中...' : '登録する' }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- 編集モーダル -->
                        <div v-if="editRecord" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="editRecord = null">
                            <div class="bg-brand-surface rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
                                <div class="p-4 border-b font-medium text-brand-text flex items-center justify-between">
                                    <span>勤怠の詳細</span>
                                    <span :class="statusClass(editRecord.status)">{{ statusLabel(editRecord.status) }}</span>
                                </div>
                                <form @submit.prevent="submitEdit" class="p-4 space-y-4">
                                    <div>
                                        <label class="block text-xs font-medium text-brand-text-muted mb-1">日付</label>
                                        <input :value="formatDateJaWithWeekday(editRecord.date)" type="text" readonly class="w-full rounded-md border-brand-border bg-brand-surface-2 text-sm" />
                                    </div>

                                    <!-- 適用パターン（振替出勤・会社カレンダーに無い日のベース時刻算出用） -->
                                    <div v-if="editRecord.payroll?.needs_pattern || editForm.pattern_override" class="rounded-md border border-orange-200 bg-orange-50 p-3 space-y-3">
                                        <p class="text-xs text-orange-900">
                                            会社カレンダーにこの日のパターンがありません。振替出勤などの場合は、ベース出勤・退勤を算出するためのパターンを選択してください。
                                        </p>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium text-brand-text-muted mb-1">適用パターン</label>
                                                <select v-model="editForm.pattern_override" class="w-full rounded-md border-brand-border text-sm">
                                                    <option value="">未設定</option>
                                                    <option v-for="p in PATTERN_OPTIONS" :key="p" :value="p">{{ p }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-brand-text-muted mb-1">振替対象日（任意）</label>
                                                <input v-model="editForm.substitute_for_date" type="date" class="w-full rounded-md border-brand-border text-sm" />
                                            </div>
                                        </div>
                                        <p class="text-xs text-brand-text-muted">
                                            「振替対象日」（代わりに休んだ出勤予定日）を登録すると振替勤務として扱われ、休日出勤日数には計上されません。
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text-muted mb-1">出勤時刻</label>
                                            <input v-model="editForm.clock_in_time" type="time" class="w-full rounded-md border-brand-border text-sm" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text-muted mb-1">退勤時刻</label>
                                            <input v-model="editForm.clock_out_time" type="time" class="w-full rounded-md border-brand-border text-sm" />
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center mb-2">
                                            <label class="block text-xs font-medium text-brand-text-muted">休憩</label>
                                            <button type="button" @click="addEditBreak" class="text-sm text-brand-primary hover:text-brand-primary-hover">+ 休憩を追加</button>
                                        </div>
                                        <div v-for="(b, i) in editForm.breaks" :key="i" class="flex gap-2 items-center mb-2">
                                            <input v-model="b.start_time" type="time" class="flex-1 rounded-md border-brand-border text-sm" />
                                            <input v-model="b.end_time" type="time" class="flex-1 rounded-md border-brand-border text-sm" />
                                            <button type="button" @click="editForm.breaks.splice(i, 1)" class="text-red-600 hover:text-red-800 text-sm">削除</button>
                                        </div>
                                    </div>
                                    <div v-if="Object.keys(editForm.errors).length" class="p-2 bg-red-50 text-red-700 rounded text-sm">
                                        <ul>
                                            <li v-for="(msg, k) in editForm.errors" :key="k">{{ msg }}</li>
                                        </ul>
                                    </div>
                                    <!-- 承認（未申請・申請済のみ） -->
                                    <div v-if="canApprove" class="rounded-md border border-green-200 bg-green-50 p-3 space-y-2">
                                        <div class="flex items-center gap-1.5 text-sm font-medium text-green-900">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            この勤怠を承認する
                                        </div>
                                        <p class="text-xs text-green-800">
                                            現在<strong>保存済みの内容</strong>で承認します。時刻を修正した場合は、先に下の「変更を保存」を押してください。
                                        </p>
                                        <button type="button" @click="approveRecord" :disabled="editForm.processing" class="w-full px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700 disabled:opacity-50">
                                            {{ editForm.processing ? '処理中...' : '承認する' }}
                                        </button>
                                    </div>

                                    <!-- 編集内容の保存／キャンセル -->
                                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-brand-border">
                                        <button type="button" @click="editRecord = null" class="px-4 py-2 text-brand-text-muted rounded-md text-sm hover:bg-brand-surface-2">キャンセル</button>
                                        <button type="submit" :disabled="editForm.processing" class="px-4 py-2 bg-brand-primary text-white rounded-md text-sm font-medium hover:bg-brand-primary-hover disabled:opacity-50">
                                            {{ editForm.processing ? '保存中...' : '変更を保存' }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatTimeJa, formatDateJa, formatDateJaWithWeekday, formatDateInputValueJa } from '@/utils/dateFormat';

function formatOvertimeRounded(val) {
    if (val === null || val === undefined) return '-';
    return `${val}分`;
}

// 業務開始(給与)セルの背景色
// - early / no_base（打刻時刻を採用）: オレンジ
// - late（遅刻で打刻時刻を採用）: 青
// - on_time / 打刻なし: 着色なし
function payrollClockInClass(payroll) {
    const category = payroll?.clock_in_category;
    if (category === 'early' || category === 'no_base') {
        return 'bg-orange-100 text-orange-900';
    }
    if (category === 'late') {
        return 'bg-blue-100 text-blue-900';
    }
    return '';
}

// 業務終了(給与)セルの背景色（残業ありは残業バッジと同じ紫）
function payrollClockOutClass(payroll) {
    if ((payroll?.overtime_minutes_rounded ?? 0) > 0) {
        return 'bg-purple-100 text-purple-900';
    }
    return '';
}

const props = defineProps({
    records: Object,
    shops: Array,
    users: Array,
    usersByShop: Object,
    leaves: { type: Array, default: () => [] },
    filters: Object,
});

const displayMode = ref('byUser');

// 会社カレンダーのパターン（WorkAttribute::PATTERNS 相当）
const PATTERN_OPTIONS = ['A', 'B', 'C'];

// 休暇区分
const LEAVE_TYPES = [
    { value: 'paid', label: '有給' },
    { value: 'special', label: '特別休暇' },
    { value: 'absence', label: '欠勤' },
];
function leaveTypeLabel(t) {
    return LEAVE_TYPES.find((x) => x.value === t)?.label ?? t;
}
function leaveTypeClass(t) {
    if (t === 'paid') return 'bg-green-100 text-green-800';
    if (t === 'special') return 'bg-blue-100 text-blue-800';
    return 'bg-gray-200 text-gray-800'; // absence
}
function userName(userId) {
    return props.users.find((u) => u.id === userId)?.name ?? `#${userId}`;
}

// 休暇登録モーダル
const leaveModal = ref({ open: false, user_id: '', date: '', leave_type: 'paid', note: '', processing: false, errors: {} });
function openLeaveModal() {
    leaveModal.value = {
        open: true,
        user_id: props.filters?.user_id || '',
        date: props.filters?.to || '',
        leave_type: 'paid',
        note: '',
        processing: false,
        errors: {},
    };
}
function submitLeave() {
    leaveModal.value.processing = true;
    leaveModal.value.errors = {};
    router.post(route('admin.attendance.leaves.store'), {
        user_id: leaveModal.value.user_id,
        date: leaveModal.value.date,
        leave_type: leaveModal.value.leave_type,
        note: leaveModal.value.note || null,
        ...currentFilterParams(),
    }, {
        preserveScroll: true,
        onError: (errors) => { leaveModal.value.errors = errors; leaveModal.value.processing = false; },
        onFinish: () => { leaveModal.value.processing = false; },
        onSuccess: () => { leaveModal.value.open = false; },
    });
}
function deleteLeave(lv) {
    if (!confirm(`${lv.date} ${userName(lv.user_id)} の休暇を削除しますか？`)) return;
    router.delete(route('admin.attendance.leaves.destroy', lv.id), {
        data: currentFilterParams(),
        preserveScroll: true,
    });
}

// 現在のフィルタ条件（リダイレクト先で同じ期間を維持するため）
function currentFilterParams() {
    return Object.fromEntries(
        Object.entries({
            shop_id: filters.value.shop_id,
            user_id: filters.value.user_id,
            from: filters.value.from,
            to: filters.value.to,
        }).filter(([, v]) => v)
    );
}

// 月次集計CSV（1スタッフ=1行）をダウンロード
function exportSummaryCsv() {
    const params = new URLSearchParams(currentFilterParams());
    window.location.href = route('admin.attendance.export-summary-csv') + (params.toString() ? `?${params.toString()}` : '');
}

const editRecord = ref(null);
const editForm = ref({
    clock_in_time: '',
    clock_out_time: '',
    breaks: [],
    processing: false,
    errors: {},
});

function isoToTime(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (isNaN(date.getTime())) return '';
    const h = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    return `${h}:${min}`;
}

function openEditModal(r) {
    const dateStr = r.date ? formatDateInputValueJa(r.date) : '';
    editRecord.value = r;
    editForm.value = {
        clock_in_time: isoToTime(r.clock_in_at),
        clock_out_time: isoToTime(r.clock_out_at),
        breaks: (r.breaks || []).map((b) => ({ start_time: isoToTime(b.start_at), end_time: isoToTime(b.end_at) })),
        pattern_override: r.pattern_override ?? '',
        substitute_for_date: r.substitute_for_date ? String(r.substitute_for_date).slice(0, 10) : '',
        processing: false,
        errors: {},
    };
}

function addEditBreak() {
    editForm.value.breaks.push({ start_time: '', end_time: '' });
}

function buildDatetime(dateStr, timeStr) {
    if (!dateStr || !timeStr) return null;
    return `${dateStr}T${timeStr}:00`;
}

function submitEdit() {
    if (!editRecord.value) return;
    const r = editRecord.value;
    const dateStr = r.date ? formatDateInputValueJa(r.date) : '';
    const clockInAt = buildDatetime(dateStr, editForm.value.clock_in_time);
    const clockOutAt = buildDatetime(dateStr, editForm.value.clock_out_time);
    const breaks = editForm.value.breaks
        .filter((b) => b.start_time)
        .map((b) => ({
            start_at: buildDatetime(dateStr, b.start_time),
            end_at: b.end_time ? buildDatetime(dateStr, b.end_time) : null,
        }));
    editForm.value.processing = true;
    editForm.value.errors = {};
    router.put(route('admin.attendance.update', r.id), {
        clock_in_at: clockInAt,
        clock_out_at: clockOutAt,
        breaks,
        pattern_override: editForm.value.pattern_override || null,
        substitute_for_date: editForm.value.substitute_for_date || null,
        ...Object.fromEntries(
            Object.entries({
                shop_id: filters.value.shop_id,
                user_id: filters.value.user_id,
                from: filters.value.from,
                to: filters.value.to,
            }).filter(([, v]) => v)
        ),
    }, {
        preserveScroll: true,
        onError: (errors) => {
            editForm.value.errors = errors;
            editForm.value.processing = false;
        },
        onFinish: () => {
            editForm.value.processing = false;
        },
        onSuccess: () => {
            editRecord.value = null;
        },
    });
}

// 未申請・申請済のみ承認可能
const canApprove = computed(() => {
    const s = editRecord.value?.status;
    return s === 'draft' || s === 'applied';
});

function approveRecord() {
    if (!editRecord.value) return;
    const r = editRecord.value;
    editForm.value.processing = true;
    editForm.value.errors = {};
    router.post(route('admin.attendance.approve', r.id), {
        ...Object.fromEntries(
            Object.entries({
                shop_id: filters.value.shop_id,
                user_id: filters.value.user_id,
                from: filters.value.from,
                to: filters.value.to,
            }).filter(([, v]) => v)
        ),
    }, {
        preserveScroll: true,
        onError: (errors) => {
            editForm.value.errors = errors;
            editForm.value.processing = false;
        },
        onFinish: () => {
            editForm.value.processing = false;
        },
        onSuccess: () => {
            editRecord.value = null;
        },
    });
}

const filters = ref({
    shop_id: props.filters?.shop_id ?? '',
    user_id: props.filters?.user_id ?? '',
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

const filteredUsers = computed(() => {
    if (filters.value.shop_id && props.usersByShop) {
        const list = props.usersByShop[filters.value.shop_id];
        return Array.isArray(list) ? list : props.users ?? [];
    }
    return props.users ?? [];
});

watch(() => filters.value.shop_id, () => {
    const ids = filteredUsers.value.map((u) => u.id);
    if (filters.value.user_id && !ids.includes(Number(filters.value.user_id))) {
        filters.value.user_id = '';
    }
});

const groupedByUser = computed(() => {
    const data = props.records?.data ?? [];
    const map = new Map();
    for (const r of data) {
        const uid = r.user_id ?? r.user?.id ?? 0;
        const name = r.user?.name ?? '-';
        if (!map.has(uid)) {
            map.set(uid, { userId: uid, userName: name, records: [] });
        }
        map.get(uid).records.push(r);
    }
    const groups = Array.from(map.values()).sort((a, b) => String(a.userName).localeCompare(String(b.userName)));
    for (const g of groups) {
        g.summary = buildUserSummary(g.userId, g.records);
    }
    return groups;
});

// ユーザーの月次集計（CSVと同じ12項目に対応する生値）
function buildUserSummary(userId, records) {
    const s = {
        attend_days: 0, holiday_work_days: 0,
        paid_days: 0, special_days: 0, absence_days: 0,
        work_minutes: 0, overtime_normal_minutes: 0, night_minutes: 0,
        late_early_count: 0, late_early_minutes: 0,
    };
    for (const r of records) {
        const m = r.metrics ?? {};
        if (m.worked) s.attend_days += 1;
        if (m.holiday_work) s.holiday_work_days += 1;
        s.work_minutes += m.work_minutes ?? 0;
        s.overtime_normal_minutes += m.overtime_normal_minutes ?? 0;
        s.night_minutes += m.night_minutes ?? 0;
        s.late_early_count += m.late_early_count ?? 0;
        s.late_early_minutes += m.late_early_minutes ?? 0;
    }
    const userLeaves = (props.leaves ?? []).filter((lv) => lv.user_id === userId);
    s.paid_days = userLeaves.filter((lv) => lv.leave_type === 'paid').length;
    s.special_days = userLeaves.filter((lv) => lv.leave_type === 'special').length;
    s.absence_days = userLeaves.filter((lv) => lv.leave_type === 'absence').length;
    return s;
}

// サマリ表示用フォーマット（CSVに合わせる）
function fmtDays(n) { return n > 0 ? n.toFixed(2) : '-'; }
function fmtMinutes(m) {
    if (!m || m <= 0) return '-';
    return `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')}`;
}
function fmtCount(n) { return n > 0 ? String(n) : '-'; }

// サマリの列定義（CSV出力と同じ並び・計算ロジック説明つき）
const SUMMARY_COLUMNS = [
    { key: 'attend_days', label: '出勤日数', fmt: fmtDays,
      logic: '期間内に出勤打刻（clock_in）がある日数。承認状態は問わず、休日出勤日数を含む。',
      rows: (g) => g.records.filter((r) => r.metrics?.worked).map((r) => ({ date: r.date, value: '出勤' })) },
    { key: 'holiday_work_days', label: '休日出勤日数', fmt: fmtDays,
      logic: '会社カレンダーにパターン（A/B/C）が無い日への出勤のうち、振替対象日が未登録のものの日数。振替対象日を登録した日は除外。',
      rows: (g) => g.records.filter((r) => r.metrics?.holiday_work).map((r) => ({ date: r.date, value: '休日出勤' })) },
    { key: 'paid_days', label: '有給日数', fmt: fmtDays,
      logic: '休暇登録で「有給」として登録された日数。',
      rows: (g) => leavesOf(g.userId, 'paid').map((lv) => ({ date: lv.date, value: '有給' + (lv.note ? `（${lv.note}）` : '') })) },
    { key: 'special_days', label: '特別休暇日数', fmt: fmtDays,
      logic: '休暇登録で「特別休暇」として登録された日数。',
      rows: (g) => leavesOf(g.userId, 'special').map((lv) => ({ date: lv.date, value: '特別休暇' + (lv.note ? `（${lv.note}）` : '') })) },
    { key: 'absence_days', label: '欠勤日数', fmt: fmtDays,
      logic: '休暇登録で「欠勤」として登録された日数。',
      rows: (g) => leavesOf(g.userId, 'absence').map((lv) => ({ date: lv.date, value: '欠勤' + (lv.note ? `（${lv.note}）` : '') })) },
    { key: 'work_minutes', label: '就労時間', fmt: fmtMinutes,
      logic: '各日の「給与用（丸め後）の在社時間 − 休憩時間」を合算。給与用＝丸め後の業務開始〜業務終了。',
      rows: (g) => g.records.filter((r) => (r.metrics?.work_minutes ?? 0) > 0).map((r) => ({ date: r.date, value: fmtMinutes(r.metrics.work_minutes) })) },
    { key: 'overtime_normal_minutes', label: '普通残業', fmt: fmtMinutes,
      logic: '各日の残業（ベース終了以降の超過・丸め後）から、深夜帯（22:00〜翌5:00）に重なる残業分を差し引いた分を合算。',
      rows: (g) => g.records.filter((r) => (r.metrics?.overtime_normal_minutes ?? 0) > 0).map((r) => ({ date: r.date, value: fmtMinutes(r.metrics.overtime_normal_minutes) })) },
    { key: 'night_minutes', label: '深夜残業', fmt: fmtMinutes,
      logic: '各日の全勤務のうち 22:00〜翌5:00 に重なる時間（深夜帯にかかる休憩は控除）を合算。',
      rows: (g) => g.records.filter((r) => (r.metrics?.night_minutes ?? 0) > 0).map((r) => ({ date: r.date, value: fmtMinutes(r.metrics.night_minutes) })) },
    { key: 'late_early_count', label: '遅早回数', fmt: fmtCount,
      logic: 'ベース時刻が解決できた日で、出勤がベース開始より遅い（遅刻）または退勤がベース終了より早い（早退）日数。1日あたり最大1回。',
      rows: (g) => g.records.filter((r) => (r.metrics?.late_early_count ?? 0) > 0).map((r) => ({ date: r.date, value: '遅刻/早退あり' })) },
    { key: 'late_early_minutes', label: '遅早時間', fmt: fmtMinutes,
      logic: '各日の遅刻分（出勤−ベース開始）＋早退分（ベース終了−退勤）を合算。ベース解決日のみ・猶予なし（1分でも計上）。',
      rows: (g) => g.records.filter((r) => (r.metrics?.late_early_minutes ?? 0) > 0).map((r) => ({ date: r.date, value: fmtMinutes(r.metrics.late_early_minutes) })) },
];

function leavesOf(userId, type) {
    return (props.leaves ?? []).filter((lv) => lv.user_id === userId && lv.leave_type === type);
}

// サマリ詳細モーダル
const summaryDetail = ref({ open: false, group: null });
function openSummaryDetail(group) {
    summaryDetail.value = { open: true, group };
}

const selectedUserIds = ref([]);

const uniqueUsersInData = computed(() => {
    const data = props.records?.data ?? [];
    const map = new Map();
    for (const r of data) {
        const uid = r.user_id ?? r.user?.id;
        if (uid && !map.has(uid)) {
            map.set(uid, { id: uid, name: r.user?.name ?? '-' });
        }
    }
    return Array.from(map.values()).sort((a, b) => String(a.name).localeCompare(String(b.name)));
});

function toggleUserSelection(userId) {
    const id = Number(userId);
    const idx = selectedUserIds.value.indexOf(id);
    if (idx >= 0) {
        selectedUserIds.value = selectedUserIds.value.filter((x) => x !== id);
    } else {
        selectedUserIds.value = [...selectedUserIds.value, id];
    }
}

function selectAllUsers() {
    selectedUserIds.value = uniqueUsersInData.value.map((u) => u.id);
}

function deselectAllUsers() {
    selectedUserIds.value = [];
}

function exportCsv() {
    if (selectedUserIds.value.length === 0) {
        return;
    }
    const params = new URLSearchParams();
    selectedUserIds.value.forEach((id) => params.append('user_ids[]', id));
    if (filters.value.from) params.set('from', filters.value.from);
    if (filters.value.to) params.set('to', filters.value.to);
    if (filters.value.shop_id) params.set('shop_id', filters.value.shop_id);
    const url = route('admin.attendance.export-csv') + '?' + params.toString();
    window.location.href = url;
}

function applyFilters() {
    router.get(route('admin.attendance.index'), {
        shop_id: filters.value.shop_id || undefined,
        user_id: filters.value.user_id || undefined,
        from: filters.value.from || undefined,
        to: filters.value.to || undefined,
    }, { preserveState: true });
}

function statusLabel(s) {
    const labels = { draft: '未申請', applied: '申請済', approved: '承認済' };
    return labels[s] ?? s;
}

function statusClass(s) {
    const classes = {
        draft: 'px-2 py-0.5 bg-brand-surface-2 text-brand-text rounded text-xs',
        applied: 'px-2 py-0.5 bg-amber-100 text-amber-800 rounded text-xs',
        approved: 'px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs',
    };
    return classes[s] ?? '';
}

// 日付の下に表示するバッジ（遅刻・早出・残業／複数可）
function payrollBadges(payroll) {
    const badges = [];
    const category = payroll?.clock_in_category;
    if (category === 'late') {
        badges.push({ label: '遅刻', class: 'bg-blue-100 text-blue-900' });
    }
    if (category === 'early') {
        badges.push({ label: '早出', class: 'bg-orange-100 text-orange-900' });
    }
    if ((payroll?.overtime_minutes_rounded ?? 0) > 0) {
        badges.push({ label: '残業', class: 'bg-purple-100 text-purple-900' });
    }
    return badges;
}
</script>
