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
        $csv = storage_path('app/seeders/customers.csv');
        
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

            // ヘッダーとデータ行の列数が一致しない場合は、不足している列を空文字で埋める
            $originalRowCount = count($row);
            if (count($header) !== $originalRowCount) {
                if ($originalRowCount < count($header)) {
                    // 「成人式エリア」の列のインデックスを取得
                    $ceremonyAreaIndex = array_search('成人式エリア', $header);
                    $missingCount = count($header) - $originalRowCount;
                    
                    // 行821-826のように「成人式エリア」の列が欠けている場合を考慮
                    // データ行の30番目の位置（「成人式エリア」の位置）に空文字を挿入
                    if ($ceremonyAreaIndex !== false && $ceremonyAreaIndex < $originalRowCount + $missingCount) {
                        // 「成人式エリア」の位置に空文字を挿入
                        array_splice($row, $ceremonyAreaIndex, 0, '');
                        // 残りの不足分を末尾に追加
                        if (count($row) < count($header)) {
                            $row = array_pad($row, count($header), '');
                        }
                        $this->command->warn("行 " . $rowIndex . " の「成人式エリア」列が欠けています。空文字で埋めます。");
                    } else {
                        // それ以外の場合は、末尾に空文字を追加
                        $row = array_pad($row, count($header), '');
                        $this->command->warn("行 " . $rowIndex . " の列数が不足しています（ヘッダー: " . count($header) . ", データ: " . $originalRowCount . "）。不足分を空文字で埋めます。");
                    }
                } else {
                    // データ行の列数が多すぎる場合、余分な列を削除
                    $row = array_slice($row, 0, count($header));
                    $this->command->warn("行 " . $rowIndex . " の列数が多すぎます（ヘッダー: " . count($header) . ", データ: " . $originalRowCount . "）。余分な列を削除します。");
                }
            }

            $data = array_combine($header, $row);

            // array_combineが失敗した場合（列数不一致など）をチェック
            if ($data === false) {
                $this->command->warn("行 " . $rowIndex . " のデータ結合に失敗しました。スキップします。");
                continue;
            }

            // 必須フィールドの存在チェック
            $requiredFields = ['顧客名', '成人年度', '電話番号', '郵便番号', '住所'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                $this->command->warn("行 " . $rowIndex . " に必須フィールドが不足しています: " . implode(', ', $missingFields) . "。スキップします。");
                continue;
            }

            // 成人式エリアの処理（値がない場合は「その他」を紐づける）
            $areaName = !empty($data['成人式エリア']) ? $data['成人式エリア'] : 'その他';
            $area = CeremonyArea::where('name', $areaName)->first();
            
            if (!$area) {
                $this->command->warn("行 " . $rowIndex . " の成人式エリア「" . $areaName . "」が見つかりません。スキップします。");
                continue;
            }

            Customer::create([
                'name' => $data['顧客名'],
                'kana' => $data['ふりがな'] ?? null,
                'guardian_name' => $data['保護者名'] ?? null,
                'birth_date' => !empty($data['生年月日'])
                    ? Carbon::parse($data['生年月日'])
                    : null,
                'coming_of_age_year' => (int)$data['成人年度'],
                'ceremony_area_id' => $area->id,
                'phone_number' => $data['電話番号'],
                'postal_code' => $data['郵便番号'],
                'address' => $data['住所'],
                'remarks' => $data['備考'] ?? null,
            ]);
        }

        fclose($handle);
    }
}
