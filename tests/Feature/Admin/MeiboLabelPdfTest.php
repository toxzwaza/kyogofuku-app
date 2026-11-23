<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Meibo\MeiboPerson;
use App\Models\Shop;
use App\Models\User;
use App\Services\Meibo\LabelPdf;
use App\Services\Meibo\MeiboQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ラベルPDF（POST /admin/meibo/data/labels）。データはすべて架空。
 */
class MeiboLabelPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        config(['meibo.data_source' => 'db']);
        $this->manager = $this->user('manager');
    }

    private function user(?string $role): User
    {
        $user = User::factory()->create(['attendance_role' => $role]);
        $shop = Shop::create(['name' => 'テスト店', 'is_active' => true]);
        $user->shops()->attach($shop->id);

        return $user;
    }

    private function labels(array $body)
    {
        return $this->actingAs($this->manager)->post(route('admin.meibo.data.labels'), $body, ['Accept' => 'application/json']);
    }

    public function test_returns_pdf_with_count_for_every_status(): void
    {
        $a = MeiboPerson::factory()->create();
        $b = MeiboPerson::factory()->status('停止', '架空の停止')->create();
        $c = MeiboPerson::factory()->status('未達', '架空の返送')->create();

        $res = $this->labels(['ids' => [$a->id, $b->id, $c->id, 999999], 'addressee' => 'guardian', 'with_family' => true, 'tantou' => '見本']);

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $res->assertHeader('X-Label-Count', '3');
        $pdf = file_get_contents($res->baseResponse->getFile()->getPathname());
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }

    public function test_label_order_is_zip_then_kana_then_name(): void
    {
        $p1 = MeiboPerson::factory()->create(['zip' => '920-0001', 'kana' => 'アイ', 'name' => '架空　一']);
        $p2 = MeiboPerson::factory()->create(['zip' => '910-0001', 'kana' => 'ウエ', 'name' => '架空　二']);
        $p3 = MeiboPerson::factory()->create(['zip' => '910-0001', 'kana' => 'アイ', 'name' => '架空　三']);
        $p4 = MeiboPerson::factory()->create(['zip' => '910-0001', 'kana' => 'アイ', 'name' => '架空　一']);
        MeiboPerson::factory()->create(['zip' => '900-0001']); // 選んでいない人は入らない

        $q = new MeiboQuery();
        $order = $q->labelOrder([$p1->id, $p2->id, $p3->id, $p4->id]);
        $this->assertSame([$p4->id, $p3->id, $p2->id, $p1->id], $order);
        $rows = iterator_to_array($q->labelRows($order, 2), false);
        $this->assertSame([$p4->master_code, $p3->master_code, $p2->master_code, $p1->master_code], array_column($rows, 'master_code'));
    }

    public function test_pdf_makes_one_page_per_ten_labels(): void
    {
        $pdf = new LabelPdf(['guide' => true]);
        foreach (MeiboPerson::factory()->count(11)->create() as $p) {
            $pdf->add($p->only(['master_code', 'name', 'guardian_name', 'pref', 'zip', 'address']));
        }
        $out = $pdf->output();
        $this->assertSame(11, $pdf->count());
        $this->assertSame(2, preg_match_all('#/Type /Page\b#', $out));
    }

    public function test_empty_or_unknown_ids_are_422(): void
    {
        $this->labels([])->assertStatus(422)->assertJsonValidationErrors('ids');
        $this->labels(['ids' => []])->assertStatus(422);
        $this->labels(['ids' => [999998, 999999]])->assertStatus(422);
        $this->labels(['ids' => [MeiboPerson::factory()->create()->id], 'addressee' => 'other'])->assertStatus(422);
    }

    public function test_only_manager_and_not_while_dummy(): void
    {
        $p = MeiboPerson::factory()->create();
        $this->actingAs($this->user(null))->postJson(route('admin.meibo.data.labels'), ['ids' => [$p->id]])->assertForbidden();

        config(['meibo.data_source' => 'dummy']);
        $this->labels(['ids' => [$p->id]])->assertNotFound();
    }

    public function test_activity_log_keeps_only_count(): void
    {
        $p = MeiboPerson::factory()->create(['name' => '架空　太郎', 'address' => '福井市架空町1丁目']);
        $this->labels(['ids' => [$p->id], 'tantou' => '架空担当'])->assertOk();

        $log = ActivityLog::sole();
        $this->assertSame('export', $log->action_type);
        $this->assertStringContainsString('1件', $log->description);
        $dump = json_encode($log->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('架空', $dump);
        $this->assertStringNotContainsString($p->master_code, $dump);
    }
}
