<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesUiView;
use App\Http\Controllers\Controller;
use App\Models\GtmTag;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * GTMタグのマスタ管理（管理会社・GTM ID・メモ・紐付け店舗）
 */
class GtmTagController extends Controller
{
    use ResolvesUiView;

    public function index(Request $request)
    {
        $this->authorizeManager($request);

        $gtmTags = GtmTag::with(['shops' => fn ($q) => $q->withoutGlobalScope('visibleGroup')->select('shops.id', 'shops.name')])
            ->withCount('events')
            ->orderBy('company_name')
            ->orderBy('gtm_id')
            ->get()
            ->map(fn (GtmTag $t) => [
                'id' => $t->id,
                'company_name' => $t->company_name,
                'gtm_id' => $t->gtm_id,
                'memo' => $t->memo,
                'is_active' => $t->is_active,
                'shop_ids' => $t->shops->pluck('id')->values()->all(),
                'shop_names' => $t->shops->pluck('name')->values()->all(),
                'events_count' => $t->events_count,
            ]);

        return Inertia::render($this->viewFor('Admin/GtmTag/Index'), [
            'gtmTags' => $gtmTags,
            'shops' => Shop::withoutGlobalScope('visibleGroup')->where('is_active', true)->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManager($request);

        [$data, $shopIds] = $this->validated($request);
        $tag = GtmTag::create($data);
        $tag->shops()->sync($shopIds);

        return redirect()->route('admin.gtm-tags.index')->with('success', "GTMタグ「{$tag->gtm_id}」を追加しました。");
    }

    public function update(Request $request, GtmTag $gtmTag)
    {
        $this->authorizeManager($request);

        [$data, $shopIds] = $this->validated($request, $gtmTag);
        $gtmTag->update($data);
        $gtmTag->shops()->sync($shopIds);

        // LPは events.gtm_id を出力しているので、IDを変えたら紐付くイベントにも反映する
        $synced = $gtmTag->events()
            ->where(fn ($q) => $q->whereNull('gtm_id')->orWhere('gtm_id', '!=', $gtmTag->gtm_id))
            ->update(['gtm_id' => $gtmTag->gtm_id]);

        $message = 'GTMタグを更新しました。';
        if ($synced > 0) {
            $message .= "（紐付くイベント{$synced}件のGTM IDも更新）";
        }

        return redirect()->route('admin.gtm-tags.index')->with('success', $message);
    }

    public function destroy(Request $request, GtmTag $gtmTag)
    {
        $this->authorizeManager($request);

        $count = $gtmTag->events()->count();
        if ($count > 0) {
            return redirect()->route('admin.gtm-tags.index')
                ->with('error', "イベント{$count}件で使用中のため削除できません。使わない場合は無効にしてください。");
        }

        $gtmTag->delete();

        return redirect()->route('admin.gtm-tags.index')->with('success', 'GTMタグを削除しました。');
    }

    private function validated(Request $request, ?GtmTag $gtmTag = null): array
    {
        $request->merge(['gtm_id' => strtoupper(trim((string) $request->input('gtm_id')))]);

        $validated = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'gtm_id' => ['required', 'string', 'regex:/^GTM-[A-Z0-9]{4,12}$/', Rule::unique('gtm_tags', 'gtm_id')->ignore($gtmTag?->id)],
            'memo' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'integer|exists:shops,id',
        ], [
            'gtm_id.regex' => 'GTM IDは「GTM-XXXXXXX」の形式で入力してください。',
            'gtm_id.unique' => 'このGTM IDは既に登録されています。',
        ]);

        $shopIds = $validated['shop_ids'] ?? [];
        unset($validated['shop_ids']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        return [$validated, $shopIds];
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->isManager(), 403);
    }
}
