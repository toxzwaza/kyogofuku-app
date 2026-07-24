<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesLineLiffUser;
use App\Models\CustomerLineContact;
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
            'stage_badge' => $this->stageBadgeDataUri($data['stage']),
            'reward_rate' => $data['reward_rate'],
            'next_stage' => $data['next_stage'],
            'matured_referrals_count' => $data['matured_referrals_count'],
            'balance' => $data['balance'],
            'hirata_balance' => $data['hirata_balance'],
            'gift_card_unit' => $data['gift_card_unit'],
            'ledger' => $data['ledger'],
            'gift_cards' => $data['gift_cards'],
            'coupons' => $data['coupons'],
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

    /**
     * ステージ章バッジを data URI で返す（ngrok等のブラウザ警告で外部画像が表示できない環境でも確実に出すため）。
     * キャッシュして毎回のエンコードを避ける。
     */
    private function stageBadgeDataUri(string $stage): ?string
    {
        $allowed = ['bronze', 'silver', 'gold', 'platinum'];
        if (!in_array($stage, $allowed, true)) {
            $stage = 'bronze';
        }

        return \Illuminate\Support\Facades\Cache::rememberForever("line.stage_badge.$stage", function () use ($stage) {
            $path = public_path("images/line/badges/{$stage}.png");
            if (!is_file($path)) {
                return null;
            }

            return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
        });
    }

    /**
     * LINE連携の解除（顧客本人がマイページから実行）。
     * 既存の管理画面と同様、CustomerLineContact を削除する（メッセージはFKでCASCADE）。
     */
    public function unlink(Request $request)
    {
        $lineUserId = $this->resolveLineUserId($request);
        if (!$lineUserId) {
            return response()->json(['state' => 'unauthorized'], 401);
        }

        $contact = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->first();

        if (!$contact) {
            return response()->json(['state' => 'not_linked'], 200);
        }

        $contact->delete();

        return response()->json(['state' => 'unlinked']);
    }

    private function view(string $screen)
    {
        return response()->view('line.liff-mypage', [
            'liffId' => config('line.liff.referral_id'),
            'screen' => $screen,
        ]);
    }
}
