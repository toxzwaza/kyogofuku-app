<?php

namespace App\Services\NaturalLanguage;

class ToolDefinitions
{
    /**
     * Claude API の tools パラメータに渡す tool 定義一覧を返す
     */
    public static function all(): array
    {
        return [
            self::listEvents(),
            self::getEvent(),
            self::createEvent(),
            self::updateEvent(),
            self::listTimeslots(),
            self::createTimeslots(),
            self::updateTimeslot(),
            self::adjustCapacity(),
            self::deleteTimeslot(),
            self::listReservations(),
            self::getReservation(),
            self::updateReservationStatus(),
        ];
    }

    private static function listEvents(): array
    {
        return [
            'name' => 'list_events',
            'description' => 'イベント一覧を取得する。公開状態や店舗名でフィルタ可能。',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'status' => [
                        'type' => 'string',
                        'enum' => ['公開中', '受付終了', '非公開', 'すべて'],
                        'description' => '公開状態でフィルタ。省略時はすべて。',
                    ],
                    'shop_name' => [
                        'type' => 'string',
                        'description' => '店舗名でフィルタ（部分一致）。例: 岡山店',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    private static function getEvent(): array
    {
        return [
            'name' => 'get_event',
            'description' => 'イベントの詳細情報を取得する。IDまたはタイトルのキーワードで検索。',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'event_id' => [
                        'type' => 'integer',
                        'description' => 'イベントID',
                    ],
                    'title_keyword' => [
                        'type' => 'string',
                        'description' => 'イベントタイトルの部分一致検索キーワード',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    private static function createEvent(): array
    {
        return [
            'name' => 'create_event',
            'description' => '新しいイベントを作成する。',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'イベントタイトル'],
                    'form_type' => [
                        'type' => 'string',
                        'enum' => ['reservation', 'reservation_hakama', 'document', 'contact'],
                        'description' => 'フォーム種別。reservation=振袖予約, reservation_hakama=袴予約, document=資料請求, contact=お問い合わせ',
                    ],
                    'start_at' => ['type' => 'string', 'description' => '開始日（YYYY-MM-DD形式）'],
                    'end_at' => ['type' => 'string', 'description' => '受付終了日（YYYY-MM-DD形式）。省略時は無期限。'],
                    'shop_names' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => '紐づける店舗名の配列。例: ["岡山店", "城東店"]',
                    ],
                    'is_public' => ['type' => 'boolean', 'description' => '公開状態。デフォルトtrue。'],
                    'description' => ['type' => 'string', 'description' => 'イベント説明文'],
                ],
                'required' => ['title', 'form_type'],
            ],
        ];
    }

    private static function updateEvent(): array
    {
        return [
            'name' => 'update_event',
            'description' => '既存イベントを更新する。変更したいフィールドのみ指定。',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'event_id' => ['type' => 'integer', 'description' => 'イベントID（必須）'],
                    'title' => ['type' => 'string', 'description' => '新しいタイトル'],
                    'end_at' => ['type' => 'string', 'description' => '新しい受付終了日（YYYY-MM-DD）。nullで無期限に変更。'],
                    'is_public' => ['type' => 'boolean', 'description' => '公開/非公開'],
                    'description' => ['type' => 'string', 'description' => '新しい説明文'],
                ],
                'required' => ['event_id'],
            ],
        ];
    }

    private static function listTimeslots(): array
    {
        return [
            'name' => 'list_timeslots',
            'description' => '指定イベントの予約枠一覧を取得する。日付や会場でフィルタ可能。',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'event_id' => ['type' => 'integer', 'description' => 'イベントID（必須）'],
                    'date' => ['type' => 'string', 'description' => '日付でフィルタ（YYYY-MM-DD）'],
                    'venue_name' => ['type' => 'string', 'description' => '会場名でフィルタ（部分一致）'],
                ],
                'required' => ['event_id'],
            ],
        ];
    }

    private static function createTimeslots(): array
    {
        return [
            'name' => 'create_timeslots',
            'description' => '予約枠を一括作成する。同じ日に複数の時間帯を一度に追加可能。',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'event_id' => ['type' => 'integer', 'description' => 'イベントID（必須）'],
                    'date' => ['type' => 'string', 'description' => '日付（YYYY-MM-DD形式、必須）'],
                    'times' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => '時刻の配列（HH:MM形式）。例: ["10:00", "11:00", "13:00"]',
                    ],
                    'capacity' => ['type' => 'integer', 'description' => '各枠の定員（必須）'],
                    'venue_name' => ['type' => 'string', 'description' => '会場名。省略時は会場なし。'],
                ],
                'required' => ['event_id', 'date', 'times', 'capacity'],
            ],
        ];
    }

    private static function updateTimeslot(): array
    {
        return [
            'name' => 'update_timeslot',
            'description' => '予約枠を更新する。',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'timeslot_id' => ['type' => 'integer', 'description' => '予約枠ID（必須）'],
                    'capacity' => ['type' => 'integer', 'description' => '新しい定員'],
                    'is_active' => ['type' => 'boolean', 'description' => '有効/無効'],
                ],
                'required' => ['timeslot_id'],
            ],
        ];
    }

    private static function adjustCapacity(): array
    {
        return [
            'name' => 'adjust_capacity',
            'description' => '予約枠の定員を増減する。',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'timeslot_id' => ['type' => 'integer', 'description' => '予約枠ID（必須）'],
                    'adjustment' => ['type' => 'integer', 'description' => '増減数。+3で3人増加、-2で2人減少。'],
                ],
                'required' => ['timeslot_id', 'adjustment'],
            ],
        ];
    }

    private static function deleteTimeslot(): array
    {
        return [
            'name' => 'delete_timeslot',
            'description' => '予約枠を削除する。予約が存在する枠は削除できない。',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'timeslot_id' => ['type' => 'integer', 'description' => '予約枠ID（必須）'],
                ],
                'required' => ['timeslot_id'],
            ],
        ];
    }

    private static function listReservations(): array
    {
        return [
            'name' => 'list_reservations',
            'description' => '予約一覧を取得する。イベントID、ステータス、日付でフィルタ可能。',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'event_id' => ['type' => 'integer', 'description' => 'イベントIDでフィルタ'],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['未対応', '確認中', '返信待ち', '対応完了済み', 'キャンセル済み'],
                        'description' => 'ステータスでフィルタ',
                    ],
                    'date' => ['type' => 'string', 'description' => '予約日でフィルタ（YYYY-MM-DD）'],
                    'limit' => ['type' => 'integer', 'description' => '取得件数上限。デフォルト20。'],
                ],
                'required' => [],
            ],
        ];
    }

    private static function getReservation(): array
    {
        return [
            'name' => 'get_reservation',
            'description' => '予約の詳細情報を取得する。',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'reservation_id' => ['type' => 'integer', 'description' => '予約ID（必須）'],
                ],
                'required' => ['reservation_id'],
            ],
        ];
    }

    private static function updateReservationStatus(): array
    {
        return [
            'name' => 'update_reservation_status',
            'description' => '予約のステータスを変更する。これは書き込み操作です。',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'reservation_id' => ['type' => 'integer', 'description' => '予約ID（必須）'],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['未対応', '確認中', '返信待ち', '対応完了済み', 'キャンセル済み'],
                        'description' => '新しいステータス（必須）',
                    ],
                ],
                'required' => ['reservation_id', 'status'],
            ],
        ];
    }
}
