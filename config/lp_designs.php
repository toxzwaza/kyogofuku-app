<?php

/**
 * 公開イベント LP テンプレート定義
 *
 * default_tokens: CSS カスタムプロパティ名 => 値（lp_theme_tokens で部分上書き）
 * allowed_token_keys: 管理画面・API で許可するキー（ホワイトリスト）
 */
return [
    'templates' => [
        'pastel_fest' => [
            'label' => 'パステルフェス（lp_design サンプル）',
            'allowed_form_types' => ['reservation'],
            'inertia_show' => 'Event/Lp/PastelFestShow',
            'inertia_reserve' => 'Event/Lp/PastelFestReserve',
            'default_tokens' => [
                '--pink' => '#e8729a',
                '--pink-soft' => '#f2a0b8',
                '--pink-pale' => '#fce4ec',
                '--pink-mist' => '#fff0f3',
                '--blush' => '#fff7f9',
                '--lavender' => '#f3e8f9',
                '--lavender-soft' => '#e8d5f5',
                '--baby-blue' => '#e3f0fa',
                '--mint' => '#e8f5ec',
                '--cream' => '#fffbf0',
                '--peach' => '#ffecd2',
                '--gold-soft' => '#e0c88a',
                '--text' => '#5c4a5a',
                '--text-light' => '#9b8a96',
                '--white' => '#fff',
                '--radius-soft' => '20px',
                '--radius-pill' => '100px',
                '--shadow-soft' => '0 4px 28px rgba(232,114,154,.10)',
                '--shadow-dreamy' => '0 8px 40px rgba(232,114,154,.12)',
            ],
        ],
    ],

    /**
     * すべてのテンプレで許可するトークンキー（上記 default_tokens のキーと一致させる）
     */
    'allowed_token_keys' => [
        '--pink',
        '--pink-soft',
        '--pink-pale',
        '--pink-mist',
        '--blush',
        '--lavender',
        '--lavender-soft',
        '--baby-blue',
        '--mint',
        '--cream',
        '--peach',
        '--gold-soft',
        '--text',
        '--text-light',
        '--white',
        '--radius-soft',
        '--radius-pill',
        '--shadow-soft',
        '--shadow-dreamy',
    ],
];
