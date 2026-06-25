<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * リッチメニュー（4項目：友達紹介 / マイステージ / マイポイント・ギフト / 顧客詳細）を
 * Messaging API で作成・画像アップロード・デフォルト設定する。
 *
 * 事前に storage/app/line/rich_menu.json（定義）と rich_menu.png（画像）を配置すること。
 */
class RegisterLineRichMenu extends Command
{
    protected $signature = 'line:register-rich-menu {--delete= : 既存リッチメニューID を削除}';

    protected $description = 'リッチメニュー（4項目）を作成・画像アップ・デフォルト設定';

    public function handle(): int
    {
        $token = config('line.messaging.channel_access_token');
        if (!$token) {
            $this->error('LINE_MESSAGING_CHANNEL_ACCESS_TOKEN が未設定です。');

            return self::FAILURE;
        }

        if ($deleteId = $this->option('delete')) {
            $res = Http::withToken($token)->delete("https://api.line.me/v2/bot/richmenu/{$deleteId}");
            $this->line('delete: '.$res->status());

            return self::SUCCESS;
        }

        $configPath = storage_path('app/line/rich_menu.json');
        $imagePath = storage_path('app/line/rich_menu.png');
        if (!is_file($configPath)) {
            $this->error("定義が見つかりません: {$configPath}");

            return self::FAILURE;
        }
        $definition = json_decode(file_get_contents($configPath), true);

        // 1. リッチメニュー作成
        $create = Http::withToken($token)
            ->post('https://api.line.me/v2/bot/richmenu', $definition);
        if (!$create->successful()) {
            $this->error('作成失敗: '.$create->body());

            return self::FAILURE;
        }
        $richMenuId = $create->json('richMenuId');
        $this->info("richMenuId: {$richMenuId}");

        // 2. 画像アップロード
        if (is_file($imagePath)) {
            $upload = Http::withToken($token)
                ->withBody(file_get_contents($imagePath), 'image/png')
                ->post("https://api-data.line.me/v2/bot/richmenu/{$richMenuId}/content");
            if (!$upload->successful()) {
                $this->error('画像アップロード失敗: '.$upload->body());

                return self::FAILURE;
            }
            $this->line('画像アップロード: OK');
        } else {
            $this->warn("画像が見つかりません（スキップ）: {$imagePath}");
        }

        // 3. デフォルト設定
        $setDefault = Http::withToken($token)
            ->post("https://api.line.me/v2/bot/user/all/richmenu/{$richMenuId}");
        $this->line('デフォルト設定: '.$setDefault->status());

        return self::SUCCESS;
    }
}
