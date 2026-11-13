<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * 共有ノート（旧称 事案記録）。
 *
 * - 店舗グループ（岡山／福井）ごとに分けて共有する。記録のグループは記録した人のグループ。
 * - 一般スタッフ・店舗管理者は自分のグループだけ。勤怠管理者・システム管理者は両グループを切り替えて見る。
 * - 直せるのは、記録した本人と、店舗管理者以上（グループ内すべて）。
 * 要件書：_Company/02_京呉服平田/10_プロジェクト/20261026_業務マニュアル導入/成果物/20261113_要件書_事案記録・対応マニュアル.md
 */
class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $group = $this->currentGroup($request);

        $base = Incident::where('group_key', $group);

        $tab = array_key_exists($request->query('tab'), Incident::TABS) ? $request->query('tab') : 'all';
        $filters = [
            'tab'  => $tab,
            'cat'  => (string) $request->query('cat', ''),
            'shop' => (string) $request->query('shop', ''),
            'kind' => (string) $request->query('kind', ''),
            'q'    => trim((string) $request->query('q', '')),
        ];

        $query = (clone $base)->inTab($tab)
            ->when($filters['cat'] !== '', fn ($q) => $q->where('category', $filters['cat']))
            ->when($filters['shop'] !== '', fn ($q) => $q->where('shop_name', $filters['shop']))
            ->when($filters['kind'] !== '', fn ($q) => $q->where('kind', $filters['kind']))
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $like = '%'.addcslashes($filters['q'], '%_\\').'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)
                    ->orWhere('what', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('consult', 'like', $like));
            });

        $incidents = $query->with('creator:id,name')
            ->orderByDesc('occurred_on')->orderByDesc('id')
            ->paginate(30)->withQueryString()
            ->through(fn (Incident $i) => [
                'id'          => $i->id,
                'occurred_on' => $i->occurred_on?->format('Y-m-d'),
                'shop_name'   => $i->shop_name,
                'category'    => $i->category,
                'kind'        => $i->kind,
                'title'       => $i->title,
                'excerpt'     => Str::limit(preg_replace('/\s+/u', ' ', $i->what), 60),
                'creator'     => $i->creator?->name,
            ]);

        $tabCounts = [];
        foreach (array_keys(Incident::TABS) as $key) {
            $tabCounts[$key] = (clone $base)->inTab($key)->count();
        }

        return Inertia::render('Admin/Incident/Index', [
            'incidents' => $incidents,
            'filters'   => $filters,
            'tabCounts' => $tabCounts,
            'options'   => [
                'categories' => (clone $base)->distinct()->orderBy('category')->pluck('category'),
                'shops'      => (clone $base)->distinct()->orderBy('shop_name')->pluck('shop_name'),
                'kinds'      => collect(Incident::KINDS)
                    ->merge((clone $base)->distinct()->pluck('kind'))->unique()->values(),
            ],
            'group'     => $group,
            'groups'    => $this->groupOptions($user),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $group = $this->currentGroup($request);
        $shops = $this->shopNames($group);

        return Inertia::render('Admin/Incident/Create', [
            'group'      => $group,
            'groups'     => $this->groupOptions($user),
            'shops'      => $shops,
            'myShop'     => $this->myShopName($user, $shops),
            'today'      => now()->toDateString(),
            'categories' => Incident::CATEGORIES,
            'kinds'      => Incident::KINDS,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $group = $this->currentGroup($request);
        $data = $this->validated($request);

        $incident = Incident::create($data + [
            'group_key'  => $group,
            'created_by' => $user->id,
        ]);

        return redirect()->route('admin.incidents.show', $incident)->with('success', '共有しました。');
    }

    public function show(Request $request, Incident $incident)
    {
        $user = $request->user();
        $this->authorizeView($user, $incident);

        $incident->load(['creator:id,name', 'comments.user:id,name']);

        return Inertia::render('Admin/Incident/Show', [
            'incident' => [
                'id'          => $incident->id,
                'group_key'   => $incident->group_key,
                'occurred_on' => $incident->occurred_on?->format('Y-m-d'),
                'shop_name'   => $incident->shop_name,
                'category'    => $incident->category,
                'kind'        => $incident->kind,
                'title'       => $incident->title,
                'what'        => $incident->what,
                'action'      => $incident->action,
                'consult'     => $incident->consult,
                'creator'     => $incident->creator?->name,
                'created_at'  => $incident->created_at?->format('Y-m-d H:i'),
                'comments'    => $incident->comments->map(fn (IncidentComment $c) => [
                    'id'         => $c->id,
                    'user'       => $c->user?->name,
                    'body'       => $c->body,
                    'created_at' => $c->created_at?->format('Y-m-d H:i'),
                ])->values(),
            ],
            'canEdit'   => $this->canEdit($user, $incident),
            'groupName' => Incident::GROUPS[$incident->group_key] ?? $incident->group_key,
        ]);
    }

    public function edit(Request $request, Incident $incident)
    {
        $user = $request->user();
        $this->authorizeEdit($user, $incident);

        return Inertia::render('Admin/Incident/Edit', [
            'incident'   => [
                'id'          => $incident->id,
                'occurred_on' => $incident->occurred_on?->format('Y-m-d'),
                'shop_name'   => $incident->shop_name,
                'category'    => $incident->category,
                'kind'        => $incident->kind,
                'title'       => $incident->title,
                'what'        => $incident->what,
                'action'      => $incident->action,
                'consult'     => $incident->consult,
            ],
            'shops'      => collect($this->shopNames($incident->group_key))
                ->merge(Incident::where('group_key', $incident->group_key)->distinct()->pluck('shop_name'))
                ->unique()->values(),
            'today'      => now()->toDateString(),
            'categories' => Incident::CATEGORIES,
            'kinds'      => Incident::KINDS,
        ]);
    }

    public function update(Request $request, Incident $incident)
    {
        $user = $request->user();
        $this->authorizeEdit($user, $incident);

        $incident->update($this->validated($request));

        return redirect()->route('admin.incidents.show', $incident)->with('success', '保存しました。');
    }

    public function storeComment(Request $request, Incident $incident)
    {
        $user = $request->user();
        $this->authorizeView($user, $incident);

        $data = $request->validate(['body' => 'required|string|max:2000'], [], ['body' => '経過']);

        $incident->comments()->create(['user_id' => $user->id, 'body' => trim($data['body'])]);

        return redirect()->route('admin.incidents.show', $incident)->with('success', '経過を追加しました。');
    }

    /* ---------------------------------------------------------------- */

    private function validated(Request $request): array
    {
        $kind = trim((string) $request->input('kind'));

        $data = $request->validate([
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'shop_name'   => ['required', 'string', 'max:100'],
            'category'    => ['required', 'string', 'max:50'],
            'kind'        => ['required', 'string', 'max:50'],
            'title'       => ['required', 'string', 'max:100'],
            'what'        => ['required', 'string', 'max:5000'],
            'action'      => [Rule::requiredIf(Incident::actionRequired($kind)), 'nullable', 'string', 'max:5000'],
            'consult'     => ['nullable', 'string', 'max:5000'],
        ], [
            'occurred_on.before_or_equal' => '発生日に未来の日付は選べません。',
        ], [
            'occurred_on' => '発生日',
            'shop_name'   => '店舗',
            'category'    => '区分',
            'kind'        => '種類',
            'title'       => '件名',
            'what'        => '内容',
            'action'      => 'その場でした対応',
            'consult'     => '困っていること・相談したいこと',
        ]);

        $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
        if (Incident::actionSkipped($data['kind'])) {
            $data['action'] = null;
        }

        return $data;
    }

    /** 見られるグループ（管理者は全グループ） */
    private function visibleGroups(User $user): array
    {
        if ($user->isAttendanceManager()) {
            return array_keys(Incident::GROUPS);
        }

        return $user->shops()->withoutGlobalScope('visibleGroup')
            ->whereNotNull('group_key')->pluck('group_key')->unique()
            ->filter(fn ($g) => isset(Incident::GROUPS[$g]))->values()->all();
    }

    /** いま見ているグループ。?group= は見られるグループの中だけ受け付ける */
    private function currentGroup(Request $request): string
    {
        $groups = $this->visibleGroups($request->user());
        abort_if(empty($groups), 403, '店舗グループが設定されていないため、共有ノートを使えません。');

        $asked = (string) $request->input('group', '');
        if (in_array($asked, $groups, true)) {
            return $asked;
        }

        // 管理者で所属店舗があればそのグループを最初に出す
        $own = $request->user()->shops()->withoutGlobalScope('visibleGroup')
            ->whereNotNull('group_key')->pluck('group_key')->first();

        return in_array($own, $groups, true) ? $own : $groups[0];
    }

    /** グループを切り替えられる人だけに選択肢を渡す */
    private function groupOptions(User $user): array
    {
        $groups = $this->visibleGroups($user);
        if (count($groups) < 2) {
            return [];
        }

        return array_map(fn ($g) => ['value' => $g, 'label' => Incident::GROUPS[$g]], $groups);
    }

    private function shopNames(string $group): array
    {
        return Shop::withoutGlobalScope('visibleGroup')
            ->where('group_key', $group)->where('is_active', true)
            ->orderBy('id')->pluck('name')->all();
    }

    private function myShopName(User $user, array $shops): ?string
    {
        $names = $user->shops()->withoutGlobalScope('visibleGroup')
            ->orderByDesc('shop_user.main')->pluck('shops.name');

        return $names->first(fn ($n) => in_array($n, $shops, true)) ?? ($shops[0] ?? null);
    }

    private function authorizeView(User $user, Incident $incident): void
    {
        abort_unless(in_array($incident->group_key, $this->visibleGroups($user), true), 404);
    }

    private function canEdit(User $user, Incident $incident): bool
    {
        if (! in_array($incident->group_key, $this->visibleGroups($user), true)) {
            return false;
        }

        return (int) $incident->created_by === (int) $user->id || $user->isManager();
    }

    private function authorizeEdit(User $user, Incident $incident): void
    {
        $this->authorizeView($user, $incident);
        abort_unless($this->canEdit($user, $incident), 403);
    }
}
