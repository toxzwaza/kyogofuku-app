@extends('event.lp.layouts.base')

@section('title', $event->title.' お申込みありがとうございます')
@section('description', 'お申込みを承りました。')

@section('styles')
<style>
    body { margin:0; background:#fdfaf3; color:#2b1d12;
        font-family: 'Noto Sans JP', system-ui, -apple-system, sans-serif; line-height:1.7; }
    .thanks-wrap { max-width: 720px; margin: 0 auto; padding: 80px 24px; text-align:center; }
    .thanks-wrap h1 { font-family:'Noto Serif JP', serif; font-weight:700; font-size: 1.6rem; margin: 0 0 16px; }
    .thanks-wrap p { color: #6e5944; }
    .thanks-back { display: inline-block; margin-top: 32px; padding: 10px 20px;
        border:1px solid #8c2f25; color:#8c2f25; text-decoration:none; border-radius:4px; }
</style>
@endsection

@section('content')
<div class="thanks-wrap">
    <h1>お申込みありがとうございます</h1>
    <p>{{ $event->success_text ?? 'ご入力いただいた内容で承りました。担当者より追ってご連絡いたします。' }}</p>
    <a class="thanks-back" href="{{ route('event.show', ['slug' => $event->slug]) }}">イベントページへ戻る</a>
</div>
@endsection
