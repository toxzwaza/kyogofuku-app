<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CsvExportLog;
use App\Queries\CustomerSearchQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 顧客一覧のCSV出力。
 * 絞り込みは顧客一覧と同じ CustomerSearchQuery を通すため、一覧の件数・店舗の閲覧制限と出力範囲が一致する。
 */
class CustomerExportController extends Controller
{
    public const COLUMN_LABELS = [
        'id' => '顧客ID',
        'name' => '顧客名',
        'kana' => 'ふりがな',
        'guardian_name' => '保護者名',
        'guardian_name_kana' => '保護者ふりがな',
        'birth_date' => '生年月日',
        'age' => '年齢',
        'coming_of_age_year' => '成人年度',
        'ceremony_area' => '成人式エリア',
        'shop' => '担当店舗',
        'phone_number' => '電話番号',
        'postal_code' => '郵便番号',
        'address' => '住所',
        'email' => 'メールアドレス',
        'school_name' => '学校名',
        'referred_by_name' => '紹介者',
        'staff_name' => '担当者',
        'visit_reasons' => '来店理由',
        'considering_plans' => '検討プラン',
        'seijin_preparation_venue' => '成人式 仕度会場',
        'seijin_preparation_time' => '成人式 仕度時間',
        'other_store_preparation' => '他店お支度',
        'other_store_salon_name' => '他店 美容室名',
        'kimono_ship_date' => '着物発送日',
        'tags' => 'タグ',
        'remarks' => '備考',
        'contract_count' => '成約件数',
        'latest_contract_date' => '最新成約日',
        'latest_contract_status' => '最新成約ステータス',
        'latest_contract_shop' => '最新成約店舗',
        'latest_contract_plan' => '最新成約プラン',
        'latest_contract_amount' => '最新成約金額',
        'latest_contract_user' => '最新成約担当',
        'created_at' => '登録日',
    ];

    public const DEFAULT_COLUMNS = [
        'name',
        'kana',
        'birth_date',
        'coming_of_age_year',
        'ceremony_area',
        'shop',
        'phone_number',
        'postal_code',
        'address',
        'email',
    ];

    private const CONTRACT_COLUMNS = [
        'contract_count',
        'latest_contract_date',
        'latest_contract_status',
        'latest_contract_shop',
        'latest_contract_plan',
        'latest_contract_amount',
        'latest_contract_user',
    ];

    public function csv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'columns' => 'required|array|min:1',
            'columns.*' => 'string',
        ]);

        $columns = array_values(array_intersect(
            array_unique($validated['columns']),
            array_keys(self::COLUMN_LABELS)
        ));
        if (empty($columns)) {
            abort(422, '出力カラムが不正です');
        }

        ['query' => $query] = CustomerSearchQuery::build(
            $request,
            CustomerSearchQuery::defaultShopIdFor($request->user())
        );

        $relations = ['ceremonyArea:id,name', 'shop:id,name'];
        if (in_array('tags', $columns, true)) {
            $relations[] = 'tags:id,name';
        }
        if (array_intersect($columns, self::CONTRACT_COLUMNS)) {
            $relations['contracts'] = fn ($q) => $q
                ->with(['shop:id,name', 'plan:id,name', 'user:id,name'])
                ->orderByDesc('contract_date')
                ->orderByDesc('id');
        }
        $query->with($relations);

        $fileName = 'customers_'.Carbon::now()->format('Ymd_His').'.csv';
        $rowCount = (clone $query)->count();

        CsvExportLog::create([
            'user_id' => $request->user()->id,
            'target' => 'customers',
            'filters' => $request->only([...CustomerSearchQuery::filterKeys(), 'customer_shop_id']),
            'columns' => $columns,
            'row_count' => $rowCount,
            'file_name' => $fileName,
        ]);

        return new StreamedResponse(function () use ($query, $columns) {
            $stream = fopen('php://output', 'w');
            fprintf($stream, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($stream, array_map(fn ($c) => self::COLUMN_LABELS[$c], $columns));

            // 顧客一覧と同じ並び順（登録日の新しい順）
            $query->orderByDesc('created_at')->orderByDesc('id')
                ->chunk(500, function ($rows) use ($stream, $columns) {
                    foreach ($rows as $row) {
                        fputcsv($stream, array_map(
                            fn ($c) => $this->escapeFormula($this->formatCell($row, $c)),
                            $columns
                        ));
                    }
                });

            fclose($stream);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    private function formatCell(Customer $row, string $column): string
    {
        $latest = in_array($column, self::CONTRACT_COLUMNS, true) ? $row->contracts->first() : null;

        switch ($column) {
            case 'birth_date':
                return $row->birth_date ? Carbon::parse($row->birth_date)->format('Y-m-d') : '';
            case 'age':
                return $row->birth_date ? (string) Carbon::parse($row->birth_date)->age : '';
            case 'ceremony_area':
                return (string) optional($row->ceremonyArea)->name;
            case 'shop':
                return (string) optional($row->shop)->name;
            case 'visit_reasons':
            case 'considering_plans':
                $v = $row->{$column};

                return is_array($v) ? implode('; ', $v) : '';
            case 'other_store_preparation':
                return $row->other_store_preparation ? 'あり' : 'なし';
            case 'kimono_ship_date':
                return $row->kimono_ship_date ? $row->kimono_ship_date->format('Y-m-d') : '';
            case 'tags':
                return $row->tags->pluck('name')->implode('; ');
            case 'contract_count':
                return (string) $row->contracts->count();
            case 'latest_contract_date':
                return $latest?->contract_date ? Carbon::parse($latest->contract_date)->format('Y-m-d') : '';
            case 'latest_contract_status':
                return (string) ($latest?->status ?? '');
            case 'latest_contract_shop':
                return (string) optional($latest?->shop)->name;
            case 'latest_contract_plan':
                return (string) optional($latest?->plan)->name;
            case 'latest_contract_amount':
                return $latest && $latest->total_amount !== null ? (string) $latest->total_amount : '';
            case 'latest_contract_user':
                return (string) optional($latest?->user)->name;
            case 'created_at':
                return $row->created_at ? $row->created_at->format('Y-m-d H:i') : '';
            default:
                $v = $row->{$column} ?? '';

                return is_scalar($v) ? (string) $v : '';
        }
    }

    /**
     * Excelで開いたときに数式として実行されないよう、先頭が = + - @ の値には ' を付ける。
     */
    private function escapeFormula(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}
