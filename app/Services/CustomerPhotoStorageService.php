<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * 顧客写真の S3（s3_private）保存処理
 *
 * Admin/CustomerController に private メソッドとしてあった処理を
 * アンケートスキャン機能と共用するために抽出したもの（ロジックは同一）。
 */
class CustomerPhotoStorageService
{
    /**
     * 利用可能なドライバーでImageManagerを作成
     *
     * @return ImageManager|null
     */
    public function createImageManager()
    {
        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            try {
                return new ImageManager(new GdDriver);
            } catch (\Exception $e) {
                Log::warning('GDドライバーの初期化に失敗: '.$e->getMessage());
            }
        }
        if (extension_loaded('imagick')) {
            try {
                return new ImageManager(new ImagickDriver);
            } catch (\Exception $e) {
                Log::warning('Imagickドライバーの初期化に失敗: '.$e->getMessage());
            }
        }
        Log::warning('画像処理ドライバー（GD/Imagick）が利用できません。');

        return null;
    }

    /**
     * アップロードファイルを WebP に変換して S3（s3_private）に保存
     *
     * @return string|null 保存した WebP のパス（customers/{id}/{unique}.webp）、失敗時は null
     */
    public function convertUploadToWebpAndPutS3Private($uploadedFile, int $customerId, $manager)
    {
        if (! $manager) {
            return null;
        }
        try {
            $webpPath = 'customers/'.$customerId.'/'.Str::random(40).'.webp';
            $image = $manager->read($uploadedFile->getRealPath());
            $tmpPath = tempnam(sys_get_temp_dir(), 'webp');
            $image->toWebp(80)->save($tmpPath);
            $content = file_get_contents($tmpPath);
            @unlink($tmpPath);
            Storage::disk('s3_private')->put($webpPath, $content);

            return $webpPath;
        } catch (\Exception $e) {
            Log::error('WebP変換エラー (S3 customers/'.$customerId.'): '.$e->getMessage());

            return null;
        }
    }

    /**
     * アップロードファイルを変換せずそのまま S3（s3_private）に保存
     * （PDF など画像変換に適さないファイル用）
     *
     * @return string|null 保存したパス（customers/{id}/{unique}.{ext}）、失敗時は null
     */
    public function putUploadToS3Private($uploadedFile, int $customerId, string $ext): ?string
    {
        try {
            $path = 'customers/'.$customerId.'/'.Str::random(40).'.'.$ext;
            Storage::disk('s3_private')->put($path, file_get_contents($uploadedFile->getRealPath()));

            return $path;
        } catch (\Exception $e) {
            Log::error('ファイル保存エラー (S3 customers/'.$customerId.'): '.$e->getMessage());

            return null;
        }
    }

    /**
     * バイナリコンテンツを S3（s3_private）に保存
     * （canvas 合成画像など UploadedFile を経由しないデータ用）
     *
     * @return string|null 保存したパス、失敗時は null
     */
    public function putContentToS3Private(string $content, int $customerId, string $ext): ?string
    {
        try {
            $path = 'customers/'.$customerId.'/'.Str::random(40).'.'.$ext;
            Storage::disk('s3_private')->put($path, $content);

            return $path;
        } catch (\Exception $e) {
            Log::error('ファイル保存エラー (S3 customers/'.$customerId.'): '.$e->getMessage());

            return null;
        }
    }

    /**
     * customer_photos の1レコードに対応する S3/ローカルのファイルを削除
     */
    public function deletePhotoFile(?string $filePath, ?string $storageDisk): void
    {
        if (! $filePath) {
            return;
        }
        $disk = ($storageDisk ?? 'public') === 's3' ? 's3_private' : ($storageDisk ?? 'public');
        $path = $disk === 's3_private' ? str_replace('\\', '/', $filePath) : $filePath;
        Storage::disk($disk)->delete($path);
    }
}
