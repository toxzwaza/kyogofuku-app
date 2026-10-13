<?php

namespace App\Queries;

use App\Models\Customer;
use App\Models\PhotoType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 顧客一覧の検索フィルタ（基本情報・成人式情報・成約情報・制約情報・前撮り情報）。
 *
 * 顧客一覧（CustomerController@index）と LINE広告の送信先選択（顧客検索タブ）で
 * 同一の絞り込み条件を共有するために抽出したクエリビルダー。
 */
class CustomerSearchQuery
{
    /**
     * ログインユーザーのデフォルト担当店舗ID（複数所属なら main フラグ優先）
     */
    public static function defaultShopIdFor(?User $user): ?int
    {
        return optional(
            $user?->shops()
                ->where('shops.is_active', true)
                ->orderByDesc('shop_user.main')
                ->orderBy('shops.id')
                ->first()
        )->id;
    }

    /**
     * リクエストの検索条件を適用した顧客クエリを構築する。
     *
     * @return array{query: Builder, customerShopFilterValue: mixed, fullBodyTypeId: int|null}
     */
    public static function build(Request $request, ?int $defaultCustomerShopId): array
    {
        $query = Customer::query();

        // 顧客情報での検索
        if ($request->filled('name')) {
            $query->where('name', 'LIKE', '%'.$request->name.'%');
        }

        if ($request->filled('created_at_from')) {
            $query->whereDate('created_at', '>=', $request->created_at_from);
        }

        if ($request->filled('created_at_to')) {
            $query->whereDate('created_at', '<=', $request->created_at_to);
        }

        // 登録日＋成約情報の店舗で絞り込み（本日登録など）
        if (($request->filled('created_at_from') || $request->filled('created_at_to')) && $request->filled('shop_id')) {
            $query->whereHas('contracts', fn ($q) => $q->where('shop_id', $request->shop_id));
        }

        if ($request->filled('kana')) {
            $query->where('kana', 'LIKE', '%'.$request->kana.'%');
        }

        // 成人式エリア（市町村）で絞り込み（複数選択可）
        if ($request->filled('ceremony_area_id')) {
            $ceremonyAreaIds = array_values(array_filter(array_map(
                fn ($v) => is_numeric($v) ? (int) $v : null,
                (array) $request->input('ceremony_area_id')
            ), fn ($v) => $v !== null));
            if (! empty($ceremonyAreaIds)) {
                $query->whereIn('ceremony_area_id', $ceremonyAreaIds);
            }
        }

        if ($request->filled('phone_number')) {
            $query->where('phone_number', 'LIKE', '%'.$request->phone_number.'%');
        }

        // 生年月日から算出した満年齢での範囲絞り込み（下限/上限は任意・片方のみも可）。
        // 生年月日未登録(NULL)の顧客は年齢条件指定時に自動的に除外される。
        self::applyAgeRange($query, $request->input('age_min'), $request->input('age_max'));

        // 顧客の担当店舗で絞り込み（複数選択可）。未指定（初回アクセス）時はデフォルト店舗。
        // 'all' を含む場合は全店舗（絞り込みなし）。
        // 'none' を含む場合は担当店舗未設定(shop_id IS NULL=本システム導入前の登録顧客)も対象に含める。
        $rawShopFilter = (array) $request->input('customer_shop_id');
        $includeNoShop = in_array('none', $rawShopFilter, true);
        if (! $request->has('customer_shop_id')) {
            $customerShopIds = $defaultCustomerShopId !== null ? [$defaultCustomerShopId] : [];
            $includeNoShop = false;
        } elseif (in_array('all', $rawShopFilter, true)) {
            $customerShopIds = [];
            $includeNoShop = false;
        } else {
            $customerShopIds = array_values(array_filter(array_map(
                fn ($v) => is_numeric($v) ? (int) $v : null,
                $rawShopFilter
            ), fn ($v) => $v !== null));
        }

        // 店舗グループによる強制制限（管理者以外）。
        // 「すべて」や他グループの店舗IDを指定しても、自グループの店舗に必ず限定する。
        // ※「店舗なし」(NULL=導入前登録顧客) はどのグループにも属さないが、全スタッフに表示する方針のため制限対象外。
        $user = $request->user();
        if ($user && ! $user->isAttendanceManager()) {
            $visible = $user->visibleShopIds();
            if (empty($customerShopIds)) {
                // 「すべて」→自グループ全店に限定（ただし店舗なしのみ選択時は NULL 絞り込みだけを行う）
                if (! $includeNoShop) {
                    $customerShopIds = $visible;
                }
            } else {
                $customerShopIds = array_values(array_intersect($customerShopIds, $visible));
                if (empty($customerShopIds) && ! $includeNoShop) {
                    $customerShopIds = $visible;                               // 全て他グループなら自グループへ
                }
            }
        }

        // shop_id 絞り込みの適用。'none' 指定時は NULL を OR で含める。
        if ($includeNoShop) {
            $query->where(function ($q) use ($customerShopIds) {
                if (! empty($customerShopIds)) {
                    $q->whereIn('shop_id', $customerShopIds);
                }
                $q->orWhereNull('shop_id');
            });
        } elseif (! empty($customerShopIds)) {
            $query->whereIn('shop_id', $customerShopIds);
        }

        // UIの選択状態反映値。全店舗=「all」、それ以外は選択ID配列（店舗なし指定時は 'none' を付加）
        if (empty($customerShopIds) && ! $includeNoShop) {
            $customerShopFilterValue = 'all';
        } else {
            $customerShopFilterValue = $customerShopIds;
            if ($includeNoShop) {
                $customerShopFilterValue[] = 'none';
            }
        }

        // 成人式情報（顧客マスタ）
        if ($request->filled('seijin_preparation_venue')) {
            $query->where('seijin_preparation_venue', 'LIKE', '%'.$request->seijin_preparation_venue.'%');
        }
        if ($request->filled('seijin_preparation_time')) {
            $query->where('seijin_preparation_time', 'LIKE', '%'.$request->seijin_preparation_time.'%');
        }
        if ($request->has('other_store_preparation')) {
            $query->where('other_store_preparation', $request->boolean('other_store_preparation'));
        }
        $otherStorePrepOn = $request->has('other_store_preparation') && $request->boolean('other_store_preparation');
        $salonNameFilled = $request->filled('other_store_salon_name');
        if ($otherStorePrepOn && ! $salonNameFilled) {
            $query->where(function ($q) {
                $q->whereNull('other_store_salon_name')
                    ->orWhere('other_store_salon_name', '');
            });
        }
        if ($salonNameFilled) {
            $query->where('other_store_salon_name', 'LIKE', '%'.$request->other_store_salon_name.'%');
        }
        if ($request->filled('kimono_ship_date')) {
            $query->whereDate('kimono_ship_date', $request->kimono_ship_date);
        }

        // 成約情報での検索（成約情報の店舗で絞り込み）
        $contractStatusNone = $request->filled('contract_status') && $request->contract_status === '成約なし';
        if ($contractStatusNone) {
            $query->whereDoesntHave('contracts');
        } elseif (
            $request->filled('contract_date_from') || $request->filled('contract_date_to')
            || $request->filled('contract_amount_min') || $request->filled('contract_amount_max')
            || $request->filled('shop_id') || $request->filled('plan_id')
            || $request->filled('kimono_type') || $request->has('warranty_flag')
            || $request->filled('user_id') || $request->filled('preparation_venue')
            || $request->filled('preparation_date')
            || $request->filled('contract_status')
        ) {
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
                // 成約金額（total_amount）。下限のみ=下限以上、上限のみ=上限以下、両方同値=一致
                if ($request->filled('contract_amount_min')) {
                    $q->where('total_amount', '>=', $request->contract_amount_min);
                }
                if ($request->filled('contract_amount_max')) {
                    $q->where('total_amount', '<=', $request->contract_amount_max);
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
                    $q->where('preparation_venue', 'LIKE', '%'.$request->preparation_venue.'%');
                }
                if ($request->filled('preparation_date')) {
                    $q->where('preparation_date', $request->preparation_date);
                }
                if ($request->filled('contract_status')) {
                    $q->where('status', $request->contract_status);
                }
            });
        }

        // 制約情報での検索
        $constraintNone = $request->filled('constraint_presence') && $request->constraint_presence === '制約なし';
        if ($constraintNone) {
            $query->whereDoesntHave('constraints');
        } elseif (
            $request->filled('constraint_template_id')
            || $request->filled('constraint_signed_at_from')
            || $request->filled('constraint_signed_at_to')
            || $request->filled('constraint_explainer_user_id')
            || ($request->filled('constraint_presence') && $request->constraint_presence === '制約あり')
        ) {
            $query->whereHas('constraints', function ($q) use ($request) {
                if ($request->filled('constraint_template_id')) {
                    $q->where('constraint_template_id', $request->constraint_template_id);
                }
                if ($request->filled('constraint_signed_at_from')) {
                    $q->whereDate('signed_at', '>=', $request->constraint_signed_at_from);
                }
                if ($request->filled('constraint_signed_at_to')) {
                    $q->whereDate('signed_at', '<=', $request->constraint_signed_at_to);
                }
                if ($request->filled('constraint_explainer_user_id')) {
                    $q->where('explainer_user_id', $request->constraint_explainer_user_id);
                }
            });
        }

        // 顧客写真（全身）の有無での検索
        $fullBodyTypeId = PhotoType::where('code', 'full_body')->value('id');
        if ($request->filled('full_body_photo_presence') && $fullBodyTypeId) {
            if ($request->full_body_photo_presence === '写真なし') {
                $query->whereDoesntHave('photos', fn ($q) => $q->where('photo_type_id', $fullBodyTypeId));
            } elseif ($request->full_body_photo_presence === '写真あり') {
                $query->whereHas('photos', fn ($q) => $q->where('photo_type_id', $fullBodyTypeId));
            }
        }

        // 前撮り詳細未決定での検索（前撮り情報の担当店舗で絞り込み）
        if ($request->filled('photo_slot_details_undecided') || $request->filled('photo_slot_shop_id')) {
            if ($request->filled('photo_slot_details_undecided')) {
                if ($request->boolean('photo_slot_details_undecided')) {
                    // 詳細未決定の前撮りを持つ顧客（担当店舗で絞る場合は該当店舗の前撮りのみ）
                    $query->whereHas('photoSlots', function ($q) use ($request) {
                        $q->where('details_undecided', true);
                        if ($request->filled('photo_slot_shop_id')) {
                            $q->whereHas('shops', fn ($sq) => $sq->where('shops.id', $request->photo_slot_shop_id));
                        }
                    });
                } else {
                    // 詳細未決定の前撮りを持たない顧客（詳細確定のみ、または前撮りなし）
                    $query->whereDoesntHave('photoSlots', fn ($q) => $q->where('details_undecided', true));
                }
            } elseif ($request->filled('photo_slot_shop_id')) {
                // 前撮り担当店舗のみで絞り込み
                $query->whereHas('photoSlots', fn ($q) => $q->whereHas('shops', fn ($sq) => $sq->where('shops.id', $request->photo_slot_shop_id)));
            }
        }

        return [
            'query' => $query,
            'customerShopFilterValue' => $customerShopFilterValue,
            'fullBodyTypeId' => $fullBodyTypeId,
        ];
    }

    /**
     * 生年月日(birth_date)から算出した満年齢での範囲絞り込みを適用する。
     *
     * 顧客・イベント予約者で同じ年齢検索ロジックを共有するためのヘルパー。
     * 年齢をクエリ時点の本日基準で birth_date の範囲に変換するため、birth_date 列に
     * インデックスがあればそのまま効く。生年月日未登録(NULL)は比較で除外される。
     *
     * @param  Builder  $query
     * @param  mixed    $ageMin  下限年齢（◯歳以上）。null/空なら無指定
     * @param  mixed    $ageMax  上限年齢（◯歳以下）。null/空なら無指定
     * @param  string   $column  対象カラム名（既定 birth_date）
     */
    public static function applyAgeRange(Builder $query, $ageMin, $ageMax, string $column = 'birth_date'): void
    {
        $today = \Carbon\Carbon::today();

        // 「ageMin 歳以上」＝ 生年月日が (本日 - ageMin 年) 以前
        if ($ageMin !== null && $ageMin !== '' && is_numeric($ageMin)) {
            $query->whereDate($column, '<=', $today->copy()->subYears((int) $ageMin)->toDateString());
        }

        // 「ageMax 歳以下」＝ 生年月日が (本日 - (ageMax+1) 年) の翌日以降
        if ($ageMax !== null && $ageMax !== '' && is_numeric($ageMax)) {
            $query->whereDate($column, '>=', $today->copy()->subYears((int) $ageMax + 1)->addDay()->toDateString());
        }
    }

    /**
     * 顧客一覧のフィルタとしてUIに返すリクエストキー一覧
     *
     * @return array<int, string>
     */
    public static function filterKeys(): array
    {
        return [
            'name', 'kana', 'ceremony_area_id', 'phone_number', 'age_min', 'age_max',
            'created_at_from', 'created_at_to',
            'seijin_preparation_venue', 'seijin_preparation_time', 'other_store_preparation',
            'other_store_salon_name', 'kimono_ship_date',
            'contract_date_from', 'contract_date_to',
            'contract_amount_min', 'contract_amount_max', 'shop_id', 'plan_id',
            'kimono_type', 'warranty_flag', 'user_id', 'preparation_venue', 'preparation_date',
            'contract_status',
            'constraint_presence', 'constraint_template_id', 'constraint_signed_at_from',
            'constraint_signed_at_to', 'constraint_explainer_user_id',
            'photo_slot_details_undecided', 'photo_slot_shop_id',
            'full_body_photo_presence',
        ];
    }
}
