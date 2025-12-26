<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportContractsFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:import-csv {file=temp/contracts.csv}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CSVファイルから成約データをインポートします';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $fileName = $this->argument('file');
        $csvPath = storage_path('app/public/' . $fileName);
        
        if (!file_exists($csvPath)) {
            $this->error("CSVファイルが見つかりません: {$csvPath}");
            return Command::FAILURE;
        }

        $this->info("CSVファイルを読み込み中: {$csvPath}");

        $contracts = $this->readCsvFile($csvPath);
        
        if (empty($contracts)) {
            $this->error('CSVファイルにデータがありません。');
            return Command::FAILURE;
        }

        $this->info("読み込んだデータ: " . count($contracts) . "件");

        // 顧客ID、shop_id、plan_idを追加
        $this->info("データを処理中...");
        $contracts = $this->enrichContractData($contracts);
        
        // contractsテーブル用のデータを準備
        $contractsForDb = $this->prepareContractsForDb($contracts);

        // データを登録
        $this->info("データベースに登録中...");
        $result = $this->importContracts($contractsForDb);

        $this->newLine();
        $this->info("インポートが完了しました。");
        $this->info("作成: {$result['created']}件");
        $this->info("更新: {$result['updated']}件");
        $this->info("スキップ: {$result['skipped']}件");

        if (!empty($result['errors'])) {
            $this->warn("エラーが発生した行:");
            foreach ($result['errors'] as $error) {
                $this->warn("  - {$error}");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * CSVファイルを読み込む
     */
    private function readCsvFile($csvPath)
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        // BOMをスキップ（UTF-8 BOMがある場合）
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // ヘッダー行を読み込む
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return [];
        }

        // ヘッダーの前後の空白や引用符を除去
        $header = array_map('trim', $header);
        $header = array_map(function($value) {
            return trim($value, '"');
        }, $header);

        $contracts = [];
        $rowIndex = 1;

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
            
            if ($data === false) {
                continue;
            }

            // 行番号を追加
            $data['_row_index'] = $rowIndex;

            $contracts[] = $data;
        }

        fclose($handle);

        return $contracts;
    }

    /**
     * 契約データに顧客ID、shop_id、plan_idを追加
     */
    private function enrichContractData($contracts)
    {
        // プラン名とIDのマッピング
        $planMapping = [
            'R8・レンタル' => 4,
            'R8・購入' => 3,
            'R8・フォトＲ' => 9,
            'R8・ママＳ' => 10,
            'R8・ママR' => 10,
            'R8・S' => 10,
            'R8・袴R' => 1,
        ];

        // すべての顧客名と店舗名を収集
        $customerNames = [];
        $shopNames = [];
        
        foreach ($contracts as $contract) {
            if (!empty($contract['customer_id'])) {
                $customerNames[] = $contract['customer_id'];
            }
            if (!empty($contract['shop_id'])) {
                $shopNames[] = $contract['shop_id'];
            }
        }

        // 重複を除去
        $customerNames = array_unique($customerNames);
        $shopNames = array_unique($shopNames);

        // 一度のクエリで顧客データを取得（name => id のマッピング）
        $customerMap = [];
        if (!empty($customerNames)) {
            $customers = Customer::whereIn('name', $customerNames)->get();
            foreach ($customers as $customer) {
                $customerMap[$customer->name] = $customer->id;
            }
        }

        // 一度のクエリで店舗データを取得（name => id のマッピング）
        $shopMap = [];
        if (!empty($shopNames)) {
            $shops = Shop::whereIn('name', $shopNames)->get();
            foreach ($shops as $shop) {
                $shopMap[$shop->name] = $shop->id;
            }
        }

        // 契約データにIDを追加
        foreach ($contracts as &$contract) {
            // 顧客IDをマッピングから取得
            if (!empty($contract['customer_id']) && isset($customerMap[$contract['customer_id']])) {
                $contract['_customer_id'] = $customerMap[$contract['customer_id']];
            } else {
                $contract['_customer_id'] = null;
            }

            // shop_idをマッピングから取得
            if (!empty($contract['shop_id']) && isset($shopMap[$contract['shop_id']])) {
                $contract['_shop_id'] = $shopMap[$contract['shop_id']];
            } else {
                $contract['_shop_id'] = null;
            }

            // plan_idをマッピングから取得
            if (!empty($contract['plan_id']) && isset($planMapping[$contract['plan_id']])) {
                $contract['_plan_id'] = $planMapping[$contract['plan_id']];
                
                // プランが「R8・袴R」の場合、着物種別を「袴」に設定
                if ($contract['plan_id'] === 'R8・袴R') {
                    $contract['kimono_type'] = '袴';
                }
            } else {
                $contract['_plan_id'] = null;
            }
        }

        return $contracts;
    }

    /**
     * contractsテーブルに格納するためのデータを準備
     */
    private function prepareContractsForDb($contracts)
    {
        $contractsForDb = [];

        foreach ($contracts as $contract) {
            $dbData = [
                'customer_id' => $contract['_customer_id'] ?? null,
                'shop_id' => $contract['_shop_id'] ?? null,
                'plan_id' => $contract['_plan_id'] ?? null,
                'contract_date' => !empty($contract['contract_date']) ? $contract['contract_date'] : null,
                'kimono_type' => !empty($contract['kimono_type']) ? $contract['kimono_type'] : null,
                'warranty_flag' => isset($contract['warranty_flag']) && ($contract['warranty_flag'] === '1' || $contract['warranty_flag'] === 1),
                'total_amount' => !empty($contract['total_amount']) && $contract['total_amount'] !== '0' ? (int)$contract['total_amount'] : null,
                'preparation_venue' => !empty($contract['preparation_venue']) ? $contract['preparation_venue'] : null,
                'preparation_date' => !empty($contract['preparation_date']) ? $contract['preparation_date'] : null,
                'user_id' => !empty($contract['user_id']) ? $contract['user_id'] : null,
                'remarks' => !empty($contract['remarks']) ? $contract['remarks'] : null,
            ];

            // 行番号を保持（エラー表示用）
            $dbData['_row_index'] = $contract['_row_index'] ?? null;

            $contractsForDb[] = $dbData;
        }

        return $contractsForDb;
    }

    /**
     * contractsテーブルにデータを登録
     */
    private function importContracts($contracts)
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        // すべてのIDを収集
        $customerIds = [];
        $shopIds = [];
        $planIds = [];
        $userIds = [];
        $contractKeys = []; // customer_id + contract_date の組み合わせ

        foreach ($contracts as $contractData) {
            if (!empty($contractData['customer_id'])) {
                $customerIds[] = $contractData['customer_id'];
            }
            if (!empty($contractData['shop_id'])) {
                $shopIds[] = $contractData['shop_id'];
            }
            if (!empty($contractData['plan_id'])) {
                $planIds[] = $contractData['plan_id'];
            }
            if (!empty($contractData['user_id'])) {
                $userIds[] = $contractData['user_id'];
            }
            if (!empty($contractData['customer_id']) && !empty($contractData['contract_date'])) {
                $contractKeys[] = [
                    'customer_id' => $contractData['customer_id'],
                    'contract_date' => $contractData['contract_date'],
                ];
            }
        }

        // 重複を除去
        $customerIds = array_unique($customerIds);
        $shopIds = array_unique($shopIds);
        $planIds = array_unique($planIds);
        $userIds = array_unique($userIds);

        // 一度のクエリで必要なデータをすべて取得
        $customerMap = [];
        if (!empty($customerIds)) {
            $customers = Customer::whereIn('id', $customerIds)->get();
            foreach ($customers as $customer) {
                $customerMap[$customer->id] = true;
            }
        }

        $shopMap = [];
        if (!empty($shopIds)) {
            $shops = Shop::whereIn('id', $shopIds)->get();
            foreach ($shops as $shop) {
                $shopMap[$shop->id] = true;
            }
        }

        $planMap = [];
        if (!empty($planIds)) {
            $plans = Plan::whereIn('id', $planIds)->get();
            foreach ($plans as $plan) {
                $planMap[$plan->id] = true;
            }
        }

        $userMap = [];
        if (!empty($userIds)) {
            $users = User::whereIn('id', $userIds)->get();
            foreach ($users as $user) {
                $userMap[$user->id] = true;
            }
        }

        // 既存のcontractsを一度に取得
        $existingContractsMap = [];
        if (!empty($contractKeys)) {
            $customerIdsForQuery = array_unique(array_column($contractKeys, 'customer_id'));
            $contractDatesForQuery = array_unique(array_column($contractKeys, 'contract_date'));
            
            $existingContracts = Contract::whereIn('customer_id', $customerIdsForQuery)
                ->whereIn('contract_date', $contractDatesForQuery)
                ->get();
            
            foreach ($existingContracts as $existing) {
                $key = $existing->customer_id . '|' . $existing->contract_date;
                $existingContractsMap[$key] = $existing;
            }
        }

        DB::beginTransaction();

        try {
            $contractsToInsert = [];
            $contractsToUpdate = [];

            $bar = $this->output->createProgressBar(count($contracts));
            $bar->start();

            foreach ($contracts as $index => $contractData) {
                try {
                    // plan_idがnullの場合はスキップ
                    if (is_null($contractData['plan_id']) || $contractData['plan_id'] === null) {
                        $skipped++;
                        $rowIndex = $contractData['_row_index'] ?? ($index + 1);
                        $errors[] = "行 {$rowIndex}: plan_idがnullのためスキップしました";
                        $bar->advance();
                        continue;
                    }

                    // 必須フィールドのチェック
                    if (empty($contractData['customer_id']) || 
                        empty($contractData['shop_id']) || 
                        empty($contractData['plan_id']) || 
                        empty($contractData['contract_date']) ||
                        empty($contractData['kimono_type'])) {
                        $skipped++;
                        $rowIndex = $contractData['_row_index'] ?? ($index + 1);
                        $errors[] = "行 {$rowIndex}: 必須フィールドが不足しています";
                        $bar->advance();
                        continue;
                    }

                    // 外部キーの存在チェック（メモリ上のマッピングから）
                    if (!isset($customerMap[$contractData['customer_id']])) {
                        $skipped++;
                        $rowIndex = $contractData['_row_index'] ?? ($index + 1);
                        $errors[] = "行 {$rowIndex}: 顧客IDが存在しません";
                        $bar->advance();
                        continue;
                    }

                    if (!isset($shopMap[$contractData['shop_id']])) {
                        $skipped++;
                        $rowIndex = $contractData['_row_index'] ?? ($index + 1);
                        $errors[] = "行 {$rowIndex}: 店舗IDが存在しません";
                        $bar->advance();
                        continue;
                    }

                    if (!isset($planMap[$contractData['plan_id']])) {
                        $skipped++;
                        $rowIndex = $contractData['_row_index'] ?? ($index + 1);
                        $errors[] = "行 {$rowIndex}: プランIDが存在しません";
                        $bar->advance();
                        continue;
                    }

                    // user_idが指定されている場合は存在チェック
                    if (!empty($contractData['user_id']) && !isset($userMap[$contractData['user_id']])) {
                        $skipped++;
                        $rowIndex = $contractData['_row_index'] ?? ($index + 1);
                        $errors[] = "行 {$rowIndex}: ユーザーIDが存在しません";
                        $bar->advance();
                        continue;
                    }

                    // 既存のレコードをチェック（メモリ上のマッピングから）
                    $key = $contractData['customer_id'] . '|' . $contractData['contract_date'];
                    
                    if (isset($existingContractsMap[$key])) {
                        // 既存の場合は更新リストに追加
                        $contractsToUpdate[] = [
                            'contract' => $existingContractsMap[$key],
                            'data' => $contractData,
                        ];
                    } else {
                        // 新規作成リストに追加
                        $contractsToInsert[] = $contractData;
                    }
                } catch (\Exception $e) {
                    $skipped++;
                    $rowIndex = $contractData['_row_index'] ?? ($index + 1);
                    $errors[] = "行 {$rowIndex}: " . $e->getMessage();
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            // 一括更新
            if (!empty($contractsToUpdate)) {
                $this->info("更新中: " . count($contractsToUpdate) . "件");
                foreach ($contractsToUpdate as $item) {
                    $item['contract']->update($item['data']);
                    $updated++;
                }
            }

            // 一括挿入（チャンクごとに）
            if (!empty($contractsToInsert)) {
                $this->info("挿入中: " . count($contractsToInsert) . "件");
                $now = now();
                foreach ($contractsToInsert as &$contractData) {
                    unset($contractData['_row_index']); // 行番号を削除
                    $contractData['created_at'] = $now;
                    $contractData['updated_at'] = $now;
                }
                unset($contractData);

                foreach (array_chunk($contractsToInsert, 100) as $chunk) {
                    Contract::insert($chunk);
                    $created += count($chunk);
                }
            }

            DB::commit();

            return [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('データの登録中にエラーが発生しました: ' . $e->getMessage());
            throw $e;
        }
    }
}
