<?php

namespace App\Console\Commands;

use App\Models\GtmTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * GTMタグのマスタに、調査・確認済みの管理会社などを入れる（空の項目にだけ入れる。何度動かしても同じ結果）。
 * マスタにない GTM ID は行を足す。種類（kind）は足すときだけ決め、既存の行の種類は変えない（違えば注意を出す）。
 * 出どころ：taskboard page-82「GTMタグ管理：現状把握と設計案」§5（2026-11-28 村上さん確認）
 */
class BackfillGtmTags extends Command
{
    protected $signature = 'gtm:backfill {--dry-run : 変更内容を表示するだけで保存しない}';

    protected $description = 'GTMタグのマスタに管理会社・用途・確度などを入れる（空の項目のみ）';

    private const CONFIRMED_AT = '2026-11-28';

    /**
     * 入れる内容。is_active は「使用していない」と確認できたものだけ false を指定する（空でなくても入れる）
     */
    public static function definitions(): array
    {
        $confirmed = ['confidence' => GtmTag::CONFIRMED, 'confirmed_at' => self::CONFIRMED_AT];

        return [
            'GTM-5LWBZH47' => ['kind' => 'event', 'company_name' => 'BE FANG', 'purpose' => '好一の広告', 'used_on' => '好一HP（kyogofuku-kouichi.com）'] + $confirmed,
            'GTM-T2CT729C' => ['kind' => 'event', 'company_name' => 'BE FANG', 'purpose' => '福井の袴予約の広告'] + $confirmed,
            'GTM-5MSL9GRL' => ['kind' => 'event', 'company_name' => 'カラフル', 'purpose' => '福井の広告', 'used_on' => '福井HP（fukui.kyogofuku-hirata.jp）'] + $confirmed,
            'GTM-5NP4QCSB' => ['kind' => 'event', 'company_name' => 'アンジュ'] + $confirmed,
            'GTM-N7BDKK6S' => ['kind' => 'event', 'company_name' => 'アンジュ', 'memo' => '使用していない（2026-11-28 確認）', 'is_active' => false] + $confirmed,
            'GTM-WHQZSR67' => ['kind' => 'company', 'company_name' => '自社', 'purpose' => '社内の計測（GA4・Clarity）', 'used_on' => 'Inertia版の全イベントLP（config services.company_gtm_id）'] + $confirmed,
            'GTM-MXSRMSXR' => ['kind' => 'site', 'company_name' => '自社', 'purpose' => 'HPの基本計測（GA4・Clarity）', 'used_on' => '好一HP（kyogofuku-kouichi.com）', 'confidence' => '中'],
            'GTM-PXPJTD9K' => ['kind' => 'site', 'company_name' => '自社', 'purpose' => 'HPの基本計測（GA4・Clarity）', 'used_on' => '福井HP（fukui.kyogofuku-hirata.jp）', 'confidence' => '中'],
            'GTM-P573JPFV' => ['kind' => 'site', 'company_name' => '自社', 'purpose' => 'HPの基本計測（GA4・Clarity）', 'used_on' => '本社HP（kyogofuku-hirata.jp）', 'confidence' => '中'],
        ];
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        DB::transaction(function () use ($dryRun, &$created, &$updated, &$unchanged) {
            foreach (self::definitions() as $gtmId => $def) {
                $tag = GtmTag::where('gtm_id', $gtmId)->first();

                if (! $tag) {
                    $attrs = $def + ['is_active' => true];
                    if (blank($attrs['memo'] ?? null)) {
                        $attrs['memo'] = 'gtm:backfill で登録';
                    }
                    $this->line("追加 {$gtmId}：".$this->describe($attrs));
                    if (! $dryRun) {
                        GtmTag::create(['gtm_id' => $gtmId] + $attrs);
                    }
                    $created++;

                    continue;
                }

                if ($tag->kind !== $def['kind']) {
                    $this->warn("注意 {$gtmId}：種類が {$tag->kind}（想定 {$def['kind']}）。種類は変えないので、必要なら画面で直してください。");
                }

                $changes = [];
                foreach (['company_name', 'purpose', 'used_on', 'confidence', 'confirmed_at', 'memo'] as $col) {
                    if (array_key_exists($col, $def) && blank($tag->getRawOriginal($col))) {
                        $changes[$col] = $def[$col];
                    }
                }
                // 確度を手で入れてあり確認済でない行には、確認日だけを入れない
                if (isset($changes['confirmed_at']) && ($changes['confidence'] ?? $tag->confidence) !== GtmTag::CONFIRMED) {
                    unset($changes['confirmed_at']);
                }
                if (array_key_exists('is_active', $def) && $tag->is_active !== $def['is_active']) {
                    $changes['is_active'] = $def['is_active'];
                }

                if (! $changes) {
                    $unchanged++;

                    continue;
                }

                $this->line("更新 {$gtmId}：".$this->describe($changes));
                if (! $dryRun) {
                    $tag->update($changes);
                }
                $updated++;
            }
        });

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}追加 {$created}件・更新 {$updated}件・変更なし {$unchanged}件");

        return self::SUCCESS;
    }

    private function describe(array $attrs): string
    {
        return collect($attrs)
            ->map(fn ($v, $k) => $k.'='.(is_bool($v) ? ($v ? 'true' : 'false') : $v))
            ->implode(' / ');
    }
}
