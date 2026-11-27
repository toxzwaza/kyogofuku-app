<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * LP 表示時の utm を session に覚え、予約保存時に取り出す。
 *
 * - utm_source 付きで来たときだけ上書きする（utm なしで開き直しても、前に覚えた流入元を消さない）
 * - 何も覚えていないときは従来どおり utm_source = NONE を入れる
 * - utm_medium / utm_campaign も一緒に覚える（予約に保存して GBP・HP・広告を見分ける）
 */
class UtmTracker
{
    public const NONE = 'NONE';

    private const KEYS = ['utm_source', 'utm_medium', 'utm_campaign'];

    public static function capture(Request $request, int $eventId): void
    {
        $source = self::clean($request->query('utm_source', $request->input('utm_source')));

        if ($source !== null) {
            $request->session()->put(self::sessionKey($eventId), [
                'utm_source' => $source,
                'utm_medium' => self::clean($request->query('utm_medium', $request->input('utm_medium'))),
                'utm_campaign' => self::clean($request->query('utm_campaign', $request->input('utm_campaign'))),
            ]);
            // 旧キー（既存の読み取り箇所との互換）
            $request->session()->put(self::legacyKey($eventId), $source);

            return;
        }

        if (!$request->session()->has(self::legacyKey($eventId))) {
            $request->session()->put(self::legacyKey($eventId), self::NONE);
        }
    }

    /**
     * 予約に保存する utm。フォームから utm_source が送られてきた場合はそれを優先する。
     *
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string}
     */
    public static function forReservation(Request $request, int $eventId): array
    {
        $stored = (array) $request->session()->get(self::sessionKey($eventId), []);
        $inputSource = self::clean($request->input('utm_source'));

        if ($inputSource !== null && $inputSource !== ($stored['utm_source'] ?? null)) {
            return [
                'utm_source' => $inputSource,
                'utm_medium' => self::clean($request->input('utm_medium')),
                'utm_campaign' => self::clean($request->input('utm_campaign')),
            ];
        }

        return [
            'utm_source' => $inputSource ?? $request->session()->get(self::legacyKey($eventId)),
            'utm_medium' => $stored['utm_medium'] ?? null,
            'utm_campaign' => $stored['utm_campaign'] ?? null,
        ];
    }

    public static function sessionKey(int $eventId): string
    {
        return 'event_utm.'.$eventId;
    }

    public static function legacyKey(int $eventId): string
    {
        return 'event_utm_sources.'.$eventId;
    }

    private static function clean($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }
}
