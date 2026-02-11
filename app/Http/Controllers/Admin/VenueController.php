<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class VenueController extends Controller
{
    /**
     * 会場一覧を表示
     */
    public function index()
    {
        $venues = Venue::orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('Admin/Venue/Index', [
            'venues' => $venues,
        ]);
    }

    /**
     * 会場追加フォームを表示
     */
    public function create()
    {
        return Inertia::render('Admin/Venue/Create');
    }

    /**
     * 会場詳細を表示
     */
    public function show(Venue $venue)
    {
        $venue->load('events');

        return Inertia::render('Admin/Venue/Show', [
            'venue' => $venue,
        ]);
    }

    /**
     * 会場編集フォームを表示
     */
    public function edit(Venue $venue)
    {
        return Inertia::render('Admin/Venue/Edit', [
            'venue' => $venue,
        ]);
    }

    /**
     * 会場を保存（新規作成）。画像は WebP で S3 public/venues/ に保存。
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active', true);
        unset($validated['image']);

        $venue = Venue::create($validated);

        if ($request->hasFile('image')) {
            $manager = $this->createImageManager();
            if ($manager) {
                $webpPath = $this->convertUploadToWebpAndPutS3($request->file('image'), $venue->id, $manager);
                if ($webpPath) {
                    $venue->update(['image' => $webpPath, 'storage_disk' => 's3']);
                }
            }
        }

        return redirect()->route('admin.venues.index')
            ->with('success', '会場を追加しました。');
    }

    /**
     * 会場を保存（イベント単位）
     */
    public function storeForEvent(Request $request, Event $event)
    {
        // 既存会場を選択した場合
        if ($request->has('venue_id') && $request->venue_id) {
            $venue = Venue::findOrFail($request->venue_id);
            
            // 既に関連付けられていない場合のみ追加
            if (!$event->venues()->where('venue_id', $venue->id)->exists()) {
                $event->venues()->attach($venue->id);
            }
            
            return redirect()->back()
                ->with('success', '会場を追加しました。');
        }
        
        // 新規会場を作成する場合
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active', true);
        unset($validated['image']);

        $venue = Venue::create($validated);
        if ($request->hasFile('image')) {
            $manager = $this->createImageManager();
            if ($manager) {
                $webpPath = $this->convertUploadToWebpAndPutS3($request->file('image'), $venue->id, $manager);
                if ($webpPath) {
                    $venue->update(['image' => $webpPath, 'storage_disk' => 's3']);
                }
            }
        }
        
        // イベントと関連付け
        $event->venues()->attach($venue->id);

        return redirect()->back()
            ->with('success', '会場を追加しました。');
    }

    /**
     * 会場を更新。画像は WebP で S3 public/venues/ に保存。ファイル未選択の場合は既存画像を維持。
     */
    public function update(Request $request, Venue $venue)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active', $venue->is_active);

        if ($request->hasFile('image')) {
            $disk = ($venue->storage_disk ?? 'public') === 's3' ? 's3_public' : 'public';
            $path = $disk === 's3_public' ? str_replace('\\', '/', $venue->image) : $venue->image;
            if ($venue->image && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
            $manager = $this->createImageManager();
            if ($manager) {
                $webpPath = $this->convertUploadToWebpAndPutS3($request->file('image'), $venue->id, $manager);
                if ($webpPath) {
                    $validated['image'] = $webpPath;
                    $validated['storage_disk'] = 's3';
                }
            }
        } elseif ($request->boolean('remove_image')) {
            $disk = ($venue->storage_disk ?? 'public') === 's3' ? 's3_public' : 'public';
            $path = $disk === 's3_public' ? str_replace('\\', '/', $venue->image) : $venue->image;
            if ($venue->image && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
            $validated['image'] = null;
            $validated['storage_disk'] = 'public';
        } else {
            // ファイル未選択かつ remove_image でない場合は image / storage_disk を更新しない（既存を維持）
            unset($validated['image'], $validated['storage_disk']);
        }

        $venue->update($validated);

        // イベント単位の更新の場合は戻る、独立した更新の場合は一覧に戻る
        if ($request->has('_redirect_to_index')) {
            return redirect()->route('admin.venues.index')
                ->with('success', '会場を更新しました。');
        }

        return redirect()->back()
            ->with('success', '会場を更新しました。');
    }

    /**
     * 会場を削除（イベント単位）
     */
    public function destroyFromEvent(Event $event, Venue $venue)
    {
        $event->venues()->detach($venue->id);
        
        // 他のイベントで使用されていない場合は削除
        if ($venue->events()->count() === 0) {
            $venue->delete();
        }

        return redirect()->back()
            ->with('success', '会場を削除しました。');
    }

    /**
     * 会場を削除
     */
    public function destroy(Venue $venue)
    {
        if ($venue->events()->count() > 0) {
            return redirect()->back()
                ->withErrors(['error' => '関連するイベントが存在するため削除できません。']);
        }
        if ($venue->image) {
            $disk = ($venue->storage_disk ?? 'public') === 's3' ? 's3_public' : 'public';
            $path = $disk === 's3_public' ? str_replace('\\', '/', $venue->image) : $venue->image;
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
        $venue->delete();

        return redirect()->route('admin.venues.index')
            ->with('success', '会場を削除しました。');
    }

    /**
     * 会場画像を S3 に移行（public → s3_public、WebP に変換）
     */
    public function migrateVenueImageToS3(Venue $venue)
    {
        if (! $venue->image) {
            return redirect()->route('admin.venues.edit', $venue)
                ->with('error', '画像が設定されていません。');
        }
        if (($venue->storage_disk ?? 'public') === 's3') {
            return redirect()->route('admin.venues.edit', $venue)
                ->with('info', 'この画像は既に S3 に保存されています。');
        }
        if (! Storage::disk('public')->exists($venue->image)) {
            return redirect()->route('admin.venues.edit', $venue)
                ->with('error', '元のファイルが見つかりません。');
        }
        $manager = $this->createImageManager();
        if (! $manager) {
            return redirect()->route('admin.venues.edit', $venue)
                ->with('error', 'WebP変換に必要な画像ドライバーが利用できません。');
        }
        try {
            $oldPath = $venue->image;
            $content = Storage::disk('public')->get($oldPath);
            $webpPath = 'venues/' . $venue->id . '/' . Str::random(40) . '.webp';
            $tmpPath = tempnam(sys_get_temp_dir(), 'img');
            file_put_contents($tmpPath, $content);
            $img = $manager->read($tmpPath);
            $webpTmp = tempnam(sys_get_temp_dir(), 'webp');
            $img->toWebp(80)->save($webpTmp);
            $webpContent = file_get_contents($webpTmp);
            @unlink($tmpPath);
            @unlink($webpTmp);
            Storage::disk('s3_public')->put($webpPath, $webpContent);
            $venue->update(['storage_disk' => 's3', 'image' => $webpPath]);
            Storage::disk('public')->delete($oldPath);
        } catch (\Exception $e) {
            Log::error('会場画像 S3 移行エラー: ' . $e->getMessage());
            return redirect()->route('admin.venues.edit', $venue)
                ->with('error', 'S3 への移行に失敗しました。');
        }
        return redirect()->route('admin.venues.edit', $venue)
            ->with('success', '画像を S3 に移行しました。');
    }

    private function createImageManager()
    {
        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            try {
                return new ImageManager(new GdDriver());
            } catch (\Exception $e) {
                Log::warning('GDドライバーの初期化に失敗: ' . $e->getMessage());
            }
        }
        if (extension_loaded('imagick')) {
            try {
                return new ImageManager(new ImagickDriver());
            } catch (\Exception $e) {
                Log::warning('Imagickドライバーの初期化に失敗: ' . $e->getMessage());
            }
        }
        return null;
    }

    /**
     * アップロードを WebP に変換して S3（s3_public）に保存。path: venues/{id}/{unique}.webp
     */
    private function convertUploadToWebpAndPutS3($uploadedFile, int $venueId, $manager)
    {
        if (! $manager) {
            return null;
        }
        try {
            $webpPath = 'venues/' . $venueId . '/' . Str::random(40) . '.webp';
            $image = $manager->read($uploadedFile->getRealPath());
            $tmpPath = tempnam(sys_get_temp_dir(), 'webp');
            $image->toWebp(80)->save($tmpPath);
            $content = file_get_contents($tmpPath);
            @unlink($tmpPath);
            Storage::disk('s3_public')->put($webpPath, $content);
            return $webpPath;
        } catch (\Exception $e) {
            Log::error('WebP変換エラー (S3 venues/' . $venueId . '): ' . $e->getMessage());
            return null;
        }
    }
}

