<template>
    <Head title="顧客一覧" />

    <AdminLayout :breadcrumb="[{ label: '顧客' }, { label: '顧客一覧' }]">
        <UiPageHeader
            title="顧客一覧"
            description="登録顧客の検索・一括操作・追加ができます。"
        >
            <template #actions>
                <UiButton variant="primary" @click="showAddCustomerModal = true">
                    <template #leading><Plus :size="14" /></template>
                    顧客追加
                </UiButton>
            </template>
        </UiPageHeader>

        <div>
            <div class="mx-auto">
                <div class="flex flex-col lg:flex-row-reverse gap-6 lg:items-start">
                    <!-- 左：検索条件 -->
                    <aside
                        class="w-full lg:w-[22rem] shrink-0 lg:sticky lg:top-4 lg:max-h-[calc(100vh-5rem)] lg:overflow-y-auto"
                    >
                        <div class="bg-brand-surface rounded-xl border border-brand-border shadow-sm">
                            <div class="px-4 py-3 border-b border-brand-border bg-brand-surface-2 rounded-t-xl">
                                <h3 class="text-base font-semibold text-brand-text">検索条件</h3>
                                <p class="text-xs text-brand-text-muted mt-0.5 leading-snug">
                                    各ブロックを開いて絞り込み、検索を実行してください
                                </p>
                            </div>
                            <form @submit.prevent="searchCustomers" class="p-3 space-y-2">
                                <!-- タブ・検索ボタン（スクロール時も上部に固定表示） -->
                                <div class="sticky top-0 z-10 -mx-3 -mt-3 px-3 pt-3 pb-2 bg-brand-surface border-b border-brand-border space-y-2">
                                <!-- 検索条件タブ -->
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="tab in filterTabs"
                                        :key="tab.key"
                                        type="button"
                                        @click="setActiveFilterTab(tab.key)"
                                        :class="[
                                            'px-3 py-1.5 text-xs font-semibold rounded-md transition-colors',
                                            activeFilterTab === tab.key
                                                ? 'bg-brand-primary text-white'
                                                : 'text-brand-text-muted hover:bg-brand-surface-2'
                                        ]"
                                    >
                                        {{ tab.label }}
                                    </button>
                                </div>

                                <!-- 検索・リセット（タブと条件の間に配置） -->
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg border border-brand-border text-sm font-medium text-brand-text bg-brand-surface hover:bg-brand-surface-2 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-brand-primary"
                                        @click="resetSearch"
                                    >
                                        <RotateCcw :size="15" />
                                        リセット
                                    </button>
                                    <button
                                        type="submit"
                                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-5 py-2 rounded-lg bg-brand-primary text-white text-sm font-medium hover:bg-brand-primary-hover focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-brand-primary"
                                    >
                                        <Search :size="15" />
                                        検索
                                    </button>
                                </div>
                                </div>

                                <!-- 基本情報 -->
                                <div v-show="activeFilterTab === 'basic'" class="rounded-lg border border-brand-border bg-brand-surface overflow-hidden">
                                    <div class="px-3 pb-3 pt-3 space-y-3 bg-brand-surface-2/50">
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">顧客名</label>
                                            <input
                                                v-model="searchForm.name"
                                                type="text"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                                placeholder="顧客名で検索"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">ふりがな</label>
                                            <input
                                                v-model="searchForm.kana"
                                                type="text"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                                placeholder="ふりがなで検索"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">成人式エリア</label>
                                            <div class="space-y-2">
                                                <select
                                                    v-model="searchForm.ceremony_prefecture"
                                                    class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                                    @change="searchForm.ceremony_area_id = []"
                                                >
                                                    <option :value="null">全て</option>
                                                    <option v-for="pref in ceremonyPrefectures" :key="pref" :value="pref">
                                                        {{ pref }}
                                                    </option>
                                                </select>
                                                <div v-if="searchForm.ceremony_prefecture">
                                                    <div class="flex items-center justify-between mb-1">
                                                        <span class="text-xs text-brand-text-muted">市町村（複数選択可）</span>
                                                        <div class="flex gap-2">
                                                            <button
                                                                type="button"
                                                                class="text-xs text-brand-primary hover:underline"
                                                                @click="selectAllCeremonyAreas"
                                                            >
                                                                全選択
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="text-xs text-brand-text-muted hover:underline"
                                                                @click="clearCeremonyAreas"
                                                            >
                                                                全解除
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="rounded-md border border-brand-border bg-brand-surface max-h-40 overflow-y-auto p-2 space-y-1">
                                                        <label
                                                            v-for="area in searchCeremonyAreasFiltered"
                                                            :key="area.id"
                                                            class="flex items-center gap-2 text-sm text-brand-text cursor-pointer"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                :checked="isCeremonyAreaSelected(area.id)"
                                                                @change="toggleCeremonyArea(area.id)"
                                                                class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                                                            />
                                                            <span>{{ area.name }}</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">電話番号</label>
                                            <input
                                                v-model="searchForm.phone_number"
                                                type="text"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                                placeholder="電話番号で検索"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">担当店舗（複数選択可）</label>
                                            <div class="rounded-md border border-brand-border bg-brand-surface max-h-40 overflow-y-auto p-2 space-y-1">
                                                <label class="flex items-center gap-2 text-sm text-brand-text cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        :checked="isAllShopsSelected"
                                                        @change="selectAllShops"
                                                        class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                                                    />
                                                    <span>全て</span>
                                                </label>
                                                <label
                                                    v-for="shop in shops"
                                                    :key="shop.id"
                                                    class="flex items-center gap-2 text-sm text-brand-text cursor-pointer"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :checked="isShopSelected(shop.id)"
                                                        @change="toggleShopFilter(shop.id)"
                                                        class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                                                    />
                                                    <span>{{ shop.name }}</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">登録日（開始）</label>
                                            <input
                                                v-model="searchForm.created_at_from"
                                                type="date"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">登録日（終了）</label>
                                            <input
                                                v-model="searchForm.created_at_to"
                                                type="date"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- 成人式情報 -->
                                <div v-show="activeFilterTab === 'seijin'" class="rounded-lg border border-brand-border bg-brand-surface overflow-hidden">
                                    <div class="px-3 pb-3 pt-3 space-y-3 bg-brand-surface-2/50">
                                        <p v-if="seijinFilterOptionsLoading" class="text-xs text-brand-text-muted">候補を読み込み中…</p>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">仕度会場</label>
                                            <input
                                                v-model="searchForm.seijin_preparation_venue"
                                                type="text"
                                                list="index-search-seijin-preparation-venue-datalist"
                                                autocomplete="off"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                                placeholder="部分一致"
                                            />
                                            <datalist id="index-search-seijin-preparation-venue-datalist">
                                                <option
                                                    v-for="v in seijinVenueDatalistOptions"
                                                    :key="'sv-' + v"
                                                    :value="v"
                                                />
                                            </datalist>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">時間</label>
                                            <input
                                                v-model="searchForm.seijin_preparation_time"
                                                type="text"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                                placeholder="部分一致"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">他店お支度</label>
                                            <select
                                                v-model="searchForm.other_store_preparation"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option :value="true">あり</option>
                                                <option :value="false">なし</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">美容室名</label>
                                            <input
                                                v-model="searchForm.other_store_salon_name"
                                                type="text"
                                                list="index-search-other-store-salon-name-datalist"
                                                autocomplete="off"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                                placeholder="部分一致"
                                            />
                                            <p class="mt-1 text-[11px] text-brand-text-muted leading-snug">
                                                他店お支度が「あり」のとき、空欄のまま検索すると美容室名が未登録の顧客のみに絞り込みます。
                                            </p>
                                            <datalist id="index-search-other-store-salon-name-datalist">
                                                <option
                                                    v-for="n in seijinFilterSalonNames"
                                                    :key="'ss-' + n"
                                                    :value="n"
                                                />
                                            </datalist>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">着物発送日</label>
                                            <input
                                                v-model="searchForm.kimono_ship_date"
                                                type="date"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- 成約情報 -->
                                <div v-show="activeFilterTab === 'contract'" class="rounded-lg border border-brand-border bg-brand-surface overflow-hidden">
                                    <div class="px-3 pb-3 pt-3 space-y-3 bg-brand-surface-2/50">
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">成約ステータス</label>
                                            <select
                                                v-model="searchForm.contract_status"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option value="成約なし">成約なし</option>
                                                <option value="確定">確定</option>
                                                <option value="保留">保留</option>
                                                <option value="キャンセル">キャンセル</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">成約日（開始）</label>
                                            <input
                                                v-model="searchForm.contract_date_from"
                                                type="date"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">成約日（終了）</label>
                                            <input
                                                v-model="searchForm.contract_date_to"
                                                type="date"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">店舗</label>
                                            <select
                                                v-model="searchForm.shop_id"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                                                    {{ shop.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">プラン</label>
                                            <select
                                                v-model="searchForm.plan_id"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                                    {{ plan.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">着物種別</label>
                                            <select
                                                v-model="searchForm.kimono_type"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option value="振袖">振袖</option>
                                                <option value="袴">袴</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">安心保証</label>
                                            <select
                                                v-model="searchForm.warranty_flag"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option :value="true">あり</option>
                                                <option :value="false">なし</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">担当スタッフ</label>
                                            <select
                                                v-model="searchForm.user_id"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option v-for="user in users" :key="user.id" :value="user.id">
                                                    {{ user.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">お仕度会場</label>
                                            <input
                                                v-model="searchForm.preparation_venue"
                                                type="text"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                                placeholder="お仕度会場で検索"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">お仕度日程</label>
                                            <input
                                                v-model="searchForm.preparation_date"
                                                type="date"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- 制約情報 -->
                                <div v-show="activeFilterTab === 'constraint'" class="rounded-lg border border-brand-border bg-brand-surface overflow-hidden">
                                    <div class="px-3 pb-3 pt-3 space-y-3 bg-brand-surface-2/50">
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">制約の有無</label>
                                            <select
                                                v-model="searchForm.constraint_presence"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option value="制約なし">制約なし</option>
                                                <option value="制約あり">制約あり</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">制約テンプレート</label>
                                            <select
                                                v-model="searchForm.constraint_template_id"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option
                                                    v-for="t in constraintTemplates"
                                                    :key="t.id"
                                                    :value="t.id"
                                                >
                                                    {{ t.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">署名日（開始）</label>
                                            <input
                                                v-model="searchForm.constraint_signed_at_from"
                                                type="date"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">署名日（終了）</label>
                                            <input
                                                v-model="searchForm.constraint_signed_at_to"
                                                type="date"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">説明担当スタッフ</label>
                                            <select
                                                v-model="searchForm.constraint_explainer_user_id"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option v-for="user in users" :key="user.id" :value="user.id">
                                                    {{ user.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 前撮り情報 -->
                                <div v-show="activeFilterTab === 'photo'" class="rounded-lg border border-brand-border bg-brand-surface overflow-hidden">
                                    <div class="px-3 pb-3 pt-3 space-y-3 bg-brand-surface-2/50">
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">担当店舗</label>
                                            <select
                                                v-model="searchForm.photo_slot_shop_id"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                                                    {{ shop.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-brand-text mb-1">前撮り詳細未決定</label>
                                            <select
                                                v-model="searchForm.photo_slot_details_undecided"
                                                class="w-full rounded-md border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option :value="true">あり（詳細未決定あり）</option>
                                                <option :value="false">なし（詳細未決定なし）</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                            </form>
                        </div>
                    </aside>

                    <!-- 右：検索結果 -->
                    <div class="flex-1 min-w-0 w-full">
                        <div class="bg-brand-surface rounded-xl border border-brand-border shadow-sm overflow-hidden min-h-[min(320px,50vh)]">
                            <div class="px-4 py-3 border-b border-brand-border flex flex-wrap items-baseline justify-between gap-2 bg-brand-surface-2/60">
                                <h3 class="text-base font-semibold text-brand-text">検索結果</h3>
                                <p class="text-sm text-brand-text-muted">
                                    全
                                    <span class="font-medium text-brand-text tabular-nums">{{ customers.total ?? 0 }}</span>
                                    件
                                </p>
                            </div>
                            <!-- 適用中の絞り込み条件 -->
                            <div
                                v-if="filterChips.length"
                                class="px-4 py-2.5 border-b border-brand-border bg-brand-surface flex flex-wrap items-center gap-1.5"
                            >
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-brand-text-muted mr-1">
                                    <Filter :size="13" />
                                    絞り込み中
                                </span>
                                <span
                                    v-for="chip in filterChips"
                                    :key="chip.key"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-brand-primary/10 text-brand-primary text-xs font-medium"
                                >
                                    <span class="text-brand-text-muted">{{ chip.label }}:</span>{{ chip.value }}
                                </span>
                            </div>
                            <div class="p-4 sm:p-5">
                                <div class="overflow-x-auto rounded-lg border border-brand-border">
                                    <table class="min-w-full divide-y divide-brand-border">
                                        <thead class="bg-brand-surface-2">
                                            <tr>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">画像</th>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">顧客名</th>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">ふりがな</th>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">成人式エリア</th>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">電話番号</th>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-brand-text-muted uppercase tracking-wider">担当店舗</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-brand-surface divide-y divide-brand-border">
                                            <template v-if="!customers.data || customers.data.length === 0">
                                                <tr>
                                                    <td colspan="6" class="px-4 py-12 text-center text-sm text-brand-text-muted">
                                                        該当する顧客がありません
                                                    </td>
                                                </tr>
                                            </template>
                                            <template v-else>
                                                <tr
                                                    v-for="customer in customers.data"
                                                    :key="customer.id"
                                                    @click="router.visit(route('admin.customers.show', customer.id))"
                                                    class="cursor-pointer hover:bg-brand-surface-2 transition-colors"
                                                >
                                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap">
                                            <div class="w-16 h-16 rounded-lg overflow-hidden border border-brand-border bg-brand-surface-2 flex items-center justify-center">
                                                <img
                                                    v-if="getFullBodyPhoto(customer)"
                                                    :src="getPhotoUrl(getFullBodyPhoto(customer))"
                                                    :alt="customer.name"
                                                    class="w-full h-full object-cover"
                                                />
                                                <svg
                                                    v-else
                                                    class="w-8 h-8 text-brand-text-subtle"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                    />
                                                </svg>
                                            </div>
                                                    </td>
                                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap text-sm text-brand-text">{{ customer.name }}</td>
                                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap text-sm text-brand-text">{{ customer.kana || '-' }}</td>
                                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap text-sm text-brand-text">{{ customer.ceremony_area?.name || '-' }}</td>
                                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap text-sm text-brand-text">{{ customer.phone_number || '-' }}</td>
                                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap text-sm text-brand-text">{{ customer.shop?.name || '-' }}</td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- ページネーション -->
                                <div v-if="customers.links && customers.links.length > 3" class="mt-4">
                                    <div class="flex flex-wrap justify-center gap-1">
                                        <template v-for="link in customers.links" :key="link.label">
                                            <Link
                                                v-if="link.url"
                                                :href="link.url"
                                                :class="[
                                                    'px-3 py-2 sm:px-4 rounded-md text-sm',
                                                    link.active ? 'bg-brand-primary text-white' : 'bg-brand-surface text-brand-text hover:bg-brand-surface-2 border border-brand-border',
                                                ]"
                                            >
                                                <span v-html="link.label"></span>
                                            </Link>
                                            <span
                                                v-else
                                                :class="[
                                                    'px-3 py-2 sm:px-4 rounded-md text-sm opacity-50 cursor-not-allowed',
                                                    link.active ? 'bg-brand-primary text-white' : 'bg-brand-surface text-brand-text border border-brand-border',
                                                ]"
                                                v-html="link.label"
                                            ></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 顧客追加モーダル -->
        <transition name="modal">
            <div
                v-if="showAddCustomerModal"
                class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto z-50 flex items-center justify-center p-4"
                @click.self="closeAddCustomerModal"
            >
                <div
                    class="relative bg-brand-surface rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
                >
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-brand-border bg-brand-surface-2">
                        <h3 class="text-xl font-bold text-brand-text flex items-center gap-2">
                            <svg class="w-6 h-6 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            顧客情報追加
                        </h3>
                        <button
                            @click="closeAddCustomerModal"
                            type="button"
                            class="text-brand-text-subtle hover:text-brand-text-muted hover:bg-brand-surface-2 rounded-full p-1 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- コンテンツ（スクロール可能） -->
                    <form @submit.prevent="storeCustomer" class="overflow-y-auto flex-1 px-6 py-5">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        顧客名 <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="customerForm.name"
                                        type="text"
                                        required
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    />
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        ふりがな
                                    </label>
                                    <input
                                        v-model="customerForm.kana"
                                        type="text"
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    />
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        保護者名
                                    </label>
                                    <input
                                        v-model="customerForm.guardian_name"
                                        type="text"
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    />
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        生年月日
                                    </label>
                                    <input
                                        v-model="customerForm.birth_date"
                                        type="date"
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    />
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        成人年度
                                    </label>
                                    <input
                                        v-model.number="customerForm.coming_of_age_year"
                                        type="number"
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    />
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        成人式エリア
                                    </label>
                                    <div class="flex gap-2 items-end">
                                        <div class="flex-1 min-w-0">
                                            <select
                                                v-model="customerFormCeremonyPrefecture"
                                                class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                                @change="customerForm.ceremony_area_id = null"
                                            >
                                                <option :value="null">選択してください</option>
                                                <option v-for="pref in ceremonyPrefectures" :key="pref" :value="pref">
                                                    {{ pref }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <select
                                                v-model="customerForm.ceremony_area_id"
                                                class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                                :disabled="!customerFormCeremonyPrefecture"
                                            >
                                                <option :value="null">選択してください</option>
                                                <option v-for="area in customerFormCeremonyAreasFiltered" :key="area.id" :value="area.id">
                                                    {{ area.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="md:col-span-2 border-t border-brand-border pt-4 mt-2">
                                    <p class="text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-3">成人式情報</p>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                            <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                                仕度会場（推奨）
                                            </label>
                                            <input
                                                v-model="customerForm.seijin_preparation_venue"
                                                type="text"
                                                list="seijin-preparation-venue-datalist-customer-index"
                                                autocomplete="off"
                                                class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            />
                                            <datalist id="seijin-preparation-venue-datalist-customer-index">
                                                <option
                                                    v-for="v in seijinVenueDatalistOptions"
                                                    :key="'av-' + v"
                                                    :value="v"
                                                />
                                            </datalist>
                                        </div>
                                        <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                            <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                                時間（推奨）
                                            </label>
                                            <input
                                                v-model="customerForm.seijin_preparation_time"
                                                type="text"
                                                placeholder="例) 10:00"
                                                class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                            />
                                        </div>
                                        <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border md:col-span-2">
                                            <label class="flex items-center gap-2 text-sm font-medium text-brand-text cursor-pointer">
                                                <input
                                                    v-model="customerForm.other_store_preparation"
                                                    type="checkbox"
                                                    class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                                                />
                                                他店お支度
                                            </label>
                                        </div>
                                        <div
                                            class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 transition-opacity"
                                            :class="{ 'opacity-50': !customerForm.other_store_preparation }"
                                        >
                                            <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                                <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                                    美容室名（推奨）
                                                </label>
                                                <input
                                                    v-model="customerForm.other_store_salon_name"
                                                    type="text"
                                                    list="other-store-salon-name-datalist-customer-index"
                                                    autocomplete="off"
                                                    :disabled="!customerForm.other_store_preparation"
                                                    class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm disabled:bg-brand-surface-2 disabled:cursor-not-allowed"
                                                />
                                                <datalist id="other-store-salon-name-datalist-customer-index">
                                                    <option
                                                        v-for="n in seijinFilterSalonNames"
                                                        :key="'as-' + n"
                                                        :value="n"
                                                    />
                                                </datalist>
                                            </div>
                                            <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                                <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                                    着物発送日
                                                </label>
                                                <input
                                                    v-model="customerForm.kimono_ship_date"
                                                    type="date"
                                                    :disabled="!customerForm.other_store_preparation"
                                                    class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm disabled:bg-brand-surface-2 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        電話番号
                                    </label>
                                    <input
                                        v-model="customerForm.phone_number"
                                        type="tel"
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    />
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        メールアドレス
                                    </label>
                                    <input
                                        v-model="customerForm.email"
                                        type="email"
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    />
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        郵便番号
                                    </label>
                                    <input
                                        v-model="customerForm.postal_code"
                                        type="text"
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    />
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border md:col-span-2">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        住所
                                    </label>
                                    <input
                                        v-model="customerForm.address"
                                        type="text"
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    />
                                </div>
                                <!-- イベント予約由来の項目 -->
                                <div
                                    v-if="prefillFromReservation"
                                    class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border md:col-span-2"
                                >
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        担当店舗（LINE 等）
                                    </label>
                                    <select
                                        v-model="customerForm.shop_id"
                                        class="w-full max-w-md rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    >
                                        <option :value="null">選択してください</option>
                                        <option
                                            v-for="shop in reservationAddShopSelectOptions"
                                            :key="shop.id"
                                            :value="shop.id"
                                        >
                                            {{ shop.name }}
                                        </option>
                                    </select>
                                    <p class="mt-1 text-xs text-brand-text-muted">
                                        イベントの開催店舗から選べます。未設定のイベントは全店舗から選択できます。
                                    </p>
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        紹介者名
                                    </label>
                                    <input
                                        v-model="customerForm.referred_by_name"
                                        type="text"
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    />
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        学校名
                                    </label>
                                    <input
                                        v-model="customerForm.school_name"
                                        type="text"
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    />
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        担当者名
                                    </label>
                                    <input
                                        v-model="customerForm.staff_name"
                                        type="text"
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    />
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        来店動機
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        <label v-for="opt in visitReasonOptions" :key="opt.value" class="flex items-center gap-1 text-sm">
                                            <input
                                                v-model="customerForm.visit_reasons"
                                                type="checkbox"
                                                :value="opt.value"
                                                class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                                            />
                                            <span>{{ opt.label }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        検討中プラン
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        <label v-for="plan in availablePlans" :key="plan" class="flex items-center gap-1 text-sm">
                                            <input
                                                v-model="customerForm.considering_plans"
                                                type="checkbox"
                                                :value="plan"
                                                class="rounded border-brand-border text-brand-primary focus:ring-brand-primary"
                                            />
                                            <span>{{ plan }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="bg-brand-surface-2 rounded-lg p-4 border border-brand-border md:col-span-2">
                                    <label class="block text-xs font-semibold text-brand-text-muted uppercase tracking-wide mb-2">
                                        備考
                                    </label>
                                    <textarea
                                        v-model="customerForm.remarks"
                                        rows="4"
                                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- フッター -->
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-brand-border">
                            <button
                                type="button"
                                @click="closeAddCustomerModal"
                                class="px-4 py-2 border border-brand-border rounded-md text-sm font-medium text-brand-text hover:bg-brand-surface-2"
                            >
                                キャンセル
                            </button>
                            <button
                                type="submit"
                                :disabled="customerForm.processing"
                                class="px-4 py-2 bg-brand-primary text-white rounded-md text-sm font-medium hover:bg-brand-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="customerForm.processing">追加中...</span>
                                <span v-else>追加</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UiPageHeader, UiButton } from '@/Components/UI';
import { Plus, Search, Filter, RotateCcw, Eye } from 'lucide-vue-next';
import ActionButton from '@/Components/ActionButton.vue';
import { SEIJIN_PREPARATION_VENUE_OPTIONS } from '@/constants/seijinPreparationVenues.js';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    customers: Object,
    ceremonyAreas: Array,
    shops: Array,
    plans: Array,
    users: Array,
    constraintTemplates: {
        type: Array,
        default: () => [],
    },
    filters: Object,
    prefillFromReservation: Object,
});

// モーダル表示フラグ
const showAddCustomerModal = ref(false);

// 検索条件タブ（左パネル）
const filterTabs = [
    { key: 'basic', label: '基本情報' },
    { key: 'seijin', label: '成人式情報' },
    { key: 'contract', label: '成約情報' },
    { key: 'constraint', label: '制約情報' },
    { key: 'photo', label: '前撮り情報' },
];
const activeFilterTab = ref('basic');
const seijinVenueOptionsFromDb = ref([]);
const seijinFilterSalonNames = ref([]);
const seijinFilterOptionsLoaded = ref(false);
const seijinFilterOptionsLoading = ref(false);

const seijinVenueDatalistOptions = computed(() => {
    const preset = [...SEIJIN_PREPARATION_VENUE_OPTIONS];
    const fromDb = seijinVenueOptionsFromDb.value.filter(
        (v) => v && typeof v === 'string' && !preset.includes(v),
    );
    fromDb.sort((a, b) => a.localeCompare(b, 'ja'));
    return [...preset, ...fromDb];
});

const loadSeijinFilterOptionsIfNeeded = async () => {
    if (seijinFilterOptionsLoaded.value || seijinFilterOptionsLoading.value) {
        return;
    }
    seijinFilterOptionsLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.customers.filters.seijin-options'));
        seijinVenueOptionsFromDb.value = Array.isArray(data.seijin_preparation_venues)
            ? data.seijin_preparation_venues
            : [];
        seijinFilterSalonNames.value = Array.isArray(data.other_store_salon_names)
            ? data.other_store_salon_names
            : [];
        seijinFilterOptionsLoaded.value = true;
    } catch (e) {
        console.error('成人式情報の候補取得に失敗しました', e);
    } finally {
        seijinFilterOptionsLoading.value = false;
    }
};

const setActiveFilterTab = (key) => {
    activeFilterTab.value = key;
    if (key === 'seijin') {
        loadSeijinFilterOptionsIfNeeded();
    }
};

// 成人式エリア：県の一覧（重複なし・ソート）
const ceremonyPrefectures = computed(() => {
    const areas = props.ceremonyAreas || [];
    const prefs = [...new Set(areas.map((a) => a.prefecture).filter(Boolean))];
    return prefs.sort((a, b) => (a || '').localeCompare(b || ''));
});
// 検索用：選択された県に属する市のみ
const searchCeremonyAreasFiltered = computed(() => {
    const pref = searchForm.ceremony_prefecture;
    if (!pref) return [];
    return (props.ceremonyAreas || []).filter((a) => a.prefecture === pref);
});
// 顧客追加フォーム用：選択された県に属する市のみ
const customerFormCeremonyPrefecture = ref(null);
const customerFormCeremonyAreasFiltered = computed(() => {
    const pref = customerFormCeremonyPrefecture.value;
    if (!pref) return [];
    return (props.ceremonyAreas || []).filter((a) => a.prefecture === pref);
});
// 顧客追加モーダルを開いたときに県を同期（既に ceremony_area_id がある場合）
watch(showAddCustomerModal, (visible) => {
    if (visible) {
        loadSeijinFilterOptionsIfNeeded();
        const id = customerForm.ceremony_area_id;
        const areas = props.ceremonyAreas || [];
        customerFormCeremonyPrefecture.value = id ? (areas.find((a) => a.id == id)?.prefecture ?? null) : null;
    }
});

// 検索フォーム
const searchForm = reactive({
    name: props.filters?.name || '',
    kana: props.filters?.kana || '',
    ceremony_prefecture: (() => {
        const v = props.filters?.ceremony_area_id;
        const id = Array.isArray(v) ? v[0] : v;
        if (id == null || id === '') return null;
        const areas = props.ceremonyAreas || [];
        const area = areas.find((a) => a.id == id);
        return area?.prefecture ?? null;
    })(),
    ceremony_area_id: (() => {
        const v = props.filters?.ceremony_area_id;
        if (v == null || v === '') return [];
        return (Array.isArray(v) ? v : [v]).map(Number);
    })(),
    phone_number: props.filters?.phone_number || '',
    customer_shop_id: (() => {
        const v = props.filters?.customer_shop_id;
        if (v === undefined || v === null || v === '' || v === 'all') return ['all'];
        if (Array.isArray(v)) {
            if (v.length === 0 || v.includes('all')) return ['all'];
            return v.map(Number);
        }
        return [Number(v)];
    })(),
    seijin_preparation_venue: props.filters?.seijin_preparation_venue || '',
    seijin_preparation_time: props.filters?.seijin_preparation_time || '',
    other_store_preparation: (() => {
        const v = props.filters?.other_store_preparation;
        if (v === undefined || v === null || v === '') return null;
        if (v === true || v === 'true' || v === 1 || v === '1') return true;
        if (v === false || v === 'false' || v === 0 || v === '0') return false;
        return null;
    })(),
    other_store_salon_name: props.filters?.other_store_salon_name || '',
    kimono_ship_date: props.filters?.kimono_ship_date || '',
    created_at_from: props.filters?.created_at_from || '',
    created_at_to: props.filters?.created_at_to || '',
    contract_date_from: props.filters?.contract_date_from || '',
    contract_date_to: props.filters?.contract_date_to || '',
    shop_id: props.filters?.shop_id || null,
    plan_id: props.filters?.plan_id || null,
    kimono_type: props.filters?.kimono_type || null,
    contract_status: props.filters?.contract_status || null,
    photo_slot_shop_id: props.filters?.photo_slot_shop_id || null,
    warranty_flag: props.filters?.warranty_flag !== undefined ? props.filters.warranty_flag : null,
    user_id: props.filters?.user_id || null,
    preparation_venue: props.filters?.preparation_venue || '',
    preparation_date: props.filters?.preparation_date || '',
    photo_slot_details_undecided: (() => {
        const v = props.filters?.photo_slot_details_undecided;
        if (v === undefined || v === null || v === '') return null;
        if (v === true || v === 'true' || v === 1 || v === '1') return true;
        if (v === false || v === 'false' || v === 0 || v === '0') return false;
        return null;
    })(),
    constraint_presence: props.filters?.constraint_presence || null,
    constraint_template_id: props.filters?.constraint_template_id != null && props.filters.constraint_template_id !== ''
        ? Number(props.filters.constraint_template_id)
        : null,
    constraint_signed_at_from: props.filters?.constraint_signed_at_from || '',
    constraint_signed_at_to: props.filters?.constraint_signed_at_to || '',
    constraint_explainer_user_id: props.filters?.constraint_explainer_user_id != null && props.filters.constraint_explainer_user_id !== ''
        ? Number(props.filters.constraint_explainer_user_id)
        : null,
});

// 担当店舗フィルタ（複数選択）。['all'] = 全店舗、[id,...] = 選択店舗のみ
const isAllShopsSelected = computed(() => {
    const v = searchForm.customer_shop_id;
    return !Array.isArray(v) || v.length === 0 || v.includes('all');
});
const isShopSelected = (id) =>
    Array.isArray(searchForm.customer_shop_id) && searchForm.customer_shop_id.includes(id);
const selectAllShops = () => {
    searchForm.customer_shop_id = ['all'];
};
const toggleShopFilter = (id) => {
    let list = Array.isArray(searchForm.customer_shop_id)
        ? searchForm.customer_shop_id.filter((v) => v !== 'all')
        : [];
    if (list.includes(id)) {
        list = list.filter((v) => v !== id);
    } else {
        list.push(id);
    }
    searchForm.customer_shop_id = list.length ? list : ['all'];
};

// 成人式エリア 市町村フィルタ（複数選択・選択された県の市町村のみ対象）
const isCeremonyAreaSelected = (id) =>
    Array.isArray(searchForm.ceremony_area_id) && searchForm.ceremony_area_id.includes(id);
const toggleCeremonyArea = (id) => {
    let list = Array.isArray(searchForm.ceremony_area_id) ? [...searchForm.ceremony_area_id] : [];
    if (list.includes(id)) {
        list = list.filter((v) => v !== id);
    } else {
        list.push(id);
    }
    searchForm.ceremony_area_id = list;
};
const selectAllCeremonyAreas = () => {
    searchForm.ceremony_area_id = searchCeremonyAreasFiltered.value.map((a) => a.id);
};
const clearCeremonyAreas = () => {
    searchForm.ceremony_area_id = [];
};

// 適用中の絞り込み条件（実際に結果へ反映されている props.filters ベース）をチップ表示用に整形
const filterChips = computed(() => {
    const f = props.filters || {};
    const chips = [];
    const findName = (list, id, field = 'name') =>
        (list || []).find((x) => x.id == id)?.[field] ?? `#${id}`;
    const boolLabel = (v) => {
        if (v === true || v === 'true' || v === 1 || v === '1') return 'あり';
        if (v === false || v === 'false' || v === 0 || v === '0') return 'なし';
        return null;
    };
    const text = (key, label) => {
        const v = f[key];
        if (v !== null && v !== undefined && v !== '') chips.push({ key, label, value: String(v) });
    };
    const bool = (key, label) => {
        const v = f[key];
        if (v === null || v === undefined || v === '') return;
        const b = boolLabel(v);
        if (b) chips.push({ key, label, value: b });
    };
    const idChip = (key, label, list, field = 'name') => {
        const v = f[key];
        if (v !== null && v !== undefined && v !== '') chips.push({ key, label, value: findName(list, v, field) });
    };
    const toArray = (v) =>
        Array.isArray(v) ? v : (v !== null && v !== undefined && v !== '' ? [v] : []);

    // 基本情報
    text('name', '顧客名');
    text('kana', 'ふりがな');
    text('phone_number', '電話番号');
    const shopIds = toArray(f.customer_shop_id).filter((x) => x !== 'all');
    if (shopIds.length) {
        chips.push({ key: 'customer_shop_id', label: '担当店舗', value: shopIds.map((id) => findName(props.shops, id)).join('、') });
    }
    const areaIds = toArray(f.ceremony_area_id);
    if (areaIds.length) {
        chips.push({ key: 'ceremony_area_id', label: '成人式エリア', value: areaIds.map((id) => findName(props.ceremonyAreas, id)).join('、') });
    }
    text('created_at_from', '登録日(開始)');
    text('created_at_to', '登録日(終了)');

    // 成人式情報
    text('seijin_preparation_venue', '仕度会場');
    text('seijin_preparation_time', '時間');
    bool('other_store_preparation', '他店お支度');
    text('other_store_salon_name', '美容室名');
    text('kimono_ship_date', '着物発送日');

    // 成約情報
    text('contract_status', '成約ステータス');
    text('contract_date_from', '成約日(開始)');
    text('contract_date_to', '成約日(終了)');
    idChip('shop_id', '成約店舗', props.shops);
    idChip('plan_id', 'プラン', props.plans);
    text('kimono_type', '着物種別');
    bool('warranty_flag', '安心保証');
    idChip('user_id', '担当スタッフ', props.users);
    text('preparation_venue', 'お仕度会場');
    text('preparation_date', 'お仕度日程');

    // 制約情報
    text('constraint_presence', '制約');
    idChip('constraint_template_id', '制約テンプレート', props.constraintTemplates);
    text('constraint_signed_at_from', '署名日(開始)');
    text('constraint_signed_at_to', '署名日(終了)');
    idChip('constraint_explainer_user_id', '説明担当', props.users);

    // 前撮り情報
    idChip('photo_slot_shop_id', '前撮り担当店舗', props.shops);
    bool('photo_slot_details_undecided', '前撮り詳細未決定');

    return chips;
});

// 来店動機をフォーム用に正規化（SNS広告・WEB広告はSNS・WEB広告に統合）
const normalizeVisitReasonsForForm = (reasons) => {
    if (!Array.isArray(reasons)) return [];
    const result = [];
    let hasSnsWeb = false;
    for (const r of reasons) {
        if (!r || typeof r !== 'string') continue;
        if (r.startsWith('その他(')) result.push(r);
        else if (r === 'SNS広告(Instaなど)' || r === 'WEB広告' || r === 'SNS・WEB広告') {
            if (!hasSnsWeb) { result.push('SNS・WEB広告'); hasSnsWeb = true; }
        } else {
            result.push(r);
        }
    }
    return result;
};

// 来店動機・検討中プランの選択肢（予約フォームと共通）
const visitReasonOptions = [
    { value: '紹介', label: '紹介' },
    { value: 'DM・カタログ', label: 'DM・カタログ' },
    { value: 'SNS・WEB広告', label: 'SNS・WEB広告' },
    { value: 'その他', label: 'その他(テキスト入力)' },
];

/** 予約からの顧客追加時：開催店舗があればその一覧、なければ全店舗 */
const reservationAddShopSelectOptions = computed(() => {
    const p = props.prefillFromReservation;
    if (!p) {
        return [];
    }
    const eventShops = p.event_shops;
    if (Array.isArray(eventShops) && eventShops.length > 0) {
        return eventShops;
    }
    return props.shops || [];
});

const availablePlans = [
    '振袖レンタルプラン',
    '振袖購入プラン',
    'ママ振りフォトプラン',
    'フォトレンタルプラン',
];

// 顧客追加フォーム
const customerForm = useForm({
    name: '',
    kana: '',
    guardian_name: '',
    birth_date: '',
    coming_of_age_year: null,
    ceremony_area_id: null,
    seijin_preparation_venue: '',
    seijin_preparation_time: '',
    other_store_preparation: false,
    other_store_salon_name: '',
    kimono_ship_date: '',
    phone_number: '',
    postal_code: '',
    address: '',
    remarks: '',
    email: '',
    referred_by_name: '',
    school_name: '',
    staff_name: '',
    visit_reasons: [],
    considering_plans: [],
    event_reservation_id: null,
    shop_id: null,
});

watch(
    () => customerForm.other_store_preparation,
    (on) => {
        if (!on) {
            customerForm.other_store_salon_name = '';
            customerForm.kimono_ship_date = '';
        }
    },
);

// 検索実行
const searchCustomers = () => {
    // 空の値を除外してクエリパラメータを送信
    const params = {};
    Object.keys(searchForm).forEach(key => {
        const value = searchForm[key];
        if (value !== null && value !== '' && value !== undefined) {
            params[key] = value;
        }
    });
    router.get(route('admin.customers.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

// 検索リセット
const resetSearch = () => {
    searchForm.name = '';
    searchForm.kana = '';
    searchForm.ceremony_prefecture = null;
    searchForm.ceremony_area_id = [];
    searchForm.phone_number = '';
    searchForm.seijin_preparation_venue = '';
    searchForm.seijin_preparation_time = '';
    searchForm.other_store_preparation = null;
    searchForm.other_store_salon_name = '';
    searchForm.kimono_ship_date = '';
    searchForm.created_at_from = '';
    searchForm.created_at_to = '';
    searchForm.contract_date_from = '';
    searchForm.contract_date_to = '';
    searchForm.shop_id = null;
    searchForm.plan_id = null;
    searchForm.kimono_type = null;
    searchForm.contract_status = null;
    searchForm.photo_slot_shop_id = null;
    searchForm.warranty_flag = null;
    searchForm.user_id = null;
    searchForm.preparation_venue = '';
    searchForm.preparation_date = '';
    searchForm.photo_slot_details_undecided = null;
    searchForm.constraint_presence = null;
    searchForm.constraint_template_id = null;
    searchForm.constraint_signed_at_from = '';
    searchForm.constraint_signed_at_to = '';
    searchForm.constraint_explainer_user_id = null;
    router.get(route('admin.customers.index'), {}, {
        preserveState: false,
        preserveScroll: false,
    });
};

// 予約からプリフィルをフォームに反映
const applyPrefillFromReservation = () => {
    if (!props.prefillFromReservation) return;
    const p = props.prefillFromReservation;
    customerForm.name = p.name ?? '';
    customerForm.kana = p.kana ?? '';
    customerForm.phone_number = p.phone_number ?? '';
    customerForm.postal_code = p.postal_code ?? '';
    customerForm.address = p.address ?? '';
    customerForm.birth_date = p.birth_date ?? '';
    customerForm.coming_of_age_year = p.coming_of_age_year ?? null;
    customerForm.remarks = p.remarks ?? '';
    customerForm.event_reservation_id = p.id ?? null;
    customerForm.email = p.email ?? '';
    customerForm.referred_by_name = p.referred_by_name ?? '';
    customerForm.school_name = p.school_name ?? '';
    customerForm.staff_name = p.staff_name ?? '';
    customerForm.visit_reasons = normalizeVisitReasonsForForm(p.visit_reasons ?? []);
    customerForm.considering_plans = Array.isArray(p.considering_plans) ? [...p.considering_plans] : [];
    const defaultShop =
        p.default_shop_id != null && p.default_shop_id !== ''
            ? Number(p.default_shop_id)
            : null;
    const firstEventShopId =
        Array.isArray(p.event_shops) && p.event_shops.length > 0 ? Number(p.event_shops[0].id) : null;
    customerForm.shop_id = defaultShop ?? firstEventShopId ?? null;
};

// モーダルを閉じてフォームをリセット
const closeAddCustomerModal = () => {
    showAddCustomerModal.value = false;
    customerForm.reset();
};

// 予約詳細から遷移した場合はモーダルを開きプリフィルを反映
onMounted(() => {
    if (props.prefillFromReservation) {
        applyPrefillFromReservation();
        showAddCustomerModal.value = true;
    }
});

// 顧客情報追加
const storeCustomer = () => {
    customerForm.post(route('admin.customers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddCustomerModal.value = false;
            customerForm.reset();
        },
    });
};

// 全身写真を取得（photo_type_idが1の写真）
const getFullBodyPhoto = (customer) => {
    if (!customer.photos || customer.photos.length === 0) {
        return null;
    }
    // photo_type_idが1の写真を取得
    return customer.photos.find(photo => photo.photo_type_id === 1) || null;
};

// 写真URLを取得（バックエンドで付与されたurlがあればそれを使用、なければローカルstorageパス）
const getPhotoUrl = (photo) => {
    if (photo?.url) return photo.url;
    return photo?.file_path ? `/storage/${photo.file_path}` : '';
};
</script>

<style scoped>
.modal-enter-active, .modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from, .modal-leave-to {
    opacity: 0;
}
</style>

