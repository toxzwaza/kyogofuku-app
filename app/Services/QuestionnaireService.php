<?php

namespace App\Services;

use App\Models\CustomerPhoto;
use App\Models\PhotoType;
use App\Support\PhotoOwner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 振袖アンケート（スキャン取込・写真配置合成・削除・印刷データ）の共通処理。
 *
 * 顧客詳細（閲覧）と予約詳細（登録・編集）の両方から PhotoOwner 経由で使う。
 * 1ページ目・2ページ目とも写真配置・合成に対応する
 * （カラム名は歴史的経緯で 2ページ目=placements/composed_page2_path、
 *   1ページ目=page1_placements/composed_page1_path）。
 */
class QuestionnaireService
{
    public function __construct(
        private CustomerPhotoStorageService $photoStorage,
    ) {
    }

    /** ページごとの配置カラム名 */
    public static function placementsColumn(int $page): string
    {
        return $page === 1 ? 'page1_placements' : 'placements';
    }

    /** ページごとの合成画像パスカラム名 */
    public static function composedColumn(int $page): string
    {
        return $page === 1 ? 'composed_page1_path' : 'composed_page2_path';
    }

    /**
     * スキャン画像を登録（ページ単位・差し替え可）
     *
     * @throws \RuntimeException 保存失敗時（メッセージをそのままユーザーに表示できる）
     */
    public function storeScan(PhotoOwner $owner, UploadedFile $file, int $page): void
    {
        $manager = $this->photoStorage->createImageManager();
        if (! $manager) {
            throw new \RuntimeException('WebP変換に必要な画像ドライバー（GD/Imagick）が利用できません。');
        }

        $storedPath = $this->photoStorage->convertUploadToWebpAndPutS3Private(
            $file, $owner->id(), $manager, $owner->dir()
        );
        if (! $storedPath) {
            throw new \RuntimeException('スキャン画像の保存に失敗しました。');
        }

        $photoTypeId = PhotoType::where('code', 'questionnaire')->value('id');
        $column = $page === 1 ? 'page1_photo_id' : 'page2_photo_id';

        DB::transaction(function () use ($owner, $photoTypeId, $storedPath, $page, $column) {
            $photo = CustomerPhoto::create($owner->attrs() + [
                'photo_type_id' => $photoTypeId,
                'file_path' => $storedPath,
                'storage_disk' => 's3',
                'remarks' => "振袖アンケート {$page}ページ目",
            ]);

            $questionnaire = $owner->firstOrCreateQuestionnaire();

            // 差し替え時は旧スキャンを削除
            $oldPhotoId = $questionnaire->{$column};

            // スキャンを撮り直したら該当ページの配置・合成はリセット
            $updates = [$column => $photo->id];
            $composedColumn = self::composedColumn($page);
            if ($questionnaire->{$composedColumn}) {
                $this->photoStorage->deletePhotoFile($questionnaire->{$composedColumn}, 's3');
            }
            $updates[self::placementsColumn($page)] = null;
            $updates[$composedColumn] = null;

            $questionnaire->update($updates);

            if ($oldPhotoId) {
                $oldPhoto = CustomerPhoto::find($oldPhotoId);
                if ($oldPhoto) {
                    $this->photoStorage->deletePhotoFile($oldPhoto->file_path, $oldPhoto->storage_disk);
                    $oldPhoto->delete();
                }
            }
        });
    }

