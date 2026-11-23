<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Meibo\MeiboDmLog;
use App\Models\Meibo\MeiboLabelTemplate;
use App\Models\Meibo\MeiboPerson;
use App\Services\Meibo\LabelPdf;
use App\Services\Meibo\MeiboQuery;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * 名簿（成人式 DM 名簿）の画面と、その画面が読む・書くデータ。設計は taskboard page-28。
 *
 * 別の API サーバーは作らず、この kyogofuku-app の 1 機能として DB（meibo_* の表）を直接読み書きする。
 * 画面（Inertia）は resources/js/services/meibo/ のアダプタを通してデータを取る：
 *  - config('meibo.data_source') = 'dummy'（既定）：画面の中の架空データ（dummyAdapter.js）
 *  - 'db'：このコントローラの data* の JSON ルート（ログインのセッション・CSRF つき。dbAdapter.js）
 *
 * 名簿は顧客になる前の方の個人情報（氏名・住所・電話）なので、店舗管理者以上に限る。
 * 汎用の操作ログ（LogActivity）からは外し（検索語＝氏名を URL ごと残さないため）、DM 状態の変更だけ要約を残す。
 */
class MeiboController extends Controller
{
    public const PAGE_SIZE_MAX = 200;

    /** ラベルの見本で一度に引く上限 */
    public const BY_IDS_MAX = 50;

    public function __construct(private MeiboQuery $query)
    {
        $this->middleware(function (Request $request, $next) {
            abort_unless($request->user()?->isManager(), 403);

            return $next($request);
        });
    }

    /** 画面が使うデータの取り方：'dummy'／'db' */
    public static function dataSource(): string
    {
        return in_array(config('meibo.data_source'), ['db', 'http'], true) ? 'db' : 'dummy';
    }

    // ---------------------------------------------------------------- 画面

    public function index()
    {
        return $this->render('Admin/Meibo/Index');
    }

    public function labels()
    {
        return $this->render('Admin/Meibo/Labels');
    }

    public function dashboard()
    {
        return $this->render('Admin/Meibo/Dashboard');
    }

    private function render(string $component)
    {
        $source = self::dataSource();

        return Inertia::render($component, [
            'dataSource' => $source,
            // 絞り込みの選択肢は開いたときに渡す（db のときだけ。ダミーは画面の中で作る）
            'meiboOptions' => $source === 'db' ? $this->query->options() : null,
        ]);
    }

    // ---------------------------------------------------------------- 読み取り（db のときだけ）

    public function options()
    {
        $this->ensureDb();

        return response()->json($this->query->options());
    }

