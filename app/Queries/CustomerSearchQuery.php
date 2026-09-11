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

        // 顧客の担当店舗で絞り込み（複数選択可）。未指定（初回アクセス）時はデフォルト店舗。
        // 'all' を含む場合は全店舗（絞り込みなし）。
        if (! $request->has('customer_shop_id')) {
            $customerShopIds = $defaultCustomerShopId !== null ? [$defaultCustomerShopId] : [];
        } elseif (in_array('all', (array) $request->input('customer_shop_id'), true)) {
            $customerShopIds = [];
        } else {
            $customerShopIds = array_values(array_filter(array_map(
                fn ($v) => is_numeric($v) ? (int) $v : null,
                (array) $request->input('customer_shop_id')
            ), fn ($v) => $v !== null));
        }
        if (! empty($customerShopIds)) {
            $query->whereIn('shop_id', $customerShopIds);
        }
        $customerShopFilterValue = empty($customerShopIds) ? 'all' : $customerShopIds;

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
     * 顧客一覧のフィルタとしてUIに返すリクエストキー一覧
     *
     * @return array<int, string>
     */
    public static function filterKeys(): array
    {
        return [
            'name', 'kana', 'ceremony_area_id', 'phone_number',
            'created_at_from', 'created_at_to',
            'seijin_preparation_venue', 'seijin_preparation_time', 'other_store_preparation',
            'other_store_salon_name', 'kimono_ship_date',
            'contract_date_from', 'contract_date_to', 'shop_id', 'plan_id',
            'kimono_type', 'warranty_flag', 'user_id', 'preparation_venue', 'preparation_date',
            'contract_status',
            'constraint_presence', 'constraint_template_id', 'constraint_signed_at_from',
            'constraint_signed_at_to', 'constraint_explainer_user_id',
            'photo_slot_details_undecided', 'photo_slot_shop_id',
            'full_body_photo_presence',
        ];
    }
}
