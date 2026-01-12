<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentImage;
use App\Models\Event;
use App\Models\Shop;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EventController extends Controller
{
    /**
     * イベント一覧を表示
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $currentUserShops = $currentUser->shops()->withPivot('main')->get();
        
        // デフォルト店舗を取得（メイン店舗、なければ最初の店舗）
        $defaultShop = $currentUserShops->firstWhere('pivot.main', true) ?? $currentUserShops->first();
        $defaultShopId = $defaultShop ? $defaultShop->id : null;
        
        $query = Event::with(['shops']);

        // フォーム種別でフィルタリング
        if ($request->filled('form_type')) {
            $query->where('form_type', $request->form_type);
        }

        // 店舗でフィルタリング（デフォルトはログインユーザーのメイン店舗）
        $shopId = $request->filled('shop_id') ? $request->shop_id : $defaultShopId;
        if ($shopId) {
            $query->whereHas('shops', function($q) use ($shopId) {
                $q->where('shops.id', $shopId);
            });
        }

        // 公開状態でフィルタリング（デフォルトは公開中）
        $publicStatus = $request->filled('public_status') ? $request->public_status : 'active';
        $today = now()->startOfDay();
        
        if ($publicStatus === 'active') {
            // 公開中：is_public = 1 かつ (end_atがnull または end_at >= 今日)
            $query->where('is_public', true)
                ->where(function($q) use ($today) {
                    $q->whereNull('end_at')
                      ->orWhere('end_at', '>=', $today);
                });
        } elseif ($publicStatus === 'ended') {
            // 受付終了：is_public = 1 かつ end_at < 今日
            $query->where('is_public', true)
                ->whereNotNull('end_at')
                ->where('end_at', '<', $today);
        } elseif ($publicStatus === 'private') {
            // 非公開：is_public = 0
            $query->where('is_public', false);
        }
        // 'all'の場合はフィルタリングしない

        $events = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $shops = Shop::where('is_active', true)->get();

        return Inertia::render('Admin/Event/Index', [
            'events' => $events,
            'shops' => $shops,
            'filters' => [
                'form_type' => $request->form_type ?? '',
                'shop_id' => $shopId,
                'public_status' => $publicStatus,
            ],
        ]);
    }

    /**
     * イベント追加フォームを表示
     */
    public function create()
    {
        $shops = Shop::where('is_active', true)->get();
        $venues = Venue::where('is_active', true)->get();
        $documents = Document::orderBy('created_at', 'desc')->get();

        return Inertia::render('Admin/Event/Create', [
            'shops' => $shops,
            'venues' => $venues,
            'documents' => $documents,
        ]);
    }

    /**
     * イベントを保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|regex:/^[a-zA-Z0-9_-]+$/|unique:events,slug',
            'description' => 'nullable|string',
            'form_type' => 'required|in:reservation,document,contact',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'is_public' => 'boolean',
            'gtm_id' => 'nullable|string|max:255',
            'success_text' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9_-]+$/',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
            'venue_ids' => 'nullable|array',
            'venue_ids.*' => 'exists:venues,id',
            'new_venue_name' => 'nullable|string|max:255',
            'new_venue_description' => 'nullable|string',
            'new_venue_address' => 'nullable|string|max:255',
            'new_venue_phone' => 'nullable|string|max:255',
            'document_ids' => 'nullable|array',
            'document_ids.*' => 'exists:documents,id',
        ]);

        // date型なのでそのまま使用（datetime-localの場合はT以降を削除）
        if ($request->has('start_at') && $request->start_at) {
            $validated['start_at'] = explode('T', $request->start_at)[0];
        }
        if ($request->has('end_at') && $request->end_at) {
            $validated['end_at'] = explode('T', $request->end_at)[0];
        }

        // slugが重複している場合は、自動的に番号を追加
        $slug = $validated['slug'];
        $counter = 1;
        while (Event::where('slug', $slug)->exists()) {
            $slug = $validated['slug'] . '-' . $counter;
            $counter++;
        }
        $validated['slug'] = $slug;

        $validated['is_public'] = $request->has('is_public') ? $request->is_public : true;

        $event = Event::create($validated);

        // 店舗を関連付け
        if ($request->filled('shop_ids') && !empty($request->shop_ids)) {
            $event->shops()->attach($request->shop_ids);
        }

        // 予約フォームの場合、会場を関連付け
        if ($event->form_type === 'reservation') {
            // 既存の会場を関連付け
            if ($request->has('venue_ids')) {
                $event->venues()->attach($request->venue_ids);
            }
            
            // 新規会場を作成して関連付け
            if ($request->has('new_venue_name') && $request->new_venue_name) {
                $venue = Venue::create([
                    'name' => $request->new_venue_name,
                    'description' => $request->new_venue_description,
                    'address' => $request->new_venue_address,
                    'phone' => $request->new_venue_phone,
                    'is_active' => true,
                ]);
                $event->venues()->attach($venue->id);
            }
        }

        // 資料請求フォームの場合、資料を関連付け
        if ($event->form_type === 'document' && $request->has('document_ids')) {
            $event->documents()->attach($request->document_ids);
        }

        return redirect()->route('admin.events.index')
            ->with('success', 'イベントを追加しました。');
    }

    /**
     * イベント詳細を表示
     */
    public function show(Event $event)
    {
        $event->load(['shops', 'images', 'timeslots', 'reservations', 'venues', 'documents']);
        $allVenues = Venue::where('is_active', true)->get();
        $allShops = Shop::where('is_active', true)->get();
        $allDocuments = Document::orderBy('created_at', 'desc')->get();

        return Inertia::render('Admin/Event/Show', [
            'event' => $event,
            'allVenues' => $allVenues,
            'allShops' => $allShops,
            'allDocuments' => $allDocuments,
        ]);
    }

    /**
     * イベントを更新
     */
    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_type' => 'required|in:reservation,document,contact',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'is_public' => 'boolean',
            'gtm_id' => 'nullable|string|max:255',
            'success_text' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9_-]+$/',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
            'document_ids' => 'nullable|array',
            'document_ids.*' => 'exists:documents,id',
        ]);

        // date型なのでそのまま使用（datetime-localの場合はT以降を削除）
        if ($request->has('start_at') && $request->start_at) {
            $validated['start_at'] = explode('T', $request->start_at)[0];
        }
        if ($request->has('end_at') && $request->end_at) {
            $validated['end_at'] = explode('T', $request->end_at)[0];
        }

        // slugを更新（タイトルが変更された場合のみ）
        if ($event->title !== $validated['title']) {
            $baseSlug = Str::slug($validated['title']);
            $slug = $baseSlug;
            $counter = 1;
            while (Event::where('slug', $slug)->where('id', '!=', $event->id)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        $validated['is_public'] = $request->has('is_public') ? $request->is_public : true;

        $event->update($validated);

        // 店舗の関連付けを更新
        if ($request->has('shop_ids')) {
            $event->shops()->sync($request->shop_ids);
        } else {
            $event->shops()->detach();
        }

        // 資料請求フォームの場合、資料の関連付けを更新
        if ($event->form_type === 'document') {
            if ($request->has('document_ids')) {
                $event->documents()->sync($request->document_ids);
            } else {
                $event->documents()->detach();
            }
        } else {
            // フォームタイプが変更された場合、資料の関連付けを削除
            $event->documents()->detach();
        }

        return redirect()->route('admin.events.show', $event->id)
            ->with('success', 'イベントを更新しました。');
    }

    /**
     * 資料を作成
     */
    public function storeDocument(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'pdf_file' => 'required|file|mimes:pdf|max:10240',
            'thumbnail_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // PDFファイルを保存
        $pdfPath = $request->file('pdf_file')->store('documents', 'public');
        
        // サムネイル画像を保存（オプション）
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail_file')) {
            $thumbnailPath = $request->file('thumbnail_file')->store('documents/thumbnails', 'public');
        }

        $document = Document::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'pdf_path' => $pdfPath,
            'thumbnail_path' => $thumbnailPath,
        ]);

        return response()->json([
            'success' => true,
            'document' => $document->load(['events']),
        ]);
    }

    /**
     * 資料の画像順序を更新
     */
    public function updateDocumentImageOrder(Request $request, Document $document)
    {
        $validated = $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'exists:document_images,id',
        ]);

        foreach ($validated['image_ids'] as $index => $imageId) {
            DocumentImage::where('id', $imageId)
                ->where('document_id', $document->id)
                ->update(['sort_order' => $index]);
        }

        return response()->json([
            'success' => true,
            'document' => $document->load(['events', 'images']),
        ]);
    }

    /**
     * 資料を更新
     */
    public function updateDocument(Request $request, Document $document)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'pdf_file' => 'nullable|file|mimes:pdf|max:10240',
            'thumbnail_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ];

        // PDFファイルを更新（オプション）
        if ($request->hasFile('pdf_file')) {
            // 古いPDFを削除
            if ($document->pdf_path && Storage::disk('public')->exists($document->pdf_path)) {
                Storage::disk('public')->delete($document->pdf_path);
            }
            $updateData['pdf_path'] = $request->file('pdf_file')->store('documents', 'public');
        }

        // サムネイル画像を更新（オプション）
        if ($request->hasFile('thumbnail_file')) {
            // 古いサムネイルを削除
            if ($document->thumbnail_path && Storage::disk('public')->exists($document->thumbnail_path)) {
                Storage::disk('public')->delete($document->thumbnail_path);
            }
            $updateData['thumbnail_path'] = $request->file('thumbnail_file')->store('documents/thumbnails', 'public');
        } elseif ($request->has('remove_thumbnail')) {
            // サムネイル削除リクエスト
            if ($document->thumbnail_path && Storage::disk('public')->exists($document->thumbnail_path)) {
                Storage::disk('public')->delete($document->thumbnail_path);
            }
            $updateData['thumbnail_path'] = null;
        }

        $document->update($updateData);

        return response()->json([
            'success' => true,
            'document' => $document->load(['events']),
        ]);
    }

    /**
     * 資料を削除
     */
    public function destroyDocument(Document $document)
    {
        // 古いPDFを削除
        if ($document->pdf_path && Storage::disk('public')->exists($document->pdf_path)) {
            Storage::disk('public')->delete($document->pdf_path);
        }

        // 古いサムネイルを削除
        if ($document->thumbnail_path && Storage::disk('public')->exists($document->thumbnail_path)) {
            Storage::disk('public')->delete($document->thumbnail_path);
        }

        $document->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * 資料一覧を取得
     */
    public function getDocuments()
    {
        $documents = Document::orderBy('created_at', 'desc')->get();
        return response()->json($documents);
    }

    /**
     * 資料を表示（turn.js用）
     */
    public function showDocument(Document $document)
    {
        $document->load('images');
        return Inertia::render('Document/Show', [
            'document' => $document,
        ]);
    }

    /**
     * 資料の画像一覧を取得（API）
     */
    public function getDocumentImages(Document $document)
    {
        $images = $document->images()->orderBy('sort_order')->get()->map(function ($image) {
            return [
                'id' => $image->id,
                'image_path' => $image->image_path,
                'image_url' => $image->image_url,
                'sort_order' => $image->sort_order,
            ];
        });
        return response()->json($images);
    }
}

