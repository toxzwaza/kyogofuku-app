<?php

namespace App\Http\Controllers;

use App\Models\S3TestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class S3TestController extends Controller
{
    /**
     * S3 テスト画面を表示
     */
    public function index()
    {
        $items = S3TestItem::orderBy('created_at', 'desc')->get()->map(function (S3TestItem $item) {
            $data = [
                'id' => $item->id,
                'path' => $item->path,
                'visibility_type' => $item->visibility_type,
                'original_name' => $item->original_name,
                'created_at' => $item->created_at->toIso8601String(),
            ];
            if ($item->visibility_type === 'public') {
                $url = Storage::disk('s3_public')->url($item->path);
                $url = str_replace(['%5C', '\\'], ['/', '/'], $url);
                $data['url'] = $url;
                $data['thumbnail_url'] = $url;
            } else {
                $data['url'] = null;
                // 旧データは path が 'private/...' のため、root 配下のパスに変換
                $pathForUrl = str_starts_with($item->path, 'private/') ? substr($item->path, 8) : $item->path;
                $data['thumbnail_url'] = Storage::disk('s3_private')->temporaryUrl($pathForUrl, now()->addMinutes(5));
            }
            return $data;
        })->values();

        return Inertia::render('S3Test', [
            'items' => $items,
        ]);
    }

    /**
     * ファイルを S3 にアップロード
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240',
            'visibility_type' => 'required|in:private,public',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION);
        $datePrefix = now()->format('Y-m-d');
        $uniqueName = Str::random(8) . ($extension ? '.' . $extension : '');

        if ($validated['visibility_type'] === 'private') {
            $path = $datePrefix . '/' . $uniqueName;
            $file->storeAs($datePrefix, $uniqueName, 's3_private');
        } else {
            $path = $datePrefix . '/' . $uniqueName;
            $file->storeAs($datePrefix, $uniqueName, 's3_public');
        }

        S3TestItem::create([
            'path' => $path,
            'visibility_type' => $validated['visibility_type'],
            'original_name' => $originalName,
        ]);

        return redirect()->route('s3-test.index')->with('success', 'アップロードしました。');
    }

    /**
     * 署名付き URL を返す（s3_private / s3_public）
     */
    public function signedUrl(Request $request)
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'disk' => 'nullable|string|in:s3_private,s3_public',
        ]);

        $path = $validated['path'];
        $disk = $validated['disk'] ?? 's3_private';
        $url = Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(60));

        return response()->json(['url' => $url]);
    }
}
