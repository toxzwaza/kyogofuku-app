<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventReservationController;
use App\Http\Controllers\PostalCodeController;
use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\EventImageController as AdminEventImageController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\TimeslotController as AdminTimeslotController;
use App\Http\Controllers\Admin\ShopController as AdminShopController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VenueController as AdminVenueController;
use App\Http\Controllers\Admin\ScheduleController as AdminScheduleController;
use App\Http\Controllers\Admin\PhotoSlotController as AdminPhotoSlotController;
use App\Http\Controllers\Admin\PhotoStudioController as AdminPhotoStudioController;
use App\Http\Controllers\Admin\CustomerTagController as AdminCustomerTagController;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\LineTestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SesInboundMailController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SesTestController;
use App\Http\Controllers\TestController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/test', [TestController::class, 'index'])->name('test');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Public Routes
Route::get('/event/{slug}', [EventController::class, 'show'])->name('event.show');
Route::post('/event/{event}/reserve', [EventReservationController::class, 'store'])->name('event.reserve');
Route::get('/event/{event}/reserve/success', [EventReservationController::class, 'success'])->name('event.reserve.success');
Route::get('/api/postal-code/search', [PostalCodeController::class, 'search'])->name('api.postal-code.search');

// 資料表示
Route::get('/document/{document}', [AdminEventController::class, 'showDocument'])->name('document.show');
Route::get('/api/document/{document}/images', [AdminEventController::class, 'getDocumentImages'])->name('document.images');

