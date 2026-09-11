<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesUiView;
use App\Http\Controllers\Controller;
use App\Models\CeremonyArea;
use App\Models\ConstraintTemplate;
use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\LineBroadcast;
use App\Models\LineBroadcastRecipient;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Queries\CustomerSearchQuery;
use App\Services\Line\LineBroadcastSender;
use App\Services\Line\LineMessageMediaStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * LINE広告（キャンペーン一斉配信）の管理画面。
 *
 * 広告の作成・一覧・編集と、送信先選択（顧客検索 / イベント予約者検索）・
 * 一斉送信の実行・配信履歴の表示を担当する。
 */
class LineBroadcastController extends Controller
{
    use ResolvesUiView;

    // ---------------------------------------------------------------
    // CRUD
    // ---------------------------------------------------------------

    public function index(Request $request)
    {
        $paginator = LineBroadcast::query()
            ->with(['mediaFile', 'createdBy:id,name'])
            ->withCount([
                'recipients as sent_count' => fn ($q) => $q->where('status', LineBroadcastRecipient::STATUS_SENT),
                'recipients as failed_count' => fn ($q) => $q->where('status', LineBroadcastRecipient::STATUS_FAILED),
            ])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $items = $paginator->getCollection()->map(fn (LineBroadcast $b) => [
            'id' => $b->id,
            'title' => $b->title,
            'text' => $b->text,
            'image_url' => $b->mediaFile?->url,
            'status' => $b->status,
            'sent_count' => $b->sent_count,
            'failed_count' => $b->failed_count,
            'last_sent_at' => $b->last_sent_at?->toIso8601String(),
            'created_by' => $b->createdBy?->name,
            'created_at' => $b->created_at?->toIso8601String(),
        ])->values();

        return Inertia::render($this->viewFor('Admin/LineBroadcasts/Index'), [
            'broadcasts' => [
                'data' => $items,
                'links' => $paginator->linkCollection(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render($this->viewFor('Admin/LineBroadcasts/Form'), [
            'broadcast' => null,
        ]);
    }

    public function store(Request $request, LineMessageMediaStore $mediaStore)
    {
        $validated = $this->validateBroadcast($request);

        if (! $request->hasFile('image_file') && trim((string) ($validated['text'] ?? '')) === '') {
            return back()->withErrors(['text' => 'メッセージ本文かバナー画像のどちらかを指定してください。']);
        }

        $broadcast = new LineBroadcast([
            'title' => $validated['title'],
            'text' => $validated['text'] ?? null,
            'status' => LineBroadcast::STATUS_DRAFT,
        ]);
        $broadcast->created_by = $request->user()?->id;

        if ($request->hasFile('image_file')) {
            $broadcast->media_file_id = $mediaStore->storeOutboundImage($request->file('image_file'))->id;
        }

        $broadcast->save();

        return redirect()
            ->route('admin.line-broadcasts.edit', $broadcast)
            ->with('success', 'LINE広告を作成しました。');
    }

    public function edit(LineBroadcast $broadcast)
    {
        return Inertia::render($this->viewFor('Admin/LineBroadcasts/Form'), [
            'broadcast' => $this->broadcastDetail($broadcast),
        ]);
    }

    public function update(Request $request, LineBroadcast $broadcast, LineMessageMediaStore $mediaStore)
    {
        $validated = $this->validateBroadcast($request);

        $broadcast->title = $validated['title'];
        $broadcast->text = $validated['text'] ?? null;

        if ($request->hasFile('image_file')) {
            $broadcast->media_file_id = $mediaStore->storeOutboundImage($request->file('image_file'))->id;
        } elseif ($request->boolean('remove_image')) {
            $broadcast->media_file_id = null;
        }

        if ($broadcast->media_file_id === null && trim((string) $broadcast->text) === '') {
            return back()->withErrors(['text' => 'メッセージ本文かバナー画像のどちらかを指定してください。']);
        }

        $broadcast->save();

        return redirect()
            ->route('admin.line-broadcasts.edit', $broadcast)
            ->with('success', 'LINE広告を更新しました。');
    }

    public function destroy(LineBroadcast $broadcast)
    {
        if ($broadcast->sentRecipients()->exists()) {
            return back()->withErrors(['broadcast' => '送信済みの広告は削除できません（配信履歴を保持するため）。']);
        }

        $broadcast->delete();

        return redirect()
            ->route('admin.line-broadcasts.index')
            ->with('success', 'LINE広告を削除しました。');
    }

    public function duplicate(Request $request, LineBroadcast $broadcast)
    {
        $copy = LineBroadcast::create([
            'title' => $broadcast->title.'のコピー',
            'text' => $broadcast->text,
            'media_file_id' => $broadcast->media_file_id,
            'status' => LineBroadcast::STATUS_DRAFT,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.line-broadcasts.edit', $copy)
            ->with('success', '広告を複製しました。内容を編集してください。');
    }

    /**
     * 広告詳細（配信履歴）
     */
    public function show(LineBroadcast $broadcast)
    {
        $recipients = $broadcast->recipients()
            ->with(['contact.customer:id,name', 'contact.eventReservation:id,name'])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $rows = $recipients->getCollection()->map(fn (LineBroadcastRecipient $r) => [
            'id' => $r->id,
            'name' => $r->recipient_name ?: '（不明）',
            'kind' => $r->recipient_kind,
            'status' => $r->status,
            'error_message' => $r->error_message,
            'sent_at' => $r->sent_at?->toIso8601String(),
        ])->values();

        return Inertia::render($this->viewFor('Admin/LineBroadcasts/Show'), [
            'broadcast' => $this->broadcastDetail($broadcast),
            'recipients' => [
                'data' => $rows,
                'links' => $recipients->linkCollection(),
                'meta' => [
                    'current_page' => $recipients->currentPage(),
                    'last_page' => $recipients->lastPage(),
                    'total' => $recipients->total(),
                ],
            ],
            'summary' => [
                'sent' => $broadcast->recipients()->where('status', LineBroadcastRecipient::STATUS_SENT)->count(),
                'failed' => $broadcast->recipients()->where('status', LineBroadcastRecipient::STATUS_FAILED)->count(),
            ],
        ]);
    }

    // ---------------------------------------------------------------
    // テスト送信
    // ---------------------------------------------------------------

    /**
     * テスト送信先の連携アカウント検索（JSON）
     */
    public function contactSearch(Request $request)
    {
        $keyword = trim((string) $request->input('q', ''));
        if ($keyword === '') {
            return response()->json(['contacts' => []]);
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword).'%';
        $contacts = CustomerLineContact::query()
            ->with(['customer:id,name', 'eventReservation:id,name', 'shop:id,name'])
            ->where(function ($q) use ($like) {
                $q->whereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('kana', 'like', $like))
                    ->orWhereHas('eventReservation', fn ($r) => $r->where('name', 'like', $like)->orWhere('furigana', 'like', $like))
                    ->orWhere('label', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (CustomerLineContact $c) => [
                'id' => $c->id,
                'name' => $c->customer?->name ?? $c->eventReservation?->name ?? $c->label ?? 'LINEユーザー',
                'kind' => $c->customer_id ? 'customer' : ($c->event_reservation_id ? 'reservation' : 'unbound'),
                'shop_name' => $c->shop?->name,
            ])
            ->values();

        return response()->json(['contacts' => $contacts]);
    }

    /**
     * テスト送信（配信履歴には記録しない）
     */
    public function testSend(Request $request, LineBroadcast $broadcast, LineBroadcastSender $sender)
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', 'exists:customer_line_contacts,id'],
        ]);

        $contact = CustomerLineContact::findOrFail($validated['contact_id']);

        try {
            $sender->sendTest($broadcast, $contact);
        } catch (\Throwable $e) {
            Log::warning('LINE broadcast test send failed', [
                'broadcast_id' => $broadcast->id,
                'contact_id' => $contact->id,
                'exception' => $e::class.': '.$e->getMessage(),
            ]);

            return response()->json(['message' => 'テスト送信に失敗しました: '.$e->getMessage()], 502);
        }

        return response()->json(['message' => 'テスト送信しました。']);
    }

    // ---------------------------------------------------------------
    // 送信先選択・一斉送信
    // ---------------------------------------------------------------

    /**
     * 送信先選択ページ
     */
    public function selectRecipients(LineBroadcast $broadcast)
    {
        return Inertia::render($this->viewFor('Admin/LineBroadcasts/Recipients'), [
            'broadcast' => $this->broadcastDetail($broadcast),
            'ceremonyAreas' => CeremonyArea::orderBy('name')->get(['id', 'name']),
            'shops' => Shop::orderBy('name')->get(['id', 'name']),
            'plans' => Plan::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'constraintTemplates' => ConstraintTemplate::orderBy('name')->get(['id', 'name']),
            'events' => Event::orderByDesc('id')->get(['id', 'title']),
        ]);
    }

    /**
     * 送信先選択: 顧客検索（JSON）
     *
     * 顧客一覧（Admin/Customer/Index）と同じフィルタで検索し、
     * 各顧客の LINE 連携状態とこの広告での送信済み状態を付与して返す。
     */
    public function searchCustomers(Request $request, LineBroadcast $broadcast)
    {
        [
            'query' => $query,
            'customerShopFilterValue' => $customerShopFilterValue,
        ] = CustomerSearchQuery::build($request, CustomerSearchQuery::defaultShopIdFor($request->user()));

        $paginator = $query
            ->with(['shop:id,name', 'ceremonyArea:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $customerIds = $paginator->getCollection()->pluck('id');

        $contactsByCustomer = CustomerLineContact::query()
            ->whereIn('customer_id', $customerIds)
            ->get(['id', 'customer_id', 'line_user_id', 'label'])
            ->groupBy('customer_id');

        $sentUserIds = $this->sentUserIds($broadcast, $contactsByCustomer->flatten(1)->pluck('line_user_id'));

        $rows = $paginator->getCollection()->map(function (Customer $customer) use ($contactsByCustomer, $sentUserIds) {
            $contacts = ($contactsByCustomer[$customer->id] ?? collect())->map(fn ($c) => [
                'id' => $c->id,
                'already_sent' => $sentUserIds->contains($c->line_user_id),
            ])->values();

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'kana' => $customer->kana,
                'phone' => $customer->phone_number,
                'shop_name' => $customer->shop?->name,
                'ceremony_area' => $customer->ceremonyArea?->name,
                'contacts' => $contacts,
                'line_linked' => $contacts->isNotEmpty(),
                'sendable_contact_ids' => $contacts->where('already_sent', false)->pluck('id')->values(),
            ];
        })->values();

        return response()->json([
            'customers' => [
                'data' => $rows,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'applied' => array_merge($request->only(CustomerSearchQuery::filterKeys()), [
                'customer_shop_id' => $customerShopFilterValue,
            ]),
        ]);
    }

    /**
     * 送信先選択: イベント予約者検索（JSON）
     *
     * イベント・名前/電話キーワード・顧客登録有無で絞り込むシンプル検索。
     */
    public function searchReservations(Request $request, LineBroadcast $broadcast)
    {
        $eventIds = array_values(array_filter(array_map(
            fn ($v) => is_numeric($v) ? (int) $v : null,
            (array) $request->input('event_ids', [])
        ), fn ($v) => $v !== null));

        $keyword = trim((string) $request->input('q', ''));
        $customerFilter = (string) $request->input('customer_linked', 'all'); // all / none / linked

        $query = EventReservation::query()
            ->with(['event:id,title'])
            ->orderByDesc('id');

        if (! empty($eventIds)) {
            $query->whereIn('event_id', $eventIds);
        }

        if ($keyword !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword).'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('furigana', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if ($customerFilter === 'none') {
            $query->whereNull('customer_id');
        } elseif ($customerFilter === 'linked') {
            $query->whereNotNull('customer_id');
        }

        $paginator = $query->paginate(20)->withQueryString();

        $reservationIds = $paginator->getCollection()->pluck('id');

        $contactsByReservation = CustomerLineContact::query()
            ->whereIn('event_reservation_id', $reservationIds)
            ->get(['id', 'event_reservation_id', 'line_user_id', 'label'])
            ->groupBy('event_reservation_id');

        $sentUserIds = $this->sentUserIds($broadcast, $contactsByReservation->flatten(1)->pluck('line_user_id'));

        $rows = $paginator->getCollection()->map(function (EventReservation $r) use ($contactsByReservation, $sentUserIds) {
            $contacts = ($contactsByReservation[$r->id] ?? collect())->map(fn ($c) => [
                'id' => $c->id,
                'already_sent' => $sentUserIds->contains($c->line_user_id),
            ])->values();

            return [
                'id' => $r->id,
                'name' => $r->name ?: ($r->form_data['name'] ?? ''),
                'furigana' => $r->furigana ?: ($r->form_data['furigana'] ?? ''),
                'phone' => $r->phone ?: ($r->form_data['phone'] ?? ''),
                'event_title' => $r->event?->title,
                'reservation_datetime' => $r->reservation_datetime,
                'has_customer' => $r->customer_id !== null,
                'contacts' => $contacts,
                'line_linked' => $contacts->isNotEmpty(),
                'sendable_contact_ids' => $contacts->where('already_sent', false)->pluck('id')->values(),
            ];
        })->values();

        return response()->json([
            'reservations' => [
                'data' => $rows,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * 一斉送信の実行
     */
    public function send(Request $request, LineBroadcast $broadcast, LineBroadcastSender $sender)
    {
        $validated = $request->validate([
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['integer'],
        ]);

        $contacts = CustomerLineContact::query()
            ->with(['customer:id,name', 'eventReservation:id,name'])
            ->whereIn('id', $validated['contact_ids'])
            ->get();

        if ($contacts->isEmpty()) {
            return back()->withErrors(['contact_ids' => '送信先が見つかりませんでした。']);
        }

        try {
            $result = $sender->send($broadcast, $contacts);
        } catch (\Throwable $e) {
            Log::warning('LINE broadcast send failed', [
                'broadcast_id' => $broadcast->id,
                'exception' => $e::class.': '.$e->getMessage(),
            ]);

            return back()->withErrors(['send' => $e->getMessage()]);
        }

        $message = sprintf('送信が完了しました（成功 %d件 / 失敗 %d件）。', $result['sent'], $result['failed']);
        if ($result['skipped_already_sent'] > 0) {
            $message .= sprintf(' 送信済みの %d件 はスキップしました。', $result['skipped_already_sent']);
        }
        if ($result['skipped_duplicate'] > 0) {
            $message .= sprintf(' 重複 %d件 を除外しました。', $result['skipped_duplicate']);
        }

        return redirect()
            ->route('admin.line-broadcasts.show', $broadcast)
            ->with('success', $message);
    }

    // ---------------------------------------------------------------
    // private
    // ---------------------------------------------------------------

    private function validateBroadcast(Request $request): array
    {
        $maxBytes = (int) config('line.image_messaging.max_size_bytes', 5 * 1024 * 1024);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'text' => ['nullable', 'string', 'max:4500'],
            'image_file' => [
                'nullable', 'file', 'image', 'mimes:jpeg,jpg,png',
                'max:'.max(1, intdiv($maxBytes, 1024)),
            ],
        ], [], [
            'title' => 'タイトル',
            'text' => 'メッセージ本文',
            'image_file' => 'バナー画像',
        ]);

        return $validated;
    }

    private function broadcastDetail(LineBroadcast $broadcast): array
    {
        $broadcast->loadMissing('mediaFile');

        return [
            'id' => $broadcast->id,
            'title' => $broadcast->title,
            'text' => $broadcast->text,
            'image_url' => $broadcast->mediaFile?->url,
            'status' => $broadcast->status,
            'last_sent_at' => $broadcast->last_sent_at?->toIso8601String(),
            'sent_count' => $broadcast->sentRecipients()->count(),
        ];
    }

    /**
     * この広告で送信済み（status=sent）の line_user_id 一覧を取得する
     */
    private function sentUserIds(LineBroadcast $broadcast, $lineUserIds)
    {
        if ($lineUserIds->isEmpty()) {
            return collect();
        }

        return LineBroadcastRecipient::query()
            ->where('line_broadcast_id', $broadcast->id)
            ->where('status', LineBroadcastRecipient::STATUS_SENT)
            ->whereIn('line_user_id', $lineUserIds)
            ->pluck('line_user_id');
    }
}
