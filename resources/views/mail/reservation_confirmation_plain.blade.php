{{ $reservation->name }} 様

この度は、{{ $reservation->event->title ?? 'イベント' }}にご予約いただき、誠にありがとうございます。

以下の内容でご予約を承りました。

【ご予約内容】
お名前: {{ $reservation->name }}
メールアドレス: {{ $reservation->email }}
電話番号: {{ $reservation->phone }}
@if($reservation->reservation_datetime)
予約日時: {{ $reservation->reservation_datetime }}
@endif
@if($reservation->venue)
会場: {{ $reservation->venue->name ?? $reservation->venue }}
@endif

@if($reservation->inquiry_message)
【お問い合わせ内容】
{{ $reservation->inquiry_message }}
@endif

ご不明な点がございましたら、お気軽にお問い合わせください。

今後ともよろしくお願いいたします。

--
{{ config('app.name') }}
{{ config('mail.from.address') }}

