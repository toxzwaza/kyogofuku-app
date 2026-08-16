<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceRegistration;
use App\Models\MediaFile;
use App\Models\MediaTag;
use App\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 登録端末（iPad 写真アップロードアプリ）からのメディアアップロードAPI
 *
 * 認証は DeviceRegistration の端末トークン（Authorization: Bearer <token>）。
 * アップロードされた画像は管理画面と同じ経路（WebP 変換 → S3）でメディアライブラリに登録され、
 * 送信元端末が media_files.device_registration_id に記録される。
 */
class MediaUploadController extends Controller
{
    public function __construct(private MediaUploadService $mediaUploadService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $device = DeviceRegistration::findActiveByToken($request->bearerToken());
        if (! $device) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => '端末トークンが無効です。アプリの設定から端末登録をやり直してください。',
            ], 401, [], JSON_UNESCAPED_UNICODE);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'sha256' => 'nullable|string|regex:/^[a-f0-9]{64}$/',
        ]);

        $device->forceFill([
            'last_used_at' => now(),
            'last_ip' => $request->ip(),
        ])->save();

        $file = $request->file('image');

        // 変換前ファイルのハッシュはサーバー側で必ず計算する。
        // クライアント申告値がある場合は転送破損の検知として照合する。
        $sha256 = hash_file('sha256', $file->getRealPath());
        if ($request->filled('sha256') && ! hash_equals($sha256, (string) $request->input('sha256'))) {
            return response()->json([
                'error' => 'checksum_mismatch',
                'message' => 'ファイルのチェックサムが一致しません。再送してください。',
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $deviceTagId = $this->resolveDeviceTagId($device);

        // 冪等性：同一ファイルの再送（リトライ・二重送信）は既存レコードを返す
        $existing = MediaFile::where('sha256', $sha256)->first();
        if ($existing) {
            // 初回登録時にタグ付与まで到達できていなかった場合に備えて再付与（既存タグは維持）
            $existing->mediaTags()->syncWithoutDetaching([$deviceTagId]);

            return response()->json([
                'duplicated' => true,
                'media' => $this->formatMedia($existing),
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $manager = $this->mediaUploadService->createImageManager();
        if (! $manager) {
            return response()->json([
                'error' => 'server_error',
                'message' => '画像ドライバーが利用できません。',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }

        $mediaFile = $this->mediaUploadService->processAndStore(
            $file,
            $manager,
            [$deviceTagId],
            $device->id,
            $sha256,
        );

        if (! $mediaFile) {
            return response()->json([
                'error' => 'upload_failed',
                'message' => '画像の保存に失敗しました。',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'duplicated' => false,
            'media' => $this->formatMedia($mediaFile),
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 「タブレット画像 > 端末ID」の階層タグを取得（なければ自動作成）し、端末タグのIDを返す
     *
     * 端末タグ名は label（台帳の端末ID。例: KI-IPAD-01）を優先し、未設定なら device_code を使う。
     * 親タグ「タブレット画像」で絞り込むと全端末分が表示される（既存のタグ絞り込みが子孫を含むため）。
     */
    private function resolveDeviceTagId(DeviceRegistration $device): int
    {
        $parent = MediaTag::firstOrCreate(['parent_id' => null, 'name' => 'タブレット画像']);

        $child = MediaTag::firstOrCreate([
            'parent_id' => $parent->id,
            'name' => $device->label ?: $device->device_code,
        ]);

        return $child->id;
    }

    private function formatMedia(MediaFile $media): array
    {
        return [
            'id' => $media->id,
            'original_filename' => $media->original_filename,
            'url' => $media->url,
            'width' => $media->width,
            'height' => $media->height,
            'file_size' => $media->file_size,
            'sha256' => $media->sha256,
            'created_at' => $media->created_at?->toIso8601String(),
        ];
    }
}
