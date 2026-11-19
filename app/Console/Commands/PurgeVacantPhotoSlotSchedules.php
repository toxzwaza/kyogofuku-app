<?php

namespace App\Console\Commands;

use App\Models\StaffSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 空き枠（お客様の入っていない前撮り枠）にひも付いた予定を消す。
 * 昔は枠を作ると予定も作っていたため、本番に出すときに1回だけ流す（設計書 6章）。
 * 予定を消すと StaffScheduleObserver が Google カレンダーの予定も消す。
 */
class PurgeVacantPhotoSlotSchedules extends Command
{
    protected $signature = 'photo-slots:purge-vacant-schedules {--dry-run : 消さずに件数と一覧だけ出す}';

    protected $description = '空きの前撮り枠にひも付いた予定（とGoogleカレンダーの予定）を消す';

    public function handle(): int
    {
        $query = StaffSchedule::query()
            ->whereNotNull('photo_slot_id')
            ->whereHas('photoSlot', fn ($q) => $q->whereNull('customer_id'));

        $total = (clone $query)->count();
        $synced = (clone $query)->whereHas('googleCalendarEventSyncs')->count();

        $this->info("空き枠の予定：{$total}件（うち Google カレンダーに送ってあるもの {$synced}件）");

        if ($this->option('dry-run')) {
            $rows = (clone $query)->with('photoSlot.studio')->orderBy('start_at')->limit(50)->get()->map(fn (StaffSchedule $s) => [
                $s->id,
                optional($s->start_at)->format('Y-m-d H:i'),
                $s->photoSlot?->studio?->name,
                $s->title,
            ]);
            if ($rows->isNotEmpty()) {
                $this->table(['予定ID', '日時', 'スタジオ', '件名'], $rows->all());
                if ($total > 50) {
                    $this->line('（先頭50件だけ出しています）');
                }
            }
            $this->comment('--dry-run のため、何も消していません。');

            return self::SUCCESS;
        }

        if ($total === 0) {
            return self::SUCCESS;
        }

        $deleted = 0;
        $failed = 0;
        $query->chunkById(200, function ($schedules) use (&$deleted, &$failed) {
            foreach ($schedules as $schedule) {
                try {
                    $schedule->delete();
                    $deleted++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('[photo-slots:purge-vacant-schedules] 予定を消せませんでした', [
                        'schedule_id' => $schedule->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info("消した予定：{$deleted}件".($failed ? "／消せなかった予定：{$failed}件（ログを見てください）" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
