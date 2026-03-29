<template>
    <Head title="顧客一覧" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客一覧</h2>
                <ActionButton variant="create" label="顧客追加" @click="showAddCustomerModal = true" />
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col lg:flex-row gap-6 lg:items-start">
                    <!-- 左：検索条件 -->
                    <aside
                        class="w-full lg:w-[22rem] shrink-0 lg:sticky lg:top-4 lg:max-h-[calc(100vh-5rem)] lg:overflow-y-auto"
                    >
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                                <h3 class="text-base font-semibold text-gray-900">検索条件</h3>
                                <p class="text-xs text-gray-500 mt-0.5 leading-snug">
                                    各ブロックを開いて絞り込み、検索を実行してください
                                </p>
                            </div>
                            <form @submit.prevent="searchCustomers" class="p-3 space-y-2">
                                <!-- 基本情報 -->
                                <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">
                                    <button
                                        type="button"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2.5 text-left text-sm font-semibold text-gray-800 hover:bg-gray-50 transition-colors"
                                        @click="toggleFilterAccordion('basic')"
                                    >
                                        <span>基本情報</span>
                                        <svg
                                            class="w-4 h-4 text-gray-500 shrink-0 transition-transform duration-200"
                                            :class="{ 'rotate-180': filterAccordion.basic }"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div
                                        v-show="filterAccordion.basic"
                                        class="px-3 pb-3 pt-0 space-y-3 border-t border-gray-100 bg-slate-50/50"
                                    >
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">顧客名</label>
                                            <input
                                                v-model="searchForm.name"
                                                type="text"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                placeholder="顧客名で検索"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">ふりがな</label>
                                            <input
                                                v-model="searchForm.kana"
                                                type="text"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                placeholder="ふりがなで検索"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">成人式エリア</label>
                                            <div class="flex gap-2 items-end">
                                                <div class="flex-1 min-w-0">
                                                    <select
                                                        v-model="searchForm.ceremony_prefecture"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                        @change="searchForm.ceremony_area_id = null"
                                                    >
                                                        <option :value="null">全て</option>
                                                        <option v-for="pref in ceremonyPrefectures" :key="pref" :value="pref">
                                                            {{ pref }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <select
                                                        v-model="searchForm.ceremony_area_id"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                        :disabled="!searchForm.ceremony_prefecture"
                                                    >
                                                        <option :value="null">全て</option>
                                                        <option v-for="area in searchCeremonyAreasFiltered" :key="area.id" :value="area.id">
                                                            {{ area.name }}
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">電話番号</label>
                                            <input
                                                v-model="searchForm.phone_number"
                                                type="text"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                placeholder="電話番号で検索"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">登録日（開始）</label>
                                            <input
                                                v-model="searchForm.created_at_from"
                                                type="date"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">登録日（終了）</label>
                                            <input
                                                v-model="searchForm.created_at_to"
                                                type="date"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- 成人式情報 -->
                                <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">
                                    <button
                                        type="button"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2.5 text-left text-sm font-semibold text-gray-800 hover:bg-gray-50 transition-colors"
                                        @click="toggleFilterAccordion('seijin')"
                                    >
                                        <span>成人式情報</span>
                                        <svg
                                            class="w-4 h-4 text-gray-500 shrink-0 transition-transform duration-200"
                                            :class="{ 'rotate-180': filterAccordion.seijin }"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div
                                        v-show="filterAccordion.seijin"
                                        class="px-3 pb-3 pt-0 space-y-3 border-t border-gray-100 bg-slate-50/50"
                                    >
                                        <p v-if="seijinFilterOptionsLoading" class="text-xs text-gray-500">候補を読み込み中…</p>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">仕度会場</label>
                                            <input
                                                v-model="searchForm.seijin_preparation_venue"
                                                type="text"
                                                list="index-search-seijin-preparation-venue-datalist"
                                                autocomplete="off"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
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
                                            <label class="block text-xs font-medium text-gray-700 mb-1">時間</label>
                                            <input
                                                v-model="searchForm.seijin_preparation_time"
                                                type="text"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                placeholder="部分一致"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">他店お支度</label>
                                            <select
                                                v-model="searchForm.other_store_preparation"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option :value="true">あり</option>
                                                <option :value="false">なし</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">美容室名</label>
                                            <input
                                                v-model="searchForm.other_store_salon_name"
                                                type="text"
                                                list="index-search-other-store-salon-name-datalist"
                                                autocomplete="off"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                placeholder="部分一致"
                                            />
                                            <p class="mt-1 text-[11px] text-gray-500 leading-snug">
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
                                            <label class="block text-xs font-medium text-gray-700 mb-1">着物発送日</label>
                                            <input
                                                v-model="searchForm.kimono_ship_date"
                                                type="date"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- 成約情報 -->
                                <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">
                                    <button
                                        type="button"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2.5 text-left text-sm font-semibold text-gray-800 hover:bg-gray-50 transition-colors"
                                        @click="toggleFilterAccordion('contract')"
                                    >
                                        <span>成約情報</span>
                                        <svg
                                            class="w-4 h-4 text-gray-500 shrink-0 transition-transform duration-200"
                                            :class="{ 'rotate-180': filterAccordion.contract }"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div
                                        v-show="filterAccordion.contract"
                                        class="px-3 pb-3 pt-0 space-y-3 border-t border-gray-100 bg-slate-50/50"
                                    >
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">成約ステータス</label>
                                            <select
                                                v-model="searchForm.contract_status"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option value="成約なし">成約なし</option>
                                                <option value="確定">確定</option>
                                                <option value="保留">保留</option>
                                                <option value="キャンセル">キャンセル</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">成約日（開始）</label>
                                            <input
                                                v-model="searchForm.contract_date_from"
                                                type="date"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">成約日（終了）</label>
                                            <input
                                                v-model="searchForm.contract_date_to"
                                                type="date"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">店舗</label>
                                            <select
                                                v-model="searchForm.shop_id"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                                                    {{ shop.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">プラン</label>
                                            <select
                                                v-model="searchForm.plan_id"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                                    {{ plan.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">着物種別</label>
                                            <select
                                                v-model="searchForm.kimono_type"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option value="振袖">振袖</option>
                                                <option value="袴">袴</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">安心保証</label>
                                            <select
                                                v-model="searchForm.warranty_flag"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option :value="true">あり</option>
                                                <option :value="false">なし</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">担当スタッフ</label>
                                            <select
                                                v-model="searchForm.user_id"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option v-for="user in users" :key="user.id" :value="user.id">
                                                    {{ user.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">お仕度会場</label>
                                            <input
                                                v-model="searchForm.preparation_venue"
                                                type="text"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                placeholder="お仕度会場で検索"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">お仕度日程</label>
                                            <input
                                                v-model="searchForm.preparation_date"
                                                type="date"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- 制約情報 -->
                                <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">
                                    <button
                                        type="button"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2.5 text-left text-sm font-semibold text-gray-800 hover:bg-gray-50 transition-colors"
                                        @click="toggleFilterAccordion('constraint')"
                                    >
                                        <span>制約情報</span>
                                        <svg
                                            class="w-4 h-4 text-gray-500 shrink-0 transition-transform duration-200"
                                            :class="{ 'rotate-180': filterAccordion.constraint }"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div
                                        v-show="filterAccordion.constraint"
                                        class="px-3 pb-3 pt-0 space-y-3 border-t border-gray-100 bg-slate-50/50"
                                    >
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">制約の有無</label>
                                            <select
                                                v-model="searchForm.constraint_presence"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option value="制約なし">制約なし</option>
                                                <option value="制約あり">制約あり</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">制約テンプレート</label>
                                            <select
                                                v-model="searchForm.constraint_template_id"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
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
                                            <label class="block text-xs font-medium text-gray-700 mb-1">署名日（開始）</label>
                                            <input
                                                v-model="searchForm.constraint_signed_at_from"
                                                type="date"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">署名日（終了）</label>
                                            <input
                                                v-model="searchForm.constraint_signed_at_to"
                                                type="date"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">説明担当スタッフ</label>
                                            <select
                                                v-model="searchForm.constraint_explainer_user_id"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
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
                                <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">
                                    <button
                                        type="button"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2.5 text-left text-sm font-semibold text-gray-800 hover:bg-gray-50 transition-colors"
                                        @click="toggleFilterAccordion('photo')"
                                    >
                                        <span>前撮り情報</span>
                                        <svg
                                            class="w-4 h-4 text-gray-500 shrink-0 transition-transform duration-200"
                                            :class="{ 'rotate-180': filterAccordion.photo }"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div
                                        v-show="filterAccordion.photo"
                                        class="px-3 pb-3 pt-0 space-y-3 border-t border-gray-100 bg-slate-50/50"
                                    >
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">担当店舗</label>
                                            <select
                                                v-model="searchForm.photo_slot_shop_id"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option v-for="shop in shops" :key="shop.id" :value="shop.id">
                                                    {{ shop.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">前撮り詳細未決定</label>
                                            <select
                                                v-model="searchForm.photo_slot_details_undecided"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            >
                                                <option :value="null">全て</option>
                                                <option :value="true">あり（詳細未決定あり）</option>
                                                <option :value="false">なし（詳細未決定なし）</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                                    <button
                                        type="button"
                                        class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500"
                                        @click="resetSearch"
                                    >
                                        リセット
                                    </button>
                                    <button
                                        type="submit"
                                        class="px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500"
                                    >
                                        検索
                                    </button>
                                </div>
                            </form>
                        </div>
                    </aside>

                    <!-- 右：検索結果 -->
                    <div class="flex-1 min-w-0 w-full">
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden min-h-[min(320px,50vh)]">
                            <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-baseline justify-between gap-2 bg-gray-50/60">
                                <h3 class="text-base font-semibold text-gray-900">検索結果</h3>
                                <p class="text-sm text-gray-500">
                                    全
                                    <span class="font-medium text-gray-800 tabular-nums">{{ customers.total ?? 0 }}</span>
                                    件
                                </p>
                            </div>
                            <div class="p-4 sm:p-5">
                                <div class="overflow-x-auto rounded-lg border border-gray-100">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">画像</th>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">タグ</th>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">顧客名</th>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ふりがな</th>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">成人式エリア</th>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">電話番号</th>
                                                <th class="px-4 py-2.5 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <template v-if="!customers.data || customers.data.length === 0">
                                                <tr>
                                                    <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500">
                                                        該当する顧客がありません
                                                    </td>
                                                </tr>
                                            </template>
                                            <template v-else>
                                                <tr v-for="customer in customers.data" :key="customer.id">
                                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap">
                                            <div class="w-16 h-16 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center">
                                                <img
                                                    v-if="getFullBodyPhoto(customer)"
                                                    :src="getPhotoUrl(getFullBodyPhoto(customer))"
                                                    :alt="customer.name"
                                                    class="w-full h-full object-cover"
                                                />
                                                <svg
                                                    v-else
                                                    class="w-8 h-8 text-gray-400"
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
                                                    <td class="px-4 py-4 sm:px-6">
                                            <div v-if="customer.tags && customer.tags.length > 0" class="flex flex-wrap gap-1">
                                                <span
                                                    v-for="tag in customer.tags"
                                                    :key="tag.id"
                                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                                    :style="{
                                                        backgroundColor: tag.color ? tag.color + '20' : '#f3f4f620',
                                                        color: tag.color || '#6b7280',
                                                        border: `1px solid ${tag.color || '#e5e7eb'}`
                                                    }"
                                                >
                                                    {{ tag.name }}
                                                </span>
                                            </div>
                                            <span v-else class="text-sm text-gray-400">-</span>
                                                    </td>
                                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap text-sm text-gray-900">{{ customer.name }}</td>
                                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap text-sm text-gray-900">{{ customer.kana || '-' }}</td>
                                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap text-sm text-gray-900">{{ customer.ceremony_area?.name || '-' }}</td>
                                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap text-sm text-gray-900">{{ customer.phone_number || '-' }}</td>
                                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap text-sm font-medium">
                                            <Link
                                                :href="route('admin.customers.show', customer.id)"
                                                class="group relative inline-flex items-center px-3 py-1.5 sm:px-4 sm:py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                            >
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                詳細
                                            </Link>
                                                    </td>
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
                                                    link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200',
                                                ]"
                                            >
                                                <span v-html="link.label"></span>
                                            </Link>
                                            <span
                                                v-else
                                                :class="[
                                                    'px-3 py-2 sm:px-4 rounded-md text-sm opacity-50 cursor-not-allowed',
                                                    link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-200',
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
                    class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
                >
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            顧客情報追加
                        </h3>
                        <button
                            @click="closeAddCustomerModal"
                            type="button"
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors"
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
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        顧客名 <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="customerForm.name"
                                        type="text"
                                        required
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        ふりがな
                                    </label>
                                    <input
                                        v-model="customerForm.kana"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        保護者名
                                    </label>
                                    <input
                                        v-model="customerForm.guardian_name"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        生年月日
                                    </label>
                                    <input
                                        v-model="customerForm.birth_date"
                                        type="date"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        成人年度
                                    </label>
                                    <input
                                        v-model.number="customerForm.coming_of_age_year"
                                        type="number"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        成人式エリア
                                    </label>
                                    <div class="flex gap-2 items-end">
                                        <div class="flex-1 min-w-0">
                                            <select
                                                v-model="customerFormCeremonyPrefecture"
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
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
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
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
                                <div class="md:col-span-2 border-t border-gray-200 pt-4 mt-2">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">成人式情報</p>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                                仕度会場（推奨）
                                            </label>
                                            <input
                                                v-model="customerForm.seijin_preparation_venue"
                                                type="text"
                                                list="seijin-preparation-venue-datalist-customer-index"
                                                autocomplete="off"
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                            <datalist id="seijin-preparation-venue-datalist-customer-index">
                                                <option
                                                    v-for="v in seijinVenueDatalistOptions"
                                                    :key="'av-' + v"
                                                    :value="v"
                                                />
                                            </datalist>
                                        </div>
                                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                                時間（推奨）
                                            </label>
                                            <input
                                                v-model="customerForm.seijin_preparation_time"
                                                type="text"
                                                placeholder="例) 10:00"
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                        </div>
                                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                                                <input
                                                    v-model="customerForm.other_store_preparation"
                                                    type="checkbox"
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                他店お支度
                                            </label>
                                        </div>
                                        <div
                                            class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 transition-opacity"
                                            :class="{ 'opacity-50': !customerForm.other_store_preparation }"
                                        >
                                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                                    美容室名（推奨）
                                                </label>
                                                <input
                                                    v-model="customerForm.other_store_salon_name"
                                                    type="text"
                                                    list="other-store-salon-name-datalist-customer-index"
                                                    autocomplete="off"
                                                    :disabled="!customerForm.other_store_preparation"
                                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                                                />
                                                <datalist id="other-store-salon-name-datalist-customer-index">
                                                    <option
                                                        v-for="n in seijinFilterSalonNames"
                                                        :key="'as-' + n"
                                                        :value="n"
                                                    />
                                                </datalist>
                                            </div>
                                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                                    着物発送日
                                                </label>
                                                <input
                                                    v-model="customerForm.kimono_ship_date"
                                                    type="date"
                                                    :disabled="!customerForm.other_store_preparation"
                                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        電話番号
                                    </label>
                                    <input
                                        v-model="customerForm.phone_number"
                                        type="tel"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        メールアドレス
                                    </label>
                                    <input
                                        v-model="customerForm.email"
                                        type="email"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        郵便番号
                                    </label>
                                    <input
                                        v-model="customerForm.postal_code"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        住所
                                    </label>
                                    <input
                                        v-model="customerForm.address"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <!-- イベント予約由来の項目 -->
                                <div
                                    v-if="prefillFromReservation"
                                    class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2"
                                >
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        担当店舗（LINE 等）
                                    </label>
                                    <select
                                        v-model="customerForm.shop_id"
                                        class="w-full max-w-md rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
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
                                    <p class="mt-1 text-xs text-gray-500">
                                        イベントの開催店舗から選べます。未設定のイベントは全店舗から選択できます。
                                    </p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        紹介者名
                                    </label>
                                    <input
                                        v-model="customerForm.referred_by_name"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        学校名
                                    </label>
                                    <input
                                        v-model="customerForm.school_name"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        担当者名
                                    </label>
                                    <input
                                        v-model="customerForm.staff_name"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        来店動機
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        <label v-for="opt in visitReasonOptions" :key="opt.value" class="flex items-center gap-1 text-sm">
                                            <input
                                                v-model="customerForm.visit_reasons"
                                                type="checkbox"
                                                :value="opt.value"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span>{{ opt.label }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        検討中プラン
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        <label v-for="plan in availablePlans" :key="plan" class="flex items-center gap-1 text-sm">
                                            <input
                                                v-model="customerForm.considering_plans"
                                                type="checkbox"
                                                :value="plan"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span>{{ plan }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        備考
                                    </label>
                                    <textarea
                                        v-model="customerForm.remarks"
                                        rows="4"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- フッター -->
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                            <button
                                type="button"
                                @click="closeAddCustomerModal"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                キャンセル
                            </button>
                            <button
                                type="submit"
                                :disabled="customerForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="customerForm.processing">追加中...</span>
                                <span v-else>追加</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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

// 検索条件アコーディオン（左パネル）
const filterAccordion = reactive({
    basic: true,
    seijin: false,
    contract: false,
    constraint: false,
    photo: false,
});
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

const toggleFilterAccordion = (key) => {
    const nextOpen = !filterAccordion[key];
    filterAccordion[key] = nextOpen;
    if (key === 'seijin' && nextOpen) {
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
        const id = props.filters?.ceremony_area_id;
        if (id == null || id === '') return null;
        const areas = props.ceremonyAreas || [];
        const area = areas.find((a) => a.id == id);
        return area?.prefecture ?? null;
    })(),
    ceremony_area_id: props.filters?.ceremony_area_id || null,
    phone_number: props.filters?.phone_number || '',
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
    searchForm.ceremony_area_id = null;
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