    public function persons(Request $request)
    {
        $this->ensureDb();
        $f = $this->filters($request);
        $v = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:' . self::PAGE_SIZE_MAX],
        ]);

        return response()->json($this->query->page($f, (int) ($v['page'] ?? 1), (int) ($v['page_size'] ?? 50)));
    }

    public function personIds(Request $request)
    {
        $this->ensureDb();

        return response()->json(['ids' => $this->query->ids($this->filters($request), (int) config('meibo.select_limit', 50000))]);
    }

    public function personsByIds(Request $request)
    {
        $this->ensureDb();
        $ids = MeiboQuery::cleanIds($request->input('ids', ''), self::BY_IDS_MAX);

        return response()->json($this->query->byIds($ids));
    }

    /** 選んだ人の件数・うち R11 の人数（全選択の 5 万件を URL に載せないよう POST で受ける） */
    public function personsSummary(Request $request)
    {
        $this->ensureDb();
        $request->validate(['ids' => ['present', 'array']]);
        $ids = MeiboQuery::cleanIds($request->input('ids', []), (int) config('meibo.select_limit', 50000));

        return response()->json($this->query->summary($ids));
    }

    public function person(MeiboPerson $person)
    {
        $this->ensureDb();

        return response()->json($this->query->detail($person));
    }

    public function stats()
    {
        $this->ensureDb();

        return response()->json($this->query->stats());
    }

    public function map(Request $request)
    {
        $this->ensureDb();

        return response()->json($this->query->map($this->filters($request)));
    }

    // ---------------------------------------------------------------- DM 状態・備考

    /**
     * DM 状態・備考を変える。変わったときだけ変更履歴（誰が・いつ）を残す。1 つのトランザクションで行う。
     */
    public function updateDm(Request $request, MeiboPerson $person)
    {
        $this->ensureDb();
        $v = $request->validate([
            'dm_status' => ['required', Rule::in(MeiboPerson::STATUSES)],
            'dm_note' => ['nullable', 'string', 'max:255'],
        ]);
        $newStatus = $v['dm_status'];
        $newNote = trim((string) ($v['dm_note'] ?? ''));
        $userId = $request->user()->id;

        [$updated, $old] = DB::transaction(function () use ($person, $newStatus, $newNote, $userId) {
            $p = MeiboPerson::whereKey($person->id)->lockForUpdate()->firstOrFail();
            $old = ['status' => $p->dm_status, 'note' => (string) $p->dm_note];
            if ($old['status'] === $newStatus && $old['note'] === $newNote) {
                return [$p, null];
            }
            $p->update(['dm_status' => $newStatus, 'dm_note' => $newNote]);
            MeiboDmLog::create([
                'person_id' => $p->id,
                'old_status' => $old['status'],
                'new_status' => $newStatus,
                'old_note' => $old['note'],
                'note' => $newNote,
                'changed_by_user_id' => $userId,
                'changed_at' => now(),
            ]);

            return [$p, $old];
        });

        if ($old !== null) {
            $this->logDmChange($request, $updated, $old['status'], $newStatus, $old['note'] !== $newNote);
        }

        return response()->json(MeiboQuery::listItem($updated));
    }

    /** 操作ログには要約だけ（誰が・どの番号の人の状態を何から何へ）。氏名・住所・備考の文は残さない */
    private function logDmChange(Request $request, MeiboPerson $p, string $from, string $to, bool $noteChanged): void
    {
        $this->logSummary(
            $request,
            'update',
            'admin.meibo.data.persons.update',
            $from === $to
                ? "名簿 {$p->master_code} の DM 備考を変更"
                : "名簿 {$p->master_code} の DM 状態を変更（{$from}→{$to}）" . ($noteChanged ? '・備考も変更' : ''),
            ['dm_status' => $to, 'dm_note_changed' => $noteChanged],
            ['dm_status' => $from],
            $p->id,
        );
    }

    /** 名簿の操作ログ（要約だけ。氏名・住所・検索語は入れない）。残せなくても本体の処理は止めない */
    private function logSummary(Request $request, string $action, string $routeName, string $description, array $new = [], ?array $old = null, ?int $resourceId = null): void
    {
        try {
            $user = $request->user();
            ActivityLog::create([
                'user_id' => $user->id,
                'shop_id' => $user->shops()->first()?->id,
                'action_type' => $action,
                'resource_type' => 'MeiboPerson',
                'resource_id' => $resourceId,
                'route_name' => $routeName,
                'url' => route('admin.meibo.index'),
                'method' => $request->method(),
                'description' => $description,
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::error('名簿の操作ログを残せませんでした', ['error' => $e->getMessage(), 'route' => $routeName]);
        }
    }

    // ---------------------------------------------------------------- ラベル PDF

    /**
     * 選んだ人の宛名ラベル（A4・10 面／A-One 66732 相当）を PDF で返す。件数は X-Label-Count。
     *
     * - 選んだ人は DM 状態に関わらず印刷する（画面で選んだことを尊重する。名簿管理システムと同じ）
     * - 並びは 郵便番号 → カナ → 氏名（DB の照合順で並べる）
     * - 件数の上限は設けない。数万件でも落ちないよう、1 人ずつ描き（行を溜めない）、
     *   ページは縮めて持ち、できた PDF は一時ファイルから流して消す
     */
    public function labelsPdf(Request $request)
    {
        $this->ensureDb();
        $v = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'sender_zip' => ['nullable', 'string', 'max:10'],
            'sender_address' => ['nullable', 'string', 'max:255'],
            'sender_company' => ['nullable', 'string', 'max:100'],
            'addressee' => ['nullable', Rule::in(['self', 'guardian'])],
            'with_family' => ['nullable', 'boolean'],
            'tantou' => ['nullable', 'string', 'max:60'],
        ], [
            'ids.required' => 'ラベルを作る人を選んでください',
            'ids.min' => 'ラベルを作る人を選んでください',
        ]);
        $ids = MeiboQuery::cleanIds($v['ids'], PHP_INT_MAX);
        if (! $ids) {
            return response()->json(['message' => 'ラベルを作る人を選んでください'], 422);
        }

        @set_time_limit(0);
        self::ensureMemory('512M');

        $ordered = $this->query->labelOrder($ids);
        if (! $ordered) {
            return response()->json(['message' => '選んだ人が名簿に見つかりません（対象 0 件）'], 422);
        }

        $pdf = new LabelPdf([
            'sender' => [
                'zip' => $v['sender_zip'] ?? null,
                'address' => $v['sender_address'] ?? null,
                'company' => $v['sender_company'] ?? null,
            ],
            'addressee' => $v['addressee'] ?? 'self',
            'with_family' => (bool) ($v['with_family'] ?? false),
            'tantou' => trim((string) ($v['tantou'] ?? '')),
        ]);
        foreach ($this->query->labelRows($ordered) as $row) {
            $pdf->add($row);
        }

        $path = tempnam(sys_get_temp_dir(), 'meibo_labels_');
        try {
            $pdf->save($path);
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }
        $this->logSummary($request, 'export', 'admin.meibo.data.labels', "名簿のラベルを発行（{$pdf->count()}件）", ['count' => $pdf->count()]);

        return response()->download($path, 'labels.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Label-Count' => (string) $pdf->count(),
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    }

    /** memory_limit が $min より小さいときだけ上げる（-1＝無制限はそのまま） */
    private static function ensureMemory(string $min): void
    {
        $toBytes = function (string $v): int {
            $v = trim($v);
            if ($v === '-1') {
                return PHP_INT_MAX;
            }
            $n = (int) $v;

            return match (strtolower(substr($v, -1))) {
                'g' => $n * 1024 ** 3,
                'm' => $n * 1024 ** 2,
                'k' => $n * 1024,
                default => $n,
            };
        };
        if ($toBytes((string) ini_get('memory_limit')) < $toBytes($min)) {
            @ini_set('memory_limit', $min);
        }
    }

    // ---------------------------------------------------------------- ラベルのテンプレート

    public function templates()
    {
        $this->ensureDb();

        return response()->json(MeiboLabelTemplate::orderBy('id')->get()->map(fn ($t) => $this->templateItem($t)));
    }

    /** 追加。同じ名前があれば 409（画面で上書きの確認をしてから編集を呼ぶ） */
    public function storeTemplate(Request $request)
    {
        $this->ensureDb();
        $data = $this->templateInput($request);
        if (MeiboLabelTemplate::where('name', $data['name'])->exists()) {
            return $this->nameTaken($data['name']);
        }
        try {
            $t = MeiboLabelTemplate::create($data);
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                return $this->nameTaken($data['name']);
            }
            throw $e;
        }

        return response()->json($this->templateItem($t), 201);
    }

    /** 編集（上書き）。ほかのテンプレートと同じ名前にすると 409 */
    public function updateTemplate(Request $request, MeiboLabelTemplate $template)
    {
        $this->ensureDb();
        $data = $this->templateInput($request);
        if (MeiboLabelTemplate::where('name', $data['name'])->whereKeyNot($template->id)->exists()) {
            return $this->nameTaken($data['name']);
        }
        try {
            $template->update($data);
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                return $this->nameTaken($data['name']);
            }
            throw $e;
        }

        return response()->json($this->templateItem($template->fresh()));
    }

    public function destroyTemplate(MeiboLabelTemplate $template)
    {
        $this->ensureDb();
        $template->delete();

        return response()->json(['ok' => true]);
    }

    private function templateInput(Request $request): array
    {
        $request->merge(['name' => trim((string) $request->input('name', ''))]);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'sender_zip' => ['nullable', 'string', 'max:10'],
            'sender_address' => ['nullable', 'string', 'max:255'],
            'sender_company' => ['nullable', 'string', 'max:100'],
            'addressee' => ['nullable', Rule::in(['self', 'guardian'])],
            'with_family' => ['nullable', 'boolean'],
            'tantou' => ['nullable', 'string', 'max:60'],
        ], [
            'name.required' => 'テンプレート名を入れてください',
        ]);

        return [
            'name' => $v['name'],
            'sender_zip' => (string) ($v['sender_zip'] ?? ''),
            'sender_address' => (string) ($v['sender_address'] ?? ''),
            'sender_company' => (string) ($v['sender_company'] ?? ''),
            'addressee' => $v['addressee'] ?? 'self',
            'with_family' => (bool) ($v['with_family'] ?? true),
            'tantou' => (string) ($v['tantou'] ?? ''),
        ];
    }

    private function templateItem(MeiboLabelTemplate $t): array
    {
        return $t->only(['id', 'name', 'sender_zip', 'sender_address', 'sender_company', 'addressee', 'tantou'])
            + ['with_family' => (bool) $t->with_family];
    }

    private function nameTaken(string $name): JsonResponse
    {
        return response()->json(['message' => "「{$name}」という名前のテンプレートがすでにあります", 'code' => 'name_taken'], 409);
    }

    private function isDuplicate(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062 || str_contains($e->getMessage(), 'UNIQUE');
    }

    // ---------------------------------------------------------------- 共通

    /** 絞り込みの値（形の違う値は捨てる） */
    private function filters(Request $request): array
    {
        $v = $request->validate([
            'pref' => ['nullable', 'string', 'max:10'],
            'seijin_year' => ['nullable', 'integer', 'between:1900,2200'],
            'status' => ['nullable', Rule::in(MeiboPerson::STATUSES)],
            'source' => ['nullable', Rule::in(['r11', 'other'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return array_filter($v, fn ($x) => $x !== null && $x !== '');
    }

    /** データの JSON ルートは db のときだけ開ける（ダミーのあいだは本物の表を読ませない） */
    private function ensureDb(): void
    {
        abort_unless(self::dataSource() === 'db', 404);
    }
}
