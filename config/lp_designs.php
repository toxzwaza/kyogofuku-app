<?php

/**
 * 公開イベント LP テンプレート定義
 *
 * 各テンプレートは以下のいずれかのレンダリング方式を指定する：
 *  - render_type = 'inertia' : Vue/Inertia ページ（inertia_show / inertia_reserve）
 *  - render_type = 'blade'   : Blade ビュー（blade_view）
 *
 * default_tokens / allowed_token_keys（グローバル）: Inertia 系の既存テーマトークン仕様。
 * Blade 系では Blade 側で自由に CSS を組めるため、必須ではない。
 */
return [
    'templates' => [
        'pastel_fest' => [
            'label' => 'パステルフェス（lp_design サンプル）',
            'render_type' => 'inertia',
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

        'kimono_classic' => [
            'label' => '和モダン クラシック（Blade テンプレ）',
            'render_type' => 'blade',
            'blade_view' => 'event.lp.templates.kimono_classic',
            'allowed_form_types' => null,
            'requires_form_schema' => true,
            'default_tokens' => [],
        ],

        'daisougyousai' => [
            'label' => '大創業祭（伝統・販促）',
            'render_type' => 'blade',
            'blade_view' => 'event.lp.templates.daisougyousai',
            'allowed_form_types' => null,
            'requires_form_schema' => true,
            'default_tokens' => [],
        ],

        'daisougyousai_okayama' => [
            'label' => '大創業祭 岡山版（京呉服 好一・岡山店/城東店）',
            'render_type' => 'blade',
            'blade_view' => 'event.lp.templates.daisougyousai_okayama',
            'allowed_form_types' => null,
            'requires_form_schema' => true,
            'default_tokens' => [],
        ],

        'shop_visit' => [
            'label' => '店舗ご来店予約（白・ベージュ・ピンク／気軽さ訴求）',
            'render_type' => 'blade',
            'blade_view' => 'event.lp.templates.shop_visit',
            'allowed_form_types' => null,
            'requires_form_schema' => true,
            'default_tokens' => [],
        ],

        'maedori_uchiawase' => [
            'label' => '前撮り打合せ会（京呉服 好一 岡山店・城東店）',
            'render_type' => 'blade',
            'blade_view' => 'event.lp.templates.maedori_uchiawase',
            'allowed_form_types' => null,
            'requires_form_schema' => true,
            'default_tokens' => [],
        ],
    ],

    /**
     * すべてのテンプレで許可するトークンキー（pastel_fest の default_tokens のキーと一致）
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
