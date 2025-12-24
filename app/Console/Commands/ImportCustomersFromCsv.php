<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ImportCustomersFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:import-csv {file=fukui_customers.csv}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CSVファイルから顧客データをインポートします';

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

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->error('CSVファイルを開けませんでした。');
            return Command::FAILURE;
        }

        // BOMをスキップ（UTF-8 BOMがある場合）
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // ヘッダー行を読み込む
        $header = fgetcsv($handle);
        if ($header === false) {
            $this->error('ヘッダー行を読み込めませんでした。');
            fclose($handle);
            return Command::FAILURE;
        }

        // ヘッダーの前後の空白や引用符を除去
        $header = array_map('trim', $header);
        $header = array_map(function($value) {
            return trim($value, '"');
        }, $header);

        $rowIndex = 1; // ヘッダー行をカウント
        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->setMessage('処理中...');

        // まず行数をカウント
        $totalLines = 0;
        $tempHandle = fopen($csvPath, 'r');
        if ($tempHandle !== false) {
            $tempBom = fread($tempHandle, 3);
            if ($tempBom !== "\xEF\xBB\xBF") {
                rewind($tempHandle);
            }
            fgetcsv($tempHandle); // ヘッダーをスキップ
            while (fgetcsv($tempHandle) !== false) {
                $totalLines++;
            }
            fclose($tempHandle);
        }
        $bar->setMaxSteps($totalLines);

        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;
            $bar->setMessage("行 {$rowIndex} を処理中...");
            
            // 空の行をスキップ
            if (empty(array_filter($row))) {
                $bar->advance();
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
                $skippedCount++;
                $bar->advance();
                continue;
            }

            // 顧客名が必須
            $name = $this->pick($data, ['name']);
            if (!$name || $name === '') {
                $skippedCount++;
                $bar->advance();
                continue;
            }

            // データの準備
            $customerData = [
                'name' => $name,
                'kana' => $this->pick($data, ['kana']) ?: null,
                'guardian_name' => $this->pick($data, ['guardian_name']) ?: null,
                'guardian_name_kana' => $this->pick($data, ['guardian_name_kana']) ?: null,
                'birth_date' => $this->toYmd($this->pick($data, ['birth_date'])) ?: null,
                'phone_number' => $this->pick($data, ['phone_number']) ?: null,
                'postal_code' => $this->pick($data, ['postal_code']) ?: null,
                'address' => $this->pick($data, ['address']) ?: null,
                'remarks' => $this->pick($data, ['remarks']) ?: null,
            ];

            // coming_of_age_yearの処理
            $comingOfAgeYear = $this->pick($data, ['coming_of_age_year']);
            if ($comingOfAgeYear && $comingOfAgeYear !== '' && $comingOfAgeYear !== 'NULL' && is_numeric($comingOfAgeYear)) {
                $customerData['coming_of_age_year'] = (int)$comingOfAgeYear;
            } else {
                $customerData['coming_of_age_year'] = null;
            }

            // ceremony_area_idの処理
            $ceremonyAreaId = $this->pick($data, ['ceremony_area_id']);
            if ($ceremonyAreaId && $ceremonyAreaId !== '' && $ceremonyAreaId !== 'NULL' && is_numeric($ceremonyAreaId)) {
                $customerData['ceremony_area_id'] = (int)$ceremonyAreaId;
            } else {
                $customerData['ceremony_area_id'] = null;
            }

            // created_at, updated_atの処理
            $createdAt = $this->pick($data, ['created_at']);
            $updatedAt = $this->pick($data, ['updated_at']);
            
            if ($createdAt && $createdAt !== '' && $createdAt !== 'NULL') {
                try {
                    $customerData['created_at'] = Carbon::parse($createdAt);
                } catch (\Exception $e) {
                    // パースに失敗した場合は現在時刻を使用（デフォルト）
                }
            }

            if ($updatedAt && $updatedAt !== '' && $updatedAt !== 'NULL') {
                try {
                    $customerData['updated_at'] = Carbon::parse($updatedAt);
                } catch (\Exception $e) {
                    // パースに失敗した場合は現在時刻を使用（デフォルト）
                }
            }

            try {
                // 名前と電話番号で既存の顧客を検索（重複チェック）
                $existingCustomer = Customer::where('name', $customerData['name'])
                    ->where('phone_number', $customerData['phone_number'])
                    ->first();

                if ($existingCustomer) {
                    // 既存の場合は更新（ただし、created_atは保持）
                    if (isset($customerData['created_at'])) {
                        unset($customerData['created_at']);
                    }
                    $existingCustomer->update($customerData);
                    $updatedCount++;
                } else {
                    // 新規作成
                    Customer::create($customerData);
                    $createdCount++;
                }
            } catch (\Exception $e) {
                $errors[] = "行 {$rowIndex}: " . $e->getMessage();
                $skippedCount++;
            }

            $bar->advance();
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);
        
        // 結果を表示
        $this->info("インポートが完了しました。");
        $this->info("作成: {$createdCount}件");
        $this->info("更新: {$updatedCount}件");
        $this->info("スキップ: {$skippedCount}件");

        if (!empty($errors)) {
            $this->warn("エラーが発生した行:");
            foreach ($errors as $error) {
                $this->warn("  - {$error}");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * データ配列から指定されたキーの値を取得（複数のキーを試行）
     *
     * @param array $data
     * @param array $keys
     * @param mixed $default
     * @return mixed
     */
    private function pick(array $data, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && $data[$key] !== '' && $data[$key] !== 'NULL' && strtoupper($data[$key]) !== 'NULL') {
                return $data[$key];
            }
        }
        return $default;
    }

    /**
     * 日付文字列をY-m-d形式に変換
     *
     * @param string|null $dateString
     * @return string|null
     */
    private function toYmd($dateString)
    {
        if (!$dateString || $dateString === '' || $dateString === 'NULL') {
            return null;
        }

        try {
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}