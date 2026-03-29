<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineMessage;
use App\Models\LineUnknownInboundMessage;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LineUnknownInboxController extends Controller
{
    public function index(Request $request)
    {
        $unassignedOnly = $request->boolean('unassigned');
        $shopId = $request->integer('shop_id') ?: null;

        $query = LineUnknownInboundMessage::query()
            ->with('shop:id,name')
            ->orderByDesc('id');

        if ($unassignedOnly) {
            $query->whereNull('shop_id');
        } elseif ($shopId) {
            $query->where('shop_id', $shopId);
        }

        $rows = $query->limit(500)->get();

        $groups = [];
        foreach ($rows as $row) {
            $key = ($row->shop_id ?? 'null').'|'.$row->line_user_id;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'shop_id' => $row->shop_id,
                    'shop_name' => $row->shop?->name ?? '（店舗未分類）',
                    'line_user_id' => $row->line_user_id,
                    'line_user_id_masked' => $this->maskUserId($row->line_user_id),
                    'message_count' => 0,
                    'last_text' => $row->text ?? '[テキスト以外]',
                    'last_at' => $row->created_at?->toIso8601String(),
                ];
            }
            $groups[$key]['message_count']++;
        }

        $groups = array_values($groups);

        usort($groups, function ($a, $b) {
            return strcmp((string) $b['last_at'], (string) $a['last_at']);
        });

        return Inertia::render('Admin/LineUnknownInbox/Index', [
            'groups' => $groups,
            'shops' => Shop::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'shop_id' => $shopId,
                'unassigned' => $unassignedOnly,
            ],
        ]);
    }

    public function showGroup(Request $request)
    {
        $validated = $request->validate([
            'line_user_id' => 'required|string|max:64',
            'shop_id' => 'nullable|exists:shops,id',
        ]);

        $messagesQuery = LineUnknownInboundMessage::query()
            ->where('line_user_id', $validated['line_user_id']);

        if (! empty($validated['shop_id'])) {
            $messagesQuery->where('shop_id', $validated['shop_id']);
        } else {
            $messagesQuery->whereNull('shop_id');
        }

        $messages = $messagesQuery
            ->orderBy('id')
            ->get(['id', 'text', 'line_message_id', 'created_at']);

        $shop = ! empty($validated['shop_id'])
            ? Shop::query()->find($validated['shop_id'])
            : null;

        return Inertia::render('Admin/LineUnknownInbox/Show', [
            'shop' => $shop ? ['id' => $shop->id, 'name' => $shop->name] : null,
            'line_user_id' => $validated['line_user_id'],
            'line_user_id_masked' => $this->maskUserId($validated['line_user_id']),
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'text' => $m->text,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function linkToCustomer(Request $request)
    {
        $validated = $request->validate([
            'line_user_id' => 'required|string|max:64',
            'unknown_shop_id' => 'nullable|exists:shops,id',
            'customer_id' => 'required|exists:customers,id',
            'label' => 'nullable|string|max:50',
        ]);

        $customer = Customer::query()->findOrFail($validated['customer_id']);
        if (! $customer->shop_id) {
            return redirect()->back()->withErrors(['customer_id' => '顧客に担当店舗が設定されていません。']);
        }

        $label = $validated['label'] ?? '';
        $label = $label !== '' ? mb_substr($label, 0, 50) : 'お客様';

        $unknownsQuery = LineUnknownInboundMessage::query()
            ->where('line_user_id', $validated['line_user_id']);

        if ($request->filled('unknown_shop_id')) {
            $unknownsQuery->where('shop_id', $validated['unknown_shop_id']);
        } else {
            $unknownsQuery->whereNull('shop_id');
        }

        $unknowns = $unknownsQuery->orderBy('id')->get();

        if ($unknowns->isEmpty()) {
            return redirect()->back()->withErrors(['line' => '対象の不明メッセージが見つかりません。']);
        }

        $existing = CustomerLineContact::query()
            ->where('line_user_id', $validated['line_user_id'])
            ->first();

        if ($existing && (int) $existing->customer_id !== (int) $customer->id) {
            return redirect()->back()->withErrors(['customer_id' => 'この LINE は既に別の顧客に紐づいています。']);
        }

        DB::transaction(function () use ($validated, $customer, $label, $unknowns) {
            $contact = CustomerLineContact::query()->updateOrCreate(
                [
                    'line_user_id' => $validated['line_user_id'],
                ],
                [
                    'customer_id' => $customer->id,
                    'shop_id' => $customer->shop_id,
                    'label' => $label,
                ]
            );

            foreach ($unknowns as $u) {
                try {
                    CustomerLineMessage::query()->create([
                        'customer_line_contact_id' => $contact->id,
                        'direction' => CustomerLineMessage::DIRECTION_INBOUND,
                        'message_type' => $u->text !== null ? 'text' : 'other',
                        'text' => $u->text,
                        'line_message_id' => $u->line_message_id,
                        'payload' => $u->raw_event,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // line_message_id の重複などはスキップ（既に取り込み済み）
                }
                $u->delete();
            }
        });

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'LINE を顧客に紐づけ、メッセージを取り込みました。');
    }

    private function maskUserId(string $id): string
    {
        if (strlen($id) <= 8) {
            return '****';
        }

        return substr($id, 0, 4).'…'.substr($id, -4);
    }
}
