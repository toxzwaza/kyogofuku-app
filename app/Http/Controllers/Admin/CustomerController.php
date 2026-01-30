<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\EventReservation;
use App\Models\Contract;
use App\Models\PhotoSlot;
use App\Models\CustomerPhoto;
use App\Models\CeremonyArea;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\User;
use App\Models\PhotoStudio;
use App\Models\PhotoType;
use App\Models\StaffSchedule;
use App\Models\CustomerTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CustomerController extends Controller
{
    /**
     * 顧客一覧を表示
     */
    public function index(Request $request)
    {
        $query = Customer::with('ceremonyArea');

        // 顧客情報での検索
        if ($request->filled('name')) {
            $query->where('name', 'LIKE', '%' . $request->name . '%');
        }

        if ($request->filled('kana')) {
            $query->where('kana', 'LIKE', '%' . $request->kana . '%');
        }

        if ($request->filled('ceremony_area_id')) {
            $query->where('ceremony_area_id', $request->ceremony_area_id);
        }

        if ($request->filled('phone_number')) {
            $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
        }

        // 成約情報での検索
        if ($request->filled('contract_date_from') || $request->filled('contract_date_to') 
            || $request->filled('shop_id') || $request->filled('plan_id') 
            || $request->filled('kimono_type') || $request->has('warranty_flag') 
            || $request->filled('user_id') || $request->filled('preparation_venue')
            || $request->filled('preparation_date')) {
            
            $query->whereHas('contracts', function ($q) use ($request) {
                if ($request->filled('contract_date_from')) {
                    $q->where('contract_date', '>=', $request->contract_date_from);
                }
                if ($request->filled('contract_date_to')) {
                    $q->where('contract_date', '<=', $request->contract_date_to);
                }
                if ($request->filled('shop_id')) {
                    $q->where('shop_id', $request->shop_id);
                }
                if ($request->filled('plan_id')) {
                    $q->where('plan_id', $request->plan_id);
                }
                if ($request->filled('kimono_type')) {
                    $q->where('kimono_type', $request->kimono_type);
                }
                if ($request->has('warranty_flag')) {
                    $q->where('warranty_flag', $request->boolean('warranty_flag'));
                }
                if ($request->filled('user_id')) {
                    $q->where('user_id', $request->user_id);
                }
                if ($request->filled('preparation_venue')) {
                    $q->where('preparation_venue', 'LIKE', '%' . $request->preparation_venue . '%');
                }
                if ($request->filled('preparation_date')) {
                    $q->where('preparation_date', $request->preparation_date);
                }
            });
        }

        $customers = $query->with([
            'photos' => function($q) {
                $q->where('photo_type_id', 1)->orderBy('created_at', 'desc')->limit(1);
            },
            'tags'
        ])->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // フォーム用のマスターデータを取得
        $ceremonyAreas = CeremonyArea::orderBy('name')->get();
        $shops = Shop::orderBy('name')->get();
        $plans = Plan::orderBy('name')->get();
        $users = User::orderBy('name')->get();

        // 予約詳細から顧客追加の場合、予約者情報をプリフィル用に渡す
        $prefillFromReservation = null;
        if ($request->filled('add_from_reservation')) {
            $reservation = EventReservation::find($request->add_from_reservation);
            if ($reservation) {
                $prefillFromReservation = [
                    'id' => $reservation->id,
                    'name' => $reservation->name,
                    'kana' => $reservation->furigana,
                    'phone_number' => $reservation->phone,
                    'postal_code' => $reservation->postal_code,
                    'address' => $reservation->address,
                    'birth_date' => $reservation->birth_date?->format('Y-m-d'),
                    'coming_of_age_year' => $reservation->seijin_year,
                    'remarks' => $reservation->inquiry_message,
                ];
            }
        }

        return Inertia::render('Admin/Customer/Index', [
            'customers' => $customers,
            'ceremonyAreas' => $ceremonyAreas,
            'shops' => $shops,
            'plans' => $plans,
            'users' => $users,
            'filters' => $request->only([
                'name', 'kana', 'ceremony_area_id', 'phone_number',
                'contract_date_from', 'contract_date_to', 'shop_id', 'plan_id',
                'kimono_type', 'warranty_flag', 'user_id', 'preparation_venue', 'preparation_date'
            ]),
            'prefillFromReservation' => $prefillFromReservation,
        ]);
    }

    /**
     * 顧客を名前で検索（予約紐づけ用・JSON返却）
     */
    public function search(Request $request)
    {
        $name = $request->query('name', '');
        // スペースを排除して検索（スペースの有無で紐づかないことを防ぐ）
        $nameNormalized = preg_replace('/\s+/', '', $name);
        $customers = [];
        if (strlen($nameNormalized) > 0) {
            $customers = Customer::query()
                ->with('ceremonyArea')
                ->whereRaw("REPLACE(REPLACE(name, ' ', ''), '\t', '') LIKE ?", ['%' . $nameNormalized . '%'])
                ->select('id', 'name', 'phone_number', 'ceremony_area_id')
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone_number' => $customer->phone_number,
                        'ceremony_area' => $customer->ceremonyArea ? ['id' => $customer->ceremonyArea->id, 'name' => $customer->ceremonyArea->name] : null,
                    ];
                });
        }
        return response()->json(['customers' => $customers]);
    }

    /**
     * 顧客情報を追加
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'kana' => 'nullable|string|max:255',
            'guardian_name' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'coming_of_age_year' => 'nullable|integer',
            'ceremony_area_id' => 'nullable|exists:ceremony_areas,id',
            'phone_number' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'event_reservation_id' => 'nullable|exists:event_reservations,id',
        ]);

        $eventReservationId = $validated['event_reservation_id'] ?? null;
        unset($validated['event_reservation_id']);

        $customer = Customer::create($validated);

        if ($eventReservationId) {
            $eventReservation = EventReservation::find($eventReservationId);
            if ($eventReservation) {
                $eventReservation->update(['customer_id' => $customer->id]);
            }
            return redirect()->route('admin.customers.show', $customer)
                ->with('success', '顧客を登録し、予約に紐づけました。');
        }

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', '顧客情報を追加しました。');
    }

    /**
     * 顧客詳細を表示
     */
    public function show(Customer $customer)
    {
        $customer->load([
            'ceremonyArea',
            'contracts.shop',
            'contracts.plan',
            'contracts.user',
            'eventReservations.event',
            'eventReservations.venue',
            'eventReservations.schedule.participantUsers',
            'photoSlots.studio',
            'photoSlots.shops',
            'photoSlots.user',
            'photoSlots.plan',
            'photos.type',
            'tags',
        ]);

        // 参加イベント（予約）を予約日時・作成日の降順でソート
        $customer->setRelation('eventReservations', $customer->eventReservations->sortByDesc(function ($r) {
            return $r->reservation_datetime ?? $r->created_at?->format('Y-m-d H:i:s') ?? $r->created_at;
        })->values());

        // photoSlotsをソート
        $customer->photoSlots = $customer->photoSlots->sortBy([
            ['shoot_date', 'asc'],
            ['shoot_time', 'asc'],
        ])->values();

        // デバッグ: 前撮り情報の取得確認
        Log::info('Customer photoSlots count: ' . $customer->photoSlots->count());
        Log::info('Customer photoSlots data: ' . json_encode($customer->photoSlots->toArray()));

        // フォーム用のマスターデータを取得
        $ceremonyAreas = CeremonyArea::orderBy('name')->get();
        $shops = Shop::orderBy('name')->get();
        $plans = Plan::orderBy('name')->get();
        $users = User::orderBy('name')->get();
        $photoStudios = PhotoStudio::orderBy('name')->get();
        $photoTypes = PhotoType::where('is_active', true)->orderBy('sort_order')->get();

        // ログインユーザーの所属店舗を取得
        $currentUser = auth()->user();
        $userShops = $currentUser ? $currentUser->shops()
            ->where('shops.is_active', true)
            ->select('shops.id', 'shops.name')
            ->orderBy('shops.name')
            ->get() : collect();

        // 事前に登録された前撮り枠（customer_idがnullのもの）を取得
        $availablePhotoSlots = PhotoSlot::whereNull('customer_id')
            ->with(['studio', 'shops', 'plan'])
            ->orderBy('shoot_date', 'asc')
            ->orderBy('shoot_time', 'asc')
            ->get();

        // 顧客タグ一覧を取得（有効なもののみ）
        $customerTags = CustomerTag::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Admin/Customer/Show', [
            'customer' => $customer,
            'ceremonyAreas' => $ceremonyAreas,
            'shops' => $shops,
            'plans' => $plans,
            'users' => $users,
            'photoStudios' => $photoStudios,
            'photoTypes' => $photoTypes,
            'availablePhotoSlots' => $availablePhotoSlots,
            'customerTags' => $customerTags,
            'userShops' => $userShops->map(function($shop) {
                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                ];
            }),
        ]);
    }

    /**
     * 顧客情報を更新
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'kana' => 'nullable|string|max:255',
            'guardian_name' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'coming_of_age_year' => 'nullable|integer',
            'ceremony_area_id' => 'nullable|exists:ceremony_areas,id',
            'phone_number' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $customer->update($validated);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', '顧客情報を更新しました。');
    }

    /**
     * 成約情報を追加
     */
    public function storeContract(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'shop_id' => 'required|exists:shops,id',
            'plan_id' => 'required|exists:plans,id',
            'contract_date' => 'required|date',
            'kimono_type' => 'required|in:振袖,袴',
            'warranty_flag' => 'boolean',
            'total_amount' => 'nullable|integer|min:0',
            'preparation_venue' => 'nullable|string|max:255',
            'preparation_date' => 'nullable|date',
            'user_id' => 'nullable|exists:users,id',
            'remarks' => 'nullable|string',
        ]);

        $validated['customer_id'] = $customer->id;
        $validated['warranty_flag'] = $request->has('warranty_flag') ? (bool)$request->warranty_flag : false;

        Contract::create($validated);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', '成約情報を追加しました。');
    }

    /**
     * 前撮り情報を追加
     */
    public function storePhotoSlot(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'photo_slot_id' => 'required|exists:photo_slots,id',
            'shop_id' => 'nullable|exists:shops,id',
            'assignment_label' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'plan_id' => 'nullable|exists:plans,id',
            'remarks' => 'nullable|string',
        ]);

        $photoSlot = PhotoSlot::findOrFail($validated['photo_slot_id']);
        
        // 既に顧客が割り当てられている場合はエラー
        if ($photoSlot->customer_id !== null) {
            return back()->withErrors([
                'photo_slot_id' => 'この前撮り枠は既に他の顧客に割り当てられています。',
            ]);
        }

        $photoSlot->update([
            'customer_id' => $customer->id,
            'assignment_label' => $validated['assignment_label'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'plan_id' => $validated['plan_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        // 担当店舗を中間テーブルに保存
        if (!empty($validated['shop_id'])) {
            $photoSlot->shops()->sync([$validated['shop_id']]);
        } else {
            $photoSlot->shops()->detach();
        }

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', '前撮り情報を追加しました。');
    }

    /**
     * 顧客にタグを紐づけ
     */
    public function attachTag(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'customer_tag_id' => 'required|exists:customer_tags,id',
            'note' => 'nullable|string',
        ]);

        // 既に紐づいている場合は更新
        if ($customer->tags()->where('customer_tag_id', $validated['customer_tag_id'])->exists()) {
            $customer->tags()->updateExistingPivot($validated['customer_tag_id'], [
                'note' => $validated['note'] ?? null,
            ]);
        } else {
            $customer->tags()->attach($validated['customer_tag_id'], [
                'note' => $validated['note'] ?? null,
            ]);
        }

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', 'タグを紐づけました。');
    }

    /**
     * 顧客からタグを外す
     */
    public function detachTag(Request $request, Customer $customer, CustomerTag $customerTag)
    {
        $customer->tags()->detach($customerTag->id);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', 'タグを外しました。');
    }

    /**
     * 顧客写真を追加
     */
    public function storeCustomerPhoto(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'photo_type_id' => 'required|exists:photo_types,id',
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
            'remarks' => 'nullable|string',
        ]);

        $file = $request->file('photo');
        $path = $file->store('customers/' . $customer->id, 'public');

        CustomerPhoto::create([
            'customer_id' => $customer->id,
            'photo_type_id' => $validated['photo_type_id'],
            'file_path' => $path,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', '写真を追加しました。');
    }

    /**
     * 顧客を削除
     */
    public function destroy(Customer $customer)
    {
        // 関連する写真ファイルを削除
        foreach ($customer->photos as $photo) {
            if ($photo->file_path) {
                Storage::disk('public')->delete($photo->file_path);
            }
        }

        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('success', '顧客情報を削除しました。');
    }
}

