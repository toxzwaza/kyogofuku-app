<?php

namespace App\Services;

use App\Models\CareSuggestion;
use App\Models\EventReservation;
use App\Models\Event;
use App\Models\ReservationNote;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ケア提案の生成（第1段：ルールベース・AIなし）。
 *
 * ケアスコア = 成約見込み(0-40) + 放置度(0-40) + 緊急度(0-20)
 *  - 成約見込み：流入元の濃さ・検討プラン・リードタイム（成約要因分析の勝ちパターン）
 *  - 放置度：最終接触からの経過日数・未対応
 *  - 緊急度：お客様を待たせている状態
 *
 * 除外：浜店/テスト店/京呉服好一（別ブランド）・キャンセル・対応完了済み。
 */
class CareSuggestionGenerator
{
    /** 別ブランド・対象外イベントを除外するためのタイトルキーワード */
    private const EXCLUDE_EVENT_KEYWORDS = ['好一', '浜店'];

    /** 表示対象外の店舗ID（浜店=3, テスト店=5） */
    private const EXCLUDE_SHOP_IDS = [3, 5];

    /**
     * ケアの対象とする「予約からの経過日数」上限。
     * 成約者の97%は予約から30日以内に決まる（成約要因分析）。
     * これを過ぎた未対応は"旬を過ぎたリード/対応済み放置"としてケア対象から外す。
     */
    private const TARGET_WINDOW_DAYS = 45;

