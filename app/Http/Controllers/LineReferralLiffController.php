<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesLineLiffUser;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralSetting;
use App\Services\Line\LineMessagingService;
use App\Services\Referral\ReferralLinkingService;
use App\Services\Referral\ReferralShopEventsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineReferralLiffController extends Controller
{
    use ResolvesLineLiffUser;

    /**
     * 紹介LIFF画面（被紹介者が ?ref=XXXX で踏む / 紹介者が自分のURL取得）
     */
    public function show(Request $request)
    {
        return response()->view('line.liff-referral', [
            'liffId' => config('line.liff.referral_id'),
            'ref' => (string) $request->query('ref', ''),
            'addFriendUrl' => config('line.line_official_add_friend_url'),
        ]);
    }

    /**
     * 紹介者の自分の紹介コード・共有URLを返す（リッチメニュー「友達紹介」＝ref無しで開いたとき）。
     * 紹介できるのは成約済（status='確定'のcontractがある）顧客のみ。既にコードがあれば有資格。
     * 有資格ならコードを発行（firstOrCreate）して返す。
     */
    public function me(Request $request, ReferralShopEventsService $shopEvents)
    {
        $lineUserId = $this->resolveLineUserId($request);
        if (!$lineUserId) {
            return response()->json(['state' => 'unauthorized'], 401);
        }

        // 誰から紹介されたか（顧客登録・成約の有無に関わらず常に返す）
        $referrer = $this->resolveReferrerInfo($lineUserId);

        $customer = $this->resolveCustomerByLineUserId($lineUserId);
        if (!$customer) {
            // イベント予約経由の連携（customer_id なし）は連携済みだが顧客未紐付け → 成約案内を出す
            if ($this->hasLineContact($lineUserId)) {
                return response()->json(['state' => 'not_contracted', 'referrer' => $referrer], 403);
            }

            return response()->json([
                'state' => 'not_linked',
                'referrer' => $referrer,
                'events' => $shopEvents->forReferredLineUserId($lineUserId),
            ], 403);
        }

        $hasCode = $customer->referralCode()->exists();
        $isContracted = $customer->contracts()->where('status', '確定')->exists();
        if (!$hasCode && !$isContracted) {
            return response()->json(['state' => 'not_eligible', 'referrer' => $referrer]);
        }

        $code = ReferralCode::firstOrCreate(
            ['customer_id' => $customer->id],
            ['code' => ReferralCode::generateUniqueCode()],
        );

        $base = (string) config('line.referral.liff_url');
        $shareUrl = $base !== '' ? $base.'?ref='.$code->code : '';

        return response()->json([
            'state' => 'ok',
            'code' => $code->code,
            'share_url' => $shareUrl,
            'referrer' => $referrer,
        ]);
    }

    /**
     * 被紹介者のLINE user_id から「誰に紹介されたか」を解決する。
     * 顧客登録・成約後も referred_line_user_id で引けるため、常に紹介者を返せる。
     * 無効な紹介（rejected/expired）は除外。紹介がなければ null。
     *
     * @return array{id:int, name:string}|null
     */
    private function resolveReferrerInfo(string $lineUserId): ?array
    {
        $referral = Referral::query()
            ->where('referred_line_user_id', $lineUserId)
            ->whereIn('status', [
                Referral::STATUS_LINKED,
                Referral::STATUS_CONTRACTED,
                Referral::STATUS_MATURED,
            ])
            ->with('referrer:id,name')
            ->latest('id')
            ->first();

        return $referral?->referrer
            ? ['id' => $referral->referrer->id, 'name' => $referral->referrer->name]
            : null;
    }

    /**
     * 状態確認：ref と LINE紐付け状況から、紐付け可能かを返す。
     */
    public function check(Request $request)
    {
        $lineUserId = $this->resolveLineUserId($request);
        if (!$lineUserId) {
            return response()->json(['state' => 'unauthorized'], 401);
        }

        $ref = (string) $request->input('ref', '');
        if ($ref === '') {
            return response()->json(['state' => 'no_ref']);
        }

        return response()->json(['state' => 'ready']);
    }

    /**
     * 紐付け実行：referrals を作成し、被紹介者へお礼テキストをPush。
     */
    public function link(Request $request, ReferralLinkingService $linking, LineMessagingService $messaging)
    {
        $lineUserId = $this->resolveLineUserId($request);
        if (!$lineUserId) {
            return response()->json(['state' => 'unauthorized'], 401);
        }

        $ref = (string) $request->input('ref', '');
        $result = $linking->link($ref, $lineUserId);

        // 成立時のみお礼テキスト（誰から紹介されたか＝紹介者の顧客ID・氏名を明示）
        if ($result['status'] === Referral::STATUS_LINKED) {
            $referrer = $result['referral']?->referrer;
            if ($referrer) {
                $text = "お友達登録ありがとうございます！\n[{$referrer->id}]{$referrer->name}さんから友達紹介されました！";
            } else {
                $text = ReferralSetting::get('referral_message_template')
                    ?: "ご登録ありがとうございます。\nご成約後、紹介特典ポイントを進呈いたします。";
            }
            try {
                $messaging->pushTextToUser($lineUserId, $text);
            } catch (\Throwable $e) {
                Log::warning('referral link push failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'state' => $result['status'],
            'reason' => $result['reason'],
        ]);
    }
}
