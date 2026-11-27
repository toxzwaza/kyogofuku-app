<?php
// MODE=preview（DBを変えない）／apply（作成）。管理画面「予約枠の期間一括作成」と同じ TimeslotBulkController を使う
$MODE = getenv('BULK_MODE') ?: 'preview';
$FOUR = [18,19,20,24];
$cfg = [
  18 => ['venues'=>[1,9], 'times'=>['10:00','12:00','14:00','16:00']],
  19 => ['venues'=>[8],   'times'=>['10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00']],
  20 => ['venues'=>[10],  'times'=>['10:00','12:00','14:00','16:00','18:00']],
  24 => ['venues'=>[3],   'times'=>['10:00','11:00','13:00','14:00','15:00','16:00']],
];
$periods = [['2026-12-01','2027-02-28'],['2027-03-01','2027-03-31']]; // 1回93日以内
$ctl = app(App\Http\Controllers\Admin\TimeslotBulkController::class);
$plans = [];
// 先に4イベント全部のプレビューを取る（4イベント同士の重なりは今と同じく許す）
foreach ($cfg as $id => $c) foreach ($periods as [$s,$t]) {
  $payload = ['start_date'=>$s,'end_date'=>$t,'venue_ids'=>$c['venues'],'exclude_weekdays'=>[2,3],
    'times'=>array_map(fn($x)=>['time'=>$x,'capacity'=>1],$c['times']),'is_active'=>true];
  $ev = App\Models\Event::find($id);
  $res = $ctl->preview(Illuminate\Http\Request::create('/x','POST',$payload), $ev)->getData(true);
  $cells=[]; $skip=[]; $n=0;
  foreach ($res['cells'] as $cell) {
    $ok = $cell['status']==='create' || ($cell['status']==='conflict' && !array_diff(array_column($cell['conflicts'],'event_id'),$FOUR));
    if ($ok) { $cells[]=['date'=>$cell['date'],'venue_id'=>$cell['venue_id']]; $n+=count($cell['new_times']); }
    elseif ($cell['status']==='conflict') $skip[]=substr($cell['date'],5).'@v'.$cell['venue_id'].'('.implode(',',array_column($cell['conflicts'],'event_id')).')';
  }
  $plans[]=[$id,$payload,$cells];
  echo "ev{$id} {$s}〜{$t}: 作る日×会場=".count($cells)." 枠={$n} ／重なりで外す=".implode(' ',$skip)."\n";
}
if ($MODE !== 'apply') { echo "preview のみ\n"; return; }
foreach ($plans as [$id,$payload,$cells]) {
  $ev = App\Models\Event::find($id);
  $before = App\Models\EventTimeslot::max('id');
  $r = $ctl->store(Illuminate\Http\Request::create('/x','POST',$payload+['cells'=>$cells]), $ev);
  $after = App\Models\EventTimeslot::max('id');
  $errs = session('errors'); 
  echo "ev$id 作成: id ".($before+1)."〜$after ／ ".(session('success') ?? '').($errs? ' ERR '.json_encode($errs->all(),JSON_UNESCAPED_UNICODE):'')."\n";
  session()->forget(['success','errors']);
}
