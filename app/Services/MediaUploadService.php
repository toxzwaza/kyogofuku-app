<?php

namespace App\Services;

use App\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * メディアアップロード共通処理
 *
 * 管理画面（Admin/MediaFileController）と端末アップロードAPI（Api/MediaUploadController）の
 * 両方から使う。WebP 変換 → S3 保存 → media_files レコード作成までを担う。
 */
class MediaUploadService
{
    /**
     * 利用可能な画像ドライバーで ImageManager を生成（GD 優先、なければ Imagick）
     */
    public function createImageManager(): ?ImageManager
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

    /**
     * アップロードファイルを WebP 変換して S3 に保存し、MediaFile を作成する
     *
     * @param int|null $deviceRegistrationId 送信元端末（iPad アプリ等）。管理画面からは null
     * @param string|null $sha256 変換前ファイルの SHA-256（端末アップロードの重複排除用）
     */
    public function processAndStore(
        UploadedFile $uploadedFile,
        ImageManager $manager,
        ?array $tagIds = null,
        ?int $deviceRegistrationId = null,
        ?string $sha256 = null,
    ): ?MediaFile {
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

            $mediaFile = MediaFile::create([
                'original_filename' => $originalFilename,
                'path' => $webpPath,
                'storage_disk' => 's3',
                'mime_type' => 'image/webp',
                'file_size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'alt' => pathinfo($originalFilename, PATHINFO_FILENAME),
                'device_registration_id' => $deviceRegistrationId,
                'sha256' => $sha256,
            ]);

            if (!empty($tagIds)) {
                $mediaFile->mediaTags()->sync($tagIds);
            }

            return $mediaFile;
        } catch (\Exception $e) {
            \Log::error('メディアアップロードエラー: ' . $e->getMessage());
            return null;
        }
    }
}
