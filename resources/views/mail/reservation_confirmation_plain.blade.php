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
@if($reservation->event->usesTimeslotReservation())
@if($reservation->furigana)
フリガナ: {{ $reservation->furigana }}
@endif
@if($reservation->postal_code)
郵便番号: {{ $reservation->postal_code }}
@endif
@if($reservation->address)
住所: {{ $reservation->address }}
@endif
@if($reservation->event->form_type === 'reservation_hakama')
@if($reservation->school_name)
学校名: {{ $reservation->school_name }}
@endif
@if($reservation->graduation_ceremony_date)
卒業式: {{ $reservation->graduation_ceremony_date->format('Y年n月j日') }}
@elseif($reservation->graduation_ceremony_year && $reservation->graduation_ceremony_month)
卒業式: {{ $reservation->graduation_ceremony_year }}年{{ $reservation->graduation_ceremony_month }}月
@endif
@if($reservation->visitor_count !== null)
来店人数: {{ $reservation->visitor_count }}名
@endif
@if($reservation->koichi_furisode_used !== null)
好一での振袖利用: {{ $reservation->koichi_furisode_used ? 'あり' : 'なし' }}
@endif
@endif
@if($reservation->visit_reasons && count($reservation->visit_reasons) > 0)
来店動機: {{ implode('、', $reservation->visit_reasons) }}
@endif
@if($reservation->considering_plans && count($reservation->considering_plans) > 0)
検討中のプラン: {{ implode('、', $reservation->considering_plans) }}
@endif
@if($reservation->parking_usage)
お車で来店: {{ $reservation->parking_usage }}@if($reservation->parking_usage === 'あり' && $reservation->parking_car_count)（{{ $reservation->parking_car_count }}台）@endif
@endif
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
