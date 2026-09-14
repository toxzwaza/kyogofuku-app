<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesTabletMediaTags;
use App\Http\Controllers\Controller;
use App\Models\CustomerPhoto;
use App\Models\EventReservation;
use App\Models\MediaFile;
use App\Services\CustomerPhotoStorageService;
use App\Support\PhotoOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 予約詳細「写真・アンケート」タブの写真操作。
 *
 * 予約が顧客に紐付いている場合は顧客写真として保存し、
 * 未紐付けなら予約自身に紐づけて保存する（顧客紐付け時に自動で引き継がれる）。
 * 処理内容は CustomerController の storeCustomerPhoto / storeCustomerPhotoFromMedia /
 * destroyCustomerPhoto と同一仕様。
 */
class ReservationPhotoController extends Controller
{
    use ResolvesTabletMediaTags;

    public function __construct(
        private CustomerPhotoStorageService $photoStorage,
    ) {
    }

    /**
     * 写真を追加（画像はWebP変換・PDFはそのままS3へ）
     */
    public function store(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'photo_type_id' => 'required|exists:photo_types,id',
            'photo' => 'required|file|mimes:jpeg,png,jpg,gif,pdf|max:10240',
            'remarks' => 'nullable|string',
        ]);

        $owner = PhotoOwner::forReservation($reservation);
        $file = $request->file('photo');
        $isPdf = strtolower($file->getClientOriginalExtension()) === 'pdf'
            || $file->getClientMimeType() === 'application/pdf';

        if ($isPdf) {
            $storedPath = $this->photoStorage->putUploadToS3Private($file, $owner->id(), 'pdf', $owner->dir());
            if (! $storedPath) {
                return redirect()->route('admin.reservations.show', $reservation)
                    ->with('error', 'PDF の保存に失敗しました。');
            }
        } else {
            $manager = $this->photoStorage->createImageManager();
            if (! $manager) {
                return redirect()->route('admin.reservations.show', $reservation)
                    ->with('error', 'WebP変換に必要な画像ドライバー（GD/Imagick）が利用できません。');
            }
            $storedPath = $this->photoStorage->convertUploadToWebpAndPutS3Private($file, $owner->id(), $manager, $owner->dir());
            if (! $storedPath) {
                return redirect()->route('admin.reservations.show', $reservation)
                    ->with('error', '写真の WebP 変換に失敗しました。');
            }
        }

        CustomerPhoto::create($owner->attrs() + [
            'photo_type_id' => $validated['photo_type_id'],
            'file_path' => $storedPath,
            'storage_disk' => 's3',
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('success', '写真を追加しました。');
    }

    /**
     * メディアライブラリ（タブレット画像）から写真を登録
     */
    public function storeFromMedia(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'media_file_id' => 'required|integer|exists:media_files,id',
            'photo_type_id' => 'required|exists:photo_types,id',
            'remarks' => 'nullable|string',
        ]);

        $owner = PhotoOwner::forReservation($reservation);
        $media = MediaFile::with('mediaTags')->findOrFail($validated['media_file_id']);

        // 自店舗プレフィックスのタブレット画像タグが付いたものだけ許可（ID直指定での越権を防ぐ）
        $allowedTagIds = $this->tabletDeviceTagsForUser($request)->pluck('id')->all();
        $isAllowed = $media->mediaTags->contains(fn ($t) => in_array($t->id, $allowedTagIds, true));
        if (! $isAllowed) {
            return redirect()->route('admin.reservations.show', $reservation)
                ->with('error', 'このメディアは写真として選択できません。');
        }

        if (! str_starts_with((string) $media->mime_type, 'image/')) {
            return redirect()->route('admin.reservations.show', $reservation)
                ->with('error', '画像以外のメディアは登録できません。');
        }

        $manager = $this->photoStorage->createImageManager();
        if (! $manager) {
            return redirect()->route('admin.reservations.show', $reservation)
                ->with('error', 'WebP変換に必要な画像ドライバー（GD/Imagick）が利用できません。');
        }

        try {
            $sourceDisk = ($media->storage_disk ?? 'public') === 's3' ? 's3_public' : 'public';
            $contents = Storage::disk($sourceDisk)->get(str_replace('\\', '/', $media->path));

            $webpPath = $owner->dir().'/'.$owner->id().'/'.Str::random(40).'.webp';
            $image = $manager->read($contents);
            $tmpPath = tempnam(sys_get_temp_dir(), 'webp');
            $image->toWebp(80)->save($tmpPath);
            Storage::disk('s3_private')->put($webpPath, file_get_contents($tmpPath));
            @unlink($tmpPath);
        } catch (\Exception $e) {
            Log::error('メディアライブラリからの写真登録エラー (reservation='.$reservation->id.', media='.$media->id.'): '.$e->getMessage());

            return redirect()->route('admin.reservations.show', $reservation)
                ->with('error', '画像の取得・変換に失敗しました。');
        }

        CustomerPhoto::create($owner->attrs() + [
            'photo_type_id' => $validated['photo_type_id'],
            'file_path' => $webpPath,
            'storage_disk' => 's3',
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('success', 'メディアライブラリから写真を追加しました。');
    }

    /**
     * 写真を削除
     */
    public function destroy(EventReservation $reservation, CustomerPhoto $photo)
    {
        $owner = PhotoOwner::forReservation($reservation);
        if (! $owner->ownsPhoto($photo)) {
            abort(404);
        }

        $this->photoStorage->deletePhotoFile($photo->file_path, $photo->storage_disk);
        $photo->delete();

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('success', '写真を削除しました。');
    }
}
