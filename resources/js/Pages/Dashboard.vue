<template>
    <Head title="ダッシュボード" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">ダッシュボード</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <!-- 統計情報カード -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <StatCard
                        v-for="stat in statCards"
                        :key="stat.key"
                        :title="stat.title"
                        :value="stat.value"
                        :icon="stat.icon"
                        :color="stat.color"
                        :link="stat.link"
                    />
                </div>

                <!-- フォームタイプ別の統計 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">フォームタイプ別の予約数</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">予約フォーム</p>
                                <p class="text-2xl font-bold text-blue-600">{{ formTypeStats?.reservation || 0 }}</p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">資料請求フォーム</p>
                                <p class="text-2xl font-bold text-green-600">{{ formTypeStats?.document || 0 }}</p>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">お問い合わせフォーム</p>
                                <p class="text-2xl font-bold text-purple-600">{{ formTypeStats?.contact || 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 期間別の予約トレンド -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">過去7日間の予約トレンド</h3>
                            <div class="flex space-x-2">
                                <button
                                    @click="chartType = 'bar'"
                                    :class="[
                                        'px-3 py-1 text-sm rounded-md transition-colors',
                                        chartType === 'bar' 
                                            ? 'bg-indigo-600 text-white' 
                                            : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                                    ]"
                                >
                                    棒グラフ
                                </button>
                                <button
                                    @click="chartType = 'line'"
                                    :class="[
                                        'px-3 py-1 text-sm rounded-md transition-colors',
                                        chartType === 'line' 
                                            ? 'bg-indigo-600 text-white' 
                                            : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                                    ]"
                                >
                                    折れ線グラフ
                                </button>
                            </div>
                        </div>
                        <TrendChart :data="props.trend7Days" :type="chartType" />
                    </div>
                </div>

                <!-- アラート・通知セクション -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- 予約枠が満席に近いイベント -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">⚠️ 予約枠が満席に近いイベント</h3>
                        <div v-if="props.eventsWithLowCapacity && props.eventsWithLowCapacity.length > 0" class="space-y-2">
                            <div
                                v-for="item in props.eventsWithLowCapacity"
                                :key="item.event.id"
                                class="bg-white p-3 rounded border border-yellow-300"
                            >
                                <Link
                                    :href="route('admin.events.show', item.event.id)"
                                    class="text-indigo-600 hover:text-indigo-900 font-medium"
                                >
                                    {{ item.event.title }}
                                </Link>
                                <p class="text-sm text-gray-600 mt-1">
                                    埋まり率: {{ item.occupancy_rate }}% ({{ item.total_reserved }}/{{ item.total_capacity }})
                                </p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500">該当するイベントはありません</p>
                    </div>

                    <!-- 受付終了間近のイベント -->
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">📅 受付終了間近のイベント（7日以内）</h3>
                        <div v-if="props.endingSoonEvents && props.endingSoonEvents.length > 0" class="space-y-2">
                            <div
                                v-for="event in props.endingSoonEvents"
                                :key="event.id"
                                class="bg-white p-3 rounded border border-orange-300"
                            >
                                <Link
                                    :href="route('admin.events.show', event.id)"
                                    class="text-indigo-600 hover:text-indigo-900 font-medium"
                                >
                                    {{ event.title }}
                                </Link>
                                <p class="text-sm text-gray-600 mt-1">
                                    終了日: {{ formatDate(event.end_at) }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500">該当するイベントはありません</p>
                    </div>
                </div>

                <!-- 未対応の予約 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">未対応の予約（メモなし）</h3>
                            <Link
                                v-if="unhandledReservations && unhandledReservations.length > 0"
                                :href="route('admin.events.index')"
                                class="text-sm text-indigo-600 hover:text-indigo-900"
                            >
                                すべて見る →
                            </Link>
                        </div>
                        <div v-if="props.unhandledReservations && props.unhandledReservations.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">イベント</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">お名前</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">登録日時</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="reservation in props.unhandledReservations.slice(0, 5)" :key="reservation.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ reservation.event?.title || '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reservation.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatDateTime(reservation.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link
                                                v-if="reservation.event"
                                                :href="route('admin.reservations.show', reservation.id)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                詳細
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            未対応の予約はありません
                        </div>
                    </div>
                </div>

                <!-- 最近の予約と最近のメモ -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- 最近の予約 -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">最近の予約</h3>
                                <Link
                                    v-if="recentReservations && recentReservations.length > 0"
                                    :href="route('admin.events.index')"
                                    class="text-sm text-indigo-600 hover:text-indigo-900"
                                >
                                    すべて見る →
                                </Link>
                            </div>
                            <div v-if="props.recentReservations && props.recentReservations.length > 0" class="space-y-3">
                                <div
                                    v-for="reservation in props.recentReservations.slice(0, 5)"
                                    :key="reservation.id"
                                    class="border-b border-gray-200 pb-3 last:border-b-0 last:pb-0"
                                >
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <Link
                                                v-if="reservation.event"
                                                :href="route('admin.reservations.show', reservation.id)"
                                                class="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                                            >
                                                {{ reservation.event.title }}
                                            </Link>
                                            <p class="text-sm text-gray-900 mt-1">{{ reservation.name }}</p>
                                            <p class="text-xs text-gray-500 mt-1">{{ formatDateTime(reservation.created_at) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                予約データがありません
                            </div>
                        </div>
                    </div>

                    <!-- 最近のメモ -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">最近のメモ</h3>
                            <div v-if="props.recentNotes && props.recentNotes.length > 0" class="space-y-3">
                                <div
                                    v-for="note in props.recentNotes.slice(0, 5)"
                                    :key="note.id"
                                    class="border-b border-gray-200 pb-3 last:border-b-0 last:pb-0"
                                >
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-900">{{ note.user?.name || '不明' }}</p>
                                            <p class="text-xs text-gray-500 mt-1">{{ formatDateTime(note.created_at) }}</p>
                                            <p class="text-sm text-gray-700 mt-2 line-clamp-2">{{ note.content }}</p>
                                            <Link
                                                v-if="note.reservation"
                                                :href="route('admin.reservations.show', note.reservation.id)"
                                                class="text-xs text-indigo-600 hover:text-indigo-900 mt-1 inline-block"
                                            >
                                                予約詳細へ →
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                メモがありません
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 今週・来週の予約 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- 今週の予約 -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">今週の予約</h3>
                            <div v-if="props.thisWeekReservations && props.thisWeekReservations.length > 0" class="space-y-2">
                                <div
                                    v-for="reservation in props.thisWeekReservations.slice(0, 5)"
                                    :key="reservation.id"
                                    class="border-b border-gray-200 pb-2 last:border-b-0 last:pb-0"
                                >
                                    <Link
                                        :href="route('admin.reservations.show', reservation.id)"
                                        class="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                                    >
                                        {{ reservation.name }}
                                    </Link>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ formatDateTime(reservation.reservation_datetime) }}
                                    </p>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                今週の予約はありません
                            </div>
                        </div>
                    </div>

                    <!-- 来週の予約 -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">来週の予約</h3>
                            <div v-if="props.nextWeekReservations && props.nextWeekReservations.length > 0" class="space-y-2">
                                <div
                                    v-for="reservation in props.nextWeekReservations.slice(0, 5)"
                                    :key="reservation.id"
                                    class="border-b border-gray-200 pb-2 last:border-b-0 last:pb-0"
                                >
                                    <Link
                                        :href="route('admin.reservations.show', reservation.id)"
                                        class="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                                    >
                                        {{ reservation.name }}
                                    </Link>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ formatDateTime(reservation.reservation_datetime) }}
                                    </p>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                来週の予約はありません
                            </div>
                        </div>
                    </div>
                </div>

                <!-- フォームタイプ別の詳細統計 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">フォームタイプ別の詳細統計</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- 予約フォーム -->
                            <div>
                                <h4 class="font-medium text-gray-700 mb-3">予約フォーム</h4>
                                <p class="text-2xl font-bold text-blue-600 mb-3">{{ props.formTypeDetails?.reservation?.total || 0 }}</p>
                                <div v-if="props.formTypeDetails?.reservation?.by_venue && props.formTypeDetails.reservation.by_venue.length > 0">
                                    <p class="text-sm font-medium text-gray-600 mb-2">会場別:</p>
                                    <div class="space-y-1">
                                        <div
                                            v-for="item in props.formTypeDetails.reservation.by_venue"
                                            :key="item.venue_name"
                                            class="text-sm text-gray-600"
                                        >
                                            {{ item.venue_name }}: {{ item.count }}件
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 資料請求フォーム -->
                            <div>
                                <h4 class="font-medium text-gray-700 mb-3">資料請求フォーム</h4>
                                <p class="text-2xl font-bold text-green-600 mb-3">{{ props.formTypeDetails?.document?.total || 0 }}</p>
                                <div v-if="props.formTypeDetails?.document?.by_method && props.formTypeDetails.document.by_method.length > 0">
                                    <p class="text-sm font-medium text-gray-600 mb-2">請求方法別:</p>
                                    <div class="space-y-1">
                                        <div
                                            v-for="item in props.formTypeDetails.document.by_method"
                                            :key="item.method"
                                            class="text-sm text-gray-600"
                                        >
                                            {{ item.method }}: {{ item.count }}件
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- お問い合わせフォーム -->
                            <div>
                                <h4 class="font-medium text-gray-700 mb-3">お問い合わせフォーム</h4>
                                <p class="text-2xl font-bold text-purple-600 mb-3">{{ props.formTypeDetails?.contact?.total || 0 }}</p>
                                <div v-if="props.formTypeDetails?.contact?.by_response_method && props.formTypeDetails.contact.by_response_method.length > 0">
                                    <p class="text-sm font-medium text-gray-600 mb-2">回答方法別:</p>
                                    <div class="space-y-1">
                                        <div
                                            v-for="item in props.formTypeDetails.contact.by_response_method"
                                            :key="item.method"
                                            class="text-sm text-gray-600"
                                        >
                                            {{ item.method }}: {{ item.count }}件
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 店舗・スタッフ別の統計 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- 店舗別の予約数 -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">店舗別の予約数（上位10店舗）</h3>
                            <div v-if="props.shopStats && props.shopStats.length > 0" class="space-y-2">
                                <div
                                    v-for="item in props.shopStats"
                                    :key="item.shop.id"
                                    class="flex justify-between items-center border-b border-gray-200 pb-2 last:border-b-0 last:pb-0"
                                >
                                    <Link
                                        :href="route('admin.shops.edit', item.shop.id)"
                                        class="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                                    >
                                        {{ item.shop.name }}
                                    </Link>
                                    <span class="text-sm text-gray-600">{{ item.reservation_count }}件</span>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                データがありません
                            </div>
                        </div>
                    </div>

                    <!-- スタッフ別のメモ数 -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">スタッフ別のメモ数（上位10名）</h3>
                            <div v-if="props.staffStats && props.staffStats.length > 0" class="space-y-2">
                                <div
                                    v-for="item in props.staffStats"
                                    :key="item.user.id"
                                    class="flex justify-between items-center border-b border-gray-200 pb-2 last:border-b-0 last:pb-0"
                                >
                                    <span class="text-sm font-medium text-gray-900">{{ item.user.name }}</span>
                                    <span class="text-sm text-gray-600">{{ item.note_count }}件</span>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                データがありません
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import StatCard from '../Components/StatCard.vue';
import TrendChart from '../Components/TrendChart.vue';

