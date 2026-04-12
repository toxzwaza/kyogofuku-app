<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventImage;
use App\Models\MediaFile;
use App\Models\SlideshowImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class MediaFileController extends Controller
{
    /**
     * メディアライブラリ一覧
     */
    public function index(Request $request)
    {
        $query = MediaFile::query()->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where('original_filename', 'like', "%{$search}%");
        }

        if ($tag = $request->input('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $mediaFiles = $query->paginate(24)->through(function ($media) {
            return [
                'id' => $media->id,
                'original_filename' => $media->original_filename,
                'path' => $media->path,
                'url' => $media->url,
                'storage_disk' => $media->storage_disk,
                'mime_type' => $media->mime_type,
                'file_size' => $media->file_size,
                'width' => $media->width,
                'height' => $media->height,
                'alt' => $media->alt,
                'tags' => $media->tags ?? [],
                'usage_count' => $media->usage_count,
                'created_at' => $media->created_at->format('Y-m-d H:i'),
            ];
        });

        // タグ一覧（サイドバーフィルタ用）
        $allTags = MediaFile::whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        return Inertia::render('Admin/Media/Index', [
            'mediaFiles' => $mediaFiles,
            'allTags' => $allTags,
            'filters' => [
                'search' => $request->input('search', ''),
                'tag' => $request->input('tag', ''),
            ],
        ]);
    }

    /**
     * メディアファイルをアップロード
     */
    public function store(Request $request)
    {
        $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $manager = $this->createImageManager();
        if (!$manager) {
            return redirect()->route('admin.media.index')
                ->with('error', 'WebP変換に必要な画像ドライバー（GD/Imagick）が利用できません。');
        }

        $uploaded = 0;
        foreach ($request->file('images') as $file) {
            $mediaFile = $this->processAndStoreUpload($file, $manager, $request->input('tags'));
            if ($mediaFile) {
                $uploaded++;
            }
        }

        if ($uploaded === 0) {
            return redirect()->route('admin.media.index')
                ->with('error', '画像のアップロードに失敗しました。');
        }

        return redirect()->route('admin.media.index')
            ->with('success', "{$uploaded}件の画像をアップロードしました。");
    }

    /**
     * JSON形式でメディアファイルをアップロード（MediaPickerからのAJAX用）
     */
    public function storeJson(Request $request)
    {
        $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $manager = $this->createImageManager();
        if (!$manager) {
            return response()->json(['error' => '画像ドライバーが利用できません。'], 500);
        }

        $results = [];
        foreach ($request->file('images') as $file) {
            $mediaFile = $this->processAndStoreUpload($file, $manager, $request->input('tags'));
            if ($mediaFile) {
                $results[] = [
                    'id' => $mediaFile->id,
                    'original_filename' => $mediaFile->original_filename,
                    'url' => $mediaFile->url,
                    'path' => $mediaFile->path,
                    'storage_disk' => $mediaFile->storage_disk,
                    'alt' => $mediaFile->alt,
                    'tags' => $mediaFile->tags ?? [],
                    'width' => $mediaFile->width,
                    'height' => $mediaFile->height,
                    'file_size' => $mediaFile->file_size,
                    'usage_count' => 0,
                    'created_at' => $mediaFile->created_at->format('Y-m-d H:i'),
                ];
            }
        }

        return response()->json(['media' => $results]);
    }

    /**
     * メディアファイル一覧をJSON形式で取得（MediaPickerからのAJAX用）
     */
    public function listJson(Request $request)
    {
        $query = MediaFile::query()->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where('original_filename', 'like', "%{$search}%");
        }

        if ($tag = $request->input('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $mediaFiles = $query->paginate(24)->through(function ($media) {
            return [
                'id' => $media->id,
                'original_filename' => $media->original_filename,
                'url' => $media->url,
                'path' => $media->path,
                'storage_disk' => $media->storage_disk,
                'alt' => $media->alt,
                'tags' => $media->tags ?? [],
                'width' => $media->width,
                'height' => $media->height,
                'file_size' => $media->file_size,
                'usage_count' => $media->usage_count,
                'created_at' => $media->created_at->format('Y-m-d H:i'),
            ];
        });

        $allTags = MediaFile::whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        return response()->json([
            'mediaFiles' => $mediaFiles,
            'allTags' => $allTags,
        ]);
    }

    /**
     * メディアファイルの情報を更新（alt・タグ）
     */
    public function update(Request $request, MediaFile $mediaFile)
    {
        $validated = $request->validate([
            'alt' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $mediaFile->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('admin.media.index')
            ->with('success', 'メディア情報を更新しました。');
    }

    /**
     * メディアファイルを削除
     */
    public function destroy(MediaFile $mediaFile)
    {
        if ($mediaFile->usage_count > 0) {
            $msg = "このメディアは{$mediaFile->usage_count}箇所で使用されています。先に参照を解除してください。";
            return redirect()->route('admin.media.index')->with('error', $msg);
        }

        $disk = ($mediaFile->storage_disk ?? 'public') === 's3' ? 's3_public' : 'public';
        $path = $disk === 's3_public' ? str_replace('\\', '/', $mediaFile->path) : $mediaFile->path;
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        $mediaFile->delete();

        return redirect()->route('admin.media.index')
            ->with('success', 'メディアを削除しました。');
    }

    /**
     * 既存のイベント画像・スライドショー画像をメディアライブラリに一括取り込み
     */
    public function importExisting()
    {
        $imported = 0;
        $linked = 0;

        // パス → MediaFile の対応を保持（同一パスを重複作成しない）
        $pathToMediaId = MediaFile::pluck('id', 'path')->toArray();

        DB::transaction(function () use (&$imported, &$linked, &$pathToMediaId) {
            // イベント画像を取り込み
            $eventImages = EventImage::whereNull('media_file_id')->get();
            foreach ($eventImages as $ei) {
                $path = str_replace('\\', '/', $ei->path);
                if (isset($pathToMediaId[$path])) {
                    // 既にメディアライブラリに存在 → 紐付けのみ
                    $ei->update(['media_file_id' => $pathToMediaId[$path]]);
                    $linked++;
                } else {
                    // 新規MediaFileレコード作成
                    $mediaFile = $this->createMediaFileFromPath(
                        $path,
                        $ei->storage_disk ?? 'public',
                        $ei->alt
                    );
                    if ($mediaFile) {
                        $pathToMediaId[$path] = $mediaFile->id;
                        $ei->update(['media_file_id' => $mediaFile->id]);
                        $imported++;
                    }
                }
            }

            // スライドショー画像を取り込み
            $slideshowImages = SlideshowImage::whereNull('media_file_id')->get();
            foreach ($slideshowImages as $si) {
                $path = str_replace('\\', '/', $si->path);
                if (isset($pathToMediaId[$path])) {
                    $si->update(['media_file_id' => $pathToMediaId[$path]]);
                    $linked++;
                } else {
                    $mediaFile = $this->createMediaFileFromPath(
                        $path,
                        $si->storage_disk ?? 'public',
                        $si->alt
                    );
                    if ($mediaFile) {
                        $pathToMediaId[$path] = $mediaFile->id;
                        $si->update(['media_file_id' => $mediaFile->id]);
                        $imported++;
                    }
                }
            }
        });

        $total = $imported + $linked;
        if ($total === 0) {
            return redirect()->route('admin.media.index')
                ->with('info', '取り込み対象の画像はありません（すべて登録済みです）。');
        }

        return redirect()->route('admin.media.index')
            ->with('success', "既存画像を取り込みました（新規: {$imported}件、既存紐付け: {$linked}件）。");
    }

    /**
     * 既存のストレージパスからMediaFileレコードを作成（ファイルはコピーしない）
     */
    private function createMediaFileFromPath(string $path, string $storageDisk, ?string $alt): ?MediaFile
    {
        try {
            $disk = $storageDisk === 's3' ? 's3_public' : 'public';
            $normalizedPath = str_replace('\\', '/', $path);

            $fileSize = null;
            $width = null;
            $height = null;

            // S3/ローカルからファイルサイズ取得を試みる
            try {
                if (Storage::disk($disk)->exists($normalizedPath)) {
                    $fileSize = Storage::disk($disk)->size($normalizedPath);
                }
            } catch (\Exception $e) {
                // サイズ取得失敗は無視
            }

            $originalFilename = basename($path);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mimeType = match ($extension) {
                'webp' => 'image/webp',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                default => 'image/' . $extension,
            };

            return MediaFile::create([
                'original_filename' => $originalFilename,
                'path' => $normalizedPath,
                'storage_disk' => $storageDisk,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'alt' => $alt,
                'tags' => null,
            ]);
        } catch (\Exception $e) {
            \Log::error("既存画像の取り込みエラー ({$path}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * アップロードファイルをWebP変換してS3に保存し、MediaFileレコードを作成
     */
    private function processAndStoreUpload($uploadedFile, $manager, ?array $tags = null): ?MediaFile
    {
        try {
            $originalFilename = $uploadedFile->getClientOriginalName();
            $webpPath = 'media/' . Str::uuid() . '.webp';

            $image = $manager->read($uploadedFile->getRealPath());
            $width = $image->width();
            $height = $image->height();

            $tmpPath = tempnam(sys_get_temp_dir(), 'webp');
            $image->toWebp(80)->save($tmpPath);
            $content = file_get_contents($tmpPath);
            $fileSize = strlen($content);
            @unlink($tmpPath);

            Storage::disk('s3_public')->put($webpPath, $content);

            return MediaFile::create([
                'original_filename' => $originalFilename,
                'path' => $webpPath,
                'storage_disk' => 's3',
                'mime_type' => 'image/webp',
                'file_size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'alt' => pathinfo($originalFilename, PATHINFO_FILENAME),
                'tags' => $tags ?: null,
            ]);
        } catch (\Exception $e) {
            \Log::error('メディアアップロードエラー: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 利用可能なドライバーでImageManagerを作成
     */
    private function createImageManager()
    {
        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            try {
                return new ImageManager(new GdDriver());
            } catch (\Exception $e) {
                \Log::warning("GDドライバーの初期化に失敗: " . $e->getMessage());
            }
        }
        if (extension_loaded('imagick')) {
            try {
                return new ImageManager(new ImagickDriver());
            } catch (\Exception $e) {
                \Log::warning("Imagickドライバーの初期化に失敗: " . $e->getMessage());
            }
        }
        return null;
    }
}
