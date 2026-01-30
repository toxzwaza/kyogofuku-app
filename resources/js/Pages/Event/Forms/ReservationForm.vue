<template>
    <form @submit.prevent="submit" class="space-y-6 sm:space-y-8">
        <!-- イベント情報 -->
        <div class="mb-6 sm:mb-8">
            <div class="bg-gradient-to-r from-pink-50 to-rose-50 rounded-xl sm:rounded-2xl p-4 sm:p-5 lg:p-6 mb-4 sm:mb-6 border border-pink-100">
                <h1 class="text-3xl font-bold mb-4 text-gray-800">{{ event.title }}</h1>
                <div v-if="event.description" class="text-gray-700 leading-relaxed" v-html="event.description"></div>
            </div>

            <!-- 開催会場 -->
            <div v-if="eventVenues && eventVenues.length > 0" class="mb-8">
                <h2 class="text-xl font-semibold mb-4 flex items-center text-gray-800">
                    <svg class="w-5 h-5 mr-2 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    開催会場
                </h2>
                <div class="space-y-6">
                    <div v-for="venue in eventVenues" :key="venue.id" class="bg-white border border-gray-200 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden">
                        <!-- 画像とテキストのグリッドレイアウト -->
                        <div class="md:flex">
                            <!-- テキスト情報（左側または上側） -->
                            <div class="flex-1 p-6">
                                <h3 class="font-bold text-xl text-gray-900 mb-3">{{ venue.name }}</h3>
                                
                                <div v-if="venue.description" class="text-sm text-gray-700 mb-4 leading-relaxed" v-html="venue.description"></div>
                                
                                <div class="space-y-3">
                                    <!-- 住所 -->
                                    <div v-if="venue.address" class="flex items-start space-x-3">
                                        <div class="flex-shrink-0 mt-0.5">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </div>
                                        <p class="text-sm text-gray-700 flex-1">{{ venue.address }}</p>
                                    </div>
                                    
                                    <!-- 電話番号 -->
                                    <div v-if="venue.phone" class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                        </div>
                                        <a :href="`tel:${venue.phone}`" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                            {{ venue.phone }}
                                        </a>
                                    </div>

                                    <!-- 開催日時（目立つ表示） -->
                                    <div v-if="venue.dates && venue.dates.length > 0" class="mt-4 pt-4 border-t border-rose-100">
                                        <div class="flex items-start gap-3 rounded-lg bg-rose-50/80 px-4 py-3">
                                            <div class="flex-shrink-0 mt-0.5">
                                                <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-bold text-rose-600 uppercase tracking-wider mb-2">開催日</p>
                                                <div class="space-y-2">
                                                    <div
                                                        v-for="(block, blockIdx) in formatVenueDates(venue.dates)"
                                                        :key="blockIdx"
                                                        class="flex flex-wrap items-baseline gap-x-1.5"
                                                    >
                                                        <span class="text-2xl font-bold tabular-nums text-rose-700 tracking-tight">{{ block.monthLabel }}</span>
                                                        <span class="text-rose-400 font-medium select-none text-lg">/</span>
                                                        <span class="text-base font-semibold tabular-nums text-gray-800">{{ block.dayParts.join(', ') }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 画像（右側または下側） -->
                            <div v-if="venue.image_url" class="md:w-1/2 lg:w-2/5 flex-shrink-0">
                                <img
                                    :src="venue.image_url"
                                    :alt="venue.name"
                                    class="w-full h-full object-cover"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- 会場選択 -->
        <div v-if="eventVenues && eventVenues.length > 0" class="mb-8">
            <h2 class="text-xl font-semibold mb-4 flex items-center text-gray-800">
                <svg class="w-5 h-5 mr-2 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                ご来店会場 <span class="text-red-500 ml-1">*</span>
            </h2>
            <select
                v-model="form.venue_id"
                required
                class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4 bg-white text-gray-700 font-medium"
            >
                <option value="">選択してください</option>
                <option v-for="venue in eventVenues" :key="venue.id" :value="venue.id">
                    {{ venue.name }}
                </option>
            </select>
        </div>

        <!-- 予約可能な日時 -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-4 flex items-center text-gray-800">
                <svg class="w-5 h-5 mr-2 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                予約可能な日時
            </h2>
            <div v-if="!form.venue_id" class="mb-4 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-lg">
                <p class="text-yellow-800 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    会場を選択してください。
                </p>
            </div>
            <div v-else-if="filteredTimeslots && filteredTimeslots.length === 0" class="mb-4 p-4 bg-gray-50 border-l-4 border-gray-400 rounded-lg">
                <p class="text-gray-600">選択された会場には予約可能な日時がありません。</p>
            </div>
            <div v-else-if="filteredTimeslots && filteredTimeslots.length > 0">
                <div v-if="!internalSelectedTimeslot" class="mb-4 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-lg">
                    <p class="text-yellow-800 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        予約日時を選択してください。
                    </p>
                </div>
                <div class="space-y-8">
                    <div
                        v-for="(dateGroup, date) in groupedTimeslots"
                        :key="date"
                        class="border-b border-gray-200 pb-8 last:border-b-0 last:pb-0"
                    >
                        <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ formatDate(date) }}
                        </h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                <button
                                    type="button"
                                    v-for="timeslot in dateGroup"
                                    :key="timeslot.id"
                                    @click="selectTimeslot(timeslot)"
                                    :class="[
                                        'p-4 rounded-xl border-2 transition-all duration-200 text-left transform hover:scale-105 hover:shadow-md',
                                        internalSelectedTimeslot?.id === timeslot.id
                                            ? 'border-pink-500 bg-gradient-to-br from-pink-50 to-rose-50 shadow-md ring-2 ring-pink-200'
                                            : 'border-gray-200 hover:border-pink-300 bg-white hover:bg-pink-50'
                                    ]"
                                >
                                <p class="font-bold text-base mb-1 text-gray-800">{{ formatTime(timeslot.start_at) }}</p>
                                <p class="text-xs text-gray-600 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    残り{{ getRemainingCapacity(timeslot) }}枠
                                </p>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div ref="customerInfoSection" class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-5 lg:p-6 border border-gray-200 shadow-sm space-y-4 sm:space-y-6">
            <h3 class="text-lg sm:text-xl font-semibold mb-4 sm:mb-6 text-gray-800 flex items-center border-b border-gray-200 pb-3 sm:pb-4">
                <svg class="w-6 h-6 mr-2 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                お客様情報
            </h3>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    予約日時 <span class="text-red-500 ml-1">*</span>
                </label>
                <input
                    type="text"
                    :value="internalSelectedTimeslot ? formatDateTime(internalSelectedTimeslot.start_at) : ''"
                    readonly
                    class="w-full rounded-lg border-2 border-gray-300 shadow-sm bg-gray-50 py-3 px-4 text-gray-700 font-medium"
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        お名前 <span class="text-red-500 ml-1">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4"
                        placeholder="山田 太郎"
                    />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                        フリガナ <span class="text-red-500 ml-1">*</span>
                    </label>
                    <input
                        v-model="form.furigana"
                        type="text"
                        required
                        class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4"
                        placeholder="ヤマダ タロウ"
                    />
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    メールアドレス <span class="text-red-500 ml-1">*</span>
                </label>
                <input
                    v-model="form.email"
                    type="email"
                    required
                    class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4"
                    placeholder="example@email.com"
                />
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    電話番号 <span class="text-red-500 ml-1">*</span>
                </label>
                <input
                    v-model="form.phone"
                    type="tel"
                    required
                    class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4"
                    placeholder="090-1234-5678"
                />
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">過去当店のご来店はありますか</label>
                <div class="flex space-x-6">
                    <label class="flex items-center cursor-pointer group">
                        <input
                            v-model="form.has_visited_before"
                            type="radio"
                            :value="false"
                            class="w-5 h-5 rounded-full border-2 border-gray-300 text-pink-600 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200"
                        />
                        <span class="ml-2 text-sm text-gray-700 font-medium group-hover:text-pink-600 transition-colors">なし</span>
                    </label>
                    <label class="flex items-center cursor-pointer group">
                        <input
                            v-model="form.has_visited_before"
                            type="radio"
                            :value="true"
                            class="w-5 h-5 rounded-full border-2 border-gray-300 text-pink-600 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200"
                        />
                        <span class="ml-2 text-sm text-gray-700 font-medium group-hover:text-pink-600 transition-colors">あり</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    郵便番号 <span class="text-red-500 ml-1">*</span>
                </label>
                <input
                    v-model="form.postal_code"
                    type="text"
                    required
                    placeholder="例: 700-0012"
                    @blur="searchAddress"
                    class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4"
                />
                <p class="mt-1 text-xs text-gray-500">郵便番号を入力すると住所が自動で入力されます</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    住所 <span class="text-red-500 ml-1">*</span>
                </label>
                <input
                    v-model="form.address"
                    type="text"
                    required
                    class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4"
                    placeholder="東京都渋谷区..."
                />
                <p class="mt-1.5 flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1.5 text-sm font-medium text-amber-800 border border-amber-300">
                    <svg class="w-4 h-4 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    番地・建物名までご入力ください
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        生年月日 <span class="text-red-500 ml-1">*</span>
                    </label>
                    <input
                        v-model="form.birth_date"
                        type="date"
                        required
                        class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4"
                    />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        成人式予定年月
                    </label>
                    <select
                        v-model="form.seijin_year"
                        class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4 bg-white"
                    >
                        <option value="">選択してください</option>
                        <option v-for="year in seijinYears" :key="year" :value="year">
                            {{ year }}年
                        </option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        学校名
                    </label>
                    <input
                        v-model="form.school_name"
                        type="text"
                        class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4"
                        placeholder="○○高等学校"
                    />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        担当者指名
                    </label>
                    <input
                        v-model="form.staff_name"
                        type="text"
                        class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4"
                        placeholder="担当者名"
                    />
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    来店動機
                </label>
                <div class="space-y-3">
                    <label
                        v-for="reason in visitReasonOptions"
                        :key="reason.value"
                        class="flex items-center p-3 rounded-lg border-2 border-gray-200 hover:border-pink-300 hover:bg-pink-50 transition-all duration-200 cursor-pointer group"
                    >
                        <input
                            type="checkbox"
                            :value="reason.value"
                            v-model="form.visit_reasons"
                            class="w-5 h-5 rounded border-2 border-gray-300 text-pink-600 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200"
                        />
                        <span class="ml-3 text-sm text-gray-700 font-medium group-hover:text-pink-600 transition-colors">{{ reason.label }}</span>
                    </label>
                    <div v-if="form.visit_reasons && form.visit_reasons.includes('その他')" class="ml-8 mt-3">
                        <input
                            v-model="form.visit_reason_other"
                            type="text"
                            placeholder="その他の内容を入力してください"
                            class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4"
                        />
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    駐車場利用
                </label>
                <div class="flex space-x-6">
                    <label class="flex items-center cursor-pointer group">
                        <input
                            v-model="form.parking_usage"
                            type="radio"
                            value="なし"
                            class="w-5 h-5 rounded-full border-2 border-gray-300 text-pink-600 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200"
                        />
                        <span class="ml-2 text-sm text-gray-700 font-medium group-hover:text-pink-600 transition-colors">なし</span>
                    </label>
                    <label class="flex items-center cursor-pointer group">
                        <input
                            v-model="form.parking_usage"
                            type="radio"
                            value="あり"
                            class="w-5 h-5 rounded-full border-2 border-gray-300 text-pink-600 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200"
                        />
                        <span class="ml-2 text-sm text-gray-700 font-medium group-hover:text-pink-600 transition-colors">あり</span>
                    </label>
                </div>
            </div>

            <div v-if="form.parking_usage === 'あり'">
                <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    駐車台数
                </label>
                <input
                    v-model="form.parking_car_count"
                    type="number"
                    min="1"
                    class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4"
                    placeholder="1"
                />
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    検討中のプラン
                </label>
                <div class="space-y-3">
                    <label
                        v-for="plan in availablePlans"
                        :key="plan"
                        class="flex items-center p-3 rounded-lg border-2 border-gray-200 hover:border-pink-300 hover:bg-pink-50 transition-all duration-200 cursor-pointer group"
                    >
                        <input
                            type="checkbox"
                            :value="plan"
                            v-model="form.considering_plans"
                            class="w-5 h-5 rounded border-2 border-gray-300 text-pink-600 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200"
                        />
                        <span class="ml-3 text-sm text-gray-700 font-medium group-hover:text-pink-600 transition-colors">{{ plan }}</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    ご紹介者様お名前
                </label>
                <input
                    v-model="form.referred_by_name"
                    type="text"
                    class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4"
                    placeholder="紹介者様のお名前"
                />
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                    お問い合わせ内容
                </label>
                <textarea
                    v-model="form.inquiry_message"
                    rows="4"
                    class="w-full rounded-lg border-2 border-gray-300 shadow-sm focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-200 py-3 px-4 resize-none"
                    placeholder="お問い合わせ内容をご記入ください"
                ></textarea>
            </div>

            <div class="pt-6 border-t border-gray-200">
                <button
                    type="submit"
                    :disabled="!internalSelectedTimeslot || processing"
                    class="w-full bg-gradient-to-r from-pink-600 to-rose-600 text-white py-4 px-6 rounded-xl font-semibold text-lg shadow-lg hover:shadow-xl hover:from-pink-700 hover:to-rose-700 transform hover:scale-[1.02] transition-all duration-200 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:transform-none disabled:shadow-none flex items-center justify-center"
                >
                    <svg v-if="!processing" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ processing ? '確認中...' : '送信内容の確認へ' }}
                </button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    event: Object,
    shops: Array,
    venues: Array,
    timeslots: Array,
    selectedTimeslot: Object,
    fromAdmin: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['submitted', 'timeslot-selected', 'confirm']);

