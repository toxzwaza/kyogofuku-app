<?php
// SHRINK_MODE=preview（読むだけ）／apply（管理画面と同じ TimeslotController::destroy で削除）
$MODE = getenv('SHRINK_MODE') ?: 'preview';
$targets = App\Models\EventTimeslot::whereBetween('id',[6665,8875])
  ->whereIn('event_id',[18,19,20,24])
  ->where('start_at','>=','2027-01-01 00:00:00')
  ->orderBy('id')->get();
$keep = []; $del = [];
foreach ($targets as $t) {
  $q = App\Models\EventReservation::where('event_id',$t->event_id)->where('reservation_datetime',$t->start_at->format('Y-m-d H:i:s'));
  $t->venue_id ? $q->where('venue_id',$t->venue_id) : $q->whereNull('venue_id');
  $n = $q->count(); // キャンセル済みも数える
  if ($n > 0) $keep[] = "id {$t->id} ev{$t->event_id} v{$t->venue_id} {$t->start_at} 予約{$n}件"; else $del[] = $t;
}
echo "対象(1/1以降・id 6665〜8875): ".count($targets)."件／予約ありで残す: ".count($keep)."件／消す: ".count($del)."件\n";
foreach ($keep as $k) echo "  残す: $k\n";
$by=[]; foreach ($del as $t){ $by[$t->event_id][]=$t->id; }
foreach ($by as $e=>$ids) echo "  ev{$e}: ".count($ids)."件 id ".min($ids)."〜".max($ids)."（連番".((max($ids)-min($ids)+1)==count($ids)?'':'でない')."）\n";
echo "  12月の枠（残す）: ".App\Models\EventTimeslot::whereBetween('id',[6665,8875])->where('start_at','<','2027-01-01')->count()."件\n";
if ($MODE !== 'apply') return;
$ctl = app(App\Http\Controllers\Admin\TimeslotController::class);
$ok=0; $ng=[];
foreach ($del as $t) {
  if ($t->id < 6665 || $t->id > 8875) { $ng[]=$t->id; continue; }
  $ctl->destroy(Illuminate\Http\Request::create('/x','DELETE'), $t);
  App\Models\EventTimeslot::whereKey($t->id)->exists() ? $ng[]=$t->id : $ok++;
}
echo "削除: {$ok}件／消えなかった: ".implode(',',$ng)."\n";
