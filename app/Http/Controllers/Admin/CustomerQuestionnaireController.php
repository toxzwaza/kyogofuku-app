<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPhoto;
use App\Models\CustomerQuestionnaire;
use App\Models\PhotoType;
use App\Services\CustomerPhotoStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 振袖アンケート用紙（印刷・スキャン取り込み・写真添付欄）
 */
class CustomerQuestionnaireController extends Controller
{
    public function __construct(
        private CustomerPhotoStorageService $photoStorage,
    ) {
    }

    /**
     * アンケート用紙の印刷ページ（A4・2ページ）
     *
     * - mode=form（既定）: 記入用の用紙。?blank=1 で顧客情報プリフィルなし
     * - mode=scan: 取り込み済みスキャンを印刷（1ページ目=スキャン、2ページ目=写真合成済みがあれば優先）
     */
    public function print(Request $request, Customer $customer)
    {
        $questionnaire = $customer->questionnaire;

        $s3Url = function (?string $path) {
            return $path
                ? Storage::disk('s3_private')->temporaryUrl(str_replace('\\', '/', $path), now()->addMinutes(60))
                : null;
        };

        return view('admin.questionnaire.print', [
            'customer' => $customer,
            'mode' => $request->query('mode') === 'scan' ? 'scan' : 'form',
            'blank' => $request->boolean('blank'),
            'page1ScanUrl' => $s3Url($questionnaire?->page1Photo?->file_path),
            'page2ScanUrl' => $s3Url($questionnaire?->page2Photo?->file_path),
            'composedPage2Url' => $s3Url($questionnaire?->composed_page2_path),
        ]);
    }

    /**
     * スキャン画像を登録（ページ単位・差し替え可）
     */
    public function storeScan(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'page' => 'required|integer|in:1,2',
            'photo' => 'required|file|mimes:jpeg,png,jpg|max:10240',
        ]);

        $manager = $this->photoStorage->createImageManager();
        if (! $manager) {
            return back()->with('error', 'WebP変換に必要な画像ドライバー（GD/Imagick）が利用できません。');
        }

        $storedPath = $this->photoStorage->convertUploadToWebpAndPutS3Private(
            $request->file('photo'), (int) $customer->id, $manager
        );
        if (! $storedPath) {
            return back()->with('error', 'スキャン画像の保存に失敗しました。');
        }

        $photoTypeId = PhotoType::where('code', 'questionnaire')->value('id');
        $page = (int) $validated['page'];
        $column = $page === 1 ? 'page1_photo_id' : 'page2_photo_id';

        DB::transaction(function () use ($customer, $photoTypeId, $storedPath, $page, $column) {
            $photo = CustomerPhoto::create([
                'customer_id' => $customer->id,
                'photo_type_id' => $photoTypeId,
                'file_path' => $storedPath,
                'storage_disk' => 's3',
                'remarks' => "振袖アンケート {$page}ページ目",
            ]);

            $questionnaire = CustomerQuestionnaire::firstOrCreate(['customer_id' => $customer->id]);

            // 差し替え時は旧スキャンを削除
            $oldPhotoId = $questionnaire->{$column};

            $updates = [$column => $photo->id];
            if ($page === 2) {
                // 2ページ目を撮り直したら配置・合成はリセット
                if ($questionnaire->composed_page2_path) {
                    $this->photoStorage->deletePhotoFile($questionnaire->composed_page2_path, 's3');
                }
                $updates['placements'] = null;
                $updates['composed_page2_path'] = null;
            }
            $questionnaire->update($updates);

            if ($oldPhotoId) {
                $oldPhoto = CustomerPhoto::find($oldPhotoId);
                if ($oldPhoto) {
                    $this->photoStorage->deletePhotoFile($oldPhoto->file_path, $oldPhoto->storage_disk);
                    $oldPhoto->delete();
                }
            }
        });

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', "アンケート{$page}ページ目を取り込みました。");
    }

    /**
     * 写真添付欄の配置情報と合成画像を保存
     */
    public function updatePlacements(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'placements' => 'nullable|array',
            'placements.*.customer_photo_id' => 'required|integer',
            'placements.*.left' => 'required|numeric',
            'placements.*.top' => 'required|numeric',
            'placements.*.scale' => 'required|numeric',
            'placements.*.angle' => 'required|numeric',
            'composed_image' => 'required|file|mimes:jpeg,png,jpg,webp|max:20480',
        ]);

        $questionnaire = $customer->questionnaire;
        if (! $questionnaire || ! $questionnaire->page2_photo_id) {
            return response()->json(['message' => '先に2ページ目のスキャンを取り込んでください。'], 422);
        }

        // 配置写真がすべて当該顧客のものであることを確認
        $placements = $validated['placements'] ?? [];
        $photoIds = collect($placements)->pluck('customer_photo_id')->unique()->values();
        if ($photoIds->isNotEmpty()) {
            $ownedCount = CustomerPhoto::where('customer_id', $customer->id)
                ->whereIn('id', $photoIds)->count();
            if ($ownedCount !== $photoIds->count()) {
                return response()->json(['message' => '配置できない写真が含まれています。'], 422);
            }
        }

        $storedPath = $this->photoStorage->putContentToS3Private(
            file_get_contents($request->file('composed_image')->getRealPath()),
            (int) $customer->id,
            'jpg'
        );
        if (! $storedPath) {
            return response()->json(['message' => '合成画像の保存に失敗しました。'], 500);
        }

        $oldComposedPath = $questionnaire->composed_page2_path;
        $questionnaire->update([
            'placements' => $placements,
            'composed_page2_path' => $storedPath,
        ]);
        if ($oldComposedPath) {
            $this->photoStorage->deletePhotoFile($oldComposedPath, 's3');
        }

        return response()->json([
            'message' => '写真添付欄を保存しました。',
            'composed_page2_url' => Storage::disk('s3_private')->temporaryUrl(
                str_replace('\\', '/', $storedPath),
                now()->addMinutes(60)
            ),
        ]);
    }

    /**
     * スキャン画像を削除（ページ単位）
     */
    public function destroyScan(Customer $customer, int $page)
    {
        abort_unless(in_array($page, [1, 2], true), 404);

        $questionnaire = $customer->questionnaire;
        $column = $page === 1 ? 'page1_photo_id' : 'page2_photo_id';
        if (! $questionnaire || ! $questionnaire->{$column}) {
            return back()->with('error', '削除対象のスキャンがありません。');
        }

        DB::transaction(function () use ($questionnaire, $column, $page) {
            $photoId = $questionnaire->{$column};

            $updates = [$column => null];
            if ($page === 2) {
                if ($questionnaire->composed_page2_path) {
                    $this->photoStorage->deletePhotoFile($questionnaire->composed_page2_path, 's3');
                }
                $updates['placements'] = null;
                $updates['composed_page2_path'] = null;
            }
            $questionnaire->update($updates);

            $photo = CustomerPhoto::find($photoId);
            if ($photo) {
                $this->photoStorage->deletePhotoFile($photo->file_path, $photo->storage_disk);
                $photo->delete();
            }
        });

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', "アンケート{$page}ページ目のスキャンを削除しました。");
    }
}