// Admin Routes
Route::prefix('admin')->middleware(['auth', 'verified'])->name('admin.')->group(function () {
    // イベント一覧
    Route::get('/events', [AdminEventController::class, 'index'])->name('events.index');
    Route::get('/events/create', [AdminEventController::class, 'create'])->name('events.create');
    Route::post('/events', [AdminEventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [AdminEventController::class, 'show'])->name('events.show');
    Route::put('/events/{event}', [AdminEventController::class, 'update'])->name('events.update');
    
    // 前撮り管理
    Route::get('/photo-slots', [AdminPhotoSlotController::class, 'index'])->name('photo-slots.index');
    Route::get('/photo-slots/create', [AdminPhotoSlotController::class, 'create'])->name('photo-slots.create');
    Route::post('/photo-slots', [AdminPhotoSlotController::class, 'store'])->name('photo-slots.store');
    Route::put('/photo-slots/bulk-update', [AdminPhotoSlotController::class, 'bulkUpdate'])->name('photo-slots.bulk-update');
    Route::post('/photo-slots/create-schedule', [AdminPhotoSlotController::class, 'createSchedule'])->name('photo-slots.create-schedule');
    Route::put('/photo-slots/{photoSlot}', [AdminPhotoSlotController::class, 'update'])->name('photo-slots.update');
    Route::delete('/photo-slots/{photoSlot}', [AdminPhotoSlotController::class, 'destroy'])->name('photo-slots.destroy');
    
    // スタジオ管理
    Route::get('/photo-studios', [AdminPhotoStudioController::class, 'index'])->name('photo-studios.index');
    Route::get('/photo-studios/create', [AdminPhotoStudioController::class, 'create'])->name('photo-studios.create');
    Route::post('/photo-studios', [AdminPhotoStudioController::class, 'store'])->name('photo-studios.store');
    Route::get('/photo-studios/{photoStudio}/edit', [AdminPhotoStudioController::class, 'edit'])->name('photo-studios.edit');
    Route::put('/photo-studios/{photoStudio}', [AdminPhotoStudioController::class, 'update'])->name('photo-studios.update');
    Route::delete('/photo-studios/{photoStudio}', [AdminPhotoStudioController::class, 'destroy'])->name('photo-studios.destroy');
    
    // イベント画像管理
    Route::get('/events/{event}/images', [AdminEventImageController::class, 'index'])->name('events.images.index');
    Route::get('/events/{event}/images/create', [AdminEventImageController::class, 'create'])->name('events.images.create');
    Route::post('/events/{event}/images', [AdminEventImageController::class, 'store'])->name('events.images.store');
    Route::delete('/images/{image}', [AdminEventImageController::class, 'destroy'])->name('images.destroy');
    Route::post('/events/{event}/images/sort', [AdminEventImageController::class, 'updateSortOrder'])->name('events.images.sort');
    
    // 予約管理
    Route::get('/events/{event}/reservations', [AdminReservationController::class, 'index'])->name('events.reservations.index');
    Route::get('/reservations/{reservation}', [AdminReservationController::class, 'show'])->name('reservations.show');
    Route::get('/reservations/{reservation}/edit', [AdminReservationController::class, 'edit'])->name('reservations.edit');
    Route::put('/reservations/{reservation}', [AdminReservationController::class, 'update'])->name('reservations.update');
    Route::delete('/reservations/{reservation}', [AdminReservationController::class, 'destroy'])->name('reservations.destroy');
    
    // 予約メモ管理
    Route::post('/reservations/{reservation}/notes', [AdminReservationController::class, 'storeNote'])->name('reservations.notes.store');
    Route::delete('/reservations/notes/{note}', [AdminReservationController::class, 'destroyNote'])->name('reservations.notes.destroy');
    
    // 予約ステータス管理
    Route::patch('/reservations/{reservation}/status', [AdminReservationController::class, 'updateStatus'])->name('reservations.status.update');
    
    // 予約スケジュール管理
    Route::post('/reservations/{reservation}/schedule', [AdminReservationController::class, 'addToSchedule'])->name('reservations.schedule.add');
    Route::delete('/reservations/{reservation}/schedule', [AdminReservationController::class, 'removeFromSchedule'])->name('reservations.schedule.remove');
    
    // 予約メール返信
    Route::post('/reservations/{reservation}/reply-email', [AdminReservationController::class, 'sendReplyEmail'])->name('reservations.reply-email');
    
    // 予約枠管理
    Route::get('/events/{event}/timeslots', [AdminTimeslotController::class, 'index'])->name('events.timeslots.index');
    Route::get('/events/{event}/timeslots/create', [AdminTimeslotController::class, 'create'])->name('events.timeslots.create');
    Route::post('/events/{event}/timeslots', [AdminTimeslotController::class, 'store'])->name('events.timeslots.store');
    Route::get('/timeslots/{timeslot}/edit', [AdminTimeslotController::class, 'edit'])->name('timeslots.edit');
    Route::put('/timeslots/{timeslot}', [AdminTimeslotController::class, 'update'])->name('timeslots.update');
    Route::post('/timeslots/{timeslot}/adjust-capacity', [AdminTimeslotController::class, 'adjustCapacity'])->name('timeslots.adjust-capacity');
    Route::delete('/timeslots/{timeslot}', [AdminTimeslotController::class, 'destroy'])->name('timeslots.destroy');
    
    // 店舗管理
    Route::get('/shops', [AdminShopController::class, 'index'])->name('shops.index');
    Route::get('/shops/create', [AdminShopController::class, 'create'])->name('shops.create');
    Route::post('/shops', [AdminShopController::class, 'store'])->name('shops.store');
    Route::get('/shops/{shop}/edit', [AdminShopController::class, 'edit'])->name('shops.edit');
    Route::put('/shops/{shop}', [AdminShopController::class, 'update'])->name('shops.update');
    Route::delete('/shops/{shop}', [AdminShopController::class, 'destroy'])->name('shops.destroy');
    
    // 顧客管理
    Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [AdminCustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::put('/customers/{customer}', [AdminCustomerController::class, 'update'])->name('customers.update');
    Route::post('/customers/{customer}/contracts', [AdminCustomerController::class, 'storeContract'])->name('customers.contracts.store');
    Route::post('/customers/{customer}/photo-slots', [AdminCustomerController::class, 'storePhotoSlot'])->name('customers.photo-slots.store');
    Route::post('/customers/{customer}/photos', [AdminCustomerController::class, 'storeCustomerPhoto'])->name('customers.photos.store');
    Route::post('/customers/{customer}/tags', [AdminCustomerController::class, 'attachTag'])->name('customers.attach-tag');
    Route::delete('/customers/{customer}/tags/{customerTag}', [AdminCustomerController::class, 'detachTag'])->name('customers.detach-tag');
    Route::delete('/customers/{customer}', [AdminCustomerController::class, 'destroy'])->name('customers.destroy');
    
    // 顧客タグ管理
    Route::get('/customer-tags', [AdminCustomerTagController::class, 'index'])->name('customer-tags.index');
    Route::get('/customer-tags/create', [AdminCustomerTagController::class, 'create'])->name('customer-tags.create');
    Route::post('/customer-tags', [AdminCustomerTagController::class, 'store'])->name('customer-tags.store');
    Route::get('/customer-tags/{customerTag}', [AdminCustomerTagController::class, 'show'])->name('customer-tags.show');
    Route::get('/customer-tags/{customerTag}/edit', [AdminCustomerTagController::class, 'edit'])->name('customer-tags.edit');
    Route::put('/customer-tags/{customerTag}', [AdminCustomerTagController::class, 'update'])->name('customer-tags.update');
    Route::delete('/customer-tags/{customerTag}', [AdminCustomerTagController::class, 'destroy'])->name('customer-tags.destroy');
    
    // スタッフ管理
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    
    // ログ管理
    Route::get('/activity-logs', [AdminActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/activity-logs/{activityLog}', [AdminActivityLogController::class, 'show'])->name('activity-logs.show');
    Route::delete('/activity-logs/unblock/{ipAddress}', [AdminActivityLogController::class, 'unblockIp'])
        ->where('ipAddress', '.*')
        ->name('activity-logs.unblock');
    
    // 会場管理
    Route::get('/venues', [AdminVenueController::class, 'index'])->name('venues.index');
    Route::get('/venues/create', [AdminVenueController::class, 'create'])->name('venues.create');
    Route::post('/venues', [AdminVenueController::class, 'store'])->name('venues.store');
    Route::get('/venues/{venue}', [AdminVenueController::class, 'show'])->name('venues.show');
    Route::get('/venues/{venue}/edit', [AdminVenueController::class, 'edit'])->name('venues.edit');
    Route::put('/venues/{venue}', [AdminVenueController::class, 'update'])->name('venues.update');
    Route::delete('/venues/{venue}', [AdminVenueController::class, 'destroy'])->name('venues.destroy');
    
    // 会場管理（イベント単位）
    Route::post('/events/{event}/venues', [AdminVenueController::class, 'storeForEvent'])->name('events.venues.store');
    Route::delete('/events/{event}/venues/{venue}', [AdminVenueController::class, 'destroyFromEvent'])->name('events.venues.destroy');
    
    // スケジュール管理
    Route::get('/schedules', [AdminScheduleController::class, 'show'])->name('schedules.show');
    Route::get('/api/schedules', [AdminScheduleController::class, 'index'])->name('schedules.index');
    Route::get('/api/schedules/shop-users', [AdminScheduleController::class, 'getShopUsers'])->name('schedules.shop-users');
    Route::post('/api/schedules', [AdminScheduleController::class, 'store'])->name('schedules.store');
    Route::put('/api/schedules/{schedule}', [AdminScheduleController::class, 'update'])->name('schedules.update');
    Route::patch('/api/schedules/{schedule}/expense-category', [AdminScheduleController::class, 'updateExpenseCategory'])->name('schedules.update-expense-category');
    Route::post('/api/schedules/predict-expense-category', [AdminScheduleController::class, 'predictExpenseCategory'])->name('schedules.predict-expense-category');
    Route::delete('/api/schedules/{schedule}', [AdminScheduleController::class, 'destroy'])->name('schedules.destroy');
    
    // 資料管理
    Route::post('/documents', [AdminEventController::class, 'storeDocument'])->name('documents.store');
    Route::put('/documents/{document}', [AdminEventController::class, 'updateDocument'])->name('documents.update');
    Route::delete('/documents/{document}', [AdminEventController::class, 'destroyDocument'])->name('documents.destroy');
    Route::get('/api/documents', [AdminEventController::class, 'getDocuments'])->name('documents.index');
    Route::put('/documents/{document}/image-order', [AdminEventController::class, 'updateDocumentImageOrder'])->name('documents.image-order');
});


Route::post('/webhook/line', [LineWebhookController::class, 'handle']);

Route::get('/test-line', function () {
    app()->call('App\Http\Controllers\LineTestController@test');
});

// Amazon SES テスト
Route::get('/test-ses-mail', [SesTestController::class, 'send']);

// SES受信メール　API
Route::post('/inbound-mail', [SesInboundMailController::class, 'handle']);


require __DIR__.'/auth.php';
