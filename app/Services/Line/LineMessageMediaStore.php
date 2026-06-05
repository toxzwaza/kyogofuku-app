<?php

namespace App\Services\Line;

use App\Models\MediaFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LineMessageMediaStore
{
    public function __construct(private LineMessagingService $messaging) {}

    /**
     * Webhook で受信した画像メッセージを LINE API からDLし、S3 public に保存して MediaFile を作成する
     *
     * @return MediaFile
     * @throws \RuntimeException
     */
    public function storeInboundImage(string $lineMessageId, string $lineUserId): MediaFile
    {
        Log::channel('line_image')->info('[store] inbound image store start', [
            'line_message_id' => $lineMessageId,
            'line_user_id' => $lineUserId,
        ]);

        // 1. LINE API から画像バイナリ取得（同期DL・10分タイムアウト前提）
        $fetched = $this->messaging->fetchInboundImage($lineMessageId);

        // 2. 保存先パス（line_inbound/YYYYMMDD/{uuid}.{ext}）
        $path = sprintf(
            'line_inbound/%s/%s.%s',
            now()->format('Ymd'),
            (string) Str::uuid(),
            $fetched['ext']
        );

        // 3. S3 public へ保存
        //    バケットが「Object Ownership = Bucket owner enforced」になっており ACL を受け付けないため、
        //    既存 MediaFileController / EventImageController と同じく options なしで put する。
        //    バケット自体がパブリック公開設定済みなので追加の ACL 指定は不要。
        try {
            $stored = Storage::disk('s3_public')->put($path, $fetched['contents']);
        } catch (\Throwable $e) {
            Log::channel('line_image')->error('[store] S3 upload exception', [
                'line_message_id' => $lineMessageId,
                'path' => $path,
                'exception' => $e::class.': '.$e->getMessage(),
            ]);
            throw new \RuntimeException('画像の S3 アップロードに失敗しました: '.$e->getMessage(), 0, $e);
        }

        if (! $stored) {
            Log::channel('line_image')->error('[store] S3 upload returned false', [
                'line_message_id' => $lineMessageId,
                'path' => $path,
            ]);
            throw new \RuntimeException('画像の S3 アップロードに失敗しました（Storage::put が false）');
        }

        Log::channel('line_image')->info('[store] S3 upload success', [
            'line_message_id' => $lineMessageId,
            'path' => $path,
            'size' => $fetched['size'],
        ]);

        // 4. MediaFile レコード作成
        $mediaFile = MediaFile::create([
            'original_filename' => 'line_'.$lineMessageId.'.'.$fetched['ext'],
            'path' => $path,
            'storage_disk' => 's3',
            'mime_type' => $fetched['mime'],
            'file_size' => $fetched['size'],
            'tags' => ['line_inbound'],
        ]);

        Log::channel('line_image')->info('[store] media_file created', [
            'media_file_id' => $mediaFile->id,
            'line_message_id' => $lineMessageId,
        ]);

        return $mediaFile;
    }

    /**
     * 管理画面からのアップロード画像を S3 public に保存して MediaFile を作成
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return MediaFile
     */
    public function storeOutboundImage(\Illuminate\Http\UploadedFile $file): MediaFile
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $mime = $file->getMimeType() ?: 'image/jpeg';
        $size = $file->getSize() ?: 0;
        $path = sprintf(
            'line_outbound/%s/%s.%s',
            now()->format('Ymd'),
            (string) Str::uuid(),
            $ext
        );

        Log::channel('line_image')->info('[store] outbound image store start', [
            'original_filename' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size' => $size,
            'path' => $path,
        ]);

        // バケットが ACL を受け付けないため、既存 MediaFileController と同じく options なしで put
        try {
            $stream = fopen($file->getRealPath(), 'rb');
            $stored = Storage::disk('s3_public')->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Throwable $e) {
            Log::channel('line_image')->error('[store] outbound S3 upload exception', [
                'path' => $path,
                'exception' => $e::class.': '.$e->getMessage(),
            ]);
            throw new \RuntimeException('画像の S3 アップロードに失敗しました: '.$e->getMessage(), 0, $e);
        }

        if (! $stored) {
            throw new \RuntimeException('画像の S3 アップロードに失敗しました（Storage::put が false）');
        }

        $mediaFile = MediaFile::create([
            'original_filename' => $file->getClientOriginalName() ?: 'upload.'.$ext,
            'path' => $path,
            'storage_disk' => 's3',
            'mime_type' => $mime,
            'file_size' => $size,
            'tags' => ['line_outbound'],
        ]);

        Log::channel('line_image')->info('[store] outbound media_file created', [
            'media_file_id' => $mediaFile->id,
            'path' => $path,
        ]);

        return $mediaFile;
    }
}
