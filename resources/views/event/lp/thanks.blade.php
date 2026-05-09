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
    .thanks-summary { margin: 40px auto 0; max-width: 560px; text-align:left;
        background:#fff; border:1px solid #e7d9c0; border-radius:6px; padding: 20px 24px; }
    .thanks-summary h2 { font-family:'Noto Serif JP', serif; font-size: 1.1rem;
        margin: 0 0 16px; padding-bottom: 8px; border-bottom: 1px solid #e7d9c0; color:#2b1d12; }
    .thanks-summary dl { margin: 0; display: grid; grid-template-columns: 9em 1fr; row-gap: 10px; column-gap: 16px; }
    .thanks-summary dt { color:#8a7456; font-size: 0.92rem; }
    .thanks-summary dd { margin: 0; color:#2b1d12; word-break: break-word; }
    @media (max-width: 520px) {
        .thanks-summary dl { grid-template-columns: 1fr; row-gap: 4px; }
        .thanks-summary dt { font-size: 0.85rem; }
        .thanks-summary dd { padding-bottom: 8px; border-bottom: 1px dashed #e7d9c0; }
        .thanks-summary dd:last-child { border-bottom: 0; }
    }
    .thanks-back { display: inline-block; margin-top: 32px; padding: 10px 20px;
        border:1px solid #8c2f25; color:#8c2f25; text-decoration:none; border-radius:4px; }
</style>
@endsection

@section('content')
@php
    /**
     * 入力内容を form_schema 順に整形する。
     * - timeslot 型は store() 側で {key}_label に整形済みの日時文字列を入れているのでそれを使う。
     * - select で options_from='event_venues' の場合は会場名に解決する。
     * - select で options が schema 内に列挙されている場合は label に解決する。
     * - checkbox 等の配列は読点区切りで連結する。
     * - hidden 型・空値は表示から除外する。
     */
    $schema = is_array($event->form_schema ?? null) ? $event->form_schema : [];
    $formData = is_array($formData ?? null) ? $formData : [];
    $rows = [];
    foreach ($schema as $field) {
        $key = $field['key'] ?? null;
        if (!$key) continue;
        $type = $field['type'] ?? null;
        if ($type === 'hidden') continue;

        if ($type === 'timeslot' && array_key_exists($key.'_label', $formData)) {
            $value = $formData[$key.'_label'];
        } else {
            $value = $formData[$key] ?? null;
        }
        if ($value === null || $value === '' || $value === []) continue;

        if ($type === 'select') {
            if (($field['options_from'] ?? null) === 'event_venues') {
                $venue = $event->venues->firstWhere('id', (int) $value);
                if ($venue) $value = $venue->name;
            } elseif (!empty($field['options']) && is_array($field['options'])) {
                foreach ($field['options'] as $opt) {
                    if (is_array($opt) && (string) ($opt['value'] ?? '') === (string) $value) {
                        $value = $opt['label'] ?? $value;
                        break;
                    }
                }
            }
        }

        if (is_array($value)) {
            $value = implode('、', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value));
        }

        $rows[] = [
            'label' => $field['label'] ?? $key,
            'value' => $value,
        ];
    }
@endphp
<div class="thanks-wrap">
    <h1>お申込みありがとうございます</h1>
    <p>下記の内容で承りました。担当者より追ってご連絡いたします。</p>

    @if (!empty($rows))
    <div class="thanks-summary">
        <h2>ご入力内容</h2>
        <dl>
            @foreach ($rows as $row)
                <dt>{{ $row['label'] }}</dt>
                <dd>{{ $row['value'] }}</dd>
            @endforeach
        </dl>
    </div>
    @endif

    <a class="thanks-back" href="{{ route('event.show', ['slug' => $event->slug]) }}">イベントページへ戻る</a>
</div>
@endsection