const internalSelectedTimeslot = ref(props.selectedTimeslot || null);
const customerInfoSection = ref(null);

// URLパラメータから予約枠を取得して自動選択（timeslot_idのみ）
const urlParams = new URLSearchParams(window.location.search);
if (props.timeslots && props.timeslots.length > 0) {
    const urlTimeslotId = urlParams.get('timeslot_id');
    
    if (urlTimeslotId) {
        // timeslot_idが指定されている場合、そのIDで直接検索
        const matchingTimeslot = props.timeslots.find(t => t.id == urlTimeslotId);
        if (matchingTimeslot) {
            internalSelectedTimeslot.value = matchingTimeslot;
        }
    }
}

// 会場を取得（コントローラーでソート済みの venues を優先＝予約枠の最終日が直近の順）
const eventVenues = computed(() => {
    if (props.venues && props.venues.length > 0) {
        return props.venues;
    }
    if (props.event?.venues && props.event.venues.length > 0) {
        return props.event.venues;
    }
    return [];
});

/**
 * 会場の開催日リストを表示用にフォーマット
 * ルール: 月を大きく表示し「/」で区切り、2日連続は「〇, 〇」、3日以上連続は「〇 ~ 〇」、月をまたぐ場合は改行して月ごとに表示
 */
function formatVenueDates(dateStrings) {
    if (!dateStrings || dateStrings.length === 0) return [];
    const sorted = [...dateStrings].sort();
    const byMonth = {};
    for (const d of sorted) {
        const [y, m] = d.split('-');
        const key = `${y}-${m}`;
        if (!byMonth[key]) byMonth[key] = [];
        byMonth[key].push(parseInt(d.split('-')[2], 10));
    }
    const monthOrder = Object.keys(byMonth).sort();
    return monthOrder.map((key) => {
        const [, m] = key.split('-');
        const monthNum = parseInt(m, 10);
        const monthLabel = `${monthNum}月`;
        const days = byMonth[key].sort((a, b) => a - b);
        const ranges = [];
        let start = days[0];
        let end = days[0];
        for (let i = 1; i < days.length; i++) {
            if (days[i] === end + 1) {
                end = days[i];
            } else {
                ranges.push({ start, end });
                start = days[i];
                end = days[i];
            }
        }
        ranges.push({ start, end });
        const dayParts = ranges.map(({ start: s, end: e }) => {
            if (s === e) return String(s);
            if (e === s + 1) return `${s}, ${e}`;
            return `${s} ~ ${e}`;
        });
        return { monthLabel, dayParts };
    });
}

