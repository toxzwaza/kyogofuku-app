<?php

namespace Tests\Feature\Admin;

use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 共有ノートのステータス（未対応→議題→対応済み）と記録（対応記録・決定事項・状態の変更）
 */
class IncidentStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;      // 岡山店の一般スタッフ

    private User $other;      // 岡山店の別の一般スタッフ

    private User $fukuiStaff; // 福井店の一般スタッフ

    protected function setUp(): void
    {
        parent::setUp();

        $okayama = Shop::create(['name' => '岡山店', 'group_key' => 'okayama', 'is_active' => true]);
        $fukui = Shop::create(['name' => '福井店', 'group_key' => 'fukui', 'is_active' => true]);

        $this->staff = User::factory()->create(['attendance_role' => null]);
        $this->staff->shops()->attach($okayama->id, ['main' => true]);
        $this->other = User::factory()->create(['attendance_role' => null]);
        $this->other->shops()->attach($okayama->id);
        $this->fukuiStaff = User::factory()->create(['attendance_role' => null]);
        $this->fukuiStaff->shops()->attach($fukui->id);
    }

    private function incident(string $group = 'okayama', ?User $by = null): Incident
    {
        return Incident::create([
            'group_key'   => $group,
            'created_by'  => ($by ?? $this->staff)->id,
            'occurred_on' => now()->toDateString(),
            'shop_name'   => '岡山店',
            'category'    => '前撮り',
            'kind'        => 'クレーム',
            'title'       => '重ね衿が入っていなかった',
            'what'        => '小物セットに重ね衿がなかった。',
            'action'      => '浜店から取り寄せた。',
        ]);
    }

    private function setStatus(Incident $i, string $status): void
    {
        $i->status = $status;
        $i->save();
    }

    public function test_new_record_starts_open_and_status_is_not_fillable(): void
    {
        $this->actingAs($this->staff)->post(route('admin.incidents.store'), [
            'occurred_on' => now()->toDateString(),
            'shop_name'   => '岡山店',
            'category'    => '前撮り',
            'kind'        => 'クレーム',
            'title'       => 't',
            'what'        => 'w',
            'action'      => 'a',
            'status'      => 'resolved',   // 送られても無視する
        ])->assertRedirect();

        $this->assertSame('open', Incident::sole()->status);
    }

    public function test_record_does_not_change_status_and_ignores_sent_user_and_time(): void
    {
        $i = $this->incident();

        $this->actingAs($this->other)
            ->post(route('admin.incidents.comments.store', $i), [
                'body'       => '店長に相談した',
                'user_id'    => $this->staff->id,
                'created_at' => '2000-01-01 00:00:00',
                'type'       => 'decision',
            ])
            ->assertSessionHas('success', '対応記録を追加しました。');

        $c = IncidentComment::sole();
        $this->assertSame('record', $c->type);
        $this->assertSame($this->other->id, $c->user_id);
        $this->assertTrue($c->created_at->isToday());
        $this->assertSame('open', $i->fresh()->status);
    }

    public function test_anyone_in_group_can_make_agenda(): void
    {
        $i = $this->incident();

        $this->actingAs($this->other)
            ->put(route('admin.incidents.status.update', $i), ['status' => 'agenda'])
            ->assertSessionHas('success', '「議題」にしました。');

        $i->refresh();
        $this->assertSame('agenda', $i->status);
        $this->assertSame($this->other->id, $i->status_changed_by);
        $this->assertNotNull($i->status_changed_at);

        $log = IncidentComment::where('type', 'status')->sole();
        $this->assertSame(['open', 'agenda', $this->other->id], [$log->from_status, $log->to_status, $log->user_id]);

        // 同じ状態にはしない
        $this->actingAs($this->other)
            ->put(route('admin.incidents.status.update', $i), ['status' => 'agenda'])
            ->assertSessionHas('error');
        $this->assertSame(1, IncidentComment::where('type', 'status')->count());
    }

    public function test_decision_resolves_from_open_or_agenda(): void
    {
        foreach (['open', 'agenda'] as $from) {
            $i = $this->incident();
            $this->setStatus($i, $from);

            $this->actingAs($this->other)
                ->post(route('admin.incidents.decisions.store', $i), ['body' => '前日に小物を照合する'])
                ->assertSessionHas('success', '決定事項を登録し、対応済みにしました。');

            $i->refresh();
            $this->assertSame('resolved', $i->status, $from);
            $this->assertSame($this->other->id, $i->status_changed_by);
            $this->assertSame(1, $i->comments()->where('type', 'decision')->count());
            $this->assertSame(1, $i->comments()->where('type', 'status')->where('from_status', $from)->where('to_status', 'resolved')->count());
        }
    }

    public function test_second_decision_on_resolved_adds_no_status_change(): void
    {
        $i = $this->incident();
        $this->actingAs($this->staff)->post(route('admin.incidents.decisions.store', $i), ['body' => '一つ目']);
        $this->actingAs($this->staff)->post(route('admin.incidents.decisions.store', $i), ['body' => '二つ目']);

        $this->assertSame(2, $i->comments()->where('type', 'decision')->count());
        $this->assertSame(1, $i->comments()->where('type', 'status')->count());
    }

    public function test_decision_requires_body(): void
    {
        $i = $this->incident();
        $this->actingAs($this->staff)
            ->post(route('admin.incidents.decisions.store', $i), ['body' => '  '])
            ->assertSessionHasErrors('body');
        $this->assertSame('open', $i->fresh()->status);
    }

    public function test_cannot_set_resolved_directly(): void
    {
        $i = $this->incident();
        $this->actingAs($this->staff)
            ->put(route('admin.incidents.status.update', $i), ['status' => 'resolved'])
            ->assertSessionHasErrors('status');
        $this->assertSame('open', $i->fresh()->status);
    }

    public function test_going_back_requires_reason(): void
    {
        $i = $this->incident();
        $this->setStatus($i, 'resolved');

        $this->actingAs($this->staff)
            ->put(route('admin.incidents.status.update', $i), ['status' => 'agenda', 'reason' => ''])
            ->assertSessionHasErrors(['reason' => '前の状態に戻すときは、理由を書いてください。']);
        $this->assertSame('resolved', $i->fresh()->status);

        $this->actingAs($this->staff)
            ->put(route('admin.incidents.status.update', $i), ['status' => 'open', 'reason' => '同じことがまた起きた'])
            ->assertSessionHasNoErrors();

        $this->assertSame('open', $i->fresh()->status);
        $log = IncidentComment::where('type', 'status')->sole();
        $this->assertSame(['resolved', 'open', '同じことがまた起きた'], [$log->from_status, $log->to_status, $log->body]);
    }

    public function test_other_group_cannot_touch_status_or_records(): void
    {
        $i = $this->incident();
        $c = IncidentComment::forceCreate(['incident_id' => $i->id, 'user_id' => $this->staff->id, 'body' => 'x']);

        $this->actingAs($this->fukuiStaff)->put(route('admin.incidents.status.update', $i), ['status' => 'agenda'])->assertNotFound();
        $this->actingAs($this->fukuiStaff)->post(route('admin.incidents.decisions.store', $i), ['body' => 'x'])->assertNotFound();
        $this->actingAs($this->fukuiStaff)->put(route('admin.incidents.comments.update', [$i, $c]), ['body' => 'y'])->assertNotFound();

        $this->assertSame('open', $i->fresh()->status);
    }

    public function test_author_can_edit_own_record_today_and_revision_is_kept(): void
    {
        $i = $this->incident();
        $this->actingAs($this->staff)->post(route('admin.incidents.comments.store', $i), ['body' => '直す前']);
        $c = IncidentComment::sole();

        $this->actingAs($this->staff)
            ->put(route('admin.incidents.comments.update', [$i, $c]), ['body' => '直した後'])
            ->assertSessionHas('success', '記録を直しました。');

        $c->refresh();
        $this->assertSame('直した後', $c->body);
        $this->assertNotNull($c->edited_at);
        $rev = $c->revisions()->sole();
        $this->assertSame(['直す前', $this->staff->id], [$rev->body, $rev->edited_by]);

        $this->actingAs($this->staff)
            ->get(route('admin.incidents.show', $i))
            ->assertInertia(fn (Assert $p) => $p
                ->where('incident.comments.0.editable', true)
                ->where('incident.comments.0.revisions.0.body', '直す前'));
    }

    public function test_others_and_next_day_and_status_logs_cannot_be_edited(): void
    {
        $i = $this->incident();
        $this->actingAs($this->staff)->post(route('admin.incidents.comments.store', $i), ['body' => '本文']);
        $c = IncidentComment::sole();

        // 他人
        $this->actingAs($this->other)->put(route('admin.incidents.comments.update', [$i, $c]), ['body' => '他人'])->assertForbidden();

        // 状態の変更の記録
        $this->actingAs($this->staff)->put(route('admin.incidents.status.update', $i), ['status' => 'agenda']);
        $log = IncidentComment::where('type', 'status')->sole();
        $this->actingAs($this->staff)->put(route('admin.incidents.comments.update', [$i, $log]), ['body' => 'x'])->assertForbidden();

        // 別の共有事項の記録を指定
        $j = $this->incident();
        $this->actingAs($this->staff)->put(route('admin.incidents.comments.update', [$j, $c]), ['body' => 'x'])->assertNotFound();

        // 翌日
        $this->travel(1)->days();
        $this->actingAs($this->staff)->put(route('admin.incidents.comments.update', [$i, $c]), ['body' => '翌日'])->assertForbidden();

        $this->assertSame('本文', $c->fresh()->body);
        $this->assertSame(0, $c->revisions()->count());
    }

    public function test_index_status_filter_and_counts(): void
    {
        $a = $this->incident();
        $b = $this->incident();
        $this->incident();
        $this->setStatus($a, 'agenda');
        $this->setStatus($b, 'resolved');
        $this->incident('fukui', $this->fukuiStaff);

        $this->actingAs($this->staff)
            ->get(route('admin.incidents.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('statusCounts', ['open' => 1, 'agenda' => 1, 'resolved' => 1, 'all' => 3])
                ->has('incidents.data', 3));

        $this->actingAs($this->staff)
            ->get(route('admin.incidents.index', ['st' => 'agenda']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('filters.st', 'agenda')
                ->has('incidents.data', 1)
                ->where('incidents.data.0.status', 'agenda'));

        // 知らない値は「すべて」
        $this->actingAs($this->staff)
            ->get(route('admin.incidents.index', ['st' => 'xxx']))
            ->assertInertia(fn (Assert $p) => $p->where('filters.st', '')->has('incidents.data', 3));
    }
}