    public function generate(): int
    {
        $now = Carbon::now();
        CareSuggestion::truncate();

        $excludeEventIds = $this->excludedEventIds();
        $noteLatest = $this->latestReservationNoteMap();
        $eventShopMap = $this->eventPrimaryShopMap();

        $count = 0;

        EventReservation::query()
            ->where('cancel_flg', 0)
            ->whereNotIn('status', ['対応完了済み'])
            // 既に確定成約済みの顧客に紐づく予約は除外（ステータスが未対応のままでも成約済みのケースがあるため、
            // ステータスではなく成約実績で判定する）。customer_id が未紐づけの予約は成約判定できないため対象に残す。
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('contracts')
                    ->whereColumn('contracts.customer_id', 'event_reservations.customer_id')
                    ->where('contracts.status', '確定')
                    ->whereNull('contracts.deleted_at');
            })
            ->where('created_at', '>=', $now->copy()->subDays(self::TARGET_WINDOW_DAYS))
            ->when($excludeEventIds->isNotEmpty(), fn ($q) => $q->whereNotIn('event_id', $excludeEventIds))
            ->orderBy('id')
            ->chunk(300, function ($reservations) use ($now, $noteLatest, $eventShopMap, &$count) {
                foreach ($reservations as $r) {
                    $row = $this->scoreReservation($r, $now, $noteLatest, $eventShopMap);
                    if ($row === null) {
                        continue;
                    }
                    CareSuggestion::create($row);
                    $count++;
                }
            });

        // 維持ケア（成約後のキャンセル防止フォロー）
        $count += $this->generateRetention($now);

        return $count;
    }

    /**
     * 維持ケア（retention）：確定成約済み×成人式がこれからの顧客を対象に、
     * キャンセルリスク = 前撮り状況 + 成約からの経過（キャンセル料が低い時期ほど離脱容易） + 最終接触からの経過
     * でスコアリングし、要フォロー（40点以上）のみ提案を作る。
     */
    private function generateRetention(Carbon $now): int
    {
        $year = (int) $now->year;

        // 対象顧客を集約（浜店3/テスト5は shop_id で除外＝1,2,4のみ）
        $customers = DB::table('customers as cu')
            ->join('contracts as c', function ($j) {
                $j->on('c.customer_id', '=', 'cu.id')
                    ->where('c.status', '確定')
                    ->whereNull('c.deleted_at');
            })
            ->where('cu.coming_of_age_year', '>=', $year)
            ->whereIn('cu.shop_id', [1, 2, 4])
            ->groupBy('cu.id', 'cu.name', 'cu.shop_id', 'cu.staff_name', 'cu.coming_of_age_year')
            ->selectRaw('cu.id, cu.name, cu.shop_id, cu.staff_name, cu.coming_of_age_year,
                MAX(c.contract_date) as last_contract_date,
                MAX(c.preparation_date) as prep_date,
                MAX(c.created_at) as contract_created')
            ->get();

        // 顧客メモの最終日時（最終接触の一材料）
        $noteLatest = DB::table('customer_notes')
            ->selectRaw('customer_id, MAX(created_at) as latest')
            ->groupBy('customer_id')
            ->pluck('latest', 'customer_id');

        $count = 0;
        foreach ($customers as $cu) {
            $row = $this->scoreRetention($cu, $now, $noteLatest);
            if ($row === null) {
                continue;
            }
            CareSuggestion::create($row);
            $count++;
        }

        return $count;
    }

    private function scoreRetention($cu, Carbon $now, $noteLatest): ?array
    {
        // 前撮りフェーズ（前撮りが済むとキャンセルされにくい）
        if (empty($cu->prep_date)) {
            $prepPhase = '前撮り未設定';
            $prepScore = 40;
        } elseif ($cu->prep_date >= $now->toDateString()) {
            $prepPhase = '前撮り予定';
            $prepScore = 25;
        } else {
            $prepPhase = '前撮り済み';
            $prepScore = 5;
        }

        // 成約からの経過（クーリングオフ〜低キャンセル料期ほど離脱容易＝リスク高）
        $contractDate = $cu->last_contract_date ? Carbon::parse($cu->last_contract_date) : null;
        $daysSinceContract = $contractDate ? $contractDate->diffInDays($now) : 999;
        $contractScore = match (true) {
            $daysSinceContract <= 8 => 30,
            $daysSinceContract <= 30 => 25,
            $daysSinceContract <= 150 => 15,
            default => 5,
        };

        // 最終接触からの経過（音信不通ほど関係希薄＝リスク高）
        $lastContact = null;
        foreach ([$noteLatest[$cu->id] ?? null, $cu->contract_created ?? null] as $d) {
            if ($d) {
                $dt = Carbon::parse($d);
                if (! $lastContact || $dt->gt($lastContact)) {
                    $lastContact = $dt;
                }
            }
        }
        $daysSinceTouch = $lastContact ? $lastContact->diffInDays($now) : 999;
        $touchScore = match (true) {
            $daysSinceTouch <= 7 => 0,
            $daysSinceTouch <= 30 => 10,
            $daysSinceTouch <= 90 => 20,
            default => 30,
        };

        $score = min(100, $prepScore + $contractScore + $touchScore);
        if ($score < 40) {
            return null; // 要フォロー（中以上）のみ提案化
        }

        [$action, $type] = $this->retentionAction($prepPhase, $daysSinceContract, $daysSinceTouch);

        return [
            'care_type' => 'retention',
            'subject_type' => 'customer',
            'event_reservation_id' => null,
            'customer_id' => $cu->id,
            'shop_id' => $cu->shop_id,
            'assignee' => $cu->staff_name,
            'subject_name' => $cu->name,
            'care_score' => $score,
            'priority_band' => $this->band($score),
            'score_factors' => [
                'prep' => $prepScore,
                'contract' => $contractScore,
                'touch' => $touchScore,
                'prep_phase' => $prepPhase,
                'days_since_contract' => $daysSinceContract,
                'days_since_touch' => $daysSinceTouch,
            ],
            'reservation_status' => $prepPhase,
            'days_since_contact' => $daysSinceTouch,
            'prospect_label' => null,
            'seijin_year' => $cu->coming_of_age_year,
            'status_summary' => "{$prepPhase}・最終接触{$daysSinceTouch}日前",
            'next_action' => $action,
            'next_action_type' => $type,
            'last_contact_at' => $lastContact,
            'ai_generated' => false,
            'generated_at' => $now,
        ];
    }

    /** 維持ケアの推奨対応（節目に応じて）。[text, type] を返す */
    private function retentionAction(string $prepPhase, int $daysSinceContract, int $daysSinceTouch): array
    {
        if ($daysSinceContract <= 8) {
            return ['成約直後のお礼・安心のご連絡', 'thanks'];
        }
        if ($prepPhase === '前撮り未設定') {
            return ['前撮り日程のご案内', 'photo'];
        }
        if ($prepPhase === '前撮り予定') {
            return ['前撮り前の確認連絡', 'prep'];
        }
        if ($daysSinceTouch >= 60) {
            return ['近況伺いのご連絡', 'followup'];
        }

        return ['フォローのご連絡', 'followup'];
    }

    private function scoreReservation(EventReservation $r, Carbon $now, $noteLatest, $eventShopMap): ?array
    {
        // --- 最終接触日時・経過日数 ---
        $lastContact = $noteLatest[$r->id] ?? $r->updated_at ?? $r->created_at;
        $lastContact = $lastContact ? Carbon::parse($lastContact) : null;
        $daysSince = $lastContact ? $lastContact->diffInDays($now) : 999;

        // --- 成約見込み(0-40) ---
        [$prospect, $utmLabel] = $this->prospectScore($r);

        // --- 放置度(0-40) ---
        $neglect = $this->neglectScore($daysSince, $r->status);

        // --- 緊急度(0-20) ---
        $urgency = $this->urgencyScore($r->status, $daysSince);

        $score = min(100, $prospect + $neglect + $urgency);
        $band = $this->band($score);

        [$action, $actionType] = $this->nextAction($r);

        return [
            'care_type' => 'acquisition',
            'subject_type' => 'reservation',
            'event_reservation_id' => $r->id,
            'customer_id' => $r->customer_id,
            'shop_id' => $eventShopMap[$r->event_id] ?? null,
            'assignee' => $r->admin_assignee ?: $r->staff_name,
            'subject_name' => $r->name,
            'care_score' => $score,
            'priority_band' => $band,
            'score_factors' => [
                'prospect' => $prospect,
                'neglect' => $neglect,
                'urgency' => $urgency,
                'utm' => $utmLabel,
                'days_since_contact' => $daysSince,
                'status' => $r->status,
            ],
            'reservation_status' => $r->status,
            'days_since_contact' => $daysSince,
            'prospect_label' => $utmLabel,
            'seijin_year' => $r->seijin_year,
            'status_summary' => $this->statusSummary($r, $utmLabel, $daysSince),
            'next_action' => $action,
            'next_action_type' => $actionType,
            'last_contact_at' => $lastContact,
            'ai_generated' => false,
            'generated_at' => $now,
        ];
    }

    /** 成約見込みスコア（流入元・検討プラン・リードタイム）。[score, utmLabel] を返す */
    private function prospectScore(EventReservation $r): array
    {
        $score = 0;
        $utm = $r->utm_source;

        // 流入元の濃さ（成約要因分析の顧客化率にもとづく）
        $hot = ['HP_TOP', 'HP_TOP_PICKUP', 'AJ', 'DM'];
        $ad = ['BF_facebook', 'BF_google'];
        if (in_array($utm, $hot, true)) {
            $score += 15;
            $utmLabel = '濃いリード';
        } elseif (in_array($utm, $ad, true) || str_starts_with((string) $utm, 'cf_utm_source')) {
            $score += 10;
            $utmLabel = '広告経由';
        } elseif ($utm === null || $utm === '' || $utm === 'NONE') {
            $score += 4;
            $utmLabel = '流入元不明';
        } else {
            $score += 6;
            $utmLabel = 'その他経由';
        }

        // 検討プラン（第1希望）
        $plan = $this->firstJsonValue($r->considering_plans);
        if ($plan !== null) {
            if (str_contains($plan, '振袖')) {
                $score += 10;
            } elseif (str_contains($plan, 'フォト') || str_contains($plan, 'ママ振')) {
                $score += 8;
            } elseif (str_contains($plan, '上下フルセット') || str_contains($plan, '袴')) {
                $score += 0; // 自店の主力外
            } else {
                $score += 5;
            }
        } else {
            $score += 5;
        }

        // リードタイム（成人式まで）
        if ($r->seijin_year) {
            $yrs = (int) $r->seijin_year - (int) now()->year;
            if ($yrs === 2 || $yrs === 3) {
                $score += 15;
            } elseif ($yrs === 1) {
                $score += 10;
            } elseif ($yrs >= 0 && $yrs <= 4) {
                $score += 5;
            } else {
                $score += 2;
            }
        } else {
            $score += 3;
        }

        return [min(40, $score), $utmLabel];
    }

    /**
     * 放置度(0-40)。早期対応が成約に直結する（成約者の97%が30日以内）ため、
     * 放置2-3日を「今すぐケアすべきゴールデンタイム」として最高点にし、
     * 15日以上は"旬を逸した/対応済み未更新の疑い"として下げる（山型カーブ）。
     */
    private function neglectScore(int $daysSince, ?string $status): int
    {
        return match (true) {
            $daysSince <= 1 => 5,   // まだ猶予がある
            $daysSince <= 3 => 40,  // ゴールデンタイム（最優先）
            $daysSince <= 7 => 30,  // まだ十分間に合う
            $daysSince <= 14 => 18, // 遅れ気味
            default => 8,           // 旬を逸した/対応済み未更新の疑い
        };
    }

    private function urgencyScore(?string $status, int $daysSince): int
    {
        // お客様を待たせている状態を重視（返信待ち）＋ゴールデンタイムの未対応
        if ($status === '返信待ち') {
            return 15;
        }
        if ($status === '未対応' && $daysSince >= 2 && $daysSince <= 7) {
            return 10;
        }

        return 0;
    }

    private function band(int $score): string
    {
        return match (true) {
            $score >= 80 => '緊急',
            $score >= 60 => '高',
            $score >= 40 => '中',
            default => '低',
        };
    }

    /** 推奨対応（簡潔な文）。ステータス・経過日数・見込みは別フィールドで持つ。[text, type] を返す */
    private function nextAction(EventReservation $r): array
    {
        return match ($r->status) {
            '返信待ち' => ['状況確認のご連絡', 'reply'],
            '確認中' => ['次のご連絡・来店案内', 'follow'],
            default => ['初回対応（LINE／電話）', 'reply'],
        };
    }

    private function statusSummary(EventReservation $r, string $utmLabel, int $daysSince): string
    {
        $parts = [$utmLabel];
        if ($r->seijin_year) {
            $parts[] = "{$r->seijin_year}年成人式";
        }
        $parts[] = "{$r->status}・{$daysSince}日経過";

        return implode('・', $parts);
    }

    // ---- helpers ----

    private function firstJsonValue($raw): ?string
    {
        if (empty($raw)) {
            return null;
        }
        $arr = is_array($raw) ? $raw : json_decode($raw, true);
        if (is_array($arr) && !empty($arr)) {
            return (string) $arr[0];
        }

        return null;
    }

    private function excludedEventIds()
    {
        return Event::query()
            ->where(function ($q) {
                foreach (self::EXCLUDE_EVENT_KEYWORDS as $kw) {
                    $q->orWhere('title', 'like', "%{$kw}%");
                }
            })
            ->pluck('id');
    }

    private function latestReservationNoteMap()
    {
        return ReservationNote::query()
            ->selectRaw('event_reservation_id, MAX(created_at) as latest')
            ->groupBy('event_reservation_id')
            ->pluck('latest', 'event_reservation_id');
    }

    /** event_id => 代表shop_id（浜店/テスト除外） */
    private function eventPrimaryShopMap()
    {
        return DB::table('event_shop')
            ->whereNotIn('shop_id', self::EXCLUDE_SHOP_IDS)
            ->selectRaw('event_id, MIN(shop_id) as shop_id')
            ->groupBy('event_id')
            ->pluck('shop_id', 'event_id');
    }
}
