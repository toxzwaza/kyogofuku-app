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
use App\Http\Controllers\Admin\TimeslotTemplateController as AdminTimeslotTemplateController;
use App\Http\Controllers\Admin\ShopController as AdminShopController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VenueController as AdminVenueController;
use App\Http\Controllers\Admin\ScheduleController as AdminScheduleController;
use App\Http\Controllers\Admin\PhotoSlotController as AdminPhotoSlotController;
use App\Http\Controllers\Admin\PhotoStudioController as AdminPhotoStudioController;
use App\Http\Controllers\Admin\CustomerTagController as AdminCustomerTagController;
use App\Http\Controllers\Admin\ConstraintTemplateController as AdminConstraintTemplateController;
use App\Http\Controllers\Admin\SlideshowController as AdminSlideshowController;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\LineTestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\SesInboundMailController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SesTestController;
use App\Http\Controllers\S3TestController;
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
Route::get('/debug-gd', function () {
    return [
        'php_version' => PHP_VERSION,
        'gd_loaded' => extension_loaded('gd'),
        'gd_info' => function_exists('gd_info') ? gd_info() : null,
    ];
});

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/test', [TestController::class, 'index'])->name('test');

Route::get('/s3-test', [S3TestController::class, 'index'])->name('s3-test.index');
Route::post('/s3-test', [S3TestController::class, 'store'])->name('s3-test.store');
Route::get('/s3-test/signed-url', [S3TestController::class, 'signedUrl'])->name('s3-test.signed-url');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
Route::get('/dashboard/recent-reservations', [DashboardController::class, 'recentReservations'])->middleware(['auth', 'verified'])->name('dashboard.recent-reservations');
Route::get('/dashboard/recent-notes', [DashboardController::class, 'recentNotes'])->middleware(['auth', 'verified'])->name('dashboard.recent-notes');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // Google カレンダー OAuth
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

