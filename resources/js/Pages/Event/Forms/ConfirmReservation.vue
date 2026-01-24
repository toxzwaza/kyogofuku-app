<template>
    <div class="w-full px-2 sm:px-4 py-3 sm:py-4 animate-fade-in">
        <!-- ヘッダーセクション -->
        <div class="mb-4 sm:mb-6 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 lg:w-16 lg:h-16 bg-gradient-to-br from-pink-500 to-rose-600 rounded-full mb-2 sm:mb-3 shadow-lg">
                <svg class="w-6 h-6 sm:w-7 sm:h-7 lg:w-8 lg:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h2 class="text-xl sm:text-2xl lg:text-3xl font-bold bg-gradient-to-r from-pink-600 to-rose-600 bg-clip-text text-transparent mb-2">
                予約内容の確認
            </h2>
            <p class="text-gray-500 text-xs sm:text-sm">内容をご確認の上、送信ボタンを押してください</p>
        </div>
        
        <!-- メインカード -->
        <div class="bg-white rounded-lg sm:rounded-xl lg:rounded-2xl shadow-xl border border-gray-100 overflow-hidden mb-4 sm:mb-6 lg:mb-8 transform transition-all duration-300 hover:shadow-2xl">
            <!-- カードヘッダー -->
            <div class="bg-gradient-to-r from-pink-500 via-rose-500 to-pink-600 p-3 sm:p-4 lg:p-6">
                <div class="flex items-center space-x-2 sm:space-x-3">
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-1.5 sm:p-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold text-base sm:text-lg">予約情報</h3>
                        <p class="text-white/80 text-xs sm:text-sm">Reservation Details</p>
                    </div>
                </div>
            </div>

            <!-- カードボディ -->
            <div class="p-3 sm:p-4 lg:p-6 xl:p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 lg:gap-6">
                    <!-- 予約日時 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">予約日時</label>
                        </div>
                        <div v-if="selectedVenueName" class="mb-2 pl-9 sm:pl-10">
                            <span class="inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium bg-pink-100 text-pink-800">
                                <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1 sm:mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="truncate">{{ selectedVenueName }}</span>
                            </span>
                        </div>
                        <p class="text-gray-900 font-medium text-base sm:text-lg pl-9 sm:pl-10 break-words">{{ formatDateTime(formData.reservation_datetime) }}</p>
                    </div>

                    <!-- 予約日時 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">予約日時</label>
                        </div>
                        <div v-if="selectedVenueName" class="mb-2 pl-9 sm:pl-10">
                            <span class="inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium bg-pink-100 text-pink-800">
                                <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1 sm:mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="truncate">{{ selectedVenueName }}</span>
                            </span>
                        </div>
                        <p class="text-gray-900 font-medium text-base sm:text-lg pl-9 sm:pl-10 break-words">{{ formatDateTime(formData.reservation_datetime) }}</p>
                    </div>

                    <!-- お名前 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-rose-100 rounded-lg flex items-center justify-center group-hover:bg-rose-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">お名前</label>
                        </div>
                        <p class="text-gray-900 font-medium text-base sm:text-lg pl-9 sm:pl-10 break-words">{{ formData.name }}</p>
                    </div>

                    <!-- フリガナ -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">フリガナ</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10 break-words">{{ formData.furigana || '-' }}</p>
                    </div>

                    <!-- メールアドレス -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">メールアドレス</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10 break-all">{{ formData.email }}</p>
                    </div>

                    <!-- 電話番号 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-rose-100 rounded-lg flex items-center justify-center group-hover:bg-rose-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">電話番号</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10 break-words">{{ formData.phone }}</p>
                    </div>

                    <!-- ご来店会場 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">ご来店会場</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10 break-words">{{ selectedVenueName || '-' }}</p>
                    </div>

                    <!-- 過去当店のご来店 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">過去当店のご来店</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10">
                            <span :class="formData.has_visited_before ? 'text-pink-600 font-semibold' : 'text-gray-500'">
                                {{ formData.has_visited_before ? 'あり' : 'なし' }}
                            </span>
                        </p>
                    </div>

                    <!-- 住所 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-rose-100 rounded-lg flex items-center justify-center group-hover:bg-rose-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">住所</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10 break-words">{{ formData.address || '-' }}</p>
                    </div>

                    <!-- 生年月日 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">生年月日</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10 break-words">{{ formData.birth_date ? formatDate(formData.birth_date) : '-' }}</p>
                    </div>

                    <!-- 成人式予定年月 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-rose-100 rounded-lg flex items-center justify-center group-hover:bg-rose-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">成人式予定年月</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10 break-words">{{ formData.seijin_year ? formData.seijin_year + '年' : '-' }}</p>
                    </div>

                    <!-- 学校名 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">学校名</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10 break-words">{{ formData.school_name || '-' }}</p>
                    </div>

                    <!-- 担当者名 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-rose-100 rounded-lg flex items-center justify-center group-hover:bg-rose-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">担当者名</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10 break-words">{{ formData.staff_name || '-' }}</p>
                    </div>

                    <!-- 来店動機 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">来店動機</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10">
                            <span v-if="formData.visit_reasons && formData.visit_reasons.length > 0" class="inline-flex flex-wrap gap-1.5 sm:gap-2">
                                <span v-for="(reason, index) in formData.visit_reasons" :key="index" 
                                      class="px-2 sm:px-3 py-1 bg-pink-100 text-pink-700 rounded-full text-xs sm:text-sm font-medium">
                                    {{ reason }}
                                </span>
                            </span>
                            <span v-else class="text-gray-400">-</span>
                        </p>
                    </div>

                    <!-- 駐車場利用 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-rose-100 rounded-lg flex items-center justify-center group-hover:bg-rose-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">駐車場利用</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10 break-words">{{ formData.parking_usage || '-' }}</p>
                    </div>

                    <!-- 駐車台数 -->
                    <div v-if="formData.parking_usage === 'あり'" class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">駐車台数</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10 break-words">{{ formData.parking_car_count || '-' }}台</p>
                    </div>

                    <!-- 検討中のプラン -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-rose-100 rounded-lg flex items-center justify-center group-hover:bg-rose-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">検討中のプラン</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10">
                            <span v-if="formData.considering_plans && formData.considering_plans.length > 0" class="inline-flex flex-wrap gap-1.5 sm:gap-2">
                                <span v-for="(plan, index) in formData.considering_plans" :key="index" 
                                      class="px-2 sm:px-3 py-1 bg-pink-100 text-pink-700 rounded-full text-xs sm:text-sm font-medium">
                                    {{ plan }}
                                </span>
                            </span>
                            <span v-else class="text-gray-400">-</span>
                        </p>
                    </div>

                    <!-- ご紹介者様お名前 -->
                    <div class="group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">ご紹介者様お名前</label>
                        </div>
                        <p class="text-gray-900 font-medium text-sm sm:text-base pl-9 sm:pl-10 break-words">{{ formData.referred_by_name || '-' }}</p>
                    </div>

                    <!-- お問い合わせ内容 -->
                    <div class="md:col-span-2 group">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-rose-100 rounded-lg flex items-center justify-center group-hover:bg-rose-200 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                </svg>
                            </div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">お問い合わせ内容</label>
                        </div>
                        <div class="bg-pink-50 rounded-lg p-3 sm:p-4 border border-pink-200 ml-9 sm:ml-10">
                            <p class="text-gray-900 whitespace-pre-wrap leading-relaxed text-sm sm:text-base">{{ formData.inquiry_message || '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ボタンセクション -->
        <div class="flex flex-col sm:flex-row justify-end gap-3 sm:gap-4">
            <button
                @click="$emit('back')"
                class="px-6 sm:px-8 py-3 sm:py-3.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 transform hover:scale-105 active:scale-95 flex items-center justify-center space-x-2 text-sm sm:text-base"
            >
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>戻る</span>
            </button>
            <button
                @click="submit"
                :disabled="processing"
                class="px-6 sm:px-8 py-3 sm:py-3.5 bg-gradient-to-r from-pink-600 to-rose-600 text-white rounded-xl font-semibold hover:from-pink-700 hover:to-rose-700 disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2 text-sm sm:text-base"
            >
                <svg v-if="!processing" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else class="w-4 h-4 sm:w-5 sm:h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ processing ? '送信中...' : '送信する' }}</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    formData: Object,
    venues: Array,
    fromAdmin: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['back']);

const processing = ref(false);

const selectedVenueName = computed(() => {
    if (!props.formData.venue_id || !props.venues) return null;
    const venue = props.venues.find(v => v.id === props.formData.venue_id);
    return venue ? venue.name : null;
});

const formatDateTime = (datetime) => {
    if (!datetime) return '-';
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const submit = () => {
    processing.value = true;
    const form = useForm({
        ...props.formData,
        from_admin: props.fromAdmin || props.formData.from_admin || false,
        timeslot_id: props.formData.timeslot_id, // 予約枠IDを含める
    });
    
    // 送信後、成功ページにリダイレクトされる
    form.post(route('event.reserve', props.event.id), {
        preserveState: false, // 状態を保持しない（リダイレクトを確実にする）
        preserveScroll: false, // スクロール位置を保持しない
        onSuccess: () => {
            // リダイレクトが成功した場合、このコールバックは呼ばれない
            // リダイレクト先のページが読み込まれる
            processing.value = false;
        },
        onError: (errors) => {
            processing.value = false;
            console.error('送信エラー:', errors);
            // エラーがある場合はアラートを表示
            if (errors && Object.keys(errors).length > 0) {
                alert('送信に失敗しました。入力内容を確認してください。');
            }
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

onMounted(() => {
    // フェードインアニメーション用のスタイルを追加
    if (!document.getElementById('confirm-reservation-styles')) {
        const style = document.createElement('style');
        style.id = 'confirm-reservation-styles';
        style.textContent = `
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .animate-fade-in {
                animation: fadeIn 0.5s ease-out;
            }
        `;
        document.head.appendChild(style);
    }
});
</script>