// 選択された会場でフィルタリングされた予約枠
const filteredTimeslots = computed(() => {
    if (!props.timeslots || props.timeslots.length === 0) return [];
    if (!form.venue_id) return [];
    
    return props.timeslots.filter(timeslot => {
        // venue_idがnullの場合は、すべての会場で利用可能とみなす（後方互換性のため）
        // venue_idが設定されている場合は、選択された会場と一致するもののみ
        return !timeslot.venue_id || timeslot.venue_id == form.venue_id;
    });
});

// 予約枠を日付ごとにグループ化
const groupedTimeslots = computed(() => {
    if (!filteredTimeslots.value || filteredTimeslots.value.length === 0) return {};
    const groups = {};
    filteredTimeslots.value.forEach(timeslot => {
        const date = new Date(timeslot.start_at);
        const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        if (!groups[dateKey]) {
            groups[dateKey] = [];
        }
        groups[dateKey].push(timeslot);
    });
    // 各日付の枠を時間順にソート
    Object.keys(groups).forEach(dateKey => {
        groups[dateKey].sort((a, b) => {
            return new Date(a.start_at) - new Date(b.start_at);
        });
    });
    return groups;
});

const selectTimeslot = async (timeslot) => {
    internalSelectedTimeslot.value = timeslot;
    emit('timeslot-selected', timeslot);
    
    // お客様情報セクションまでスクロール
    await nextTick();
    if (customerInfoSection.value) {
        customerInfoSection.value.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'short',
    });
};

