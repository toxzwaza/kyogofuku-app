<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Contract;
use App\Models\PhotoSlot;
use App\Models\CustomerPhoto;
use App\Models\CeremonyArea;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\User;
use App\Models\PhotoStudio;
use App\Models\PhotoType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $customers = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // フォーム用のマスターデータを取得
        $ceremonyAreas = CeremonyArea::orderBy('name')->get();
        $shops = Shop::orderBy('name')->get();
        $plans = Plan::orderBy('name')->get();
        $users = User::orderBy('name')->get();

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
        ]);
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
        ]);

        $customer = Customer::create($validated);

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
            'photoSlots.studio',
            'photos.type',
        ]);

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

        return Inertia::render('Admin/Customer/Show', [
            'customer' => $customer,
            'ceremonyAreas' => $ceremonyAreas,
            'shops' => $shops,
            'plans' => $plans,
            'users' => $users,
            'photoStudios' => $photoStudios,
            'photoTypes' => $photoTypes,
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
            'photo_studio_id' => 'required|exists:photo_studios,id',
            'shoot_date' => 'required|date',
            'shoot_time' => 'required|date_format:H:i',
            'remarks' => 'nullable|string',
        ]);

        $validated['customer_id'] = $customer->id;

        PhotoSlot::create($validated);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', '前撮り情報を追加しました。');
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
}

