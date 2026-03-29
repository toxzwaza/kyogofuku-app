<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineLinkToken;
use App\Models\CustomerLineMessage;
use App\Services\Line\LineMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerLineMessageController extends Controller
{
    public function issueLinkToken(Request $request, Customer $customer)
    {
        if (! $customer->shop_id) {
            return redirect()->back()->withErrors(['line' => '担当店舗が未設定のため、LINE 連携リンクを発行できません。']);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:50',
        ]);

        $liffId = config('line.liff.id');
        $loginChannelId = config('line.liff.login_channel_id');
        if (empty($liffId) || empty($loginChannelId)) {
            return redirect()->back()->withErrors([
                'line' => 'LINE 連携用の環境変数が未設定です。LIFF ID（LINE_LIFF_ID または LINE_MESSAGING_LIFF_ID）と、LINEログインチャネルの Channel ID（LINE_LOGIN_CHANNEL_ID）を .env に設定してください。',
            ]);
        }

        $label = mb_substr(trim($validated['label']), 0, 50);
        if ($label === '') {
            return redirect()->back()->withErrors(['line' => '連携後の表示名（本人・母など）を入力してください。']);
        }

        $token = CustomerLineLinkToken::generateToken();
        CustomerLineLinkToken::query()->create([
            'token' => $token,
            'customer_id' => $customer->id,
            'shop_id' => $customer->shop_id,
            'suggested_label' => $label,
            'expires_at' => now()->addDays(7),
            'created_by_user_id' => $request->user()?->id,
        ]);

        // 自ドメインのパスにトークンを載せる（liff.line.me?link_token= はクエリが消えるクライアントがある）
        $base = rtrim((string) config('app.url'), '/');
        $liffUrl = $base.'/line/liff/link/'.rawurlencode($token);

        return redirect()->back()
            ->with('success', 'LINE 連携用リンクを発行しました。お客様に共有してください。')
            ->with('line_liff_link_url', $liffUrl);
    }

    public function contactMessages(Customer $customer, CustomerLineContact $contact)
    {
        $this->assertContactBelongsToCustomer($customer, $contact);

        $collection = $contact->messages()
            ->with('sentByUser:id,name')
            ->orderBy('id')
            ->limit(500)
            ->get();

        $markedReadAt = now();
        CustomerLineMessage::query()
            ->where('customer_line_contact_id', $contact->id)
            ->where('direction', CustomerLineMessage::DIRECTION_INBOUND)
            ->whereNull('admin_read_at')
            ->update(['admin_read_at' => $markedReadAt]);

        $messages = $collection->map(function (CustomerLineMessage $m) use ($markedReadAt) {
            $readAt = $m->admin_read_at;
            if ($m->direction === CustomerLineMessage::DIRECTION_INBOUND && $readAt === null) {
                $readAt = $markedReadAt;
            }

            return [
                'id' => $m->id,
                'direction' => $m->direction,
                'message_type' => $m->message_type,
                'text' => $m->text,
                'created_at' => $m->created_at?->toIso8601String(),
                'sent_by' => $m->sentByUser?->name,
                'admin_read_at' => $readAt?->toIso8601String(),
            ];
        });

        return response()->json(['messages' => $messages]);
    }

    public function send(Request $request, Customer $customer, CustomerLineContact $contact, LineMessagingService $messaging)
    {
        $this->assertContactBelongsToCustomer($customer, $contact);

        $validated = $request->validate([
            'text' => 'required|string|max:4500',
        ]);

        if ((int) $customer->shop_id !== (int) $contact->shop_id) {
            return response()->json(['message' => '顧客の担当店舗と連絡先の店舗が一致しません。'], 422);
        }

        try {
            $messaging->pushTextToUser($contact->line_user_id, $validated['text']);
        } catch (\Throwable $e) {
            Log::error('LINE send from admin failed', ['e' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 502);
        }

        $message = CustomerLineMessage::query()->create([
            'customer_line_contact_id' => $contact->id,
            'direction' => CustomerLineMessage::DIRECTION_OUTBOUND,
            'message_type' => 'text',
            'text' => $validated['text'],
            'line_message_id' => null,
            'payload' => null,
            'sent_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'direction' => $message->direction,
                'message_type' => $message->message_type,
                'text' => $message->text,
                'created_at' => $message->created_at?->toIso8601String(),
                'sent_by' => $request->user()?->name,
            ],
        ]);
    }

    public function destroyContact(Request $request, Customer $customer, CustomerLineContact $contact)
    {
        $this->assertContactBelongsToCustomer($customer, $contact);

        Log::info('admin LINE contact unlinked (customer)', [
            'user_id' => $request->user()?->id,
            'customer_id' => $customer->id,
            'contact_id' => $contact->id,
        ]);

        $contact->delete();

        return redirect()->back()->with('success', 'LINE 連携を解除しました。同じ LINE アカウントを別の顧客や予約に紐づけられます。');
    }

    private function assertContactBelongsToCustomer(Customer $customer, CustomerLineContact $contact): void
    {
        if ($contact->customer_id === null || (int) $contact->customer_id !== (int) $customer->id) {
            abort(404);
        }
    }
}
