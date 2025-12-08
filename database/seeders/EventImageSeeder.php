<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventImage;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class EventImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $csvPath = database_path('seeders/data/event_images.csv');
        
        if (!file_exists($csvPath)) {
            $this->command->warn("CSVファイルが見つかりません: {$csvPath}");
            return;
        }

        $file = fopen($csvPath, 'r');
        if (!$file) {
            $this->command->error("CSVファイルを開けませんでした: {$csvPath}");
            return;
        }

        // ヘッダー行をスキップ
        $header = fgetcsv($file);

        // CSVデータを読み込んで登録
        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 7) {
                continue;
            }

            // CSVの値を取得（ダブルクォートを除去）
            $id = trim($row[0], '"');
            $eventId = (int)trim($row[1], '"');
            $path = trim($row[2], '"');
            $altValue = trim($row[3], '"');
            $alt = ($altValue === 'NULL' || $altValue === '') ? null : $altValue;
            $sortOrderValue = trim($row[4], '"');
            $sortOrder = ($sortOrderValue === 'NULL' || $sortOrderValue === '') ? 0 : (int)$sortOrderValue;
            $createdAtValue = trim($row[5], '"');
            $createdAt = ($createdAtValue === 'NULL' || $createdAtValue === '') ? null : Carbon::parse($createdAtValue);
            $updatedAtValue = trim($row[6], '"');
            $updatedAt = ($updatedAtValue === 'NULL' || $updatedAtValue === '') ? null : Carbon::parse($updatedAtValue);

            // イベントが存在するか確認
            $event = Event::find($eventId);
            if (!$event) {
                $this->command->warn("イベントID {$eventId} が見つかりません。スキップします。");
                continue;
            }

            // EventImageを作成
            EventImage::create([
                'event_id' => $eventId,
                'path' => $path,
                'alt' => $alt,
                'sort_order' => $sortOrder,
                'created_at' => $createdAt ?? now(),
                'updated_at' => $updatedAt ?? now(),
            ]);
        }

        fclose($file);
    }
}

