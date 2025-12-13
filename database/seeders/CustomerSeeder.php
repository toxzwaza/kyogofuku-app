<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CeremonyArea;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $csv = __DIR__ . '/csv/customers.csv';
        
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
        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

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
                $skippedCount++;
                continue;
            }

            // 顧客名が必須
            $name = $this->pick($data, ['name']);
            if (!$name || $name === '') {
                $skippedCount++;
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
            if ($comingOfAgeYear && $comingOfAgeYear !== 'NULL' && is_numeric($comingOfAgeYear)) {
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
                    // パースに失敗した場合は現在時刻を使用
                }
            }

            if ($updatedAt && $updatedAt !== '' && $updatedAt !== 'NULL') {
                try {
                    $customerData['updated_at'] = Carbon::parse($updatedAt);
                } catch (\Exception $e) {
                    // パースに失敗した場合は現在時刻を使用
                }
            }

            try {
                // IDで既存の顧客を検索
                $id = $this->pick($data, ['id']);
                if ($id && $id !== '' && $id !== 'NULL' && is_numeric($id)) {
                    $customer = Customer::find((int)$id);
                    if ($customer) {
                        // 既存の場合は更新（ただし、created_atは保持）
                        unset($customerData['created_at']);
                        $customer->update($customerData);
                        $updatedCount++;
                        continue;
                    }
                }

                // 新規作成
                Customer::create($customerData);
                $createdCount++;
            } catch (\Exception $e) {
                $this->command->warn("行 {$rowIndex} でエラーが発生しました: " . $e->getMessage());
                $skippedCount++;
            }
        }

        fclose($handle);
        
        $this->command->info("顧客データのシードが完了しました。");
        $this->command->info("作成: {$createdCount}件, 更新: {$updatedCount}件, スキップ: {$skippedCount}件");
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
