<?php

namespace App\Services\Line;

use App\Models\CustomerLineContact;
use App\Models\LineBroadcast;
use App\Models\LineBroadcastRecipient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LINE広告の一斉配信サービス。
 *
 * 重複送信防止の仕組み（三重）:
 *   1. line_user_id で名寄せし、1回の配信内で同一ユーザーへは1通のみ
 *   2. 同一広告で既に送信済み（status=sent）のユーザーは自動スキップ
 *   3. line_broadcast_recipients の unique(line_broadcast_id, line_user_id) 制約で
 *      アプリ層をすり抜けてもDB層で二重登録を拒否
 *
 * 送信は Multicast API（最大500件/回）でチャンク実行し、チャンク失敗時は
 * 1件ずつの push にフォールバックして失敗ユーザーを特定する。
 */
class LineBroadcastSender
{
    public const CHUNK_SIZE = 500;

    public function __construct(private LineMessagingService $messaging) {}

    /**
     * 広告メッセージを LINE メッセージオブジェクト配列に変換する（画像→本文の順）
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildMessages(LineBroadcast $broadcast): array
    {
        $messages = [];

        if ($broadcast->mediaFile) {
            $url = $broadcast->mediaFile->url;
            if (! app()->environment('testing') && ! str_starts_with((string) $url, 'https://')) {
                throw new \RuntimeException('バナー画像の URL が HTTPS ではありません（S3 設定要確認）。');
            }
            $messages[] = [
                'type' => 'image',
                'originalContentUrl' => $url,
                'previewImageUrl' => $url,
            ];
        }

        if (trim((string) $broadcast->text) !== '') {
            $messages[] = [
                'type' => 'text',
                'text' => $broadcast->text,
            ];
        }

        if (empty($messages)) {
            throw new \RuntimeException('メッセージ本文かバナー画像のどちらかが必要です。');
        }

        return $messages;
    }

    /**
     * 指定した連絡先へ広告を一斉配信する。
     *
     * @param  Collection<int, CustomerLineContact>  $contacts
     * @return array{sent:int, failed:int, skipped_already_sent:int, skipped_duplicate:int}
     */
    public function send(LineBroadcast $broadcast, Collection $contacts): array
    {
        $messages = $this->buildMessages($broadcast);

        // ---- 配信予約（トランザクション内で行を確保し、同時実行・重複を排除する） ----
        $reserved = collect();      // LineBroadcastRecipient（pending として確保できた行）
        $skippedAlreadySent = 0;
        $skippedDuplicate = 0;

        DB::transaction(function () use ($broadcast, $contacts, &$reserved, &$skippedAlreadySent, &$skippedDuplicate) {
            // 同時実行ガード: 広告行をロックして「送信中」の多重起動を防ぐ
            $locked = LineBroadcast::whereKey($broadcast->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === LineBroadcast::STATUS_SENDING
                && $locked->updated_at !== null
                && $locked->updated_at->gt(now()->subMinutes(10))
            ) {
                // 10分以上前の sending は異常終了の残骸とみなして送信を許可する
                throw new \RuntimeException('この広告は現在送信処理中です。しばらく待ってから再度お試しください。');
            }
            $locked->update(['status' => LineBroadcast::STATUS_SENDING]);

            $seenUserIds = [];
            foreach ($contacts as $contact) {
                $lineUserId = (string) $contact->line_user_id;
                if ($lineUserId === '') {
                    continue;
                }

                // 1回の配信内での名寄せ（顧客タブ・予約タブ両方から選ばれた場合など）
                if (isset($seenUserIds[$lineUserId])) {
                    $skippedDuplicate++;
                    continue;
                }
                $seenUserIds[$lineUserId] = true;

                $existing = LineBroadcastRecipient::query()
                    ->where('line_broadcast_id', $broadcast->id)
                    ->where('line_user_id', $lineUserId)
                    ->first();

                if ($existing && $existing->status === LineBroadcastRecipient::STATUS_SENT) {
                    // 送信済みユーザーには再送しない
                    $skippedAlreadySent++;
                    continue;
                }

                if ($existing) {
                    // 前回失敗（failed）・中断（pending）の行は再送対象として確保し直す
                    $existing->update([
                        'customer_line_contact_id' => $contact->id,
                        'recipient_name' => $this->recipientName($contact),
                        'recipient_kind' => $this->recipientKind($contact),
                        'status' => LineBroadcastRecipient::STATUS_PENDING,
                        'error_message' => null,
                    ]);
                    $reserved->push($existing);
                } else {
                    $reserved->push(LineBroadcastRecipient::create([
                        'line_broadcast_id' => $broadcast->id,
                        'customer_line_contact_id' => $contact->id,
                        'line_user_id' => $lineUserId,
                        'recipient_name' => $this->recipientName($contact),
                        'recipient_kind' => $this->recipientKind($contact),
                        'status' => LineBroadcastRecipient::STATUS_PENDING,
                    ]));
                }
            }
        });

        // ---- チャンク送信 ----
        $sent = 0;
        $failed = 0;

        foreach ($reserved->chunk(self::CHUNK_SIZE) as $chunk) {
            $userIds = $chunk->pluck('line_user_id')->values()->all();

            try {
                $this->messaging->multicast($userIds, $messages);
                $now = now();
                LineBroadcastRecipient::whereIn('id', $chunk->pluck('id'))->update([
                    'status' => LineBroadcastRecipient::STATUS_SENT,
                    'error_message' => null,
                    'sent_at' => $now,
                ]);
                $sent += count($userIds);
            } catch (\Throwable $e) {
                Log::warning('LINE broadcast multicast chunk failed, falling back to per-user push', [
                    'broadcast_id' => $broadcast->id,
                    'chunk_size' => count($userIds),
                    'exception' => $e::class.': '.$e->getMessage(),
                ]);

                // フォールバック: 1件ずつ送信して失敗ユーザーを特定する
                foreach ($chunk as $recipient) {
                    try {
                        $this->messaging->pushMessagesToUser($recipient->line_user_id, $messages);
                        $recipient->update([
                            'status' => LineBroadcastRecipient::STATUS_SENT,
                            'error_message' => null,
                            'sent_at' => now(),
                        ]);
                        $sent++;
                    } catch (\Throwable $pushError) {
                        $recipient->update([
                            'status' => LineBroadcastRecipient::STATUS_FAILED,
                            'error_message' => mb_substr($pushError->getMessage(), 0, 1000),
                        ]);
                        $failed++;
                    }
                }
            }
        }

        // ---- ステータス確定 ----
        $broadcast->update([
            'status' => LineBroadcast::STATUS_SENT,
            'last_sent_at' => now(),
        ]);

        Log::info('LINE broadcast finished', [
            'broadcast_id' => $broadcast->id,
            'sent' => $sent,
            'failed' => $failed,
            'skipped_already_sent' => $skippedAlreadySent,
            'skipped_duplicate' => $skippedDuplicate,
        ]);

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped_already_sent' => $skippedAlreadySent,
            'skipped_duplicate' => $skippedDuplicate,
        ];
    }

    /**
     * テスト送信: 指定した連絡先1件に広告メッセージを送る（配信履歴には記録しない）
     */
    public function sendTest(LineBroadcast $broadcast, CustomerLineContact $contact): void
    {
        $this->messaging->pushMessagesToUser($contact->line_user_id, $this->buildMessages($broadcast));
    }

    private function recipientName(CustomerLineContact $contact): ?string
    {
        return $contact->customer?->name
            ?? $contact->eventReservation?->name
            ?? $contact->label;
    }

    private function recipientKind(CustomerLineContact $contact): string
    {
        if ($contact->customer_id) {
            return 'customer';
        }
        if ($contact->event_reservation_id) {
            return 'reservation';
        }

        return 'unbound';
    }
}