    /**
     * 写真添付欄の配置情報と合成画像を保存
     *
     * @param  array  $placements  正規化前の配置データ
     * @return string 合成画像の署名付きURL
     *
     * @throws \RuntimeException 検証・保存失敗時（getCode() がHTTPステータス相当）
     */
    public function updatePlacements(PhotoOwner $owner, array $placements, UploadedFile $composedImage, int $page): string
    {
        $questionnaire = $owner->questionnaire();
        $scanColumn = $page === 1 ? 'page1_photo_id' : 'page2_photo_id';
        if (! $questionnaire || ! $questionnaire->{$scanColumn}) {
            throw new \RuntimeException("先に{$page}ページ目のスキャンを取り込んでください。", 422);
        }

        foreach ($placements as $item) {
            $type = $item['type'] ?? 'photo';
            if ($type === 'photo' && empty($item['customer_photo_id'])) {
                throw new \RuntimeException('配置データが不正です。', 422);
            }
            if ($type === 'text' && trim($item['text'] ?? '') === '') {
                throw new \RuntimeException('テキストが空の配置が含まれています。', 422);
            }
        }

        // 配置写真がすべて持ち主（顧客 or 予約）のものであることを確認
        $photoIds = collect($placements)
            ->filter(fn ($p) => ($p['type'] ?? 'photo') === 'photo')
            ->pluck('customer_photo_id')->unique()->values();
        if ($photoIds->isNotEmpty()) {
            $ownedCount = $owner->photosQuery()->whereIn('id', $photoIds)->count();
            if ($ownedCount !== $photoIds->count()) {
                throw new \RuntimeException('配置できない写真が含まれています。', 422);
            }
        }

        $storedPath = $this->photoStorage->putContentToS3Private(
            file_get_contents($composedImage->getRealPath()),
            $owner->id(),
            'jpg',
            $owner->dir()
        );
        if (! $storedPath) {
            throw new \RuntimeException('合成画像の保存に失敗しました。', 500);
        }

        // FormData経由の値はすべて文字列になるため、数値項目を正規化して保存する
        // （文字列のままだとフロント復元時のID照合が厳密比較で失敗する）
        $normalized = collect($placements)->map(function ($p) {
            foreach (['left', 'top', 'angle', 'scale', 'scale_x', 'scale_y', 'font_size', 'width'] as $key) {
                if (isset($p[$key])) {
                    $p[$key] = (float) $p[$key];
                }
            }
            if (isset($p['customer_photo_id'])) {
                $p['customer_photo_id'] = (int) $p['customer_photo_id'];
            }

            return $p;
        })->values()->all();

        $composedColumn = self::composedColumn($page);
        $oldComposedPath = $questionnaire->{$composedColumn};
        $questionnaire->update([
            self::placementsColumn($page) => $normalized,
            $composedColumn => $storedPath,
        ]);
        if ($oldComposedPath) {
            $this->photoStorage->deletePhotoFile($oldComposedPath, 's3');
        }

        return $this->s3Url($storedPath);
    }

    /**
     * スキャン画像を削除（ページ単位。配置・合成もリセット）
     *
     * @throws \RuntimeException 対象なし
     */
    public function destroyScan(PhotoOwner $owner, int $page): void
    {
        $questionnaire = $owner->questionnaire();
        $column = $page === 1 ? 'page1_photo_id' : 'page2_photo_id';
        if (! $questionnaire || ! $questionnaire->{$column}) {
            throw new \RuntimeException('削除対象のスキャンがありません。');
        }

        DB::transaction(function () use ($questionnaire, $column, $page) {
            $photoId = $questionnaire->{$column};

            $updates = [$column => null];
            $composedColumn = self::composedColumn($page);
            if ($questionnaire->{$composedColumn}) {
                $this->photoStorage->deletePhotoFile($questionnaire->{$composedColumn}, 's3');
            }
            $updates[self::placementsColumn($page)] = null;
            $updates[$composedColumn] = null;

            $questionnaire->update($updates);

            $photo = CustomerPhoto::find($photoId);
            if ($photo) {
                $this->photoStorage->deletePhotoFile($photo->file_path, $photo->storage_disk);
                $photo->delete();
            }
        });
    }

    /**
     * フロントに渡すアンケートデータ（署名付きURL込み）
     */
    public function questionnairePayload(PhotoOwner $owner): ?array
    {
        $questionnaire = $owner->questionnaire();
        if (! $questionnaire) {
            return null;
        }

        return [
            'page1_photo_id' => $questionnaire->page1_photo_id,
            'page2_photo_id' => $questionnaire->page2_photo_id,
            'page1_url' => $this->s3Url($questionnaire->page1Photo?->file_path),
            'page2_url' => $this->s3Url($questionnaire->page2Photo?->file_path),
            'placements' => $questionnaire->placements,
            'page1_placements' => $questionnaire->page1_placements,
            'composed_page1_url' => $this->s3Url($questionnaire->composed_page1_path),
            'composed_page2_url' => $this->s3Url($questionnaire->composed_page2_path),
        ];
    }

    /**
     * 印刷ページ（admin.questionnaire.print ビュー）に渡すデータ
     */
    public function printData(PhotoOwner $owner, string $mode, bool $blank): array
    {
        $questionnaire = $owner->questionnaire();

        return [
            'customer' => $owner->printCustomer(),
            'mode' => $mode === 'scan' ? 'scan' : 'form',
            'blank' => $blank,
            'page1ScanUrl' => $this->s3Url($questionnaire?->page1Photo?->file_path),
            'page2ScanUrl' => $this->s3Url($questionnaire?->page2Photo?->file_path),
            'composedPage1Url' => $this->s3Url($questionnaire?->composed_page1_path),
            'composedPage2Url' => $this->s3Url($questionnaire?->composed_page2_path),
        ];
    }

    private function s3Url(?string $path): ?string
    {
        return $path
            ? Storage::disk('s3_private')->temporaryUrl(str_replace('\\', '/', $path), now()->addMinutes(60))
            : null;
    }
}