const props = defineProps({
    stats: Object,
    formTypeStats: Object,
    occupancyRate: Number,
    trend7Days: Array,
    trend30Days: Array,
    recentReservations: Array,
    recentNotes: Array,
    recentEvents: Array,
    eventsWithLowCapacity: Array,
    endingSoonEvents: Array,
    unhandledReservations: Array,
    formTypeDetails: Object,
    shopStats: Array,
    staffStats: Array,
    thisWeekReservations: Array,
    nextWeekReservations: Array,
});

const statCards = computed(() => [
    {
        key: 'events',
        title: 'イベント数',
        value: props.stats.events || 0,
        icon: 'calendar',
        color: 'blue',
        link: route('admin.events.index'),
    },
    {
        key: 'active_events',
        title: 'アクティブなイベント',
        value: props.stats.active_events || 0,
        icon: 'calendar',
        color: 'green',
        link: route('admin.events.index'),
    },
    {
        key: 'reservations_today',
        title: '今日の予約',
        value: props.stats.reservations_today || 0,
        icon: 'clipboard',
        color: 'yellow',
        link: route('admin.events.index'),
    },
    {
        key: 'reservations_this_month',
        title: '今月の予約',
        value: props.stats.reservations_this_month || 0,
        icon: 'clipboard',
        color: 'green',
        link: route('admin.events.index'),
    },
    {
        key: 'reservations',
        title: '総予約数',
        value: props.stats.reservations || 0,
        icon: 'clipboard',
        color: 'indigo',
        link: route('admin.events.index'),
    },
        {
            key: 'occupancy_rate',
            title: '予約枠埋まり率',
            value: `${props.occupancyRate || 0}%`,
            icon: 'chart',
            color: 'purple',
            link: route('admin.events.index'),
        },
    {
        key: 'shops',
        title: '店舗数',
        value: props.stats.shops || 0,
        icon: 'store',
        color: 'orange',
        link: route('admin.shops.index'),
    },
    {
        key: 'users',
        title: 'スタッフ数',
        value: props.stats.users || 0,
        icon: 'users',
        color: 'indigo',
        link: route('admin.users.index'),
    },
]);

const chartType = ref('bar');

const formatDateTime = (datetime) => {
    if (!datetime) return '-';
    const date = new Date(datetime);
    return date.toLocaleString('ja-JP');
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP');
};
</script>
