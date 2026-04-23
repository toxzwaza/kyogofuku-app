const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', '"Noto Sans JP"', ...defaultTheme.fontFamily.sans],
                serif: ['"Zen Old Mincho"', '"Yu Mincho"', '"YuMincho"', ...defaultTheme.fontFamily.serif],
            },

            // ------------------------------
            // 和＋モダン カラーシステム
            //   - 7系統の色スケール（sumi, ai, enji, unohana, uguisu, natane, akane）は固定値
            //   - brand-* セマンティックトークンは CSS 変数経由で light/dark を切替
            // ------------------------------
            colors: {
                // sumi (墨) - neutral / text
                sumi: {
                    50:  '#F7F7F8',
                    100: '#EEEEF0',
                    200: '#D8D8DC',
                    300: '#B4B4BB',
                    400: '#8A8A93',
                    500: '#62626B',
                    600: '#47474F',
                    700: '#33333A',
                    800: '#232329',
                    900: '#14141A',
                    950: '#0A0A0E',
                },
                // ai (藍) - primary
                ai: {
                    50:  '#F0F5FA',
                    100: '#DAE7F3',
                    200: '#AEC7E2',
                    300: '#7CA2CC',
                    400: '#4E7FB4',
                    500: '#2C5C94',
                    600: '#1F4577',
                    700: '#17335A',
                    800: '#102440',
                    900: '#0A1829',
                    950: '#060F1C',
                },
                // enji (臙脂) - accent
                enji: {
                    50:  '#FBF3F3',
                    100: '#F5E1E1',
                    200: '#E9B8B8',
                    300: '#D88C8C',
                    400: '#C26060',
                    500: '#A7413F',
                    600: '#8B2E2D',
                    700: '#6B2422',
                    800: '#4D1A19',
                    900: '#310F0E',
                    950: '#200908',
                },
                // unohana (卯の花) - surface warm
                unohana: {
                    50:  '#FEFDF9',
                    100: '#FBF8EF',
                    200: '#F4EEDB',
                    300: '#EBE1BE',
                    400: '#DBCB96',
                    500: '#C4AF6E',
                    600: '#A18B4E',
                    700: '#7A6939',
                    800: '#524629',
                    900: '#2E2817',
                    950: '#1A160C',
                },
                // uguisu (鶯) - success
                uguisu: {
                    50:  '#F4F7EE',
                    100: '#E3EBD0',
                    200: '#C5D8A2',
                    300: '#A0BE6E',
                    400: '#83A44A',
                    500: '#6A8737',
                    600: '#526B29',
                    700: '#3F511F',
                    800: '#2A3615',
                    900: '#18200C',
                    950: '#0E1306',
                },
                // natane (菜種) - warning
                natane: {
                    50:  '#FDF9EC',
                    100: '#FAF1CB',
                    200: '#F4DF8D',
                    300: '#ECC94B',
                    400: '#D9B11A',
                    500: '#B5920F',
                    600: '#8C7109',
                    700: '#665307',
                    800: '#423605',
                    900: '#231C03',
                    950: '#120F01',
                },
                // akane (茜) - danger
                akane: {
                    50:  '#FDF2F2',
                    100: '#FBDCDC',
                    200: '#F6AEAE',
                    300: '#EE7878',
                    400: '#E04848',
                    500: '#C42929',
                    600: '#9F1C1C',
                    700: '#7A1616',
                    800: '#561010',
                    900: '#320909',
                    950: '#1F0404',
                },
                // brand-* セマンティックトークン (light/dark で自動切替)
                brand: {
                    primary:         'rgb(var(--color-primary) / <alpha-value>)',
                    'primary-hover': 'rgb(var(--color-primary-hover) / <alpha-value>)',
                    'on-primary':    'rgb(var(--color-on-primary) / <alpha-value>)',
                    accent:          'rgb(var(--color-accent) / <alpha-value>)',
                    'on-accent':     'rgb(var(--color-on-accent) / <alpha-value>)',
                    success:         'rgb(var(--color-success) / <alpha-value>)',
                    warning:         'rgb(var(--color-warning) / <alpha-value>)',
                    danger:          'rgb(var(--color-danger) / <alpha-value>)',
                    surface:         'rgb(var(--color-surface) / <alpha-value>)',
                    'surface-2':     'rgb(var(--color-surface-2) / <alpha-value>)',
                    bg:              'rgb(var(--color-bg) / <alpha-value>)',
                    border:          'rgb(var(--color-border) / <alpha-value>)',
                    'border-strong': 'rgb(var(--color-border-strong) / <alpha-value>)',
                    text:            'rgb(var(--color-text) / <alpha-value>)',
                    'text-muted':    'rgb(var(--color-text-muted) / <alpha-value>)',
                    'text-subtle':   'rgb(var(--color-text-subtle) / <alpha-value>)',
                },
            },

            borderRadius: {
                'soft-sm': '4px',
                'soft':    '8px',
                'soft-lg': '12px',
                'soft-xl': '20px',
            },

            boxShadow: {
                'soft-sm': '0 1px 2px rgba(20,20,26,0.05)',
                'soft':    '0 4px 12px rgba(20,20,26,0.08)',
                'soft-lg': '0 12px 32px rgba(20,20,26,0.12)',
            },
        },
    },

    plugins: [require('@tailwindcss/forms')],
};
