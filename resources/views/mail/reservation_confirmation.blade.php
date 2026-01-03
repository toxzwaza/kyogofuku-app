<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ご予約ありがとうございます</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif;
            background-color: #f3f4f6;
            line-height: 1.6;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 20px;
            text-align: center;
        }
        .logo {
            max-width: 200px;
            height: auto;
            margin-bottom: 20px;
        }
        .email-content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 30px;
            font-weight: 600;
        }
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
            color: white;
        }
        .info-card-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-card-title svg {
            width: 24px;
            height: 24px;
        }
        .info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 16px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        .info-item:last-child {
            margin-bottom: 0;
        }
        .info-label {
            font-weight: 600;
            min-width: 120px;
            font-size: 14px;
        }
        .info-value {
            flex: 1;
            font-size: 15px;
        }
        .details-section {
            background-color: #f9fafb;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid #e5e7eb;
        }
        .details-title {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .details-title svg {
            width: 20px;
            height: 20px;
            color: #667eea;
        }
        .details-content {
            color: #374151;
            white-space: pre-wrap;
            line-height: 1.8;
        }
        .catalog-section {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
            text-align: center;
        }
        .catalog-title {
            font-size: 18px;
            font-weight: bold;
            color: white;
            margin-bottom: 16px;
        }
        .catalog-link {
            display: inline-block;
            background-color: white;
            color: #f5576c;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .catalog-link:hover {
            transform: scale(1.05);
        }
        .footer {
            background-color: #1f2937;
            color: #9ca3af;
            padding: 30px;
            text-align: center;
            font-size: 14px;
        }
        .footer-text {
            margin-bottom: 10px;
        }
        .footer-address {
            color: #6b7280;
            font-size: 12px;
        }
        @media only screen and (max-width: 600px) {
            .email-content {
                padding: 30px 20px;
            }
            .info-item {
                flex-direction: column;
            }
            .info-label {
                min-width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- ヘッダー -->
        <div class="email-header">
            <img src="{{ asset('storage/logo/logo_b.png') }}" alt="京呉服 好一" class="logo">
        </div>

        <!-- コンテンツ -->
        <div class="email-content">
            <div class="greeting">
                {{ $reservation->name }} 様
            </div>

            <p style="color: #4b5563; margin-bottom: 30px; font-size: 16px;">
                この度は、<strong>{{ $reservation->event->title ?? 'イベント' }}</strong>にご予約いただき、誠にありがとうございます。
            </p>

            <!-- 予約情報カード -->
            <div class="info-card">
                <div class="info-card-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>ご予約内容</span>
                </div>

                <div class="info-item">
                    <div class="info-label">お名前</div>
                    <div class="info-value">{{ $reservation->name }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">メールアドレス</div>
                    <div class="info-value">{{ $reservation->email }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">電話番号</div>
                    <div class="info-value">{{ $reservation->phone }}</div>
                </div>

                @if($reservation->reservation_datetime)
                <div class="info-item">
                    <div class="info-label">予約日時</div>
                    <div class="info-value">{{ \Carbon\Carbon::parse($reservation->reservation_datetime)->format('Y年m月d日 H:i') }}</div>
                </div>
                @endif

                @if($reservation->venue)
                <div class="info-item">
                    <div class="info-label">会場</div>
                    <div class="info-value">{{ $reservation->venue->name ?? $reservation->venue }}</div>
                </div>
                @endif

                @if($reservation->event->form_type === 'document' && $reservation->request_method)
                <div class="info-item">
                    <div class="info-label">希望方法</div>
                    <div class="info-value">{{ $reservation->request_method }}</div>
                </div>
                @endif
            </div>

            @if($reservation->inquiry_message)
            <!-- お問い合わせ内容 -->
            <div class="details-section">
                <div class="details-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                    <span>お問い合わせ内容</span>
                </div>
                <div class="details-content">{{ $reservation->inquiry_message }}</div>
            </div>
            @endif

            @if($reservation->event->form_type === 'document' && $reservation->request_method === 'デジタルカタログ' && $reservation->document_id)
            <!-- デジタルカタログ -->
            <div class="catalog-section">
                <div class="catalog-title">デジタルカタログ</div>
                <p style="color: white; margin-bottom: 16px;">以下のリンクからデジタルカタログをご覧いただけます。</p>
                <a href="{{ route('document.show', $reservation->document_id) }}" class="catalog-link">
                    カタログを閲覧する
                </a>
            </div>
            @endif

            <p style="color: #4b5563; margin-top: 30px; font-size: 15px;">
                ご不明な点がございましたら、お気軽にお問い合わせください。
            </p>

            <p style="color: #4b5563; margin-top: 20px; font-size: 15px;">
                今後ともよろしくお願いいたします。
            </p>
        </div>

        <!-- フッター -->
        <div class="footer">
            <div class="footer-text">{{ config('app.name') }}</div>
            <div class="footer-address">{{ config('mail.from.address') }}</div>
        </div>
    </div>
</body>
</html>

