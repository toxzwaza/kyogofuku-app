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
@if($reservation->event->form_type === 'document' && $reservation->request_method)
希望方法: {{ $reservation->request_method }}
@endif

@if($reservation->inquiry_message)
【お問い合わせ内容】
{{ $reservation->inquiry_message }}
@endif

@if($reservation->event->form_type === 'document' && $reservation->request_method === 'デジタルカタログ' && $reservation->document_id)
【デジタルカタログ】
以下のリンクからデジタルカタログをご覧いただけます。

{{ route('document.show', $reservation->document_id) }}

@endif

ご不明な点がございましたら、お気軽にお問い合わせください。

今後ともよろしくお願いいたします。

--
{{ config('app.name') }}
{{ config('mail.from.address') }}

