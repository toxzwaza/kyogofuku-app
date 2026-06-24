<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaTag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MediaTagController extends Controller
{
    /**
     * タグツリー（フラット配列＋パス・件数）をJSONで返す
     */
    public function index()
    {
        return response()->json([
            'tags' => $this->tagList(),
        ]);
    }

    /**
     * タグを作成（親を指定して子タグも作成可）
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'parent_id' => 'nullable|integer|exists:media_tags,id',
        ]);

        $name = trim($validated['name']);
        $parentId = $validated['parent_id'] ?? null;

        $this->assertUniqueSibling($name, $parentId);

        $sortOrder = (int) MediaTag::where('parent_id', $parentId)->max('sort_order') + 1;

        $tag = MediaTag::create([
            'name' => $name,
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
        ]);

        return response()->json([
            'success' => true,
            'tag' => $tag,
            'tags' => $this->tagList(),
        ]);
    }

    /**
     * タグのリネーム／移動（親付け替え）
     */
    public function update(Request $request, MediaTag $mediaTag)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'parent_id' => 'nullable|integer|exists:media_tags,id',
        ]);

        $name = trim($validated['name']);
        $parentId = array_key_exists('parent_id', $validated) ? $validated['parent_id'] : $mediaTag->parent_id;

        // 循環参照防止：自分自身・子孫を親に指定できない
        if ($parentId !== null) {
            $descendantIds = $mediaTag->selfAndDescendantIds();
            if (in_array((int) $parentId, $descendantIds, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'このタグ自身またはその子孫を親に指定することはできません。',
                ]);
            }
        }

        $this->assertUniqueSibling($name, $parentId, $mediaTag->id);

        $mediaTag->update([
            'name' => $name,
            'parent_id' => $parentId,
        ]);

        return response()->json([
            'success' => true,
            'tag' => $mediaTag->fresh(),
            'tags' => $this->tagList(),
        ]);
    }

    /**
     * タグを削除（子孫タグ・メディア紐付けはFKカスケードで除去）
     */
    public function destroy(MediaTag $mediaTag)
    {
        $removedIds = $mediaTag->selfAndDescendantIds();

        // FK の cascadeOnDelete により子孫タグと media_file_tag は連鎖削除される
        $mediaTag->delete();

        return response()->json([
            'success' => true,
            'removed_count' => count($removedIds),
            'tags' => $this->tagList(),
        ]);
    }

    /**
     * フラットなタグ一覧（id, parent_id, name, sort_order, full_path, media_count）
     *
     * @return array<int, array<string, mixed>>
     */
    private function tagList(): array
    {
        $all = MediaTag::query()
            ->withCount('mediaFiles')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $all->map(function (MediaTag $tag) use ($all) {
            return [
                'id' => $tag->id,
                'parent_id' => $tag->parent_id,
                'name' => $tag->name,
                'sort_order' => $tag->sort_order,
                'full_path' => $tag->fullPath($all),
                'media_count' => $tag->media_files_count,
            ];
        })->values()->all();
    }

    /**
     * 同一階層に同名タグが無いことを確認
     */
    private function assertUniqueSibling(string $name, ?int $parentId, ?int $ignoreId = null): void
    {
        $exists = MediaTag::query()
            ->where('name', $name)
            ->when($parentId === null, fn ($q) => $q->whereNull('parent_id'))
            ->when($parentId !== null, fn ($q) => $q->where('parent_id', $parentId))
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => '同じ階層に同名のタグが既に存在します。',
            ]);
        }
    }
}
