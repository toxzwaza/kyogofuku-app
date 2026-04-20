<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineMessage;
use App\Models\EventReservation;
use App\Services\Line\EventLineShopResolver;
use App\Services\Line\LineIdTokenVerifier;
use App\Services\Line\LineMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * あいさつメッセージ経由のセルフ紐付け用コントローラ。
 *
 * link token を持たずに友だち追加だけしたユーザーが、自分で
 * 「予約番号」または「電話番号」＋お名前カナを入力して紐付けるためのフロー。
 */
class LineWelcomeLinkController extends Controller
{
    public function __construct(
        private LineIdTokenVerifier $idTokenVerifier,
        private LineMessagingService $lineMessaging,
        private EventLineShopResolver $eventLineShopResolver
    ) {}

    public function show(Request $request): Response
    {
        $liffId = config('line.liff.welcome_id') ?: config('line.liff.id');
        $channelId = config('line.liff.login_channel_id');

        if (empty($liffId) || empty($channelId)) {
            return response()->view('line.liff-welcome', [
                'error' => 'LINE 連携のサーバー設定が未完了です。管理者にお問い合わせください。',
                'liffId' => null,
            ]);
        }

        return response()->view('line.liff-welcome', [
            'error' => null,
            'liffId' => $liffId,
        ]);
    }