const formatTime = (datetime) => {
    const date = new Date(datetime);
    return date.toLocaleTimeString('ja-JP', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getRemainingCapacity = (timeslot) => {
    return timeslot.remaining_capacity || 0;
};

// URLパラメータからフォームの初期値を取得
const getUrlParam = (key, defaultValue = '') => {
    return urlParams.has(key) ? urlParams.get(key) : defaultValue;
};

const getUrlParamArray = (key, defaultValue = []) => {
    if (urlParams.has(key)) {
        const value = urlParams.get(key);
        return value.split(',').map(p => p.trim()).filter(p => p);
    }
    return defaultValue;
};

// 成人式予定年の選択肢（現在年から10年後まで）
const currentYear = new Date().getFullYear();
const seijinYears = Array.from({ length: 11 }, (_, i) => currentYear + i);

// 生年月日から成人式予定年を計算する関数
const calculateSeijinYear = (birthDate) => {
    if (!birthDate) {
        return null;
    }
    
    try {
        const date = new Date(birthDate);
        if (isNaN(date.getTime())) {
            return null;
        }
        
        // 生年月日の年 + 20 = 成人式予定年
        const birthYear = date.getFullYear();
        const seijinYear = birthYear + 20;
        
        // 計算された年が選択肢に含まれているか確認
        if (seijinYears.includes(seijinYear)) {
            return seijinYear;
        }
        
        return null;
    } catch (error) {
        console.error('生年月日の計算エラー:', error);
        return null;
    }
};

// URLパラメータから生年月日と成人式予定年を取得
const urlBirthDate = getUrlParam('birth_date', '');
const urlSeijinYear = urlParams.has('seijin_year') ? parseInt(urlParams.get('seijin_year'), 10) : null;

// URLパラメータから成人式予定年が取得できない場合、生年月日から計算
let initialSeijinYear = urlSeijinYear;
if (!initialSeijinYear && urlBirthDate) {
    initialSeijinYear = calculateSeijinYear(urlBirthDate);
}

console.log('[デバッグ] URLパラメータから取得:', {
    urlBirthDate,
    urlSeijinYear,
    initialSeijinYear,
    calculatedFromBirthDate: !urlSeijinYear && urlBirthDate
});

const form = useForm({
    name: getUrlParam('name', ''),
    email: getUrlParam('email', ''),
    phone: getUrlParam('phone', ''),
    reservation_datetime: '',
    venue_id: null,
    has_visited_before: urlParams.has('has_visited_before') ? urlParams.get('has_visited_before') === 'true' : false,
    postal_code: getUrlParam('postal_code', ''),
    address: getUrlParam('address', ''),
    birth_date: urlBirthDate,
    seijin_year: initialSeijinYear,
    referred_by_name: getUrlParam('referred_by_name', ''),
    furigana: getUrlParam('furigana', ''),
    school_name: '',
    staff_name: '',
    visit_reasons: [],
    visit_reason_other: '',
    parking_usage: '',
    parking_car_count: null,
    considering_plans: getUrlParamArray('considering_plans', []),
    heard_from: '',
    inquiry_message: getUrlParam('inquiry_message', ''),
});

console.log('[デバッグ] フォーム初期値:', {
    birth_date: form.birth_date,
    seijin_year: form.seijin_year
});

// 検討中のプランの選択肢
const availablePlans = [
    '振袖レンタルプラン',
    '振袖購入プラン',
    'ママ振りフォトプラン',
    'フォトレンタルプラン',
];

// 来店動機の選択肢
const visitReasonOptions = [
    { value: '紹介', label: '紹介' },
    { value: 'DM・カタログ', label: 'DM・カタログ' },
    { value: 'SNS広告(Instaなど)', label: 'SNS広告(Instaなど)' },
    { value: 'WEB広告', label: 'WEB広告' },
    { value: 'その他', label: 'その他(テキスト入力)' },
];

// 生年月日の変更を監視して、成人式予定年を自動選択
watch(() => form.birth_date, (newBirthDate) => {
    if (newBirthDate) {
        const calculatedYear = calculateSeijinYear(newBirthDate);
        if (calculatedYear && !form.seijin_year) {
            // 既に値が設定されていない場合のみ自動選択
            form.seijin_year = calculatedYear;
        }
    }
});

// 会場が一つしかない場合はデフォルトで選択
watch(() => eventVenues.value, (newVenues) => {
    if (newVenues && newVenues.length === 1 && !form.venue_id) {
        // 会場が一つしかない場合は自動選択
        form.venue_id = newVenues[0].id;
    }
}, { immediate: true });

// 予約枠が選択された場合、会場を自動選択
watch(() => internalSelectedTimeslot.value, (newTimeslot) => {
    if (newTimeslot) {
        // 予約枠から会場IDを取得して自動選択
        if (newTimeslot.venue_id && !form.venue_id) {
            const venue = eventVenues.value.find(v => v.id === newTimeslot.venue_id);
            if (venue) {
                form.venue_id = venue.id;
            }
        }
        
        // start_atをY-m-d H:i:s形式の文字列に変換
        const date = new Date(newTimeslot.start_at);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        form.reservation_datetime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }
}, { immediate: true });

// 会場が変更された場合、選択されている予約枠をクリア（予約枠の会場と一致しない場合のみ）
watch(() => form.venue_id, (newVenueId, oldVenueId) => {
    // 初期化時はスキップ
    if (oldVenueId === undefined) return;
    
    // 予約枠が選択されている場合、会場が一致しない場合は予約枠をクリア
    if (internalSelectedTimeslot.value) {
        const timeslotVenueId = internalSelectedTimeslot.value.venue_id;
        // 予約枠に会場IDが設定されている場合、選択された会場と一致しない場合はクリア
        if (timeslotVenueId && timeslotVenueId != newVenueId) {
            internalSelectedTimeslot.value = null;
            form.reservation_datetime = '';
        }
    }
});

const processing = ref(false);

const searchAddress = async () => {
    if (!form.postal_code) return;
    const postalCode = form.postal_code.replace(/-/g, '');
    if (!/^\d{7}$/.test(postalCode)) return;
    try {
        const response = await axios.get(route('api.postal-code.search'), {
            params: { postal_code: postalCode },
        });
        if (response.data.address) {
            form.address = response.data.address;
        }
    } catch (error) {
        if (error.response?.data?.error) {
            console.warn('住所の取得に失敗しました:', error.response.data.error);
        } else if (error.message) {
            console.error('住所の取得に失敗しました:', error.message);
        }
    }
};

const formatDateTime = (datetime) => {
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const submit = () => {
    if (!internalSelectedTimeslot.value) {
        alert('予約日時を選択してください。');
        return;
    }

    // 確認ページに遷移
    emit('confirm', {
        ...form.data(),
        reservation_datetime: form.reservation_datetime,
        timeslot_id: internalSelectedTimeslot.value.id, // 予約枠IDを追加
        from_admin: props.fromAdmin,
    });
};
</script>