// Public Routes
Route::get('/event/{slug}', [EventController::class, 'show'])->name('event.show');
Route::post('/event/{event}/reserve', [EventReservationController::class, 'store'])->name('event.reserve');
Route::get('/event/{event}/reserve/success/{text?}', [EventReservationController::class, 'success'])->name('event.reserve.success');
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
    Route::post('/events/{event}/generate-url', [AdminEventController::class, 'generateUrl'])->name('events.generate-url');
    
    // 前撮り管理
    Route::get('/photo-slots', [AdminPhotoSlotController::class, 'index'])->name('photo-slots.index');
    Route::get('/photo-slots/create', [AdminPhotoSlotController::class, 'create'])->name('photo-slots.create');
    Route::post('/photo-slots', [AdminPhotoSlotController::class, 'store'])->name('photo-slots.store');
    Route::put('/photo-slots/bulk-update', [AdminPhotoSlotController::class, 'bulkUpdate'])->name('photo-slots.bulk-update');
    Route::post('/photo-slots/create-schedule', [AdminPhotoSlotController::class, 'createSchedule'])->name('photo-slots.create-schedule');
    Route::post('/photo-slots/create-slot-schedule', [AdminPhotoSlotController::class, 'createSlotSchedule'])->name('photo-slots.create-slot-schedule');
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
    Route::post('/events/{event}/images/bulk-destroy', [AdminEventImageController::class, 'destroyBulk'])->name('events.images.bulk-destroy');
    Route::post('/events/{event}/images/{image}/convert-webp', [AdminEventImageController::class, 'convertToWebp'])->name('events.images.convert-webp');
    Route::post('/events/{event}/images/sort', [AdminEventImageController::class, 'updateSortOrder'])->name('events.images.sort');
    Route::post('/events/{event}/slideshow-positions', [AdminEventImageController::class, 'updateSlideshowPositions'])->name('events.slideshow-positions.update');
    
    // スライドショー管理
    Route::get('/slideshows', [AdminSlideshowController::class, 'index'])->name('slideshows.index');
    Route::get('/slideshows/create', [AdminSlideshowController::class, 'create'])->name('slideshows.create');
    Route::post('/slideshows', [AdminSlideshowController::class, 'store'])->name('slideshows.store');
    Route::get('/slideshows/{slideshow}', [AdminSlideshowController::class, 'show'])->name('slideshows.show');
    Route::put('/slideshows/{slideshow}', [AdminSlideshowController::class, 'update'])->name('slideshows.update');
    Route::delete('/slideshows/{slideshow}', [AdminSlideshowController::class, 'destroy'])->name('slideshows.destroy');
    Route::post('/slideshows/{slideshow}/images', [AdminSlideshowController::class, 'storeImage'])->name('slideshows.images.store');
    Route::post('/slideshows/{slideshow}/images/{image}/migrate-to-s3', [AdminSlideshowController::class, 'migrateSlideshowImageToS3'])->name('slideshows.images.migrate-to-s3');
    Route::post('/slideshows/{slideshow}/images/bulk-destroy', [AdminSlideshowController::class, 'destroyBulk'])->name('slideshows.images.bulk-destroy');
    Route::delete('/slideshow-images/{image}', [AdminSlideshowController::class, 'destroyImage'])->name('slideshow-images.destroy');
    Route::post('/slideshows/{slideshow}/images/sort', [AdminSlideshowController::class, 'updateImageSortOrder'])->name('slideshows.images.sort');
    
    // 予約管理
    Route::get('/events/{event}/reservations', [AdminReservationController::class, 'index'])->name('events.reservations.index');
    Route::post('/events/{event}/reservations/store-from-customer', [AdminReservationController::class, 'storeFromCustomer'])->name('events.reservations.store-from-customer');
    Route::get('/reservations/{reservation}', [AdminReservationController::class, 'show'])->name('reservations.show');
    Route::get('/reservations/{reservation}/edit', [AdminReservationController::class, 'edit'])->name('reservations.edit');
    Route::put('/reservations/{reservation}', [AdminReservationController::class, 'update'])->name('reservations.update');
    Route::delete('/reservations/{reservation}', [AdminReservationController::class, 'destroy'])->name('reservations.destroy');
    Route::delete('/reservations/{reservation}/force', [AdminReservationController::class, 'forceDestroy'])->name('reservations.force-destroy');
    Route::patch('/reservations/{reservation}/restore', [AdminReservationController::class, 'restore'])->name('reservations.restore');
    
    // 予約メモ管理
    Route::post('/reservations/{reservation}/notes', [AdminReservationController::class, 'storeNote'])->name('reservations.notes.store');
    Route::delete('/reservations/notes/{note}', [AdminReservationController::class, 'destroyNote'])->name('reservations.notes.destroy');
    
    // 予約ステータス管理
    Route::patch('/reservations/{reservation}/status', [AdminReservationController::class, 'updateStatus'])->name('reservations.status.update');
    
    // 予約スケジュール管理
    Route::post('/reservations/{reservation}/schedule', [AdminReservationController::class, 'addToSchedule'])->name('reservations.schedule.add');
    Route::delete('/reservations/{reservation}/schedule', [AdminReservationController::class, 'removeFromSchedule'])->name('reservations.schedule.remove');
    Route::patch('/reservations/{reservation}/customer', [AdminReservationController::class, 'linkCustomer'])->name('reservations.customer.link');
    Route::delete('/reservations/{reservation}/customer', [AdminReservationController::class, 'unlinkCustomer'])->name('reservations.customer.unlink');
    
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
    
    // 予約枠テンプレート管理
    Route::get('/timeslot-templates', [AdminTimeslotTemplateController::class, 'index'])->name('timeslot-templates.index');
    Route::get('/timeslot-templates/create', [AdminTimeslotTemplateController::class, 'create'])->name('timeslot-templates.create');
    Route::post('/timeslot-templates', [AdminTimeslotTemplateController::class, 'store'])->name('timeslot-templates.store');
    Route::get('/timeslot-templates/{timeslotTemplate}/edit', [AdminTimeslotTemplateController::class, 'edit'])->name('timeslot-templates.edit');
    Route::put('/timeslot-templates/{timeslotTemplate}', [AdminTimeslotTemplateController::class, 'update'])->name('timeslot-templates.update');
    Route::delete('/timeslot-templates/{timeslotTemplate}', [AdminTimeslotTemplateController::class, 'destroy'])->name('timeslot-templates.destroy');
    
    // 店舗管理
    Route::get('/shops', [AdminShopController::class, 'index'])->name('shops.index');
    Route::get('/shops/create', [AdminShopController::class, 'create'])->name('shops.create');
    Route::post('/shops', [AdminShopController::class, 'store'])->name('shops.store');
    Route::get('/shops/{shop}/edit', [AdminShopController::class, 'edit'])->name('shops.edit');
    Route::put('/shops/{shop}', [AdminShopController::class, 'update'])->name('shops.update');
    Route::delete('/shops/{shop}', [AdminShopController::class, 'destroy'])->name('shops.destroy');
    
    // 顧客管理
    Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/search', [AdminCustomerController::class, 'search'])->name('customers.search');
    Route::post('/customers', [AdminCustomerController::class, 'store'])->name('customers.store');
    Route::post('/customers/{customer}/notes', [AdminCustomerController::class, 'storeNote'])->name('customers.notes.store');
    Route::delete('/customers/notes/{note}', [AdminCustomerController::class, 'destroyNote'])->name('customers.notes.destroy');
    Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/additional-info', [AdminCustomerController::class, 'additionalInfoForm'])->name('customers.additional-info');
    Route::put('/customers/{customer}/additional-info', [AdminCustomerController::class, 'storeAdditionalInfo'])->name('customers.additional-info.store');
    Route::get('/customers/{customer}/additional-info/thanks', [AdminCustomerController::class, 'additionalInfoThanks'])->name('customers.additional-info.thanks');
    Route::put('/customers/{customer}', [AdminCustomerController::class, 'update'])->name('customers.update');
    Route::post('/customers/{customer}/contracts', [AdminCustomerController::class, 'storeContract'])->name('customers.contracts.store');
    Route::put('/customers/{customer}/contracts/{contract}', [AdminCustomerController::class, 'updateContract'])->name('customers.contracts.update');
    Route::post('/customers/{customer}/photo-slots', [AdminCustomerController::class, 'storePhotoSlot'])->name('customers.photo-slots.store');
    Route::put('/customers/{customer}/photo-slots/{photoSlot}', [AdminCustomerController::class, 'updatePhotoSlot'])->name('customers.photo-slots.update');
    Route::post('/customers/{customer}/photos', [AdminCustomerController::class, 'storeCustomerPhoto'])->name('customers.photos.store');
    Route::post('/customers/{customer}/photos/{photo}/migrate-to-s3', [AdminCustomerController::class, 'migrateCustomerPhotoToS3'])->name('customers.photos.migrate-to-s3');
    Route::delete('/customers/{customer}/photos/{photo}', [AdminCustomerController::class, 'destroyCustomerPhoto'])->name('customers.photos.destroy');
    Route::post('/customers/{customer}/tags', [AdminCustomerController::class, 'attachTag'])->name('customers.attach-tag');
    Route::delete('/customers/{customer}/tags/{customerTag}', [AdminCustomerController::class, 'detachTag'])->name('customers.detach-tag');
    Route::get('/customers/{customer}/constraints/sign', [AdminCustomerController::class, 'constraintSignForm'])->name('customers.constraints.sign');
    Route::post('/customers/{customer}/constraints', [AdminCustomerController::class, 'storeCustomerConstraint'])->name('customers.constraints.store');
    Route::put('/customers/{customer}/constraints/{customerConstraint}', [AdminCustomerController::class, 'updateCustomerConstraint'])->name('customers.constraints.update');
    Route::delete('/customers/{customer}/constraints/{customerConstraint}', [AdminCustomerController::class, 'destroyCustomerConstraint'])->name('customers.constraints.destroy');
    Route::delete('/customers/{customer}', [AdminCustomerController::class, 'destroy'])->name('customers.destroy');
    
    // 制約テンプレート管理
    Route::get('/constraint-templates', [AdminConstraintTemplateController::class, 'index'])->name('constraint-templates.index');
    Route::get('/constraint-templates/create', [AdminConstraintTemplateController::class, 'create'])->name('constraint-templates.create');
    Route::post('/constraint-templates', [AdminConstraintTemplateController::class, 'store'])->name('constraint-templates.store');
    Route::match(['get', 'post'], '/constraint-templates/preview', [AdminConstraintTemplateController::class, 'preview'])->name('constraint-templates.preview');
    Route::get('/constraint-templates/{constraintTemplate}/edit', [AdminConstraintTemplateController::class, 'edit'])->name('constraint-templates.edit');
    Route::put('/constraint-templates/{constraintTemplate}', [AdminConstraintTemplateController::class, 'update'])->name('constraint-templates.update');
    Route::delete('/constraint-templates/{constraintTemplate}', [AdminConstraintTemplateController::class, 'destroy'])->name('constraint-templates.destroy');
    
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
    Route::post('/venues/{venue}/migrate-image-to-s3', [AdminVenueController::class, 'migrateVenueImageToS3'])->name('venues.migrate-image-to-s3');
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