    public function match(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => 'required|string',
            'lookup_key' => 'required|string|max:255',
            'kana' => 'nullable|string|max:255',
        ]);

        $channelId = config('line.liff.login_channel_id');
        if (empty($channelId)) {
            return response()->json(['message' => '設定エラーです。'], 503);
        }

        $verified = $this->idTokenVerifier->verify($validated['id_token'], (string) $channelId);
        if ($verified === null) {
            return response()->json(['message' => 'LINE の認証に失敗しました。再度お試しください。'], 401);
        }

        $lineUserId = $verified['sub'];
        $phone = $this->normalizePhone((string) $validated['lookup_key']);
        $kana = $this->normalizeKana((string) ($validated['kana'] ?? ''));

        if ($phone === '') {
            return $this->notFoundResponse();
        }

        $existing = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->first();

        if ($existing && ($existing->customer_id !== null || $existing->event_reservation_id !== null)) {
            return response()->json([
                'message' => 'この LINE アカウントは既に連携済みです。LINE 画面に戻ってください。',
                'already_linked' => true,
            ]);
        }

        // Customer 優先で照合（顧客情報があれば予約より優先）
        $customer = $this->findCustomerByPhone($phone, $kana);
        if ($customer) {
            return $this->linkToCustomer($customer, $lineUserId);
        }

        $reservation = $this->findReservationByPhone($phone, $kana);
        if ($reservation) {
            return $this->linkToReservation($reservation, $lineUserId);
        }

        return $this->notFoundResponse();
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'message' => '該当するご予約・お客様情報が見つかりませんでした。'
                ."\n\n"
                .'お手数ですが、このトーク画面にて「お名前」と「お電話番号」をメッセージで送信してください。'
                .'数日以内にスタッフが対応いたします。',
        ], 404);
    }

    private function findReservationByPhone(string $phone, string $kana): ?EventReservation
    {
        $candidates = EventReservation::query()
            ->whereRaw("REPLACE(REPLACE(phone, '-', ''), ' ', '') = ?", [$phone])
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        if ($candidates->isEmpty()) {
            return null;
        }
        if ($kana === '') {
            return $candidates->first();
        }
        foreach ($candidates as $r) {
            if ($r->furigana && $this->normalizeKana((string) $r->furigana) === $kana) {
                return $r;
            }
        }

        return null;
    }

    private function findCustomerByPhone(string $phone, string $kana): ?Customer
    {
        $candidates = Customer::query()
            ->whereRaw("REPLACE(REPLACE(phone_number, '-', ''), ' ', '') = ?", [$phone])
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        if ($candidates->isEmpty()) {
            return null;
        }
        if ($kana === '') {
            return $candidates->first();
        }
        foreach ($candidates as $c) {
            foreach ([$c->kana, $c->guardian_name_kana] as $k) {
                if ($k && $this->normalizeKana((string) $k) === $kana) {
                    return $c;
                }
            }
        }

        return null;
    }

    /**
     * 全角を半角化し、数字以外を除去した電話番号を返す。空文字なら検索対象外。
     */
    private function normalizePhone(string $input): string
    {
        $half = mb_convert_kana($input, 'a');
        $digits = preg_replace('/\D+/u', '', (string) $half);

        return is_string($digits) ? $digits : '';
    }

    /**
     * カナを正規化する。半角カナ→全角カナ、半角→全角、全角/半角スペース除去。
     */
    private function normalizeKana(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }
        $kana = mb_convert_kana($input, 'KCV');
        $kana = preg_replace('/[\s\x{3000}]+/u', '', (string) $kana);

        return is_string($kana) ? $kana : '';
    }

    private function linkToReservation(EventReservation $reservation, string $lineUserId): JsonResponse
    {
        $event = $reservation->event;
        if (! $event) {
            return response()->json(['message' => 'ご予約に紐づくイベントが見つかりません。'], 422);
        }

        $shopId = $this->eventLineShopResolver->resolveShopIdForEvent($event);
        if ($shopId === null) {
            return response()->json(['message' => 'ご予約のイベントに店舗が設定されていません。お電話でお問い合わせください。'], 422);
        }

        $occupied = CustomerLineContact::query()
            ->where('event_reservation_id', $reservation->id)
            ->first();
        if ($occupied && $occupied->line_user_id !== $lineUserId) {
            return response()->json(['message' => 'このご予約には既に別の LINE アカウントが連携済みです。お電話でお問い合わせください。'], 422);
        }

        $hadContactBefore = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->exists();

        DB::transaction(function () use ($reservation, $lineUserId, $shopId): void {
            CustomerLineContact::query()->updateOrCreate(
                ['line_user_id' => $lineUserId],
                [
                    'customer_id' => null,
                    'event_reservation_id' => $reservation->id,
                    'shop_id' => $shopId,
                    'label' => '本人',
                ]
            );
        });

        if (! $hadContactBefore) {
            $this->sendWelcomePushAndRecord($lineUserId);
        }

        return response()->json(['message' => 'ご予約と連携しました。LINE 画面にお戻りください。']);
    }

    private function linkToCustomer(Customer $customer, string $lineUserId): JsonResponse
    {
        if (! $customer->shop_id) {
            return response()->json(['message' => 'お客様情報の担当店舗が未設定のため連携できません。お電話でお問い合わせください。'], 422);
        }

        $existing = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->first();
        if ($existing && $existing->customer_id !== null && (int) $existing->customer_id !== (int) $customer->id) {
            return response()->json(['message' => 'この LINE アカウントは別のお客様に連携済みです。'], 422);
        }

        $hadContactBefore = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->exists();

        DB::transaction(function () use ($customer, $lineUserId): void {
            CustomerLineContact::query()->updateOrCreate(
                ['line_user_id' => $lineUserId],
                [
                    'customer_id' => $customer->id,
                    'event_reservation_id' => null,
                    'shop_id' => $customer->shop_id,
                    'label' => 'お客様',
                ]
            );
        });

        if (! $hadContactBefore) {
            $this->sendWelcomePushAndRecord($lineUserId);
        }

        return response()->json(['message' => 'お客様情報と連携しました。LINE 画面にお戻りください。']);
    }

    private function sendWelcomePushAndRecord(string $lineUserId): void
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
            Log::warning('LINE welcome push failed (welcome flow)', [
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
                'payload' => ['kind' => 'welcome_link_self_match'],
                'sent_by_user_id' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('LINE welcome push: DB record failed (welcome flow)', [
                'line_user_id' => $lineUserId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
