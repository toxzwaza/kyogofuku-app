@props(['field', 'old' => []])

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

@if($type === 'hidden')
    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
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
                @if($required) required @endif
                class="lp-field__input lp-field__select"
            >
                <option value="">{{ $placeholder ?: '選択してください' }}</option>
                @foreach($options as $opt)
                    <option value="{{ $opt }}" @selected((string) $value === (string) $opt)>{{ $opt }}</option>
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
