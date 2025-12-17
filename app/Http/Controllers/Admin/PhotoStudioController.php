<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoStudio;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PhotoStudioController extends Controller
{
    /**
     * スタジオ一覧を表示
     */
    public function index()
    {
        $photoStudios = PhotoStudio::orderBy('name')->paginate(20);

        return Inertia::render('Admin/PhotoStudio/Index', [
            'photoStudios' => $photoStudios,
        ]);
    }

    /**
     * スタジオ追加フォームを表示
     */
    public function create()
    {
        return Inertia::render('Admin/PhotoStudio/Create');
    }

    /**
     * スタジオを保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        PhotoStudio::create($validated);

        return redirect()->route('admin.photo-studios.index')
            ->with('success', 'スタジオを追加しました。');
    }

    /**
     * スタジオ編集フォームを表示
     */
    public function edit(PhotoStudio $photoStudio)
    {
        return Inertia::render('Admin/PhotoStudio/Edit', [
            'photoStudio' => $photoStudio,
        ]);
    }

    /**
     * スタジオを更新
     */
    public function update(Request $request, PhotoStudio $photoStudio)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $photoStudio->update($validated);

        return redirect()->route('admin.photo-studios.index')
            ->with('success', 'スタジオを更新しました。');
    }

    /**
     * スタジオを削除
     */
    public function destroy(PhotoStudio $photoStudio)
    {
        // 前撮り枠が紐づいている場合は削除不可
        if ($photoStudio->photoSlots()->exists()) {
            return redirect()->route('admin.photo-studios.index')
                ->with('error', 'このスタジオには前撮り枠が紐づいているため削除できません。');
        }

        $photoStudio->delete();

        return redirect()->route('admin.photo-studios.index')
            ->with('success', 'スタジオを削除しました。');
    }
}

