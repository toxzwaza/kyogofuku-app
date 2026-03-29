<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Venue;
use Illuminate\Http\Request;

/**
 * サンクスメール HTML のブラウザ確認用（本番でも管理者のみ）。
 *
 * クエリ例:
 * - form_type=document & request_method=デジタルカタログ … カタログブロック表示
 * - no_line=1 … LINE 案内なし
 * - no_inquiry=1 … お問い合わせ内容なし
 * - hide_add_friend=1 … 友だち URL 併記をオフ（設定の逆を試す場合）
 */
class ReservationConfirmationMailPreviewController extends Controller
{
    public function __invoke(Request $request)
    {
        $formType = $request->query('form_type', 'reservation');
        if (! in_array($formType, ['reservation', 'document', 'contact'], true)) {
            $formType = 'reservation';
        }

        $requestMethod = $request->query('request_method');
        if ($formType === 'document' && $request->query('catalog')) {
            $requestMethod = 'デジタルカタログ';
        }

        $event = new Event([
            'title' => $request->query('title', '【プレビュー】サンプルイベント'),
            'form_type' => $formType,
            'slug' => 'preview',
        ]);

        $venue = new Venue([
            'name' => $request->query('venue', 'サンプル会場 本店'),
        ]);

        $reservation = new EventReservation([
            'name' => $request->query('name', '山田 花子'),
            'email' => $request->query('email', 'guest@example.com'),
            'phone' => $request->query('phone', '090-1234-5678'),
            'reservation_datetime' => $request->query('datetime', now()->addDays(14)->format('Y-m-d 14:00:00')),
            'inquiry_message' => $request->boolean('no_inquiry')
                ? null
                : $request->query('inquiry', "試着のご相談をしたく予約しました。\nよろしくお願いいたします。"),
            'request_method' => $requestMethod,
            'document_id' => $request->query('document_id', 1),
        ]);

        $reservation->setRelation('event', $event);
        $reservation->setRelation('venue', $venue);

        $lineLiffUrl = $request->boolean('no_line')
            ? null
            : $request->query('line_url', url('/line/liff/link/00000000-0000-4000-8000-000000000001'));

        return response()->view('mail.reservation_confirmation', [
            'reservation' => $reservation,
            'emailThreadId' => null,
            'lineLiffUrl' => $lineLiffUrl,
            'lineAddFriendUrl' => (string) config('line.line_official_add_friend_url', ''),
            'reservationEmailIncludeAddFriendUrl' => $request->boolean('hide_add_friend')
                ? false
                : (bool) config('line.reservation_email_include_add_friend_url', true),
        ]);
    }
}
