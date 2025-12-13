<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $csv = __DIR__ . '/csv/contracts.csv';
        
        if (!file_exists($csv)) {
            $this->command->error('CSVファイルが見つかりません: ' . $csv);
            return;
        }

        // CSVファイルを適切に読み込む
        $handle = fopen($csv, 'r');
        if ($handle === false) {
            $this->command->error('CSVファイルを開けませんでした。');
            return;
        }

        // BOMをスキップ（UTF-8 BOMがある場合）
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // ヘッダー行を読み込む
        $header = fgetcsv($handle);
        if ($header === false) {
            $this->command->error('ヘッダー行を読み込めませんでした。');
            fclose($handle);
            return;
        }

        // ヘッダーの前後の空白や引用符を除去
        $header = array_map('trim', $header);
        $header = array_map(function($value) {
            return trim($value, '"');
        }, $header);

        $rowIndex = 1; // ヘッダー行をカウント
        $created = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;
            
            // 空の行をスキップ
            if (empty(array_filter($row))) {
                continue;
            }

            // データ行の前後の空白や引用符を除去
            $row = array_map('trim', $row);
            $row = array_map(function($value) {
                return trim($value, '"');
            }, $row);

            // ヘッダーとデータ行の列数が一致しない場合は調整
            if (count($header) !== count($row)) {
                if (count($row) < count($header)) {
                    $row = array_pad($row, count($header), '');
                } else {
                    $row = array_slice($row, 0, count($header));
                }
            }

            $data = array_combine($header, $row);

            // array_combineが失敗した場合をチェック
            if ($data === false) {
                $skipped++;
                continue;
            }

            try {
                // 必須フィールドのチェック
                if (empty($data['customer_id']) || empty($data['contract_date'])) {
                    $skipped++;
                    continue;
                }

                // データを準備（idは除外）
                $contractData = [
                    'customer_id' => (int)$data['customer_id'],
                    'shop_id' => !empty($data['shop_id']) && strtoupper($data['shop_id']) !== 'NULL' ? (int)$data['shop_id'] : null,
                    'plan_id' => !empty($data['plan_id']) && strtoupper($data['plan_id']) !== 'NULL' ? (int)$data['plan_id'] : null,
                    'contract_date' => $data['contract_date'],
                    'kimono_type' => !empty($data['kimono_type']) && strtoupper($data['kimono_type']) !== 'NULL' ? $data['kimono_type'] : null,
                    'warranty_flag' => !empty($data['warranty_flag']) && strtoupper($data['warranty_flag']) !== 'NULL' ? (bool)(int)$data['warranty_flag'] : false,
                    'total_amount' => !empty($data['total_amount']) && strtoupper($data['total_amount']) !== 'NULL' ? (int)$data['total_amount'] : 0,
                    'preparation_venue' => !empty($data['preparation_venue']) && strtoupper($data['preparation_venue']) !== 'NULL' ? $data['preparation_venue'] : null,
                    'preparation_date' => !empty($data['preparation_date']) && strtoupper($data['preparation_date']) !== 'NULL' ? $data['preparation_date'] : null,
                    'user_id' => !empty($data['user_id']) && strtoupper($data['user_id']) !== 'NULL' ? (int)$data['user_id'] : null,
                    'remarks' => !empty($data['remarks']) && strtoupper($data['remarks']) !== 'NULL' ? $data['remarks'] : null,
                ];

                // created_at, updated_atを設定（CSVから読み込む）
                if (!empty($data['created_at']) && strtoupper($data['created_at']) !== 'NULL') {
                    $contractData['created_at'] = Carbon::parse($data['created_at']);
                }
                if (!empty($data['updated_at']) && strtoupper($data['updated_at']) !== 'NULL') {
                    $contractData['updated_at'] = Carbon::parse($data['updated_at']);
                }

                // 既存のレコードをチェック（customer_idとcontract_dateの組み合わせで）
                $existing = Contract::where('customer_id', $contractData['customer_id'])
                    ->where('contract_date', $contractData['contract_date'])
                    ->first();

                if ($existing) {
                    $existing->update($contractData);
                    $updated++;
                } else {
                    Contract::create($contractData);
                    $created++;
                }
            } catch (\Exception $e) {
                $this->command->error("行 " . $rowIndex . " でエラーが発生しました: " . $e->getMessage());
                $skipped++;
            }
        }

        fclose($handle);

        $this->command->info("契約データのシードが完了しました。");
        $this->command->info("作成: {$created}件, 更新: {$updated}件, スキップ: {$skipped}件");
    }

}
