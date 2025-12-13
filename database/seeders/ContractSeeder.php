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

        $defaultUser = User::first();
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
                    if ($ceremonyAreaIndex !== false && $ceremonyAreaIndex < $originalRowCount + $missingCount) {
                        // 「成人式エリア」の位置に空文字を挿入
                        array_splice($row, $ceremonyAreaIndex, 0, '');
                        // 残りの不足分を末尾に追加
                        if (count($row) < count($header)) {
                            $row = array_pad($row, count($header), '');
                        }
                    } else {
                        // それ以外の場合は、末尾に空文字を追加
                        $row = array_pad($row, count($header), '');
                    }
                } else {
                    // データ行の列数が多すぎる場合、余分な列を削除
                    $row = array_slice($row, 0, count($header));
                }
            }

            $data = array_combine($header, $row);

            // array_combineが失敗した場合（列数不一致など）をチェック
            if ($data === false) {
                continue;
            }

            // 成約日が必須
            $contractDate = $this->toYmd($this->pick($data, ['成約日']));
            if (!$contractDate) {
                continue;
            }

            // 顧客名が必須
            $customerName = $this->normalizeName($this->pick($data, ['顧客名']));
            if (!$customerName) {
                continue;
            }

            $customer = Customer::where('name', $customerName)->first();
            if (!$customer) {
                $this->command->warn("行 " . $rowIndex . " の顧客「" . $customerName . "」が見つかりません。スキップします。");
                continue;
            }

            // 店舗が必須
            $shopName = $this->pick($data, ['店舗']);
            if (!$shopName) {
                continue;
            }

            $shop = Shop::where('name', $shopName)->first();
            if (!$shop) {
                $this->command->warn("行 " . $rowIndex . " の店舗「" . $shopName . "」が見つかりません。スキップします。");
                continue;
            }

            $kimonoType = $shop->name === '浜店' ? '袴' : '振袖';

            // プラン（通常は CSVの「プラン」、浜店は無いので固定）
            $planName = $this->pick($data, ['プラン']);
            $plan = null;
            if ($planName) {
                $plan = Plan::where('name', $planName)->first();
            }
            
            // プランが見つからない場合、浜店の場合はデフォルトプランを探す
            if (!$plan && $shop->name === '浜店') {
                $plan = Plan::where('code', 'hamaten_hakama')->first()
                     ?: Plan::where('name', '浜店_袴')->first();
            }

            if (!$plan) {
                $this->command->warn("行 " . $rowIndex . " のプランが見つかりません。スキップします。");
                continue;
            }

            // 金額
            $amountRaw = $this->pick($data, ['金額（税込）']);
            $amount = 0;
            if ($amountRaw) {
                $amount = (int) str_replace(['￥', ',', '円', ' '], '', (string)$amountRaw);
            }

            // お仕度日程（通常：お仕度日程 / 浜店：お仕度日）
            $prepDate = $this->toYmd($this->pick($data, ['お仕度日程', 'お仕度日']));

            // お仕度会場（どちらにも存在）
            $prepVenue = $this->pick($data, ['お仕度会場']);

            // 保証（浜店は列が無い→false）
            $warranty = $this->pick($data, ['安心保証']);
            $warrantyFlag = $warranty === 'Y';

            // 担当スタッフ（CSVの「担当」列から取得）
            $assignedStaffName = $this->pick($data, ['担当']);
            $userId = null;
            if ($assignedStaffName) {
                $user = User::where('name', 'LIKE', '%' . $assignedStaffName . '%')->first();
                if ($user) {
                    $userId = $user->id;
                }
            }

            Contract::firstOrCreate(
                [
                    'customer_id' => $customer->id,
                    'contract_date' => $contractDate,
                ],
                [
                    'shop_id' => $shop->id,
                    'plan_id' => $plan->id,
                    'kimono_type' => $kimonoType,
                    'warranty_flag' => $warrantyFlag,
                    'total_amount' => $amount,
                    'preparation_venue' => $prepVenue ?: null,
                    'preparation_date' => $prepDate,
                    'user_id' => $userId,
                    'remarks' => $this->pick($data, ['備考']) ?: null,
                ]
            );
        }

        fclose($handle);
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
            if (isset($data[$key]) && $data[$key] !== '') {
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
    private function toYmd(?string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // 様々な日付形式に対応
            $date = Carbon::parse($dateString);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 顧客名を正規化（全角スペースを半角に変換など）
     *
     * @param string|null $name
     * @return string|null
     */
    private function normalizeName(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }

        // 全角スペースを半角に変換
        $name = str_replace('　', ' ', $name);
        // 前後の空白を除去
        $name = trim($name);
        // 連続するスペースを1つに
        $name = preg_replace('/\s+/', ' ', $name);

        return $name ?: null;
    }
}
