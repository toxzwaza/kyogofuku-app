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
 * - form_type=reservation_hakama … 袴予約向けブロック表示
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
        if (! in_array($formType, ['reservation', 'reservation_hakama', 'document', 'contact'], true)) {
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
            'postal_code' => $formType === 'reservation_hakama' ? null : '7000821',
            'furigana' => $formType === 'reservation_hakama' ? 'ヤマダ ハナコ' : null,
            'address' => $formType === 'reservation_hakama' ? '岡山県岡山市北区表町1-1' : null,
            'school_name' => $formType === 'reservation_hakama' ? 'サンプル高等学校' : null,
            'graduation_ceremony_date' => $formType === 'reservation_hakama' ? now()->addMonths(2)->format('Y-m-d') : null,
            'visitor_count' => $formType === 'reservation_hakama' ? 3 : null,
            'koichi_furisode_used' => $formType === 'reservation_hakama' ? false : null,
            'visit_reasons' => $formType === 'reservation_hakama' ? ['紹介', 'SNS・WEB広告'] : null,
            'considering_plans' => $formType === 'reservation_hakama' ? ['上下フルセットプラン'] : null,
            'parking_usage' => $formType === 'reservation_hakama' ? 'あり' : null,
            'parking_car_count' => $formType === 'reservation_hakama' ? 1 : null,
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
