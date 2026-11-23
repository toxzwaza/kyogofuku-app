<?php

namespace Tests\Feature\Admin;

use App\Models\Meibo\MeiboDmLog;
use App\Models\Meibo\MeiboPerson;
use App\Services\Meibo\MeiboBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * meibo:import（CSV からの取り込み）。CSV・人はすべて架空。
 */
class MeiboImportTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    private const JA = ['統合ID', '都道府県', '成人式年', '生年度', '本人氏名', '本人フリガナ', '生年月日', '性別', '郵便番号', '住所', '固定電話', '携帯電話', '保護者氏名', '保護者フリガナ', '統合元版', '統合件数', 'DM備考'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/meibo_import_test_' . uniqid();
        mkdir($this->tmp);
        config(['meibo.backup_dir' => $this->tmp . '/backup', 'meibo.backup_keep_days' => 30]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    /** master.csv と同じ形（BOM・日本語の見出し）の架空 CSV */
    private function masterCsv(array $rows): string
    {
        $path = $this->tmp . '/master_' . uniqid() . '.csv';
        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, self::JA);
        foreach ($rows as $r) {
            fputcsv($fh, array_map(fn ($k) => $r[$k] ?? '', self::JA));
        }
        fclose($fh);

        return $path;
    }

    private function row(string $code, array $over = []): array
    {
        return $over + [
            '統合ID' => $code, '都道府県' => '福井県', '成人式年' => '2029', '生年度' => '2008', '本人氏名' => '架空　花子',
            '本人フリガナ' => 'カクウ　ハナコ', '生年月日' => '2008/05/01', '性別' => '女', '郵便番号' => '910-0001',
            '住所' => '福井市架空町1-2-3', '固定電話' => '0776-00-0000', '保護者氏名' => '架空　一郎', '統合元版' => 'G_R07', '統合件数' => '1',
        ];
    }

    private function import(string $file, array $opts = []): array
    {
        $code = Artisan::call('meibo:import', ['file' => $file] + $opts);

        return [$code, Artisan::output()];
    }

    public function test_dry_run_counts_without_writing(): void
    {
        $keep = MeiboPerson::factory()->create(['master_code' => 'M000001', 'name' => '架空　花子', 'kana' => 'カクウ　ハナコ', 'pref' => '福井県', 'seijin_year' => 2029, 'birth_fy' => 2008, 'birth_date' => '2008-05-01', 'gender' => '女', 'zip' => '910-0001', 'address' => '福井市架空町1-2-3', 'tel' => '0776-00-0000', 'mobile' => null, 'guardian_name' => '架空　一郎', 'guardian_kana' => null, 'source_versions' => 'G_R07', 'merge_count' => 1]);
        $change = MeiboPerson::factory()->create(['master_code' => 'M000002']);
        MeiboPerson::factory()->create(['master_code' => 'M000099']); // CSV に無い
        $file = $this->masterCsv([
            $this->row('M000001'),
            $this->row('M000002', ['住所' => '福井市架空町9-9']),
            $this->row('M000003', ['統合元版' => 'G_R07/G_R11', 'DM備考' => '架空の備考']),
            $this->row('', []),
            $this->row('M000004', ['生年月日' => '2008/13/40']),
        ]);

        [$code, $out] = $this->import($file, ['--dry-run' => true]);

        $this->assertSame(0, $code);
        $this->assertSame(3, MeiboPerson::count());
        $this->assertSame($change->address, $change->fresh()->address);
        $this->assertMatchesRegularExpression('/新しい人（追加）\s*\|\s*1（うち DM 備考あり 1）/u', $out);
        $this->assertMatchesRegularExpression('/更新）\s*\|\s*1\s/u', $out);
        $this->assertMatchesRegularExpression('/変わらない人\s*\|\s*1\s/u', $out);
        $this->assertMatchesRegularExpression('/CSV に無い人（消さずに残す）\s*\|\s*1\s/u', $out);
        $this->assertMatchesRegularExpression('/エラーで入れない行\s*\|\s*2\s/u', $out);
        $this->assertMatchesRegularExpression('/R11 成人名簿の人\s*\|\s*1\s/u', $out);
        $this->assertStringContainsString('5行目：統合ID が空', $out);
        $this->assertStringContainsString('6行目：生年月日が日付でない', $out);
        $this->assertStringNotContainsString('架空', $out); // 氏名・住所は出さない
        $this->assertDirectoryDoesNotExist($this->tmp . '/backup'); // dry-run はバックアップも取らない
        $this->assertTrue($keep->is($keep->fresh()));
    }

    public function test_upsert_keeps_dm_and_ids_and_never_deletes(): void
    {
        $p = MeiboPerson::factory()->status('停止', '架空の停止依頼')->create(['master_code' => 'M000001', 'address' => '福井市架空町1-1-1']);
        MeiboDmLog::create(['person_id' => $p->id, 'old_status' => '送付可', 'new_status' => '停止', 'note' => '架空の停止依頼', 'changed_at' => now()]);
        $gone = MeiboPerson::factory()->create(['master_code' => 'M000050']);
        $id = $p->id;

        $file = $this->masterCsv([
            $this->row('M000001', ['住所' => '福井市架空町2-2-2', 'DM備考' => 'CSV の備考（入れない）']),
            $this->row('M000002', ['DM備考' => '新しい人の備考']),
            $this->row('M000003'),
        ]);
        [$code, $out] = $this->import($file);

        $this->assertSame(0, $code, $out);
        $p = MeiboPerson::where('master_code', 'M000001')->sole();
        $this->assertSame($id, $p->id);
        $this->assertSame('福井市架空町2-2-2', $p->address);
        $this->assertSame('停止', $p->dm_status);
        $this->assertSame('架空の停止依頼', $p->dm_note);
        $this->assertSame(1, MeiboDmLog::count());
        $this->assertNotNull($gone->fresh());

        $new = MeiboPerson::where('master_code', 'M000002')->sole();
        $this->assertSame('送付可', $new->dm_status);
        $this->assertSame('新しい人の備考', $new->dm_note);
        $this->assertSame('2008-05-01', $new->birth_date->format('Y-m-d'));
        $this->assertSame(2029, (int) $new->seijin_year);
        $this->assertSame('', MeiboPerson::where('master_code', 'M000003')->sole()->dm_note);
        $this->assertSame(4, MeiboPerson::count());

        // もう一度入れても変わらない
        [, $out2] = $this->import($file);
        $this->assertMatchesRegularExpression('/変わらない人\s*\|\s*3\s/u', $out2);
        $this->assertSame(4, MeiboPerson::count());
    }

    public function test_duplicate_code_in_csv_is_error_and_bad_header_fails(): void
    {
        $file = $this->masterCsv([$this->row('M000001'), $this->row('M000001', ['住所' => '別'])]);
        [, $out] = $this->import($file);
        $this->assertStringContainsString('3行目：統合ID が CSV の中で重複（2行目と同じ）', $out);
        $this->assertSame(1, MeiboPerson::count());

        $bad = $this->tmp . '/bad.csv';
        file_put_contents($bad, "a,b\n1,2\n");
        [$code, $out] = $this->import($bad);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('見出しに必要な列がありません', $out);
    }

    public function test_backup_before_write_and_prune_old_ones(): void
    {
        $p = MeiboPerson::factory()->create(['master_code' => 'M000001']);
        MeiboDmLog::create(['person_id' => $p->id, 'old_status' => '送付可', 'new_status' => '未達', 'note' => null, 'changed_at' => now()]);
        $dir = $this->tmp . '/backup';
        mkdir($dir, 0700, true);
        $old = $dir . '/meibo_persons_' . now()->subDays(31)->format('Ymd_His') . '.csv.gz';
        $recent = $dir . '/meibo_persons_' . now()->subDays(29)->format('Ymd_His') . '.csv.gz';
        touch($old);
        touch($recent);

        [$code, $out] = $this->import($this->masterCsv([$this->row('M000002')]));

        $this->assertSame(0, $code, $out);
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
        $files = glob($dir . '/meibo_persons_' . now()->format('Ymd') . '_*.csv.gz');
        $this->assertCount(1, $files);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($files[0])), -4));
        $lines = explode("\n", trim(gzdecode(file_get_contents($files[0]))));
        $this->assertCount(2, $lines); // 見出し＋取り込み前の 1 人（新しい人はまだいない）
        $this->assertStringStartsWith('id,master_code,', $lines[0]);
        $this->assertCount(1, glob($dir . '/meibo_dm_logs_*.csv.gz'));
        $this->assertGreaterThanOrEqual(2, (new MeiboBackup())->prune(1, now()->addDays(2)));
    }

    public function test_initial_brings_dm_and_remaps_logs(): void
    {
        // 名簿DB の persons／dm_logs を書き出した形（列名の見出し・空は NULL）。id は名簿DB 側の番号
        $persons = $this->tmp . '/persons.tsv';
        file_put_contents($persons, implode("\n", [
            "id\tmaster_code\tpref\tseijin_year\tbirth_fy\tname\tkana\tbirth_date\tgender\tzip\taddress\ttel\tmobile\tguardian_name\tguardian_kana\tsource_versions\tmerge_count\tdm_status\tdm_note",
            "501\tM000001\t福井県\t2029\t2008\t架空　花子\tカクウ　ハナコ\t2008-05-01\t女\t910-0001\t福井市架空町1\tNULL\tNULL\tNULL\tNULL\tG_R11\t2\t停止\t架空の停止",
            "502\tM000002\t石川県\t2030\t2009\t架空　美咲\tNULL\tNULL\tNULL\t920-0001\t金沢市架空町2\tNULL\t090-0000-0000\tNULL\tNULL\tG_R07\t1\t送付可\t",
        ]) . "\n");
        $logs = $this->tmp . '/dm_logs.csv';
        file_put_contents($logs, "id,person_id,old_status,new_status,note,changed_at\n1,501,送付可,停止,架空の停止,2026-01-02 03:04:05\n2,999,送付可,未達,,2026-01-03 00:00:00\n");

        [$code, $out] = $this->import($persons, ['--initial' => true, '--logs' => $logs, '--dry-run' => true]);
        $this->assertSame(0, $code, $out);
        $this->assertSame(0, MeiboPerson::count());
        $this->assertMatchesRegularExpression('/付け替えて入れる\s*\|\s*1\s/u', $out);

        [$code, $out] = $this->import($persons, ['--initial' => true, '--logs' => $logs]);
        $this->assertSame(0, $code, $out);
        $a = MeiboPerson::where('master_code', 'M000001')->sole();
        $this->assertSame('停止', $a->dm_status);
        $this->assertSame('架空の停止', $a->dm_note);
        $this->assertSame('', MeiboPerson::where('master_code', 'M000002')->sole()->dm_note);
        $log = MeiboDmLog::sole();
        $this->assertSame($a->id, $log->person_id);
        $this->assertNull($log->changed_by_user_id);
        $this->assertSame('2026-01-02 03:04:05', $log->changed_at->format('Y-m-d H:i:s'));
        $this->assertMatchesRegularExpression('/相手の人がいない（入れない）\s*\|\s*1\s/u', $out);

        // 2 回目の --initial は断る（差分で入れる）
        [$code, $out] = $this->import($persons, ['--initial' => true]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('初回の移行（--initial）は空の表にだけ', $out);
        $this->assertSame(2, MeiboPerson::count());
    }
}
