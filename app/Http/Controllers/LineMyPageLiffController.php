<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesLineLiffUser;
use App\Services\Referral\ReferralSummaryService;
use Illuminate\Http\Request;

/**
 * 友達紹介系の表示専用LIFF（マイステージ / マイポイント / 顧客詳細）。
 * いずれも「顧客登録済み＋LINE連携済み」のユーザーのみ内容を返す。
 */
class LineMyPageLiffController extends Controller
{
    use ResolvesLineLiffUser;

    public function showMyStage()
    {
        return $this->view('my-stage');
    }

    public function showMyPoints()
    {
        return $this->view('my-points');
    }

    public function showMyPage()
    {
        return $this->view('mypage');
    }

    /**
     * マイステージ＋マイポイントのデータ（残高・ステージ・履歴・ギフト）
     */
    public function points(Request $request, ReferralSummaryService $summary)
    {
        $customer = $this->resolveCustomerByLineUserId($this->resolveLineUserId($request));
        if (!$customer) {
            return response()->json(['state' => 'not_linked'], 403);
        }

        $data = $summary->forCustomer($customer);

        return response()->json([
            'state' => 'ok',
            'stage' => $data['stage'],
            'matured_referrals_count' => $data['matured_referrals_count'],
            'balance' => $data['balance'],
            'gift_card_unit' => $data['gift_card_unit'],
            'ledger' => $data['ledger'],
            'gift_cards' => $data['gift_cards'],
            'referrals_made' => $data['referrals_made'],
        ]);
    }

    /**
     * 顧客詳細（マイページ）：顧客情報・成約・前撮り日（表示専用）
     */
    public function mypage(Request $request)
    {
        $customer = $this->resolveCustomerByLineUserId($this->resolveLineUserId($request));
        if (!$customer) {
            return response()->json(['state' => 'not_linked'], 403);
        }

        $customer->load([
            'contracts' => fn ($q) => $q->where('status', '確定')->orderByDesc('contract_date'),
            'contracts.plan:id,name',
            'photoSlots:id,customer_id,shoot_date,shoot_time',
        ]);

        return response()->json([
            'state' => 'ok',
            'customer' => [
                'name' => $customer->name,
                'kana' => $customer->kana,
                'phone_number' => $customer->phone_number,
            ],
            'contracts' => $customer->contracts->map(fn ($c) => [
                // contract_date は date キャスト未設定で文字列のことがあるため安全に整形
                'contract_date' => $c->contract_date ? \Illuminate\Support\Carbon::parse($c->contract_date)->format('Y-m-d') : null,
                'plan' => $c->plan?->name,
                'kimono_type' => $c->kimono_type,
                'total_amount' => $c->total_amount,
            ]),
            'photo_slots' => $customer->photoSlots->map(fn ($p) => [
                'shoot_date' => $p->shoot_date,
                'shoot_time' => $p->shoot_time,
            ]),
        ]);
    }

    private function view(string $screen)
    {
        return response()->view('line.liff-mypage', [
            'liffId' => config('line.liff.referral_id'),
            'screen' => $screen,
        ]);
    }
}
