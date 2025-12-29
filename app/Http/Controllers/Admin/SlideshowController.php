<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slideshow;
use App\Models\SlideshowImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SlideshowController extends Controller
{
    /**
     * スライドショー一覧を表示
     */
    public function index()
    {
        $slideshows = Slideshow::with('images')->orderBy('created_at', 'desc')->get();

        return Inertia::render('Admin/Slideshow/Index', [
            'slideshows' => $slideshows,
        ]);
    }

    /**
     * スライドショー作成フォームを表示
     */
    public function create()
    {
        return Inertia::render('Admin/Slideshow/Create');
    }

    /**
     * スライドショー詳細を表示
     */
    public function show(Slideshow $slideshow)
    {
        $slideshow->load('images');

        return Inertia::render('Admin/Slideshow/Show', [
            'slideshow' => $slideshow,
        ]);
    }

    /**
     * スライドショーを保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $slideshow = Slideshow::create($validated);

        return redirect()->route('admin.slideshows.show', $slideshow->id)
            ->with('success', 'スライドショーを作成しました。');
    }

    /**
     * スライドショーを更新
     */
    public function update(Request $request, Slideshow $slideshow)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $slideshow->update($validated);

        return redirect()->back()
            ->with('success', 'スライドショーを更新しました。');
    }

    /**
     * スライドショーを削除
     */
    public function destroy(Slideshow $slideshow)
    {
        // 画像ファイルを削除
        foreach ($slideshow->images as $image) {
            if (Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }
        }

        $slideshow->delete();

        return redirect()->route('admin.slideshows.index')
            ->with('success', 'スライドショーを削除しました。');
    }

    /**
     * スライドショー画像を追加
     */
    public function storeImage(Request $request, Slideshow $slideshow)
    {
        $validated = $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:10240',
            'alt' => 'nullable|string|max:255',
        ]);

        $maxSortOrder = $slideshow->images()->max('sort_order') ?? 0;

        foreach ($request->file('images') as $index => $file) {
            $path = $file->store('slideshows/' . $slideshow->id, 'public');
            
            SlideshowImage::create([
                'slideshow_id' => $slideshow->id,
                'path' => $path,
                'alt' => $request->alt ?? null,
                'sort_order' => $maxSortOrder + $index + 1,
            ]);
        }

        return redirect()->route('admin.slideshows.show', $slideshow->id)
            ->with('success', '画像を追加しました。');
    }

    /**
     * スライドショー画像を削除
     */
    public function destroyImage(SlideshowImage $image)
    {
        $slideshowId = $image->slideshow_id;
        
        // ストレージからファイルを削除
        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        $image->delete();

        return redirect()->route('admin.slideshows.show', $slideshowId)
            ->with('success', '画像を削除しました。');
    }

    /**
     * スライドショー画像のソート順を更新
     */
    public function updateImageSortOrder(Request $request, Slideshow $slideshow)
    {
        $validated = $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'exists:slideshow_images,id',
        ]);

        foreach ($validated['image_ids'] as $index => $imageId) {
            SlideshowImage::where('id', $imageId)
                ->where('slideshow_id', $slideshow->id)
                ->update(['sort_order' => $index + 1]);
        }

        return redirect()->route('admin.slideshows.show', $slideshow->id)
            ->with('success', 'ソート順を更新しました。');
    }
}
