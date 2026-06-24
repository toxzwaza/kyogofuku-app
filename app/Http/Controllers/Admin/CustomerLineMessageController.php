<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineLinkToken;
use App\Models\CustomerLineMessage;
use App\Services\Line\LineMessageMediaStore;
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
            ->with(['sentByUser:id,name', 'mediaFile'])
            ->orderBy('id')
            ->limit(500)
            ->get();

        // メッセージ取得では既読化しない（既読化は markRead で明示的に行う）
        $messages = $collection->map(function (CustomerLineMessage $m) {
            return [
                'id' => $m->id,
                'direction' => $m->direction,
                'message_type' => $m->message_type,
                'text' => $m->text,
                'image_url' => $m->mediaFile?->url,
                'created_at' => $m->created_at?->toIso8601String(),
                'sent_by' => $m->sentByUser?->name,
                'admin_read_at' => $m->admin_read_at?->toIso8601String(),
            ];
        });

        return response()->json(['messages' => $messages]);
    }

    /**
     * スレッドの未読（受信）メッセージを既読にする
     */
    public function markRead(Customer $customer, CustomerLineContact $contact)
    {
        $this->assertContactBelongsToCustomer($customer, $contact);

        $markedReadAt = now();
        $updated = CustomerLineMessage::query()
            ->where('customer_line_contact_id', $contact->id)
            ->where('direction', CustomerLineMessage::DIRECTION_INBOUND)
            ->whereNull('admin_read_at')
            ->update(['admin_read_at' => $markedReadAt]);

        return response()->json([
            'updated' => $updated,
            'admin_read_at' => $markedReadAt->toIso8601String(),
        ]);
    }

    public function send(
        Request $request,
        Customer $customer,
        CustomerLineContact $contact,
        LineMessagingService $messaging,
        LineMessageMediaStore $mediaStore,
    ) {
        $this->assertContactBelongsToCustomer($customer, $contact);

        if ((int) $customer->shop_id !== (int) $contact->shop_id) {
            return response()->json(['message' => '顧客の担当店舗と連絡先の店舗が一致しません。'], 422);
        }

        $imageEnabled = (bool) config('line.image_messaging.enabled', true);
        $maxBytes = (int) config('line.image_messaging.max_size_bytes', 1024 * 1024);
        $hasImage = $imageEnabled && $request->hasFile('image_file');

        // text と image の少なくともどちらかが必要
        $rules = [
            'text' => ['nullable', 'string', 'max:4500'],
        ];
        if ($imageEnabled) {
            $rules['image_file'] = [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,jpg,png',
                'max:'.max(1, intdiv($maxBytes, 1024)),
            ];
        }
        $validated = $request->validate($rules);

        if (! $hasImage && empty($validated['text'])) {
            return response()->json(['message' => 'メッセージ本文か画像のどちらかを指定してください。'], 422);
        }

        // ---- 画像送信 ----
        if ($hasImage) {
            try {
                $mediaFile = $mediaStore->storeOutboundImage($request->file('image_file'));
            } catch (\Throwable $e) {
                Log::channel('line_image')->error('[admin-send] media store failed', [
                    'customer_id' => $customer->id,
                    'contact_id' => $contact->id,
                    'exception' => $e::class.': '.$e->getMessage(),
                ]);

                return response()->json(['message' => '画像の保存に失敗しました: '.$e->getMessage()], 500);
            }

            $imageUrl = $mediaFile->url;
            // 本番では LINE 側仕様で HTTPS 必須。テスト環境は Storage::fake() で http URL になるためスキップ。
            if (! app()->environment('testing')
                && (! $imageUrl || ! str_starts_with($imageUrl, 'https://'))
            ) {
                Log::channel('line_image')->error('[admin-send] image url is not https', [
                    'media_file_id' => $mediaFile->id,
                    'url' => $imageUrl,
                ]);

                return response()->json(['message' => '画像 URL が HTTPS ではありません（S3 設定要確認）。'], 500);
            }

            try {
                $messaging->pushImageToUser($contact->line_user_id, $imageUrl, $imageUrl);
            } catch (\Throwable $e) {
                Log::channel('line_image')->error('[admin-send] LINE push image failed', [
                    'customer_id' => $customer->id,
                    'contact_id' => $contact->id,
                    'media_file_id' => $mediaFile->id,
                    'exception' => $e::class.': '.$e->getMessage(),
                ]);

                return response()->json(['message' => $e->getMessage()], 502);
            }

            $message = CustomerLineMessage::query()->create([
                'customer_line_contact_id' => $contact->id,
                'direction' => CustomerLineMessage::DIRECTION_OUTBOUND,
                'message_type' => 'image',
                'text' => null,
                'line_message_id' => null,
                'payload' => null,
                'sent_by_user_id' => $request->user()?->id,
                'media_file_id' => $mediaFile->id,
            ]);

            return response()->json([
                'message' => [
                    'id' => $message->id,
                    'direction' => $message->direction,
                    'message_type' => $message->message_type,
                    'text' => null,
                    'image_url' => $imageUrl,
                    'created_at' => $message->created_at?->toIso8601String(),
                    'sent_by' => $request->user()?->name,
                ],
            ]);
        }

        // ---- テキスト送信（従来通り）----
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
                'image_url' => null,
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
