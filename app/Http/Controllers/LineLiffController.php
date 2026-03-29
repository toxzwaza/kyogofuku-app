<?php

namespace App\Http\Controllers;

use App\Models\CustomerLineContact;
use App\Models\CustomerLineLinkToken;
use App\Models\CustomerLineMessage;
use App\Services\Line\EventLineShopResolver;
use App\Services\Line\LineIdTokenVerifier;
use App\Services\Line\LineMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LineLiffController extends Controller
{
    public function __construct(
        private LineIdTokenVerifier $idTokenVerifier,
        private LineMessagingService $lineMessaging,
        private EventLineShopResolver $eventLineShopResolver
    ) {}

    /**
     * LIFF 連携ページ（チャネルシークレットを露出しない）
     *
     * @param  string|null  $token  ルートパラメータ（推奨）。未指定時は ?link_token=（レガシー）
     */
    public function showLink(Request $request, ?string $token = null)
    {
        $token = $token !== null && $token !== ''
            ? $token
            : (string) $request->query('link_token', '');
        if ($token === '') {
            return $this->liffLinkView([
                'error' => '連携用リンクが無効です（トークンがありません）。',
                'liffId' => null,
                'linkToken' => '',
            ]);
        }

        $link = CustomerLineLinkToken::query()
            ->where('token', $token)
            ->with(['customer', 'eventReservation'])
            ->first();

        if (! $link || ! $link->isUsable()) {
            return $this->liffLinkView([
                'error' => 'この連携リンクは期限切れか、既に使用済みです。',
                'liffId' => null,
                'linkToken' => '',
            ]);
        }

        if ($link->customer_id === null && $link->event_reservation_id === null) {
            return $this->liffLinkView([
                'error' => '連携リンクの設定が不正です。',
                'liffId' => null,
                'linkToken' => '',
            ]);
        }

        $liffId = config('line.liff.id');
        $channelId = config('line.liff.login_channel_id');
        if (empty($liffId) || empty($channelId)) {
            return $this->liffLinkView([
                'error' => 'LINE 連携のサーバー設定が未完了です。管理者にお問い合わせください。',
                'liffId' => null,
                'linkToken' => '',
            ]);
        }

        $linkFlowMode = $link->event_reservation_id ? 'reservation' : 'customer';
        $suggestedLabel = $linkFlowMode === 'reservation'
            ? '本人'
            : (filled($link->suggested_label) ? (string) $link->suggested_label : 'お客様');

        return $this->liffLinkView([
            'error' => null,
            'liffId' => $liffId,
            'linkToken' => $token,
            'suggestedLabel' => $suggestedLabel,
            'linkFlowMode' => $linkFlowMode,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function liffLinkView(array $data): \Illuminate\Http\Response
    {
        $defaults = [
            'addFriendUrl' => (string) config('line.line_official_add_friend_url', ''),
            'linkFlowMode' => 'customer',
            'suggestedLabel' => 'お客様',
        ];

        return response()->view('line.liff-link', array_merge($defaults, $data));
    }

    public function complete(Request $request)
    {
        $validated = $request->validate([
            'id_token' => 'required|string',
            'link_token' => 'required|string',
            'label' => 'nullable|string|max:50',
        ]);

        $link = CustomerLineLinkToken::query()
            ->where('token', $validated['link_token'])
            ->with(['customer', 'eventReservation.event'])
            ->first();

        if (! $link || ! $link->isUsable()) {
            return response()->json(['message' => '連携リンクが無効です。'], 422);
        }

        if ($link->customer_id === null && $link->event_reservation_id === null) {
            return response()->json(['message' => '連携リンクの設定が不正です。'], 422);
        }

        $channelId = config('line.liff.login_channel_id');
        if (empty($channelId)) {
            return response()->json(['message' => '設定エラーです。'], 503);
        }

        $verified = $this->idTokenVerifier->verify($validated['id_token'], (string) $channelId);
        if ($verified === null) {
            return response()->json([
                'message' => 'LINE の認証に失敗しました。'
                    .' `.env` の `LINE_LOGIN_CHANNEL_ID` が、LIFF を追加した「LINEログイン」チャネルの Channel ID と一致しているか確認してください（Messaging API の Channel ID では検証できません）。'
                    .' 変更後は `php artisan config:clear` を実行してください。詳細は `storage/logs/laravel.log` を参照してください。',
            ], 401);
        }

        $lineUserId = $verified['sub'];

        if ($link->event_reservation_id) {
            return $this->completeForReservation($link, $lineUserId);
        }

        return $this->completeForCustomer($link, $lineUserId, $validated['label'] ?? '');
    }

    private function completeForCustomer(CustomerLineLinkToken $link, string $lineUserId, string $labelInput): \Illuminate\Http\JsonResponse
    {
        $label = $labelInput !== '' ? mb_substr($labelInput, 0, 50) : 'お客様';

        $customer = $link->customer;
        if (! $customer || ! $customer->shop_id) {
            return response()->json(['message' => '顧客の担当店舗が未設定のため連携できません。'], 422);
        }

        $existing = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->first();

        if ($existing && $existing->event_reservation_id && $existing->customer_id === null) {
            return response()->json(['message' => 'この LINE アカウントはイベント予約への連携用です。予約が顧客に紐づいた後は、顧客詳細から連携してください。'], 422);
        }

        if ($existing && (int) $existing->customer_id !== (int) $link->customer_id) {
            return response()->json(['message' => 'この LINE アカウントは別の顧客に連携済みです。'], 422);
        }

        $hadContactBefore = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->exists();

        DB::transaction(function () use ($link, $lineUserId, $label, $customer) {
            CustomerLineContact::query()->updateOrCreate(
                [
                    'line_user_id' => $lineUserId,
                ],
                [
                    'customer_id' => $link->customer_id,
                    'event_reservation_id' => null,
                    'shop_id' => $customer->shop_id,
                    'label' => $label,
                ]
            );

            $link->used_at = now();
            $link->save();
        });

        if (! $hadContactBefore) {
            $this->sendLinkWelcomeToLineUser($lineUserId);
        }

        return response()->json(['message' => '連携が完了しました。この画面を閉じて LINE に戻ってください。']);
    }

    private function completeForReservation(CustomerLineLinkToken $link, string $lineUserId): \Illuminate\Http\JsonResponse
    {
        $reservation = $link->eventReservation;
        if (! $reservation) {
            return response()->json(['message' => '予約が見つかりません。'], 422);
        }

        $event = $reservation->event;
        if (! $event) {
            return response()->json(['message' => 'イベントが見つかりません。'], 422);
        }

        $shopId = $this->eventLineShopResolver->resolveShopIdForEvent($event);
        if ($shopId === null) {
            return response()->json(['message' => 'イベントに店舗が設定されていないため連携できません。'], 422);
        }

        $existing = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->first();

        if ($existing && $existing->customer_id !== null) {
            return response()->json(['message' => 'この LINE アカウントは既に顧客に連携済みです。'], 422);
        }

        if ($existing && $existing->event_reservation_id !== null
            && (int) $existing->event_reservation_id !== (int) $link->event_reservation_id) {
            return response()->json(['message' => 'この LINE アカウントは別の予約に連携済みです。'], 422);
        }

        $hadContactBefore = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->exists();

        DB::transaction(function () use ($link, $lineUserId, $shopId) {
            CustomerLineContact::query()->updateOrCreate(
                [
                    'line_user_id' => $lineUserId,
                ],
                [
                    'customer_id' => null,
                    'event_reservation_id' => $link->event_reservation_id,
                    'shop_id' => $shopId,
                    'label' => '本人',
                ]
            );

            $link->used_at = now();
            $link->save();
        });

        if (! $hadContactBefore) {
            $this->sendLinkWelcomeToLineUser($lineUserId);
        }

        return response()->json(['message' => '連携が完了しました。この画面を閉じて LINE に戻ってください。']);
    }

    /**
     * liff.login() 後の固定コールバック先（LINE コンソールに 1 本登録すればよい）
     */
    public function liffLoginResume()
    {
        $liffId = config('line.liff.id');

        return response()->view('line.liff-resume', [
            'liffId' => $liffId,
        ]);
    }

    /**
     * 初回連携直後にあいさつを LINE へ送り、スレッドにも残す（失敗しても連携 API は成功扱い）
     */
    private function sendLinkWelcomeToLineUser(string $lineUserId): void
    {
        $text = trim((string) config('line.link_welcome_text', ''));
        if ($text === '') {
            return;
        }

        $contact = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->first();
        if (! $contact) {
            return;
        }

        try {
            $this->lineMessaging->pushTextToUser($lineUserId, $text);
        } catch (\Throwable $e) {
            Log::warning('LINE link welcome: push failed', [
                'line_user_id' => $lineUserId,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        try {
            CustomerLineMessage::query()->create([
                'customer_line_contact_id' => $contact->id,
                'direction' => CustomerLineMessage::DIRECTION_OUTBOUND,
                'message_type' => 'text',
                'text' => $text,
                'line_message_id' => null,
                'payload' => ['kind' => 'link_welcome'],
                'sent_by_user_id' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('LINE link welcome: DB record failed', [
                'line_user_id' => $lineUserId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
