<?php

namespace App\Support;

/**
 * s3_public に「ランダムな名前で」保存する画像の put オプション。
 * 名前が毎回変わり上書きされないので、ブラウザ・CDN に長くキャッシュさせてよい（A17）。
 */
class S3PublicUpload
{
    /**
     * @return array<string, string>
     */
    public static function immutableOptions(): array
    {
        $cacheControl = (string) config('filesystems.disks.s3_public.immutable_cache_control', '');

        return $cacheControl === '' ? [] : ['CacheControl' => $cacheControl];
    }
}
