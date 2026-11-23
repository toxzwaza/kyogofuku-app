<?php

namespace App\Services\Meibo;

use App\Models\Meibo\MeiboPerson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 名簿の読み取り（一覧・検索・全選択・詳細・集計・地図）。
 * 返す形は画面のダミー（resources/js/services/meibo/dummyAdapter.js）と同じにそろえる。
 */
class MeiboQuery
{
    /** 一覧で返す項目（画面が使う 15 項目だけ。生年月日・性別・保護者フリガナは返さない） */
    public const LIST_COLUMNS = [
        'id', 'master_code', 'pref', 'seijin_year', 'name', 'kana', 'zip', 'address', 'tel', 'mobile',
        'guardian_name', 'dm_status', 'dm_note', 'source_versions', 'merge_count',
    ];

    /** キーワードで探す列 */
    private const KEYWORD_COLUMNS = ['name', 'kana', 'guardian_name', 'address', 'zip', 'tel', 'mobile'];

    /**
     * 絞り込み：pref・seijin_year・status・source（r11／other）・q（キーワード。全角・半角スペースの違いは無視）
     */
    public function filtered(array $f): Builder
    {
        $q = MeiboPerson::query();
        if (! empty($f['pref'])) {
            $q->where('meibo_persons.pref', $f['pref']);
        }
        if (! empty($f['seijin_year'])) {
            $q->where('meibo_persons.seijin_year', (int) $f['seijin_year']);
        }
        if (! empty($f['status'])) {
            $q->where('meibo_persons.dm_status', $f['status']);
        }
        if (($f['source'] ?? '') === 'r11') {
            $q->fromR11();
        } elseif (($f['source'] ?? '') === 'other') {
            $q->notFromR11();
        }
        $kw = self::normalizeKeyword($f['q'] ?? '');
        if ($kw !== '') {
            $like = '%' . addcslashes($kw, '\\%_') . '%';
            $q->where(function (Builder $w) use ($like) {
                foreach (self::KEYWORD_COLUMNS as $col) {
                    $w->orWhereRaw("REPLACE(REPLACE(COALESCE(meibo_persons.{$col}, ''), ' ', ''), '　', '') LIKE ?", [$like]);
                }
            });
        }

        return $q;
    }

    public static function normalizeKeyword(?string $q): string
    {
        return preg_replace('/[\s　]+/u', '', (string) $q);
    }

    /** 一覧の並び（県・成人式年・フリガナ・氏名。同じなら id） */
    private function ordered(Builder $q): Builder
    {
        return $q->orderBy('pref')->orderBy('seijin_year')->orderBy('kana')->orderBy('name')->orderBy('id');
    }

    public function options(): array
    {
        return [
            'prefs' => MeiboPerson::whereNotNull('pref')->distinct()->orderBy('pref')->pluck('pref')->values()->all(),
            'seijin_years' => MeiboPerson::whereNotNull('seijin_year')->distinct()->orderBy('seijin_year')->pluck('seijin_year')->map(fn ($y) => (int) $y)->values()->all(),
            'statuses' => MeiboPerson::STATUSES,
        ];
    }

    public function page(array $f, int $page, int $pageSize): array
    {
        $base = $this->filtered($f);
        $total = (clone $base)->count();
        $items = $this->ordered($base)
            ->forPage(max(1, $page), $pageSize)
            ->get(self::LIST_COLUMNS);

        return ['items' => $items->map(fn ($p) => self::listItem($p))->all(), 'total' => $total];
    }

    /** 全選択用：絞り込みに合う人の id（一覧と同じ並び） */
    public function ids(array $f, int $limit): array
    {
        return $this->ordered($this->filtered($f))->limit($limit)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** 渡した id の順に返す（ラベルの見本用） */
    public function byIds(array $ids): array
    {
        $rows = MeiboPerson::whereIn('id', $ids)->get(self::LIST_COLUMNS)->keyBy('id');

        return collect($ids)->map(fn ($id) => $rows->get($id))->filter()->map(fn ($p) => self::listItem($p))->values()->all();
    }

    /** 選んだ人の件数と、うち R11 成人名簿の人の数（全選択の 5 万件でも URL に載せずに数えるため POST で受ける） */
    public function summary(array $ids): array
    {
        $count = 0;
        $r11 = 0;
        foreach (array_chunk($ids, 5000) as $chunk) {
            $count += MeiboPerson::whereIn('id', $chunk)->count();
            $r11 += MeiboPerson::whereIn('id', $chunk)->fromR11()->count();
        }

        return ['count' => $count, 'r11' => $r11];
    }

    public function detail(MeiboPerson $p): array
    {
        $logs = $p->dmLogs()->with('changedBy:id,name')->orderByDesc('changed_at')->orderByDesc('id')->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'changed_at' => $l->changed_at?->format('Y-m-d\TH:i:s'),
                'old_status' => $l->old_status,
                'new_status' => $l->new_status,
                'note' => (string) $l->note,
                'changed_by' => $l->changedBy?->name ?? '（取り込み）',
            ])->all();

