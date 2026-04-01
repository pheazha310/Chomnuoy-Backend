<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AbaPayWayController;
use App\Http\Controllers\Api\AdminProfileController;
use App\Http\Controllers\Api\AuthControllerRegister;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CampaignImageController;
use App\Http\Controllers\Api\CampaignUpdateController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\DonationStatusHistoryController;
use App\Http\Controllers\Api\MaterialItemController;
use App\Http\Controllers\Api\MaterialPickupController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OrganizationDocumentController;
use App\Http\Controllers\Api\OrganizationHistoryController;
use App\Http\Controllers\Api\OrganizationVerificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserCredentialController;
use App\Http\Controllers\Api\UserHistoryController;
use App\Http\Controllers\Api\UserRoleController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthControllerRegister::class, 'register']);
Route::post('/auth/login', [AuthControllerRegister::class, 'login']);
Route::post('/auth/change-password', [AuthControllerRegister::class, 'changePassword']);
Route::get('/auth/providers/status', [SocialAuthController::class, 'status']);
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);
Route::get('/health', function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'service' => 'chomnuoy-backend',
    ]);
});

Route::get('/users/by-email', [UserController::class, 'findByEmail']);
Route::apiResource('users', UserController::class);
Route::post('/users/{id}/last-seen', [UserController::class, 'updateLastSeen']);
Route::apiResource('roles', RoleController::class);
Route::apiResource('user_roles', UserRoleController::class);
Route::apiResource('user_credentials', UserCredentialController::class);
Route::apiResource('user_history', UserHistoryController::class);
Route::get('/organizations/by-email', [OrganizationController::class, 'findByEmail']);
Route::apiResource('organizations', OrganizationController::class);
Route::apiResource('organization_verifications', OrganizationVerificationController::class);
Route::apiResource('organization_history', OrganizationHistoryController::class);
Route::apiResource('organization_document', OrganizationDocumentController::class);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('donations', DonationController::class);
Route::apiResource('donation_status_history', DonationStatusHistoryController::class);
Route::apiResource('material_items', MaterialItemController::class);
Route::apiResource('material_pickups', MaterialPickupController::class);
Route::apiResource('payment_methods', PaymentMethodController::class);
Route::apiResource('payments', PaymentController::class);
Route::apiResource('reviews', ReviewController::class);
Route::get('notifications/stream', [NotificationController::class, 'stream']);
Route::apiResource('notifications', NotificationController::class);
Route::apiResource('audit_logs', AuditLogController::class);
Route::get('report/admin-dashboard', [ReportController::class, 'adminDashboard']);
Route::apiResource('report', ReportController::class);
Route::get('profile/me', [ProfileController::class, 'me']);
Route::post('profile/me', [ProfileController::class, 'updateMe']);
Route::get('profile/{user}', [ProfileController::class, 'show']);
Route::post('profile/{user}', [ProfileController::class, 'update']);
Route::post('profile/{user}/password', [ProfileController::class, 'updatePassword']);
Route::post('profile/{user}/avatar', [ProfileController::class, 'uploadAvatar']);
Route::post('profile/{user}/activities', [ProfileController::class, 'addActivity']);
Route::delete('profile/{user}/activities/{activityId}', [ProfileController::class, 'deleteActivity']);
Route::get('admin/profile/{user}', [AdminProfileController::class, 'show']);
Route::post('admin/profile/{user}', [AdminProfileController::class, 'update']);
Route::post('admin/profile/{user}/password', [AdminProfileController::class, 'updatePassword']);
Route::apiResource('campaigns', CampaignController::class); 
Route::get('campaigns/{campaign}/donations', [CampaignController::class, 'donations']);
Route::get('campaigns/{campaign}/velocity', [CampaignController::class, 'velocity']);
Route::apiResource('campaign_image', CampaignImageController::class);
Route::apiResource('campaign_update', CampaignUpdateController::class);


Route::post('/payment/generate', [PaymentController::class, 'generateQR']);
Route::post('/payment/check', [PaymentController::class, 'checkPayment']);
Route::post('/payment/status', [PaymentController::class, 'getPaymentStatus']);
Route::post('/payment/verify', [PaymentController::class, 'verifyQR']);
Route::post('/payment/decode', [PaymentController::class, 'decodeQR']);
Route::post('/payment/deep-link', [PaymentController::class, 'generateDeepLink']);
