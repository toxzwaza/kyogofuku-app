<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ご予約ありがとうございます</title>
    <style>
        /* メールクライアントは CSS 変数に非対応なことが多いため、色・角丸は実値で記述する */
        body {
            margin: 0;
            padding: 24px 16px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', 'Yu Gothic UI', 'Meiryo', sans-serif;
            background: linear-gradient(165deg, #f3eeef 0%, #fdf8f9 48%, #f8f6f4 100%);
            line-height: 1.75;
            color: #5a5356;
            -webkit-font-smoothing: antialiased;
        }
        .email-wrap {
            max-width: 560px;
            margin: 0 auto;
        }
        .email-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(44, 40, 41, 0.07);
            overflow: hidden;
            border: 1px solid rgba(235, 227, 229, 0.9);
        }
        .email-header {
            padding: 36px 28px 28px;
            text-align: center;
            background: linear-gradient(180deg, #fffefe 0%, #ffffff 100%);
            border-bottom: 1px solid #ebe3e5;
            position: relative;
        }
        .email-header::after {
            content: '';
            display: block;
            width: 48px;
            height: 3px;
            margin: 20px auto 0;
            border-radius: 9999px;
            background: linear-gradient(90deg, #e8d4d6, #b07a7a, #e8d4d6);
            opacity: 0.85;
        }
        .logo {
            max-width: 168px;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .tagline {
            margin-top: 14px;
            font-size: 11px;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: #a8989b;
            font-weight: 500;
        }
        .email-body {
            padding: 32px 28px 36px;
        }
        .hero-name {
            font-size: 22px;
            font-weight: 600;
            color: #2c2829;
            margin: 0 0 8px;
            letter-spacing: 0.02em;
        }
        .hero-name span {
            font-size: 14px;
            font-weight: 500;
            color: #b07a7a;
            margin-left: 4px;
        }
        .lead {
            margin: 0 0 28px;
            font-size: 15px;
            color: #5a5356;
        }
        .lead strong {
            color: #2c2829;
            font-weight: 600;
            background: linear-gradient(transparent 65%, #f5eaea 65%);
            padding: 0 2px;
        }
        .section-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.12em;
            color: #b07a7a;
            margin-bottom: 14px;
            text-transform: uppercase;
        }
        .section-label .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #b07a7a;
            opacity: 0.6;
        }
        .section-label--line {
            color: #2f8f68;
            margin-bottom: 14px;
        }
        .section-label--line .dot {
            background: #2f8f68;
            opacity: 0.55;
        }
        .info-panel {
            background: #fdf8f9;
            border-radius: 14px;
            padding: 4px 4px 2px;
            margin-bottom: 28px;
            border: 1px solid rgba(232, 212, 214, 0.6);
        }
        .info-panel-inner {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px 18px;
        }
        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 4px;
            border-bottom: 1px dashed #ebe3e5;
        }
        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 2px;
        }
        .info-label {
            flex: 0 0 108px;
            font-size: 12px;
            font-weight: 600;
            color: #9a8f91;
            letter-spacing: 0.04em;
        }
        .info-value {
            flex: 1;
            font-size: 15px;
            color: #2c2829;
            font-weight: 500;
            word-break: break-word;
        }
        .info-value--multiline {
            white-space: pre-wrap;
            line-height: 1.75;
            font-weight: 400;
        }
        .line-block {
            background: #eef6f2;
            border-radius: 14px;
            padding: 4px 4px 2px;
            margin-bottom: 28px;
            border: 1px solid rgba(47, 143, 104, 0.22);
        }
        .line-block-inner {
            background: #ffffff;
            border-radius: 12px;
            padding: 22px 18px 20px;
            text-align: center;
        }
        .line-block-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            padding: 5px 12px;
            border-radius: 9999px;
            background: rgba(47, 143, 104, 0.1);
            color: #256b4f;
            margin-bottom: 12px;
        }
        .line-block-title {
            margin: 0 0 10px;
            font-size: 17px;
            font-weight: 600;
            color: #2c2829;
            letter-spacing: 0.06em;
            line-height: 1.35;
        }
        .line-block-lead {
            margin: 0 0 18px;
            font-size: 14px;
            line-height: 1.75;
            color: #5a5356;
            text-align: left;
        }
        .line-block-note {
            margin: 0 0 14px;
            font-size: 13px;
            line-height: 1.65;
            color: #5a5356;
            text-align: left;
        }
        .line-block-note strong {
            color: #256b4f;
            font-weight: 600;
        }
        .line-block-btn {
            display: inline-block;
            margin: 2px 0 12px;
            padding: 14px 32px;
            font-size: 14px;
            font-weight: 600;
            color: #ffffff !important;
            text-decoration: none !important;
            border-radius: 9999px;
            background: linear-gradient(135deg, #3d9f76 0%, #256b4f 100%);
            box-shadow: 0 4px 14px rgba(37, 107, 79, 0.22);
            letter-spacing: 0.06em;
        }
        .line-block-url-box {
            margin-top: 4px;
            padding: 14px 14px;
            background: #eef6f2;
            border-radius: 10px;
            border: 1px dashed rgba(47, 143, 104, 0.28);
            font-size: 12px;
            line-height: 1.6;
            word-break: break-all;
            text-align: left;
            color: #6b7a72;
        }
        .line-block-url-box a {
            color: #256b4f !important;
            font-weight: 600;
        }
        .line-block-sub {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px dashed #ebe3e5;
            font-size: 13px;
            line-height: 1.65;
            text-align: left;
            color: #5a5356;
        }
        .line-block-sub a {
            color: #256b4f !important;
            font-weight: 600;
            word-break: break-all;
        }
        .panel-catalog {
            text-align: center;
            background: linear-gradient(145deg, #fff5f3 0%, #ffefea 50%, #faf5f2 100%);
            border-radius: 14px;
            padding: 28px 22px;
            margin-bottom: 24px;
            border: 1px solid rgba(224, 184, 176, 0.45);
        }
        .panel-catalog h3 {
            margin: 0 0 8px;
            font-size: 17px;
            font-weight: 600;
            color: #6b4f4a;
            letter-spacing: 0.06em;
        }
        .panel-catalog p {
            margin: 0 0 20px;
            font-size: 14px;
            color: #7d6560;
        }
        .btn-primary {
            display: inline-block;
            padding: 14px 32px;
            font-size: 14px;
            font-weight: 600;
            color: #ffffff !important;
            text-decoration: none !important;
            border-radius: 9999px;
            background: linear-gradient(135deg, #9a6b6b 0%, #7d5558 100%);
            box-shadow: 0 4px 14px rgba(125, 85, 88, 0.28);
            letter-spacing: 0.06em;
        }
        .closing {
            margin: 28px 0 0;
            padding-top: 24px;
            border-top: 1px solid #ebe3e5;
            font-size: 14px;
            color: #5a5356;
            line-height: 1.9;
        }
        .ornament {
            text-align: center;
            margin: 18px 0 0;
            font-size: 13px;
            color: #c4b4b7;
            letter-spacing: 0.35em;
        }
        .email-footer {
            padding: 28px 24px 32px;
            text-align: center;
            background: linear-gradient(180deg, #faf7f8 0%, #f0ebed 100%);
            border-top: 1px solid #ebe3e5;
        }
        .email-footer .name {
            font-size: 13px;
            font-weight: 600;
            color: #2c2829;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
        }
        .email-footer .addr {
            font-size: 12px;
            color: #8a8183;
        }
        @media only screen and (max-width: 600px) {
            body { padding: 16px 12px; }
            .email-body { padding: 26px 20px 30px; }
            .info-row { flex-direction: column; gap: 6px; }
            .info-label { flex: none; }
            .line-block-inner { padding: 20px 16px 18px; }
            .line-block-title { font-size: 16px; }
            .line-block-btn { display: block; text-align: center; padding: 14px 20px; }
        }
    </style>
</head>
<body>
    <div class="email-wrap">
        <div class="email-card">
            <div class="email-header">
                <img src="{{ asset('storage/logo/logo_b.png') }}" alt="{{ config('app.name') }}" class="logo">
                <p class="tagline">Thank you</p>
            </div>

            <div class="email-body">
                <p class="hero-name">{{ $reservation->name }}<span>様</span></p>

                <p class="lead">
                    この度は、<strong>{{ $reservation->event->title ?? 'イベント' }}</strong> にご予約いただき、ありがとうございます。大切なお知らせをお届けします。
                </p>

                @php
                    $lineLiffUrlVal = $lineLiffUrl ?? null;
                    $lineAddFriendUrlVal = $lineAddFriendUrl ?? '';
                    $lineIncludeFriend = $reservationEmailIncludeAddFriendUrl ?? true;
                    $showLineHero = filled($lineLiffUrlVal) || ($lineIncludeFriend && filled($lineAddFriendUrlVal));
                @endphp
                @if($showLineHero)
                <div class="section-label section-label--line"><span class="dot" aria-hidden="true"></span>LINE連携</div>
                <div class="line-block">
                    <div class="line-block-inner">
                        <div class="line-block-badge">おすすめ</div>
                        <h2 class="line-block-title">簡単LINE連携</h2>
                        <p class="line-block-lead">
                            以下のURLよりLINE連携を行っていただくと、今後のご連絡がスムーズになります。ご協力のほどお願いいたします。
                        </p>
                        @if(filled($lineLiffUrlVal))
                        <p class="line-block-note">
                            スマートフォンの<strong>LINEアプリ</strong>で開き、画面の手順に沿って友だち追加と連携を完了してください。
                        </p>
                        <a href="{{ $lineLiffUrlVal }}" class="line-block-btn">LINE連携ページを開く</a>
                        <div class="line-block-url-box">
                            <span style="color:#8a918d;">URL：</span><a href="{{ $lineLiffUrlVal }}">{{ $lineLiffUrlVal }}</a>
                        </div>
                        @if($lineIncludeFriend && filled($lineAddFriendUrlVal))
                        <div class="line-block-sub">
                            友だち追加だけ先に行う場合：<a href="{{ $lineAddFriendUrlVal }}">{{ $lineAddFriendUrlVal }}</a>
                        </div>
                        @endif
                        @else
                        <p class="line-block-note">
                            公式アカウントの友だち追加は、下記URLからお願いいたします。
                        </p>
                        <a href="{{ $lineAddFriendUrlVal }}" class="line-block-btn">友だち追加ページを開く</a>
                        <div class="line-block-url-box">
                            <span style="color:#8a918d;">URL：</span><a href="{{ $lineAddFriendUrlVal }}">{{ $lineAddFriendUrlVal }}</a>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <div class="section-label"><span class="dot" aria-hidden="true"></span>ご予約内容</div>
                <div class="info-panel">
                    <div class="info-panel-inner">
                        <div class="info-row">
                            <div class="info-label">お名前</div>
                            <div class="info-value">{{ $reservation->name }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">メール</div>
                            <div class="info-value">{{ $reservation->email }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">電話番号</div>
                            <div class="info-value">{{ $reservation->phone }}</div>
                        </div>
                        @if($reservation->reservation_datetime)
                        <div class="info-row">
                            <div class="info-label">予約日時</div>
                            <div class="info-value">{{ \Carbon\Carbon::parse($reservation->reservation_datetime)->format('Y年m月d日 H:i') }}</div>
                        </div>
                        @endif
                        @if($reservation->venue)
                        <div class="info-row">
                            <div class="info-label">会場</div>
                            <div class="info-value">{{ $reservation->venue->name ?? $reservation->venue }}</div>
                        </div>
                        @endif
                        @if($reservation->event->usesTimeslotReservation())
                        @if($reservation->furigana)
                        <div class="info-row">
                            <div class="info-label">フリガナ</div>
                            <div class="info-value">{{ $reservation->furigana }}</div>
                        </div>
                        @endif
                        @if($reservation->postal_code)
                        <div class="info-row">
                            <div class="info-label">郵便番号</div>
                            <div class="info-value">{{ $reservation->postal_code }}</div>
                        </div>
                        @endif
                        @if($reservation->address)
                        <div class="info-row">
                            <div class="info-label">住所</div>
                            <div class="info-value">{{ $reservation->address }}</div>
                        </div>
                        @endif
                        @if($reservation->event->form_type === 'reservation_hakama')
                        @if($reservation->school_name)
                        <div class="info-row">
                            <div class="info-label">学校名</div>
                            <div class="info-value">{{ $reservation->school_name }}</div>
                        </div>
                        @endif
                        @if($reservation->graduation_ceremony_date)
                        <div class="info-row">
                            <div class="info-label">卒業式</div>
                            <div class="info-value">{{ $reservation->graduation_ceremony_date->format('Y年n月j日') }}</div>
                        </div>
                        @elseif($reservation->graduation_ceremony_year && $reservation->graduation_ceremony_month)
                        <div class="info-row">
                            <div class="info-label">卒業式</div>
                            <div class="info-value">{{ $reservation->graduation_ceremony_year }}年{{ $reservation->graduation_ceremony_month }}月</div>
                        </div>
                        @endif
                        @if($reservation->visitor_count !== null)
                        <div class="info-row">
                            <div class="info-label">来店人数</div>
                            <div class="info-value">{{ $reservation->visitor_count }}名</div>
                        </div>
                        @endif
                        @if(!empty($reservation->companion_types))
                        <div class="info-row">
                            <div class="info-label">お連れ様</div>
                            <div class="info-value">{{ implode('、', $reservation->companion_types) }}</div>
                        </div>
                        @if($reservation->companion_hakama_usage !== null)
                        <div class="info-row">
                            <div class="info-label">お連れ様の袴着用</div>
                            <div class="info-value">{{ $reservation->companion_hakama_usage ? '着用する' : '着用しない' }}</div>
                        </div>
                        @endif
                        @endif
                        @if($reservation->koichi_furisode_used !== null)
                        <div class="info-row">
                            <div class="info-label">好一での振袖利用</div>
                            <div class="info-value">{{ $reservation->koichi_furisode_used ? 'あり' : 'なし' }}</div>
                        </div>
                        @endif
                        @endif
                        @if($reservation->visit_reasons && count($reservation->visit_reasons) > 0)
                        <div class="info-row">
                            <div class="info-label">来店動機</div>
                            <div class="info-value">{{ implode('、', $reservation->visit_reasons) }}</div>
                        </div>
                        @endif
                        @if($reservation->considering_plans && count($reservation->considering_plans) > 0)
                        <div class="info-row">
                            <div class="info-label">検討中のプラン</div>
                            <div class="info-value">{{ implode('、', $reservation->considering_plans) }}</div>
                        </div>
                        @endif
                        @if($reservation->parking_usage)
                        <div class="info-row">
                            <div class="info-label">お車で来店</div>
                            <div class="info-value">{{ $reservation->parking_usage }}@if($reservation->parking_usage === 'あり' && $reservation->parking_car_count)（{{ $reservation->parking_car_count }}台）@endif</div>
                        </div>
                        @endif
                        @endif
                        @if($reservation->event->form_type === 'document' && $reservation->request_method)
                        <div class="info-row">
                            <div class="info-label">希望方法</div>
                            <div class="info-value">{{ $reservation->request_method }}</div>
                        </div>
                        @endif
                        @if($reservation->inquiry_message)
                        <div class="info-row">
                            <div class="info-label">お問い合わせ内容</div>
                            <div class="info-value info-value--multiline">{{ $reservation->inquiry_message }}</div>
                        </div>
                        @endif
                    </div>
                </div>

                @if($reservation->event->form_type === 'document' && $reservation->request_method === 'デジタルカタログ' && $reservation->document_id)
                <div class="panel-catalog">
                    <h3>デジタルカタログ</h3>
                    <p>下のボタンから、カタログをご覧いただけます。</p>
                    <a href="{{ route('document.show', $reservation->document_id) }}" class="btn-primary">カタログを開く</a>
                </div>
                @endif

                <div class="closing">
                    ご不明な点がございましたら、お気軽にお問い合わせください。<br>
                    スタッフ一同、お会いできる日を楽しみにしております。
                </div>
                <p class="ornament" aria-hidden="true">✦ — ✦ — ✦</p>
            </div>

            <div class="email-footer">
                <div class="name">{{ config('app.name') }}</div>
                <div class="addr">{{ config('mail.from.address') }}</div>
            </div>
        </div>
    </div>
</body>
</html>
