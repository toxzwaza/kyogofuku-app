<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerNote;
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
use App\Models\ConstraintTemplate;
use App\Models\CustomerConstraint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

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

        if ($request->filled('created_at_from')) {
            $query->whereDate('created_at', '>=', $request->created_at_from);
        }

        if ($request->filled('created_at_to')) {
            $query->whereDate('created_at', '<=', $request->created_at_to);
        }

        // 登録日＋成約情報の店舗で絞り込み（本日登録など）
        if (($request->filled('created_at_from') || $request->filled('created_at_to')) && $request->filled('shop_id')) {
            $query->whereHas('contracts', fn($q) => $q->where('shop_id', $request->shop_id));
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

        // 成約情報での検索（成約情報の店舗で絞り込み）
        if ($request->filled('contract_date_from') || $request->filled('contract_date_to') 
            || $request->filled('shop_id') || $request->filled('plan_id') 
            || $request->filled('kimono_type') || $request->has('warranty_flag') 
            || $request->filled('user_id') || $request->filled('preparation_venue')
            || $request->filled('preparation_date') || $request->filled('contract_status')) {
            
            $query->whereHas('contracts', function ($q) use ($request) {
                if ($request->filled('shop_id')) {
                    $q->where('shop_id', $request->shop_id);
                }
                if ($request->filled('contract_date_from')) {
                    $q->where('contract_date', '>=', $request->contract_date_from);
                }
                if ($request->filled('contract_date_to')) {
                    $q->where('contract_date', '<=', $request->contract_date_to);
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
                if ($request->filled('contract_status')) {
                    $q->where('status', $request->contract_status);
                }
            });
        }

        // 前撮り詳細未決定での検索（前撮り情報の担当店舗で絞り込み）
        if ($request->filled('photo_slot_details_undecided') || $request->filled('photo_slot_shop_id')) {
            if ($request->filled('photo_slot_details_undecided')) {
                if ($request->boolean('photo_slot_details_undecided')) {
                    // 詳細未決定の前撮りを持つ顧客（担当店舗で絞る場合は該当店舗の前撮りのみ）
                    $query->whereHas('photoSlots', function ($q) use ($request) {
                        $q->where('details_undecided', true);
                        if ($request->filled('photo_slot_shop_id')) {
                            $q->whereHas('shops', fn($sq) => $sq->where('shops.id', $request->photo_slot_shop_id));
                        }
                    });
                } else {
                    // 詳細未決定の前撮りを持たない顧客（詳細確定のみ、または前撮りなし）
                    $query->whereDoesntHave('photoSlots', fn($q) => $q->where('details_undecided', true));
                }
            } elseif ($request->filled('photo_slot_shop_id')) {
                // 前撮り担当店舗のみで絞り込み
                $query->whereHas('photoSlots', fn($q) => $q->whereHas('shops', fn($sq) => $sq->where('shops.id', $request->photo_slot_shop_id)));
            }
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
                    'remarks' => '',
                    'email' => $reservation->email,
                    'referred_by_name' => $reservation->referred_by_name,
                    'school_name' => $reservation->school_name,
                    'staff_name' => $reservation->staff_name,
                    'visit_reasons' => $reservation->visit_reasons ?? [],
                    'considering_plans' => $reservation->considering_plans ?? [],
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
                'created_at_from', 'created_at_to',
                'contract_date_from', 'contract_date_to', 'shop_id', 'plan_id',
                'kimono_type', 'warranty_flag', 'user_id', 'preparation_venue', 'preparation_date',
                'contract_status', 'photo_slot_details_undecided', 'photo_slot_shop_id'
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
                ->select('id', 'name', 'phone_number', 'email', 'ceremony_area_id')
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone_number' => $customer->phone_number,
                        'email' => $customer->email,
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
            'email' => 'nullable|email|max:255',
            'referred_by_name' => 'nullable|string|max:255',
            'school_name' => 'nullable|string|max:255',
            'staff_name' => 'nullable|string|max:255',
            'visit_reasons' => 'nullable|array',
            'visit_reasons.*' => 'nullable|string|max:255',
            'considering_plans' => 'nullable|array',
            'considering_plans.*' => 'nullable|string|max:255',
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
            'constraints.constraintTemplate.shops',
            'constraints.explainerUser',
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

        // 制約テンプレート一覧（有効、店舗・スタッフ付き）
        $constraintTemplates = ConstraintTemplate::where('is_active', true)
            ->with(['shops' => fn($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(function ($t) {
                $shopIds = $t->shops->pluck('id')->toArray();
                $staff = User::whereHas('shops', fn($q) => $q->whereIn('shops.id', $shopIds))
                    ->orderBy('name')
                    ->get(['id', 'name']);
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'body' => $t->body,
                    'shops' => $t->shops->map(fn($s) => ['id' => $s->id, 'name' => $s->name]),
                    'staff' => $staff->map(fn($u) => ['id' => $u->id, 'name' => $u->name]),
                ];
            });

        // 顧客写真に表示用 URL を付与（s3=署名URL、public=ローカルURL）
        $customerForInertia = $customer->toArray();
        $customerForInertia['photos'] = $customer->photos->map(function ($photo) {
            $item = $photo->toArray();
            if (($photo->storage_disk ?? 'public') === 's3') {
                $path = str_replace('\\', '/', $photo->file_path); // S3 キーは常に /
                $item['url'] = Storage::disk('s3_private')->temporaryUrl($path, now()->addMinutes(60));
            } else {
                $item['url'] = '/storage/' . $photo->file_path;
            }
            return $item;
        })->values()->all();

        return Inertia::render('Admin/Customer/Show', [
            'customer' => $customerForInertia,
            'notes' => $customer->notes()->with('user')->orderBy('created_at', 'desc')->get(),
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
            'constraintTemplates' => $constraintTemplates,
        ]);
    }

    /**
     * 追加情報（振袖アンケート）入力フォームを表示
     */
    public function additionalInfoForm(Customer $customer)
    {
        $initial = $this->buildAdditionalInfoInitial($customer);
        $initial['staff_name'] = auth()->user()?->name ?? '';
        return Inertia::render('Admin/Customer/AdditionalInfo', [
            'customer' => $customer->only('id', 'name', 'kana'),
            'initial' => $initial,
        ]);
    }

    /**
     * 追加情報の初期値（顧客マスタ＋既存 additional_info）を組み立て
     */
    private function buildAdditionalInfoInitial(Customer $customer): array
    {
        $saved = is_array($customer->additional_info) ? $customer->additional_info : [];
        $fromCustomer = [];

        $fromCustomer['name_daughter'] = $customer->name ?? '';
        $fromCustomer['furigana_daughter'] = $customer->kana ?? '';
        $fromCustomer['name_mother'] = $customer->guardian_name ?? '';
        $fromCustomer['furigana_mother'] = $customer->guardian_name_kana ?? '';

        $today = now();
        if (! isset($saved['entry_date_year']) && ! isset($saved['entry_date_month']) && ! isset($saved['entry_date_day'])) {
            $fromCustomer['entry_date_year'] = (string) $today->year;
            $fromCustomer['entry_date_month'] = (string) $today->month;
            $fromCustomer['entry_date_day'] = (string) $today->day;
        }

        if ($customer->birth_date) {
            $d = \Carbon\Carbon::parse($customer->birth_date);
            $fromCustomer['birth_year'] = (string) $d->year;
            $fromCustomer['birth_month'] = (string) $d->month;
            $fromCustomer['birth_day'] = (string) $d->day;
        }

        $fromCustomer['address'] = $customer->address ?? '';
        $fromCustomer['postal_code'] = $customer->postal_code ?? '';
        if ($customer->phone_number) {
            $digits = preg_replace('/\D/', '', $customer->phone_number);
            $fromCustomer['phone_home_1'] = substr($digits, 0, 3);
            $fromCustomer['phone_home_2'] = substr($digits, 3, 4);
            $fromCustomer['phone_home_3'] = substr($digits, 7, 4);
        }
        $fromCustomer['visit_reasons'] = $this->getVisitReasonsWithoutOther($customer->visit_reasons ?? []);
        $fromCustomer['visit_reason_other'] = $this->extractVisitReasonOther($customer->visit_reasons ?? []);

        $initial = array_merge($this->defaultAdditionalInfoKeys(), $fromCustomer, $saved);

        if (isset($initial['sisters']) && is_array($initial['sisters'])) {
            // already array
        } elseif (
            ! empty($initial['sister1_name']) || ! empty($initial['sister2_name']) || ! empty($initial['sister3_name'])
        ) {
            $sisters = [];
            foreach ([1, 2, 3] as $i) {
                $sisters[] = [
                    'name' => $initial["sister{$i}_name"] ?? '',
                    'year' => $initial["sister{$i}_year"] ?? '',
                    'month' => $initial["sister{$i}_month"] ?? '',
                    'day' => $initial["sister{$i}_day"] ?? '',
                ];
            }
            $initial['sisters'] = $sisters;
        }

        return $initial;
    }

    /**
     * 追加情報フォームの全キーとデフォルト値
     */
    private function defaultAdditionalInfoKeys(): array
    {
        return [
            'entry_date_year' => '', 'entry_date_month' => '', 'entry_date_day' => '',
            'name_daughter' => '', 'furigana_daughter' => '',
            'name_mother' => '', 'furigana_mother' => '',
            'birth_year' => '', 'birth_month' => '', 'birth_day' => '',
            'height' => '', 'foot_size' => '',
            'postal_code' => '', 'address' => '',
            'phone_home_1' => '', 'phone_home_2' => '', 'phone_home_3' => '',
            'phone_daughter_1' => '', 'phone_daughter_2' => '', 'phone_daughter_3' => '',
            'phone_mother_1' => '', 'phone_mother_2' => '', 'phone_mother_3' => '',
            'color' => [], 'color_other' => '', 'hobby' => [], 'hobby_other' => '', 'sports_detail' => '', 'furisode_image' => '',
            'graduation_year' => '', 'hakama' => '',
            'plan' => '', 'option' => [], 'price' => [],
            'university' => '', 'college' => '', 'parttime' => '', 'work' => '', 'other_status' => '',
            'sisters' => [],
            'visit_reasons' => [], 'visit_reason_other' => '',
            'staff_name' => '',
        ];
    }

    /**
     * 来店動機を処理（「その他」の場合はテキスト入力も含める）予約フォームと同一
     */
    private function processVisitReasons(?array $visitReasons, ?string $visitReasonOther): array
    {
        if (! $visitReasons || ! is_array($visitReasons)) {
            return [];
        }
        $reasons = [];
        foreach ($visitReasons as $reason) {
            if ($reason === 'その他' && $visitReasonOther !== null && $visitReasonOther !== '') {
                $reasons[] = 'その他(' . $visitReasonOther . ')';
            } else {
                $reasons[] = $reason;
            }
        }
        return array_values(array_filter($reasons));
    }

    /**
     * 既存の来店動機から「その他」のテキストを抽出
     */
    private function extractVisitReasonOther($visitReasons): string
    {
        if (! is_array($visitReasons)) {
            return '';
        }
        foreach ($visitReasons as $r) {
            if (is_string($r) && preg_match('/^その他\((.+)\)$/u', $r, $m)) {
                return $m[1];
            }
        }
        return '';
    }

    /**
     * 既存の来店動機から「その他」を除いた配列を取得（「その他(テキスト)」は「その他」に正規化）
     */
    private function getVisitReasonsWithoutOther($visitReasons): array
    {
        if (! is_array($visitReasons)) {
            return [];
        }
        $reasons = [];
        $hasOther = false;
        foreach ($visitReasons as $r) {
            if (! is_string($r)) {
                continue;
            }
            if (str_starts_with($r, 'その他(')) {
                if (! $hasOther) {
                    $reasons[] = 'その他';
                    $hasOther = true;
                }
            } else {
                $reasons[] = $r;
            }
        }
        return $reasons;
    }

    /**
     * 追加情報を保存してサンクスへリダイレクト
     * 顧客テーブルに存在する項目は customer を更新し、それ以外のみ additional_info に保存する
     */
    public function storeAdditionalInfo(Request $request, Customer $customer)
    {
        $payload = $request->validate([
            'additional_info' => 'required|array',
        ])['additional_info'];

        $customerUpdate = [];
        $customerUpdate['name'] = $payload['name_daughter'] ?? $customer->name;
        $customerUpdate['kana'] = $payload['furigana_daughter'] ?? $customer->kana;
        $customerUpdate['guardian_name'] = $payload['name_mother'] ?? $customer->guardian_name;
        $customerUpdate['guardian_name_kana'] = $payload['furigana_mother'] ?? $customer->guardian_name_kana;
        $customerUpdate['postal_code'] = $payload['postal_code'] ?? $customer->postal_code;
        $customerUpdate['address'] = $payload['address'] ?? $customer->address;
        $customerUpdate['staff_name'] = $payload['staff_name'] ?? $customer->staff_name;

        $visitReasons = $payload['visit_reasons'] ?? [];
        $visitReasonOther = $payload['visit_reason_other'] ?? '';
        $customerUpdate['visit_reasons'] = $this->processVisitReasons(
            is_array($visitReasons) ? $visitReasons : [],
            $visitReasonOther
        );

        if (! empty($payload['birth_year']) || ! empty($payload['birth_month']) || ! empty($payload['birth_day'])) {
            $y = (int) ($payload['birth_year'] ?? 0);
            $m = (int) ($payload['birth_month'] ?? 1);
            $d = (int) ($payload['birth_day'] ?? 1);
            if ($y && $m && $d && checkdate($m, $d, $y)) {
                $customerUpdate['birth_date'] = sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }

        $ph1 = $payload['phone_home_1'] ?? '';
        $ph2 = $payload['phone_home_2'] ?? '';
        $ph3 = $payload['phone_home_3'] ?? '';
        if ($ph1 !== '' || $ph2 !== '' || $ph3 !== '') {
            $customerUpdate['phone_number'] = implode('-', array_filter([$ph1, $ph2, $ph3]));
        }

        $onlyAdditionalInfoKeys = [
            'entry_date_year', 'entry_date_month', 'entry_date_day',
            'height', 'foot_size',
            'phone_daughter_1', 'phone_daughter_2', 'phone_daughter_3',
            'phone_mother_1', 'phone_mother_2', 'phone_mother_3',
            'color', 'hobby', 'sports_detail', 'furisode_image',
            'graduation_year', 'hakama', 'plan', 'option', 'price',
            'university', 'college', 'parttime', 'work', 'other_status',
            'sisters',
        ];
        $additionalInfoOnly = array_intersect_key($payload, array_fill_keys($onlyAdditionalInfoKeys, true));

        $customer->update(array_merge($customerUpdate, ['additional_info' => $additionalInfoOnly]));
        return redirect()->route('admin.customers.additional-info.thanks', $customer)
            ->with('success', '追加情報を保存しました。');
    }

    /**
     * 追加情報入力完了（サンクス）ページ
     */
    public function additionalInfoThanks(Customer $customer)
    {
        return Inertia::render('Admin/Customer/AdditionalInfoThanks', [
            'customer' => $customer->only('id', 'name'),
        ]);
    }

    /**
     * 顧客メモを追加
     */
    public function storeNote(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        CustomerNote::create([
            'user_id' => auth()->id(),
            'customer_id' => $customer->id,
            'content' => $validated['content'],
        ]);

        return redirect()->back()->with('success', 'メモを追加しました。');
    }

    /**
     * 顧客メモを削除
     */
    public function destroyNote(CustomerNote $note)
    {
        $note->delete();

        return redirect()->back()->with('success', 'メモを削除しました。');
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
            'status' => 'required|in:保留,確定,キャンセル',
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
     * 成約情報を更新
     */
    public function updateContract(Request $request, Customer $customer, Contract $contract)
    {
        if ($contract->customer_id !== $customer->id) {
            abort(403);
        }

        $validated = $request->validate([
            'shop_id' => 'required|exists:shops,id',
            'plan_id' => 'required|exists:plans,id',
            'contract_date' => 'required|date',
            'kimono_type' => 'required|in:振袖,袴',
            'status' => 'required|in:保留,確定,キャンセル',
            'warranty_flag' => 'boolean',
            'total_amount' => 'nullable|integer|min:0',
            'preparation_venue' => 'nullable|string|max:255',
            'preparation_date' => 'nullable|date',
            'user_id' => 'nullable|exists:users,id',
            'remarks' => 'nullable|string',
        ]);

        $validated['warranty_flag'] = $request->has('warranty_flag') ? (bool)$request->warranty_flag : false;

        $contract->update($validated);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', '成約情報を更新しました。');
    }

    /**
     * 前撮り情報を追加
     */
    public function storePhotoSlot(Request $request, Customer $customer)
    {
        $detailsUndecided = $request->boolean('details_undecided');

        $rules = [
            'details_undecided' => 'boolean',
            'assignment_label' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'plan_id' => 'nullable|exists:plans,id',
            'remarks' => 'nullable|string',
        ];

        if ($detailsUndecided) {
            $rules['shop_id'] = 'required|exists:shops,id';
        } else {
            $rules['photo_slot_id'] = 'required|exists:photo_slots,id';
            $rules['shop_id'] = 'nullable|exists:shops,id';
        }

        $validated = $request->validate($rules);

        if ($detailsUndecided) {
            // 詳細未決定: 新規PhotoSlotを作成（会場・日時はnull）
            $photoSlot = PhotoSlot::create([
                'customer_id' => $customer->id,
                'details_undecided' => true,
                'photo_studio_id' => null,
                'shoot_date' => null,
                'shoot_time' => null,
                'assignment_label' => $validated['assignment_label'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
                'plan_id' => $validated['plan_id'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);
            $photoSlot->shops()->sync([$validated['shop_id']]);
        } else {
            // 通常: 既存枠に顧客を割り当て
            $photoSlot = PhotoSlot::findOrFail($validated['photo_slot_id']);

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

            if (!empty($validated['shop_id'])) {
                $photoSlot->shops()->sync([$validated['shop_id']]);
            } else {
                $photoSlot->shops()->detach();
            }
        }

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', '前撮り情報を追加しました。');
    }

    /**
     * 前撮り情報を更新
     */
    public function updatePhotoSlot(Request $request, Customer $customer, PhotoSlot $photoSlot)
    {
        if ($photoSlot->customer_id !== $customer->id) {
            abort(403);
        }

        $detailsUndecided = $request->boolean('details_undecided');

        $rules = [
            'details_undecided' => 'boolean',
            'shop_id' => $detailsUndecided ? 'required|exists:shops,id' : 'nullable|exists:shops,id',
            'assignment_label' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'plan_id' => 'nullable|exists:plans,id',
            'remarks' => 'nullable|string',
        ];

        if (!$detailsUndecided) {
            $rules['photo_slot_id'] = 'required|exists:photo_slots,id';
        }

        $validated = $request->validate($rules);

        $updateData = [
            'assignment_label' => $validated['assignment_label'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'plan_id' => $validated['plan_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ];

        if ($detailsUndecided) {
            // 詳細未決定に変更: 会場・日時をnullにして仮枠に
            $updateData['details_undecided'] = true;
            $updateData['photo_studio_id'] = null;
            $updateData['shoot_date'] = null;
            $updateData['shoot_time'] = null;
            $photoSlot->update($updateData);
            if (isset($validated['shop_id']) && $validated['shop_id']) {
                $photoSlot->shops()->sync([$validated['shop_id']]);
            } else {
                $photoSlot->shops()->detach();
            }
        } else {
            $targetSlotId = (int) ($validated['photo_slot_id'] ?? 0);
            $targetSlot = PhotoSlot::findOrFail($targetSlotId);

            // 選択枠が自分自身の場合はそのまま更新
            if ($targetSlot->id === $photoSlot->id) {
                $photoSlot->update($updateData);
                if (isset($validated['shop_id']) && $validated['shop_id']) {
                    $photoSlot->shops()->sync([$validated['shop_id']]);
                } else {
                    $photoSlot->shops()->detach();
                }
            } else {
                // 別枠を選択: 既に他顧客がいる場合はエラー
                if ($targetSlot->customer_id !== null) {
                    return back()->withErrors([
                        'photo_slot_id' => 'この前撮り枠は既に他の顧客に割り当てられています。',
                    ]);
                }

                // 対象枠に顧客を移行
                $targetSlot->update(array_merge($updateData, [
                    'customer_id' => $customer->id,
                    'details_undecided' => false,
                ]));
                if (isset($validated['shop_id']) && $validated['shop_id']) {
                    $targetSlot->shops()->sync([$validated['shop_id']]);
                } else {
                    $targetSlot->shops()->detach();
                }

                // 元の枠（詳細未決定など）を削除
                $photoSlot->delete();
            }
        }

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', '前撮り情報を更新しました。');
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
     * 顧客写真を追加（WebP に変換して S3 にのみ保存）
     */
    public function storeCustomerPhoto(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'photo_type_id' => 'required|exists:photo_types,id',
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
            'remarks' => 'nullable|string',
        ]);

        $manager = $this->createImageManager();
        if (! $manager) {
            return redirect()->route('admin.customers.show', $customer)
                ->with('error', 'WebP変換に必要な画像ドライバー（GD/Imagick）が利用できません。');
        }

        $file = $request->file('photo');
        $webpPath = $this->convertUploadToWebpAndPutS3Private($file, (int) $customer->id, $manager);
        if (! $webpPath) {
            return redirect()->route('admin.customers.show', $customer)
                ->with('error', '写真の WebP 変換に失敗しました。');
        }

        CustomerPhoto::create([
            'customer_id' => $customer->id,
            'photo_type_id' => $validated['photo_type_id'],
            'file_path' => $webpPath,
            'storage_disk' => 's3',
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', '写真を追加しました。');
    }

    /**
     * 利用可能なドライバーでImageManagerを作成
     */
    private function createImageManager()
    {
        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            try {
                return new ImageManager(new GdDriver());
            } catch (\Exception $e) {
                Log::warning('GDドライバーの初期化に失敗: ' . $e->getMessage());
            }
        }
        if (extension_loaded('imagick')) {
            try {
                return new ImageManager(new ImagickDriver());
            } catch (\Exception $e) {
                Log::warning('Imagickドライバーの初期化に失敗: ' . $e->getMessage());
            }
        }
        Log::warning('画像処理ドライバー（GD/Imagick）が利用できません。');
        return null;
    }

    /**
     * アップロードファイルを WebP に変換して S3（s3_private）に保存
     * @return string|null 保存した WebP のパス（customers/{id}/{unique}.webp）、失敗時は null
     */
    private function convertUploadToWebpAndPutS3Private($uploadedFile, int $customerId, $manager)
    {
        if (! $manager) {
            return null;
        }
        try {
            $webpPath = 'customers/' . $customerId . '/' . Str::random(40) . '.webp';
            $image = $manager->read($uploadedFile->getRealPath());
            $tmpPath = tempnam(sys_get_temp_dir(), 'webp');
            $image->toWebp(80)->save($tmpPath);
            $content = file_get_contents($tmpPath);
            @unlink($tmpPath);
            Storage::disk('s3_private')->put($webpPath, $content);
            return $webpPath;
        } catch (\Exception $e) {
            Log::error('WebP変換エラー (S3 customers/' . $customerId . '): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 顧客写真を削除
     */
    public function destroyCustomerPhoto(Customer $customer, CustomerPhoto $photo)
    {
        if ($photo->customer_id !== $customer->id) {
            abort(404);
        }

        if ($photo->file_path) {
            $disk = ($photo->storage_disk ?? 'public') === 's3' ? 's3_private' : ($photo->storage_disk ?? 'public');
            $path = $disk === 's3_private' ? str_replace('\\', '/', $photo->file_path) : $photo->file_path;
            Storage::disk($disk)->delete($path);
        }

        $photo->delete();

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', '写真を削除しました。');
    }

    /**
     * 顧客写真を S3 に移行（public → s3）
     */
    public function migrateCustomerPhotoToS3(Customer $customer, CustomerPhoto $photo)
    {
        if ($photo->customer_id !== $customer->id) {
            abort(404);
        }
        if (($photo->storage_disk ?? 'public') === 's3') {
            return redirect()->route('admin.customers.show', $customer)
                ->with('info', 'この写真は既に S3 に保存されています。');
        }

        if (! Storage::disk('public')->exists($photo->file_path)) {
            return redirect()->route('admin.customers.show', $customer)
                ->with('error', '元のファイルが見つかりません。');
        }

        $content = Storage::disk('public')->get($photo->file_path);
        $s3Path = str_replace('\\', '/', $photo->file_path);
        Storage::disk('s3_private')->put($s3Path, $content);
        $photo->update(['storage_disk' => 's3', 'file_path' => $s3Path]);
        Storage::disk('public')->delete($photo->file_path);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', '写真を S3 に移行しました。');
    }

    /**
     * 制約署名フォームを表示
     */
    public function constraintSignForm(Request $request, Customer $customer)
    {
        $templateId = $request->query('template_id');
        $template = ConstraintTemplate::with(['shops' => fn($q) => $q->where('is_active', true)])
            ->findOrFail($templateId);

        // テンプレートに紐づく店舗のスタッフを取得
        $shopIds = $template->shops->pluck('id')->toArray();
        $staff = User::whereHas('shops', fn($q) => $q->whereIn('shops.id', $shopIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        // 選択された説明者
        $explainerId = $request->query('explainer_user_id');
        $explainer = $explainerId ? User::find($explainerId) : null;

        // check_values をJSONデコード
        $checkValues = [];
        if ($request->query('check_values')) {
            $checkValues = json_decode($request->query('check_values'), true) ?? [];
        }

        // 編集モードの場合、既存の制約情報を取得
        $editId = $request->query('edit_id');
        $existingConstraint = null;
        $existingSignature = null;
        if ($editId) {
            $existingConstraint = CustomerConstraint::where('customer_id', $customer->id)
                ->where('id', $editId)
                ->first();
            if ($existingConstraint) {
                $existingSignature = $existingConstraint->signature_image;
            }
        }

        return Inertia::render('Admin/Customer/ConstraintSign', [
            'customer' => $customer->only('id', 'name', 'kana'),
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'body' => $template->body,
                'display_settings' => $template->getDisplaySettings(),
            ],
            'staff' => $staff->map(fn($u) => ['id' => $u->id, 'name' => $u->name]),
            'signedAt' => $request->query('signed_at', date('Y-m-d')),
            'explainerUserId' => $explainerId,
            'explainerName' => $explainer?->name,
            'checkValues' => $checkValues,
            'editId' => $editId,
            'existingSignature' => $existingSignature,
        ]);
    }

    /**
     * 顧客制約を追加
     */
    public function storeCustomerConstraint(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'constraint_template_id' => 'required|exists:constraint_templates,id',
            'signed_at' => 'nullable|date',
            'signature_image' => 'nullable|string',
            'explainer_user_id' => 'nullable|exists:users,id',
            'check_values' => 'nullable|array',
        ]);

        $validated['customer_id'] = $customer->id;

        $constraint = CustomerConstraint::create($validated);

        $query = [
            'template_id' => $validated['constraint_template_id'],
            'edit_id' => $constraint->id,
            'signed_at' => $validated['signed_at'] ?? null,
            'explainer_user_id' => $validated['explainer_user_id'] ?? null,
        ];
        if (! empty($validated['check_values'])) {
            $query['check_values'] = json_encode($validated['check_values']);
        }

        return redirect()->to(route('admin.customers.constraints.sign', $customer) . '?' . http_build_query(array_filter($query)))
            ->with('success', '保存が完了しました。');
    }

    /**
     * 顧客制約を更新
     */
    public function updateCustomerConstraint(Request $request, Customer $customer, CustomerConstraint $customerConstraint)
    {
        if ($customerConstraint->customer_id !== $customer->id) {
            abort(403);
        }

        $validated = $request->validate([
            'signed_at' => 'nullable|date',
            'signature_image' => 'nullable|string',
            'explainer_user_id' => 'nullable|exists:users,id',
            'check_values' => 'nullable|array',
        ]);

        $customerConstraint->update($validated);

        $query = [
            'template_id' => $customerConstraint->constraint_template_id,
            'edit_id' => $customerConstraint->id,
            'signed_at' => $validated['signed_at'] ?? null,
            'explainer_user_id' => $validated['explainer_user_id'] ?? null,
        ];
        if (! empty($validated['check_values'])) {
            $query['check_values'] = json_encode($validated['check_values']);
        }

        return redirect()->to(route('admin.customers.constraints.sign', $customer) . '?' . http_build_query(array_filter($query)))
            ->with('success', '保存が完了しました。');
    }

    /**
     * 顧客制約を削除
     */
    public function destroyCustomerConstraint(Customer $customer, CustomerConstraint $customerConstraint)
    {
        if ($customerConstraint->customer_id !== $customer->id) {
            abort(403);
        }

        $customerConstraint->delete();

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', '制約情報を削除しました。');
    }

    /**
     * 顧客を削除
     */
    public function destroy(Customer $customer)
    {
        // 関連する写真ファイルを削除
        foreach ($customer->photos as $photo) {
            if ($photo->file_path) {
                $disk = ($photo->storage_disk ?? 'public') === 's3' ? 's3_private' : ($photo->storage_disk ?? 'public');
                $path = $disk === 's3_private' ? str_replace('\\', '/', $photo->file_path) : $photo->file_path;
                Storage::disk($disk)->delete($path);
            }
        }

        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('success', '顧客情報を削除しました。');
    }
}

