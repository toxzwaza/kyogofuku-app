<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesLineLiffUser;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralSetting;
use App\Services\Line\LineMessagingService;
use App\Services\Referral\ReferralLinkingService;
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
    public function me(Request $request)
    {
        $lineUserId = $this->resolveLineUserId($request);
        if (!$lineUserId) {
            return response()->json(['state' => 'unauthorized'], 401);
        }

        $customer = $this->resolveCustomerByLineUserId($lineUserId);
        if (!$customer) {
            return response()->json(['state' => 'not_linked'], 403);
        }

        $hasCode = $customer->referralCode()->exists();
        $isContracted = $customer->contracts()->where('status', '確定')->exists();
        if (!$hasCode && !$isContracted) {
            return response()->json(['state' => 'not_eligible']);
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
        ]);
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

        // 成立時のみお礼テキスト（特典は成約後にポイント付与する旨）
        if ($result['status'] === Referral::STATUS_LINKED) {
            $text = ReferralSetting::get('referral_message_template')
                ?: "ご登録ありがとうございます。\nご成約後、紹介特典ポイントを進呈いたします。";
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
