<script setup>
import { ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const hoveredMenu = ref(null);

const menuItems = [
    {
        key: 'events',
        label: 'イベント管理',
        route: 'admin.events.index',
        subItems: [
            { label: 'イベント一覧', route: 'admin.events.index' },
            { label: 'イベント作成', route: 'admin.events.create' },
        ],
    },
    {
        key: 'photo-slots',
        label: '前撮り管理',
        route: 'admin.photo-slots.index',
        subItems: [
            { label: '前撮り一覧', route: 'admin.photo-slots.index' },
            { label: '前撮り枠追加', route: 'admin.photo-slots.create' },
            { label: 'スタジオ一覧', route: 'admin.photo-studios.index' },
            { label: 'スタジオ追加', route: 'admin.photo-studios.create' },
        ],
    },
    {
        key: 'customers',
        label: '顧客管理',
        route: 'admin.customers.index',
        subItems: [
            { label: '顧客一覧', route: 'admin.customers.index' },
            { label: '顧客タグ一覧', route: 'admin.customer-tags.index' },
        ],
    },
    {
        key: 'master',
        label: 'マスタ管理',
        route: 'admin.shops.index',
        subItems: [
            { label: '店舗一覧', route: 'admin.shops.index' },
            { label: '店舗作成', route: 'admin.shops.create' },
            { label: 'スタッフ一覧', route: 'admin.users.index' },
            { label: 'スタッフ作成', route: 'admin.users.create' },
        ],
    },
    {
        key: 'activity-logs',
        label: 'ログ管理',
        route: 'admin.activity-logs.index',
        subItems: [
            { label: 'ログ一覧', route: 'admin.activity-logs.index' },
        ],
    },
];
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="bg-white border-b border-gray-100">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')">
                                    <img
                                        src="/storage/logo/logo_b.png"
                                        alt="京呉服 好一"
                                        class="block h-9 w-auto"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex items-center">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    ダッシュボード
                                </NavLink>
                                <div
                                    v-for="menu in menuItems"
                                    :key="menu.key"
                                    class="relative -my-px"
                                    @mouseenter="hoveredMenu = menu.key"
                                    @mouseleave="hoveredMenu = null"
                                >
                                    <Link
                                        :href="route(menu.route)"
                                        :class="[
                                            'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out',
                                            route().current(menu.route.replace('.index', '.*')) || 
                                            (menu.key === 'master' && (route().current('admin.shops.*') || route().current('admin.users.*'))) ||
                                            (menu.key === 'customers' && route().current('admin.customer-tags.*'))
                                                ? 'border-indigo-400 text-gray-900 focus:outline-none focus:border-indigo-700'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300'
                                        ]"
                                    >
                                        {{ menu.label }}
                                        <svg
                                            v-if="menu.subItems.length > 0"
                                            class="ml-1 h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </Link>
                                    <!-- サブナビゲーション -->
                                    <div
                                        v-if="hoveredMenu === menu.key && menu.subItems.length > 0"
                                        class="absolute top-full left-0 pt-1 w-48 z-50"
                                        @mouseenter="hoveredMenu = menu.key"
                                        @mouseleave="hoveredMenu = null"
                                    >
                                        <div class="bg-white rounded-md shadow-lg py-1 border border-gray-200">
                                            <Link
                                                v-for="subItem in menu.subItems"
                                                :key="subItem.route"
                                                :href="route(subItem.route)"
                                                :class="[
                                                    'block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors',
                                                    route().current(subItem.route) ? 'bg-gray-50 text-indigo-600 font-medium' : ''
                                                ]"
                                            >
                                                {{ subItem.label }}
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ml-6">
                            <!-- Settings Dropdown -->
                            <div class="ml-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="ml-2 -mr-0.5 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')"> Profile </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-mr-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
                    class="sm:hidden"
                >
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            ダッシュボード
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('admin.events.index')" :active="route().current('admin.events.*')">
                            イベント管理
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('admin.photo-slots.index')" :active="route().current('admin.photo-slots.*')">
                            前撮り管理
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('admin.customers.index')" :active="route().current('admin.customers.*') && !route().current('admin.customer-tags.*')">
                            顧客一覧
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('admin.customer-tags.index')" :active="route().current('admin.customer-tags.*')" class="pl-8">
                            顧客タグ一覧
                        </ResponsiveNavLink>
                        <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            マスタ管理
                        </div>
                        <ResponsiveNavLink :href="route('admin.shops.index')" :active="route().current('admin.shops.*')" class="pl-8">
                            店舗一覧
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('admin.shops.create')" :active="route().current('admin.shops.create')" class="pl-8">
                            店舗作成
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('admin.users.index')" :active="route().current('admin.users.*') && !route().current('admin.users.create')" class="pl-8">
                            スタッフ一覧
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('admin.users.create')" :active="route().current('admin.users.create')" class="pl-8">
                            スタッフ作成
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('admin.activity-logs.index')" :active="route().current('admin.activity-logs.*')">
                            ログ管理
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-gray-200">
                        <div class="px-4">
                            <div class="font-medium text-base text-gray-800">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="font-medium text-sm text-gray-500">{{ $page.props.auth.user.email }}</div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')"> Profile </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-white shadow" v-if="$slots.header">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