        return self::listItem($p) + ['logs' => $logs];
    }

    public function stats(): array
    {
        $byStatus = array_fill_keys(MeiboPerson::STATUSES, 0);
        foreach (MeiboPerson::select('dm_status', DB::raw('COUNT(*) as c'))->groupBy('dm_status')->get() as $r) {
            $byStatus[$r->dm_status] = (int) $r->c;
        }
        $byYear = MeiboPerson::select('pref', 'seijin_year', DB::raw('COUNT(*) as c'))
            ->groupBy('pref', 'seijin_year')->orderBy('pref')->orderBy('seijin_year')->get()
            ->map(fn ($r) => ['pref' => $r->pref, 'seijin_year' => $r->seijin_year === null ? null : (int) $r->seijin_year, 'c' => (int) $r->c])
            ->all();

        return [
            'total' => MeiboPerson::count(),
            'by_status' => $byStatus,
            'by_year' => $byYear,
            'r11' => MeiboPerson::fromR11()->count(),
        ];
    }

    /** 郵便番号ごとの人数（座標つき）。座標の無い郵便番号の人は unmatched に数える */
    public function map(array $f): array
    {
        $base = $this->filtered($f);
        $rows = (clone $base)
            ->join('meibo_zip_geo as g', 'g.zip', '=', 'meibo_persons.zip')
            ->groupBy('meibo_persons.zip', 'meibo_persons.pref', 'g.lat', 'g.lng')
            ->orderByDesc('cnt')
            ->get([
                'meibo_persons.zip', 'meibo_persons.pref', 'g.lat', 'g.lng',
                DB::raw('COUNT(*) as cnt'), DB::raw('MIN(meibo_persons.address) as addr'),
            ]);
        $unmatched = (clone $base)
            ->leftJoin('meibo_zip_geo as g', 'g.zip', '=', 'meibo_persons.zip')
            ->whereNull('g.zip')
            ->count();

        $points = $rows->map(fn ($r) => [
            'zip' => $r->zip,
            'pref' => $r->pref,
            'lat' => (float) $r->lat,
            'lng' => (float) $r->lng,
            'count' => (int) $r->cnt,
            'area' => ($r->pref ?? '') . self::city($r->addr),
        ])->all();

        return [
            'points' => $points,
            'plotted' => array_sum(array_column($points, 'count')),
            'spots' => count($points),
            'unmatched' => $unmatched,
        ];
    }

    /** 住所の先頭から市区町村を取り出す（地図のふきだし用。名簿管理システムの _city と同じ） */
    public static function city(?string $addr): string
    {
        if (! $addr) {
            return '';
        }
        if (preg_match('/^(.+?市.+?区|.+?[市区郡].*?[町村]|.+?[市区郡])/u', $addr, $m)) {
            return $m[1];
        }

        return mb_substr($addr, 0, 6);
    }

    /** 画面の 1 行の形（管理番号は code。空は ''） */
    public static function listItem(MeiboPerson $p): array
    {
        $s = fn ($v) => $v === null ? '' : (string) $v;

        return [
            'id' => (int) $p->id,
            'code' => $p->master_code,
            'pref' => $s($p->pref),
            'seijin_year' => $p->seijin_year,
            'name' => $p->name,
            'kana' => $s($p->kana),
            'zip' => $s($p->zip),
            'address' => $s($p->address),
            'tel' => $s($p->tel),
            'mobile' => $s($p->mobile),
            'guardian_name' => $s($p->guardian_name),
            'dm_status' => $p->dm_status,
            'dm_note' => $s($p->dm_note),
            'source_versions' => $s($p->source_versions),
            'merge_count' => (int) $p->merge_count,
        ];
    }

    /** @param  Collection|array  $ids */
    public static function cleanIds($ids, int $limit): array
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        return collect($ids)->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->unique()->take($limit)->values()->all();
    }
}
