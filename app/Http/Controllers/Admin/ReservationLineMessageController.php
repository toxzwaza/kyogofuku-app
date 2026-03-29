<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineLinkToken;
use App\Models\CustomerLineMessage;
use App\Models\EventReservation;
use App\Services\Line\EventLineShopResolver;
use App\Services\Line\LineMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReservationLineMessageController extends Controller
{
    public function __construct(
        private EventLineShopResolver $eventLineShopResolver
    ) {}

    public function issueLinkToken(Request $request, EventReservation $reservation)
    {
        if ($reservation->customer_id) {
            return redirect()->back()->withErrors([
                'line' => '顧客が紐づいているため、顧客詳細画面の LINE から連携リンクを発行してください。',
            ]);
        }

        $reservation->load('event');
        $event = $reservation->event;
        if (! $event) {
            return redirect()->back()->withErrors(['line' => 'イベントが見つかりません。']);
        }

        $shopId = $this->eventLineShopResolver->resolveShopIdForEvent($event);
        if ($shopId === null) {
            return redirect()->back()->withErrors(['line' => 'イベントに店舗が設定されていないため、LINE 連携リンクを発行できません。']);
        }

        $liffId = config('line.liff.id');
        $loginChannelId = config('line.liff.login_channel_id');
        if (empty($liffId) || empty($loginChannelId)) {
            return redirect()->back()->withErrors([
                'line' => 'LINE 連携用の環境変数が未設定です。LIFF ID と LINE_LOGIN_CHANNEL_ID を .env に設定してください。',
            ]);
        }

        $token = CustomerLineLinkToken::generateToken();
        CustomerLineLinkToken::query()->create([
            'token' => $token,
            'customer_id' => null,
            'event_reservation_id' => $reservation->id,
            'shop_id' => $shopId,
            'suggested_label' => '本人',
            'expires_at' => now()->addDays(30),
            'created_by_user_id' => $request->user()?->id,
        ]);

        $base = rtrim((string) config('app.url'), '/');
        $liffUrl = $base.'/line/liff/link/'.rawurlencode($token);

        return redirect()->back()
            ->with('success', 'LINE 連携用リンクを発行しました。お客様に共有してください。')
            ->with('line_liff_link_url', $liffUrl);
    }

    public function contactMessages(EventReservation $reservation, CustomerLineContact $contact)
    {
        $this->assertReservationOwnsContact($reservation, $contact);

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

    public function send(Request $request, EventReservation $reservation, CustomerLineContact $contact, LineMessagingService $messaging)
    {
        $this->assertReservationOwnsContact($reservation, $contact);

        $validated = $request->validate([
            'text' => 'required|string|max:4500',
        ]);

        try {
            $messaging->pushTextToUser($contact->line_user_id, $validated['text']);
        } catch (\Throwable $e) {
            Log::error('LINE send from admin (reservation) failed', ['e' => $e->getMessage()]);

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

    public function destroyContact(Request $request, EventReservation $reservation, CustomerLineContact $contact)
    {
        $this->assertReservationOwnsContact($reservation, $contact);

        Log::info('admin LINE contact unlinked (reservation)', [
            'user_id' => $request->user()?->id,
            'reservation_id' => $reservation->id,
            'contact_id' => $contact->id,
        ]);

        $contact->delete();

        return redirect()->back()->with('success', 'LINE 連携を解除しました。同じ LINE アカウントを別の紐づけに使えます。');
    }

    private function assertReservationOwnsContact(EventReservation $reservation, CustomerLineContact $contact): void
    {
        if ($reservation->customer_id) {
            if ((int) $contact->customer_id !== (int) $reservation->customer_id) {
                abort(404);
            }

            return;
        }

        if ((int) $contact->event_reservation_id !== (int) $reservation->id) {
            abort(404);
        }
    }
}
