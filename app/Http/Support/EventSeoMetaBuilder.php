<?php

namespace App\Http\Support;

use App\Models\Event;
use App\Support\LpBrand;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Inertia型イベントLPのSEO用サーバー出力（meta/OGP/JSON-LD/概要テキスト）を組み立てる。
 * Vue側は無変更で、クローラー・SNS・AI検索が読む層だけをapp.blade.phpに渡す。
 */
class EventSeoMetaBuilder
{
    public function build(Event $event): array
    {
        $brand = LpBrand::forEvent($event);
        $siteName = $brand['name'];
        $canonical = rtrim(config('app.url'), '/') . '/event/' . $event->slug;

        $venues = $event->venues()->get(['name', 'address', 'phone']);

        $isContact = $event->form_type === 'contact';
        $isStoreReservation = LpBrand::isStoreReservation($event);
        $isEnded = $event->end_at && now()->startOfDay()->gt($event->end_at);

        // 実際の開催日は予約枠から導出（events.start_at/end_atはLP公開期間のため使わない）
        [$eventStart, $eventEnd] = $this->resolveEventDates($event);

        $plainDescription = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $event->description)));
        // 問い合わせ・通年の店舗予約は「開催」ではないので期間を出さない
        $period = ($isContact || $isStoreReservation) ? null : $this->formatPeriod($eventStart, $eventEnd);
        $venueNames = $venues->pluck('name')->filter()->implode('・');

        $description = $plainDescription !== ''
            ? mb_substr($plainDescription, 0, 110) . (mb_strlen($plainDescription) > 110 ? '…' : '')
            : $this->fallbackDescription($event, $siteName, $period, $venueNames, $isContact, $isStoreReservation);

        // og:image：イベントのサムネイル、無ければブランドのロゴ
        $ogImage = $event->thumbnail_url ?: ($brand['logo_url'] ?? null);

        $organizer = [
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => $brand['site_url'],
        ];

        if ($isContact || $isStoreReservation) {
            // 通年の来店予約・問い合わせは「イベント」ではないので Event 構造化データにしない
            // （店舗情報は HP の LocalBusiness と GBP に任せる）
            $jsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $event->title,
                'description' => $description,
                'url' => $canonical,
                'publisher' => $organizer,
            ];
        } else {
            $jsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'Event',
                'name' => $event->title,
                'description' => $description,
                'url' => $canonical,
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'organizer' => $organizer,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'JPY',
                    'availability' => 'https://schema.org/InStock',
                    'url' => $canonical,
                ],
            ];
            if ($eventStart) {
                $jsonLd['startDate'] = $eventStart->toDateString();
            }
            if ($eventEnd) {
                $jsonLd['endDate'] = $eventEnd->toDateString();
            }
            if ($event->thumbnail_url) {
                $jsonLd['image'] = [$event->thumbnail_url];
            }
            $locations = $venues->map(function ($v) {
                $place = ['@type' => 'Place', 'name' => $v->name];
                if ($v->address) {
                    $place['address'] = [
                        '@type' => 'PostalAddress',
                        'streetAddress' => $v->address,
                        'addressCountry' => 'JP',
                    ];
                }
                return $place;
            })->values()->all();
            if (!empty($locations)) {
                $jsonLd['location'] = count($locations) === 1 ? $locations[0] : $locations;
            }
        }

        return [
            'title' => "{$event->title}｜{$siteName}",
            'description' => $description,
            'canonical' => $canonical,
            'ogImage' => $ogImage,
            'siteName' => $siteName,
            // 問い合わせフォーム・終了したイベントは索引に入れない（リンクはたどってよい）
            'robots' => ($isContact || $isEnded) ? 'noindex, follow' : null,
            'jsonLd' => $jsonLd,
            // 概要テキスト層（Vueマウント外に可視出力）
            'summary' => [
                'title' => $event->title,
                'period' => $period,
                'venues' => $venues,
                'description' => $plainDescription !== '' ? mb_substr($plainDescription, 0, 300) : null,
                'organizer' => "{$siteName}（{$brand['description']}）",
                'siteUrl' => $brand['site_url'],
                'isContact' => $isContact,
            ],
        ];
    }

    /**
     * 説明文が未入力のときの description。フォームの種類・店舗に合わせる。
     */
    private function fallbackDescription(Event $event, string $siteName, ?string $period, string $venueNames, bool $isContact, bool $isStoreReservation): string
    {
        if ($isContact) {
            return "{$siteName}へのお問い合わせフォームです。";
        }

        $subject = $event->isHakamaReservation() ? '卒業式の袴' : '振袖';

        if ($isStoreReservation) {
            return "{$event->title}。{$siteName}" . ($venueNames ? "（{$venueNames}）" : '')
                . "の来店予約ページです。{$subject}のご試着・ご相談はWEB来店予約から。";
        }

        if ($event->form_type === 'document') {
            return "{$event->title}。{$siteName}のカタログ・資料のご請求はこちらから。";
        }

        return "{$event->title}" . ($period ? "（{$period}開催）" : '') . ($venueNames ? "。会場: {$venueNames}。" : '。')
            . "{$subject}のご試着・ご相談はWEB来店予約から。";
    }

    /**
     * 開催日範囲。予約枠（event_timeslots）の最小〜最大日を優先し、無ければevents.start_at/end_at。
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveEventDates(Event $event): array
    {
        $range = DB::table('event_timeslots')
            ->where('event_id', $event->id)
            ->selectRaw('MIN(start_at) AS min_start, MAX(start_at) AS max_start')
            ->first();

        if ($range && $range->min_start) {
            return [Carbon::parse($range->min_start)->startOfDay(), Carbon::parse($range->max_start)->startOfDay()];
        }

        return [$event->start_at, $event->end_at];
    }

    private function formatPeriod(?Carbon $start, ?Carbon $end): ?string
    {
        if (!$start) {
            return null;
        }
        $text = $start->format('Y年n月j日');
        if ($end && !$end->isSameDay($start)) {
            $text .= '〜' . $end->format('n月j日');
        }
        return $text;
    }
}
