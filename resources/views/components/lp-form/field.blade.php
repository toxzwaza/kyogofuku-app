@props(['field', 'old' => [], 'event' => null])

@php
    $key = $field['key'] ?? '';
    $type = $field['type'] ?? 'text';
    $label = $field['label'] ?? $key;
    $placeholder = $field['placeholder'] ?? '';
    $required = !empty($field['required']);
    $help = $field['help'] ?? null;
    $options = $field['options'] ?? [];

    $value = old($key, $old[$key] ?? ($field['default'] ?? ''));
    $errorClass = $errors->has($key) ? ' lp-field--error' : '';
@endphp

@php
    // 親 form の x-data に "values" オブジェクトがある場合に Alpine と双方向同期する。
    // values が無くても Alpine は黙って無視するため安全。
    $xModel = "values['".addslashes($key)."']";
@endphp

@if($type === 'hidden')
    <input type="hidden" name="{{ $key }}" value="{{ $value }}" x-model="{{ $xModel }}">
@else
<div class="lp-field{{ $errorClass }}">
    @if($type !== 'checkbox' || !empty($options))
        <label class="lp-field__label" for="lp-{{ $key }}">
            {{ $label }}
            @if($required)<span class="lp-field__required">必須</span>@endif
        </label>
    @endif

    @switch($type)
        @case('textarea')
            <textarea
                id="lp-{{ $key }}"
                name="{{ $key }}"
                x-model="{{ $xModel }}"
                @if($placeholder) placeholder="{{ $placeholder }}" @endif
                @if($required) required @endif
                rows="{{ $field['rows'] ?? 4 }}"
                class="lp-field__input lp-field__textarea"
            >{{ $value }}</textarea>
            @break

        @case('select')
            <select
                id="lp-{{ $key }}"
                name="{{ $key }}"
                x-model="{{ $xModel }}"
                @if($required) required @endif
                class="lp-field__input lp-field__select"
            >
                <option value="">{{ $placeholder ?: '選択してください' }}</option>
                @foreach($options as $opt)
                    <option value="{{ $opt }}" @selected((string) $value === (string) $opt)>{{ $opt }}</option>
                @endforeach
            </select>
            @break

        @case('timeslot')
            @php
                $weekdayMap = ['Mon'=>'月','Tue'=>'火','Wed'=>'水','Thu'=>'木','Fri'=>'金','Sat'=>'土','Sun'=>'日'];
                $slots = $event ? $event->timeslots->where('is_active', true)->sortBy('start_at') : collect();
                // 予約済み数を日時+venueでカウント
                $reservedCounts = $event
                    ? $event->reservations->where('cancel_flg', false)
                        ->groupBy(function ($r) {
                            $dt = $r->reservation_datetime;
                            $key = is_string($dt) ? $dt : optional($dt)->format('Y-m-d H:i:s');
                            return $key.'|'.($r->venue_id ?? 'null');
                        })
                        ->map->count()
                    : collect();
            @endphp
            <select
                id="lp-{{ $key }}"
                name="{{ $key }}"
                x-model="{{ $xModel }}"
                @if($required) required @endif
                class="lp-field__input lp-field__select"
            >
                <option value="">{{ $placeholder ?: 'ご来店希望日時をお選びください' }}</option>
                @foreach($slots as $slot)
                    @php
                        $startAt = $slot->start_at;
                        $endAt = $startAt->copy()->addHour();
                        $wd = $weekdayMap[$startAt->format('D')] ?? '';
                        $label = $startAt->format('n月j日').'（'.$wd.'）'.$startAt->format('H:i').' 〜 '.$endAt->format('H:i');
                        $countKey = $startAt->format('Y-m-d H:i:s').'|'.($slot->venue_id ?? 'null');
                        $reserved = (int) ($reservedCounts[$countKey] ?? 0);
                        $remaining = max(0, $slot->capacity - $reserved);
                        $isFull = $remaining <= 0;
                    @endphp
                    <option value="{{ $slot->id }}"
                        @if($isFull) disabled @endif
                        @selected((string) $value === (string) $slot->id)>
                        {{ $label }}{{ $isFull ? '【満席】' : '（残'.$remaining.'席）' }}
                    </option>
                @endforeach
            </select>
            @break

        @case('radio')
            <div class="lp-field__choices">
                @foreach($options as $opt)
                    <label class="lp-field__choice">
                        <input type="radio"
                            name="{{ $key }}"
                            value="{{ $opt }}"
                            x-model="{{ $xModel }}"
                            @checked((string) $value === (string) $opt)
                            @if($required) required @endif>
                        <span>{{ $opt }}</span>
                    </label>
                @endforeach
            </div>
            @break

        @case('checkbox')
            @if(!empty($options))
                @php $values = is_array($value) ? array_map('strval', $value) : []; @endphp
                <div class="lp-field__choices">
                    @foreach($options as $opt)
                        <label class="lp-field__choice">
                            <input type="checkbox"
                                name="{{ $key }}[]"
                                value="{{ $opt }}"
                                x-model="{{ $xModel }}"
                                @checked(in_array((string) $opt, $values, true))>
                            <span>{{ $opt }}</span>
                        </label>
                    @endforeach
                </div>
            @else
                <label class="lp-field__choice lp-field__choice--single">
                    <input type="hidden" name="{{ $key }}" value="0">
                    <input type="checkbox"
                        id="lp-{{ $key }}"
                        name="{{ $key }}"
                        value="1"
                        x-model="{{ $xModel }}"
                        @checked((string) $value === '1' || $value === true)
                        @if($required) required @endif>
                    <span>{{ $label }}@if($required)<span class="lp-field__required">必須</span>@endif</span>
                </label>
            @endif
            @break

        @default
            <input type="{{ $type }}"
                id="lp-{{ $key }}"
                name="{{ $key }}"
                value="{{ $value }}"
                x-model="{{ $xModel }}"
                @if($placeholder) placeholder="{{ $placeholder }}" @endif
                @if($required) required @endif
                @if(isset($field['min'])) min="{{ $field['min'] }}" @endif
                @if(isset($field['max'])) max="{{ $field['max'] }}" @endif
                @if(isset($field['pattern'])) pattern="{{ $field['pattern'] }}" @endif
                class="lp-field__input">
    @endswitch

    @if($help)
        <p class="lp-field__help">{{ $help }}</p>
    @endif

    @error($key)
        <p class="lp-field__error">{{ $message }}</p>
    @enderror
    @error($key.'.*')
        <p class="lp-field__error">{{ $message }}</p>
    @enderror
</div>
@endif
