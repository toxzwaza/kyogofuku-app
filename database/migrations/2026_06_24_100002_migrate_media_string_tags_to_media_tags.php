<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 既存の media_files.tags（JSON文字列配列）を media_tags（フラット）＋ piv へ移行する。
     * 旧カラムはロールバック安全のため保持し、後日別マイグレーションで削除する。
     */
    public function up(): void
    {
        $now = now();

        // 1) 既存の distinct なタグ文字列を収集
        $names = [];
        DB::table('media_files')
            ->whereNotNull('tags')
            ->select('id', 'tags')
            ->orderBy('id')
            ->each(function ($row) use (&$names) {
                $decoded = json_decode($row->tags, true);
                if (!is_array($decoded)) {
                    return;
                }
                foreach ($decoded as $t) {
                    $t = is_string($t) ? trim($t) : '';
                    if ($t !== '') {
                        $names[$t] = true;
                    }
                }
            });

        // 2) ルート（parent_id=null）のフラットタグとして作成（既存があれば再利用）
        $nameToId = [];
        foreach (array_keys($names) as $name) {
            $existing = DB::table('media_tags')
                ->whereNull('parent_id')
                ->where('name', $name)
                ->value('id');
            if ($existing) {
                $nameToId[$name] = $existing;
                continue;
            }
            $nameToId[$name] = DB::table('media_tags')->insertGetId([
                'parent_id' => null,
                'name' => $name,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3) 各メディアにピボットで紐付け（重複はスキップ）
        DB::table('media_files')
            ->whereNotNull('tags')
            ->select('id', 'tags')
            ->orderBy('id')
            ->each(function ($row) use ($nameToId, $now) {
                $decoded = json_decode($row->tags, true);
                if (!is_array($decoded)) {
                    return;
                }
                $rows = [];
                $seen = [];
                foreach ($decoded as $t) {
                    $t = is_string($t) ? trim($t) : '';
                    if ($t === '' || !isset($nameToId[$t]) || isset($seen[$t])) {
                        continue;
                    }
                    $seen[$t] = true;
                    $rows[] = [
                        'media_file_id' => $row->id,
                        'media_tag_id' => $nameToId[$t],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($rows) {
                    DB::table('media_file_tag')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        // ピボットとタグを全削除（旧 tags カラムは保持しているため復元可能）
        DB::table('media_file_tag')->delete();
        DB::table('media_tags')->delete();
    }
};
