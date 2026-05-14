<?php

namespace App\Services\BladeLp;

/**
 * events.form_schema (JSON) から Laravel Validator のルールを動的生成する。
 *
 * サポートする field type:
 *  - text / tel / email / textarea / number / url / date / datetime-local / time
 *  - select / radio              ... options 配列必須
 *  - checkbox                    ... options 配列があれば複数選択（配列）
 *  - hidden
 */
class FormSchemaValidator
{
    public const SUPPORTED_TYPES = [
        'text', 'tel', 'email', 'textarea', 'number', 'url',
        'date', 'datetime-local', 'time',
        'select', 'radio', 'checkbox', 'hidden',
        'timeslot',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<string, array<int, mixed>>
     */
    public function rulesFromSchema(array $schema): array
    {
        $rules = [];
        foreach ($schema as $field) {
            if (!is_array($field)) {
                continue;
            }
            $key = $field['key'] ?? null;
            $type = $field['type'] ?? 'text';
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (!in_array($type, self::SUPPORTED_TYPES, true)) {
                continue;
            }

            $required = !empty($field['required']);
            $isMulti = $type === 'checkbox' && !empty($field['options']);

            $fieldRules = [];

            if ($isMulti) {
                $fieldRules[] = $required ? 'required' : 'nullable';
                $fieldRules[] = 'array';
                $rules[$key] = $fieldRules;

                $rules[$key.'.*'] = ['string', 'max:500'];
                if (!empty($field['options']) && is_array($field['options'])) {
                    $rules[$key.'.*'][] = 'in:'.implode(',', array_map('strval', $field['options']));
                }
                continue;
            }

            $fieldRules[] = $required ? 'required' : 'nullable';

            switch ($type) {
                case 'email':
                    $fieldRules[] = 'email';
                    $fieldRules[] = 'max:255';
                    break;
                case 'tel':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:32';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    if (isset($field['min'])) $fieldRules[] = 'min:'.(float) $field['min'];
                    if (isset($field['max'])) $fieldRules[] = 'max:'.(float) $field['max'];
                    break;
                case 'url':
                    $fieldRules[] = 'url';
                    $fieldRules[] = 'max:1000';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'datetime-local':
                    $fieldRules[] = 'date';
                    break;
                case 'time':
                    $fieldRules[] = 'date_format:H:i';
                    break;
                case 'textarea':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:5000';
                    break;
                case 'select':
                case 'radio':
                    // options_from='event_venues' の場合は venue.id (integer) を期待
                    if (($field['options_from'] ?? null) === 'event_venues') {
                        $fieldRules[] = 'integer';
                        $fieldRules[] = 'exists:venues,id';
                        break;
                    }
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
                    // auto_options が指定されている場合は in: を付けない（動的生成のため）
                    if (empty($field['auto_options'])
                        && !empty($field['options']) && is_array($field['options'])) {
                        $fieldRules[] = 'in:'.implode(',', array_map('strval', $field['options']));
                    }
                    break;
                case 'checkbox':
                    // 単独 checkbox（options 無し）
                    if ($required) {
                        // required=true は「チェック必須」として扱う（hidden=0 のすり抜けを防ぐ）
                        $fieldRules = ['accepted'];
                    } else {
                        $fieldRules[] = 'boolean';
                    }
                    break;
                case 'timeslot':
                    $fieldRules[] = 'integer';
                    $fieldRules[] = 'exists:event_timeslots,id';
                    break;
                case 'hidden':
                case 'text':
                default:
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:500';
                    break;
            }

            $rules[$key] = $fieldRules;
        }
        return $rules;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<string, string>
     */
    public function attributesFromSchema(array $schema): array
    {
        $attrs = [];
        foreach ($schema as $field) {
            if (!is_array($field)) continue;
            $key = $field['key'] ?? null;
            $label = $field['label'] ?? null;
            if (is_string($key) && is_string($label) && $label !== '') {
                $attrs[$key] = $label;
            }
        }
        return $attrs;
    }
}
