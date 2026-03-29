{{ $reservation->name }} 様

この度は、{{ $reservation->event->title ?? 'イベント' }}にご予約いただき、誠にありがとうございます。

@if(!empty($lineLiffUrl) || (!empty($reservationEmailIncludeAddFriendUrl) && !empty($lineAddFriendUrl)))
【簡単LINE連携・おすすめ】
以下のURLよりLINE連携を行っていただくと、今後のご連絡がスムーズになります。ご協力のほどお願いいたします。

@if(!empty($lineLiffUrl))
スマートフォンのLINEアプリで次のURLを開き、画面の手順に沿って友だち追加と連携を完了してください。

LINE連携ページ:
{{ $lineLiffUrl }}

@if(!empty($reservationEmailIncludeAddFriendUrl) && !empty($lineAddFriendUrl))
友だち追加のみ先に行う場合:
{{ $lineAddFriendUrl }}

@endif
@elseif(!empty($reservationEmailIncludeAddFriendUrl) && !empty($lineAddFriendUrl))
公式アカウントの友だち追加は、次のURLからお願いいたします。

{{ $lineAddFriendUrl }}

@endif
@endif

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
お問い合わせ内容:
{{ $reservation->inquiry_message }}
@endif

@if($reservation->event->form_type === 'document' && $reservation->request_method === 'デジタルカタログ' && $reservation->document_id)
【デジタルカタログ】
以下のリンクからデジタルカタログをご覧いただけます。

{{ route('document.show', $reservation->document_id) }}

@endif

ご不明な点がございましたら、お気軽にお問い合わせください。
スタッフ一同、お会いできる日を楽しみにしております。

--
{{ config('app.name') }}
{{ config('mail.from.address') }}
